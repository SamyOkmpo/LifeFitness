<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: productos.php"); exit;
}

// Procesar edición masiva (en lote)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_update') {
    $ids = isset($_POST['selected_ids']) ? explode(',', $_POST['selected_ids']) : [];
    $ids = array_filter(array_map('intval', $ids));
    
    if (!empty($ids)) {
        $catVal = $_POST['bulk_categoria'] ?? 'KEEP';
        $brandVal = $_POST['bulk_marca'] ?? 'KEEP';
        $serieMode = $_POST['bulk_serie_mode'] ?? 'KEEP';
        $serieVal = trim($_POST['bulk_serie'] ?? '');
        $codeMode = $_POST['bulk_item_no_mode'] ?? 'KEEP';
        $codeVal = trim($_POST['bulk_item_no'] ?? '');
        
        $updates = [];
        $params = [];
        
        if ($codeMode === 'SET') {
            $updates[] = "item_no = ?";
            $params[] = $codeVal;
        } else if ($codeMode === 'CLEAR') {
            $updates[] = "item_no = ''";
        }
        
        if ($catVal !== 'KEEP') {
            if ($catVal === 'NONE' || $catVal === '') {
                $updates[] = "categoria_id = NULL";
            } else {
                $updates[] = "categoria_id = ?";
                $params[] = intval($catVal);
            }
        }
        
        if ($brandVal !== 'KEEP') {
            if ($brandVal === 'NONE') {
                $updates[] = "series = ''";
            } else {
                $updates[] = "series = ?";
                $params[] = $brandVal;
            }
        }

        if ($serieMode === 'SET') {
            $updates[] = "serie = ?";
            $params[] = $serieVal;
            if ($serieVal !== '') {
                $chk = $pdo->prepare("SELECT id FROM series WHERE name = ?");
                $chk->execute([$serieVal]);
                if (!$chk->fetch()) {
                    $posStmt = $pdo->query("SELECT COALESCE(MAX(pos), 0) + 1 AS next_pos FROM series");
                    $nextPos = $posStmt->fetch()['next_pos'];
                    $ins = $pdo->prepare("INSERT INTO series (name, pos) VALUES (?, ?)");
                    $ins->execute([$serieVal, $nextPos]);
                }
            }
        } else if ($serieMode === 'CLEAR') {
            $updates[] = "serie = NULL";
        }
        
        if (!empty($updates)) {
            $inPlaceholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "UPDATE productos SET " . implode(', ', $updates) . " WHERE id IN ($inPlaceholders)";
            $params = array_merge($params, $ids);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $_SESSION['flash_msg'] = "Se actualizaron exitosamente " . count($ids) . " productos.";
        }
    }
    header("Location: productos.php"); exit;
}


function getProductImgUrl($rawImg, $mediaJson = null) {
    $img = $rawImg ?? '';
    if (empty($img) && !empty($mediaJson)) {
        $media = json_decode($mediaJson, true);
        if (is_array($media) && count($media) > 0 && isset($media[0]['url'])) {
            $img = $media[0]['url'];
        }
    }
    if (!$img) return '';
    if (strpos($img, 'http') === 0) return $img;
    
    $img = ltrim($img, '/');
    if (strpos($img, 'v1/cotizaciones/') === 0) {
        $img = substr($img, strlen('v1/cotizaciones/'));
    } else if (strpos($img, 'cotizaciones/') === 0) {
        $img = substr($img, strlen('cotizaciones/'));
    }
    $img = ltrim($img, '/');
    
    return '../cotizaciones/' . $img;
}

