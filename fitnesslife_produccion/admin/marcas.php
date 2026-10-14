<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM marcas WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: marcas.php"); exit;
}
$stmt = $pdo->query("SELECT * FROM marcas ORDER BY pos ASC, name ASC");
$marcas = $stmt->fetchAll(PDO::FETCH_ASSOC);
include 'header.php';
?>
<div class="panel-card">
    <div style="display:flex; justify-content:space-between; margin-bottom:20px; align-items:center;">
        <h2 style="margin:0;font-family:Oswald;text-transform:uppercase;color:#1d3557;">Marcas / Series</h2>
        <a href="marca_form.php" class="btn-primary">+ Nueva Marca</a>
    </div>
    <div class="table-wrapper">
        <table class="products-table">
            <thead>
                <tr>
                    <th>Orden</th>
                    <th>Logo</th>
                    <th>Nombre</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($marcas as $m): ?>
                    <tr>
                        <td><?= $m['pos'] ?></td>
                        <td><?php if($m['img']): ?><img src="../<?= htmlspecialchars($m['img']) ?>" style="height:32px;object-fit:contain;" /><?php endif; ?></td>
                        <td><strong><?= htmlspecialchars($m['name']) ?></strong></td>
                        <td>
                            <a href="marca_form.php?id=<?= $m['id'] ?>" class="action-link">Editar</a>
                            <a href="marcas.php?delete=<?= $m['id'] ?>" class="action-link danger" onclick="return confirm('¿Seguro?');">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body></html>
