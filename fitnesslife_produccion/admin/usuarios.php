<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

// Control de acceso al módulo web
if (isset($_SESSION['acceso_web']) && $_SESSION['acceso_web'] == 0) {
    if (isset($_SESSION['acceso_cotizador']) && $_SESSION['acceso_cotizador'] == 1) {
        header("Location: ../v1/cotizaciones/cotizador.html");
    } else {
        header("Location: logout.php");
    }
    exit;
}

// Solo administradores pueden gestionar usuarios
if (($_SESSION['rol'] ?? '') !== 'admin') {
    die("<div style='font-family:sans-serif;padding:30px;text-align:center;'><h2>Acceso denegado</h2><p>Solo usuarios con rol Administrador pueden gestionar los perfiles de usuario.</p><p><a href='index.php'>Regresar al inicio</a></p></div>");
}

// Eliminar usuario
if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    if ($deleteId === (int)$_SESSION['user_id']) {
        $_SESSION['flash_error'] = "No puedes eliminar tu propia cuenta en sesión.";
    } else {
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$deleteId]);
        $_SESSION['flash_success'] = "Usuario eliminado correctamente.";
    }
    header("Location: usuarios.php");
    exit;
}

$stmt = $pdo->query("SELECT id, username, nombre, rol, acceso_web, acceso_cotizador, created_at FROM usuarios ORDER BY nombre ASC");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

include 'header.php';
?>

<div class="panel-card">
    <div style="display:flex; justify-content:space-between; margin-bottom:24px; align-items:center; flex-wrap:wrap; gap:12px;">
        <div>
            <h2 style="margin:0;font-family:'Oswald',sans-serif;text-transform:uppercase;color:#1d3557;font-size:24px;">
                👥 Perfiles de Usuario y Permisos
            </h2>
            <p style="margin:4px 0 0; color:#718096; font-size:13px;">
                Define qué usuarios tienen acceso al Administrador Web, al Módulo del Cotizador o a ambos.
            </p>
        </div>
        <a href="usuario_form.php" class="btn-primary" style="background:#e63946; display:flex; align-items:center; gap:6px;">
            <span>+</span> Nuevo Usuario
        </a>
    </div>

    <?php if ($flash_success): ?>
        <div style="background:#e6fffa;color:#234e52;padding:12px 16px;border-radius:8px;margin-bottom:20px;border:1px solid #b2f5ea;font-size:14px;">
            ✅ <?= htmlspecialchars($flash_success) ?>
        </div>
    <?php endif; ?>

    <?php if ($flash_error): ?>
        <div style="background:#fff5f5;color:#c53030;padding:12px 16px;border-radius:8px;margin-bottom:20px;border:1px solid #feb2b2;font-size:14px;">
            ⚠️ <?= htmlspecialchars($flash_error) ?>
        </div>
    <?php endif; ?>

    <div class="table-wrapper">
        <table class="products-table">
            <thead>
                <tr>
                    <th>Nombre Completo</th>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th style="text-align:center;">Acceso Web</th>
                    <th style="text-align:center;">Acceso Cotizador</th>
                    <th>Fecha Alta</th>
                    <th style="text-align:right;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($u['nombre']) ?></strong>
                            <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                <span style="font-size:10px; background:#e0f2fe; color:#0369a1; padding:2px 6px; border-radius:10px; margin-left:6px; font-weight:700;">Tú</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:#4a5568;"><?= htmlspecialchars($u['username']) ?></td>
                        <td>
                            <?php if ($u['rol'] === 'admin'): ?>
                                <span style="background:#fef3c7; color:#92400e; padding:3px 8px; border-radius:12px; font-weight:700; font-size:11px; text-transform:uppercase;">
                                    Administrador
                                </span>
                            <?php else: ?>
                                <span style="background:#e2e8f0; color:#475569; padding:3px 8px; border-radius:12px; font-weight:700; font-size:11px; text-transform:uppercase;">
                                    Comercial
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($u['acceso_web']): ?>
                                <span style="display:inline-flex; align-items:center; gap:4px; color:#16a34a; font-weight:600; font-size:12px; background:#f0fdf4; padding:3px 8px; border-radius:6px; border:1px solid #bbf7d0;">
                                    ✓ Permitido
                                </span>
                            <?php else: ?>
                                <span style="display:inline-flex; align-items:center; gap:4px; color:#dc2626; font-weight:600; font-size:12px; background:#fef2f2; padding:3px 8px; border-radius:6px; border:1px solid #fecaca;">
                                    ✗ Bloqueado
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($u['acceso_cotizador']): ?>
                                <span style="display:inline-flex; align-items:center; gap:4px; color:#16a34a; font-weight:600; font-size:12px; background:#f0fdf4; padding:3px 8px; border-radius:6px; border:1px solid #bbf7d0;">
                                    ✓ Permitido
                                </span>
                            <?php else: ?>
                                <span style="display:inline-flex; align-items:center; gap:4px; color:#dc2626; font-weight:600; font-size:12px; background:#fef2f2; padding:3px 8px; border-radius:6px; border:1px solid #fecaca;">
                                    ✗ Bloqueado
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="color:#718096; font-size:12px;">
                            <?= !empty($u['created_at']) ? date('d/m/Y', strtotime($u['created_at'])) : '-' ?>
                        </td>
                        <td style="text-align:right;">
                            <a href="usuario_form.php?id=<?= $u['id'] ?>" class="action-link" style="color:#1d3557;">Editar</a>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <a href="usuarios.php?delete=<?= $u['id'] ?>" class="action-link danger" onclick="return confirm('¿Estás seguro de eliminar el usuario <?= addslashes($u['username']) ?>?');">Eliminar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
