<?php
header('Content-Type: application/json');

// --- CONFIGURACIÓN DE HETZNER STORAGE BOX ---
$sftp_host = 'uXXXXX.your-storagebox.de'; // Tu host de Hetzner
$sftp_port = 22;                          // Puerto SFTP
$sftp_user = 'uXXXXX';                    // Tu usuario de Hetzner
$sftp_pass = 'TuContraseñaSegura';        // Tu contraseña

// Directores temporales locales en el servidor web
$temp_dir = sys_get_temp_dir() . '/resumable_chunks';
if (!file_exists($temp_dir)) {
    mkdir($temp_dir, 0777, true);
}

// Parámetros que envía Resumable.js por defecto
$resumableIdentifier = $_POST['resumableIdentifier'] ?? '';
$resumableFilename   = $_POST['resumableFilename'] ?? '';
$resumableChunkNumber= $_POST['resumableChunkNumber'] ?? '';
$resumableTotalChunks= $_POST['resumableTotalChunks'] ?? '';

if (!empty($_FILES)) {
    $file = $_FILES['file'];
    
    // Directorio temporal específico para este archivo
    $chunk_dir = $temp_dir . '/' . $resumableIdentifier;
    if (!file_exists($chunk_dir)) {
        mkdir($chunk_dir, 0777, true);
    }

    // Guardar el fragmento actual
    $dest_file = $chunk_dir . '/' . $resumableFilename . '.part' . $resumableChunkNumber;
    move_uploaded_file($file['tmp_name'], $dest_file);

    // Comprobar si ya se recibieron TODOS los fragmentos
    $all_chunks_received = true;
    for ($i = 1; $i <= $resumableTotalChunks; $i++) {
        if (!file_exists($chunk_dir . '/' . $resumableFilename . '.part' . $i)) {
            $all_chunks_received = false;
            break;
        }
    }

    // SI YA ESTÁN TODOS LOS FRAGMENTOS -> ENSAMBLAR Y SUBIR A HETZNER
    if ($all_chunks_received) {
        
        // 1. Sanitizar variables recibidas del formulario
        $cliente  = preg_replace('/[^a-zA-Z0-9_-]/', '_', $_POST['cliente'] ?? 'cliente');
        $sucursal = preg_replace('/[^a-zA-Z0-9_-]/', '_', $_POST['sucursal'] ?? 'sucursal');
        $fecha    = $_POST['fecha'] ?? date('Y-m-d');
        $video_id = preg_replace('/[^a-zA-Z0-9_-]/', '_', $_POST['video_id'] ?? 'id');

        // 2. Crear el archivo de video final localmente
        $final_local_video = $temp_dir . '/' . $video_id . '_' . $resumableFilename;
        $out = fopen($final_local_video, 'wb');

        for ($i = 1; $i <= $resumableTotalChunks; $i++) {
            $chunk_file = $chunk_dir . '/' . $resumableFilename . '.part' . $i;
            $in = fopen($chunk_file, 'rb');
            stream_copy_to_stream($in, $out);
            fclose($in);
            @unlink($chunk_file); // Borrar fragmento
        }
        fclose($out);
        @rmdir($chunk_dir); // Borrar carpeta temporal de fragmentos

        // 3. Crear archivo de metadatos (.txt)
        $txt_content  = "DETALLES DEL VIDEO\n";
        $txt_content .= "==================\n";
        $txt_content .= "Cliente: " . ($_POST['cliente'] ?? '') . "\n";
        $txt_content .= "Sucursal: " . ($_POST['sucursal'] ?? '') . "\n";
        $txt_content .= "Fecha Toma: " . $fecha . "\n";
        $txt_content .= "ID Video: " . $video_id . "\n";
        $txt_content .= "Fecha Carga: " . date('Y-m-d H:i:s') . "\n";

        $final_local_txt = $temp_dir . '/metadata_' . $video_id . '.txt';
        file_put_contents($final_local_txt, $txt_content);

        // 4. Conectar a Hetzner vía SFTP y transferir
        $connection = ssh2_connect($sftp_host, $sftp_port);
        if ($connection && ssh2_auth_password($connection, $sftp_user, $sftp_pass)) {
            $sftp = ssh2_sftp($connection);

            $dir_remoto = "subidas/{$sucursal}/{$fecha}_{$cliente}_{$video_id}";
            @ssh2_sftp_mkdir($sftp, "subidas/{$sucursal}", 0777, true);
            @ssh2_sftp_mkdir($sftp, $dir_remoto, 0777, true);

            $extension = pathinfo($resumableFilename, PATHINFO_EXTENSION);
            
            // Subir archivos a Hetzner
            ssh2_scp_send($connection, $final_local_txt, "{$dir_remoto}/datos_registro.txt", 0644);
            ssh2_scp_send($connection, $final_local_video, "{$dir_remoto}/video_original.{$extension}", 0644);

            // Eliminar archivos temporales del servidor web
            @unlink($final_local_txt);
            @unlink($final_local_video);

            echo json_encode([
                'status' => 'success',
                'message' => '¡Video ensamblado y subido a Hetzner correctamente!'
            ]);
            exit;
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'El video se ensambló pero falló la conexión SFTP con Hetzner.'
            ]);
            exit;
        }
    }

    // Si aún faltan fragmentos por subir
    echo json_encode(['status' => 'uploading_chunk']);
    exit;
}
?>