<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

// Cargar categorías, marcas y series existentes
$stmt = $pdo->query("SELECT id, name FROM categorias ORDER BY pos ASC, name ASC");
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT name FROM marcas ORDER BY pos ASC, name ASC");
$marcas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT name FROM series ORDER BY pos ASC, name ASC");
$seriesList = $stmt->fetchAll(PDO::FETCH_ASSOC);

$flash_error = null;

// Procesar guardado en masa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_batch_products') {
    $rows = $_POST['products'] ?? [];
    $uploadDir = '../cotizaciones/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $createdCount = 0;
    
    // Preparar inserciones
    $stmtInsertProd = $pdo->prepare("
        INSERT INTO productos (name, item_no, series, serie, categoria_id, price, img, media_json) 
        VALUES (:name, :item_no, :series, :serie, :cat, :price, :img, :media_json)
    ");

    $stmtCheckSerie = $pdo->prepare("SELECT id FROM series WHERE name = ?");
    $stmtInsertSerie = $pdo->prepare("INSERT INTO series (name, pos) VALUES (?, ?)");

    foreach ($rows as $index => $prod) {
        $name = trim($prod['name'] ?? '');
        // Si no tiene nombre, se asume fila vacía y se salta
        if (empty($name)) {
            continue;
        }

        $item_no = trim($prod['item_no'] ?? '');
        $series = trim($prod['series'] ?? '');
        $serie = trim($prod['serie'] ?? '');
        $catId = !empty($prod['categoria_id']) ? (int)$prod['categoria_id'] : null;
        $price = !empty($prod['price']) ? (float)$prod['price'] : 0;
        $img = '';

        // Auto-crear serie si no existe
        if (!empty($serie)) {
            $stmtCheckSerie->execute([$serie]);
            if (!$stmtCheckSerie->fetch()) {
                $posStmt = $pdo->query("SELECT COALESCE(MAX(pos), 0) + 1 AS next_pos FROM series");
                $nextPos = $posStmt->fetchColumn();
                $stmtInsertSerie->execute([$serie, $nextPos]);
            }
        }

        // 1. Procesar imagen Base64 (comprimida desde frontend)
        $base64 = $prod['img_base64'] ?? '';
        if (!empty($base64)) {
            $parts = explode(',', $base64);
            if (count($parts) === 2) {
                $data = base64_decode($parts[1]);
                $filename = uniqid('prod_') . '.jpg';
                $destPath = $uploadDir . $filename;
                if (file_put_contents($destPath, $data)) {
                    $img = 'uploads/' . $filename;
                }
            }
        } 
        // 2. Si no es base64, verificar si se subió como archivo tradicional
        else if (isset($_FILES['products']['name'][$index]['img_file']) && $_FILES['products']['error'][$index]['img_file'] === UPLOAD_ERR_OK) {
            $file = $_FILES['products']['name'][$index]['img_file'];
            $tmp = $_FILES['products']['tmp_name'][$index]['img_file'];
            $filename = uniqid('prod_') . '_' . basename($file);
            $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '', $filename);
            $destPath = $uploadDir . $filename;
            if (move_uploaded_file($tmp, $destPath)) {
                $img = 'uploads/' . $filename;
            }
        }

        $media_json = json_encode([['url' => $img, 'type' => 'image']]);

        $stmtInsertProd->execute([
            ':name' => $name,
            ':item_no' => $item_no,
            ':series' => $series,
            ':serie' => $serie,
            ':cat' => $catId,
            ':price' => $price,
            ':img' => $img,
            ':media_json' => $media_json
        ]);
        $createdCount++;
    }

    if ($createdCount > 0) {
        $_SESSION['flash_msg'] = "¡Éxito! Se crearon {$createdCount} nuevo(s) producto(s) en el inventario.";
        header("Location: productos.php");
        exit;
    } else {
        $flash_error = "No se crearon productos. Por favor escribe al menos el nombre de un equipo.";
    }
}

include 'header.php';
?>

