<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
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

// Obtener los IDs solicitados
$rawIds = $_POST['selected_ids'] ?? $_GET['ids'] ?? '';
$ids = [];
if (!empty($rawIds)) {
    if (is_array($rawIds)) {
        $ids = array_filter(array_map('intval', $rawIds));
    } else {
        $ids = array_filter(array_map('intval', explode(',', $rawIds)));
    }
}

// Si es un POST para guardar cambios
$updatedCount = 0;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_batch_images') {
    $uploadDir = '../cotizaciones/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $productIds = $_POST['product_ids'] ?? [];
    if (!empty($productIds)) {
        $updateStmt = $pdo->prepare("UPDATE productos SET img = ?, media_json = ? WHERE id = ?");

        foreach ($productIds as $pid) {
            $pid = (int)$pid;
            $newImgPath = null;

            // 1. Verificar si viene imagen optimizada en Base64
            $base64Key = 'img_base64_' . $pid;
            if (!empty($_POST[$base64Key])) {
                $base64_string = $_POST[$base64Key];
                $parts = explode(',', $base64_string);
                if (count($parts) === 2) {
                    $data = base64_decode($parts[1]);
                    $filename = uniqid('prod_') . '.jpg';
                    $destPath = $uploadDir . $filename;
                    if (file_put_contents($destPath, $data)) {
                        $newImgPath = 'uploads/' . $filename;
                    } else {
                        $errors[] = "Error al guardar imagen del producto ID: $pid";
                    }
                }
            } 
            // 2. Si no es base64, verificar si se subió archivo normal
            else if (isset($_FILES['img_file_' . $pid]) && $_FILES['img_file_' . $pid]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['img_file_' . $pid];
                $filename = uniqid('prod_') . '_' . basename($file['name']);
                $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '', $filename);
                $destPath = $uploadDir . $filename;
                if (move_uploaded_file($file['tmp_name'], $destPath)) {
                    $newImgPath = 'uploads/' . $filename;
                } else {
                    $errors[] = "Error al mover imagen subida del producto ID: $pid";
                }
            }

            // Si se procesó una nueva imagen, actualizar registro
            if ($newImgPath) {
                $mediaJson = json_encode([['url' => $newImgPath, 'type' => 'image']]);
                $updateStmt->execute([$newImgPath, $mediaJson, $pid]);
                $updatedCount++;
            }
        }
    }

    $_SESSION['flash_msg'] = "Se actualizaron con éxito las imágenes de {$updatedCount} producto(s).";
    header("Location: productos.php");
    exit;
}

// Cargar la información de los productos seleccionados
$productos = [];
if (!empty($ids)) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as categoria_nombre 
        FROM productos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id 
        WHERE p.id IN ($placeholders) 
        ORDER BY p.name ASC
    ");
    $stmt->execute(array_values($ids));
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include 'header.php';
?>

