<?php
session_start();

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

// Auto migration: ensure mostrar_home column exists in categorias
try {
    $checkCol = $pdo->query("SHOW COLUMNS FROM categorias LIKE 'mostrar_home'")->fetch();
    if (!$checkCol) {
        $pdo->exec("ALTER TABLE categorias ADD COLUMN mostrar_home TINYINT(1) DEFAULT 1");
    }
} catch (Exception $e) {}

// Auto migration: ensure series table, categoria_id and productos.serie exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS series (
        id INT AUTO_INCREMENT PRIMARY KEY,
        categoria_id INT DEFAULT NULL,
        name VARCHAR(100) NOT NULL,
        pos INT DEFAULT 0
    )");
    try {
        $pdo->exec("ALTER TABLE series DROP INDEX name");
    } catch (Exception $e) {}
    $checkCatCol = $pdo->query("SHOW COLUMNS FROM series LIKE 'categoria_id'")->fetch();
    if (!$checkCatCol) {
        $pdo->exec("ALTER TABLE series ADD COLUMN categoria_id INT DEFAULT NULL");
    }
    $checkSerie = $pdo->query("SHOW COLUMNS FROM productos LIKE 'serie'")->fetch();
    if (!$checkSerie) {
        $pdo->exec("ALTER TABLE productos ADD COLUMN serie VARCHAR(100) DEFAULT NULL");
    }
    // Link existing series to categories based on products if categoria_id is null
    $pdo->exec("UPDATE series s 
        INNER JOIN (
            SELECT serie, categoria_id 
            FROM productos 
            WHERE serie IS NOT NULL AND TRIM(serie) != '' AND categoria_id IS NOT NULL 
            GROUP BY serie, categoria_id
        ) p ON s.name = p.serie 
        SET s.categoria_id = p.categoria_id 
        WHERE s.categoria_id IS NULL");
    // Auto migration: ensure acceso_web and acceso_cotizador exist in usuarios
    try {
        $checkWeb = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'acceso_web'")->fetch();
        if (!$checkWeb) {
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN acceso_web TINYINT(1) NOT NULL DEFAULT 1");
        }
        $checkCotiz = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'acceso_cotizador'")->fetch();
        if (!$checkCotiz) {
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN acceso_cotizador TINYINT(1) NOT NULL DEFAULT 1");
        }
    } catch (Exception $e) {}
} catch (Exception $e) {}
} catch (PDOException $e) {
    die("Error de conexión a la base de datos.");
}
?>
