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
    die("<div style='font-family:sans-serif;padding:30px;text-align:center;'><h2>Acceso denegado</h2><p>Solo administradores pueden crear o editar perfiles de usuario.</p><p><a href='index.php'>Regresar al inicio</a></p></div>");
}

$id = $_GET['id'] ?? null;
$item = [
    'username' => '',
    'nombre' => '',
    'rol' => 'comercial',
    'acceso_web' => 1,
    'acceso_cotizador' => 1
];
$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $password = $_POST['password'] ?? '';
    $rol = $_POST['rol'] ?? 'comercial';
    $acceso_web = isset($_POST['acceso_web']) ? 1 : 0;
    $acceso_cotizador = isset($_POST['acceso_cotizador']) ? 1 : 0;

    // Validación básica: Al menos un módulo debe estar seleccionado
    if ($acceso_web === 0 && $acceso_cotizador === 0) {
        $error = "Debes habilitar al menos un módulo de acceso (Web o Cotizador).";
    } elseif (empty($username) || empty($nombre)) {
        $error = "Por favor completa el nombre y el usuario.";
    } elseif (!$id && empty($password)) {
        $error = "Debes asignar una contraseña para el nuevo usuario.";
    } else {
        try {
            if ($id) {
                // Actualizar usuario existente
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE usuarios SET username = ?, password = ?, nombre = ?, rol = ?, acceso_web = ?, acceso_cotizador = ? WHERE id = ?");
                    $stmt->execute([$username, $hash, $nombre, $rol, $acceso_web, $acceso_cotizador, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE usuarios SET username = ?, nombre = ?, rol = ?, acceso_web = ?, acceso_cotizador = ? WHERE id = ?");
                    $stmt->execute([$username, $nombre, $rol, $acceso_web, $acceso_cotizador, $id]);
                }

                // Si se está editando a sí mismo, refrescar la sesión
                if ($id == $_SESSION['user_id']) {
                    $_SESSION['username'] = $username;
                    $_SESSION['nombre'] = $nombre;
                    $_SESSION['rol'] = $rol;
                    $_SESSION['acceso_web'] = $acceso_web;
                    $_SESSION['acceso_cotizador'] = $acceso_cotizador;
                }

                $_SESSION['flash_success'] = "Usuario '$username' actualizado correctamente.";
                header("Location: usuarios.php");
                exit;
            } else {
                // Crear nuevo usuario
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO usuarios (username, password, nombre, rol, acceso_web, acceso_cotizador) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $hash, $nombre, $rol, $acceso_web, $acceso_cotizador]);
                $_SESSION['flash_success'] = "Usuario '$username' creado con éxito.";
                header("Location: usuarios.php");
                exit;
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate') !== false) {
                $error = "El nombre de usuario '$username' ya existe. Elige otro.";
            } else {
                $error = "Error al guardar: " . $e->getMessage();
            }
        }
    }
}

