<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

// Auto-descubrir y registrar series que ya estén en la tabla productos asociándolas a su categoría
try {
    $rows = $pdo->query("SELECT DISTINCT categoria_id, serie FROM productos WHERE serie IS NOT NULL AND TRIM(serie) != ''")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $sName = trim($r['serie']);
        $catId = $r['categoria_id'] ? (int)$r['categoria_id'] : null;
        if ($catId) {
            $chk = $pdo->prepare("SELECT id FROM series WHERE name = ? AND categoria_id = ?");
            $chk->execute([$sName, $catId]);
        } else {
            $chk = $pdo->prepare("SELECT id FROM series WHERE name = ? AND (categoria_id IS NULL OR categoria_id = 0)");
            $chk->execute([$sName]);
        }
        if (!$chk->fetch()) {
            $maxStmt = $pdo->prepare("SELECT COALESCE(MAX(pos), 0) FROM series WHERE " . ($catId ? "categoria_id = ?" : "(categoria_id IS NULL OR categoria_id = 0)"));
            $maxStmt->execute($catId ? [$catId] : []);
            $nextPos = (int)$maxStmt->fetchColumn() + 1;
            $ins = $pdo->prepare("INSERT INTO series (categoria_id, name, pos) VALUES (?, ?, ?)");
            $ins->execute([$catId, $sName, $nextPos]);
        }
    }
} catch (Exception $e) {}

// Eliminar serie
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM series WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $_SESSION['flash_msg'] = "Serie eliminada correctamente.";
    header("Location: series.php" . (isset($_GET['cat']) ? "?cat=" . urlencode($_GET['cat']) : '')); exit;
}

// Mover arriba / abajo dentro de la misma categoría
if (isset($_GET['move_up']) || isset($_GET['move_down'])) {
    $id = (int)($_GET['move_up'] ?? $_GET['move_down']);
    $isUp = isset($_GET['move_up']);
    $current = $pdo->query("SELECT id, categoria_id, pos FROM series WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
    if ($current) {
        $catCond = $current['categoria_id'] ? "= " . (int)$current['categoria_id'] : "IS NULL";
        $op = $isUp ? "<" : ">";
        $order = $isUp ? "DESC" : "ASC";
        $neighbor = $pdo->query("SELECT id, pos FROM series WHERE categoria_id $catCond AND pos $op {$current['pos']} ORDER BY pos $order LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($neighbor) {
            $pdo->prepare("UPDATE series SET pos = ? WHERE id = ?")->execute([$neighbor['pos'], $current['id']]);
            $pdo->prepare("UPDATE series SET pos = ? WHERE id = ?")->execute([$current['pos'], $neighbor['id']]);
            $_SESSION['flash_msg'] = "Orden actualizado correctamente.";
        }
    }
    header("Location: series.php" . (isset($_GET['cat']) ? "?cat=" . urlencode($_GET['cat']) : '')); exit;
}

// Guardar orden masivo desde los inputs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_order') {
    if (isset($_POST['pos']) && is_array($_POST['pos'])) {
        $stmtUpdate = $pdo->prepare("UPDATE series SET pos = ? WHERE id = ?");
        foreach ($_POST['pos'] as $serieId => $newPos) {
            $stmtUpdate->execute([(int)$newPos, (int)$serieId]);
        }
        $_SESSION['flash_msg'] = "El orden de las series fue guardado exitosamente.";
    }
    header("Location: series.php" . (isset($_POST['cat_filter']) && $_POST['cat_filter'] !== '' ? "?cat=" . urlencode($_POST['cat_filter']) : '')); exit;
}

// Categorías disponibles
$categorias = $pdo->query("SELECT id, name FROM categorias ORDER BY pos ASC")->fetchAll(PDO::FETCH_ASSOC);
$catMap = [];
foreach ($categorias as $c) { $catMap[$c['id']] = $c['name']; }

// Filtro de categoría actual
$selectedCat = $_GET['cat'] ?? '';

$sql = "SELECT s.*, c.name as cat_name FROM series s LEFT JOIN categorias c ON s.categoria_id = c.id ";
if ($selectedCat !== '') {
    if ($selectedCat === 'NONE') {
        $sql .= " WHERE s.categoria_id IS NULL OR s.categoria_id = 0 ";
    } else {
        $sql .= " WHERE s.categoria_id = " . (int)$selectedCat . " ";
    }
}
$sql .= " ORDER BY c.pos ASC, s.categoria_id ASC, s.pos ASC, s.name ASC";
$seriesList = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Conteo de productos por serie y categoría
$prodCounts = [];
try {
    $cStmt = $pdo->query("SELECT CONCAT(COALESCE(categoria_id, 0), '_', TRIM(serie)) as k, COUNT(*) as cnt FROM productos WHERE serie IS NOT NULL AND TRIM(serie) != '' GROUP BY categoria_id, TRIM(serie)");
    while ($row = $cStmt->fetch(PDO::FETCH_ASSOC)) {
        $prodCounts[$row['k']] = (int)$row['cnt'];
    }
} catch (Exception $e) {}