$sql = "SELECT id, name, item_no, series, serie, categoria_id, price, img, media_json FROM productos ORDER BY id DESC ";
$stmt = $pdo->query($sql);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$cats = $pdo->query("SELECT id, name FROM categorias ORDER BY pos ASC")->fetchAll(PDO::FETCH_ASSOC);
$marcas = $pdo->query("SELECT name FROM marcas ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$seriesList = $pdo->query("SELECT name FROM series ORDER BY pos ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
$catMap = [];
foreach ($cats as $c) { $catMap[$c['id']] = $c['name']; }
include 'header.php';
?>

<style>
.products-table tr.selected-row { background: #eef6fc !important; }
.product-card { position: relative; transition: border-color 0.2s, box-shadow 0.2s; }
.product-card.selected-card { border-color: #457b9d !important; box-shadow: 0 0 0 2px #457b9d, 0 6px 16px rgba(69,123,157,0.2) !important; background: #f8fbfe !important; }
.card-checkbox-badge { position: absolute; top: 12px; left: 12px; z-index: 10; background: rgba(255,255,255,0.95); border: 1px solid #ddd; border-radius: 6px; padding: 4px 6px; display: flex; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); cursor: pointer; }
.card-checkbox-badge:hover { background: #fff; border-color: #457b9d; }
@media (max-width: 768px) {
  .products-table td.checkbox-cell { grid-column: 1 / -1; display: flex; align-items: center; gap: 8px; border-bottom: 1px dashed #eee !important; padding: 6px 0 !important; }
}
</style>

<div class="panel-card">
    <?php if (isset($_SESSION['flash_msg'])): ?>
        <div style="background:#e6fffa; color:#234e52; padding:12px 18px; border-radius:8px; margin-bottom:20px; border:1px solid #b2f5ea; display:flex; justify-content:space-between; align-items:center; font-weight:600;">
            <span>✅ <?= htmlspecialchars($_SESSION['flash_msg']) ?></span>
            <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;font-weight:bold;color:#234e52;font-size:16px;">✕</button>
        </div>
        <?php unset($_SESSION['flash_msg']); ?>
    <?php endif; ?>

    <div style="margin-bottom:20px;">
        <h2 style="margin:0 0 16px 0; font-family:'Oswald', sans-serif; text-transform:uppercase; color:#1d3557; font-size:24px; border-bottom:1px solid #eee; padding-bottom:12px;">📦 Catálogo de Equipos</h2>
        
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
            <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            
            <div id="topFilters" style="display:none; gap:12px; align-items:center; flex-wrap:wrap;">
                <select id="catFilterTop" class="form-group" style="margin:0; padding:8px 12px; background:#f7f7f7; border:1px solid #e5e5e5; border-radius:6px; outline:none; font-size:13px;">
                    <option value="">Todas las categorías</option>
                    <option value="NONE">- Sin categoría -</option>
                    <?php foreach ($cats as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="brandFilterTop" class="form-group" style="margin:0; padding:8px 12px; background:#f7f7f7; border:1px solid #e5e5e5; border-radius:6px; outline:none; font-size:13px;">
                    <option value="">Todas las marcas</option>
                    <option value="NONE">- Sin marca -</option>
                    <?php foreach ($marcas as $m): ?>
                        <option value="<?= htmlspecialchars($m['name']) ?>"><?= htmlspecialchars($m['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="serieFilterTop" class="form-group" style="margin:0; padding:8px 12px; background:#f7f7f7; border:1px solid #e5e5e5; border-radius:6px; outline:none; font-size:13px;">
                    <option value="">Todas las series</option>
                    <option value="NONE">- Sin serie -</option>
                    <?php foreach ($seriesList as $s): ?>
                        <option value="<?= htmlspecialchars($s['name']) ?>"><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

                <input type="text" id="searchInput" placeholder="Buscar equipo o SKU..." style="padding:8px 12px; background:#f7f7f7; border:1px solid #e5e5e5; border-radius:6px; outline:none; font-size:13px; width:250px;">
            </div>
            
            <div style="display:flex; gap:12px; align-items:center;">
                <button id="selectAllBtn" type="button" style="background: #fff; color: #457b9d; border: 1px solid #457b9d; padding: 0 14px; border-radius: 6px; cursor: pointer; height: 41px; display: flex; align-items: center; font-weight: 600; font-family: 'Inter', sans-serif;" title="Seleccionar o deseleccionar todos los equipos visibles">☑️ Seleccionar Todos</button>
                <button id="viewToggleBtn" style="background: #eee; color: #333; border: 1px solid #ccc; padding: 0 15px; border-radius: 6px; cursor: pointer; height: 41px; display: flex; align-items: center; font-weight: 600; font-family: 'Inter', sans-serif;">🔲 Bloques</button>
                <a href="producto_form.php" style="background: #457b9d; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-family: 'Oswald', sans-serif; font-weight: 600; text-transform: uppercase; cursor: pointer; white-space: nowrap; height: 41px; box-sizing: border-box; text-decoration: none; display: flex; align-items: center;">+ Añadir</a>
            </div>
        </div>
    </div>
    
    <div class="table-wrapper" id="tableView">
        <table class="products-table" id="productsTable">
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">
                        <input type="checkbox" id="selectAllHeader" title="Seleccionar todos los visibles" style="cursor: pointer; width: 16px; height: 16px;">
                    </th>
                    <th style="width: 60px">IMG</th>
                    <th style="width: 100px">CÓDIGO</th>
                    <th style="width: 170px">
                        <div style="display:flex; flex-direction:column; gap:6px;">
                            <span>CATEGORÍA</span>
                            <select id="catFilter" style="padding:4px 8px; background:#f7f7f7; border:1px solid #ccc; border-radius:4px; outline:none; font-size:11px; font-weight:normal; width:100%;">
                                <option value="">Todas (Filtro)</option>
                                <option value="NONE">- Sin categoría -</option>
                                <?php foreach ($cats as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </th>
                    <th style="width: 150px">
                        <div style="display:flex; flex-direction:column; gap:6px;">
                            <span>MARCA</span>
                            <select id="brandFilter" style="padding:4px 8px; background:#f7f7f7; border:1px solid #ccc; border-radius:4px; outline:none; font-size:11px; font-weight:normal; width:100%;">
                                <option value="">Todas (Filtro)</option>
                                <option value="NONE">- Sin marca -</option>
                                <?php foreach ($marcas as $m): ?>
                                    <option value="<?= htmlspecialchars($m['name']) ?>"><?= htmlspecialchars($m['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </th>
                    <th style="width: 150px">
                        <div style="display:flex; flex-direction:column; gap:6px;">
                            <span>SERIE</span>
                            <select id="serieFilter" style="padding:4px 8px; background:#f7f7f7; border:1px solid #ccc; border-radius:4px; outline:none; font-size:11px; font-weight:normal; width:100%;">
                                <option value="">Todas (Filtro)</option>
                                <option value="NONE">- Sin serie -</option>
                                <?php foreach ($seriesList as $s): ?>
                                    <option value="<?= htmlspecialchars($s['name']) ?>"><?= htmlspecialchars($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </th>
                    <th>NOMBRE DEL EQUIPO</th>
                    <th style="width: 120px">PRECIO REF.</th>
                    <th style="width: 120px; text-align: right">ACCIONES</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productos as $p): ?>
                    <tr class="product-row" data-id="<?= $p['id'] ?>" data-cat="<?= htmlspecialchars($p['categoria_id'] ?? '') ?>" data-brand="<?= htmlspecialchars($p['series'] ?? '') ?>" data-serie="<?= htmlspecialchars($p['serie'] ?? '') ?>">
                        <td class="checkbox-cell" data-label="Sel" style="text-align: center;">
                            <input type="checkbox" class="prod-checkbox" value="<?= $p['id'] ?>" style="cursor: pointer; width: 16px; height: 16px;">
                        </td>
                        <td class="img-cell" data-label="IMG">
                            <?php $img = getProductImgUrl($p['img'] ?? '', $p['media_json'] ?? ''); ?>
                            <div style="width:44px; height:34px; border:1px solid #eee; background:white; border-radius:4px; display:flex; align-items:center; justify-content:center; overflow:hidden;">
                                <?php if ($img): ?>
                                    <img src="<?= htmlspecialchars($img) ?>" style="max-width:100%; max-height:100%; object-fit:contain;" onerror="this.style.display='none'" />
                                <?php else: ?>
                                    <span style="font-size:10px; color:#ccc">No img</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="searchable" data-label="Código" style="font-weight: 600; color: #457b9d;"><?= htmlspecialchars($p['item_no'] ?? '-') ?></td>
                        <td class="searchable" data-label="Categoría"><span style="background:#e0ebf3; color:#1d3557; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:600;"><?= htmlspecialchars($catMap[$p['categoria_id']] ?? 'Sin Categoría') ?></span></td>
                        <td class="searchable" data-label="Marca"><?= htmlspecialchars($p['series'] ?: '-') ?></td>
                        <td class="searchable" data-label="Serie"><?= htmlspecialchars($p['serie'] ?: '-') ?></td>
                        <td class="searchable" data-label="Nombre" style="font-weight: 500;"><?= htmlspecialchars($p['name'] ?? '') ?></td>
                        <td data-label="Precio Ref.">$ <?= number_format((float)$p['price'], 0, ',', '.') ?></td>
                        <td class="actions-cell" data-label="Acciones" style="text-align: right; white-space: nowrap;">
                            <a href="producto_form.php?id=<?= $p['id'] ?>" style="background: transparent; border: 1px solid #ccc; border-radius: 4px; padding: 6px 10px; cursor: pointer; margin-right: 4px; text-decoration: none; display: inline-block; color: #333;">✏️</a>
                            <a href="productos.php?delete=<?= $p['id'] ?>" style="background: transparent; border: 1px solid #ffcccc; color: #e63946; border-radius: 4px; padding: 6px 10px; cursor: pointer; text-decoration: none; display: inline-block;" onclick="return confirm('¿Estás seguro de eliminar este producto? Esta acción no se puede deshacer.');">🗑️</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr id="noResultsRow" style="display:none;"><td colspan="9" style="text-align:center; padding:40px; color:#718096;">No se encontraron productos.</td></tr>
            </tbody>
        </table>
    </div>

    <div id="gridView" style="display:none; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 24px; margin-top:20px;">
        <?php foreach ($productos as $p): ?>
            <?php $img = getProductImgUrl($p['img'] ?? '', $p['media_json'] ?? ''); ?>
            <div class="product-card" data-id="<?= $p['id'] ?>" data-cat="<?= htmlspecialchars($p['categoria_id'] ?? '') ?>" data-brand="<?= htmlspecialchars($p['series'] ?? '') ?>" data-serie="<?= htmlspecialchars($p['serie'] ?? '') ?>" style="background: white; border: 1px solid #eee; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); display: flex; flex-direction: column;">
                <label class="card-checkbox-badge" title="Seleccionar equipo">
                    <input type="checkbox" class="prod-checkbox" value="<?= $p['id'] ?>" style="cursor: pointer; width: 18px; height: 18px; margin: 0;">
                </label>
                <div style="height: 220px; display: flex; align-items: center; justify-content: center; background: #fdfdfd; padding: 16px;">
                    <?php if ($img): ?>
                        <img src="<?= htmlspecialchars($img) ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;" onerror="this.style.display='none'">
                    <?php else: ?>
                        <span style="color: #ccc; font-size: 14px;">Sin imagen</span>
                    <?php endif; ?>
                </div>
                <div style="padding: 16px; display: flex; flex-direction: column; flex-grow: 1; border-top: 1px solid #f0f0f0;">
                    <div class="searchable-card" style="font-size: 12px; color: #888; margin-bottom: 6px; font-weight: 600; letter-spacing: 0.05em;">
                        <?= htmlspecialchars($catMap[$p['categoria_id']] ?? 'Sin Categoría') ?> • <?= htmlspecialchars($p['series'] ?: 'Sin Marca') ?><?= !empty($p['serie']) ? ' • Serie: ' . htmlspecialchars($p['serie']) : '' ?> • <?= htmlspecialchars($p['item_no'] ?? '-') ?>
                    </div>
                    <div class="searchable-card" style="font-weight: 600; font-size: 16px; color: #1d3557; margin-bottom: 12px; line-height: 1.3;">
                        <?= htmlspecialchars($p['name'] ?? '') ?>
                    </div>
                    <div style="font-weight: 600; color: #e63946; font-size: 18px; margin-top: auto;">
                        $ <?= number_format((float)$p['price'], 0, ',', '.') ?>
                    </div>
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <a href="producto_form.php?id=<?= $p['id'] ?>" style="flex-grow: 1; background: #f7f7f7; border: 1px solid #ddd; padding: 8px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; color: #555; text-align:center; text-decoration:none;">✏️ Editar</a>
                        <a href="productos.php?delete=<?= $p['id'] ?>" onclick="return confirm('¿Estás seguro de eliminar este producto? Esta acción no se puede deshacer.');" style="background: #fff0f0; border: 1px solid #ffcccc; color: #e63946; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 14px; text-decoration:none;">🗑️</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <div id="noResultsGrid" style="display:none; grid-column: 1 / -1; text-align: center; padding: 40px; color: #888;">No se encontraron productos.</div>
    </div>

</div>

<!-- Barra Flotante de Acciones en Lote -->
<div id="bulkActionBar" style="position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); background: #1d3557; color: white; padding: 12px 24px; border-radius: 50px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); display: none; align-items: center; gap: 16px; z-index: 1000; font-family: 'Inter', sans-serif;">
    <span style="font-weight: 600; font-size: 14px;"><span id="selectedCountBadge" style="background: #e63946; color: white; padding: 3px 10px; border-radius: 12px; margin-right: 6px; font-weight: bold;">0</span> seleccionados</span>
    <button id="openBulkModalBtn" type="button" style="background: #457b9d; color: white; border: none; padding: 8px 18px; border-radius: 20px; font-weight: 600; cursor: pointer; font-size: 13px; transition: background 0.2s; display: flex; align-items: center; gap: 6px;">⚡ Editar en masa</button>
    <button id="clearSelectionBtn" type="button" style="background: transparent; color: #cbd5e0; border: 1px solid rgba(255,255,255,0.3); padding: 8px 14px; border-radius: 20px; font-weight: 500; cursor: pointer; font-size: 12px;">Deseleccionar</button>
</div>

<!-- Modal de Edición Masiva -->
<div id="bulkModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div style="background: white; border-radius: 12px; max-width: 480px; width: 92%; padding: 26px; box-shadow: 0 20px 40px rgba(0,0,0,0.25); box-sizing: border-box;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; border-bottom: 1px solid #eee; padding-bottom: 12px;">
            <h3 style="margin: 0; font-family: 'Oswald', sans-serif; text-transform: uppercase; color: #1d3557; font-size: 20px;">⚡ Editar Equipos en Masa</h3>
            <button id="closeBulkModalBtn" type="button" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #888; line-height: 1;">✕</button>
        </div>
        
        <p style="font-size: 14px; color: #555; margin-top: 0; margin-bottom: 20px; line-height: 1.5;">
            Vas a modificar <strong id="modalSelectedCountText" style="color: #e63946; font-size: 16px;">0</strong> productos seleccionados.
            <br><span style="font-size: 12px; color: #718096;">* Los campos que dejes en "No cambiar" mantendrán su valor actual.</span>
        </p>
        
        <form id="bulkEditForm" method="POST" action="productos.php">
            <input type="hidden" name="action" value="bulk_update">
            <input type="hidden" name="selected_ids" id="selectedIdsInput" value="">
            
            <div class="form-group" style="margin-bottom: 16px;">
                <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px; color: #1d3557;">Categoría</label>
                <select name="bulk_categoria" id="bulkCatSelect" style="width: 100%; padding: 10px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; background: #fdfdfd; box-sizing: border-box;">
                    <option value="KEEP">-- No cambiar (Mantener actual) --</option>
                    <option value="NONE">- Sin categoría -</option>
                    <?php foreach ($cats as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom: 16px;">
                <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px; color: #1d3557;">Marca (Series)</label>
                <select name="bulk_marca" id="bulkBrandSelect" style="width: 100%; padding: 10px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; background: #fdfdfd; box-sizing: border-box;">
                    <option value="KEEP">-- No cambiar (Mantener actual) --</option>
                    <option value="NONE">- Sin marca -</option>
                    <?php foreach ($marcas as $m): ?>
                        <option value="<?= htmlspecialchars($m['name']) ?>"><?= htmlspecialchars($m['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px; color: #1d3557;">Serie</label>
                <select name="bulk_serie_mode" id="bulkSerieMode" style="width: 100%; padding: 10px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; background: #fdfdfd; box-sizing: border-box; margin-bottom: 8px;">
                    <option value="KEEP">-- No cambiar (Mantener actual) --</option>
                    <option value="SET">Asignar serie</option>
                    <option value="CLEAR">- Dejar sin serie -</option>
                </select>
                <div id="bulkSerieWrapper" style="display: none;">
                    <input type="text" name="bulk_serie" id="bulkSerieInput" list="bulkSeriesDatalist" placeholder="Escribe o selecciona la serie..." style="width: 100%; padding: 10px 12px; border: 1px solid #457b9d; border-radius: 6px; font-size: 14px; background: #fff; box-sizing: border-box;">
                    <datalist id="bulkSeriesDatalist">
                        <?php foreach ($seriesList as $s): ?>
                            <option value="<?= htmlspecialchars($s['name']) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px; color: #1d3557;">Código (SKU)</label>
                <select name="bulk_item_no_mode" id="bulkItemNoMode" style="width: 100%; padding: 10px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; background: #fdfdfd; box-sizing: border-box; margin-bottom: 8px;">
                    <option value="KEEP">-- No cambiar (Mantener actual) --</option>
                    <option value="SET">Asignar nuevo código a todos</option>
                    <option value="CLEAR">- Dejar código vacío -</option>
                </select>
                <input type="text" name="bulk_item_no" id="bulkItemNoInput" placeholder="Escribe el nuevo código..." style="display: none; width: 100%; padding: 10px 12px; border: 1px solid #457b9d; border-radius: 6px; font-size: 14px; background: #fff; box-sizing: border-box;">
            </div>
            
            <div style="display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid #eee; padding-top: 16px;">
                <button type="button" id="cancelBulkModalBtn" style="background: #edf2f7; color: #4a5568; border: none; padding: 10px 18px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 14px;">Cancelar</button>
                <button type="submit" style="background: #1d3557; color: white; border: none; padding: 10px 22px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 14px;">Aplicar Cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const rows = document.querySelectorAll('.product-row');
    const cards = document.querySelectorAll('.product-card');
    const noResultsRow = document.getElementById('noResultsRow');
    const noResultsGrid = document.getElementById('noResultsGrid');
    const tableView = document.getElementById('tableView');
    const gridView = document.getElementById('gridView');
    const viewToggleBtn = document.getElementById('viewToggleBtn');
    const topFilters = document.getElementById('topFilters');
    
    const catFilterTop = document.getElementById('catFilterTop');
    const brandFilterTop = document.getElementById('brandFilterTop');
    const serieFilterTop = document.getElementById('serieFilterTop');
    const catFilterTable = document.getElementById('catFilter');
    const brandFilterTable = document.getElementById('brandFilter');
    const serieFilterTable = document.getElementById('serieFilter');

    let isGridView = false;

    // --- Bulk Selection Elements ---
    const selectedIds = new Set();
    const bulkActionBar = document.getElementById('bulkActionBar');
    const selectedCountBadge = document.getElementById('selectedCountBadge');
    const openBulkModalBtn = document.getElementById('openBulkModalBtn');
    const clearSelectionBtn = document.getElementById('clearSelectionBtn');
    const selectAllHeader = document.getElementById('selectAllHeader');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const bulkModal = document.getElementById('bulkModal');
    const closeBulkModalBtn = document.getElementById('closeBulkModalBtn');
    const cancelBulkModalBtn = document.getElementById('cancelBulkModalBtn');
    const modalSelectedCountText = document.getElementById('modalSelectedCountText');
    const selectedIdsInput = document.getElementById('selectedIdsInput');
    const bulkEditForm = document.getElementById('bulkEditForm');
    const bulkCatSelect = document.getElementById('bulkCatSelect');
    const bulkBrandSelect = document.getElementById('bulkBrandSelect');
    const bulkSerieMode = document.getElementById('bulkSerieMode');
    const bulkSerieWrapper = document.getElementById('bulkSerieWrapper');
    const bulkSerieInput = document.getElementById('bulkSerieInput');
    const bulkItemNoMode = document.getElementById('bulkItemNoMode');
    const bulkItemNoInput = document.getElementById('bulkItemNoInput');

    if (bulkSerieMode) {
        bulkSerieMode.addEventListener('change', function() {
            if (this.value === 'SET') {
                bulkSerieWrapper.style.display = 'block';
                bulkSerieInput.focus();
            } else {
                bulkSerieWrapper.style.display = 'none';
            }
        });
    }

    if (bulkItemNoMode) {
        bulkItemNoMode.addEventListener('change', function() {
            if (this.value === 'SET') {
                bulkItemNoInput.style.display = 'block';
                bulkItemNoInput.focus();
            } else {
                bulkItemNoInput.style.display = 'none';
            }
        });
    }

    function updateSelectionUI() {
        const count = selectedIds.size;
        selectedCountBadge.textContent = count;
        bulkActionBar.style.display = count > 0 ? 'flex' : 'none';

        // Update checkboxes and styles in table & grid
        document.querySelectorAll('.prod-checkbox').forEach(cb => {
            const isChecked = selectedIds.has(cb.value);
            cb.checked = isChecked;
            
            const row = cb.closest('.product-row');
            if (row) {
                if (isChecked) row.classList.add('selected-row');
                else row.classList.remove('selected-row');
            }

            const card = cb.closest('.product-card');
            if (card) {
                if (isChecked) card.classList.add('selected-card');
                else card.classList.remove('selected-card');
            }
        });

        // Sync Select All Header
        const visibleRows = Array.from(rows).filter(r => r.style.display !== 'none');
        if (visibleRows.length > 0 && selectAllHeader) {
            selectAllHeader.checked = visibleRows.every(r => selectedIds.has(r.getAttribute('data-id')));
        }
    }

    // Toggle individual checkbox
    document.addEventListener('change', function(e) {
        if (e.target && e.target.classList.contains('prod-checkbox')) {
            const id = e.target.value;
            if (e.target.checked) {
                selectedIds.add(id);
            } else {
                selectedIds.delete(id);
            }
            updateSelectionUI();
        }
    });

    // Toggle select all visible
    function toggleSelectAllVisible() {
        const visibleItems = isGridView 
            ? Array.from(cards).filter(c => c.style.display !== 'none')
            : Array.from(rows).filter(r => r.style.display !== 'none');

        if (visibleItems.length === 0) return;

        const allSelected = visibleItems.every(el => selectedIds.has(el.getAttribute('data-id')));
        visibleItems.forEach(el => {
            const id = el.getAttribute('data-id');
            if (allSelected) {
                selectedIds.delete(id);
            } else {
                selectedIds.add(id);
            }
        });
        updateSelectionUI();
    }

    if (selectAllHeader) selectAllHeader.addEventListener('change', toggleSelectAllVisible);
    if (selectAllBtn) selectAllBtn.addEventListener('click', toggleSelectAllVisible);

    if (clearSelectionBtn) {
        clearSelectionBtn.addEventListener('click', function() {
            selectedIds.clear();
            updateSelectionUI();
        });
    }

    // Modal controls
    if (openBulkModalBtn) {
        openBulkModalBtn.addEventListener('click', function() {
            if (selectedIds.size === 0) return;
            selectedIdsInput.value = Array.from(selectedIds).join(',');
            modalSelectedCountText.textContent = selectedIds.size;
            bulkCatSelect.value = 'KEEP';
            bulkBrandSelect.value = 'KEEP';
            bulkSerieMode.value = 'KEEP';
            bulkSerieInput.value = '';
            if (bulkSerieWrapper) bulkSerieWrapper.style.display = 'none';
            bulkItemNoMode.value = 'KEEP';
            bulkItemNoInput.value = '';
            bulkItemNoInput.style.display = 'none';
            bulkModal.style.display = 'flex';
        });
    }

    function closeModal() {
        bulkModal.style.display = 'none';
    }
    if (closeBulkModalBtn) closeBulkModalBtn.addEventListener('click', closeModal);
    if (cancelBulkModalBtn) cancelBulkModalBtn.addEventListener('click', closeModal);
    if (bulkModal) {
        bulkModal.addEventListener('click', function(e) {
            if (e.target === bulkModal) closeModal();
        });
    }

    if (bulkEditForm) {
        bulkEditForm.addEventListener('submit', function(e) {
            const hasChange = bulkCatSelect.value !== 'KEEP' || 
                              bulkBrandSelect.value !== 'KEEP' || 
                              bulkSerieMode.value !== 'KEEP' || 
                              bulkItemNoMode.value !== 'KEEP';
            if (!hasChange) {
                e.preventDefault();
                alert('Debes seleccionar al menos un campo a modificar (Categoría, Marca, Serie o Código).');
                return;
            }
            if (bulkSerieMode.value === 'SET' && bulkSerieInput.value.trim() === '') {
                e.preventDefault();
                alert('Ingresa o selecciona la serie que deseas asignar o elige "Dejar sin serie".');
                bulkSerieInput.focus();
                return;
            }
            if (bulkItemNoMode.value === 'SET' && bulkItemNoInput.value.trim() === '') {
                e.preventDefault();
                alert('Ingresa el código que deseas asignar o selecciona "Dejar código vacío".');
                bulkItemNoInput.focus();
                return;
            }
            if (!confirm(`¿Estás seguro de actualizar ${selectedIds.size} productos con los nuevos valores?`)) {
                e.preventDefault();
            }
        });
    }

    // Filter and view toggle synchronization
    function syncFilters(e) {
        if (e.target.id === 'catFilter') catFilterTop.value = e.target.value;
        if (e.target.id === 'catFilterTop') catFilterTable.value = e.target.value;
        if (e.target.id === 'brandFilter') brandFilterTop.value = e.target.value;
        if (e.target.id === 'brandFilterTop') brandFilterTable.value = e.target.value;
        if (e.target.id === 'serieFilter') serieFilterTop.value = e.target.value;
        if (e.target.id === 'serieFilterTop') serieFilterTable.value = e.target.value;
        filterTable();
    }

    catFilterTable.addEventListener('change', syncFilters);
    catFilterTop.addEventListener('change', syncFilters);
    brandFilterTable.addEventListener('change', syncFilters);
    brandFilterTop.addEventListener('change', syncFilters);
    serieFilterTable.addEventListener('change', syncFilters);
    serieFilterTop.addEventListener('change', syncFilters);

    viewToggleBtn.addEventListener('click', function() {
        isGridView = !isGridView;
        if (isGridView) {
            tableView.style.display = 'none';
            gridView.style.display = 'grid';
            viewToggleBtn.textContent = '📋 Tabla';
            topFilters.style.display = 'flex';
        } else {
            tableView.style.display = 'block';
            gridView.style.display = 'none';
            viewToggleBtn.textContent = '🔲 Bloques';
            topFilters.style.display = 'none';
        }
        updateSelectionUI();
    });

    function filterTable() {
        const query = searchInput.value.toLowerCase().trim();
        const cat = catFilterTable.value;
        const brand = brandFilterTable.value;
        const serie = serieFilterTable.value;
        let visibleCount = 0;

        // Filter Table Rows
        rows.forEach(row => {
            let show = true;
            if (cat === 'NONE') { if (row.getAttribute('data-cat') !== '') show = false; } else if (cat !== '' && row.getAttribute('data-cat') !== cat) show = false;
            if (brand === 'NONE') { if (row.getAttribute('data-brand') !== '') show = false; } else if (brand !== '' && row.getAttribute('data-brand') !== brand) show = false;
            if (serie === 'NONE') { if (row.getAttribute('data-serie') !== '') show = false; } else if (serie !== '' && row.getAttribute('data-serie') !== serie) show = false;

            if (show && query !== '') {
                const searchData = Array.from(row.querySelectorAll('.searchable'))
                                      .map(td => td.textContent.toLowerCase())
                                      .join(' ');
                if (!searchData.includes(query)) show = false;
            }

            if (show) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        noResultsRow.style.display = (visibleCount === 0) ? '' : 'none';

        // Filter Grid Cards
        let gridVisibleCount = 0;
        cards.forEach(card => {
            let show = true;
            if (cat === 'NONE') { if (card.getAttribute('data-cat') !== '') show = false; } else if (cat !== '' && card.getAttribute('data-cat') !== cat) show = false;
            if (brand === 'NONE') { if (card.getAttribute('data-brand') !== '') show = false; } else if (brand !== '' && card.getAttribute('data-brand') !== brand) show = false;
            if (serie === 'NONE') { if (card.getAttribute('data-serie') !== '') show = false; } else if (serie !== '' && card.getAttribute('data-serie') !== serie) show = false;

            if (show && query !== '') {
                const searchData = Array.from(card.querySelectorAll('.searchable-card'))
                                      .map(div => div.textContent.toLowerCase())
                                      .join(' ');
                if (!searchData.includes(query)) show = false;
            }

            if (show) {
                card.style.display = 'flex';
                gridVisibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        noResultsGrid.style.display = (gridVisibleCount === 0) ? 'block' : 'none';

        updateSelectionUI();
    }

    searchInput.addEventListener('input', filterTable);
    
});
</script>
</body></html>
