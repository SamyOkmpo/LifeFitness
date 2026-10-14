<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

// Auto-descubrir y registrar series que ya estén en la tabla productos pero no en la tabla series
try {
    $existingDistinct = $pdo->query("SELECT DISTINCT serie FROM productos WHERE serie IS NOT NULL AND TRIM(serie) != ''")->fetchAll(PDO::FETCH_COLUMN);
    $curSeries = $pdo->query("SELECT name FROM series")->fetchAll(PDO::FETCH_COLUMN);
    $maxPos = (int)$pdo->query("SELECT COALESCE(MAX(pos), 0) FROM series")->fetchColumn();
    foreach ($existingDistinct as $es) {
        $es = trim($es);
        if (!in_array($es, $curSeries)) {
            $maxPos++;
            $ins = $pdo->prepare("INSERT INTO series (name, pos) VALUES (?, ?)");
            $ins->execute([$es, $maxPos]);
            $curSeries[] = $es;
        }
    }
} catch (Exception $e) {}

// Eliminar serie
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM series WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $_SESSION['flash_msg'] = "Serie eliminada correctamente.";
    header("Location: series.php"); exit;
}

// Mover arriba / abajo
if (isset($_GET['move_up']) || isset($_GET['move_down'])) {
    $id = (int)($_GET['move_up'] ?? $_GET['move_down']);
    $isUp = isset($_GET['move_up']);
    $current = $pdo->query("SELECT id, pos FROM series WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
    if ($current) {
        $op = $isUp ? "<" : ">";
        $order = $isUp ? "DESC" : "ASC";
        $neighbor = $pdo->query("SELECT id, pos FROM series WHERE pos $op {$current['pos']} ORDER BY pos $order LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($neighbor) {
            $pdo->prepare("UPDATE series SET pos = ? WHERE id = ?")->execute([$neighbor['pos'], $current['id']]);
            $pdo->prepare("UPDATE series SET pos = ? WHERE id = ?")->execute([$current['pos'], $neighbor['id']]);
            $_SESSION['flash_msg'] = "Orden actualizado correctamente.";
        }
    }
    header("Location: series.php"); exit;
}

// Guardar orden masivo desde los inputs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_order') {
    if (isset($_POST['pos']) && is_array($_POST['pos'])) {
        $stmtUpdate = $pdo->prepare("UPDATE series SET pos = ? WHERE id = ?");
        foreach ($_POST['pos'] as $serieId => $newPos) {
            $stmtUpdate->execute([(int)$newPos, (int)$serieId]);
        }
        $_SESSION['flash_msg'] = "El orden de todas las series fue guardado exitosamente.";
    }
    header("Location: series.php"); exit;
}

