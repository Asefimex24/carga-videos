<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard de Evidencias</title>

    <!-- Google Fonts: Inter para mejor legibilidad en tamaños pequeños -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <style>
        :root {
            --navy-900: #0a1120;      /* Azul muy oscuro para navbar */
            --navy-800: #131f37;      /* Azul marino profundo */
            --navy-700: #1e3a8a;      /* Azul acento */
            --blue-accent: #2563eb;   /* Azul vibrante para detalles */
            --bg-body: #f1f5f9;       /* Gris azulado muy tenue */
            --border-color: #e2e8f0;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            font-size: 0.875rem; /* 14px base */
            -webkit-font-smoothing: antialiased;
        }

        /* Navbar Azul Oscuro */
        .navbar-custom {
            background: linear-gradient(90deg, var(--navy-900) 0%, var(--navy-800) 100%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding: 0.65rem 0;
        }

        .navbar-brand {
            font-weight: 600;
            font-size: 0.95rem;
            letter-spacing: -0.01em;
            color: #f8fafc !important;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .navbar-brand i {
            color: #60a5fa;
            font-size: 1.1rem;
        }

        /* Tarjetas de KPIs compactas */
        .card-stats {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .card-stats:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.07);
        }

        .card-stats .card-body {
            padding: 0.9rem 1.15rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stat-label {
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-muted);
            margin-bottom: 0.2rem;
        }

        .stat-value {
            font-size: 1.45rem;
            font-weight: 700;
            color: var(--navy-900);
            line-height: 1.2;
            margin: 0;
        }

        .stat-icon {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            background-color: #eff6ff;
            color: var(--navy-700);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        /* Contenedor de Tabla */
        .table-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
            overflow: hidden;
        }

        .table-card .card-header {
            background-color: #ffffff;
            border-bottom: 1px solid var(--border-color);
            padding: 0.85rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-card .card-header h5 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--navy-900);
        }

        /* Tabla compacta y limpia */
        #tablaVideos {
            font-size: 0.8rem; /* ~12.8px para visualizar más datos */
            margin-bottom: 0 !important;
        }

        #tablaVideos thead th {
            background-color: var(--navy-800);
            color: #f1f5f9;
            font-weight: 600;
            font-size: 0.73rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: none;
            padding: 0.65rem 0.85rem;
            white-space: nowrap;
        }

        #tablaVideos tbody td {
            padding: 0.55rem 0.85rem;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
        }

        #tablaVideos tbody tr:hover {
            background-color: #f8fafc;
        }

        /* Badges y botones pequeños dentro de tabla */
        .badge-custom {
            font-size: 0.72rem;
            font-weight: 500;
            padding: 0.3em 0.6em;
            border-radius: 6px;
        }

        /* Ajustes DataTables (Buscador, Paginación, Info) */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            font-size: 0.78rem;
            color: var(--text-muted);
        }

        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select {
            font-size: 0.78rem;
            border: 1px solid #cbd5e1;
            border-radius: 6px !important;
            padding: 0.25rem 0.6rem;
        }

        .dataTables_wrapper .dataTables_filter input:focus,
        .dataTables_wrapper .dataTables_length select:focus {
            border-color: var(--blue-accent);
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
            outline: none;
        }

        .page-link {
            font-size: 0.78rem;
            color: var(--navy-800);
            padding: 0.3rem 0.6rem;
        }

        .page-item.active .page-link {
            background-color: var(--navy-800);
            border-color: var(--navy-800);
            color: #ffffff;
        }

        #videoModal .modal-dialog{
    max-width: 90vw;
}

.video-container{
    height: 70vh;
    background:#000;
}

#videoPlayer{
    width:80%;
    height:80%;
    object-fit:contain;
}
    </style>
</head>

<body>

    <nav class="navbar navbar-dark navbar-custom shadow-sm">
        <div class="container-fluid px-4">
            <span class="navbar-brand mb-0">
                <i class="bi bi-camera-video-fill"></i>
                Videos de Desembolso
            </span>
        </div>
    </nav>

    <div class="container-fluid px-4 py-3">

        <!-- KPIs -->
        <div class="row g-3 mb-3">

            <div class="col-md-4">
                <div class="card card-stats">
                    <div class="card-body">
                        <div>
                            <div class="stat-label">Total Registros</div>
                            <h2 class="stat-value" id="totalRegistros">0</h2>
                        </div>
                        <div class="stat-icon">
                            <i class="bi bi-folder2-open"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card card-stats">
                    <div class="card-body">
                        <div>
                            <div class="stat-label">Videos Presentación</div>
                            <h2 class="stat-value" id="totalPresentacion">0</h2>
                        </div>
                        <div class="stat-icon">
                            <i class="bi bi-person-video3"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card card-stats">
                    <div class="card-body">
                        <div>
                            <div class="stat-label">Videos Contrato</div>
                            <h2 class="stat-value" id="totalContrato">0</h2>
                        </div>
                        <div class="stat-icon">
                            <i class="bi bi-file-earmark-play"></i>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Tabla de Evidencias -->
        <div class="card table-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-table me-1 text-primary"></i>
                    Evidencias Registradas
                </h5>
            </div>

            <div class="card-body p-3">
                <div class="table-responsive">
                    <table id="tablaVideos" class="table table-hover align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fecha</th>
                                <th>Sucursal</th>
                                <th>Cliente</th>
                                <th>#Crédito</th>
                                <th>Presentación</th>
                                <th>Firma</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- JQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="app.js"></script>

    <script>
        $(document).on('click', '.btn-video', function(){

    const id = $(this).data('id');
    const tipo = $(this).data('tipo');

    const video = document.getElementById('videoPlayer');

    video.src =
        `stream.php?id=${id}&tipo=${tipo}`;

    const modal =
        new bootstrap.Modal(
            document.getElementById('videoModal')
        );

    modal.show();

});

document
    .getElementById('videoModal')
    .addEventListener('hidden.bs.modal', function(){

        const video =
            document.getElementById('videoPlayer');

        video.pause();

        video.removeAttribute('src');

        video.load();

    });
    </script>

</body>

<div
    class="modal fade"
    id="videoModal"
    tabindex="-1"
    aria-hidden="true"
>
    <div class="modal-dialog modal-xl modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">
                    Reproductor de Video
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>

            </div>

            <div class="modal-body">

                <video
                    id="videoPlayer"
                    
                    controls
                    preload="metadata"
                >
                    Tu navegador no soporta video HTML5.
                </video>

            </div>

        </div>

    </div>
</div>

</html>