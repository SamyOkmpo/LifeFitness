<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$id = $_GET['id'] ?? null;
$item = ['name' => '', 'pos' => 0];
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
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

                $stmt = $pdo->prepare("UPDATE series SET name = ?, pos = ? WHERE id = ?");
                $stmt->execute([$name, $pos, $id]);

                if ($oldName && $oldName !== $name) {
                    $stmtProd = $pdo->prepare("UPDATE productos SET serie = ? WHERE serie = ?");
                    $stmtProd->execute([$name, $oldName]);
                }
            } else {
                $stmt = $pdo->prepare("INSERT INTO series (name, pos) VALUES (?, ?)");
                $stmt->execute([$name, $pos]);
                $id = $pdo->lastInsertId();
            }
            $success = true;
        } catch (Exception $e) {
            $error = 'Ya existe una serie con ese nombre.';
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
<div class="panel-card" style="max-width:500px; margin: 0 auto;">
    <h2 style="margin-top:0;font-family:Oswald;text-transform:uppercase;color:#1d3557;border-bottom:1px solid #eee;padding-bottom:12px"><?= $id ? 'Editar Serie' : 'Nueva Serie' ?></h2>
    <?php if ($success): ?><div style="background:#e6fffa;color:#234e52;padding:12px;border-radius:6px;margin-bottom:20px;border:1px solid #b2f5ea;">✅ Guardado correctamente.</div><?php endif; ?>
    <?php if ($error): ?><div style="background:#fff5f5;color:#c53030;padding:12px;border-radius:6px;margin-bottom:20px;border:1px solid #feb2b2;">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    
    <form method="POST" action="">
        <div class="form-group">
            <label>Nombre de la Serie (ej: Optima, Insignia, Versa, Discovery, M7Pro)</label>
            <input type="text" name="name" value="<?= htmlspecialchars($item['name']) ?>" required placeholder="Nombre de la serie">
        </div>
        
        <div class="form-group">
            <label>Orden de aparición en el Catálogo (Posición)</label>
            <input type="number" name="pos" value="<?= htmlspecialchars($item['pos']) ?>" placeholder="1, 2, 3...">
            <div style="font-size:12px; color:#718096; margin-top:4px;">* Las series con menor número aparecerán primero en los filtros del catálogo público.</div>
        </div>

        <div style="margin-top:24px; display:flex; gap:12px;">
            <button type="submit" class="btn-primary">Guardar Serie</button>
            <a href="series.php" class="btn-nav">Volver a Series</a>
        </div>
    </form>
</div>
</body></html>
