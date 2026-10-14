<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$stmt = $pdo->query("SELECT * FROM solicitudes_cotizacion ORDER BY fecha DESC");
$cotizaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
include 'header.php';
?>
<div class="panel-card">
    <h2 style="margin-top:0;font-family:Oswald;text-transform:uppercase;color:#1d3557;border-bottom:1px solid #eee;padding-bottom:12px;margin-bottom:20px;">Buzón de Cotizaciones (Leads)</h2>
    <div class="table-wrapper">
        <table class="products-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Datos de Contacto</th>
                    <th>Mensaje / Equipos</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cotizaciones as $c): ?>
                    <tr>
                        <td style="white-space: nowrap;"><?= htmlspecialchars($c['fecha']) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($c['nombre'] ?? 'Sin nombre') ?></strong><br>
                            <span style="color:#718096; font-size:12px;"><?= htmlspecialchars($c['empresa'] ?? '') ?></span>
                        </td>
                        <td>
                            📞 <?= htmlspecialchars($c['telefono'] ?? '-') ?><br>
                            ✉️ <?= htmlspecialchars($c['email'] ?? '-') ?>
                        </td>
                        <td>
                            <?php if(!empty($c['json_productos'])): ?>
                                <span style="background:#e2e8f0;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:600;color:#4a5568;"><?= count(json_decode($c['json_productos'], true) ?: []) ?> Equipos</span>
                            <?php endif; ?>
                            <p style="margin-top:8px; font-size:13px; color:#4a5568;"><?= nl2br(htmlspecialchars($c['mensaje'] ?? '')) ?></p>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if(count($cotizaciones) === 0): ?>
                    <tr><td colspan="4" style="text-align:center; color:#a0aec0; padding:40px;">No hay cotizaciones aún.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body></html>