<div class="panel-card" style="max-width: 1050px; margin: 0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; margin-bottom:24px; border-bottom:1px solid #eee; padding-bottom:16px;">
        <div>
            <h2 style="margin:0; font-family:'Oswald', sans-serif; text-transform:uppercase; color:#1d3557; font-size:24px; display:flex; align-items:center; gap:8px;">
                🖼️ Actualización de Imágenes por Producto
            </h2>
            <p style="margin:6px 0 0; color:#64748b; font-size:14px;">
                Carga o arrastra una nueva imagen para cada uno de los <strong><?= count($productos) ?></strong> productos seleccionados. Los que dejes sin cambiar conservarán su imagen actual.
            </p>
        </div>
        <div style="display:flex; gap:12px;">
            <a href="productos.php" class="btn-nav" style="background:#edf2f7; color:#4a5568; padding:9px 16px; border-radius:6px; text-decoration:none; font-weight:600; font-size:13px; display:inline-flex; align-items:center; gap:6px;">
                ✕ Cancelar y Volver
            </a>
            <?php if (!empty($productos)): ?>
                <button type="button" onclick="document.getElementById('batchImagesForm').submit();" class="btn-primary" style="background:#2a9d8f; padding:9px 20px; font-size:14px; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                    💾 Guardar Todas las Imágenes
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($productos)): ?>
        <div style="text-align:center; padding:50px 20px; background:#f8fafc; border:2px dashed #cbd5e1; border-radius:12px; margin:20px 0;">
            <span style="font-size:42px; display:block; margin-bottom:12px;">🔍</span>
            <h3 style="margin:0 0 8px; color:#1e293b; font-family:'Oswald',sans-serif; text-transform:uppercase;">No seleccionaste productos</h3>
            <p style="color:#64748b; font-size:14px; margin-bottom:20px;">Regresa al inventario de productos y marca las casillas de los equipos a los que deseas actualizar la imagen.</p>
            <a href="productos.php" class="btn-primary" style="background:#457b9d; display:inline-block;">Ir al Catálogo de Productos</a>
        </div>
    <?php else: ?>
        <form id="batchImagesForm" method="POST" action="productos_imagenes.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save_batch_images">
            
            <div style="display: flex; flex-direction: column; gap: 18px;">
                <?php foreach ($productos as $p): ?>
                    <?php 
                        $pid = $p['id'];
                        $imgSrc = getProductImgUrl($p['img'] ?? '', $p['media_json'] ?? '');
                    ?>
                    <input type="hidden" name="product_ids[]" value="<?= $pid ?>">
                    <input type="hidden" name="img_base64_<?= $pid ?>" id="img_base64_<?= $pid ?>" value="">

                    <div class="product-image-card" style="display:flex; flex-wrap:wrap; gap:20px; align-items:center; background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:18px; box-shadow:0 2px 6px rgba(0,0,0,0.03); transition:border-color 0.2s;">
                        <!-- Columna Info del Producto -->
                        <div style="flex: 1 1 280px; min-width: 250px;">
                            <div style="font-size:12px; font-weight:700; color:#457b9d; text-transform:uppercase; margin-bottom:4px; letter-spacing:0.04em;">
                                SKU: <?= htmlspecialchars($p['item_no'] ?: 'Sin código') ?>
                            </div>
                            <h4 style="margin:0 0 8px; font-size:16px; color:#1d3557; line-height:1.3; font-weight:600;">
                                <?= htmlspecialchars($p['name']) ?>
                            </h4>
                            <div style="font-size:12px; color:#64748b; display:flex; gap:10px; flex-wrap:wrap;">
                                <span>📁 <?= htmlspecialchars($p['categoria_nombre'] ?: 'Sin categoría') ?></span>
                                <span>🏷️ <?= htmlspecialchars($p['series'] ?: 'Sin marca') ?><?= !empty($p['serie']) ? ' (' . htmlspecialchars($p['serie']) . ')' : '' ?></span>
                            </div>
                        </div>

                        <!-- Columna Imagen Actual -->
                        <div style="text-align:center; flex: 0 0 110px;">
                            <span style="display:block; font-size:11px; font-weight:600; color:#94a3b8; text-transform:uppercase; margin-bottom:6px;">Actual</span>
                            <div style="width:100px; height:85px; border:1px solid #e2e8f0; border-radius:6px; background:#f8fafc; display:flex; align-items:center; justify-content:center; overflow:hidden; padding:4px; box-sizing:border-box;">
                                <?php if ($imgSrc): ?>
                                    <img src="<?= htmlspecialchars($imgSrc) ?>" style="max-width:100%; max-height:100%; object-fit:contain;" alt="<?= htmlspecialchars($p['name']) ?>">
                                <?php else: ?>
                                    <span style="font-size:10px; color:#cbd5e1;">Sin imagen</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Flecha indicadora -->
                        <div style="color:#cbd5e1; font-size:20px; display:flex; align-items:center;">
                            ➜
                        </div>

                        <!-- Columna Nueva Imagen (Dropzone individual) -->
                        <div style="flex: 2 1 320px; min-width: 280px;">
                            <span style="display:block; font-size:11px; font-weight:700; color:#2a9d8f; text-transform:uppercase; margin-bottom:6px;">Nueva Imagen</span>
                            
                            <div id="dropzone_<?= $pid ?>" class="custom-dropzone" style="border:2px dashed #cbd5e1; border-radius:8px; padding:12px; text-align:center; background:#fafafa; cursor:pointer; position:relative; transition:all 0.2s;">
                                <input type="file" id="file_input_<?= $pid ?>" name="img_file_<?= $pid ?>" accept="image/*" style="position:absolute; inset:0; width:100%; height:100%; opacity:0; cursor:pointer;" onchange="handleSingleFile(<?= $pid ?>, this.files[0])">
                                
                                <div id="prompt_<?= $pid ?>" style="display:flex; align-items:center; justify-content:center; gap:10px; color:#64748b; font-size:13px;">
                                    <span style="font-size:22px;">📤</span>
                                    <span><strong>Haz clic o arrastra</strong> una imagen nueva</span>
                                </div>

                                <div id="preview_wrap_<?= $pid ?>" style="display:none; align-items:center; justify-content:space-between; gap:12px;">
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <div style="width:60px; height:50px; background:#fff; border:1px solid #cbd5e1; border-radius:4px; display:flex; align-items:center; justify-content:center; overflow:hidden;">
                                            <img id="preview_img_<?= $pid ?>" src="" style="max-width:100%; max-height:100%; object-fit:contain;">
                                        </div>
                                        <div style="text-align:left;">
                                            <span style="display:block; font-size:12px; font-weight:700; color:#16a34a;">✅ Nueva imagen lista</span>
                                            <span id="preview_name_<?= $pid ?>" style="font-size:11px; color:#64748b;">Optimizada</span>
                                        </div>
                                    </div>
                                    <button type="button" onclick="cancelSingleImage(<?= $pid ?>, event)" style="background:#fee2e2; border:1px solid #fecaca; color:#b91c1c; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:600; cursor:pointer;">
                                        Deshacer
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Barra inferior flotante para guardar -->
            <div style="position:sticky; bottom:20px; margin-top:30px; background:#1d3557; padding:16px 24px; border-radius:12px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 10px 25px rgba(0,0,0,0.2); z-index:100;">
                <div style="color:#ffffff; font-size:14px; font-family:'Inter', sans-serif;">
                    <span id="readyCountBadge" style="background:#2a9d8f; color:white; padding:3px 10px; border-radius:12px; font-weight:bold; margin-right:8px;">0</span> imágenes preparadas para guardar
                </div>
                <div style="display:flex; gap:12px; align-items:center;">
                    <a href="productos.php" style="color:#cbd5e1; text-decoration:none; font-size:13px; font-weight:500;">Cancelar</a>
                    <button type="submit" id="submitBtn" class="btn-primary" style="background:#2a9d8f; padding:10px 24px; font-size:14px; border-radius:6px; cursor:pointer; font-weight:700;">
                        💾 Guardar Cambios
                    </button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
