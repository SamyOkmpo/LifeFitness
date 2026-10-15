<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

// Asegurar que la sesión siempre tenga los datos actualizados de la BD (ej. cambio de nombre)
$stmtUser = $pdo->prepare("SELECT username, nombre, rol, token, acceso_web, acceso_cotizador FROM usuarios WHERE id = ?");
$stmtUser->execute([$_SESSION['user_id']]);
$uData = $stmtUser->fetch(PDO::FETCH_ASSOC);

if ($uData) {
    $_SESSION['username'] = $uData['username'];
    $_SESSION['nombre'] = $uData['nombre'];
    $_SESSION['rol'] = $uData['rol'];
    $_SESSION['acceso_web'] = (int)($uData['acceso_web'] ?? 1);
    $_SESSION['acceso_cotizador'] = (int)($uData['acceso_cotizador'] ?? 1);

    $dbToken = $uData['token'];
    if (empty($dbToken)) {
        $dbToken = bin2hex(random_bytes(32));
        $pdo->prepare("UPDATE usuarios SET token = ? WHERE id = ?")->execute([$dbToken, $_SESSION['user_id']]);
    }
    $_SESSION['token'] = $dbToken;
}

// Obtener estadísticas rápidas
$stats = [
    'productos' => $pdo->query("SELECT COUNT(*) FROM productos")->fetchColumn(),
    'cotizaciones_leads' => $pdo->query("SELECT COUNT(*) FROM solicitudes_cotizacion")->fetchColumn(),
    'marcas' => $pdo->query("SELECT COUNT(*) FROM marcas")->fetchColumn(),
    'categorias' => $pdo->query("SELECT COUNT(*) FROM categorias")->fetchColumn()
];

