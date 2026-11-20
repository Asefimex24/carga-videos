<?php

include '../config/config.php';
require_once __DIR__ . '../../vendor/autoload.php';

use phpseclib3\Net\SFTP;

$id = intval($_GET['id'] ?? 0);
$tipo = $_GET['tipo'] ?? 'presentacion';

try {

    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );

    $stmt = $pdo->prepare("
        SELECT *
        FROM cargavideos
        WHERE id_registro = ?
    ");

    $stmt->execute([$id]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        exit;
    }

    $ruta = ($tipo === 'contrato')
        ? $row['video_contrato']
        : $row['video_presentacion'];

    $sftp = new SFTP($host, $port);

    if (!$sftp->login($user, $pass)) {
        http_response_code(500);
        exit;
    }

    if (!$sftp->file_exists($ruta)) {
        http_response_code(404);
        exit;
    }

    $size = $sftp->filesize($ruta);

    header('Content-Type: video/mp4');
    header('Content-Length: ' . $size);

    $tempFile = tempnam(sys_get_temp_dir(), 'video_');

    if (!$sftp->get($ruta, $tempFile)) {
        http_response_code(500);
        exit;
    }

    readfile($tempFile);

    unlink($tempFile);

} catch(Exception $e) {

    http_response_code(500);

}