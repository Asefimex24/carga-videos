<?php

header('Content-Type: application/json; charset=utf-8');

//cargar datos de conguracion
include '../../config/config.php';

date_default_timezone_set('America/Mexico_City');


// 1. Configuración de conexión a MySQL
$host     = $dbHost;
$dbname   = $dbName;
$usuario  = $dbUser;
$password = $dbPass;

// 2. Leer el JSON enviado desde app.js
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No se recibieron datos válidos.']);
    exit;
}

$sucursal    = trim($input['sucursal'] ?? '');
$cliente     = mb_strtoupper(trim($input['cliente'] ?? ''), 'UTF-8');
$credito     = trim($input['credito'] ?? '');
$ejecutivo   = mb_strtoupper(trim($input['ejecutivo'] ?? ''), 'UTF-8');
$fechaDesem  = trim($input['fechaDesem'] ?? '');
$rutaVideo1  = trim($input['ruta_video1'] ?? '');
$rutaVideo2  = trim($input['ruta_video2'] ?? '');
$fecha_registro = date('Y-m-d H:i:s');


// 3. Validar que ningún campo obligatorio venga vacío
if (
    empty($sucursal) || empty($credito) || empty($cliente) ||
    empty($ejecutivo) || empty($fechaDesem) ||
    empty($rutaVideo1) || empty($rutaVideo2)
) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Faltan campos obligatorios para guardar el registro.']);
    exit;
}

try {
    // 4. Conectar con PDO usando consultas preparadas (protegido contra inyección SQL)
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $usuario, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $sql = "INSERT INTO videos 
            (sucursal, credito, cliente, ejecutivo, fecha_desembolso, video1, video2,fecha_registro) 
            VALUES 
            (:sucursal, :credito, :cliente, :ejecutivo, :fecha_desembolso, :ruta_video1, :ruta_video2, :fecha_registro)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':sucursal'         => $sucursal,
        ':credito'          => $credito,
        ':cliente'          => $cliente,
        ':ejecutivo'        => $ejecutivo,
        ':fecha_desembolso' => $fechaDesem,
        ':ruta_video1'      => $rutaVideo1,
        ':ruta_video2'      => $rutaVideo2,
        ':fecha_registro'   =>  $fecha_registro,
    ]);

    echo json_encode([
        'ok' => true,
        'id' => $pdo->lastInsertId(),
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Error de base de datos: ' . $e->getMessage(),
    ]);
}