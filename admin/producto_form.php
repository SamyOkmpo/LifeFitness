<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

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

$id = $_GET['id'] ?? null;
$producto = ['name' => '', 'item_no' => '', 'series' => '', 'categoria_id' => '', 'price' => 0, 'img' => '', 'media_json' => '[]'];
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $item_no = $_POST['item_no'] ?? '';
    $series = $_POST['series'] ?? '';
    $categoria_id = $_POST['categoria_id'] ?? null;
    if ($categoria_id === '') $categoria_id = null;
    $price = $_POST['price'] ?? 0;

    $img = $_POST['img'] ?? '';
    
    $uploadErrorMsg = '';
    
    // File upload logic (Base64 from Drag & Drop / Resize)
    if (!empty($_POST['img_base64'])) {
        $uploadDir = '../cotizaciones/uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $base64_string = $_POST['img_base64'];
        $parts = explode(',', $base64_string);
        if (count($parts) === 2) {
            $data = base64_decode($parts[1]);
            $filename = uniqid('prod_') . '.jpg';
            $destPath = $uploadDir . $filename;
            if (file_put_contents($destPath, $data)) {
                $img = 'uploads/' . $filename;
            } else {
                $uploadErrorMsg = "Error al guardar la imagen procesada.";
            }
        }
    } 
    // Fallback normal file upload
    else if (isset($_FILES['img_file']) && $_FILES['img_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['img_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../cotizaciones/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $filename = uniqid('prod_') . '_' . basename($_FILES['img_file']['name']);
            $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '', $filename);
            $destPath = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['img_file']['tmp_name'], $destPath)) {
                $img = 'uploads/' . $filename;
            } else {
                $uploadErrorMsg = "Error al mover el archivo subido al servidor.";
            }
        } else {
            $uploadErrorMsg = "Error al subir la imagen. Código de error PHP: " . $_FILES['img_file']['error'] . " (Probablemente la imagen es muy pesada).";
        }
    }
    
    if (false) { // dummy to keep bracket balance
        $uploadDir = '../cotizaciones/uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $filename = uniqid('prod_') . '_' . basename($_FILES['img_file']['name']);
        // sanitize filename
        $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '', $filename);
        $destPath = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['img_file']['tmp_name'], $destPath)) {
            // Save the relative path just like React does
            $img = 'uploads/' . $filename;
        }
    }

    $media_json = json_encode([['url' => $img, 'type' => 'image']]);

    if ($id) {
        $stmt = $pdo->prepare("UPDATE productos SET name=:name, item_no=:item_no, series=:series, categoria_id=:cat, price=:price, img=:img, media_json=:media_json WHERE id=:id");
        $stmt->execute(['name'=>$name, 'item_no'=>$item_no, 'series'=>$series, 'cat'=>$categoria_id, 'price'=>$price, 'img'=>$img, 'media_json'=>$media_json, 'id'=>$id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO productos (name, item_no, series, categoria_id, price, img, media_json) VALUES (:name, :item_no, :series, :cat, :price, :img, :media_json)");
        $stmt->execute(['name'=>$name, 'item_no'=>$item_no, 'series'=>$series, 'cat'=>$categoria_id, 'price'=>$price, 'img'=>$img, 'media_json'=>$media_json]);
        $id = $pdo->lastInsertId();
    }
    $success = true;
}
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC) ?: $producto;
}
$stmt = $pdo->query("SELECT * FROM categorias ORDER BY pos ASC");
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $pdo->query("SELECT * FROM marcas ORDER BY name ASC");
$marcas = $stmt->fetchAll(PDO::FETCH_ASSOC);
include 'header.php';
?>
<div class="panel-card" style="max-width:600px; margin: 0 auto;">
    <h2 style="margin-top:0;font-family:Oswald;text-transform:uppercase;color:#1d3557;border-bottom:1px solid #eee;padding-bottom:12px"><?= $id ? 'Editar Equipo' : 'Nuevo Equipo' ?></h2>
    <?php if ($success): ?><div style="background:#e6fffa;color:#234e52;padding:12px;border-radius:6px;margin-bottom:20px;border:1px solid #b2f5ea;">✅ Los cambios se han guardado correctamente.</div><?php endif; ?>
    <?php if (!empty($uploadErrorMsg)): ?><div style="background:#fff5f5;color:#c53030;padding:12px;border-radius:6px;margin-bottom:20px;border:1px solid #feb2b2;">❌ <?= $uploadErrorMsg ?></div><?php endif; ?>
    <form method="POST" action="" enctype="multipart/form-data">
        <div class="form-group">
            <label>Nombre del Equipo</label>
            <input type="text" name="name" value="<?= htmlspecialchars($producto['name'] ?? '') ?>" required>
        </div>
        <div style="display:flex; gap:16px; flex-wrap:wrap;">
            <div class="form-group" style="flex:1;">
                <label>SKU (Código)</label>
                <input type="text" name="item_no" value="<?= htmlspecialchars($producto['item_no'] ?? '') ?>">
            </div>
            <div class="form-group" style="flex:1;">
                <label>Precio</label>
                <input type="number" step="0.01" name="price" value="<?= htmlspecialchars($producto['price'] ?? 0) ?>">
            </div>
        </div>
        <div style="display:flex; gap:16px; flex-wrap:wrap;">
            <div class="form-group" style="flex:1;">
                <label>Marca (Series)</label>
                <select name="series">
                    <option value="">-- Sin Marca --</option>
                    <?php foreach ($marcas as $m): ?>
                        <option value="<?= htmlspecialchars($m['name']) ?>" <?= ($producto['series']===$m['name']) ? 'selected' : '' ?>><?= htmlspecialchars($m['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div style="font-size:11px;color:#718096;margin-top:4px;">* Las marcas se crean en el panel de Marcas</div>
            </div>
            <div class="form-group" style="flex:1;">
                <label>Categoría Web</label>
                <select name="categoria_id">
                    <option value="">-- Sin Categoría --</option>
                    <?php foreach ($categorias as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($producto['categoria_id']==$c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div style="font-size:11px;color:#718096;margin-top:4px;">* Para el catálogo de la página web</div>
            </div>
        </div>
        <div class="form-group" style="background:#f9f9f9; padding:16px; border:1px solid #eee; border-radius:8px;">
            <label style="color:#1d3557; margin-bottom:12px; display:block; font-weight:bold;">📷 Foto del Equipo</label>
            <div style="display:flex; gap:16px; align-items:flex-start;">
                <?php $imgSrc = getProductImgUrl($producto['img'] ?? '', $producto['media_json'] ?? ''); ?>
                <?php if ($producto['img']): ?>
                    <div style="width:120px; height:120px; border-radius:6px; border:1px solid #ccc; background:#fff; display:flex; align-items:center; justify-content:center; overflow:hidden; flex-shrink:0; padding:4px;">
                        <img src="<?= htmlspecialchars($imgSrc) ?>" style="max-width:100%; max-height:100%; object-fit:contain;" alt="Imagen rota o no encontrada">
                    </div>
                <?php endif; ?>
                
                <div style="flex:1;">
                    <div id="dropzone" style="margin-bottom:16px; border:2px dashed #a0aec0; border-radius:8px; padding:20px; text-align:center; background:#fff; transition:all 0.3s ease; position:relative;">
                        <div id="dropzone-text" style="color:#4a5568; font-size:14px; margin-bottom:8px;">
                            <strong>Arrastra una imagen aquí</strong> o haz clic para buscarla.<br>
                            <span style="font-size:12px; color:#718096;">Se comprimirá automáticamente para cargar rápido.</span>
                        </div>
                        <input type="file" id="img_file_input" name="img_file" accept="image/*" style="position:absolute; inset:0; width:100%; height:100%; opacity:0; cursor:pointer;">
                        <input type="hidden" id="img_base64" name="img_base64" value="">
                        
                        <div id="preview-container" style="display:none; margin-top:12px;">
                            <img id="preview-img" src="" style="max-height:120px; max-width:100%; object-fit:contain; border-radius:4px; border:1px solid #e2e8f0;">
                            <div style="font-size:12px; color:#38a169; margin-top:4px; font-weight:bold;">¡Imagen optimizada lista para guardar!</div>
                        </div>
                    </div>
                    
                    <div>
                        <label style="font-size:12px; font-weight:normal; margin-bottom:4px;">O usar una URL existente (Ruta manual):</label>
                        <input type="text" name="img" id="img_url_input" value="<?= htmlspecialchars($producto['img'] ?? '') ?>" placeholder="ej: uploads/imagen.jpg">
                    </div>
                </div>
            </div>
        </div>
        <div style="margin-top: 24px; display:flex; gap:12px;">
            <button type="submit" class="btn-primary">Guardar Cambios</button>
            <a href="productos.php" class="btn-nav">Volver al Inventario</a>
        </div>
    </form>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('img_file_input');
    const base64Input = document.getElementById('img_base64');
    const previewContainer = document.getElementById('preview-container');
    const previewImg = document.getElementById('preview-img');
    const dropzoneText = document.getElementById('dropzone-text');

    // Highlight on drag
    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, preventDefaults, false);
        dropzone.addEventListener(eventName, () => dropzone.style.background = '#e6fffa', false);
        dropzone.addEventListener(eventName, () => dropzone.style.borderColor = '#319795', false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, preventDefaults, false);
        dropzone.addEventListener(eventName, () => dropzone.style.background = '#fff', false);
        dropzone.addEventListener(eventName, () => dropzone.style.borderColor = '#a0aec0', false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    dropzone.addEventListener('drop', function(e) {
        let dt = e.dataTransfer;
        let files = dt.files;
        if (files.length > 0) handleFile(files[0]);
    });

    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) handleFile(this.files[0]);
    });

    function handleFile(file) {
        if (!file.type.startsWith('image/')) return alert("Por favor sube solo imágenes.");
        
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

                // Compress to JPEG with 80% quality
                const dataUrl = canvas.toDataURL('image/jpeg', 0.8);
                
                base64Input.value = dataUrl;
                previewImg.src = dataUrl;
                previewContainer.style.display = 'block';
                dropzoneText.style.display = 'none';
                
                // Clear the actual file input so the server only processes the base64
                fileInput.value = '';
            }
        }
    }
});
</script>
</body>
</html>