try {
    $stats['cotizaciones_app'] = $pdo->query("SELECT COUNT(*) FROM cotizaciones")->fetchColumn();
    $stats['usuarios'] = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
} catch (Exception $e) {
    $stats['cotizaciones_app'] = 0;
    $stats['usuarios'] = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centro de Control — Fitness Life</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1d3557;
            --accent: #e63946;
            --secondary: #457b9d;
            --bg: #f4f6f9;
            --card-bg: #ffffff;
            --text-dark: #1e293b;
            --text-muted: #64748b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background-color: var(--bg);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .header {
            background-color: var(--primary);
            border-bottom: 4px solid var(--accent);
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .logo-wrap {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .logo-wrap img {
            height: 38px;
            filter: brightness(0) invert(1);
        }
        .logo-wrap h1 {
            font-family: 'Oswald', sans-serif;
            font-size: 22px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .badge-hub {
            font-size: 11px;
            background: rgba(230, 57, 70, 0.25);
            color: #ff8fa3;
            border: 1px solid rgba(230, 57, 70, 0.5);
            padding: 3px 8px;
            border-radius: 4px;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            letter-spacing: 0;
        }
        .user-nav {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .user-info {
            text-align: right;
            font-size: 13px;
        }
        .user-name {
            font-weight: 600;
            color: #fff;
        }
        .user-role {
            font-size: 11px;
            color: #a8dadc;
            text-transform: uppercase;
        }
        .btn-logout {
            background: rgba(230, 57, 70, 0.15);
            color: #ffcccc;
            border: 1px solid rgba(230, 57, 70, 0.35);
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-logout:hover {
            background: var(--accent);
            color: white;
        }

        .container {
            max-width: 1180px;
            margin: 40px auto;
            padding: 0 24px;
            flex: 1;
            width: 100%;
        }
        .hero-title {
            text-align: center;
            margin-bottom: 36px;
        }
        .hero-title h2 {
            font-family: 'Oswald', sans-serif;
            font-size: 32px;
            text-transform: uppercase;
            color: var(--primary);
            margin-bottom: 8px;
        }
        .hero-title p {
            color: var(--text-muted);
            font-size: 15px;
        }

        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
            gap: 28px;
            margin-bottom: 40px;
        }

        .module-card {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.04);
            transition: all 0.25s ease;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .module-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 32px rgba(29, 53, 87, 0.1);
            border-color: #cbd5e1;
        }
        .card-top {
            padding: 24px 28px 18px;
            border-bottom: 1px solid #f1f5f9;
        }
        .card-tag {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 4px 10px;
            border-radius: 20px;
            margin-bottom: 12px;
        }
        .tag-web {
            background: #e0f2fe;
            color: #0369a1;
        }
        .tag-quote {
            background: #fee2e2;
            color: #b91c1c;
        }
        .card-title {
            font-family: 'Oswald', sans-serif;
            font-size: 24px;
            text-transform: uppercase;
            color: var(--primary);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card-desc {
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.5;
        }

        .links-list {
            padding: 20px 28px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 10px;
            background: #fafcff;
        }
        .nav-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: white;
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            text-decoration: none;
            color: var(--text-dark);
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
        }
        .nav-item:hover {
            border-color: var(--secondary);
            background: #f0f7ff;
            color: var(--primary);
            transform: translateX(4px);
        }
        .nav-item-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .nav-icon {
            font-size: 18px;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            background: #f1f5f9;
        }
        .nav-item-badge {
            font-size: 12px;
            font-weight: 700;
            background: #e2e8f0;
            color: #475569;
            padding: 2px 8px;
            border-radius: 12px;
        }

        .card-footer {
            padding: 18px 28px;
            background: white;
            border-top: 1px solid #f1f5f9;
        }
        .btn-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            text-align: center;
            transition: all 0.2s;
        }
        .btn-cta-primary {
            background: var(--primary);
            color: white;
        }
        .btn-cta-primary:hover {
            background: #15263f;
        }
        .btn-cta-accent {
            background: var(--accent);
            color: white;
        }
        .btn-cta-accent:hover {
            background: #c52d3a;
        }

        .quick-bar {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
        }
        .quick-links {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }
        .quick-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--secondary);
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }
        .quick-link:hover {
            text-decoration: underline;
            color: var(--primary);
        }

        @media (max-width: 768px) {
            .header { padding: 14px 18px; }
            .container { margin: 20px auto; padding: 0 16px; }
            .modules-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<header class="header">
    <div class="logo-wrap">
        <img src="../assets/logo.png" alt="Fitness Life">
        <h1>Centro de Control <span class="badge-hub">HUB</span></h1>
    </div>
    <div class="user-nav">
        <div class="user-info">
            <div class="user-name"><?= htmlspecialchars($_SESSION['nombre'] ?? $_SESSION['username'] ?? 'Administrador') ?></div>
            <div class="user-role"><?= htmlspecialchars($_SESSION['rol'] ?? 'Admin') ?></div>
        </div>
        <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
    </div>
</header>

<div class="container">
    <div class="hero-title">
        <h2>Panel de Administración Integral</h2>
        <p>Selecciona el área que deseas gestionar</p>
    </div>

    <?php 
    $hasWeb = !isset($_SESSION['acceso_web']) || $_SESSION['acceso_web'] == 1;
    $hasCotiz = !isset($_SESSION['acceso_cotizador']) || $_SESSION['acceso_cotizador'] == 1;
    $isAdmin = ($_SESSION['rol'] ?? '') === 'admin';
    ?>

    <div class="modules-grid" style="<?= (!$hasWeb || !$hasCotiz) ? 'max-width:680px; margin:0 auto 40px;' : '' ?>">
        <?php if ($hasWeb): ?>
        <!-- MODULO 1: ADMINISTRADOR DE LA PÁGINA WEB -->
        <div class="module-card">
            <div class="card-top">
                <span class="card-tag tag-web">Sitio Web & Catálogo</span>
                <div class="card-title">🌐 Administrador Web</div>
                <div class="card-desc">Control del catálogo público de la página web, series, marcas y categorías.</div>
            </div>
            <div class="links-list">
                <a href="productos.php" class="nav-item">
                    <div class="nav-item-left">
                        <span class="nav-icon">📦</span>
                        <span>Equipos del Catálogo</span>
                    </div>
                    <span class="nav-item-badge"><?= $stats['productos'] ?></span>
                </a>
                <a href="categorias.php" class="nav-item">
                    <div class="nav-item-left">
                        <span class="nav-icon">📑</span>
                        <span>Categorías</span>
                    </div>
                    <span class="nav-item-badge"><?= $stats['categorias'] ?></span>
                </a>
                <a href="marcas.php" class="nav-item">
                    <div class="nav-item-left">
                        <span class="nav-icon">🏷️</span>
                        <span>Marcas</span>
                    </div>
                    <span class="nav-item-badge"><?= $stats['marcas'] ?></span>
                </a>
                <a href="series.php" class="nav-item">
                    <div class="nav-item-left">
                        <span class="nav-icon">⚡</span>
                        <span>Series por Categoría</span>
                    </div>
                </a>
                <?php if ($isAdmin): ?>
                <a href="usuarios.php" class="nav-item" style="border-left:3px solid #1d3557;">
                    <div class="nav-item-left">
                        <span class="nav-icon">👥</span>
                        <span>Perfiles de Usuario & Permisos</span>
                    </div>
                    <span class="nav-item-badge" style="background:#e0f2fe;color:#0369a1;"><?= $stats['usuarios'] ?></span>
                </a>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <a href="productos.php" class="btn-cta btn-cta-primary">
                    Entrar al Administrador Web &rarr;
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($hasCotiz): ?>
        <!-- MODULO 2: ADMINISTRADOR DEL COTIZADOR -->
        <div class="module-card">
            <div class="card-top">
                <span class="card-tag tag-quote">App de Ventas & Cotizaciones</span>
                <div class="card-title">💼 Administrador Cotizador</div>
                <div class="card-desc">Plataforma comercial para generar propuestas formales a clientes, PDFs comerciales, cálculo de márgenes y usuarios asesores.</div>
            </div>
            <div class="links-list">
                <a href="../v1/cotizaciones/cotizador.html" class="nav-item">
                    <div class="nav-item-left">
                        <span class="nav-icon">✨</span>
                        <span>Crear Nueva Cotización</span>
                    </div>
                </a>
                <a href="../v1/cotizaciones/mis_cotizaciones.html" class="nav-item">
                    <div class="nav-item-left">
                        <span class="nav-icon">📂</span>
                        <span>Historial de Cotizaciones & PDFs</span>
                    </div>
                    <?php if ($stats['cotizaciones_app'] > 0): ?>
                    <span class="nav-item-badge" style="background:#fee2e2;color:#b91c1c"><?= $stats['cotizaciones_app'] ?></span>
                    <?php endif; ?>
                </a>
                <a href="../v1/cotizaciones/admin_productos.html" class="nav-item">
                    <div class="nav-item-left">
                        <span class="nav-icon">📊</span>
                        <span>Precios Base & Costos del Cotizador</span>
                    </div>
                </a>
                <?php if ($isAdmin): ?>
                <a href="usuarios.php" class="nav-item" style="border-left:3px solid #e63946;">
                    <div class="nav-item-left">
                        <span class="nav-icon">👥</span>
                        <span>Usuarios & Permisos de Acceso</span>
                    </div>
                    <?php if ($stats['usuarios'] > 0): ?>
                    <span class="nav-item-badge"><?= $stats['usuarios'] ?></span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <a href="../v1/cotizaciones/cotizador.html" class="btn-cta btn-cta-accent">
                    Entrar al Cotizador Comercial &rarr;
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="quick-bar">
        <span style="font-size:13px; color:var(--text-muted);">
            <strong>Accesos Rápidos:</strong> Puedes regresar a este Centro de Control en cualquier momento desde el menú superior.
        </span>
        <div class="quick-links">
            <a href="../index.php" target="_blank" class="quick-link">🌐 Ver Página Web</a>
            <?php if ($hasCotiz): ?>
            <a href="../v1/cotizaciones/mis_cotizaciones.html" class="quick-link">📂 Ver Cotizaciones Guardadas</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($_SESSION['token'])): ?>
<script>
// Single Sign-On: Sincronizar automáticamente la sesión activa con la App del Cotizador
(function() {
    const token = <?= json_encode($_SESSION['token']) ?>;
    const user = {
        id: <?= (int)$_SESSION['user_id'] ?>,
        username: <?= json_encode($_SESSION['username'] ?? '') ?>,
        nombre: <?= json_encode($_SESSION['nombre'] ?? '') ?>,
        rol: <?= json_encode($_SESSION['rol'] ?? 'admin') ?>,
        acceso_web: <?= (int)($_SESSION['acceso_web'] ?? 1) ?>,
        acceso_cotizador: <?= (int)($_SESSION['acceso_cotizador'] ?? 1) ?>
    };
    try {
        localStorage.setItem('fl_auth_token', token);
        localStorage.setItem('fl_auth_user', JSON.stringify(user));
    } catch(e) {}
})();
</script>
<?php endif; ?>

</body>
</html>
