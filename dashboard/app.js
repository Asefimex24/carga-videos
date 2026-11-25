document.addEventListener("DOMContentLoaded", () => {
  cargarVideos();
});

async function cargarVideos() {
  try {
    const response = await fetch("../api/videos/index.php");

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const resultado = await response.json();

    if (!resultado.success) {
      throw new Error(resultado.error || "La API devolvió un error");
    }

    const registros = resultado.data || [];

    // Actualizar KPI de Total Registros
    const kpiTotal = document.getElementById("totalRegistros");
    if (kpiTotal) {
      kpiTotal.textContent = registros.length;
    }

    $("#tablaVideos").DataTable({
      destroy: true,
      responsive: true,
      pageLength: 25,
      order: [[0, "desc"]],
      language: {
        url: "https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json",
      },
      data: registros,
      columns: [
        {
          data: "id_registro",
        },
        {
          data: "fecha_registro",
          render: function (data) {
            if (!data) return "-";
            const fecha = new Date(data);
            return fecha.toLocaleString("es-MX");
          },
        },
        {
          data: "sucursal",
          render: function (data) {
            return `<span class="badge-sucursal">${data}</span>`;
          },
        },
        {
          data: "cliente",
        },
        {
          data: "credito",
        },
        {
          data: null,
          orderable: false,
          className: "text-center",
          render: function (row) {
            if (!row.video1) return `<span class="badge-no-aplica">Sin archivo</span>`;
            return `
              <button type="button" class="btn-play-evidencia btn-video-1 btn-video" data-id="${row.id_registro}" data-tipo="video1">
                <i class="bi bi-play-fill"></i>
                <span>Ver video</span>
              </button>
            `;
          },
        },
        {
          data: null,
          orderable: false,
          className: "text-center",
          render: function (row) {
            if (!row.video2) return `<span class="badge-no-aplica">Sin archivo</span>`;
            return `
              <button type="button" class="btn-play-evidencia btn-video-2 btn-video" data-id="${row.id_registro}" data-tipo="video2">
                <i class="bi bi-play-fill"></i>
                <span>Ver video</span>
              </button>
            `;
          },
        },
        {
          data: null,
          orderable: false,
          className: "text-center",
          render: function (row) {
            if (!row.video3) {
              return `<span class="badge-no-aplica"><i class="bi bi-dash"></i>No aplica</span>`;
            }
            return `
              <button type="button" class="btn-play-evidencia btn-video-3 btn-video" data-id="${row.id_registro}" data-tipo="video3">
                <i class="bi bi-play-fill"></i>
                <span>Ver video</span>
              </button>
            `;
          },
        },
      ],
    });
  } catch (error) {
    console.error(error);

    Swal.fire({
      icon: "error",
      title: "Error",
      text: "No fue posible cargar los registros.",
    });
  }
}
