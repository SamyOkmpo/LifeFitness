<?php
session_start();

$entorno = ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1' || strpos($_SERVER['SERVER_NAME'], '.local') !== false || strpos($_SERVER['SERVER_NAME'], '192.168.') !== false) ? 'LOCAL' : 'PRODUCCION';

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
} catch (PDOException $e) {
    die("Error de conexión a la base de datos.");
}
?>