<div class="panel-card" style="max-width: 1200px; margin: 0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; margin-bottom:20px; border-bottom:1px solid #eee; padding-bottom:16px;">
        <div>
            <h2 style="margin:0; font-family:'Oswald', sans-serif; text-transform:uppercase; color:#1d3557; font-size:24px; display:flex; align-items:center; gap:8px;">
                ⚡ Creación de Equipos en Masa
            </h2>
            <p style="margin:6px 0 0; color:#64748b; font-size:14px;">
                Escribe y configura varios productos simultáneamente en una sola pantalla. Las filas que dejes vacías no se crearán.
            </p>
        </div>
        <div style="display:flex; gap:12px; align-items:center;">
            <a href="productos.php" class="btn-nav" style="background:#edf2f7; color:#4a5568; padding:9px 16px; border-radius:6px; text-decoration:none; font-weight:600; font-size:13px;">
                ✕ Cancelar y Volver
            </a>
            <button type="button" onclick="document.getElementById('batchCreateForm').submit();" class="btn-primary" style="background:#2a9d8f; padding:9px 22px; font-size:14px; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                💾 Guardar Todos los Equipos
            </button>
        </div>
    </div>

    <?php if ($flash_error): ?>
        <div style="background:#fff5f5; color:#c53030; padding:12px 18px; border-radius:8px; margin-bottom:20px; border:1px solid #feb2b2;">
            ⚠️ <?= htmlspecialchars($flash_error) ?>
        </div>
    <?php endif; ?>

    <datalist id="seriesListDatalist">
        <?php foreach ($seriesList as $s): ?>
            <option value="<?= htmlspecialchars($s['name']) ?>"></option>
        <?php endforeach; ?>
    </datalist>

    <form id="batchCreateForm" method="POST" action="productos_crear_masa.php" enctype="multipart/form-data">
        <input type="hidden" name="action" value="create_batch_products">

        <div class="table-wrapper" style="box-shadow: none; border: 1px solid #e2e8f0; border-radius: 8px; overflow-x: auto;">
            <table class="products-table" style="min-width: 980px;">
                <thead>
                    <tr style="background: #f8fafc;">
                        <th style="width: 45px; text-align: center;">#</th>
                        <th style="width: 80px; text-align: center;">Foto</th>
                        <th style="min-width: 220px;">Nombre del Equipo *</th>
                        <th style="width: 140px;">SKU (Código)</th>
                        <th style="width: 160px;">Categoría</th>
                        <th style="width: 140px;">Marca</th>
                        <th style="width: 130px;">Serie / Línea</th>
                        <th style="width: 120px;">Precio</th>
                        <th style="width: 80px; text-align: center;">Acción</th>
                    </tr>
                </thead>
                <tbody id="rowsContainer">
                    <!-- Filas dinámicas generadas aquí -->
                </tbody>
            </table>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; margin-top:20px;">
            <div style="display:flex; gap:10px;">
                <button type="button" id="addRowBtn" style="background:#ffffff; color:#1d3557; border:1px solid #cbd5e1; padding:8px 16px; border-radius:6px; font-weight:600; cursor:pointer; font-size:13px; display:inline-flex; align-items:center; gap:6px;">
                    ➕ Añadir 1 Fila
                </button>
                <button type="button" id="add5RowsBtn" style="background:#ffffff; color:#1d3557; border:1px solid #cbd5e1; padding:8px 16px; border-radius:6px; font-weight:600; cursor:pointer; font-size:13px; display:inline-flex; align-items:center; gap:6px;">
                    ➕ Añadir 5 Filas
                </button>
            </div>
            <div style="font-size:13px; color:#64748b;">
                <span id="rowsCountText">5 filas disponibles</span>
            </div>
        </div>

        <!-- Barra inferior fija para guardar -->
        <div style="position:sticky; bottom:20px; margin-top:30px; background:#1d3557; padding:16px 24px; border-radius:12px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 10px 25px rgba(0,0,0,0.2); z-index:100; font-family:'Inter', sans-serif;">
            <div style="color:#ffffff; font-size:14px;">
                💡 <span style="color:#cbd5e0;">Completa los campos y haz clic en</span> <strong>Guardar Todos los Equipos</strong>.
            </div>
            <div style="display:flex; gap:12px; align-items:center;">
                <a href="productos.php" style="color:#cbd5e1; text-decoration:none; font-size:13px; font-weight:500;">Cancelar</a>
                <button type="submit" id="submitBtn" class="btn-primary" style="background:#2a9d8f; padding:10px 24px; font-size:14px; border-radius:6px; cursor:pointer; font-weight:700;">
                    💾 Guardar Todos los Equipos
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Template Opciones de Categorías y Marcas -->
<script>
const categoriasOptions = `
    <option value="">-- Sin categoría --</option>
    <?php foreach ($categorias as $c): ?>
        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
    <?php endforeach; ?>
`;