// Agrupar series por categoría para la visualización
$groupedSeries = [];
foreach ($seriesList as $s) {
    $catId = $s['categoria_id'] ?: 0;
    $catName = $s['cat_name'] ?: 'Sin categoría asignada';
    if (!isset($groupedSeries[$catId])) {
        $groupedSeries[$catId] = [
            'name' => $catName,
            'items' => []
        ];
    }
    $groupedSeries[$catId]['items'][] = $s;
}

include 'header.php';
?>

<div class="panel-card">
    <?php if (isset($_SESSION['flash_msg'])): ?>
        <div style="background:#e6fffa; color:#234e52; padding:12px 18px; border-radius:8px; margin-bottom:20px; border:1px solid #b2f5ea; display:flex; justify-content:space-between; align-items:center; font-weight:600;">
            <span>✅ <?= htmlspecialchars($_SESSION['flash_msg']) ?></span>
            <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;font-weight:bold;color:#234e52;font-size:16px;">✕</button>
        </div>
        <?php unset($_SESSION['flash_msg']); ?>
    <?php endif; ?>

    <div style="display:flex; justify-content:space-between; margin-bottom:20px; align-items:center; flex-wrap:wrap; gap:16px;">
        <div>
            <h2 style="margin:0 0 6px 0; font-family:'Oswald', sans-serif; text-transform:uppercase; color:#1d3557; font-size:24px;">⚡ Orden de Series Parametrizado por Categoría</h2>
            <div style="font-size:13px; color:#555; line-height:1.4;">
                El orden de las series está <strong>parametrizado por cada Categoría</strong>. Define la posición horizontal de las series y el orden en que se listan los equipos dentro de esa categoría en el catálogo.
            </div>
        </div>
        <div style="display:flex; gap:10px; align-items:center;">
            <a href="productos.php" style="background:#f7f7f7; color:#457b9d; border:1px solid #ccc; padding:10px 16px; border-radius:6px; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">📦 Ver Equipos</a>
            <a href="serie_form.php<?= $selectedCat !== '' && $selectedCat !== 'NONE' ? '?cat=' . (int)$selectedCat : '' ?>" class="btn-primary" style="background:#457b9d; color:white; padding:10px 18px; border-radius:6px; text-decoration:none; font-family:'Oswald', sans-serif; text-transform:uppercase; font-weight:600;">+ Nueva Serie</a>
        </div>
    </div>

    <!-- Filtro de Categoría -->
    <div style="background:#f8fbfe; border:1px solid #d0e2f2; border-radius:8px; padding:14px 18px; margin-bottom:24px; display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
        <label style="font-weight:700; color:#1d3557; font-size:14px;">Filtrar por Categoría:</label>
        <select onchange="window.location.href='series.php' + (this.value ? '?cat=' + encodeURIComponent(this.value) : '')" style="padding:8px 14px; border:1px solid #457b9d; border-radius:6px; font-size:14px; font-weight:600; color:#1d3557; background:#fff; outline:none; min-width:240px;">
            <option value="">-- Ver Todas las Categorías --</option>
            <?php foreach ($categorias as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= ($selectedCat == $cat['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                </option>
            <?php endforeach; ?>
            <option value="NONE" <?= ($selectedCat === 'NONE') ? 'selected' : '' ?>>- Sin categoría asignada -</option>
        </select>
        <span style="font-size:12px; color:#718096;">* Selecciona una categoría para ver y ordenar exclusivamente sus series.</span>
    </div>

    <form method="POST" action="series.php">
        <input type="hidden" name="action" value="save_order">
        <input type="hidden" name="cat_filter" value="<?= htmlspecialchars($selectedCat) ?>">
        
        <?php foreach ($groupedSeries as $catId => $group): ?>
            <div style="margin-bottom:28px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
                <div style="background:#1d3557; color:white; padding:12px 18px; display:flex; justify-content:space-between; align-items:center;">
                    <div style="font-family:'Oswald', sans-serif; text-transform:uppercase; font-size:17px; letter-spacing:0.04em;">
                        📂 Categoría: <?= htmlspecialchars($group['name']) ?>
                    </div>
                    <span style="background:rgba(255,255,255,0.2); padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600;">
                        <?= count($group['items']) ?> <?= count($group['items']) === 1 ? 'serie' : 'series' ?>
                    </span>
                </div>

                <div class="table-wrapper" style="margin:0; border:none; border-radius:0;">
                    <table class="products-table">
                        <thead>
                            <tr>
                                <th style="width: 100px; text-align:center;">ORDEN</th>
                                <th style="width: 110px; text-align:center;">MOVER</th>
                                <th>NOMBRE DE LA SERIE</th>
                                <th style="width: 140px; text-align:center;">EQUIPOS</th>
                                <th style="width: 140px; text-align:right;">ACCIONES</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($group['items'] as $idx => $s): ?>
                                <tr>
                                    <td style="text-align:center;">
                                        <input type="number" name="pos[<?= $s['id'] ?>]" value="<?= (int)$s['pos'] ?>" min="0" style="width: 65px; padding: 6px 8px; border: 1px solid #457b9d; border-radius: 6px; font-weight: 700; color: #1d3557; text-align: center; font-size: 14px; background:#f8fbfe;">
                                    </td>
                                    <td style="text-align:center; white-space:nowrap;">
                                        <?php $catParam = $selectedCat !== '' ? '&cat=' . urlencode($selectedCat) : ''; ?>
                                        <?php if ($idx > 0): ?>
                                            <a href="series.php?move_up=<?= $s['id'] ?><?= $catParam ?>" style="display:inline-block; padding:4px 8px; background:#edf2f7; border-radius:4px; text-decoration:none; color:#2d3748; font-weight:bold; font-size:12px; margin-right:2px;" title="Subir prioridad">▲</a>
                                        <?php else: ?>
                                            <span style="display:inline-block; padding:4px 8px; color:#cbd5e0; font-size:12px; margin-right:2px;">▲</span>
                                        <?php endif; ?>
                                        <?php if ($idx < count($group['items']) - 1): ?>
                                            <a href="series.php?move_down=<?= $s['id'] ?><?= $catParam ?>" style="display:inline-block; padding:4px 8px; background:#edf2f7; border-radius:4px; text-decoration:none; color:#2d3748; font-weight:bold; font-size:12px;" title="Bajar prioridad">▼</a>
                                        <?php else: ?>
                                            <span style="display:inline-block; padding:4px 8px; color:#cbd5e0; font-size:12px;">▼</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong style="font-size:15px; color:#1d3557;"><?= htmlspecialchars($s['name']) ?></strong>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php 
                                            $k = ($s['categoria_id'] ?: 0) . '_' . trim($s['name']);
                                            $cnt = $prodCounts[$k] ?? 0; 
                                        ?>
                                        <span style="background:<?= $cnt > 0 ? '#e0ebf3' : '#edf2f7' ?>; color:<?= $cnt > 0 ? '#1d3557' : '#718096' ?>; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600;">
                                            <?= $cnt ?> <?= $cnt === 1 ? 'equipo' : 'equipos' ?>
                                        </span>
                                    </td>
                                    <td style="text-align:right; white-space:nowrap;">
                                        <a href="serie_form.php?id=<?= $s['id'] ?>" style="background: transparent; border: 1px solid #ccc; border-radius: 4px; padding: 6px 10px; cursor: pointer; margin-right: 4px; text-decoration: none; display: inline-block; color: #333;">✏️ Editar</a>
                                        <a href="series.php?delete=<?= $s['id'] ?><?= $catParam ?>" style="background: transparent; border: 1px solid #ffcccc; color: #e63946; border-radius: 4px; padding: 6px 10px; cursor: pointer; text-decoration: none; display: inline-block;" onclick="return confirm('¿Estás seguro de eliminar esta serie? (Los equipos asociados no se eliminarán)');">🗑️</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (count($seriesList) === 0): ?>
            <div style="text-align:center; padding:50px; background:#fff; border:1px solid #eee; border-radius:8px; color:#718096;">
                No se encontraron series para esta categoría. Haz clic en <strong>+ Nueva Serie</strong> para agregar una.
            </div>
        <?php endif; ?>

        <?php if (count($seriesList) > 0): ?>
            <div style="margin-top:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; border-top:1px solid #eee; padding-top:16px;">
                <span style="font-size:12px; color:#718096;">* Puedes cambiar los números de orden directamente y presionar Guardar Orden, o usar las flechas ▲ ▼ para mover una a una dentro de su categoría.</span>
                <button type="submit" style="background:#1d3557; color:white; border:none; padding:12px 24px; border-radius:6px; font-weight:700; font-family:'Oswald', sans-serif; text-transform:uppercase; font-size:15px; cursor:pointer; display:inline-flex; align-items:center; gap:8px;">
                    💾 Guardar Todo el Orden
                </button>
            </div>
        <?php endif; ?>
    </form>
</div>
</body></html>