// Contador de imágenes preparadas
const updatedProducts = new Set();

function updateReadyCount() {
    const badge = document.getElementById('readyCountBadge');
    if (badge) {
        badge.textContent = updatedProducts.size;
    }
}

function handleSingleFile(pid, file) {
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

            // Comprimir a JPEG con calidad 80%
            const dataUrl = canvas.toDataURL('image/jpeg', 0.82);

            // Asignar al input oculto
            document.getElementById('img_base64_' + pid).value = dataUrl;

            // Mostrar vista previa
            document.getElementById('preview_img_' + pid).src = dataUrl;
            document.getElementById('prompt_' + pid).style.display = 'none';
            document.getElementById('preview_wrap_' + pid).style.display = 'flex';
            
            const dropzone = document.getElementById('dropzone_' + pid);
            dropzone.style.borderColor = '#16a34a';
            dropzone.style.background = '#f0fdf4';

            updatedProducts.add(pid);
            updateReadyCount();
        };
    };
}

function cancelSingleImage(pid, e) {
    e.preventDefault();
    e.stopPropagation();

    document.getElementById('img_base64_' + pid).value = '';
    document.getElementById('file_input_' + pid).value = '';
    document.getElementById('preview_img_' + pid).src = '';
    document.getElementById('prompt_' + pid).style.display = 'flex';
    document.getElementById('preview_wrap_' + pid).style.display = 'none';

    const dropzone = document.getElementById('dropzone_' + pid);
    dropzone.style.borderColor = '#cbd5e1';
    dropzone.style.background = '#fafafa';

    updatedProducts.delete(pid);
    updateReadyCount();
}

// Drag & drop listeners para cada dropzone
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('.custom-dropzone').forEach(dropzone => {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.style.borderColor = '#2a9d8f';
                dropzone.style.background = '#e6fffa';
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                const pid = dropzone.id.replace('dropzone_', '');
                if (!updatedProducts.has(parseInt(pid))) {
                    dropzone.style.borderColor = '#cbd5e1';
                    dropzone.style.background = '#fafafa';
                }
            }, false);
        });

        dropzone.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files.length > 0) {
                const pid = dropzone.id.replace('dropzone_', '');
                handleSingleFile(pid, files[0]);
            }
        });
    });

    const form = document.getElementById('batchImagesForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (updatedProducts.size === 0) {
                if (!confirm("No has seleccionado ninguna imagen nueva. ¿Deseas volver al inventario?")) {
                    e.preventDefault();
                    return;
                }
            }
            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = "⏳ Guardando imágenes...";
                submitBtn.style.opacity = '0.7';
            }
        });
    }
});
</script>
</body>
</html>