const marcasOptions = `
    <option value="">-- Sin marca --</option>
    <?php foreach ($marcas as $m): ?>
        <option value="<?= htmlspecialchars($m['name']) ?>"><?= htmlspecialchars($m['name']) ?></option>
    <?php endforeach; ?>
`;

let rowIndexCounter = 0;
const container = document.getElementById('rowsContainer');

function createRow(data = {}) {
    const idx = rowIndexCounter++;
    const tr = document.createElement('tr');
    tr.id = `row_${idx}`;
    tr.style.transition = 'background 0.2s';

    tr.innerHTML = `
        <td style="text-align: center; color: #94a3b8; font-weight: bold; font-size: 13px;" class="row-number">
            ${container.children.length + 1}
        </td>
        <td style="text-align: center; padding: 6px;">
            <input type="hidden" name="products[${idx}][img_base64]" id="img_base64_${idx}" value="">
            <div id="dropzone_${idx}" class="row-dropzone" style="cursor: pointer; display: inline-flex; align-items: center; justify-content: center; width: 54px; height: 46px; border: 2px dashed #cbd5e1; border-radius: 6px; background: #ffffff; position: relative; overflow: hidden; transition: all 0.2s;" title="Haz clic o arrastra una foto aquí">
                <input type="file" accept="image/*" style="position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer;" onchange="handleRowFile(${idx}, this.files[0])">
                <img id="preview_${idx}" src="" style="display: none; width: 100%; height: 100%; object-fit: contain;">
                <span id="icon_${idx}" style="font-size: 20px; color: #94a3b8;">📷</span>
            </div>
        </td>
        <td style="padding: 6px;">
            <input type="text" name="products[${idx}][name]" value="${data.name || ''}" placeholder="ej: Caminadora Profesional T90" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; box-sizing: border-box;" required>
        </td>
        <td style="padding: 6px;">
            <input type="text" name="products[${idx}][item_no]" value="${data.item_no || ''}" placeholder="ej: TM-90" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; box-sizing: border-box;">
        </td>
        <td style="padding: 6px;">
            <select name="products[${idx}][categoria_id]" style="width: 100%; padding: 8px 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; box-sizing: border-box; background: #fff;">
                ${categoriasOptions}
            </select>
        </td>
        <td style="padding: 6px;">
            <select name="products[${idx}][series]" style="width: 100%; padding: 8px 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; box-sizing: border-box; background: #fff;">
                ${marcasOptions}
            </select>
        </td>
        <td style="padding: 6px;">
            <input type="text" name="products[${idx}][serie]" value="${data.serie || ''}" list="seriesListDatalist" placeholder="ej: M7Pro" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; box-sizing: border-box;">
        </td>
        <td style="padding: 6px;">
            <input type="number" step="0.01" name="products[${idx}][price]" value="${data.price || ''}" placeholder="0.00" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; box-sizing: border-box;">
        </td>
        <td style="text-align: center; padding: 6px; white-space: nowrap;">
            <button type="button" onclick="duplicateRow(${idx})" title="Duplicar esta fila" style="background: none; border: 1px solid #cbd5e1; border-radius: 4px; padding: 5px 8px; cursor: pointer; color: #457b9d; margin-right: 4px;">📋</button>
            <button type="button" onclick="removeRow(${idx})" title="Eliminar fila" style="background: none; border: 1px solid #fecaca; border-radius: 4px; padding: 5px 8px; cursor: pointer; color: #dc2626;">🗑️</button>
        </td>
    `;

    // Asignar selects si vienen datos
    if (data.categoria_id) {
        tr.querySelector(`select[name="products[${idx}][categoria_id]"]`).value = data.categoria_id;
    }
    if (data.series) {
        tr.querySelector(`select[name="products[${idx}][series]"]`).value = data.series;
    }

    container.appendChild(tr);
    attachDropzoneEvents(idx);
    updateRowNumbers();
}

