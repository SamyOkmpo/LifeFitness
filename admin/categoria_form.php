<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$id = $_GET['id'] ?? null;
$item = ['name' => '', 'slug' => '', 'tag' => '', 'img' => '', 'badge' => '', 'pos' => 0, 'mostrar_home' => 1];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $name));
    $tag = $_POST['tag'] ?? '';
    $img = $_POST['img'] ?? '';
    $badge = $_POST['badge'] ?? '';
    $pos = $_POST['pos'] ?? 0;
    $mostrar_home = isset($_POST['mostrar_home']) ? 1 : 0;
    
    // File upload logic
    if (isset($_FILES['img_file']) && $_FILES['img_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $filename = uniqid('cat_') . '_' . basename($_FILES['img_file']['name']);
        // sanitize filename
        $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '', $filename);
        $destPath = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['img_file']['tmp_name'], $destPath)) {
            // Save the relative path just like React does
            $img = 'uploads/' . $filename;
        }
    }

    if ($id) {
        $stmt = $pdo->prepare("UPDATE categorias SET name=?, slug=?, tag=?, img=?, badge=?, pos=?, mostrar_home=? WHERE id=?");
        $stmt->execute([$name, $slug, $tag, $img, $badge, $pos, $mostrar_home, $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO categorias (name, slug, tag, img, badge, pos, mostrar_home) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $slug, $tag, $img, $badge, $pos, $mostrar_home]);
        $id = $pdo->lastInsertId();
    }
    $success = true;
}

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM categorias WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC) ?: $item;
}
include 'header.php';
?>
<div class="panel-card" style="max-width:600px; margin: 0 auto;">
    <h2 style="margin-top:0;font-family:Oswald;text-transform:uppercase;color:#1d3557;border-bottom:1px solid #eee;padding-bottom:12px"><?= $id ? 'Editar Categoría' : 'Nueva Categoría' ?></h2>
    <?php if ($success): ?><div style="background:#e6fffa;color:#234e52;padding:12px;border-radius:6px;margin-bottom:20px;border:1px solid #b2f5ea;">✅ Guardado correctamente.</div><?php endif; ?>
    
    <form method="POST" action="" enctype="multipart/form-data">
        <div class="form-group">
            <label>Nombre de la Categoría</label>
            <input type="text" name="name" value="<?= htmlspecialchars($item['name']) ?>" required>
        </div>
        <div class="form-group">
            <label>Etiqueta (Subtítulo descriptivo)</label>
            <input type="text" name="tag" value="<?= htmlspecialchars($item['tag']) ?>">
        </div>
        
        <div class="form-group" style="background:#f9f9f9; padding:16px; border:1px solid #eee; border-radius:8px;">
            <label style="color:#1d3557;">📷 Imagen de Portada</label>
            <div style="display:flex; gap:16px; align-items:center; margin-top:8px;">
                <?php if ($item['img']): ?>
                    <div style="width:80px; height:80px; border-radius:6px; border:1px solid #ccc; background:#fff; display:flex; align-items:center; justify-content:center; overflow:hidden; flex-shrink:0;">
                        <img src="../<?= htmlspecialchars($item['img']) ?>" style="max-width:100%; max-height:100%; object-fit:cover;" onerror="this.style.display='none'">
                    </div>
                <?php endif; ?>
                <div style="flex:1;">
                    <label style="font-size:11px; font-weight:normal; margin-bottom:4px;">Subir nueva imagen:</label>
                    <input type="file" name="img_file" accept="image/*" style="background:#fff;">
                    
                    <div style="margin-top:12px;">
                        <label style="font-size:11px; font-weight:normal; margin-bottom:4px;">O usar una URL directa (Ruta):</label>
                        <input type="text" name="img" value="<?= htmlspecialchars($item['img']) ?>" placeholder="ej: assets/cat_eliptica.webp">
                    </div>
                </div>
            </div>
        </div>

        
        <div class="form-group" style="background:#f0f7ff; border:1px solid #cce3f8; border-radius:8px; padding:16px; margin-top:16px;">
            <label style="display:flex; align-items:center; gap:10px; margin:0; cursor:pointer; font-weight:600; color:#1d3557; font-size:14px;">
                <input type="checkbox" name="mostrar_home" value="1" <?= (!isset($item['mostrar_home']) || $item['mostrar_home'] == 1) ? 'checked' : '' ?> style="width:20px; height:20px; cursor:pointer; margin:0;">
                <span>🌟 Mostrar en la página de inicio (Sección "Equipos Exclusivos")</span>
            </label>
            <div style="font-size:12px; color:#4a5568; margin-top:6px; margin-left:30px;">
                Si está activado, aparecerá en la cuadrícula de inicio. Si lo desactivas, se oculta del inicio pero seguirá disponible en el catálogo completo.
            </div>
        </div>

        <div class="form-group" style="display:flex;gap:16px; margin-top:16px;">
            <div style="flex:1">
                <label>Badge (Número/Texto esquina)</label>
                <input type="text" name="badge" value="<?= htmlspecialchars($item['badge']) ?>">
            </div>
            <div style="flex:1">
                <label>Orden (Posición)</label>
                <input type="number" name="pos" value="<?= htmlspecialchars($item['pos']) ?>">
            </div>
        </div>
        <div style="margin-top:24px; display:flex; gap:12px;">
            <button type="submit" class="btn-primary">Guardar Cambios</button>
            <a href="categorias.php" class="btn-nav">Volver</a>
        </div>
    </form>
</div>
</body></html>
