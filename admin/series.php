<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM series WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: series.php"); exit;
}

$stmt = $pdo->query("SELECT * FROM series ORDER BY pos ASC, name ASC");
$seriesList = $stmt->fetchAll(PDO::FETCH_ASSOC);
include 'header.php';
?>
<div class="panel-card">
    <div style="display:flex; justify-content:space-between; margin-bottom:20px; align-items:center; flex-wrap:wrap; gap:12px;">
        <div>
            <h2 style="margin:0;font-family:Oswald;text-transform:uppercase;color:#1d3557;">Series / Líneas de Equipos</h2>
            <div style="font-size:13px; color:#718096; margin-top:4px;">Define el orden de prioridad en que aparecerán las series en los filtros del catálogo público.</div>
        </div>
        <a href="serie_form.php" class="btn-primary">+ Nueva Serie</a>
    </div>
    <div class="table-wrapper">
        <table class="products-table">
            <thead>
                <tr>
                    <th style="width: 80px;">Orden</th>
                    <th>Nombre de la Serie</th>
                    <th style="width: 150px; text-align:right;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($seriesList as $s): ?>
                    <tr>
                        <td style="font-weight:700; color:#457b9d;"><?= (int)$s['pos'] ?></td>
                        <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                        <td style="text-align:right;">
                            <a href="serie_form.php?id=<?= $s['id'] ?>" class="action-link">Editar</a>
                            <a href="series.php?delete=<?= $s['id'] ?>" class="action-link danger" onclick="return confirm('¿Seguro de eliminar esta serie?');">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (count($seriesList) === 0): ?>
                    <tr>
                        <td colspan="3" style="text-align:center; padding:30px; color:#888;">
                            No hay series registradas aún. Haz clic en <strong>+ Nueva Serie</strong> para agregar la primera.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body></html>
