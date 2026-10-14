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

// Auto migration: ensure series table and productos.serie column exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS series (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        pos INT DEFAULT 0
    )");
    $checkSerie = $pdo->query("SHOW COLUMNS FROM productos LIKE 'serie'")->fetch();
    if (!$checkSerie) {
        $pdo->exec("ALTER TABLE productos ADD COLUMN serie VARCHAR(100) DEFAULT NULL");
    }
} catch (Exception $e) {}
} catch (PDOException $e) {
    die("Error de conexión a la base de datos.");
}
?>
