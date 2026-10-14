<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM categorias WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: categorias.php"); exit;
}
$stmt = $pdo->query("SELECT * FROM categorias ORDER BY pos ASC");
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
include 'header.php';
?>
<div class="panel-card">
    <div style="display:flex; justify-content:space-between; margin-bottom:20px; align-items:center;">
        <h2 style="margin:0;font-family:Oswald;text-transform:uppercase;color:#1d3557;">Categorías</h2>
        <a href="categoria_form.php" class="btn-primary">+ Nueva Categoría</a>
    </div>
    <div class="table-wrapper">
        <table class="products-table">
            <thead>
                <tr>
                    <th>Orden</th>
                    <th>Imagen</th>
                    <th>Nombre</th>
                    <th>Subtítulo (Tag)</th>
                    <th>En Inicio</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $m): ?>
                    <tr>
                        <td><?= $m['pos'] ?></td>
                        <td><?php if($m['img']): ?><img src="../<?= htmlspecialchars($m['img']) ?>" style="height:32px;object-fit:contain;" /><?php endif; ?></td>
                        <td><strong><?= htmlspecialchars($m['name']) ?></strong></td>
                        <td><?= htmlspecialchars($m['tag']) ?></td>
                        <td>
                            <?php if(!isset($m['mostrar_home']) || $m['mostrar_home'] == 1): ?>
                                <span style="background:#e6fffa; color:#234e52; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:700; border:1px solid #b2f5ea;">Visible</span>
                            <?php else: ?>
                                <span style="background:#edf2f7; color:#718096; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:600;">Oculto</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="categoria_form.php?id=<?= $m['id'] ?>" class="action-link">Editar</a>
                            <a href="categorias.php?delete=<?= $m['id'] ?>" class="action-link danger" onclick="return confirm('¿Seguro?');">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body></html>
