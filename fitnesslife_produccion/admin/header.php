<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fitness Life Admin</title>
    <?php include 'css_patch.php'; ?>
    <style>
        .header-actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn-nav {
            background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); 
            padding: 10px 4px; box-sizing: border-box; font-weight: 600; font-size: 11px; 
            border-radius: 4px; text-transform: uppercase; font-family: 'Oswald', sans-serif; 
            cursor: pointer; display: flex; align-items: center; justify-content: center; 
            gap: 4px; flex-direction: column; text-decoration: none; min-width: 80px;
        }
        .btn-nav.active {
            background: #457b9d; border-color: #457b9d;
        }
        .btn-nav:hover:not(.active) {
            background: rgba(255,255,255,0.2);
        }
        .app-header { background: #1d3557 !important; color: white !important; border-bottom: 3px solid #e63946 !important; }
        .app-header h1 { color: white !important; }
    </style>
</head>
<body>
    <header class="app-header">
        <h1><img src="../assets/logo.png" style="height:32px; filter: brightness(0) invert(1);" alt="Logo"> Panel Admin</h1>
        <div class="header-actions">
            <?php 
            $curr = basename($_SERVER['PHP_SELF']); 
            $hasWeb = !isset($_SESSION['acceso_web']) || $_SESSION['acceso_web'] == 1;
            $hasCotiz = !isset($_SESSION['acceso_cotizador']) || $_SESSION['acceso_cotizador'] == 1;
            $isAdmin = ($_SESSION['rol'] ?? '') === 'admin';
            ?>
            <a href="index.php" class="btn-nav <?= $curr=='index.php' ? 'active' : '' ?>">
                <span style="font-size:16px; margin-bottom:4px;">🏠</span><span>Inicio</span>
            </a>

            <?php if ($hasWeb): ?>
            <a href="productos.php" class="btn-nav <?= $curr=='productos.php'||$curr=='producto_form.php' ? 'active' : '' ?>">
                <span style="font-size:16px; margin-bottom:4px;">📦</span><span>Productos</span>
            </a>
            <a href="categorias.php" class="btn-nav <?= $curr=='categorias.php'||$curr=='categoria_form.php' ? 'active' : '' ?>">
                <span style="font-size:16px; margin-bottom:4px;">📑</span><span>Categorías</span>
            </a>
            <a href="marcas.php" class="btn-nav <?= $curr=='marcas.php'||$curr=='marca_form.php' ? 'active' : '' ?>">
                <span style="font-size:16px; margin-bottom:4px;">🏷️</span><span>Marcas</span>
            </a>
            <a href="series.php" class="btn-nav <?= $curr=='series.php'||$curr=='serie_form.php' ? 'active' : '' ?>">
                <span style="font-size:16px; margin-bottom:4px;">⚡</span><span>Series</span>
            </a>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
            <a href="usuarios.php" class="btn-nav <?= $curr=='usuarios.php'||$curr=='usuario_form.php' ? 'active' : '' ?>">
                <span style="font-size:16px; margin-bottom:4px;">👥</span><span>Usuarios</span>
            </a>
            <?php endif; ?>
            <a href="logout.php" class="btn-nav" style="color:#ffcccc;border-color:rgba(255,0,0,0.3);background:rgba(255,0,0,0.1)">
                <span style="font-size:16px; margin-bottom:4px;">🚪</span><span>Salir</span>
            </a>
        </div>
    </header>
    <div class="main-container">
