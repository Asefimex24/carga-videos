<?php

header('Content-Type: application/json; charset=utf-8');

include '../config/config.php';

try {

    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );

    $sql = "
        SELECT
            id_registro,
            sucursal,
            cliente,
            credito,
            fecha_captura,
            video_presentacion,
            video_contrato
        FROM cargavideos
        ORDER BY fecha_creacion DESC
    ";

    $stmt = $pdo->query($sql);

    echo json_encode([
        'success' => true,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}