function handleRowFile(idx, file) {
    if (!file) return;
    if (!file.type.startsWith('image/')) {
        alert("Por favor selecciona un archivo de imagen válido.");
        return;
    }

    const reader = new FileReader();
    reader.readAsDataURL(file);
    reader.onload = function(event) {
        const img = new Image();
        img.src = event.target.result;
        img.onload = function() {
            const MAX_WIDTH = 1200;
            const MAX_HEIGHT = 1200;
            let width = img.width;
            let height = img.height;

            if (width > height) {
                if (width > MAX_WIDTH) {
                    height *= MAX_WIDTH / width;
                    width = MAX_WIDTH;
                }
            } else {
                if (height > MAX_HEIGHT) {
                    width *= MAX_HEIGHT / height;
                    height = MAX_HEIGHT;
                }
            }

            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, width, height);

            const dataUrl = canvas.toDataURL('image/jpeg', 0.82);

            document.getElementById('img_base64_' + idx).value = dataUrl;
            
            const prevImg = document.getElementById('preview_' + idx);
            const icon = document.getElementById('icon_' + idx);
            const dropzone = document.getElementById('dropzone_' + idx);
            prevImg.src = dataUrl;
            prevImg.style.display = 'block';
            icon.style.display = 'none';
            if (dropzone) {
                dropzone.style.borderColor = '#16a34a';
                dropzone.style.background = '#f0fdf4';
            }
        };
    };
}

function attachDropzoneEvents(idx) {
    const dropzone = document.getElementById(`dropzone_${idx}`);
    if (!dropzone) return;

    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.style.borderColor = '#2a9d8f';
            dropzone.style.background = '#e6fffa';
            dropzone.style.transform = 'scale(1.08)';
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.style.transform = 'scale(1)';
            const hasImg = !!document.getElementById(`img_base64_${idx}`).value;
            dropzone.style.borderColor = hasImg ? '#16a34a' : '#cbd5e1';
            dropzone.style.background = hasImg ? '#f0fdf4' : '#ffffff';
        }, false);
    });

    dropzone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files && files.length > 0) {
            handleRowFile(idx, files[0]);
        }
    });
}

function duplicateRow(idx) {
    const origRow = document.getElementById(`row_${idx}`);
    if (!origRow) return;

    const data = {
        name: origRow.querySelector(`input[name="products[${idx}][name]"]`).value,
        item_no: origRow.querySelector(`input[name="products[${idx}][item_no]"]`).value,
        categoria_id: origRow.querySelector(`select[name="products[${idx}][categoria_id]"]`).value,
        series: origRow.querySelector(`select[name="products[${idx}][series]"]`).value,
        serie: origRow.querySelector(`input[name="products[${idx}][serie]"]`).value,
        price: origRow.querySelector(`input[name="products[${idx}][price]"]`).value,
    };
    createRow(data);
}

function removeRow(idx) {
    const row = document.getElementById(`row_${idx}`);
    if (container.children.length <= 1) {
        alert("Debe haber al menos 1 fila disponible.");
        return;
    }
    if (row) {
        row.remove();
        updateRowNumbers();
    }
}

function updateRowNumbers() {
    const rows = container.querySelectorAll('tr');
    rows.forEach((r, i) => {
        const numTd = r.querySelector('.row-number');
        if (numTd) numTd.textContent = i + 1;
    });
    const countText = document.getElementById('rowsCountText');
    if (countText) countText.textContent = `${rows.length} fila(s) disponibles`;
}

document.addEventListener("DOMContentLoaded", function() {
    // Inicializar con 5 filas vacías
    for (let i = 0; i < 5; i++) {
        createRow();
    }

    document.getElementById('addRowBtn').addEventListener('click', () => createRow());
    document.getElementById('add5RowsBtn').addEventListener('click', () => {
        for (let i = 0; i < 5; i++) createRow();
    });

    const form = document.getElementById('batchCreateForm');
    form.addEventListener('submit', function(e) {
        // Validar que al menos una fila tenga nombre
        const names = Array.from(document.querySelectorAll('input[name*="[name]"]'))
                           .map(input => input.value.trim())
                           .filter(val => val !== '');
        
        if (names.length === 0) {
            e.preventDefault();
            alert("Por favor ingresa el nombre de al menos un producto para guardar.");
            return;
        }

        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = "⏳ Guardando productos...";
            submitBtn.style.opacity = '0.7';
        }
    });
});
</script>
</body>
</html>