if ($id) {
    $stmt = $pdo->prepare("SELECT id, username, nombre, rol, acceso_web, acceso_cotizador FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC) ?: $item;
}

include 'header.php';
?>

<div class="panel-card" style="max-width:640px; margin: 0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; padding-bottom:12px; margin-bottom:20px;">
        <h2 style="margin:0; font-family:'Oswald',sans-serif; text-transform:uppercase; color:#1d3557; font-size:22px;">
            <?= $id ? '✏️ Editar Perfil de Usuario' : '➕ Crear Nuevo Perfil' ?>
        </h2>
        <a href="usuarios.php" style="text-decoration:none; color:#718096; font-size:13px; font-weight:600;">&larr; Volver a usuarios</a>
    </div>

    <?php if ($error): ?>
        <div style="background:#fff5f5; color:#c53030; padding:12px 16px; border-radius:8px; margin-bottom:20px; border:1px solid #feb2b2; font-size:14px;">
            ⚠️ <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label>Nombre y Apellido</label>
            <input type="text" name="nombre" value="<?= htmlspecialchars($item['nombre']) ?>" placeholder="Ej: Carlos Pérez" required>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
            <div class="form-group">
                <label>Nombre de Usuario (Login)</label>
                <input type="text" name="username" value="<?= htmlspecialchars($item['username']) ?>" placeholder="Ej: cperez" required autocomplete="off">
            </div>
            <div class="form-group">
                <label>Contraseña <?= $id ? '<span style="font-weight:normal;color:#a0aec0;">(Dejar vacía para conservar)</span>' : '' ?></label>
                <input type="password" name="password" placeholder="<?= $id ? '••••••••' : 'Contraseña segura' ?>" <?= $id ? '' : 'required' ?> autocomplete="new-password">
            </div>
        </div>

        <div class="form-group">
            <label>Rol del Sistema</label>
            <select name="rol" required>
                <option value="comercial" <?= ($item['rol'] === 'comercial') ? 'selected' : '' ?>>Comercial / Asesor de Ventas</option>
                <option value="admin" <?= ($item['rol'] === 'admin') ? 'selected' : '' ?>>Administrador General</option>
            </select>
            <small style="color:#718096; display:block; margin-top:4px; font-size:12px;">
                Los administradores pueden gestionar usuarios, bases de datos y configuraciones. Los comerciales solo gestionan catálogo y cotizaciones asignadas.
            </small>
        </div>

        <!-- PERMISOS DE ACCESO A MÓDULOS -->
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:20px; margin:24px 0;">
            <label style="display:block; font-family:'Oswald',sans-serif; text-transform:uppercase; font-size:14px; color:#1d3557; margin-bottom:12px;">
                🔐 Permisos de Acceso a Módulos
            </label>

            <div style="display:flex; flex-direction:column; gap:14px;">
                <!-- Modulo 1: Administrador Web -->
                <label style="display:flex; align-items:flex-start; gap:12px; cursor:pointer; background:white; padding:12px 16px; border-radius:8px; border:1px solid #e2e8f0;">
                    <input type="checkbox" name="acceso_web" value="1" <?= (!empty($item['acceso_web'])) ? 'checked' : '' ?> style="width:20px; height:20px; margin-top:2px; accent-color:#1d3557;">
                    <div>
                        <div style="font-weight:700; color:#1d3557; font-size:14px;">🌐 Acceso al Administrador de la Página Web</div>
                        <div style="color:#64748b; font-size:12px; margin-top:2px;">
                            Permite administrar el catálogo de la página, series, marcas, categorías y ver solicitudes recibidas (leads).
                        </div>
                    </div>
                </label>

                <!-- Modulo 2: Administrador del Cotizador -->
                <label style="display:flex; align-items:flex-start; gap:12px; cursor:pointer; background:white; padding:12px 16px; border-radius:8px; border:1px solid #e2e8f0;">
                    <input type="checkbox" name="acceso_cotizador" value="1" <?= (!empty($item['acceso_cotizador'])) ? 'checked' : '' ?> style="width:20px; height:20px; margin-top:2px; accent-color:#e63946;">
                    <div>
                        <div style="font-weight:700; color:#e63946; font-size:14px;">💼 Acceso al Módulo del Cotizador</div>
                        <div style="color:#64748b; font-size:12px; margin-top:2px;">
                            Permite ingresar a la app comercial para generar propuestas formales, descargar cotizaciones en PDF y ver el historial comercial.
                        </div>
                    </div>
                </label>
            </div>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:12px;">
            <a href="usuarios.php" class="btn-nav" style="border:1px solid #cbd5e1; background:white; color:#475569; padding:10px 20px;">Cancelar</a>
            <button type="submit" class="btn-primary" style="background:#1d3557; padding:10px 24px; font-size:14px; cursor:pointer;">
                💾 <?= $id ? 'Guardar Cambios' : 'Crear Usuario' ?>
            </button>
        </div>
    </form>
</div>

</body>
</html>
