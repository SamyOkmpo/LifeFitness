<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$id = $_GET['id'] ?? null;
$item = ['name' => '', 'categoria_id' => '', 'pos' => 0];
$success = false;
$error = '';

$categorias = $pdo->query("SELECT id, name FROM categorias ORDER BY pos ASC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $categoria_id = !empty($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : null;
    $pos = (int)($_POST['pos'] ?? 0);

    if ($name === '') {
        $error = 'El nombre de la serie no puede estar vacío.';
    } else {
        try {
            if ($id) {
                // Check if name changed to update products referencing old name
                $stmtOld = $pdo->prepare("SELECT name FROM series WHERE id = ?");
                $stmtOld->execute([$id]);
                $oldName = $stmtOld->fetchColumn();

                $stmt = $pdo->prepare("UPDATE series SET name = ?, categoria_id = ?, pos = ? WHERE id = ?");
                $stmt->execute([$name, $categoria_id, $pos, $id]);

                if ($oldName && $oldName !== $name) {
                    $stmtProd = $pdo->prepare("UPDATE productos SET serie = ? WHERE serie = ?");
                    $stmtProd->execute([$name, $oldName]);
                }
            } else {
                $stmt = $pdo->prepare("INSERT INTO series (name, categoria_id, pos) VALUES (?, ?, ?)");
                $stmt->execute([$name, $categoria_id, $pos]);
                $id = $pdo->lastInsertId();
            }
            $success = true;
        } catch (Exception $e) {
            $error = 'Error al guardar la serie: ' . $e->getMessage();
        }
    }
}

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM series WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC) ?: $item;
}
include 'header.php';
?>
<div class="panel-card" style="max-width:550px; margin: 0 auto;">
    <h2 style="margin-top:0;font-family:Oswald;text-transform:uppercase;color:#1d3557;border-bottom:1px solid #eee;padding-bottom:12px"><?= $id ? 'Editar Serie' : 'Nueva Serie' ?></h2>
    <?php if ($success): ?><div style="background:#e6fffa;color:#234e52;padding:12px;border-radius:6px;margin-bottom:20px;border:1px solid #b2f5ea;">✅ Guardado correctamente.</div><?php endif; ?>
    <?php if ($error): ?><div style="background:#fff5f5;color:#c53030;padding:12px;border-radius:6px;margin-bottom:20px;border:1px solid #feb2b2;">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    
    <form method="POST" action="">
        <div class="form-group" style="margin-bottom:16px;">
            <label style="display:block; font-weight:600; margin-bottom:6px; color:#1d3557;">Categoría a la que pertenece esta Serie</label>
            <select name="categoria_id" style="width:100%; padding:10px 12px; border:1px solid #ccc; border-radius:6px; font-size:14px; background:#fff;">
                <option value="">- Sin categoría específica (Todas) -</option>
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($item['categoria_id'] == $cat['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div style="font-size:12px; color:#718096; margin-top:4px;">* Las series parametrizadas por categoría aparecerán de forma horizontal cuando el cliente filtre esa categoría en el catálogo.</div>
        </div>

        <div class="form-group" style="margin-bottom:16px;">
            <label style="display:block; font-weight:600; margin-bottom:6px; color:#1d3557;">Nombre de la Serie (ej: M7, M2AJ, M2, M3, PF, Elevation, etc.)</label>
            <input type="text" name="name" value="<?= htmlspecialchars($item['name']) ?>" required placeholder="Nombre de la serie" style="width:100%; padding:10px 12px; border:1px solid #ccc; border-radius:6px; font-size:14px; box-sizing:border-box;">
        </div>
        
        <div class="form-group" style="margin-bottom:20px;">
            <label style="display:block; font-weight:600; margin-bottom:6px; color:#1d3557;">Orden de salida (Posición)</label>
            <input type="number" name="pos" value="<?= htmlspecialchars($item['pos']) ?>" placeholder="1, 2, 3..." style="width:100%; padding:10px 12px; border:1px solid #ccc; border-radius:6px; font-size:14px; box-sizing:border-box;">
            <div style="font-size:12px; color:#718096; margin-top:4px;">* Define tanto el orden horizontal de las series como el orden de salida de los equipos en el catálogo (serie 1 sale primero, luego serie 2, etc.).</div>
        </div>

        <div style="margin-top:24px; display:flex; gap:12px;">
            <button type="submit" class="btn-primary" style="background:#1d3557; color:white; padding:10px 22px; border-radius:6px; font-weight:700; border:none; cursor:pointer;">Guardar Serie</button>
            <a href="series.php" class="btn-nav" style="padding:10px 16px; border:1px solid #ccc; border-radius:6px; text-decoration:none; color:#555;">Volver a Series</a>
        </div>
    </form>
</div>
</body></html>
