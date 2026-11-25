<?php
header('Content-Type: application/json; charset=utf-8');

include '../../config/config.php';
require '../../vendor/autoload.php';

use Aws\S3\S3Client;

$id   = (int)($_GET['id'] ?? 0);
$tipo = $_GET['tipo'] ?? '';

// Lista blanca estricta para evitar inyección SQL en el nombre de columna
$columnasPermitidas = ['video1', 'video2', 'video3'];

if ($id <= 0 || !in_array($tipo, $columnasPermitidas, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos.']);
    exit;
}

try {
    // 1. Buscar la ruta del video en MySQL
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $sql = "SELECT {$tipo} AS ruta, cliente, credito FROM videos WHERE id_registro = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registro || empty($registro['ruta'])) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'El video solicitado no existe.']);
        exit;
    }

    // 2. Generar URL firmada de Cloudflare R2 válida por 30 minutos
    $s3 = new S3Client([
        'version'                 => 'latest',
        'region'                  => 'auto',
        'endpoint'                => $r2Endp,
        'use_path_style_endpoint' => true,
        'credentials'             => [
            'key'    => $r2Key,
            'secret' => $r2Sec,
        ],
        'signature_version'       => 'v4',
    ]);

    $cmd = $s3->getCommand('GetObject', [
        'Bucket' => $r2Bucket,
        'Key'    => $registro['ruta'],
    ]);

    $request = $s3->createPresignedRequest($cmd, '+30 minutes');
    $urlFirmada = (string)$request->getUri();

    echo json_encode([
        'ok'      => true,
        'url'     => $urlFirmada,
        'cliente' => $registro['cliente'],
        'credito' => $registro['credito'],
    ]);
} catch (Throwable $e) {
    error_log('Error al obtener URL de video R2: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo cargar el video.']);
}