$stmt = $pdo->query("SELECT * FROM series ORDER BY pos ASC, name ASC");
$seriesList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Conteo de productos por serie
$prodCounts = [];
try {
    $cStmt = $pdo->query("SELECT serie, COUNT(*) as cnt FROM productos WHERE serie IS NOT NULL AND TRIM(serie) != '' GROUP BY serie");
    while ($row = $cStmt->fetch(PDO::FETCH_ASSOC)) {
        $prodCounts[trim($row['serie'])] = (int)$row['cnt'];
    }
} catch (Exception $e) {}

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
            <h2 style="margin:0 0 6px 0; font-family:'Oswald', sans-serif; text-transform:uppercase; color:#1d3557; font-size:24px;">⚡ Orden de Series de Equipos</h2>
            <div style="font-size:13px; color:#555; line-height:1.4;">
                El número de orden aquí definido determina <strong>el orden horizontal de las series</strong> y <strong>el orden de salida de los equipos en el catálogo</strong> (los de la serie #1 salen primero, luego serie #2, etc.).
            </div>
        </div>
        <div style="display:flex; gap:10px; align-items:center;">
            <a href="productos.php" style="background:#f7f7f7; color:#457b9d; border:1px solid #ccc; padding:10px 16px; border-radius:6px; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">📦 Ver Equipos</a>
            <a href="serie_form.php" class="btn-primary" style="background:#457b9d; color:white; padding:10px 18px; border-radius:6px; text-decoration:none; font-family:'Oswald', sans-serif; text-transform:uppercase; font-weight:600;">+ Nueva Serie</a>
        </div>
    </div>

    <form method="POST" action="series.php">
        <input type="hidden" name="action" value="save_order">
        
        <div class="table-wrapper">
            <table class="products-table">
                <thead>
                    <tr>
                        <th style="width: 110px; text-align:center;">ORDEN</th>
                        <th style="width: 120px; text-align:center;">MOVER</th>
                        <th>NOMBRE DE LA SERIE</th>
                        <th style="width: 140px; text-align:center;">EQUIPOS</th>
                        <th style="width: 150px; text-align:right;">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($seriesList as $idx => $s): ?>
                        <tr>
                            <td style="text-align:center;">
                                <input type="number" name="pos[<?= $s['id'] ?>]" value="<?= (int)$s['pos'] ?>" min="0" style="width: 70px; padding: 6px 8px; border: 1px solid #457b9d; border-radius: 6px; font-weight: 700; color: #1d3557; text-align: center; font-size: 14px; background:#f8fbfe;">
                            </td>
                            <td style="text-align:center; white-space:nowrap;">
                                <?php if ($idx > 0): ?>
                                    <a href="series.php?move_up=<?= $s['id'] ?>" style="display:inline-block; padding:4px 8px; background:#edf2f7; border-radius:4px; text-decoration:none; color:#2d3748; font-weight:bold; font-size:12px; margin-right:2px;" title="Subir prioridad">▲</a>
                                <?php else: ?>
                                    <span style="display:inline-block; padding:4px 8px; color:#cbd5e0; font-size:12px; margin-right:2px;">▲</span>
                                <?php endif; ?>
                                <?php if ($idx < count($seriesList) - 1): ?>
                                    <a href="series.php?move_down=<?= $s['id'] ?>" style="display:inline-block; padding:4px 8px; background:#edf2f7; border-radius:4px; text-decoration:none; color:#2d3748; font-weight:bold; font-size:12px;" title="Bajar prioridad">▼</a>
                                <?php else: ?>
                                    <span style="display:inline-block; padding:4px 8px; color:#cbd5e0; font-size:12px;">▼</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="font-size:15px; color:#1d3557;"><?= htmlspecialchars($s['name']) ?></strong>
                            </td>
                            <td style="text-align:center;">
                                <?php $cnt = $prodCounts[trim($s['name'])] ?? 0; ?>
                                <span style="background:<?= $cnt > 0 ? '#e0ebf3' : '#edf2f7' ?>; color:<?= $cnt > 0 ? '#1d3557' : '#718096' ?>; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600;">
                                    <?= $cnt ?> <?= $cnt === 1 ? 'equipo' : 'equipos' ?>
                                </span>
                            </td>
                            <td style="text-align:right; white-space:nowrap;">
                                <a href="serie_form.php?id=<?= $s['id'] ?>" style="background: transparent; border: 1px solid #ccc; border-radius: 4px; padding: 6px 10px; cursor: pointer; margin-right: 4px; text-decoration: none; display: inline-block; color: #333;">✏️ Editar</a>
                                <a href="series.php?delete=<?= $s['id'] ?>" style="background: transparent; border: 1px solid #ffcccc; color: #e63946; border-radius: 4px; padding: 6px 10px; cursor: pointer; text-decoration: none; display: inline-block;" onclick="return confirm('¿Estás seguro de eliminar esta serie? (Los equipos asociados no se eliminarán)');">🗑️</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($seriesList) === 0): ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding:40px; color:#718096;">
                                No hay series registradas aún. Haz clic en <strong>+ Nueva Serie</strong> para agregar una.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (count($seriesList) > 0): ?>
            <div style="margin-top:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; border-top:1px solid #eee; padding-top:16px;">
                <span style="font-size:12px; color:#718096;">* Puedes cambiar los números de orden directamente y presionar Guardar Orden, o usar las flechas ▲ ▼ para mover una a una.</span>
                <button type="submit" style="background:#1d3557; color:white; border:none; padding:12px 24px; border-radius:6px; font-weight:700; font-family:'Oswald', sans-serif; text-transform:uppercase; font-size:15px; cursor:pointer; display:inline-flex; align-items:center; gap:8px;">
                    💾 Guardar Orden
                </button>
            </div>
        <?php endif; ?>
    </form>
</div>
</body></html>
