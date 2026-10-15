<?php
$serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
$entorno = ($serverName === 'localhost' || $serverName === '127.0.0.1' || strpos($serverName, '.local') !== false || strpos($serverName, '192.168.') !== false || php_sapi_name() === 'cli') ? 'LOCAL' : 'PRODUCCION';

if ($entorno === "LOCAL") {
    $host = "127.0.0.1";
    $port = "8889";
    $dbname = "cotizacioneslifefitness";
    $username = "root";
    $password = "root";
} else {
    $host = "mysql.advantascience.com";
    $port = "3306";
    $dbname = "cotizacioneslifefitness";
    $username = "lifefitnesdb";
    $password = "JT-sq16cy21";
}

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Auto migration: ensure mostrar_home and series exist, and auto-discover series from products
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM categorias LIKE 'mostrar_home'")->fetch();
        if (!$checkCol) {
            $pdo->exec("ALTER TABLE categorias ADD COLUMN mostrar_home TINYINT(1) DEFAULT 1");
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS series (
            id INT AUTO_INCREMENT PRIMARY KEY,
            categoria_id INT DEFAULT NULL,
            name VARCHAR(100) NOT NULL,
            pos INT DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        try {
            $pdo->exec("ALTER TABLE series DROP INDEX name");
        } catch (Exception $e) {}
        $checkCatCol = $pdo->query("SHOW COLUMNS FROM series LIKE 'categoria_id'")->fetch();
        if (!$checkCatCol) {
            $pdo->exec("ALTER TABLE series ADD COLUMN categoria_id INT DEFAULT NULL");
        }
        $chkSerie = $pdo->query("SHOW COLUMNS FROM productos LIKE 'serie'")->fetch();
        if (!$chkSerie) {
            $pdo->exec("ALTER TABLE productos ADD COLUMN serie VARCHAR(100) DEFAULT NULL;");
        }
        // Auto-discover series per category
        $distinctPairs = $pdo->query("SELECT DISTINCT serie, categoria_id FROM productos WHERE serie IS NOT NULL AND TRIM(serie) != ''")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($distinctPairs as $dp) {
            $sName = trim($dp['serie']);
            $cId = !empty($dp['categoria_id']) ? (int)$dp['categoria_id'] : null;
            if ($cId !== null) {
                $chk = $pdo->prepare("SELECT id FROM series WHERE name = ? AND categoria_id = ?");
                $chk->execute([$sName, $cId]);
            } else {
                $chk = $pdo->prepare("SELECT id FROM series WHERE name = ? AND categoria_id IS NULL");
                $chk->execute([$sName]);
            }
            if (!$chk->fetch()) {
                $maxPos = (int)$pdo->query("SELECT COALESCE(MAX(pos), 0) FROM series WHERE " . ($cId ? "categoria_id = $cId" : "categoria_id IS NULL"))->fetchColumn();
                $ins = $pdo->prepare("INSERT INTO series (categoria_id, name, pos) VALUES (?, ?, ?)");
                $ins->execute([$cId, $sName, $maxPos + 1]);
            }
        }
        try {
            $pdo->exec("ALTER TABLE series CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;");
            $pdo->exec("ALTER TABLE productos MODIFY COLUMN serie VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL;");
        } catch (Exception $e) {}
    } catch (Exception $e) {}

    $stmt = $pdo->query("SELECT p.*, COALESCE(s.pos, 999999) AS serie_pos FROM productos p LEFT JOIN series s ON (p.serie COLLATE utf8mb4_general_ci = s.name COLLATE utf8mb4_general_ci AND (s.categoria_id = p.categoria_id OR s.categoria_id IS NULL)) ORDER BY serie_pos ASC, p.series ASC, p.name ASC");
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($productos as &$p) {
        $p['media_json'] = (array_key_exists('media_json', $p) && !empty($p['media_json'])) ? json_decode($p['media_json'], true) : [];
    }

    $categorias = $pdo->query("SELECT * FROM categorias ORDER BY pos ASC")->fetchAll(PDO::FETCH_ASSOC);
    $marcas = $pdo->query("SELECT * FROM marcas ORDER BY pos ASC")->fetchAll(PDO::FETCH_ASSOC);
    $series = $pdo->query("SELECT * FROM series ORDER BY pos ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $dbError = null;
} catch (Exception $e) {
    $dbError = $e->getMessage();
    $productos = [];
    $categorias = [];
    $marcas = [];
    $series = [];
}
$productosJson = json_encode($productos);
$categoriasJson = json_encode($categorias);
$marcasJson = json_encode($marcas);
$seriesJson = json_encode($series);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Fitness Life S.A.S — Equipos de gimnasio en Colombia</title>
<meta name="description" content="Equipos de entrenamiento físico para gimnasios, hoteles y hogares. Importadores directos de las mejores marcas en Colombia."/>
<meta property="og:type" content="website"/>
<meta property="og:site_name" content="Fitness Life S.A.S"/>
<meta property="og:title" content="Fitness Life S.A.S — Equipos de gimnasio en Colombia"/>
<meta property="og:description" content="Importadores directos de equipos 100% americanos: Life Fitness, Precor, Hoist, Matrix, Cybex y Free Motion. Cali, cobertura nacional."/>
<meta property="og:image" content="https://www.fitnesslife.com.co/assets/logo.webp"/>
<meta name="twitter:card" content="summary_large_image"/>
<meta name="twitter:title" content="Fitness Life S.A.S — Equipos de gimnasio en Colombia"/>
<meta name="twitter:description" content="Importadores directos de equipos 100% americanos para gimnasios, hoteles y hogares."/>
<meta name="twitter:image" content="https://www.fitnesslife.com.co/assets/logo.webp"/>
<meta name="theme-color" content="#c62828"/>
<link rel="icon" type="image/png" href="assets/logo.webp">
<script src="js/support.js"></script>
<style>
              .grid-catalogo {
                display: grid;
                gap: 24px;
                grid-template-columns: repeat(4, minmax(0, 1fr));
              }
              @media (max-width: 1024px) {
                .grid-catalogo {
                  grid-template-columns: repeat(3, minmax(0, 1fr));
                }
              }
              @media (max-width: 768px) {
                .grid-catalogo {
                  grid-template-columns: repeat(2, minmax(0, 1fr));
                }
              }
              @media (max-width: 480px) {
                .grid-catalogo {
                  grid-template-columns: repeat(1, minmax(0, 1fr));
                }
              }
            </style>
</head>
<body>
<x-dc>
<helmet>
<template id="__bundler_thumbnail" data-bg-color="#141416">
  <svg viewBox="0 0 1200 800" xmlns="http://www.w3.org/2000/svg"><rect width="1200" height="800" fill="#141416"/><rect x="360" y="300" width="480" height="26" fill="#c62828"/><text x="600" y="430" font-family="Oswald, sans-serif" font-size="118" font-weight="700" fill="#ffffff" text-anchor="middle">FITNESS LIFE</text><text x="600" y="500" font-family="Inter, sans-serif" font-size="40" letter-spacing="14" fill="#8a8a90" text-anchor="middle">S.A.S</text></svg>
</template>
<title>Fitness Life S.A.S — Equipos de gimnasio en Colombia</title>
<meta name="description" content="Equipos de entrenamiento físico para gimnasios, hoteles y hogares. Importadores directos de las mejores marcas en Colombia."/>
<meta property="og:type" content="website"/>
<meta property="og:site_name" content="Fitness Life S.A.S"/>
<meta property="og:title" content="Fitness Life S.A.S — Equipos de gimnasio en Colombia"/>
<meta property="og:description" content="Importadores directos de equipos 100% americanos: Life Fitness, Precor, Hoist, Matrix, Cybex y Free Motion. Cali, cobertura nacional."/>
<meta property="og:image" content="assets/logo.webp"/>
<meta name="twitter:card" content="summary_large_image"/>
<meta name="twitter:title" content="Fitness Life S.A.S — Equipos de gimnasio en Colombia"/>
<meta name="twitter:description" content="Importadores directos de equipos 100% americanos para gimnasios, hoteles y hogares."/>
<meta name="twitter:image" content="assets/logo.webp"/>
<meta name="theme-color" content="#c62828"/>
<meta name="ext-resource-dependency" content="assets/cat_eliptica_main.webp" data-resource-id="assets/cat_eliptica_main.webp"/>
<meta name="ext-resource-dependency" content="assets/cat_bici_main.webp" data-resource-id="assets/cat_bici_main.webp"/>
<meta name="ext-resource-dependency" content="assets/cat_trotadora_main.webp" data-resource-id="assets/cat_trotadora_main.webp"/>
<meta name="ext-resource-dependency" content="assets/cat_pesas_main.webp" data-resource-id="assets/cat_pesas_main.webp"/>
<meta name="ext-resource-dependency" content="assets/hero_hoist.webp" data-resource-id="assets/hero_hoist.webp"/>
<meta name="ext-resource-dependency" content="uploads/elipticas.jpeg" data-resource-id="uploads/elipticas.jpeg"/>
<meta name="ext-resource-dependency" content="uploads/trotadoras.jpeg" data-resource-id="uploads/trotadoras.jpeg"/>
<meta name="ext-resource-dependency" content="uploads/estaticas.webp" data-resource-id="uploads/estaticas.webp"/>
<meta name="ext-resource-dependency" content="uploads/selectorizado.jpeg" data-resource-id="uploads/selectorizado.jpeg"/>
<meta name="ext-resource-dependency" content="uploads/escaleras.jpeg" data-resource-id="uploads/escaleras.jpeg"/>
<meta name="ext-resource-dependency" content="assets/cat_escaleras_v3.webp" data-resource-id="assets/cat_escaleras_v3.webp"/>
<meta name="ext-resource-dependency" content="uploads/spinning.jpeg" data-resource-id="uploads/spinning.jpeg"/>
<meta name="ext-resource-dependency" content="uploads/circuito.jpeg" data-resource-id="uploads/circuito.jpeg"/>
<meta name="ext-resource-dependency" content="uploads/funcional.jpeg" data-resource-id="uploads/funcional.jpeg"/>
<meta name="ext-resource-dependency" content="assets/sol_comercial.webp" data-resource-id="assets/sol_comercial.webp"/>
<meta name="ext-resource-dependency" content="assets/sol_institucional.webp" data-resource-id="assets/sol_institucional.webp"/>
<meta name="ext-resource-dependency" content="assets/sol_hogar.webp" data-resource-id="assets/sol_hogar.webp"/>
<meta name="ext-resource-dependency" content="assets/sol_accesorios.webp" data-resource-id="assets/sol_accesorios.webp"/>
<meta name="ext-resource-dependency" content="assets/trim_brand_lifefitness.webp" data-resource-id="assets/trim_brand_lifefitness.webp"/>
<meta name="ext-resource-dependency" content="assets/trim_brand_precor.webp" data-resource-id="assets/trim_brand_precor.webp"/>
<meta name="ext-resource-dependency" content="assets/trim_brand_hoist.webp" data-resource-id="assets/trim_brand_hoist.webp"/>
<meta name="ext-resource-dependency" content="assets/trim_brand_keiser.webp" data-resource-id="assets/trim_brand_keiser.webp"/>
<meta name="ext-resource-dependency" content="assets/trim_brand_truefitness.webp" data-resource-id="assets/trim_brand_truefitness.webp"/>
<meta name="ext-resource-dependency" content="assets/trim_brand_freemotion.webp" data-resource-id="assets/trim_brand_freemotion.webp"/>
<meta name="ext-resource-dependency" content="assets/trim_brand_realleader.webp" data-resource-id="assets/trim_brand_realleader.webp"/>
<meta name="ext-resource-dependency" content="assets/trim_client_mundofitness2.webp" data-resource-id="assets/trim_client_mundofitness2.webp"/>
<meta name="ext-resource-dependency" content="assets/trim_client_powerhouse.webp" data-resource-id="assets/trim_client_powerhouse.webp"/>
<meta name="ext-resource-dependency" content="assets/trim_client_energym.webp" data-resource-id="assets/trim_client_energym.webp"/>
<meta name="ext-resource-dependency" content="assets/trim_client_arena.webp" data-resource-id="assets/trim_client_arena.webp"/>
<meta name="ext-resource-dependency" content="assets/trim_client_nhhoteles.webp" data-resource-id="assets/trim_client_nhhoteles.webp"/>
<meta name="ext-resource-dependency" content="assets/trim_client_colegiobolivar.webp" data-resource-id="assets/trim_client_colegiobolivar.webp"/>
<meta name="ext-resource-dependency" content="assets/trim_client_unicoc.webp" data-resource-id="assets/trim_client_unicoc.webp"/>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin=""/>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap"/>
<style>
  body{margin:0;background:oklch(93% .004 270);color:oklch(20% .005 270);font-family:Inter,sans-serif}
  a{color:oklch(58% .22 25);text-decoration:none}
  a:hover{color:oklch(65% .24 27)}
  ::selection{background:oklch(58% .22 25);color:white}
  @keyframes ticker{0%{transform:translateX(0)}100%{transform:translateX(-50%)}}
  @keyframes slideFade{0%{opacity:0;transform:translateY(20px)}100%{opacity:1;transform:translateY(0)}}
  input::placeholder, textarea::placeholder{color:oklch(42% .01 270)}
  input, textarea, select{font-family:Inter,sans-serif}
</style>
</helmet>

<div style="background:oklch(93% .004 270);min-height:100vh;overflow-x:hidden">

  <div style="background:oklch(58% .22 25);color:white;overflow:hidden;white-space:nowrap;padding:5px 0">
    <div style="display:inline-block;animation:ticker 24s linear infinite;font-weight:600;font-size:10px;letter-spacing:0.12em">
      ⚡ IMPORTADORES DIRECTOS — EQUIPAMIENTO 100% AMERICANO ORIGINAL DE ALTO TRÁFICO ⚡&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;⚡ IMPORTADORES DIRECTOS — EQUIPAMIENTO 100% AMERICANO ORIGINAL DE ALTO TRÁFICO ⚡&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;⚡ IMPORTADORES DIRECTOS — EQUIPAMIENTO 100% AMERICANO ORIGINAL DE ALTO TRÁFICO ⚡&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    </div>
  </div>

  <header style="position:sticky;top:0;z-index:40;background:oklch(98% 0 0 / .96);backdrop-filter:blur(10px);border-bottom:1px solid oklch(28% .008 270)">
    <div style="max-width:1280px;margin:0 auto;padding:14px 24px;display:flex;align-items:center;justify-content:space-between;gap:24px">
      <img src="assets/logo.webp" alt="Fitness Life S.A.S" onClick="{{goHome}}" style="height:{{logoH}};width:auto;cursor:pointer;flex-shrink:0"/>
      <div style="display:{{headerSearchDisplay}};flex:1;margin:0 8px;max-width:600px">
        <input type="text" id="headerSearchInput" placeholder="Ej. Cybex, Abdominal..." value="{{catalogoSearch}}" onInput="{{onHeaderSearch}}" style="width:100%;box-sizing:border-box;padding:10px 16px;border:1px solid oklch(85% 0 0);border-radius:999px;font-size:14px;outline:none;box-shadow:0 4px 12px rgba(0,0,0,0.05)" />
      </div>
      <nav style="display:{{navDisplay}};align-items:center;gap:24px;flex-wrap:wrap">
        <a href="#inicio" onClick="{{goHome}}" style="font-size:13px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:oklch(14% .005 270)">Inicio</a>
        <a href="#catalogo" onClick="{{goToCatalogo}}" style="font-size:13px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:oklch(14% .005 270)">Catálogo</a>
        <div style="position:relative;display:flex;align-items:center" onMouseEnter="{{openEquipos}}" onMouseLeave="{{closeEquipos}}">
          <a href="#categorias" onClick="{{scrollTo_categorias}}" style="font-size:13px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:oklch(14% .005 270);display:flex;align-items:center;gap:6px">Equipos<svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="transform:{{equiposArrow}};transition:transform .25s ease"><path d="M6 9l6 6 6-6"></path></svg></a>
          <div style="display:{{equiposMenuDisplay}};position:absolute;top:100%;left:-12px;padding-top:14px;z-index:60">
            <div style="background:oklch(99% 0 0);border:1px solid oklch(88% .006 270);box-shadow:0 18px 38px -14px oklch(20% .01 270 / .35);min-width:230px;max-height:420px;overflow-y:auto;overflow-x:hidden">
              <sc-for list="{{navCategories}}" as="nc">
                <a href="#" onClick="{{nc.onClick}}" style="font-size:13px;font-weight:500;color:oklch(24% .006 270);padding:10px 18px;display:block;white-space:nowrap;transition:background .2s ease,color .2s ease;border-bottom:1px solid oklch(95% 0 0)" style-hover="background:oklch(58% .22 25);color:white">{{nc.name}}</a>
              </sc-for>
            </div>
          </div>
        </div>
        <a href="#soluciones" onClick="{{scrollTo_soluciones}}" style="font-size:13px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:oklch(14% .005 270)">Soluciones</a>
        <a href="#circuitos" onClick="{{scrollTo_circuitos}}" style="font-size:13px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:oklch(14% .005 270)">Circuitos</a>
        <a href="#personalizacion" onClick="{{scrollTo_personalizacion}}" style="font-size:13px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:oklch(14% .005 270)">Personalización</a>
        <a href="#marcas" onClick="{{scrollTo_marcas}}" style="font-size:13px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:oklch(14% .005 270)">Marcas</a>
        <a href="#clientes" onClick="{{scrollTo_clientes}}" style="font-size:13px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:oklch(14% .005 270)">Clientes</a>
        <a href="#" onClick="{{goToContacto}}" style="font-size:13px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:oklch(14% .005 270)">Contacto</a>
      </nav>
      <div style="display:flex;align-items:center;gap:12px;flex-shrink:0">
        <button onClick="{{openGlobalSearch}}" aria-label="Buscar" style="width:40px;height:40px;border-radius:999px;background:oklch(96% .01 270);border:1px solid oklch(90% .006 270);color:oklch(20% .005 270);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s ease" style-hover="background:oklch(58% .22 25);color:white;border-color:oklch(58% .22 25)">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        </button>
        <button onClick="{{goToContacto}}" style="display:{{ctaDisplay}};background:linear-gradient(135deg, oklch(58% .22 25), oklch(65% .24 27));color:white;border:none;padding:11px 22px;font-weight:700;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;cursor:pointer;flex-shrink:0">Cotizar</button>
      </div>
      <button onClick="{{toggleMenu}}" aria-label="Menú" style="display:{{burgerDisplay}};flex-direction:column;justify-content:center;gap:5px;width:44px;height:44px;background:none;border:none;cursor:pointer;padding:0;flex-shrink:0">
        <span style="display:block;height:2px;width:24px;background:oklch(14% .005 270);transition:transform .3s ease,opacity .3s ease;transform:{{bar1}}"></span>
        <span style="display:block;height:2px;width:24px;background:oklch(14% .005 270);transition:opacity .3s ease;opacity:{{bar2Opacity}}"></span>
        <span style="display:block;height:2px;width:24px;background:oklch(14% .005 270);transition:transform .3s ease;transform:{{bar3}}"></span>
      </button>
    </div>
    <div style="display:{{mobileMenuDisplay}};border-top:1px solid oklch(90% .006 270);background:oklch(99% 0 0);padding:8px 24px 20px">
      <nav style="display:flex;flex-direction:column">
        <a href="#inicio" onClick="{{goHome}}" style="font-size:15px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:oklch(14% .005 270);padding:14px 0;border-bottom:1px solid oklch(90% .006 270);display:block">Inicio</a>
        <a href="#catalogo" onClick="{{goToCatalogo}}" style="font-size:15px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:oklch(14% .005 270);padding:14px 0;border-bottom:1px solid oklch(90% .006 270);display:block">Catálogo</a>
        <a href="#categorias" onClick="{{scrollTo_categorias}}" style="font-size:15px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:oklch(14% .005 270);padding:14px 0;border-bottom:1px solid oklch(90% .006 270);display:block">Equipos</a>
        <sc-for list="{{navCategories}}" as="nc">
          <a href="#" onClick="{{nc.onClick}}" style="font-size:13px;font-weight:500;color:oklch(38% .008 270);padding:11px 0 11px 18px;border-bottom:1px solid oklch(93% .004 270);display:block">{{nc.name}}</a>
        </sc-for>
        <a href="#soluciones" onClick="{{scrollTo_soluciones}}" style="font-size:15px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:oklch(14% .005 270);padding:14px 0;border-bottom:1px solid oklch(90% .006 270);display:block">Soluciones</a>
        <a href="#circuitos" onClick="{{scrollTo_circuitos}}" style="font-size:15px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:oklch(14% .005 270);padding:14px 0;border-bottom:1px solid oklch(90% .006 270);display:block">Circuitos</a>
        <a href="#personalizacion" onClick="{{scrollTo_personalizacion}}" style="font-size:15px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:oklch(14% .005 270);padding:14px 0;border-bottom:1px solid oklch(90% .006 270);display:block">Personalización</a>
        <a href="#marcas" onClick="{{scrollTo_marcas}}" style="font-size:15px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:oklch(14% .005 270);padding:14px 0;border-bottom:1px solid oklch(90% .006 270);display:block">Marcas</a>
        <a href="#clientes" onClick="{{scrollTo_clientes}}" style="font-size:15px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:oklch(14% .005 270);padding:14px 0;border-bottom:1px solid oklch(90% .006 270);display:block">Clientes</a>
        <a href="#" onClick="{{goToContacto}}" style="font-size:15px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:oklch(14% .005 270);padding:14px 0;border-bottom:1px solid oklch(90% .006 270);display:block">Contacto</a>
      </nav>
      <button onClick="{{goToContacto}}" style="width:100%;margin-top:18px;background:linear-gradient(135deg, oklch(58% .22 25), oklch(65% .24 27));color:white;border:none;padding:15px;font-weight:700;font-size:13px;letter-spacing:0.08em;text-transform:uppercase;cursor:pointer">Cotizar</button>
    </div>
  </header>

  <sc-if value="{{isHome}}" hint-placeholder-val="{{true}}">
  <main>

    <section id="inicio" style="position:relative;height:{{heroH}};min-height:{{heroMinH}};overflow:hidden;background:oklch(10% .005 270)">
      <sc-for list="{{heroSlides}}" as="slide" hint-placeholder-count="2">
        <div style="position:absolute;inset:0;opacity:{{slide.opacity}};transition:opacity 1s ease;background:oklch(10% .005 270)">
          <sc-if value="{{slide.isImage}}" hint-placeholder-val="{{true}}">
            <img src="assets/hero_hoist.webp" data-sc-camel-src="{{slide.img}}" alt="{{slide.alt}}" ref="{{heroImgRef}}" style="position:absolute;inset:0;width:100%;height:100%;object-fit:{{slide.fit}}"/>
          </sc-if>
          <sc-if value="{{slide.showVideo}}" hint-placeholder-val="{{false}}">
            <video ref="{{videoRef}}" sc-camel-src="{{slide.videoSrc}}" autoPlay="{{true}}" muted="{{true}}" loop="{{true}}" playsInline="{{true}}" preload="auto" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover"></video>
          </sc-if>
          <div style="position:absolute;inset:0;background:linear-gradient(105deg, oklch(16% .006 270 / .68) 0%, oklch(16% .006 270 / .42) 50%, oklch(16% .006 270 / .16) 100%)"></div>
          <div style="position:absolute;inset:0;background:linear-gradient(to right, oklch(12% .005 270 / .4), oklch(12% .005 270 / .18) 55%, oklch(12% .005 270 / .04))"></div>
        </div>
      </sc-for>
      <button onClick="{{toggleSound}}" aria-label="{{soundLabel}}" title="{{soundLabel}}" style="position:absolute;right:24px;bottom:30px;z-index:25;display:{{soundBtnDisplay}};align-items:center;justify-content:center;width:44px;height:44px;border-radius:999px;background:oklch(16% .006 270 / .55);border:1px solid oklch(100% 0 0 / .3);color:white;font-size:17px;cursor:pointer;backdrop-filter:blur(6px);transition:background .3s ease,transform .3s ease" style-hover="background:oklch(58% .22 25);transform:translateY(-3px)">{{soundIcon}}</button>
      <div style="position:absolute;bottom:34px;left:50%;transform:translateX(-50%);z-index:20;display:flex;gap:10px">
        <sc-for list="{{heroDots}}" as="dot" hint-placeholder-count="2">
          <button onClick="{{dot.onClick}}" aria-label="{{dot.label}}" style="width:{{dot.width}};height:4px;border:none;padding:0;cursor:pointer;background:{{dot.bg}};transition:width .4s cubic-bezier(.2,.8,.2,1),background .3s ease"></button>
        </sc-for>
      </div>
      <div style="text-align:center;margin-top:48px">
        <button onClick="{{goToCatalogo}}" style="display:inline-flex;align-items:center;gap:10px;background:oklch(20% .005 270);color:white;border:none;padding:18px 36px;font-family:Oswald,sans-serif;font-weight:600;font-size:16px;letter-spacing:0.05em;text-transform:uppercase;cursor:pointer;border-radius:4px;transition:background .3s ease,transform .3s ease" style-hover="background:oklch(58% .22 25);transform:translateY(-2px)">
          Ver catálogo completo de productos →
        </button>
      </div>
      <div style="position:relative;z-index:10;max-width:1280px;margin:0 auto;padding:0 24px;height:100%;display:flex;align-items:center">
        <div style="max-width:620px;animation:slideFade .7s ease-out">
          <h1 style="font-family:Oswald,sans-serif;font-size:clamp(32px,5.5vw,64px);font-weight:700;line-height:0.96;margin:0 0 22px;color:oklch(98% 0 0)">INSPIRANDO<br/>AL MUNDO<br/>A ENTRENAR</h1>
          <p style="font-size:{{heroPSize}};color:oklch(80% .01 270);max-width:420px;margin:0 0 {{heroPGap}};line-height:1.55">Equipos de cardio y fuerza comercial líderes en el mundo, diseñados para ofrecer un rendimiento superior.</p>
          <a href="#categorias" onClick="{{scrollTo_categorias}}" style="display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg, oklch(58% .22 25), oklch(65% .24 27));color:white;padding:16px 30px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;font-size:13px;min-height:52px;box-sizing:border-box">Ver Equipos →</a>
  
      </div>
      </div>
    </section>

    <section id="categorias" ref="{{categoriasRef}}" style="padding:{{secPad}};background:oklch(88% .004 265);position:relative;overflow:hidden"><div style="max-width:1280px;margin:0 auto;position:relative">
      <div style="text-align:left;max-width:640px;margin:0 0 56px;opacity:{{categoriesHeaderOpacity}};transform:{{categoriesHeaderTransform}};transition:opacity .6s ease,transform .6s ease">
        <div style="display:flex;align-items:center;gap:12px;font-size:12px;letter-spacing:0.3em;color:oklch(58% .22 25);font-weight:700;text-transform:uppercase;margin-bottom:14px"><span style="width:44px;height:2px;background:oklch(58% .22 25)"></span>Catálogo</div>
        <h2 style="font-family:Oswald,sans-serif;font-size:clamp(30px,4vw,48px);font-weight:700;margin:0 0 16px;text-transform:uppercase;color:oklch(16% .006 270)">Equipos <span style="color:oklch(58% .22 25)">Exclusivos</span></h2>
        <p style="color:oklch(42% .01 270);font-size:16px;line-height:1.6;margin:0">Encuentra equipos profesionales de las marcas más prestigiosas: Life Fitness, Precor, Hoist, Cybex, Matrix y Free Motion.</p>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:24px">
        <sc-for list="{{categories}}" as="cat" hint-placeholder-count="4">
          <div onClick="{{cat.onClick}}" onMouseEnter="{{cat.onEnterCard}}" onMouseLeave="{{cat.onLeaveCard}}" style="position:relative;cursor:pointer;background:oklch(98% 0 0);border:1px solid oklch(88% .006 270);border-radius:22px;overflow:hidden;opacity:{{cat.revealOpacity}};transform:{{cat.revealTransform}} scale(1);transition:box-shadow .45s ease,border-color .45s ease,transform .45s cubic-bezier(.2,.8,.2,1),opacity .6s ease {{cat.revealDelay}}" style-hover="box-shadow:0 30px 60px -18px oklch(58% .22 25 / .38),0 0 0 1px oklch(58% .22 25 / .35);border-color:oklch(58% .22 25 / .5);transform:translateY(-10px) scale(1.02)">
            <div style="position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,oklch(58% .22 25),oklch(68% .24 30));transform:scaleX({{cat.topBorderScale}});transform-origin:left;transition:transform .5s cubic-bezier(.2,.8,.2,1);z-index:3"></div>
            <div style="position:relative;height:340px;overflow:hidden;background:oklch(16% .006 270)">
              <img loading="lazy" sc-camel-src="{{cat.img}}" alt="{{cat.name}}" style="width:100%;height:100%;object-fit:cover;display:block;transition:transform .7s cubic-bezier(.2,.8,.2,1),filter .5s ease" style-hover="transform:scale(1.09);filter:brightness(1.06) contrast(1.04) saturate(1.05)"/>
              <div style="position:absolute;inset:0;background:radial-gradient(120% 80% at 50% 110%, oklch(58% .22 25 / .38), transparent 62%);opacity:0;transition:opacity .5s ease" style-hover="opacity:1"></div>
              <div style="position:absolute;left:18px;top:18px;padding:7px 14px;border-radius:999px;background:oklch(20% .005 270 / .6);backdrop-filter:blur(6px);border:1px solid oklch(98% 0 0 / .22);color:oklch(98% 0 0);font-size:10px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;opacity:0;transform:translateY(-8px);transition:opacity .4s ease,transform .4s cubic-bezier(.2,.8,.2,1)" style-hover="opacity:1;transform:translateY(0)">{{cat.tag}}</div>
            </div>
            <div style="padding:24px 26px 26px;overflow:hidden">
              <div style="font-size:11px;letter-spacing:0.15em;color:oklch(58% .22 25);font-weight:700;text-transform:uppercase;margin-bottom:8px;height:30px;display:flex;align-items:flex-start;transition:transform .35s ease" style-hover="transform:translateX(5px)">{{cat.tag}}</div>
              <h3 style="font-family:Oswald,sans-serif;font-size:23px;font-weight:600;margin:0 0 16px;text-transform:uppercase;color:oklch(20% .005 270);transition:transform .35s ease .04s" style-hover="transform:translateX(5px)">{{cat.name}}</h3>
              <span style="font-size:11px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:oklch(20% .005 270);display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:999px;border:1px solid oklch(85% .006 270);transition:background .35s ease,color .35s ease,border-color .35s ease" style-hover="background:oklch(58% .22 25);color:oklch(99% 0 0);border-color:oklch(58% .22 25)">Ver más <span style="display:inline-block;transition:transform .35s ease" style-hover="transform:translateX(5px)">→</span></span>
            </div>
          </div>
        </sc-for>
      </div>
      <div style="text-align:center;margin-top:48px">
        <button onClick="{{goToCatalogo}}" style="display:inline-flex;align-items:center;gap:10px;background:oklch(20% .005 270);color:white;border:none;padding:18px 36px;font-family:Oswald,sans-serif;font-weight:600;font-size:16px;letter-spacing:0.05em;text-transform:uppercase;cursor:pointer;border-radius:4px;transition:background .3s ease,transform .3s ease" style-hover="background:oklch(58% .22 25);transform:translateY(-2px)">
          Ver catálogo completo de productos →
        </button>
      </div>
      </div>
    </section>

    <section style="padding:{{bandPad}};overflow:hidden;position:relative;background:oklch(97% .002 270)">
      <div style="text-align:center;max-width:640px;margin:0 auto 40px;padding:0 24px">
        <div style="font-size:12px;letter-spacing:0.3em;color:oklch(58% .22 25);font-weight:700;text-transform:uppercase;margin-bottom:14px">Portafolio</div>
        <h2 style="font-family:Oswald,sans-serif;font-size:clamp(30px,4vw,48px);font-weight:700;margin:0 0 16px;text-transform:uppercase">Equipos que <span style="color:oklch(58% .22 25)">Transforman Espacios</span></h2>
        <p style="color:oklch(42% .01 270);font-size:16px;line-height:1.6;margin:0">Una muestra de nuestras líneas de cardio, fuerza y circuitos, instaladas en gimnasios y centros deportivos de todo el país.</p>
      </div>
      <div style="position:absolute;left:0;top:0;bottom:0;width:140px;z-index:2;pointer-events:none;background:linear-gradient(90deg,oklch(97% .002 270),oklch(97% .002 270 / 0))"></div>
      <div style="position:absolute;right:0;top:0;bottom:0;width:140px;z-index:2;pointer-events:none;background:linear-gradient(270deg,oklch(97% .002 270),oklch(97% .002 270 / 0))"></div>
      <div style="display:flex;width:max-content;gap:24px;animation:ticker 58s linear infinite">
        <sc-for list="{{carouselCards}}" as="p" hint-placeholder-count="10">
          <div onClick="{{p.onClick}}" style="flex-shrink:0;cursor:pointer;position:relative;overflow:hidden;width:{{cardW}};height:{{cardH}};border-radius:24px;box-shadow:0 14px 34px -10px oklch(20% .01 270 / .28);outline:0px solid oklch(58% .22 25);outline-offset:-2px;transition:transform .45s cubic-bezier(.2,.8,.2,1),box-shadow .45s ease,outline-width .35s ease" style-hover="transform:translateY(-14px) scale(1.04);box-shadow:0 34px 60px -18px oklch(58% .22 25 / .48);outline-width:3px">
            <img loading="lazy" sc-camel-src="{{p.img}}" alt="{{p.name}}" style="width:100%;height:100%;object-fit:contain;transform:scale({{p.zoom}});background:oklch(100% 0 0);padding:{{p.pad}};box-sizing:border-box;transition:transform .8s cubic-bezier(.2,.8,.2,1)" style-hover="transform:scale(1.07)"/>
            <div style="position:absolute;left:0;right:0;bottom:0;height:76px;background:oklch(16% .006 270)"></div>
            <div style="position:absolute;inset:0;background:radial-gradient(120% 75% at 50% 112%, oklch(58% .22 25 / .42), transparent 62%);opacity:0;transition:opacity .5s ease" style-hover="opacity:1"></div>
            <div style="position:absolute;left:0;right:0;bottom:0;padding:12px 18px;display:flex;flex-direction:column;gap:3px">
              <span style="font-size:11px;font-weight:600;letter-spacing:0.06em;color:oklch(75% .2 27);transition:transform .4s ease" style-hover="transform:translateX(5px)">{{p.tag}}</span>
              <h3 style="font-family:Oswald,sans-serif;font-size:19px;font-weight:600;color:white;margin:0;text-transform:uppercase;letter-spacing:.03em;transition:transform .4s ease .04s" style-hover="transform:translateX(5px)">{{p.name}}</h3>
              
            </div>
          </div>
        </sc-for>
      </div>
    </section>

    <section id="soluciones" ref="{{solucionesRef}}" style="padding:{{secPadLg}};background:oklch(14% .005 270);position:relative;overflow:hidden">
      <div style="position:absolute;inset:0;background:linear-gradient(103deg, oklch(24% .008 270) 0%, oklch(24% .008 270) 48%, oklch(13% .006 270) 48.4%, oklch(13% .006 270) 100%);pointer-events:none"></div>
      <div style="position:absolute;top:-120px;right:-80px;width:420px;height:420px;border:1px solid oklch(58% .22 25 / .22);border-radius:999px"></div>
      <div style="position:absolute;bottom:-160px;left:-100px;width:340px;height:340px;border:1px solid oklch(58% .22 25 / .14);border-radius:999px"></div>
      <div style="position:absolute;inset:0;pointer-events:none;background:linear-gradient(to bottom right, oklch(30% .006 270) 0%, oklch(30% .006 270) 50%, oklch(14% .005 270) 50%, oklch(14% .005 270) 100%)"></div>
      <div style="max-width:1280px;margin:0 auto;position:relative">
        <div style="max-width:720px;margin:0 0 64px;opacity:{{solucionesHeaderOpacity}};transform:{{solucionesHeaderTransform}};transition:opacity .7s ease,transform .7s cubic-bezier(.2,.8,.2,1)">
          <div style="display:flex;align-items:center;gap:14px;margin-bottom:18px">
            <div style="width:72px;height:2px;background:oklch(58% .22 25)"></div>
            <div style="font-size:12px;letter-spacing:0.3em;color:oklch(65% .24 27);font-weight:700;text-transform:uppercase">Soluciones</div>
          </div>
          <h2 style="font-family:Oswald,sans-serif;font-size:clamp(28px,3.4vw,42px);font-weight:700;margin:0;color:oklch(97% 0 0);line-height:1.12;text-wrap:pretty">Conoce nuestras <span style="color:oklch(65% .24 27)">soluciones</span> en equipos para entrenamiento físico</h2>
        </div>
        <div style="display:grid;grid-template-columns:{{grid4}};gap:1px;background:oklch(42% .006 270)">
          <sc-for list="{{solutions}}" as="sol" hint-placeholder-count="4">
            <div ref="{{sol.cardRef}}" onMouseEnter="{{sol.onEnter}}" onMouseLeave="{{sol.onLeave}}" onClick="{{sol.onClick}}" style="position:relative;overflow:hidden;cursor:pointer;background:oklch(25% .008 270);padding:44px 30px 40px;min-height:330px;display:flex;flex-direction:column;opacity:{{sol.revealOpacity}};transform:{{sol.revealTransform}};transition:opacity .7s ease {{sol.revealDelay}},transform .7s cubic-bezier(.2,.8,.2,1) {{sol.revealDelay}}">
              <img loading="lazy" sc-camel-src="{{sol.bgImg}}" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:{{sol.imgOpacity}};transform:{{sol.imgTransform}};transition:opacity .6s ease,transform .8s cubic-bezier(.2,.8,.2,1);pointer-events:none"/>
              <div style="position:absolute;inset:0;background:radial-gradient(130% 90% at 50% 120%, oklch(58% .22 25 / .26), transparent 62%);opacity:0;transition:opacity .6s ease" style-hover="opacity:1"></div>
              <div style="position:absolute;left:0;right:0;bottom:0;height:100%;background:linear-gradient(to top,oklch(46% .2 25 / .3),oklch(58% .22 25 / .16));transform:scaleY({{sol.fillScale}});transform-origin:bottom;transition:transform .55s cubic-bezier(.2,.8,.2,1)"></div>
              <div style="position:absolute;top:0;left:0;right:0;height:4px;background:oklch(99% 0 0);transform:scaleX({{sol.fillScale}});transform-origin:left;transition:transform .5s cubic-bezier(.2,.8,.2,1) .1s"></div>
              <div style="position:relative;flex:1;display:flex;flex-direction:column;justify-content:flex-end;transition:transform .5s cubic-bezier(.2,.8,.2,1);transform:{{sol.contentTransform}}">
                <div style="width:40px;height:3px;background:transparent;margin-bottom:20px"></div>
                <h3 style="font-family:Oswald,sans-serif;margin:0 0 14px;color:oklch(98% 0 0);letter-spacing:.01em;line-height:1;text-transform:uppercase;display:flex;flex-direction:column;justify-content:flex-end;min-height:78px"><span style="font-size:17px;font-weight:500;letter-spacing:.16em;opacity:.75;margin-bottom:6px;min-height:22px">{{sol.kicker}}</span><span style="font-size:clamp(28px,2.4vw,38px);font-weight:700">{{sol.main}}</span></h3>
                <p style="color:{{sol.descColor}};font-size:14px;line-height:1.6;margin:0 0 22px;height:92px;overflow:hidden;transition:color .4s ease">{{sol.desc}}</p>
                <div style="display:flex;align-items:center;gap:10px;font-size:12px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:{{sol.linkColor}};transition:color .4s ease">
                  <span>Ver más</span>
                  <span style="display:inline-block;transition:transform .4s cubic-bezier(.2,.8,.2,1);transform:{{sol.arrowTransform}}">→</span>
                </div>
              </div>
            </div>
          </sc-for>
        </div>
      </div>
    </section>

    <section id="circuitos" ref="{{circuitosRef}}" style="padding:{{secPad}};background:oklch(86% .004 265)"><div style="max-width:1280px;margin:0 auto">
      <div style="text-align:center;max-width:560px;margin:0 auto 64px;opacity:{{circuitosHeaderOpacity}};transform:{{circuitosHeaderTransform}};transition:opacity .7s ease,transform .7s cubic-bezier(.2,.8,.2,1)">
        <div style="display:flex;align-items:center;justify-content:center;gap:12px;font-size:12px;letter-spacing:0.3em;color:oklch(58% .22 25);font-weight:700;text-transform:uppercase;margin-bottom:14px"><span style="width:36px;height:2px;background:oklch(58% .22 25);transform:scaleX({{circBarScale}});transform-origin:right;transition:transform .7s ease .15s"></span>Equipos Remanufacturados<span style="width:36px;height:2px;background:oklch(58% .22 25);transform:scaleX({{circBarScale}});transform-origin:left;transition:transform .7s ease .15s"></span></div>
        <h2 style="font-family:Oswald,sans-serif;font-size:clamp(32px,4.4vw,52px);font-weight:700;margin:0 0 16px;line-height:1.08;text-wrap:pretty">Circuitos de Fuerza <span style="color:oklch(58% .22 25)">Remanufacturados</span></h2>
        <p style="color:oklch(42% .01 270);font-size:16px;line-height:1.6;margin:0">Equipa tu centro fitness con sistemas de entrenamiento de grado comercial reconstruidos a la perfección con un ahorro de hasta el 60%.</p>
      </div>

      <div style="display:grid;grid-template-columns:{{grid2wide}};gap:{{gap2}};align-items:center;margin-bottom:110px">
        <div style="position:relative;aspect-ratio:16/11;overflow:hidden;border-radius:22px;background:oklch(91% .004 270);box-shadow:0 16px 38px -14px oklch(20% .01 270 / .3);opacity:{{circRow1ImgOpacity}};transform:{{circRow1ImgTransform}};transition:opacity .8s ease,transform .8s cubic-bezier(.2,.8,.2,1),box-shadow .45s ease" style-hover="box-shadow:0 36px 68px -22px oklch(58% .22 25 / .48);transform:translateY(-10px)">
          <img loading="lazy" src="uploads/8FEB4914-1274-4F8E-A291-D73031341E44_1_102_o.jpeg" alt="Circuito Matrix Fitness" style="width:100%;height:100%;object-fit:cover;transition:transform .8s cubic-bezier(.2,.8,.2,1)" style-hover="transform:scale(1.07)"/>
          <div style="position:absolute;top:18px;left:18px;width:56px;height:4px;border-radius:4px;background:oklch(58% .22 25);transform:scaleX({{circBarScale}});transform-origin:left;transition:transform .7s ease .3s"></div>
          <div style="position:absolute;top:18px;left:18px;width:4px;height:56px;border-radius:4px;background:oklch(58% .22 25);transform:scaleY({{circBarScale}});transform-origin:top;transition:transform .7s ease .4s"></div>
          <div style="position:absolute;bottom:22px;left:22px;background:oklch(58% .22 25);color:white;padding:10px 18px;border-radius:999px;font-family:Oswald,sans-serif;font-size:15px;font-weight:700;letter-spacing:.06em;opacity:{{circRow1ImgOpacity}};transition:opacity .6s ease .5s">HASTA −60%</div>
        </div>
        <div style="opacity:{{circRow1TextOpacity}};transform:{{circRow1TextTransform}};transition:opacity .7s ease .1s,transform .7s cubic-bezier(.2,.8,.2,1) .1s">
          <div style="font-size:11px;letter-spacing:0.15em;color:oklch(65% .24 27);font-weight:700;text-transform:uppercase;margin-bottom:10px">Respaldo Fitness Life — Matrix Fitness</div>
          <h3 style="font-family:Oswald,sans-serif;font-size:30px;font-weight:600;margin:0 0 18px;line-height:1.15">Equipos Matrix de Fuerza con Respaldo de Fitness Life</h3>
          <p style="color:oklch(42% .01 270);font-size:15px;line-height:1.65;margin:0 0 26px">Equipos Matrix seleccionados, revisados y avalados por Fitness Life. Renueva tus zonas de entrenamiento con garantía integral.</p>
          <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:28px;opacity:{{circRow1TextOpacity}};transition:opacity .6s ease .25s">
            <span style="border:1px solid oklch(83% .006 270);padding:8px 14px;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:oklch(30% .008 270)">Garantía Real</span>
            <span style="border:1px solid oklch(83% .006 270);padding:8px 14px;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:oklch(30% .008 270)">Precio de Oportunidad</span>
            <span style="border:1px solid oklch(83% .006 270);padding:8px 14px;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:oklch(30% .008 270)">Ergonomía Premium</span>
          </div>
          <div style="display:flex;gap:16px;flex-wrap:wrap">
            <a href="#" onClick="{{goToContacto}}" style="background:linear-gradient(135deg, oklch(58% .22 25), oklch(65% .24 27));color:white;padding:14px 26px;font-weight:700;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;transition:transform .3s cubic-bezier(.2,.8,.2,1),box-shadow .3s ease" style-hover="transform:translateY(-3px);box-shadow:0 14px 26px -10px oklch(58% .22 25 / .6)">Cotizar Circuito</a>
            <a href="#" style="border:1px solid oklch(83% .006 270);color:oklch(20% .005 270);padding:14px 26px;font-weight:700;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;transition:border-color .3s ease,transform .3s ease,background .3s ease" style-hover="border-color:oklch(58% .22 25);transform:translateY(-3px);background:oklch(96% .01 25)">Video Demostrativo</a>
          </div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:{{grid2wide}};gap:{{gap2}};align-items:center">
        <div style="order:2;opacity:{{circRow2TextOpacity}};transform:{{circRow2TextTransform}};transition:opacity .7s ease .1s,transform .7s cubic-bezier(.2,.8,.2,1) .1s">
          <div style="font-size:11px;letter-spacing:0.15em;color:oklch(65% .24 27);font-weight:700;text-transform:uppercase;margin-bottom:10px">Garantía de Fábrica — Life Fitness</div>
          <h3 style="font-family:Oswald,sans-serif;font-size:30px;font-weight:600;margin:0 0 18px;line-height:1.15">Circuito Life Fitness Optima™ Series 30 Express</h3>
          <p style="color:oklch(42% .01 270);font-size:15px;line-height:1.65;margin:0 0 26px">Circuito compacto de 30 minutos remanufacturado a especificaciones de fábrica. Ideal para hoteles, condominios y centros premium.</p>
          <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:28px;opacity:{{circRow2TextOpacity}};transition:opacity .6s ease .25s">
            <span style="border:1px solid oklch(83% .006 270);padding:8px 14px;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:oklch(30% .008 270)">Dual-Function</span>
            <span style="border:1px solid oklch(83% .006 270);padding:8px 14px;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:oklch(30% .008 270)">Circuito Rápido</span>
            <span style="border:1px solid oklch(83% .006 270);padding:8px 14px;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:oklch(30% .008 270)">Acabado Como Nuevo</span>
          </div>
          <div style="display:flex;gap:16px;flex-wrap:wrap">
            <a href="#" onClick="{{goToContacto}}" style="background:linear-gradient(135deg, oklch(58% .22 25), oklch(65% .24 27));color:white;padding:14px 26px;font-weight:700;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;transition:transform .3s cubic-bezier(.2,.8,.2,1),box-shadow .3s ease" style-hover="transform:translateY(-3px);box-shadow:0 14px 26px -10px oklch(58% .22 25 / .6)">Cotizar Circuito</a>
            <a href="#" style="border:1px solid oklch(83% .006 270);color:oklch(20% .005 270);padding:14px 26px;font-weight:700;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;transition:border-color .3s ease,transform .3s ease,background .3s ease" style-hover="border-color:oklch(58% .22 25);transform:translateY(-3px);background:oklch(96% .01 25)">Video Demostrativo</a>
          </div>
        </div>
        <div style="order:1;position:relative;aspect-ratio:16/11;overflow:hidden;border-radius:22px;background:oklch(91% .004 270);box-shadow:0 16px 38px -14px oklch(20% .01 270 / .3);opacity:{{circRow2ImgOpacity}};transform:{{circRow2ImgTransform}};transition:opacity .8s ease,transform .8s cubic-bezier(.2,.8,.2,1),box-shadow .45s ease" style-hover="box-shadow:0 36px 68px -22px oklch(58% .22 25 / .48);transform:translateY(-10px)">
          <img loading="lazy" src="uploads/BEC1A73E-E07A-4973-8E7B-51ED81B82924_1_102_o.jpeg" alt="Circuito Life Fitness Optima" style="width:100%;height:100%;object-fit:cover;transition:transform .8s cubic-bezier(.2,.8,.2,1)" style-hover="transform:scale(1.07)"/>
          <div style="position:absolute;top:18px;right:18px;width:56px;height:4px;border-radius:4px;background:oklch(58% .22 25);transform:scaleX({{circBarScale}});transform-origin:right;transition:transform .7s ease .3s"></div>
          <div style="position:absolute;top:18px;right:18px;width:4px;height:56px;border-radius:4px;background:oklch(58% .22 25);transform:scaleY({{circBarScale}});transform-origin:top;transition:transform .7s ease .4s"></div>
          <div style="position:absolute;bottom:22px;right:22px;background:oklch(20% .005 270);color:white;padding:10px 18px;border-radius:999px;font-family:Oswald,sans-serif;font-size:15px;font-weight:700;letter-spacing:.06em;opacity:{{circRow2ImgOpacity}};transition:opacity .6s ease .5s">30 MIN / CUERPO COMPLETO</div>
        </div>
      </div>
      </div>
    </section>

    <section id="personalizacion" ref="{{colorsRef}}" style="padding:{{secPad}};background:oklch(98% 0 0)">
      <div style="max-width:1280px;margin:0 auto">
        <div style="text-align:center;max-width:640px;margin:0 auto 44px;opacity:{{colorsOpacity}};transform:{{colorsTransform}};transition:opacity .6s ease,transform .6s ease">
          <div style="font-size:12px;letter-spacing:0.3em;color:oklch(58% .22 25);font-weight:700;text-transform:uppercase;margin-bottom:14px">Personalización</div>
          <h2 style="font-family:Oswald,sans-serif;font-size:clamp(28px,4vw,44px);font-weight:700;margin:0 0 16px;text-transform:uppercase">Diseña tu equipo <span style="color:oklch(58% .22 25)">a tu medida</span></h2>
          <p style="color:oklch(42% .01 270);font-size:16px;line-height:1.6;margin:0">Elige el color de la estructura, la cojinería y los pines para que tus equipos combinen con la identidad de tu gimnasio.</p>
        </div>
        <div style="display:grid;grid-template-columns:{{persCols}};gap:28px;align-items:stretch">
          <div style="position:relative;border-radius:20px;overflow:hidden;background:oklch(14% .005 270);aspect-ratio:9/16;max-height:{{persVideoMaxH}};width:{{persVideoW}};justify-self:center;box-shadow:0 24px 50px -24px oklch(20% .01 270 / .35);opacity:{{colorsOpacity}};transform:{{colorsImgTransform}};transition:opacity .8s ease,transform .8s cubic-bezier(.2,.8,.2,1)">
            <video ref="{{rs800Ref}}" src="assets/realleader_rs800_loop.mp4" autoPlay="{{true}}" muted="{{rs800Muted}}" loop="{{true}}" playsInline="{{true}}" preload="auto" style="width:100%;height:100%;object-fit:cover;display:block"></video>
            <button onClick="{{toggleRs800Sound}}" aria-label="{{rs800SoundLabel}}" style="position:absolute;top:16px;right:16px;z-index:2;display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:999px;border:none;background:oklch(10% .005 270 / .65);color:white;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;cursor:pointer;backdrop-filter:blur(6px);transition:background .3s ease" style-hover="background:oklch(58% .22 25)"><span style="font-size:14px;line-height:1">{{rs800SoundIcon}}</span>{{rs800SoundLabel}}</button>
            <div style="position:absolute;left:0;right:0;bottom:0;padding:22px;background:linear-gradient(to top,oklch(10% .005 270 / .78),oklch(10% .005 270 / 0))">
              <div style="font-size:11px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:oklch(72% .2 27);margin-bottom:4px">Realleader USA</div>
              <div style="font-family:Oswald,sans-serif;font-size:26px;font-weight:700;color:white;text-transform:uppercase;letter-spacing:.02em">Serie RS800</div>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;justify-content:center;gap:24px;min-width:0;opacity:{{colorsOpacity}};transform:{{colorsImgTransform}};transition:opacity .8s ease .15s,transform .8s cubic-bezier(.2,.8,.2,1) .15s">
            <div style="border-radius:20px;overflow:hidden;background:white;border:1px solid oklch(88% .006 270);box-shadow:0 24px 50px -24px oklch(20% .01 270 / .25)">
              <img src="assets/personalizacion_colores.png" alt="Opciones de color de cojinería, estructura y pines" style="width:100%;height:auto;display:block"/>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:12px">
              <span style="border:1px solid oklch(83% .006 270);border-radius:999px;padding:10px 18px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:oklch(30% .008 270)">8 colores de cojinería</span>
              <span style="border:1px solid oklch(83% .006 270);border-radius:999px;padding:10px 18px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:oklch(30% .008 270)">8 acabados de estructura</span>
              <a href="#" onClick="{{goToContacto}}" style="background:oklch(58% .22 25);color:white;border-radius:999px;padding:10px 22px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;transition:transform .3s ease" style-hover="transform:translateY(-3px);color:white">Cotiza tu combinación →</a>
            </div>
          </div>
        </div>
      </div>
    </section>

    

    <section ref="{{processRef}}" style="padding:{{secPad}};background:oklch(93% .004 270)">
      <div style="max-width:1280px;margin:0 auto">
        <div style="text-align:center;max-width:560px;margin:0 auto 64px;opacity:{{processHeaderOpacity}};transform:{{processHeaderTransform}};transition:opacity .6s ease,transform .6s ease">
          <div style="font-size:12px;letter-spacing:0.3em;color:oklch(58% .22 25);font-weight:700;text-transform:uppercase;margin-bottom:14px">Estándar de Calidad</div>
          <h2 style="font-family:Oswald,sans-serif;font-size:clamp(28px,4vw,44px);font-weight:700;margin:0 0 16px">Nuestro Proceso de Remanufactura Élite</h2>
          <p style="color:oklch(42% .01 270);font-size:16px;line-height:1.6;margin:0">No vendemos equipos usados comunes. Cada máquina pasa por un riguroso proceso de reconstrucción artesanal e industrial para garantizar que luzca y rinda al 100%.</p>
        </div>
        <div style="display:grid;grid-template-columns:{{grid4}};gap:1px;background:oklch(85% .004 270);border-top:1px solid oklch(85% .004 270)">
          <sc-for list="{{processSteps}}" as="step" hint-placeholder-count="4">
            <div style="position:relative;overflow:hidden;background:oklch(96% .002 270);padding:64px 28px 36px;opacity:{{step.revealOpacity}};transform:{{step.revealTransform}};transition:opacity .6s ease {{step.revealDelay}},transform .6s cubic-bezier(.2,.8,.2,1) {{step.revealDelay}},background .35s ease,box-shadow .4s ease" style-hover="background:oklch(100% 0 0);box-shadow:0 22px 44px -18px oklch(58% .22 25 / .5);transform:translateY(-8px)">
              <div style="position:absolute;top:22px;right:22px;font-family:Oswald,sans-serif;font-size:118px;font-weight:700;line-height:.82;color:oklch(88% .006 270);pointer-events:none;transition:color .4s ease,transform .5s cubic-bezier(.2,.8,.2,1)" style-hover="color:oklch(58% .22 25 / .24);transform:translateY(4px) scale(1.04)">{{step.num}}</div>
              <div style="position:absolute;top:0;left:0;right:0;height:3px;background:oklch(58% .22 25);transform:scaleX(0);transform-origin:left;transition:transform .45s cubic-bezier(.2,.8,.2,1)" style-hover="transform:scaleX(1)"></div>
              <div style="position:relative">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
                  <span style="width:26px;height:2px;background:oklch(58% .22 25)"></span>
                  <span style="font-size:10px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:oklch(58% .22 25)">Paso {{step.num}}</span>
                </div>
                <h3 style="font-family:Oswald,sans-serif;font-size:20px;font-weight:600;margin:0 0 10px;line-height:1.2">{{step.title}}</h3>
                <p style="color:oklch(42% .01 270);font-size:14px;line-height:1.65;margin:0">{{step.desc}}</p>
              </div>
            </div>
          </sc-for>
        </div>
      </div>
    </section>

    <section id="marcas" ref="{{marcasRef}}" style="padding:{{secPadSm}};max-width:1280px;margin:0 auto">
      <div style="text-align:center;max-width:560px;margin:0 auto 48px;opacity:{{marcasHeaderOpacity}};transform:{{marcasHeaderTransform}};transition:opacity .6s ease,transform .6s ease">
        <div style="font-size:12px;letter-spacing:0.3em;color:oklch(58% .22 25);font-weight:700;text-transform:uppercase;margin-bottom:14px">Marcas Autorizadas</div>
        <h2 style="font-family:Oswald,sans-serif;font-size:clamp(28px,4vw,44px);font-weight:700;margin:0 0 16px">Distribuidor Oficial</h2>
        <p style="color:oklch(42% .01 270);font-size:16px;line-height:1.6;margin:0">Representamos y distribuimos en Colombia a los mayores referentes mundiales en equipamiento e innovación fitness.</p>
      </div>
      <div style="position:relative;overflow:hidden;background:oklch(100% 0 0);padding:26px 0;opacity:{{marcasHeaderOpacity}};transition:opacity .8s ease .2s">
        <div style="position:absolute;left:0;top:0;bottom:0;width:120px;z-index:2;pointer-events:none;background:linear-gradient(90deg,oklch(100% 0 0),oklch(100% 0 0 / 0))"></div>
        <div style="position:absolute;right:0;top:0;bottom:0;width:120px;z-index:2;pointer-events:none;background:linear-gradient(270deg,oklch(100% 0 0),oklch(100% 0 0 / 0))"></div>
        <div style="display:flex;width:max-content;gap:56px;align-items:center;animation:ticker 30s linear infinite">
          <sc-for list="{{brandsTicker}}" as="brand" hint-placeholder-count="14">
            <div style="flex-shrink:0;width:170px;height:78px;display:flex;align-items:center;justify-content:center;transition:transform .3s ease" style-hover="transform:translateY(-4px)">
              <img loading="lazy" src="{{brand.img}}" alt="{{brand.name}}" style="width:{{brand.w}};height:{{brand.h}};object-fit:contain"/>
            </div>
          </sc-for>
        </div>
      </div>
    </section>

    <section id="clientes" ref="{{clientesRef}}" style="padding:{{secPadSm}};background:oklch(93% .004 270)">
      <div style="max-width:1280px;margin:0 auto">
        <div style="text-align:center;max-width:900px;margin:0 auto 48px;opacity:{{clientesHeaderOpacity}};transform:{{clientesHeaderTransform}};transition:opacity .6s ease,transform .6s ease">
          <div style="font-size:12px;letter-spacing:0.3em;color:oklch(58% .22 25);font-weight:700;text-transform:uppercase;margin-bottom:14px">Clientes &amp; Casos de Éxito</div>
          <h2 style="font-family:Oswald,sans-serif;font-size:clamp(22px,2.4vw,30px);font-weight:700;margin:0;white-space:nowrap">Clientes que confían en Fitness Life</h2>
        </div>
        <blockquote style="max-width:720px;margin:0 auto 56px;text-align:center;font-family:Oswald,sans-serif;font-size:22px;font-weight:500;line-height:1.5;color:oklch(20% .005 270);opacity:{{clientesHeaderOpacity}};transform:{{clientesHeaderTransform}};transition:opacity .7s ease .1s,transform .7s ease .1s">
          "Equipar nuestras salas de entrenamiento con Fitness Life ha sido la mejor decisión comercial. Los equipos remanufacturados tienen el rendimiento y apariencia idénticos a los nuevos."
          <footer style="margin-top:18px;font-family:Inter,sans-serif;font-size:13px;font-weight:600;color:oklch(58% .22 25);letter-spacing:0.05em;text-transform:uppercase">— Mundo Fitness</footer>
        </blockquote>
        <div style="font-size:11px;letter-spacing:0.15em;color:oklch(42% .01 270);font-weight:600;text-transform:uppercase;text-align:center;margin-bottom:24px">Alianzas e Instituciones Destacadas</div>
        <div style="display:flex;justify-content:center;gap:14px;flex-wrap:{{clientsWrap}};opacity:{{clientesHeaderOpacity}};transition:opacity .8s ease .2s">
          <sc-for list="{{clients}}" as="client" hint-placeholder-count="6">
            <div style="flex:0 1 {{clientBasis}};min-width:0;background:oklch(100% 0 0);border-radius:12px;height:{{clientH}};display:flex;align-items:center;justify-content:center;padding:12px;box-sizing:border-box;box-shadow:0 6px 20px -10px oklch(20% .01 270 / .25);transition:transform .3s ease,box-shadow .3s ease" style-hover="transform:translateY(-5px);box-shadow:0 16px 32px -14px oklch(20% .01 270 / .4)">
              <img loading="lazy" sc-camel-src="{{client.img}}" alt="{{client.name}}" style="max-width:100%;max-height:100%;object-fit:contain"/>
            </div>
          </sc-for>
        </div>
      </div>
    </section>

    <section ref="{{whyUsRef}}" style="padding:{{secPadSm}};background:oklch(14% .005 270)"><div style="max-width:1280px;margin:0 auto">
      <div style="text-align:center;max-width:600px;margin:0 auto 56px;opacity:{{whyUsHeaderOpacity}};transform:{{whyUsHeaderTransform}};transition:opacity .6s ease,transform .6s ease">
        <div style="font-size:12px;letter-spacing:0.3em;color:oklch(58% .22 25);font-weight:700;text-transform:uppercase;margin-bottom:14px">Por qué elegirnos</div>
        <h2 style="font-family:Oswald,sans-serif;font-size:clamp(28px,4vw,44px);font-weight:700;margin:0 0 16px;color:oklch(98% 0 0)">Nuestras Ventajas</h2>
        <p style="color:oklch(72% .008 270);font-size:16px;line-height:1.6;margin:0">Garantizamos la máxima rentabilidad de tu inversión deportiva con la ingeniería más avanzada del mercado.</p>
      </div>
      <div style="display:grid;grid-template-columns:{{grid4}};gap:1px;background:oklch(32% .006 270);border-top:1px solid oklch(32% .006 270)">
        <sc-for list="{{whyUs}}" as="w" hint-placeholder-count="4">
          <div style="position:relative;overflow:hidden;background:oklch(97% .002 270);padding:42px 28px 36px;opacity:{{w.revealOpacity}};transform:{{w.revealTransform}};transition:opacity .6s ease {{w.revealDelay}},transform .6s cubic-bezier(.2,.8,.2,1) {{w.revealDelay}},background .35s ease,box-shadow .4s ease" style-hover="background:oklch(100% 0 0);box-shadow:0 22px 44px -18px oklch(58% .22 25 / .55);transform:translateY(-8px)">
            <div style="position:absolute;top:-6px;right:10px;font-family:Oswald,sans-serif;font-size:96px;font-weight:700;line-height:1;color:oklch(88% .006 270);pointer-events:none;transition:color .4s ease,transform .5s cubic-bezier(.2,.8,.2,1)" style-hover="color:oklch(58% .22 25 / .22);transform:translateY(6px)">{{w.icon}}</div>
            <div style="position:absolute;top:0;left:0;right:0;height:3px;background:oklch(58% .22 25);transform:scaleX(0);transform-origin:left;transition:transform .45s cubic-bezier(.2,.8,.2,1)" style-hover="transform:scaleX(1)"></div>
            <div style="position:relative">
              <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
                <span style="width:26px;height:2px;background:oklch(58% .22 25)"></span>
                <span style="font-size:10px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:oklch(58% .22 25)">Ventaja</span>
              </div>
              <h3 style="font-family:Oswald,sans-serif;font-size:20px;font-weight:600;margin:0 0 10px;line-height:1.2;color:oklch(16% .006 270)">{{w.title}}</h3>
              <p style="color:oklch(42% .01 270);font-size:14px;line-height:1.65;margin:0">{{w.desc}}</p>
            </div>
          </div>
        </sc-for>
      </div>

      </div>
    </section>

    <section id="cotizacion" style="background:oklch(11% .004 270);padding:0">
      <div style="max-width:1280px;margin:0 auto;display:grid;grid-template-columns:{{grid2}}">
        <div style="padding:{{cotPadLeft}};border-right:1px solid oklch(26% .006 270)">
          <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px">
            <span style="width:56px;height:2px;background:oklch(58% .22 25)"></span>
            <span style="font-size:12px;letter-spacing:.32em;color:oklch(65% .24 27);font-weight:700;text-transform:uppercase">Cotización</span>
          </div>
          <h2 style="font-family:Oswald,sans-serif;font-size:clamp(38px,4.6vw,62px);font-weight:700;line-height:.98;margin:0 0 26px;color:oklch(98% 0 0);text-transform:uppercase">¿Deseas un<br/><span style="color:oklch(58% .22 25)">precio<br/>especial</span>?</h2>
          <p style="color:oklch(70% .008 270);font-size:16px;line-height:1.6;margin:0 0 40px;max-width:400px">Llena el formulario y consigue precios especiales en tus equipos de entrenamiento físico.</p>
          <div style="display:flex;flex-direction:column;gap:22px">
            <div style="display:flex;align-items:center;gap:16px">
              <span style="width:46px;height:46px;flex-shrink:0;border-radius:10px;background:oklch(58% .22 25);display:flex;align-items:center;justify-content:center;color:white"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M22 16.9v3a2 2 0 01-2.2 2 19.8 19.8 0 01-8.6-3.1 19.5 19.5 0 01-6-6A19.8 19.8 0 012.1 4.2 2 2 0 014.1 2h3a2 2 0 012 1.7c.1.9.3 1.8.7 2.6a2 2 0 01-.5 2.1L8.1 9.9a16 16 0 006 6l1.5-1.2a2 2 0 012.1-.5c.8.4 1.7.6 2.6.7a2 2 0 011.7 2z"></path></svg></span>
              <div><div style="font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:oklch(62% .008 270);font-weight:600;margin-bottom:3px">Teléfono</div><div style="font-size:17px;color:oklch(97% 0 0);font-weight:600">312-8011838 · 312-7199008</div></div>
            </div>
            <div style="display:flex;align-items:center;gap:16px">
              <span style="width:46px;height:46px;flex-shrink:0;border-radius:10px;background:oklch(58% .22 25);display:flex;align-items:center;justify-content:center;color:white"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="M2 6l10 7 10-7"></path></svg></span>
              <div><div style="font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:oklch(62% .008 270);font-weight:600;margin-bottom:3px">Email</div><div style="font-size:17px;color:oklch(97% 0 0);font-weight:600">fitnesslifesas@gmail.com</div></div>
            </div>
            <div style="display:flex;align-items:center;gap:16px">
              <span style="width:46px;height:46px;flex-shrink:0;border-radius:10px;background:oklch(58% .22 25);display:flex;align-items:center;justify-content:center;color:white"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 1116 0z"></path><circle cx="12" cy="10" r="3"></circle></svg></span>
              <div><div style="font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:oklch(62% .008 270);font-weight:600;margin-bottom:3px">Ubicación</div><div style="font-size:17px;color:oklch(97% 0 0);font-weight:600">Cali, Colombia · Cobertura nacional</div></div>
            </div>
          </div>
        </div>
        <div style="padding:{{cotPadRight}}">
          <h3 style="font-family:Oswald,sans-serif;font-size:30px;font-weight:700;margin:0 0 34px;color:oklch(98% 0 0);text-transform:uppercase">Solicita tu cotización</h3>
          <sc-if value="{{quoteSent}}" hint-placeholder-val="{{false}}">
            <div style="border:1px solid oklch(58% .22 25);background:oklch(58% .22 25 / .12);padding:34px 28px;display:flex;gap:18px;align-items:flex-start">
              <span style="width:44px;height:44px;flex-shrink:0;border-radius:999px;background:oklch(58% .22 25);color:white;display:flex;align-items:center;justify-content:center"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"></path></svg></span>
              <div>
                <div style="font-family:Oswald,sans-serif;font-size:22px;font-weight:600;color:oklch(98% 0 0);margin-bottom:8px;text-transform:uppercase">Solicitud enviada</div>
                <p style="color:oklch(78% .008 270);font-size:15px;line-height:1.6;margin:0 0 18px">Gracias {{quoteName}}. Un asesor de Fitness Life te contactará dentro de las próximas 24 horas hábiles.</p>
                <button onClick="{{resetQuote}}" style="background:none;border:1px solid oklch(45% .01 270);color:oklch(90% 0 0);padding:12px 20px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;cursor:pointer">Enviar otra solicitud</button>
              </div>
            </div>
          </sc-if>
          <sc-if value="{{quoteOpen}}" hint-placeholder-val="{{true}}">
            <div>
              <div style="display:grid;grid-template-columns:{{grid2}};gap:16px;margin-bottom:16px">
                <div>
                  <input placeholder="Nombre *" value="{{qName}}" onChange="{{onQName}}" style="background:oklch(16% .005 270);color:oklch(96% 0 0);padding:17px 18px;font-size:15px;width:100%;box-sizing:border-box;outline:none;border:1px solid {{qNameBorder}}"/>
                  <div style="font-size:11px;color:oklch(70% .2 27);margin-top:6px;min-height:14px">{{qNameError}}</div>
                </div>
                <div>
                  <input placeholder="Empresa" value="{{qCompany}}" onChange="{{onQCompany}}" style="background:oklch(16% .005 270);color:oklch(96% 0 0);padding:17px 18px;font-size:15px;width:100%;box-sizing:border-box;outline:none;border:1px solid oklch(28% .006 270)"/>
                  <div style="min-height:20px"></div>
                </div>
                <div>
                  <input placeholder="Email *" value="{{qEmail}}" onChange="{{onQEmail}}" style="background:oklch(16% .005 270);color:oklch(96% 0 0);padding:17px 18px;font-size:15px;width:100%;box-sizing:border-box;outline:none;border:1px solid {{qEmailBorder}}"/>
                  <div style="font-size:11px;color:oklch(70% .2 27);margin-top:6px;min-height:14px">{{qEmailError}}</div>
                </div>
                <div>
                  <input placeholder="Teléfono *" value="{{qPhone}}" onChange="{{onQPhone}}" style="background:oklch(16% .005 270);color:oklch(96% 0 0);padding:17px 18px;font-size:15px;width:100%;box-sizing:border-box;outline:none;border:1px solid {{qPhoneBorder}}"/>
                  <div style="font-size:11px;color:oklch(70% .2 27);margin-top:6px;min-height:14px">{{qPhoneError}}</div>
                </div>
              </div>
              <select value="{{qType}}" onChange="{{onQType}}" style="background:oklch(16% .005 270);border:1px solid oklch(28% .006 270);color:oklch(88% .006 270);padding:17px 18px;font-size:15px;width:100%;box-sizing:border-box;margin-bottom:16px;outline:none;font-family:Inter,sans-serif">
                <option value="">Tipo de equipo</option>
                <option value="Elípticas">Elípticas</option>
                <option value="Bicicletas estáticas">Bicicletas estáticas</option>
                <option value="Trotadoras">Trotadoras</option>
                <option value="Máquinas de pesas">Máquinas de pesas</option>
                <option value="Circuitos remanufacturados">Circuitos remanufacturados</option>
                <option value="Accesorios">Accesorios</option>
              </select>
              <textarea placeholder="Mensaje" rows="5" value="{{qMessage}}" onChange="{{onQMessage}}" style="background:oklch(16% .005 270);border:1px solid oklch(28% .006 270);color:oklch(96% 0 0);padding:17px 18px;font-size:15px;width:100%;box-sizing:border-box;margin-bottom:24px;outline:none;resize:vertical;font-family:Inter,sans-serif"></textarea>
              <button onClick="{{submitQuote}}" style="width:100%;background:oklch(58% .22 25);color:white;border:none;padding:20px;font-weight:700;font-size:14px;letter-spacing:.1em;text-transform:uppercase;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:12px;transition:background .3s ease,transform .3s ease" style-hover="background:oklch(65% .24 27);transform:translateY(-2px)">Enviar solicitud <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"></path></svg></button>
              <p style="font-size:11px;color:oklch(58% .008 270);margin:14px 0 0;line-height:1.6">Los campos marcados con * son obligatorios. También puedes escribirnos por WhatsApp al 312-8011838.</p>
            </div>
          </sc-if>
        </div>
      </div>
    </section>

  </main>
  </sc-if>

  <sc-if value="{{isCatalogo}}" hint-placeholder-val="{{false}}">
    <main style="padding-bottom:100px;background:oklch(97% .004 270);min-height:100vh">
      <section style="background:oklch(14% .005 270);padding:80px 24px 60px;text-align:center">
        <div style="max-width:800px;margin:0 auto">
          <div style="font-size:12px;letter-spacing:0.3em;color:oklch(58% .22 25);font-weight:700;text-transform:uppercase;margin-bottom:14px">Catálogo Completo</div>
          <h1 style="font-family:Oswald,sans-serif;font-size:clamp(32px,5vw,56px);font-weight:700;margin:0 0 20px;color:white">Todos nuestros equipos</h1>
          <p style="color:oklch(70% .008 270);font-size:16px;line-height:1.6;margin:0">Explora nuestra colección completa de maquinaria de fuerza y cardio.</p>
        </div>
      </section>
      
      
      <section style="max-width:1280px;width:100%;box-sizing:border-box;margin:0 auto;padding:40px 24px;display:flex;gap:40px;flex-direction:{{catLayoutDir}};align-items:flex-start">
        
        <!-- BARRA LATERAL (Filtros) -->
        <aside style="width:{{catAsideWidth}};box-sizing:border-box;flex-shrink:0;background:white;padding:24px;border-radius:12px;border:1px solid oklch(90% 0 0);position:{{catAsidePos}};top:{{catAsideTop}};">
          
          <div style="margin-bottom:24px">
            <h3 style="font-family:Oswald,sans-serif;font-size:18px;margin:0 0 12px;color:oklch(20% .005 270)">Buscar Equipo</h3>
            <input type="text" id="catalogoSearchInput" placeholder="Ej. Cybex, Abdominal..." onInput="{{onCatalogoSearch}}" value="{{catalogoSearch}}" style="width:100%;box-sizing:border-box;padding:12px;border:1px solid oklch(85% 0 0);border-radius:6px;font-size:14px;outline:none" />
          </div>

          
          <div style="margin-bottom:24px">
            <h3 style="font-family:Oswald,sans-serif;font-size:18px;margin:0 0 12px;color:oklch(20% .005 270)">Categorías</h3>
            <div style="display:flex;flex-direction:{{catListDir}};flex-wrap:{{catListWrap}};gap:4px;padding-right:8px;">
              <div style="display:flex;flex-direction:{{catListDir}};gap:4px;flex-grow:1;flex-wrap:wrap;">
              <sc-for list="{{catalogoFilters}}" as="f">
                <button onClick="{{f.onClick}}" style="text-align:left;background:{{f.bg}};color:{{f.color}};border:1px solid {{f.border}};padding:8px 12px;border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;transition:all .2s ease">{{f.label}}</button>
              </sc-for>
              </div>
            </div>
          </div>

          <div style="margin-bottom:16px">
            <h3 style="font-family:Oswald,sans-serif;font-size:18px;margin:0 0 8px;color:oklch(20% .005 270)">Marcas</h3>
            <input type="text" onInput="{{onBrandSearch}}" value="{{brandSearch}}" placeholder="Buscar marca..." style="width:100%;box-sizing:border-box;padding:8px 12px;border:1px solid oklch(85% 0 0);border-radius:6px;font-size:13px;outline:none;margin-bottom:12px;background:#fdfdfd" />
            <div style="display:flex;flex-direction:column;gap:8px;max-height:450px;overflow-y:auto;padding-right:8px;scrollbar-width:thin;scrollbar-color:oklch(85% 0 0) transparent">
              <style>
                .brand-scroll::-webkit-scrollbar { width: 4px; }
                .brand-scroll::-webkit-scrollbar-track { background: transparent; }
                .brand-scroll::-webkit-scrollbar-thumb { background: oklch(85% 0 0); border-radius: 4px; }
              </style>
              <div class="brand-scroll" style="display:flex;flex-direction:column;gap:8px;flex-grow:1">
              <sc-for list="{{catalogoBrandsList}}" as="b">
                <label style="display:flex;align-items:center;gap:10px;font-size:13px;color:oklch(30% .008 270);cursor:pointer">
                  <input type="checkbox" checked="{{b.checked}}" onChange="{{b.onChange}}" style="accent-color:oklch(58% .22 25);width:16px;height:16px;cursor:pointer" />
                  {{b.name}}
                </label>
              </sc-for>
              </div>
            </div>
          </div>

        </aside>

        <!-- CUADRÍCULA DE PRODUCTOS -->
        <div style="flex-grow:1;min-width:0;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
            <h2 style="font-family:Oswald,sans-serif;font-size:24px;margin:0;color:oklch(20% .005 270)">Equipos</h2>
          </div>

          <!-- Barra de Control: Filtros y Ordenamiento -->
          <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:24px;background:#ffffff;padding:16px 20px;border-radius:12px;border:1px solid oklch(88% .006 270);box-shadow:0 2px 8px rgba(0,0,0,0.03)">
            
            <!-- Fila superior: Selectores principales (Marca y Ordenar) bien alineados -->
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;padding-bottom:12px;border-bottom:1px solid oklch(93% 0 0)">
              <div style="display:inline-flex;align-items:center;gap:10px">
                <span style="font-size:12px;font-weight:700;color:oklch(35% .008 270);font-family:Oswald,sans-serif;text-transform:uppercase;letter-spacing:0.06em">Filtrar Marca:</span>
                <select onChange="{{onTopBrandSelect}}" value="{{topBrandValue}}" style="padding:7px 14px;border-radius:6px;border:1px solid oklch(85% 0 0);font-size:13px;font-weight:500;color:oklch(20% .005 270);outline:none;cursor:pointer;background:#fafafa;min-width:180px">
                  <option value="">Todas las Marcas</option>
                  <sc-for list="{{topBrandsList}}" as="bm">
                    <option value="{{bm.name}}">{{bm.name}}</option>
                  </sc-for>
                </select>
              </div>

              <div style="display:inline-flex;align-items:center;gap:10px;margin-left:auto">
                <span style="font-size:12px;color:oklch(40% 0 0);font-weight:700;font-family:Oswald,sans-serif;text-transform:uppercase;letter-spacing:0.06em">Ordenar:</span>
                <select onChange="{{onCatalogoSort}}" value="{{catalogoSort}}" style="padding:7px 14px;border-radius:6px;border:1px solid oklch(85% 0 0);font-size:13px;color:oklch(20% .005 270);outline:none;cursor:pointer;background:#fafafa;min-width:190px">
                  <option value="serie_order">Por Serie (Recomendado)</option>
                  <option value="name_asc">Nombre (A-Z)</option>
                  <option value="name_desc">Nombre (Z-A)</option>
                  <option value="series_asc">Marca (A-Z)</option>
                </select>
              </div>
            </div>

            <!-- Fila inferior: Series en pastillas ordenadas y estilizadas -->
            <sc-if value="{{hasSeriesFilter}}">
              <div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px;padding-top:2px">
                <span style="font-size:12px;font-weight:700;color:oklch(35% .008 270);margin-right:4px;font-family:Oswald,sans-serif;text-transform:uppercase;letter-spacing:0.06em">Series:</span>
                <sc-for list="{{catalogoSeriesList}}" as="s">
                  <button onClick="{{s.onClick}}" style="background:{{s.bg}};color:{{s.color}};border:{{s.border}};padding:5px 13px;border-radius:20px;font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:all .2s ease;box-shadow:0 1px 2px rgba(0,0,0,0.04);white-space:nowrap">
                    <sc-if value="{{s.checked}}"><span style="font-size:11px">✓</span></sc-if>
                    <span>{{s.name}}</span>
                  </button>
                </sc-for>
                <sc-if value="{{hasSelectedSeries}}">
                  <button onClick="{{clearCatalogoSeries}}" style="background:white;color:oklch(58% .22 25);border:1px solid oklch(85% 0 0);padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600;cursor:pointer;transition:all .2s ease;margin-left:4px" title="Limpiar selección de series">✕ Todas</button>
                </sc-if>
              </div>
            </sc-if>

          </div>
          
          
          
            
            
            <div class="grid-catalogo">
            <sc-for list="{{catalogoProducts}}" as="prod">
              <div style="position:relative;border:1px solid oklch(88% .006 270);border-radius:12px;overflow:hidden;background:white;transition:transform .3s ease,box-shadow .3s ease;display:flex;flex-direction:column;height:100%" style-hover="transform:translateY(-5px);box-shadow:0 16px 32px -12px oklch(20% .01 270 / .15)">
                
                <div onClick="{{prod.onOpen}}" style="height:160px;background:oklch(98% .002 270);padding:16px;display:flex;align-items:center;justify-content:center;position:relative;cursor:pointer" title="Ver detalles">
                  <img loading="lazy" sc-camel-src="{{prod.image}}" alt="{{prod.name}}" style="max-width:100%;max-height:100%;object-fit:contain"/>
                </div>

                <div style="padding:16px;flex-grow:1;display:flex;flex-direction:column">
                  <div style="display:flex;align-items:center;justify-content:space-between;gap:6px;margin-bottom:8px">
                    <div style="font-size:12px;color:oklch(58% .22 25);font-weight:700;text-transform:uppercase">{{prod.series}}</div>
                    <sc-if value="{{prod.hasSerie}}"><span style="font-size:11px;background:oklch(96% 0 0);color:oklch(20% .005 270);border:1px solid oklch(88% 0 0);padding:2px 6px;border-radius:4px;font-weight:600">Serie: {{prod.serie}}</span></sc-if>
                  </div>
                  <h4 style="font-family:Oswald,sans-serif;font-size:16px;margin:0 0 8px;color:oklch(20% .005 270);line-height:1.2;flex-grow:1">{{prod.name}}</h4>
                  
                  <!-- ESPECIFICACIONES (ocultas por defecto, pero mostradas si hay) -->
                  <div style="margin-bottom:20px;font-size:12px;color:oklch(40% .005 270);line-height:1.5">
                    <sc-if value="{{prod.hasDims}}"><div><b>Dim:</b> {{prod.set_up_dimension}}</div></sc-if>
                    <sc-if value="{{prod.hasWeight}}"><div><b>Peso:</b> {{prod.weight_stack}}</div></sc-if>
                  </div>

                  <div style="display:flex;gap:8px">
                    <button onClick="{{prod.onToggleCart}}" style="flex-grow:1;background:{{prod.cartBg}};color:{{prod.cartColor}};border:{{prod.cartBorder}};padding:10px 8px;border-radius:6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.02em;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;transition:background .2s" style-hover="background:{{prod.cartBgHover}};color:white">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                      {{prod.cartText}}
                    </button>
                    <a href="{{prod.waLink}}" target="_blank" style="flex-shrink:0;background:#25D366;color:white;border:none;padding:12px;border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .2s" style-hover="background:#1ebd5c" title="Cotizar rápido por WhatsApp">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                    </a>
                  </div>
                </div>

              </div>
            </sc-for>
            </div>
          
          

          <sc-if value="{{noCatalogoProducts}}" hint-placeholder-val="{{false}}">
               <div style="grid-column:1/-1;text-align:center;padding:60px 20px;background:white;border-radius:12px;border:1px solid oklch(90% 0 0)">
                  <p style="color:oklch(42% .01 270);font-size:16px;margin:0">No se encontraron productos que coincidan con tu búsqueda.</p>
               </div>
            </sc-if>

          <sc-if value="{{hasPagination}}">
            <div style="display:flex;justify-content:center;align-items:center;gap:8px;margin-top:40px;margin-bottom:20px;width:100%">
              <sc-if value="{{prevPageDisabled}}" hint-placeholder-val="{{false}}">
                 <button disabled style="width:40px;height:40px;border-radius:8px;border:1px solid oklch(90% 0 0);background:oklch(98% 0 0);color:oklch(80% 0 0);display:flex;align-items:center;justify-content:center;cursor:not-allowed">
                   <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                 </button>
              </sc-if>
              <sc-if value="{{prevPageEnabled}}" hint-placeholder-val="{{true}}">
                 <button onClick="{{onPrevPage}}" style="width:40px;height:40px;border-radius:8px;border:1px solid oklch(80% 0 0);background:white;color:oklch(20% .005 270);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s" style-hover="background:oklch(96% 0 0)">
                   <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                 </button>
              </sc-if>
              
              <sc-for list="{{catalogoPages}}" as="pg">
                <button onClick="{{pg.onClick}}" style="min-width:40px;height:40px;border-radius:8px;border:{{pg.border}};background:{{pg.bg}};color:{{pg.color}};font-weight:700;font-size:14px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;padding:0 8px">
                  {{pg.num}}
                </button>
              </sc-for>

              <sc-if value="{{nextPageDisabled}}" hint-placeholder-val="{{false}}">
                 <button disabled style="width:40px;height:40px;border-radius:8px;border:1px solid oklch(90% 0 0);background:oklch(98% 0 0);color:oklch(80% 0 0);display:flex;align-items:center;justify-content:center;cursor:not-allowed">
                   <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                 </button>
              </sc-if>
              <sc-if value="{{nextPageEnabled}}" hint-placeholder-val="{{true}}">
                 <button onClick="{{onNextPage}}" style="width:40px;height:40px;border-radius:8px;border:1px solid oklch(80% 0 0);background:white;color:oklch(20% .005 270);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s" style-hover="background:oklch(96% 0 0)">
                   <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                 </button>
              </sc-if>
            </div>
          </sc-if>
          </div>
      </section>


      <sc-if value="{{modalOpen}}">
        <div style="position:fixed;inset:0;background:rgba(0,0,0,0.6);backdrop-filter:blur(5px);z-index:999;display:flex;align-items:center;justify-content:center;padding:20px;box-sizing:border-box">
          <div style="background:white;width:100%;max-width:1100px;border-radius:16px;display:flex;flex-direction:{{catLayoutDir}};overflow-y:auto;box-shadow:0 30px 60px -15px oklch(20% .01 270 / .4);position:relative;max-height:90vh;animation:slideFade .3s ease-out">
            
            <button onClick="{{closeModal}}" aria-label="Cerrar" style="position:absolute;top:20px;right:20px;z-index:20;width:36px;height:36px;border-radius:999px;background:oklch(96% 0 0);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .2s" style-hover="background:oklch(90% 0 0)">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"></path></svg>
            </button>

            <!-- Foto -->
            <div style="flex:1.2;background:oklch(99% .002 270);padding:{{modalImgPadding}};display:flex;align-items:center;justify-content:center;border-right:{{modalBorderR}};border-bottom:{{modalBorderB}};min-height:{{modalImgMinH}}">
               <img sc-camel-src="{{modalProd.image}}" style="max-width:100%;max-height:400px;object-fit:contain" />
            </div>

            <!-- Centro: Detalles -->
            <div style="flex:1.5;padding:{{modalPadding}};display:flex;flex-direction:column">
               <h2 style="font-family:Oswald,sans-serif;font-size:32px;margin:0 0 16px;color:oklch(20% .005 270);line-height:1.2;text-transform:uppercase">{{modalProd.name}}</h2>
               
               <div style="display:flex;align-items:center;gap:4px;margin-bottom:24px;color:#FFB800">
                 <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path></svg>
                 <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path></svg>
                 <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path></svg>
                 <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path></svg>
                 <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path></svg>
                 <span style="color:oklch(60% 0 0);font-size:13px;margin-left:8px">(6 Reviews)</span>
               </div>

               <p style="font-size:15px;color:oklch(40% .005 270);line-height:1.6;margin-bottom:32px;flex-grow:1">
                 Equipamiento profesional ideal para trabajo pesado en gimnasios de alto tráfico. Diseñado biomecánicamente para resultados superiores y construido para resistir el paso del tiempo.
               </p>

               <div style="display:flex;gap:16px;margin-bottom:32px">
                 <button onClick="{{modalProd.onToggleCart}}" style="flex:1;background:{{modalProd.cartBg}};color:{{modalProd.cartColor}};border:{{modalProd.cartBorder}};padding:16px;border-radius:8px;font-size:15px;font-weight:700;letter-spacing:0.05em;cursor:pointer;text-transform:uppercase;transition:all .3s ease">
                   {{modalProd.cartText}}
                 </button>
               </div>
               
               <div style="font-size:13px;color:oklch(50% 0 0);line-height:1.8;border-top:1px solid oklch(92% 0 0);padding-top:16px">
                 <div><b>Marca:</b> {{modalProd.series}}</div>
                 <sc-if value="{{modalProd.hasSerie}}"><div><b>Serie:</b> {{modalProd.serie}}</div></sc-if>
                 <div><b>SKU:</b> {{modalProd.item_no}}</div>
               </div>
            </div>

            <!-- Derecha: Beneficios -->
            <div style="flex:1;padding:{{modalPadding}};background:oklch(98% .002 270);border-left:{{modalBorderL}};border-top:{{modalBorderB}};display:flex;flex-direction:column;gap:24px;justify-content:center">
               
               

               <div style="display:flex;align-items:center;gap:16px">
                 <div style="width:48px;height:48px;border-radius:50%;background:white;border:1px solid oklch(90% 0 0);display:flex;align-items:center;justify-content:center;color:oklch(20% .01 270)">
                   <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                 </div>
                 <div style="font-size:12px;font-weight:700;letter-spacing:0.05em;color:oklch(30% .01 270);text-transform:uppercase">Envío a todo<br>El País</div>
               </div>

               <div style="display:flex;align-items:center;gap:16px">
                 <div style="width:48px;height:48px;border-radius:50%;background:white;border:1px solid oklch(90% 0 0);display:flex;align-items:center;justify-content:center;color:oklch(20% .01 270)">
                   <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                 </div>
                 <div style="font-size:12px;font-weight:700;letter-spacing:0.05em;color:oklch(30% .01 270);text-transform:uppercase">Los Mejores<br>Precios</div>
               </div>

               <div style="display:flex;align-items:center;gap:16px">
                 <div style="width:48px;height:48px;border-radius:50%;background:white;border:1px solid oklch(90% 0 0);display:flex;align-items:center;justify-content:center;color:oklch(20% .01 270)">
                   <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                 </div>
                 <div style="font-size:12px;font-weight:700;letter-spacing:0.05em;color:oklch(30% .01 270);text-transform:uppercase">Respaldo y<br>Garantía</div>
               </div>

            </div>
          </div>
        </div>
      </sc-if>

    </main>
  </sc-if>

  <sc-if value="{{isContacto}}" hint-placeholder-val="{{false}}">
    <main style="max-width:1280px;margin:0 auto;padding:80px 24px 120px">
      
      <div style="text-align:center;max-width:600px;margin:0 auto 56px">
        <button onClick="{{goBackHistory}}" style="display:inline-flex;align-items:center;gap:6px;background:none;border:none;color:oklch(42% .01 270);font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;cursor:pointer;margin-bottom:24px;transition:color .2s" style-hover="color:oklch(58% .22 25)">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
          Volver atrás
        </button>
        <div style="font-size:12px;letter-spacing:0.3em;color:oklch(58% .22 25);font-weight:700;text-transform:uppercase;margin-bottom:14px">Cotización</div>
        <h1 style="font-family:Oswald,sans-serif;font-size:clamp(32px,5vw,52px);font-weight:700;margin:0 0 16px">¿Deseas un precio especial?</h1>
        
        <p style="color:oklch(42% .01 270);font-size:16px;line-height:1.6;margin:0">Llena el formulario y consigue precios especiales en tus equipos de entrenamiento físico.</p>
      </div>

      <sc-if value="{{hasCartItems}}" hint-placeholder-val="{{false}}">
        <div style="max-width:600px;margin:0 auto 40px;background:white;border:1px solid oklch(88% .006 270);border-radius:12px;overflow:hidden">
          <div style="background:oklch(97% 0 0);padding:16px 24px;border-bottom:1px solid oklch(88% .006 270);font-weight:700;color:oklch(20% .005 270);display:flex;justify-content:space-between;align-items:center">
            <span>Equipos a cotizar ({{cartCount}})</span>
          </div>
          <div style="padding:12px 24px">
            <sc-for list="{{cartItemsList}}" as="cp">
              <div style="display:flex;align-items:center;gap:16px;padding:12px 0;border-bottom:1px solid oklch(93% 0 0)">
                <img sc-camel-src="{{cp.image}}" style="width:50px;height:50px;object-fit:contain;border-radius:6px;background:oklch(98% 0 0);border:1px solid oklch(90% 0 0)"/>
                <div style="flex-grow:1">
                  <div style="font-size:14px;font-weight:600;color:oklch(20% .005 270);line-height:1.2">{{cp.name}}</div>
                  <div style="font-size:11px;color:oklch(58% .22 25);text-transform:uppercase;font-weight:700;margin-top:4px">SKU: {{cp.item_no}}</div>
                </div>
                <button onClick="{{cp.onRemove}}" style="background:none;border:none;color:oklch(60% 0 0);cursor:pointer;padding:8px" title="Quitar">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
              </div>
            </sc-for>
          </div>
        </div>
      </sc-if>

      <div style="display:grid;grid-template-columns:{{contactGrid}};gap:48px">
        <div style="display:flex;flex-direction:column;gap:28px;order:{{contactInfoOrder}}">
          <div>
            <div style="font-size:11px;letter-spacing:0.15em;color:oklch(58% .22 25);font-weight:700;text-transform:uppercase;margin-bottom:8px">Teléfono</div>
            <div style="font-size:18px;font-weight:600">+57 (1) 555-0100</div>
          </div>
          <div>
            <div style="font-size:11px;letter-spacing:0.15em;color:oklch(58% .22 25);font-weight:700;text-transform:uppercase;margin-bottom:8px">Email</div>
            <div style="font-size:18px;font-weight:600">ventas@fitnesslifesas.com</div>
          </div>
          <div>
            <div style="font-size:11px;letter-spacing:0.15em;color:oklch(58% .22 25);font-weight:700;text-transform:uppercase;margin-bottom:8px">Ubicación</div>
            <div style="font-size:18px;font-weight:600;margin-bottom:6px">Colombia · Cobertura nacional</div>
            <div style="font-size:14px;color:oklch(40% .01 270);display:flex;align-items:center;gap:16px"><a href="tel:3128011838" style="color:inherit;text-decoration:none">📞 312 8011838</a> <a href="tel:3127199008" style="color:inherit;text-decoration:none">📞 312 7199008</a></div>
          </div>
          <div style="height:240px;border:1px solid oklch(85% 0 0);border-radius:12px;overflow:hidden"><iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d127402.13110996841!2d-76.62002306766453!3d3.411681283626245!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x8e30a6f0cc4bb3f1%3A0x1f0fb5e952ae6168!2sCali%2C%20Valle%20del%20Cauca!5e0!3m2!1sen!2sco!4v1700000000000!5m2!1sen!2sco" width="100%" height="100%" style="border:0;filter:grayscale(1) contrast(1.2)" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>
        </div>
        <div style="background:oklch(98% 0 0);border:1px solid oklch(83% .006 270);padding:{{modalPadding}};order:{{contactFormOrder}}">
          <sc-if value="{{formSubmitted}}" hint-placeholder-val="{{false}}">
            <div style="text-align:center;padding:40px 16px">
              <div style="width:64px;height:64px;background:oklch(96% .05 145);color:#10b981;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:28px">✓</div>
              <h3 style="font-family:Oswald,sans-serif;font-size:24px;font-weight:600;margin:0 0 10px;color:oklch(20% .005 270);text-transform:uppercase">¡Solicitud recibida con éxito!</h3>
              <p style="color:oklch(40% .01 270);font-size:15px;line-height:1.5;margin:0 0 12px">Hemos enviado un resumen detallado de tu cotización a tu correo electrónico.</p>
              <p style="color:oklch(55% .01 270);font-size:13px;margin:0">Un asesor comercial de Fitness Life se pondrá en contacto contigo muy pronto.</p>
            </div>
          </sc-if>
          <sc-if value="{{formNotSubmitted}}" hint-placeholder-val="{{true}}">
            <form onSubmit="{{submitForm}}" style="display:flex;flex-direction:column;gap:18px">
              <h3 style="font-family:Oswald,sans-serif;font-size:22px;font-weight:600;margin:0 0 4px">Solicita tu cotización</h3>
              <input name="name" required="{{true}}" placeholder="Nombre completo" style="background:oklch(96% .003 270);border:1px solid oklch(83% .006 270);color:oklch(20% .005 270);padding:14px 16px;font-size:14px"/>
              <input name="email" required="{{true}}" type="email" placeholder="Correo electrónico" style="background:oklch(96% .003 270);border:1px solid oklch(83% .006 270);color:oklch(20% .005 270);padding:14px 16px;font-size:14px"/>
              <input name="phone" required="{{true}}" type="tel" placeholder="Teléfono / Celular" style="background:oklch(96% .003 270);border:1px solid oklch(83% .006 270);color:oklch(20% .005 270);padding:14px 16px;font-size:14px"/>
              <select name="type" style="background:oklch(96% .003 270);border:1px solid oklch(83% .006 270);color:oklch(20% .005 270);padding:14px 16px;font-size:14px">
                <option value="">Tipo de equipo / Proyecto</option>
                <option>Línea Comercial de Alto Tráfico</option>
                <option>Paquete / Dotación para Gimnasio</option>
                <option>Equipos para Conjunto / Institucional</option>
                <option>Equipos de Fuerza & Cardio para Hogar</option>
              </select>
              <textarea name="message" placeholder="Cuéntanos detalles de tu proyecto o inquietudes adicionales..." rows="4" style="background:oklch(96% .003 270);border:1px solid oklch(83% .006 270);color:oklch(20% .005 270);padding:14px 16px;font-size:14px;resize:vertical"></textarea>
              <button type="submit" disabled="{{formSubmitting}}" style="background:linear-gradient(135deg, oklch(58% .22 25), oklch(65% .24 27));color:white;border:none;padding:16px;font-weight:700;font-size:13px;letter-spacing:0.08em;text-transform:uppercase;cursor:pointer;opacity:{{submitBtnOpacity}}">{{submitBtnText}}</button>
            </form>
          </sc-if>
        </div>
      </div>
    </main>
  </sc-if>

  <button onClick="{{scrollTop}}" aria-label="Volver arriba" style="position:fixed;left:16px;bottom:16px;z-index:120;width:46px;height:46px;border-radius:999px;background:oklch(20% .006 270);color:white;border:1px solid oklch(38% .008 270);display:flex;align-items:center;justify-content:center;cursor:pointer;opacity:{{topBtnOpacity}};pointer-events:{{topBtnPointer}};box-shadow:0 10px 24px -10px oklch(20% .01 270 / .5);transition:opacity .35s ease,transform .3s cubic-bezier(.2,.8,.2,1),background .3s ease" style-hover="transform:translateY(-4px);background:oklch(58% .22 25)"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7-7 7 7"></path></svg></button>

  <a href="https://wa.me/573128011838" target="_blank" rel="noopener" aria-label="WhatsApp" style="position:fixed;right:24px;bottom:100px;z-index:120;width:58px;height:58px;border-radius:999px;background:#25D366;color:white;display:flex;align-items:center;justify-content:center;box-shadow:0 10px 26px -8px oklch(20% .01 270 / .5);transition:transform .3s cubic-bezier(.2,.8,.2,1),box-shadow .3s ease" style-hover="transform:scale(1.09);box-shadow:0 18px 34px -10px oklch(20% .01 270 / .6)"><svg viewBox="0 0 24 24" width="30" height="30" fill="currentColor"><path d="M12 2a9.9 9.9 0 00-8.5 15L2 22l5.2-1.4A10 10 0 1012 2zm5.6 14.1c-.2.7-1.3 1.3-1.9 1.4-.5.1-1.1.1-1.8-.1-.4-.1-1-.3-1.7-.6-3-1.3-4.9-4.3-5-4.5-.2-.2-1.2-1.6-1.2-3s.7-2.1 1-2.4c.2-.3.5-.4.7-.4h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.3 0 .5l-.4.5c-.1.1-.3.3-.1.6.1.3.6 1.1 1.4 1.8 1 .9 1.8 1.1 2 1.2.3.1.4.1.6-.1l.7-.9c.2-.2.4-.2.6-.1l2 1c.2.1.4.2.4.3.1.2.1.7-.1 1.3z"></path></svg></a>

  <footer style="background:oklch(19% .004 265);color:oklch(92% .003 270);padding:72px 24px 34px">
    <div style="max-width:1280px;margin:0 auto">
      <div style="display:grid;grid-template-columns:{{footerGrid}};gap:40px;margin-bottom:48px">
        <div>
          <img src="assets/logo.webp" alt="Fitness Life S.A.S" style="height:80px;width:auto;margin-bottom:16px;filter:brightness(0) invert(1)"/>
          <p style="color:oklch(70% .006 270);font-size:14px;line-height:1.6;margin:0 0 20px;max-width:280px">Inspirando al mundo a entrenar. Importadores directos de equipamiento deportivo comercial de las marcas líderes en EE.UU.</p>
          <div style="font-size:11px;letter-spacing:0.12em;color:oklch(98% 0 0);text-transform:uppercase;font-weight:700;margin-bottom:10px">Recibe nuestros catálogos</div>
          <div style="display:flex;gap:8px;margin-bottom:24px;max-width:300px">
            <input placeholder="Tu correo" style="flex:1;min-width:0;background:oklch(26% .005 265);border:1px solid oklch(36% .006 270);color:oklch(95% 0 0);padding:12px 14px;font-size:13px"/>
            <button style="background:oklch(58% .22 25);color:white;border:none;padding:0 20px;font-weight:700;font-size:12px;cursor:pointer">Ir</button>
          </div>
          <div style="font-size:11px;letter-spacing:0.12em;color:oklch(98% 0 0);text-transform:uppercase;font-weight:700;margin-bottom:10px">Síguenos en redes</div>
          <div style="display:flex;gap:10px">
            <a href="https://www.instagram.com/fitnesslifesas/?hl=es" target="_blank" rel="noopener" aria-label="Instagram" style="width:38px;height:38px;border-radius:999px;border:1px solid oklch(34% .006 270);display:flex;align-items:center;justify-content:center;color:oklch(92% .003 270);transition:background .3s ease,color .3s ease,border-color .3s ease" style-hover="background:oklch(58% .22 25);border-color:oklch(58% .22 25);color:white"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="16" height="16" rx="5"></rect><circle cx="12" cy="12" r="4.2"></circle><circle cx="17.8" cy="6.2" r="1.2" fill="currentColor" stroke="none"></circle></svg></a>
            <a href="https://www.facebook.com/fitnesslifesas" target="_blank" rel="noopener" style="width:38px;height:38px;border-radius:999px;border:1px solid oklch(34% .006 270);display:flex;align-items:center;justify-content:center;color:oklch(92% .003 270);transition:background .3s ease,color .3s ease,border-color .3s ease" style-hover="background:oklch(58% .22 25);border-color:oklch(58% .22 25);color:white"><svg viewBox="0 0 24 24" width="17" height="17" fill="currentColor"><path d="M14.5 8.5V6.9c0-.8.2-1.2 1.4-1.2h1.5V2.8c-.3 0-1.2-.1-2.3-.1-2.4 0-4 1.4-4 4.1v1.7H8.4v3h2.7V21h3.4v-8.5h2.6l.4-3h-3z"></path></svg></a>
            <a href="https://wa.me/573128011838" target="_blank" rel="noopener" style="width:38px;height:38px;border-radius:999px;border:1px solid oklch(34% .006 270);display:flex;align-items:center;justify-content:center;color:oklch(92% .003 270);transition:background .3s ease,color .3s ease,border-color .3s ease" style-hover="background:oklch(58% .22 25);border-color:oklch(58% .22 25);color:white"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12 2a9.9 9.9 0 00-8.5 15L2 22l5.2-1.4A10 10 0 1012 2zm5.6 14.1c-.2.7-1.3 1.3-1.9 1.4-.5.1-1.1.1-1.8-.1-.4-.1-1-.3-1.7-.6-3-1.3-4.9-4.3-5-4.5-.2-.2-1.2-1.6-1.2-3s.7-2.1 1-2.4c.2-.3.5-.4.7-.4h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.3 0 .5l-.4.5c-.1.1-.3.3-.1.6.1.3.6 1.1 1.4 1.8 1 .9 1.8 1.1 2 1.2.3.1.4.1.6-.1l.7-.9c.2-.2.4-.2.6-.1l2 1c.2.1.4.2.4.3.1.2.1.7-.1 1.3z"></path></svg></a>
          </div>
        </div>
        <div>
          <div style="font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;margin-bottom:18px;color:oklch(98% 0 0)">Equipos</div>
          <div style="display:flex;flex-direction:column;gap:12px">
            <a href="#" onClick="{{goToCategory_elipticas}}" style="font-size:14px;color:oklch(70% .006 270)">Elípticas Premium</a>
            <a href="#" onClick="{{goToCategory_bicicletas}}" style="font-size:14px;color:oklch(70% .006 270)">Bicicletas Estáticas &amp; Indoor</a>
            <a href="#" onClick="{{goToCategory_trotadoras}}" style="font-size:14px;color:oklch(70% .006 270)">Trotadoras de Última Generación</a>
            <a href="#" onClick="{{goToCategory_pesas}}" style="font-size:14px;color:oklch(70% .006 270)">Máquinas de Pesas y Fuerza</a>
          </div>
        </div>
        <div>
          <div style="font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;margin-bottom:18px;color:oklch(98% 0 0)">Soluciones</div>
          <div style="display:flex;flex-direction:column;gap:12px">
            <span style="font-size:14px;color:oklch(70% .006 270)">Línea Comercial de Alto Tráfico</span>
            <span style="font-size:14px;color:oklch(70% .006 270)">Dotación de Gimnasios y Clubes</span>
            <span style="font-size:14px;color:oklch(70% .006 270)">Gimnasios Corporativos y de Hoteles</span>
            <span style="font-size:14px;color:oklch(70% .006 270)">Equipamiento Premium para Hogar</span>
            <span style="font-size:14px;color:oklch(70% .006 270)">Equipos 100% Americanos</span>
          </div>
        </div>
        <div>
          <div style="font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;margin-bottom:18px;color:oklch(98% 0 0)">Contáctenos</div>
          <div style="display:flex;flex-direction:column;gap:18px">
            <div>
              <div style="font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:oklch(58% .22 25);margin-bottom:5px">Dirección</div>
              <div style="font-size:14px;color:oklch(70% .006 270);line-height:1.55">Cra 1 # 30-75 barrio Fátima<br/>Cali, Colombia</div>
            </div>
            <div>
              <div style="font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:oklch(58% .22 25);margin-bottom:5px">Teléfono</div>
              <div style="font-size:14px;color:oklch(70% .006 270);line-height:1.55">312-8011838 &nbsp;·&nbsp; 312-7199008</div>
            </div>
            <div>
              <div style="font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:oklch(58% .22 25);margin-bottom:5px">Email</div>
              <a href="mailto:fitnesslifesas@gmail.com" style="font-size:14px;color:oklch(70% .006 270)">fitnesslifesas@gmail.com</a>
            </div>
            <div>
              <div style="font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:oklch(58% .22 25);margin-bottom:5px">Horario de trabajo</div>
              <div style="font-size:14px;color:oklch(70% .006 270);line-height:1.55">Lunes a Viernes / 8:00AM - 5:00PM</div>
            </div>
          </div>
        </div>
      </div>
      <div style="border-top:1px solid oklch(30% .006 270);padding-top:24px;display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <span style="font-size:12px;color:oklch(70% .006 270)">© 2026 Fitness Life S.A.S — Todos los derechos reservados. Importador Directo Autorizado en Colombia.</span>
        <div style="display:flex;gap:20px">
          <span style="font-size:12px;color:oklch(70% .006 270)">Términos de Uso</span>
          <span style="font-size:12px;color:oklch(70% .006 270)">Política de Privacidad</span>
        </div>
      </div>
    </div>
  </footer>


  <!-- Floating Cart -->
  <sc-if value="{{hasCartItems}}">
    <div onClick="{{goToCart}}" style="position:fixed;bottom:32px;right:20px;background:oklch(58% .22 25);color:white;padding:16px 24px;border-radius:999px;box-shadow:0 12px 24px -8px oklch(58% .22 25 / .5);cursor:pointer;display:flex;align-items:center;gap:12px;z-index:9999;transition:transform .3s cubic-bezier(.2,.8,.2,1)" style-hover="transform:translateY(-5px) scale(1.05)">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
      <div style="display:flex;flex-direction:column">
        <span style="font-size:14px;font-weight:700;line-height:1">Ver Cotización</span>
        <span style="font-size:11px;opacity:0.9">{{cartCount}} equipos añadidos</span>
      </div>
    </div>
  </sc-if>
</div>

</x-dc>
<script>
window.DB_PRODUCTS = <?php echo $productosJson; ?>;
window.DB_CATEGORIAS = <?php echo $categoriasJson; ?>;
window.DB_MARCAS = <?php echo $marcasJson; ?>;
window.DB_SERIES = <?php echo $seriesJson; ?>;
window.DB_ERROR = <?php echo json_encode($dbError ?? null); ?>;
</script>
<script type="text/x-dc" data-dc-script>
const ITEMS_PER_PAGE = 12;
const R = (p) => (window.__resources && window.__resources[p]) || p;
const DB_CATS = window.DB_CATEGORIAS || [];
const CATEGORIES = {};
DB_CATS.forEach((c, idx) => {
    CATEGORIES[c.slug] = {
        slug: c.slug,
        name: c.name,
        tag: c.tag || 'Equipos profesionales',
        img: c.img || '',
        badge: c.badge || ('0' + (idx+1)),
        mostrar_home: (c.mostrar_home !== undefined && c.mostrar_home !== null) ? Number(c.mostrar_home) : 1,
        longDesc: 'Colección de equipos de alto rendimiento y durabilidad. Equipos de uso rudo diseñados para soportar el trabajo diario en los gimnasios, conjuntos y hogares.',
        features: [
            {title:'Grado comercial', desc:'Construcción robusta y materiales de alta calidad para un rendimiento continuo.'},
            {title:'Biomecánica natural', desc:'Trayectorias de movimiento diseñadas para minimizar el riesgo de lesión y proteger las articulaciones.'},
            {title:'Durabilidad probada', desc:'Superficies y estructuras reforzadas ideales para miles de horas de uso.'},
            {title:'Bajo mantenimiento', desc:'Componentes diseñados para minimizar los tiempos de parada y costos de servicio.'}
        ]
    };
});

class Component extends DCLogic {
  state = {
    page: 'home',
    categorySlug: null,
    heroIndex: 0,
    formSubmitted: false,
    cart: [],
    cartOpen: false,
    catalogoFilter: 'todos',
    catalogoSearch: '',
    catalogoPage: 1,
    categoryLimit: 6,
    catalogoViewMode: 'grid',
    catalogoSort: 'serie_order',
    modalProduct: null,
    catalogoBrands: [],
    brandSearch: '',
    catalogoSeries: [],
    seriesSearch: '',
    categoriesVisible: false,
    hoveredSolution: null,
    activeSolution: null,
    videoMuted: true,
    isMobile: false,
    isNarrow: false,
    menuOpen: false,
    equiposOpen: false,
    quote: { name: '', company: '', email: '', phone: '', type: '', message: '' },
    allProducts: [],
    quoteErrors: {},
    quoteSent: false,
    quoteSending: false,
    quoteName: '',
    showTop: false,
    globalSearchOpen: false,
    solucionesVisible: false,
    colorsVisible: false,
    rs800Sound: false,
    circuitosVisible: false,
    processVisible: false,
    marcasVisible: false,
    clientesVisible: false,
    whyUsVisible: false,
  };

  componentDidMount() {
        this.setState({ allProducts: window.DB_PRODUCTS || [] });
    this.scheduleHero();
    this.handleResize();
    window.addEventListener('resize', this.handleResize);
    window.addEventListener('scroll', this.handleScroll, { passive: true });
    window.addEventListener('popstate', this.handlePopState);
    
    // Also parse initial hash on load
    setTimeout(this.handlePopState, 100);
  }

  scheduleHero() {
    clearTimeout(this._heroTimer);
    const delay = this.state.heroIndex === 0 ? 9000 : 14000;
    this._heroTimer = setTimeout(() => {
      if (this.state.page === 'home') {
        this.setState(s => ({ heroIndex: (s.heroIndex + 1) % 2 }), () => this.scheduleHero());
      } else {
        this.scheduleHero();
      }
    }, delay);
  }

  componentWillUnmount() {
    clearTimeout(this._heroTimer);
    window.removeEventListener('resize', this.handleResize);
    window.removeEventListener('scroll', this.handleScroll);
    Object.values(this._solObservers).forEach((o) => o.disconnect());
    if (this._categoriasObserver) this._categoriasObserver.disconnect();
    Object.values(this._sectionObservers).forEach((o) => o.disconnect());
  }


  handlePopState = () => {
    const hash = window.location.hash;
    if (hash === '#catalogo') {
      this.setState({ page: 'catalogo', catalogoFilter: 'todos' });
    } else if (hash.startsWith('#catalogo=')) {
      this.setState({ page: 'catalogo', catalogoFilter: hash.split('=')[1] });
    } else if (hash.startsWith('#categoria=')) {
      this.setState({ page: 'catalogo', catalogoFilter: hash.split('=')[1] });
    } else if (hash === '#contacto') {
      this.setState({ page: 'contacto' });
    } else {
      this.setState({ page: 'home' });
    }
    window.scrollTo(0,0);
  };

  handleResize = () => {
    const w = window.innerWidth;
    const mobile = w < 1100;
    const narrow = w < 700;
    if (mobile !== this.state.isMobile || narrow !== this.state.isNarrow) {
      this.setState({ isMobile: mobile, isNarrow: narrow, menuOpen: mobile ? this.state.menuOpen : false });
    }
  };
  toggleMenu = () => this.setState(s => ({ menuOpen: !s.menuOpen }));
  handleScroll = () => {
    const show = window.scrollY > 500;
    if (show !== this.state.showTop) this.setState({ showTop: show });
  };
  scrollTop = () => window.scrollTo({ top: 0, behavior: 'smooth' });

  _solObservers = {};
  setSolRef = (i) => (el) => {
    if (!el || this._solObservers[i]) return;
    const obs = new IntersectionObserver((entries) => {
      entries.forEach((en) => {
        if (en.isIntersecting && this.state.isMobile) this.setState({ activeSolution: i });
      });
    }, { threshold: 0.6, rootMargin: '-15% 0px -15% 0px' });
    obs.observe(el);
    this._solObservers[i] = obs;
  };

  heroImgRef = (el) => {
    if (el) { const s = el.getAttribute('data-src'); if (s && s.indexOf('{{') === -1 && el.getAttribute('src') !== s) el.setAttribute('src', s); }
  };
  videoRef = (el) => {
    this._videoEl = el;
    if (el) { el.muted = this.state.videoMuted; const p = el.play(); if (p && p.catch) p.catch(() => {}); }
  };
  toggleSound = (e) => {
    e.preventDefault(); e.stopPropagation();
    const next = !this.state.videoMuted;
    if (this._videoEl) { this._videoEl.muted = next; if (!next) { const p = this._videoEl.play(); if (p && p.catch) p.catch(() => {}); } }
    this.setState({ videoMuted: next });
  };
  goHeroSlide = (i) => () => this.setState({ heroIndex: i }, () => this.scheduleHero());

  setQuote = (key) => (e) => {
    const v = e.target.value;
    this.setState(s => {
      const errs = { ...s.quoteErrors };
      delete errs[key];
      return { quote: { ...s.quote, [key]: v }, quoteErrors: errs };
    });
  };
  submitQuote = async () => {
    const q = this.state.quote;
    const errors = {};
    if (!q.name.trim()) errors.name = 'Ingresa tu nombre';
    if (!q.email.trim()) errors.email = 'Ingresa tu email';
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(q.email.trim())) errors.email = 'Email no válido';
    const digits = q.phone.replace(/\D/g, '');
    if (!digits) errors.phone = 'Ingresa tu teléfono';
    else if (digits.length < 7) errors.phone = 'Teléfono incompleto';
    if (Object.keys(errors).length) { this.setState({ quoteErrors: errors }); return; }
    
    try {
      await fetch('v1/cotizaciones/send_quote.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...q, items: [] })
      });
    } catch(err) {
      console.error(err);
    }
    
    this.setState({
      quoteSent: true,
      quoteName: q.name.trim().split(' ')[0],
      quoteErrors: {},
    });
  };
  resetQuote = () => this.setState({ quoteSent: false, quote: { name: '', company: '', email: '', phone: '', type: '', message: '' }, quoteErrors: {} });
  scrollTo_personalizacion = (e) => this.navTo('personalizacion')(e);

  toggleRs800Sound = () => {
    const el = this._rs800El;
    const on = !this.state.rs800Sound;
    if (el) {
      el.muted = !on;
      if (on) {
        el.volume = 1;
        el.play().catch(() => {
          el.muted = true;
          el.play().catch(() => {});
          this.setState({ rs800Sound: false });
        });
      }
    }
    this.setState({ rs800Sound: on });
  };

  setRs800Ref = (el) => {
    if (el && el !== this._rs800El) {
      this._rs800El = el;
      el.muted = !this.state.rs800Sound; el.defaultMuted = true; el.playsInline = true;
      el.play().catch(() => {});
    }
  };

  openEquipos = () => this.setState({ equiposOpen: true });
  closeEquipos = () => this.setState({ equiposOpen: false });
  goCat = (slug) => (e) => { e.preventDefault(); this.setState({ menuOpen: false, equiposOpen: false }); this.goToCategory(slug); };
  navTo = (id) => {
    const fn = this.scrollToSection(id);
    return (e) => { this.setState({ menuOpen: false }); fn(e); };
  };

  _sectionObservers = {};
  setSectionRef = (key) => (el) => {
    if (el && !this._sectionObservers[key]) {
      const obs = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting) {
          this.setState({ [key]: true });
          obs.disconnect();
        }
      }, { threshold: 0.15 });
      obs.observe(el);
      this._sectionObservers[key] = obs;
    }
  };

  cardRefs = {};
  setCardRef = (slug) => (el) => { this.cardRefs[slug] = el; };
  handleCardGlow = (slug) => (e) => {
    const card = this.cardRefs[slug];
    if (!card) return;
    const glow = card.querySelector('[data-glow="' + slug + '"]');
    if (!glow) return;
    const rect = card.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    glow.style.opacity = '1';
    glow.style.background = 'radial-gradient(220px circle at ' + x + 'px ' + y + 'px, oklch(58% .22 25 / .55), transparent 70%)';
  };
  handleCardGlowLeave = (slug) => () => {
    const card = this.cardRefs[slug];
    const glow = card && card.querySelector('[data-glow="' + slug + '"]');
    if (glow) glow.style.opacity = '0';
  };

  categoriasRef = null;
  setCategoriasRef = (el) => {
    this.categoriasRef = el;
    if (el && !this._categoriasObserver) {
      this._categoriasObserver = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting) {
          this.setState({ categoriesVisible: true });
          this._categoriasObserver.disconnect();
        }
      }, { threshold: 0.15 });
      this._categoriasObserver.observe(el);
    }
  };

  scrollToId(id) {
    const el = document.getElementById(id);
    if (el) {
      const top = el.getBoundingClientRect().top + window.scrollY - 76;
      window.scrollTo({ top, behavior: 'smooth' });
    }
  }

  goHome = (e) => {
    if (e) e.preventDefault();
    window.history.pushState(null, '', window.location.pathname);
    if (e) e.preventDefault();
    this.setState({ page: 'home', categorySlug: null });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  goToContacto = (e) => {
    if (e) e.preventDefault();
    window.history.pushState(null, '', '#contacto');
    window.history.pushState(null, '', '#contacto');
    this.setState({ page: 'contacto', formSubmitted: false });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };



  openGlobalSearch = (e) => {
    if (e) e.preventDefault();
    this.setState({ globalSearchOpen: !this.state.globalSearchOpen });
    if (!this.state.globalSearchOpen) {
      setTimeout(() => {
        const el = document.getElementById('headerSearchInput');
        if (el) el.focus();
      }, 50);
    }
  };
  
  onHeaderSearch = (e) => {
    const val = e.target.value;
    this.setState({ catalogoSearch: val });
    if (this.state.page !== 'catalogo') {
      window.history.pushState(null, '', '#catalogo');
      this.setState({ page: 'catalogo', categorySlug: null, catalogoPage: 1, menuOpen: false });
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  };


  goToCatalogo = (e) => {
    if (e) e.preventDefault();
    window.history.pushState(null, '', '#catalogo');
    this.setState({ page: 'catalogo', catalogoFilter: 'todos', catalogoPage: 1, catalogoSearch: '', catalogoBrands: [], catalogoSeries: [] }, () => {
      setTimeout(() => window.scrollTo(0, 0), 10);
      setTimeout(() => window.scrollTo(0, 0), 100);
    });
  };
  

  toggleCart = (prod) => (e) => {
    e.preventDefault();
    e.stopPropagation();
    const cart = [...this.state.cart];
    const prodId = prod.id || prod.item_no;
    const idx = cart.findIndex(p => (p.id || p.item_no) === prodId);
    let added = false;
    if (idx >= 0) {
      cart.splice(idx, 1);
    } else {
      cart.push({ ...prod, id: prodId, qty: 1 });
      added = true;
    }
    this.setState({ cart });
    
    // Si lo agregó desde el modal, cerramos el modal para que vea el carrito flotante
    if (added && this.state.modalProduct && (this.state.modalProduct.id || this.state.modalProduct.item_no) === prodId) {
      setTimeout(() => {
        this.setState({ modalProduct: null });
      }, 300);
    }
  };
  

  updateCartQty = (prodId, delta) => (e) => {
    e.preventDefault();
    const cart = [...this.state.cart];
    const idx = cart.findIndex(p => (p.id || p.item_no) === prodId);
    if (idx >= 0) {
      cart[idx].qty = Math.max(1, cart[idx].qty + delta);
      this.setState({ cart });
    }
  };

  removeFromCart = (prodId) => (e) => {
    e.preventDefault();
    this.setState({ cart: this.state.cart.filter(p => (p.id || p.item_no) !== prodId) });
  };
  
  goToCart = (e) => {
    if (e) e.preventDefault();
    this.setState({ page: 'contacto', formSubmitted: false });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };


  toggleCatalogoBrand = (brand) => (e) => {
    const brands = [...this.state.catalogoBrands];
    const idx = brands.indexOf(brand);
    if (idx >= 0) brands.splice(idx, 1);
    else brands.push(brand);
    this.setState({ catalogoBrands: brands, catalogoPage: 1 });
  };

  toggleCatalogoSerie = (serie) => (e) => {
    if (e) e.preventDefault();
    const series = [...this.state.catalogoSeries];
    const idx = series.indexOf(serie);
    if (idx >= 0) series.splice(idx, 1);
    else series.push(serie);
    this.setState({ catalogoSeries: series, catalogoPage: 1 });
  };

  clearCatalogoSeries = (e) => {
    if (e) e.preventDefault();
    this.setState({ catalogoSeries: [], catalogoPage: 1 });
  };



  loadMoreCategory = (e) => {
    if (e) e.preventDefault();
    this.setState(s => ({ categoryLimit: s.categoryLimit + 6 }));
  };

  setGridView = () => this.setState({ catalogoViewMode: 'grid' });
  setTableView = () => this.setState({ catalogoViewMode: 'table' });
  loadMoreCatalogo = (e) => {
    if (e) e.preventDefault();
  };
  setCatalogoPage = (pageNum) => (e) => {
    if (e) e.preventDefault();
    this.setState({ catalogoPage: pageNum }, () => {
      window.scrollTo({ top: 300, behavior: 'smooth' });
    });
  };


  onTopBrandSelect = (e) => {
    const val = e.target.value;
    if (!val) {
      this.setState({ catalogoBrands: [], catalogoPage: 1 });
    } else {
      this.setState({ catalogoBrands: [val], catalogoPage: 1 });
    }
  };

  onCatalogoSort = (e) => {
    this.setState({ catalogoSort: e.target.value });
  };
  openModal = (prod) => (e) => {
    if (e) e.preventDefault();
    this.setState({ modalProduct: prod });
  };
  closeModal = (e) => {
    if (e) e.preventDefault();
    this.setState({ modalProduct: null });
  };

  onCatalogoSearch = (e) => {
    this.setState({ catalogoSearch: e.target.value, catalogoPage: 1 });
  };
  onBrandSearch = (e) => {
    this.setState({ brandSearch: e.target.value });
  };
  onSerieSearch = (e) => {
    this.setState({ seriesSearch: e.target.value });
  };
  setCatalogoFilter = (filter) => (e) => {
    if (e) e.preventDefault();
    this.setState({ catalogoFilter: filter, catalogoPage: 1, catalogoSeries: [], catalogoBrands: [] });
  };

  goToCategory = (slug) => {
    window.history.pushState(null, '', '#catalogo=' + slug);
    this.setState({ page: 'catalogo', catalogoFilter: slug, catalogoPage: 1, catalogoSearch: '', catalogoBrands: [], catalogoSeries: [] }, () => {
      setTimeout(() => window.scrollTo(0, 0), 10);
      setTimeout(() => window.scrollTo(0, 0), 100);
    });
  };

  scrollToSection = (id) => (e) => {
    e.preventDefault();
    if (this.state.page !== 'home') {
      this.setState({ page: 'home', categorySlug: null }, () => setTimeout(() => this.scrollToId(id), 60));
    } else {
      this.scrollToId(id);
    }
  };


  goBackHistory = (e) => {
    e.preventDefault();
    window.history.back();
  };

  submitForm = async (e) => {
    e.preventDefault();
    if (this.state.formSubmitting) return;

    this.setState({ formSubmitting: true });
    const fd = new FormData(e.target);
    const data = Object.fromEntries(fd.entries());
    data.items = this.state.cart.map(c => ({
      item_no: c.item_no || 'N/A',
      name: c.name,
      serie: c.serie || c.series || '',
      img: c.image || c.img || '',
      qty: c.qty || 1
    }));
    
    try {
      await fetch('v1/cotizaciones/send_quote.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });
    } catch (err) {
      console.error(err);
    }
    
    this.setState({ formSubmitted: true, formSubmitting: false, cart: [] });
  };

  renderVals() {
    const heroData = [
      { img: R('assets/hero_hoist.webp'), alt: 'Disciplina, enfoque, consistencia y resultados', fit: 'cover', label1: 'BIOMECÁNICA', label2: 'REVOLUCIONARIA' },
      { video: 'https://www.realleadermex.com/galeriavideo/inicio3.mp4', alt: 'Real Leader — equipos de fuerza comercial', label1: 'FUERZA', label2: 'REAL LEADER' },
    ];
    const heroSlides = heroData.map((h, i) => {
      const active = i === this.state.heroIndex;
      return {
        ...h,
        img: h.img || R('assets/hero_hoist.webp'),
        fit: h.fit || 'cover',
        opacity: active ? 1 : 0,
        isImage: !h.video,
        showVideo: !!h.video && active,
        videoSrc: h.video || '',
      };
    });
    const heroDots = heroData.map((_, i) => ({
      label: 'Slide ' + (i + 1),
      width: i === this.state.heroIndex ? '46px' : '22px',
      bg: i === this.state.heroIndex ? 'oklch(58% .22 25)' : 'oklch(98% 0 0 / .35)',
      onClick: () => this.setState({ heroIndex: i }, () => this.scheduleHero()),
    }));

    const categories = Object.values(CATEGORIES).filter(c => c.slug !== 'todos' && c.mostrar_home === 1).map((c, i) => ({
      slug: c.slug, name: c.name, tag: c.tag, img: c.img, badge: c.badge,
      onClick: () => this.goToCategory(c.slug),
      revealOpacity: 1,
      revealTransform: this.state.categoriesVisible ? 'translateY(0)' : 'translateY(28px)',
      revealDelay: (i * 0.12) + 's',
      setRef: this.setCardRef(c.slug),
      onMove: this.handleCardGlow(c.slug),
      onLeave: this.handleCardGlowLeave(c.slug),
      topBorderScale: this.state.hoveredCategory === c.slug ? 1 : 0,
      onEnterCard: () => this.setState({ hoveredCategory: c.slug }),
      onLeaveCard: () => this.setState({ hoveredCategory: null }),
    }));

    const carouselItems = [
      { img: R('uploads/elipticas.jpeg'), name: 'Elípticas', tag: 'Cardio de bajo impacto', slug: 'elipticas', pad: '46px' },
      { img: R('uploads/trotadoras.jpeg'), name: 'Trotadoras', tag: 'Alto rendimiento', slug: 'trotadoras' },
      { img: R('uploads/estaticas.webp'), name: 'Bicicletas Estáticas', tag: 'Equipos para rehabilitación', slug: 'bicicletas' },
      { img: R('uploads/escaleras.jpeg'), name: 'Escaleras', tag: 'Cardio de alta intensidad', slug: 'escaleras', pad: '4px' },
      { img: R('uploads/spinning.jpeg'), name: 'Spinning', tag: 'Clases grupales', slug: 'bicicletas' },
      { img: R('assets/portfolio_pesas.png'), name: 'Máquinas de Pesas', tag: 'Fuerza selectorizada', slug: 'pesas' },
      { img: R('assets/portfolio_peso_libre.png'), name: 'Peso Libre', tag: 'Placa cargada y banco ajustable', slug: 'pesolibre' },
      { img: R('assets/portfolio_circuitos.png'), name: 'Circuitos', tag: 'Fuerza en circuito', slug: 'circuitos', pad: '0px', zoom: 1.12 },
      { img: R('uploads/funcional.jpeg'), name: 'Entrenamiento Funcional', tag: 'Synrgy360 — Life Fitness', slug: 'entrenamientofuncional' },
      { img: R('assets/portfolio_accesorios.png'), name: 'Accesorios', tag: 'Mancuernas, barras y discos', slug: 'accesorios' },
    ];

    const carouselCards = [...carouselItems, ...carouselItems].map((c, i) => ({
      img: c.img, name: c.name, tag: c.tag, pad: c.pad || '18px', zoom: c.zoom || 1, onClick: () => this.goToCategory(c.slug), key: i,
    }));

    const currentCategory = CATEGORIES[this.state.categorySlug] || Object.values(CATEGORIES)[0];
    const categoryProductsFull = (this.state.allProducts || []).filter(p => {
      const slug = this.state.categorySlug;
      const name = (p.name || '').toLowerCase();
      const series = (p.series || '').toLowerCase();
      const search = name + ' ' + series;
      if (slug === 'elipticas') return search.includes('elliptical') || search.includes('elíptica') || search.includes('eliptica') || search.includes('cross trainer');
      if (slug === 'trotadoras') return search.includes('treadmill') || search.includes('trotadora') || search.includes('caminadora') || search.includes('run');
      if (slug === 'bicicletas') return search.includes('bike') || search.includes('bicicleta') || search.includes('spin') || search.includes('cycle') || search.includes('stair') || search.includes('escalera') || search.includes('climb') || search.includes('step');
            if (slug === 'pesas') return !search.includes('elliptical') && !search.includes('eliptica') && !search.includes('treadmill') && !search.includes('trotadora') && !search.includes('caminadora') && !search.includes('bike') && !search.includes('bicicleta') && !search.includes('spin') && !search.includes('cycle') && !search.includes('stair') && !search.includes('escalera') && !search.includes('climb');
      if (slug === 'accesorios') return search.includes('mancuerna') || search.includes('barra') || search.includes('disco') || search.includes('accesorio') || search.includes('grip') || search.includes('kettlebell') || search.includes('banda') || search.includes('peso');
      if (slug === 'comercial' || slug === 'institucional' || slug === 'hogar') return true;
      return false;
    }).map(p => {
      let image = p.img || '';
      if (!image && p.media_json && p.media_json.length > 0 && p.media_json[0].url) {
         image = p.media_json[0].url;
      }
      if (image && !image.startsWith('http') && !image.startsWith('cotizaciones/') && !image.startsWith('v1/cotizaciones/')) { image = 'cotizaciones/' + image; }
      const item_no = p.item_no || 'N/A';
      const prodId = p.id || item_no;
      const inCart = this.state.cart.some(c => (c.id || c.item_no) === prodId);
      const cartText = inCart ? 'Añadido ✓' : 'Añadir';
      const cartBg = inCart ? 'oklch(96% 0 0)' : 'oklch(97% 0 0)';
      const cartBgHover = inCart ? 'oklch(40% 0 0)' : 'oklch(58% .22 25)';
      const cartColor = inCart ? 'oklch(20% .005 270)' : 'oklch(20% .005 270)';
      const cartBorder = inCart ? '1px solid oklch(80% 0 0)' : '1px solid transparent';
      const waMsg = encodeURIComponent('Hola, estoy interesado en recibir información y precios del equipo ' + p.name + ' (SKU: ' + item_no + ').');
            
      const onOpen = this.openModal({ ...p, image, item_no, prodId, hasDims, hasWeight, waLink, inCart, cartText, cartBg, cartBgHover, cartColor, cartBorder });
      return { ...p, image, hasDims, hasWeight, item_no, prodId, inCart, cartText, cartBg, cartBgHover, cartColor, cartBorder, waLink, onToggleCart: this.toggleCart({ ...p, image, item_no, id: prodId }), onOpen };
    });
    
    
    const seriePosMap = {};
    const globalSeriePosMap = {};
    (window.DB_SERIES || []).forEach(s => {
      const sName = (s.name || '').trim();
      const pos = Number(s.pos);
      if (s.categoria_id) {
        seriePosMap[s.categoria_id + '_' + sName] = pos;
      }
      if (!(sName in globalSeriePosMap) || pos < globalSeriePosMap[sName]) {
        globalSeriePosMap[sName] = pos;
      }
    });

    const getSeriePos = (p) => {
      if (!p || !p.serie) return 999999;
      const sName = p.serie.trim();
      if (!sName) return 999999;
      const catKey = (p.categoria_id || 0) + '_' + sName;
      if (catKey in seriePosMap) return seriePosMap[catKey];
      if (sName in globalSeriePosMap) return globalSeriePosMap[sName];
      return 999999;
    };

    const sorter = (a, b) => {
      const s = this.state.catalogoSort;
      if (s === 'name_asc') return (a.name||'').localeCompare(b.name||'');
      if (s === 'name_desc') return (b.name||'').localeCompare(a.name||'');
      if (s === 'series_asc') return (a.series||'').localeCompare(b.series||'');

      // 'serie_order' (predeterminado): Orden de Series configurado en el admin parametrizado por Categoría
      const posA = getSeriePos(a);
      const posB = getSeriePos(b);
      if (posA !== posB) return posA - posB;
      const brandCmp = (a.series || '').localeCompare(b.series || '');
      if (brandCmp !== 0) return brandCmp;
      return (a.name || '').localeCompare(b.name || '');
    };
    categoryProductsFull.sort(sorter);

    const categoryProducts = categoryProductsFull.slice(0, this.state.categoryLimit);
    const hasMoreCategory = categoryProductsFull.length > this.state.categoryLimit;



    // Obtener la categoría actual
    const currentCatSlug = this.state.catalogoFilter;
    const currentCatObj = currentCatSlug !== 'todos' ? (window.DB_CATEGORIAS || []).find(c => c.slug === currentCatSlug) : null;
    const currentCatId = currentCatObj ? currentCatObj.id : null;

    // Obtener marcas únicas que realmente tienen productos en la categoría actual (o todas si es 'todos')
    const activeCategoryProducts = currentCatId 
      ? (this.state.allProducts || []).filter(p => p.categoria_id == currentCatId)
      : (this.state.allProducts || []);
    const availableBrandNames = [...new Set(activeCategoryProducts.map(p => (p.series || '').trim()).filter(Boolean))];

    const bs = this.state.brandSearch.toLowerCase().trim();
    // Marcas disponibles en esta categoría, ordenadas por el catálogo de marcas
    const filteredDbMarcas = (window.DB_MARCAS || []).filter(m => {
      const matchSearch = !bs || m.name.toLowerCase().includes(bs);
      const matchCat = currentCatId ? availableBrandNames.includes(m.name.trim()) : true;
      return matchSearch && matchCat;
    });

    const catalogoBrandsList = filteredDbMarcas.map(m => ({
      name: m.name,
      checked: this.state.catalogoBrands.includes(m.name),
      onChange: this.toggleCatalogoBrand(m.name)
    }));

    const topBrandValue = this.state.catalogoBrands.length === 1 ? this.state.catalogoBrands[0] : '';
    const topBrandsList = (window.DB_MARCAS || []).filter(m => {
      return currentCatId ? availableBrandNames.includes(m.name.trim()) : true;
    }).map(m => ({
      name: m.name,
      selected: this.state.catalogoBrands.includes(m.name)
    }));

    // Las series se muestran asociadas a la categoría seleccionada, o todas las series si está en 'todos'
    const knownSeries = [];
    if (currentCatId) {
      // 1. Series asociadas específicamente a esta categoría en la base de datos
      const relevantSeriesDb = (window.DB_SERIES || []).filter(s => s.categoria_id == currentCatId);
      relevantSeriesDb.forEach(s => {
        const sName = (s.name || '').trim();
        if (sName && !knownSeries.includes(sName)) {
          knownSeries.push(sName);
        }
      });

      // 2. Series de productos pertenecientes a esta categoría
      (this.state.allProducts || []).forEach(p => {
        if (p.categoria_id == currentCatId && p.serie && p.serie.trim() && !knownSeries.includes(p.serie.trim())) {
          knownSeries.push(p.serie.trim());
        }
      });
    } else {
      // Todas las series disponibles en la base de datos y productos
      (window.DB_SERIES || []).forEach(s => {
        const sName = (s.name || '').trim();
        if (sName && !knownSeries.includes(sName)) {
          knownSeries.push(sName);
        }
      });
      (this.state.allProducts || []).forEach(p => {
        if (p.serie && p.serie.trim() && !knownSeries.includes(p.serie.trim())) {
          knownSeries.push(p.serie.trim());
        }
      });
    }

    // Ordenar knownSeries según pos dentro de la categoría
    knownSeries.sort((s1, s2) => {
      const p1 = currentCatId && (currentCatId + '_' + s1 in seriePosMap) ? seriePosMap[currentCatId + '_' + s1] : (globalSeriePosMap[s1] ?? 999999);
      const p2 = currentCatId && (currentCatId + '_' + s2 in seriePosMap) ? seriePosMap[currentCatId + '_' + s2] : (globalSeriePosMap[s2] ?? 999999);
      if (p1 !== p2) return p1 - p2;
      return s1.localeCompare(s2);
    });

    const catalogoSeriesList = knownSeries.map(sName => {
      const checked = this.state.catalogoSeries.includes(sName);
      return {
        name: sName,
        checked: checked,
        bg: checked ? 'oklch(58% .22 25)' : 'white',
        color: checked ? 'white' : 'oklch(20% .005 270)',
        border: checked ? '1px solid oklch(58% .22 25)' : '1px solid oklch(85% 0 0)',
        onClick: this.toggleCatalogoSerie(sName)
      };
    });
    const hasSeriesFilter = knownSeries.length > 0;
    const hasSelectedSeries = this.state.catalogoSeries.length > 0;
    const noSeriesFilter = !hasSeriesFilter;

        const filterDefs = [{ id: 'todos', label: 'Todos' }].concat(
        (window.DB_CATEGORIAS || []).map(c => ({ id: c.slug, label: c.name }))
    );
    
    const catalogoFilters = filterDefs.map(f => {
      const active = this.state.catalogoFilter === f.id;
      return {
        label: f.label,
        onClick: this.setCatalogoFilter(f.id),
        bg: active ? 'oklch(96% 0 0)' : 'white',
        color: active ? 'oklch(58% .22 25)' : 'oklch(30% .008 270)',
        border: active ? 'oklch(58% .22 25)' : 'oklch(80% .008 270)'
      };
    });

    const catalogoProductsFull = (this.state.allProducts || []).filter(p => {
      const f = this.state.catalogoFilter;
      if (f !== 'todos') {
        const cat = (window.DB_CATEGORIAS || []).find(c => c.slug === f);
        if (cat && p.categoria_id != cat.id) return false;
      }
      const q = this.state.catalogoSearch.trim().toLowerCase();
      if (q) {
        const searchStr = ((p.name||'') + ' ' + (p.series||'') + ' ' + (p.serie||'') + ' ' + (p.item_no||'')).toLowerCase();
        if (!searchStr.includes(q)) return false;
      }
      
      const b = this.state.catalogoBrands;
      if (b.length > 0 && !b.includes(p.series)) return false;

      const ser = this.state.catalogoSeries;
      if (ser.length > 0 && (!p.serie || !ser.includes(p.serie.trim()))) return false;
      
      return true;
    }).map(p => {
      let image = p.img || '';
      if (!image && p.media_json && p.media_json.length > 0 && p.media_json[0].url) {
         image = p.media_json[0].url;
      }
      if (image && !image.startsWith('http') && !image.startsWith('cotizaciones/') && !image.startsWith('v1/cotizaciones/')) { image = 'cotizaciones/' + image; }
      const item_no = p.item_no || 'N/A';
      const prodId = p.id || item_no;
      const hasDims = p.set_up_dimension && p.set_up_dimension.trim() !== '';
      const hasWeight = p.weight_stack && p.weight_stack.trim() !== '';
      const hasSerie = p.serie && p.serie.trim() !== '';
      const serie = p.serie ? p.serie.trim() : '';
      
      const inCart = this.state.cart.some(c => (c.id || c.item_no) === prodId);
      const cartText = inCart ? 'Añadido ✓' : 'Añadir';
      const cartBg = inCart ? 'oklch(96% 0 0)' : 'oklch(20% .005 270)';
      const cartBgHover = inCart ? 'oklch(40% 0 0)' : 'oklch(58% .22 25)';
      const cartColor = inCart ? 'oklch(20% .005 270)' : 'white';
      const cartBorder = inCart ? '1px solid oklch(80% 0 0)' : '1px solid transparent';
      
      const waMsg = encodeURIComponent('Hola, estoy interesado en recibir información y precios del equipo ' + p.name + ' (SKU: ' + item_no + ').');
      const waLink = 'https://wa.me/573128011838?text=' + waMsg;
      
      
      const onOpen = this.openModal({ ...p, image, item_no, prodId, hasDims, hasWeight, hasSerie, serie, waLink, inCart, cartText, cartBg, cartBgHover, cartColor, cartBorder });
      return { ...p, image, hasDims, hasWeight, hasSerie, serie, item_no, prodId, inCart, cartText, cartBg, cartBgHover, cartColor, cartBorder, waLink, onToggleCart: this.toggleCart({ ...p, image, item_no, id: prodId }), onOpen };
    });
    
    catalogoProductsFull.sort(sorter);

    const totalPages = Math.ceil(catalogoProductsFull.length / ITEMS_PER_PAGE);
    const currentPage = Math.min(this.state.catalogoPage, totalPages || 1);
    const startIdx = (currentPage - 1) * ITEMS_PER_PAGE;
    const catalogoProducts = catalogoProductsFull.slice(startIdx, startIdx + ITEMS_PER_PAGE);
    const hasMoreCatalogo = false; // Disable load more button
    
    // Generate page array for pagination UI
    let catalogoPages = [];
    if (totalPages > 1) {
      let startP = Math.max(1, currentPage - 2);
      let endP = Math.min(totalPages, startP + 4);
      if (endP - startP < 4) {
        startP = Math.max(1, endP - 4);
      }
      for (let i = startP; i <= endP; i++) {
        catalogoPages.push({
          num: i,
          active: i === currentPage,
          bg: i === currentPage ? 'oklch(58% .22 25)' : 'white',
          color: i === currentPage ? 'white' : 'oklch(20% .005 270)',
          border: i === currentPage ? '1px solid oklch(58% .22 25)' : '1px solid oklch(85% 0 0)',
          onClick: this.setCatalogoPage(i)
        });
      }
    }


    const solutionsData = [
      { title: 'Línea Comercial', desc: 'Equipos profesionales para dotar tu gimnasio.', slug: 'comercial' },
      { title: 'Línea Institucional', desc: 'Soluciones para hoteles, clubes y conjuntos.', slug: 'institucional' },
      { title: 'Equipos para Hogar', desc: 'Mantente en forma en casa con equipos premium.', slug: 'hogar' },
      { title: 'Accesorios', desc: 'Pesas libres y elementos de entrenamiento funcional.', slug: 'accesorios' },
    ].map((s, i) => {
      const hov = this.state.isMobile ? this.state.activeSolution === i : this.state.hoveredSolution === i;
      const imageVisible = this.state.isMobile ? this.state.solucionesVisible : hov;
      const bgImgs = [R('assets/sol_comercial.webp'), R('assets/sol_institucional.webp'), R('assets/sol_hogar.webp'), R('assets/sol_accesorios.webp')];
      return {
        ...s,
        onClick: () => this.goToCategory(s.slug),
        bgImg: bgImgs[i],
        kicker: s.title.startsWith('Línea') ? 'Línea' : (s.title.startsWith('Equipos') ? 'Equipos para' : ''),
        main: s.title.startsWith('Línea') ? s.title.slice(6) : (s.title.startsWith('Equipos') ? 'Hogar' : s.title),
        imgOpacity: imageVisible ? 0.88 : 0,
        imgTransform: hov ? 'scale(1.08)' : 'scale(1)',
        num: '0' + (i + 1),
        cardRef: this.setSolRef(i),
        onEnter: () => this.setState({ hoveredSolution: i }),
        onLeave: () => this.setState({ hoveredSolution: null }),
        fillScale: hov ? 1 : 0,
        numColor: hov ? 'oklch(97% 0 0)' : 'oklch(45% .012 270)',
        ruleColor: hov ? 'oklch(99% 0 0)' : 'oklch(58% .22 25)',
        contentTransform: hov ? 'translateY(-10px)' : 'translateY(0)',
        numTransform: hov ? 'translateY(-6px)' : 'translateY(0)',
        descColor: hov ? 'oklch(96% .02 25)' : 'oklch(66% .012 270)',
        linkColor: hov ? 'oklch(99% 0 0)' : 'oklch(65% .24 27)',
        arrowTransform: hov ? 'translateX(8px)' : 'translateX(0)',
        revealOpacity: 1,
        revealTransform: 'translateY(0)',
        revealDelay: (i * 0.11) + 's',
      };
    });

    const processStepsData = [
      { num: '01', title: 'Desensamble Completo', desc: 'Cada máquina se desmonta por completo hasta su chasis de acero para inspeccionar cada soldadura y componente estructural.' },
      { num: '02', title: 'Sandblasting & Pintura', desc: 'Removemos la pintura vieja mediante chorro de arena y aplicamos pintura electrostática al horno de calidad automotriz.' },
      { num: '03', title: 'Componentes Nuevos', desc: 'Reemplazamos rodamientos, guayas de acero, poleas, empuñaduras y tapicerías por repuestos originales premium nuevos.' },
      { num: '04', title: 'Certificación & Garantía', desc: 'Cada equipo es calibrado y sometido a pruebas de carga antes de recibir nuestro sello de garantía total de Fitness Life.' },
    ].map((s, i) => ({
      ...s,
      revealOpacity: 1,
      revealTransform: this.state.processVisible ? 'translateY(0)' : 'translateY(32px)',
      revealDelay: (i * 0.1) + 's',
    }));

    const brandsData = [
      { name: 'Life Fitness', img: R('assets/trim_brand_lifefitness.webp'), w: '150px', h: '25px' },
      { name: 'Precor', img: R('assets/trim_brand_precor.webp'), w: '150px', h: '24px' },
      { name: 'Hoist', img: R('assets/trim_brand_hoist.webp'), w: '150px', h: '42px' },
      { name: 'Keiser', img: R('assets/trim_brand_keiser.webp'), w: '150px', h: '24px' },
      { name: 'True Fitness', img: R('assets/trim_brand_truefitness.webp'), w: '150px', h: '20px' },
      { name: 'Freemotion', img: R('assets/trim_brand_freemotion.webp'), w: '150px', h: '26px' },
      { name: 'Realleader USA', img: R('assets/trim_brand_realleader.webp'), w: '150px', h: '34px' },
    ].map((b, i) => ({
      ...b,
      revealOpacity: this.state.marcasVisible ? 1 : 0,
      revealTransform: this.state.marcasVisible ? 'scale(1)' : 'scale(0.9)',
      revealDelay: (i * 0.06) + 's',
    }));

    const clientsData = [
      { name: 'Mundo Fitness', img: R('assets/trim_client_mundofitness2.webp'), w: '59px', h: '62px' },
      { name: 'Powerhouse', img: R('assets/trim_client_powerhouse.webp'), w: '150px', h: '27px' },
      { name: 'Energym Club', img: R('assets/trim_client_energym.webp'), w: '150px', h: '49px' },
      { name: 'Arena', img: R('assets/trim_client_arena.webp'), w: '89px', h: '62px' },
      { name: 'NH Hoteles', img: R('assets/trim_client_nhhoteles.webp'), w: '72px', h: '62px' },
      { name: 'Colegio Bolívar', img: R('assets/trim_client_colegiobolivar.webp'), w: '150px', h: '28px' },
      { name: 'Unicoc', img: R('assets/trim_client_unicoc.webp'), w: '150px', h: '46px' },
    ].map((c, i) => ({
      ...c,
      revealOpacity: this.state.clientesVisible ? 1 : 0,
      revealTransform: this.state.clientesVisible ? 'scale(1)' : 'scale(0.9)',
      revealDelay: (i * 0.06) + 's',
    }));

    const whyUsData = [
      { icon: 'US', title: 'Equipos 100% Americanos', desc: 'Biomecánica original importada de EE.UU., el estándar de oro global en durabilidad y ergonomía.' },
      { icon: '✓', title: 'Calidad Garantizada', desc: 'Equipos con garantía real y respaldo total de repuestos y soporte técnico oficial.' },
      { icon: '→', title: 'Importación & Entrega', desc: 'Importadores directos con logística propia y entregas seguras a nivel nacional en Colombia.' },
      { icon: '★', title: 'Soporte Experto', desc: 'Acompañamiento post-venta con técnicos especializados durante toda la vida útil de tus máquinas.' },
    ].map((w, i) => ({
      ...w,
      revealOpacity: this.state.whyUsVisible ? 1 : 0,
      revealTransform: this.state.whyUsVisible ? 'translateY(0)' : 'translateY(32px)',
      revealDelay: (i * 0.1) + 's',
    }));

    return {
      isHome: this.state.page === 'home',
      isCategoria: this.state.page === 'categoria',
      hasMoreCategory,
      loadMoreCategory: this.loadMoreCategory,
      categoryTotal: categoryProductsFull.length,
      isCatalogo: this.state.page === 'catalogo',

      catalogoSort: this.state.catalogoSort,
      onCatalogoSort: this.onCatalogoSort,
      
      
      modalOpen: !!this.state.modalProduct,
      closeModal: this.closeModal,
      modalProd: (() => {
        const mp = this.state.modalProduct;
        if (!mp) return {};
        const item_no = mp.item_no || 'N/A';
        const prodId = mp.id || item_no;
        const inCart = this.state.cart.some(c => (c.id || c.item_no) === prodId);
        const cartText = inCart ? 'Añadido ✓' : 'Añadir';
        const cartBg = inCart ? 'oklch(96% 0 0)' : 'oklch(20% .005 270)';
        const cartColor = inCart ? 'oklch(20% .005 270)' : 'white';
        const cartBorder = inCart ? '1px solid oklch(80% 0 0)' : '1px solid transparent';
        return { 
          ...mp, 
          prodId,
          inCart, 
          cartText, 
          cartBg, 
          cartColor, 
          cartBorder,
          onToggleCart: this.toggleCart({ ...mp, item_no, id: prodId }) 
        };
      })(),
      modalImages: (() => {
        const mp = this.state.modalProduct;
        if (!mp) return [];
        let imgs = [];
        if (mp.media_json && mp.media_json.length > 0) {
          imgs = mp.media_json.map(m => m.url.startsWith('http') || m.url.startsWith('v1/cotizaciones/') ? m.url : 'v1/cotizaciones/' + m.url);
        } else if (mp.image) {
          imgs = [mp.image];
        }
        return imgs.map((img, i) => ({ url: img, isFirst: i === 0 }));
      })(),


      catalogoBrandsList,
      topBrandsList,
      topBrandValue,
      onTopBrandSelect: this.onTopBrandSelect,
      catalogoSeriesList,
      hasSeriesFilter,
      hasCartItems: this.state.cart.length > 0,
      cartCount: this.state.cart.length,
      goToCart: this.goToCart,
      cartItemsList: this.state.cart.map(c => ({...c, onRemove: this.removeFromCart(c.id || c.item_no), onInc: this.updateCartQty(c.id || c.item_no, 1), onDec: this.updateCartQty(c.id || c.item_no, -1)})),
      catalogoSearch: this.state.catalogoSearch,
      onCatalogoSearch: this.onCatalogoSearch,
      brandSearch: this.state.brandSearch,
      onBrandSearch: this.onBrandSearch,
      hasSelectedSeries,
      noSeriesFilter,
      clearCatalogoSeries: this.clearCatalogoSeries,
      catalogoFilters,
      catalogoProducts,
      hasMoreCatalogo,
      catalogoPages: typeof catalogoPages !== 'undefined' ? catalogoPages : [],
      hasPagination: typeof totalPages !== 'undefined' && totalPages > 1,
      currentPage: typeof currentPage !== 'undefined' ? currentPage : 1,
      totalPages: typeof totalPages !== 'undefined' ? totalPages : 1,
      prevPageDisabled: typeof currentPage !== 'undefined' && currentPage <= 1,
      prevPageEnabled: !(typeof currentPage !== 'undefined' && currentPage <= 1),
      nextPageDisabled: typeof currentPage !== 'undefined' && typeof totalPages !== 'undefined' && currentPage >= totalPages,
      nextPageEnabled: !(typeof currentPage !== 'undefined' && typeof totalPages !== 'undefined' && currentPage >= totalPages),
      onPrevPage: this.setCatalogoPage(typeof currentPage !== 'undefined' ? currentPage - 1 : 1),
      onNextPage: this.setCatalogoPage(typeof currentPage !== 'undefined' ? currentPage + 1 : 2),
      loadMoreCatalogo: this.loadMoreCatalogo,
      catalogoTotal: catalogoProductsFull.length,
      gridCols: this.state.isMobile ? 'repeat(auto-fill, minmax(160px, 1fr))' : 'repeat(auto-fill, minmax(200px, 1fr))',

      noCatalogoProducts: catalogoProducts.length === 0,
      goToCatalogo: this.goToCatalogo,
      isContacto: this.state.page === 'contacto',
      goBackHistory: this.goBackHistory,
      openGlobalSearch: this.openGlobalSearch,
      onHeaderSearch: this.onHeaderSearch,
      headerSearchDisplay: this.state.globalSearchOpen ? "block" : "none",
      navVisibility: this.state.globalSearchOpen ? "hidden" : "visible",
      formSubmitted: this.state.formSubmitted,
      formNotSubmitted: !this.state.formSubmitted,
      formSubmitting: !!this.state.formSubmitting,
      submitBtnText: this.state.formSubmitting ? 'Enviando solicitud...' : 'Enviar Solicitud',
      submitBtnOpacity: this.state.formSubmitting ? 0.7 : 1,
      categoriasRef: this.setCategoriasRef,
      categoriesHeaderOpacity: 1,
      categoriesHeaderTransform: 'translateY(0)',
      solucionesRef: this.setSectionRef('solucionesVisible'),
      solucionesHeaderOpacity: 1,
      solucionesHeaderTransform: 'translateY(0)',
      circuitosRef: this.setSectionRef('circuitosVisible'),
      circuitosHeaderOpacity: this.state.circuitosVisible ? 1 : 0,
      circuitosHeaderTransform: this.state.circuitosVisible ? 'translateY(0)' : 'translateY(28px)',
      circBarScale: this.state.circuitosVisible ? 1 : 0,
      circRow1ImgOpacity: this.state.circuitosVisible ? 1 : 0,
      circRow1ImgTransform: this.state.circuitosVisible ? 'translateX(0)' : 'translateX(-40px)',
      circRow1TextOpacity: this.state.circuitosVisible ? 1 : 0,
      circRow1TextTransform: this.state.circuitosVisible ? 'translateX(0)' : 'translateX(40px)',
      circRow2ImgOpacity: this.state.circuitosVisible ? 1 : 0,
      circRow2ImgTransform: this.state.circuitosVisible ? 'translateX(0)' : 'translateX(40px)',
      circRow2TextOpacity: this.state.circuitosVisible ? 1 : 0,
      circRow2TextTransform: this.state.circuitosVisible ? 'translateX(0)' : 'translateX(-40px)',
      colorsRef: this.setSectionRef('colorsVisible'),
      colorsImg: R('assets/personalizacion_colores.png'),
      persCols: this.state.isMobile ? '1fr' : 'minmax(0,340px) minmax(0,1fr)',
      persVideoMaxH: this.state.isMobile ? '560px' : '640px',
      persVideoW: this.state.isMobile ? 'min(100%, 315px)' : '100%',
      rs800Video: R('assets/realleader_rs800_loop.mp4'),
      rs800Ref: this.setRs800Ref,
      toggleRs800Sound: this.toggleRs800Sound,
      rs800Muted: !this.state.rs800Sound,
      rs800SoundLabel: this.state.rs800Sound ? 'Silenciar' : 'Activar sonido',
      rs800SoundIcon: this.state.rs800Sound ? '🔊' : '🔇',
      colorsOpacity: this.state.colorsVisible ? 1 : 0,
      colorsTransform: this.state.colorsVisible ? 'translateY(0)' : 'translateY(28px)',
      colorsImgTransform: this.state.colorsVisible ? 'translateY(0) scale(1)' : 'translateY(40px) scale(.97)',
      scrollTo_personalizacion: this.scrollTo_personalizacion,
      processRef: this.setSectionRef('processVisible'),
      processHeaderOpacity: 1,
      processHeaderTransform: 'translateY(0)',
      marcasRef: this.setSectionRef('marcasVisible'),
      marcasHeaderOpacity: 1,
      marcasHeaderTransform: 'translateY(0)',
      clientesRef: this.setSectionRef('clientesVisible'),
      clientesHeaderOpacity: 1,
      clientesHeaderTransform: 'translateY(0)',
      whyUsRef: this.setSectionRef('whyUsVisible'),
      whyUsHeaderOpacity: 1,
      whyUsHeaderTransform: 'translateY(0)',
      whyUsBannerOpacity: 1,
      whyUsBannerTransform: 'translateY(0)',
      catLayoutDir: this.state.isMobile ? 'column' : 'row',
      catAsideWidth: this.state.isMobile ? '100%' : '260px',
      catAsidePos: this.state.isMobile ? 'relative' : 'sticky',
      catListDir: this.state.isMobile ? 'row' : 'column',
      catListWrap: this.state.isMobile ? 'wrap' : 'nowrap',
      modalPadding: this.state.isMobile ? '24px' : '40px',
      modalImgPadding: this.state.isMobile ? '20px' : '40px',
      modalImgMinH: this.state.isMobile ? '280px' : '300px',
      modalBorderR: this.state.isMobile ? 'none' : '1px solid oklch(92% 0 0)',
      modalBorderB: this.state.isMobile ? '1px solid oklch(92% 0 0)' : 'none',
      contactGrid: this.state.isNarrow ? '1fr' : '1fr 1.3fr',
      contactInfoOrder: this.state.isNarrow ? 2 : 1,
      contactFormOrder: this.state.isNarrow ? 1 : 2,
      modalBorderL: this.state.isMobile ? 'none' : '1px solid oklch(92% 0 0)',
      catAsideTop: this.state.isMobile ? '0' : '80px',
      goHome: this.goHome,
      logoH: this.state.isMobile ? '62px' : '96px',
      navDisplay: this.state.globalSearchOpen ? 'none' : (this.state.isMobile ? 'none' : 'flex'),
      ctaDisplay: this.state.isMobile ? 'none' : 'block',
      burgerDisplay: this.state.isMobile ? 'flex' : 'none',
      mobileMenuDisplay: this.state.isMobile && this.state.menuOpen ? 'block' : 'none',
      bar1: this.state.menuOpen ? 'translateY(7px) rotate(45deg)' : 'none',
      bar2Opacity: this.state.menuOpen ? 0 : 1,
      bar3: this.state.menuOpen ? 'translateY(-7px) rotate(-45deg)' : 'none',
      toggleMenu: this.toggleMenu,
      scrollTop: this.scrollTop,
      topBtnOpacity: this.state.showTop ? 1 : 0,
      topBtnPointer: this.state.showTop ? 'auto' : 'none',
      heroPSize: this.state.isNarrow ? '15px' : '17px',
      heroPGap: this.state.isNarrow ? '26px' : '32px',
      clientBasis: this.state.isNarrow ? '132px' : '190px',
      clientH: this.state.isNarrow ? '64px' : '76px',
      secPad: this.state.isNarrow ? '60px 18px' : (this.state.isMobile ? '80px 22px' : '110px 24px'),
      secPadLg: this.state.isNarrow ? '64px 18px' : (this.state.isMobile ? '88px 22px' : '120px 24px'),
      secPadSm: this.state.isNarrow ? '54px 18px' : (this.state.isMobile ? '74px 22px' : '100px 24px'),
      bandPad: this.state.isNarrow ? '44px 0' : (this.state.isMobile ? '56px 0' : '70px 0'),
      heroH: this.state.isNarrow ? '72vh' : (this.state.isMobile ? '80vh' : '88vh'),
      heroMinH: this.state.isNarrow ? '440px' : (this.state.isMobile ? '520px' : '600px'),
      cardW: this.state.isNarrow ? '224px' : (this.state.isMobile ? '260px' : '300px'),
      cardH: this.state.isNarrow ? '300px' : (this.state.isMobile ? '348px' : '400px'),
      grid4: this.state.isNarrow ? '1fr' : (this.state.isMobile ? 'repeat(2,1fr)' : 'repeat(4,1fr)'),
      grid2: this.state.isNarrow ? '1fr' : 'repeat(2,1fr)',
      grid2wide: this.state.isMobile ? '1fr' : '1.5fr 1fr',
      gap2: this.state.isMobile ? '32px' : '56px',
      cotPadLeft: this.state.isMobile ? '64px 24px 40px' : '80px 56px 80px 24px',
      cotPadRight: this.state.isMobile ? '0 24px 64px' : '80px 24px 80px 56px',
      clientsWrap: this.state.isMobile ? 'wrap' : 'nowrap',
      footerGrid: this.state.isNarrow ? '1fr' : (this.state.isMobile ? 'repeat(2,1fr)' : '1.4fr 1fr 1fr 1.2fr'),
      quoteSent: this.state.quoteSent,
      quoteSending: this.state.quoteSending,
      quoteOpen: !this.state.quoteSent,
      quoteName: this.state.quoteName,
      resetQuote: this.resetQuote,
      submitQuote: this.submitQuote,
      qName: this.state.quote.name,
      qCompany: this.state.quote.company,
      qEmail: this.state.quote.email,
      qPhone: this.state.quote.phone,
      qType: this.state.quote.type,
      qMessage: this.state.quote.message,
      onQName: this.setQuote('name'),
      onQCompany: this.setQuote('company'),
      onQEmail: this.setQuote('email'),
      onQPhone: this.setQuote('phone'),
      onQType: this.setQuote('type'),
      onQMessage: this.setQuote('message'),
      qNameError: this.state.quoteErrors.name || '',
      qEmailError: this.state.quoteErrors.email || '',
      qPhoneError: this.state.quoteErrors.phone || '',
      qNameBorder: this.state.quoteErrors.name ? 'oklch(58% .22 25)' : 'oklch(28% .006 270)',
      qEmailBorder: this.state.quoteErrors.email ? 'oklch(58% .22 25)' : 'oklch(28% .006 270)',
      qPhoneBorder: this.state.quoteErrors.phone ? 'oklch(58% .22 25)' : 'oklch(28% .006 270)',
      equiposMenuDisplay: this.state.equiposOpen ? 'block' : 'none',
      equiposArrow: this.state.equiposOpen ? 'rotate(180deg)' : 'rotate(0deg)',
      openEquipos: this.openEquipos,
      closeEquipos: this.closeEquipos,
      navCategories: (window.DB_CATEGORIAS || []).map(c => ({
        name: c.name,
        slug: c.slug,
        onClick: this.goCat(c.slug)
      })),
      goCat_elipticas: this.goCat('elipticas'),
      goCat_bicicletas: this.goCat('bicicletas'),
      goCat_trotadoras: this.goCat('trotadoras'),
      goCat_pesas: this.goCat('pesas'),
      goToContacto: (e) => { this.setState({ menuOpen: false }); this.goToContacto(e); },
      submitForm: this.submitForm,
      scrollTo_categorias: this.navTo('categorias'),
      scrollTo_soluciones: this.navTo('soluciones'),
      scrollTo_circuitos: this.navTo('circuitos'),
      scrollTo_marcas: this.navTo('marcas'),
      scrollTo_clientes: this.navTo('clientes'),
      goToCategory_elipticas: (e) => { e.preventDefault(); this.goToCategory('elipticas'); },
      goToCategory_bicicletas: (e) => { e.preventDefault(); this.goToCategory('bicicletas'); },
      goToCategory_trotadoras: (e) => { e.preventDefault(); this.goToCategory('trotadoras'); },
      goToCategory_pesas: (e) => { e.preventDefault(); this.goToCategory('pesas'); },
      heroSlides,
      heroDots: heroData.map((_, i) => ({
        label: 'Slide ' + (i + 1),
        width: i === this.state.heroIndex ? '46px' : '22px',
        bg: i === this.state.heroIndex ? 'oklch(58% .22 25)' : 'oklch(99% 0 0 / .45)',
        onClick: this.goHeroSlide(i),
      })),
      videoRef: this.videoRef,
      toggleSound: this.toggleSound,
      soundBtnDisplay: heroSlides.some(s => s.showVideo) ? 'flex' : 'none',
      soundIcon: this.state.videoMuted ? '🔇' : '🔊',
      soundLabel: this.state.videoMuted ? 'Activar sonido' : 'Silenciar',
      heroImgRef: this.heroImgRef,
      prevHero: () => this.setState(s => ({ heroIndex: (s.heroIndex + 1) % 2 }), () => this.scheduleHero()),
      nextHero: () => this.setState(s => ({ heroIndex: (s.heroIndex + 1) % 2 }), () => this.scheduleHero()),
      categories,
      currentCategory,
      categoryProducts,
      noProducts: categoryProducts.length === 0,
      solutions: solutionsData,
      carouselCards,
      processSteps: processStepsData,
      brands: brandsData,
      brandsTicker: [...brandsData, ...brandsData].map((b, i) => ({ img: b.img, name: b.name, w: b.w, h: b.h, key: i })),
      clients: clientsData,
      clientsTicker: [...clientsData, ...clientsData].map((c, i) => ({ img: c.img, name: c.name, w: c.w, h: c.h, key: i })),
      whyUs: whyUsData,
    };
  }
}
</script>
</body>
</html>
