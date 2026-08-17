<?php
header('Content-Type: application/json');

// --- CONFIGURACIÓN ---
$MODO_PRUEBA_LOCAL = true; // Cambiar a 'false' cuando tengas tus accesos de Hetzner

$sftp_host = 'uXXXXX.your-storagebox.de';
$sftp_port = 22;
$sftp_user = 'uXXXXX';
$sftp_pass = 'TuContraseñaSegura';

$temp_dir = sys_get_temp_dir() . '/resumable_chunks';
if (!file_exists($temp_dir)) {
    mkdir($temp_dir, 0777, true);
}

$resumableIdentifier  = $_POST['resumableIdentifier'] ?? '';
$resumableFilename    = $_POST['resumableFilename'] ?? '';
$resumableChunkNumber = $_POST['resumableChunkNumber'] ?? '';
$resumableTotalChunks = $_POST['resumableTotalChunks'] ?? '';

if (!empty($_FILES)) {
    $file = $_FILES['file'];
    
    $chunk_dir = $temp_dir . '/' . $resumableIdentifier;
    if (!file_exists($chunk_dir)) {
        mkdir($chunk_dir, 0777, true);
    }

    $dest_file = $chunk_dir . '/' . $resumableFilename . '.part' . $resumableChunkNumber;
    move_uploaded_file($file['tmp_name'], $dest_file);

    // Validar si llegaron todos los fragmentos
    $all_chunks_received = true;
    for ($i = 1; $i <= $resumableTotalChunks; $i++) {
        if (!file_exists($chunk_dir . '/' . $resumableFilename . '.part' . $i)) {
            $all_chunks_received = false;
            break;
        }
    }

    if ($all_chunks_received) {
        $cliente    = preg_replace('/[^a-zA-Z0-9_-]/', '_', $_POST['cliente'] ?? 'cliente');
        $sucursal   = preg_replace('/[^a-zA-Z0-9_-]/', '_', $_POST['sucursal'] ?? 'sucursal');
        $fecha      = $_POST['fecha'] ?? date('Y-m-d');
        $video_id   = preg_replace('/[^a-zA-Z0-9_-]/', '_', $_POST['video_id'] ?? 'id');
        $tipo_video = $_POST['tipo_video'] ?? 'video_general';

        // 1. Unir fragmentos
        $final_local_video = $temp_dir . '/' . $tipo_video . '_' . $video_id . '_' . $resumableFilename;
        $out = fopen($final_local_video, 'wb');

        for ($i = 1; $i <= $resumableTotalChunks; $i++) {
            $chunk_file = $chunk_dir . '/' . $resumableFilename . '.part' . $i;
            $in = fopen($chunk_file, 'rb');
            stream_copy_to_stream($in, $out);
            fclose($in);
            @unlink($chunk_file);
        }
        fclose($out);
        @rmdir($chunk_dir);

        // 2. Crear archivo de texto con la información
        $txt_content  = "DETALLES DE LA INSPECCIÓN\n";
        $txt_content .= "=========================\n";
        $txt_content .= "Cliente: " . ($_POST['cliente'] ?? '') . "\n";
        $txt_content .= "Sucursal: " . ($_POST['sucursal'] ?? '') . "\n";
        $txt_content .= "Fecha Toma: " . $fecha . "\n";
        $txt_content .= "ID Inspección: " . $video_id . "\n";
        $txt_content .= "Fecha Carga: " . date('Y-m-d H:i:s') . "\n";

        $final_local_txt = $temp_dir . '/datos_registro.txt';
        file_put_contents($final_local_txt, $txt_content);

        $extension = pathinfo($resumableFilename, PATHINFO_EXTENSION);

        // --- OPCIÓN A: MODO LOCAL EN LARAGON ---
        if ($MODO_PRUEBA_LOCAL) {
            $dir_destino = __DIR__ . "/almacen_local/{$sucursal}/{$fecha}_{$cliente}_{$video_id}";
            if (!file_exists($dir_destino)) {
                mkdir($dir_destino, 0777, true);
            }

            copy($final_local_txt, "{$dir_destino}/datos_registro.txt");
            rename($final_local_video, "{$dir_destino}/{$tipo_video}.{$extension}");
            @unlink($final_local_txt);

            echo json_encode(['status' => 'success', 'message' => 'Procesado localmente con éxito']);
            exit;
        }

        // --- OPCIÓN B: MODO HETZNER STORAGE BOX (SFTP) ---
        $connection = ssh2_connect($sftp_host, $sftp_port);
        if ($connection && ssh2_auth_password($connection, $sftp_user, $sftp_pass)) {
            $sftp = ssh2_sftp($connection);

            $dir_remoto = "subidas/{$sucursal}/{$fecha}_{$cliente}_{$video_id}";
            @ssh2_sftp_mkdir($sftp, "subidas/{$sucursal}", 0777, true);
            @ssh2_sftp_mkdir($sftp, $dir_remoto, 0777, true);

            ssh2_scp_send($connection, $final_local_txt, "{$dir_remoto}/datos_registro.txt", 0644);
            ssh2_scp_send($connection, $final_local_video, "{$dir_remoto}/{$tipo_video}.{$extension}", 0644);

            @unlink($final_local_txt);
            @unlink($final_local_video);

            echo json_encode(['status' => 'success', 'message' => 'Video enviado a Hetzner.']);
            exit;
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error de conexión SFTP con Hetzner.']);
            exit;
        }
    }

    echo json_encode(['status' => 'uploading_chunk']);
    exit;
}
?>