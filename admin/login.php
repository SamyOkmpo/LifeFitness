<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    if (!empty($user) && !empty($pass)) {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username = :u LIMIT 1");
        $stmt->execute(['u' => $user]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($pass, $usuario['password'])) {
            $_SESSION['user_id'] = $usuario['id'];
            $_SESSION['username'] = $usuario['username'];
            $_SESSION['nombre'] = $usuario['nombre'];
            $_SESSION['rol'] = $usuario['rol'];
            $_SESSION['acceso_web'] = (int)($usuario['acceso_web'] ?? 1);
            $_SESSION['acceso_cotizador'] = (int)($usuario['acceso_cotizador'] ?? 1);

            // Redirección inteligente si solo tiene acceso a un módulo
            if ($_SESSION['acceso_web'] == 1 && $_SESSION['acceso_cotizador'] == 0) {
                header("Location: productos.php");
                exit;
            } elseif ($_SESSION['acceso_web'] == 0 && $_SESSION['acceso_cotizador'] == 1) {
                header("Location: ../v1/cotizaciones/cotizador.html");
                exit;
            } else {
                header("Location: index.php");
                exit;
            }
        } else {
            $error = "Usuario o contraseña incorrectos.";
        }
    } else {
        $error = "Por favor completa ambos campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fitness Life - Admin Login</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #f4f6f8; margin: 0; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .login-card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 100%; max-width: 360px; text-align: center; }
        .logo { max-width: 180px; margin-bottom: 30px; }
        .input-group { margin-bottom: 20px; text-align: left; }
        .input-group label { display: block; font-size: 13px; font-weight: 600; color: #4a5568; margin-bottom: 8px; }
        .input-group input { width: 100%; box-sizing: border-box; padding: 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 15px; outline: none; transition: border-color 0.2s; }
        .input-group input:focus { border-color: #c92026; }
        .btn { width: 100%; padding: 12px; background: linear-gradient(135deg, #c92026, #b11b21); color: white; border: none; border-radius: 6px; font-size: 15px; font-weight: 600; cursor: pointer; transition: opacity 0.2s; }
        .btn:hover { opacity: 0.9; }
        .error { color: #e53e3e; background: #fff5f5; padding: 10px; border-radius: 6px; font-size: 13px; margin-bottom: 20px; border: 1px solid #fc8181; }
    </style>
</head>
<body>

<div class="login-card">
    <img src="../assets/logo.png" alt="Fitness Life" class="logo">
    <h2 style="font-size:20px; color:#2d3748; margin-top:0; margin-bottom:24px;">Panel de Administración</h2>
    
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="input-group">
            <label for="username">Usuario</label>
            <input type="text" id="username" name="username" required autocomplete="username">
        </div>
        <div class="input-group">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn">Iniciar Sesión</button>
    </form>
</div>

</body>
</html>
