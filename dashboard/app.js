document.addEventListener("DOMContentLoaded", () => {
  cargarVideos();
});

async function cargarVideos() {
  try {
    const response = await fetch("../api/videos.php");

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const resultado = await response.json();

    console.log(resultado);
    console.log(resultado.data);
    console.log(resultado.data[0]);

    if (!resultado.success) {
      throw new Error("La API devolvió un error");
    }

    $("#tablaVideos").DataTable({
      destroy: true,

      responsive: true,

      pageLength: 25,

      order: [[0, "desc"]],

      language: {
        url: "https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json",
      },

      data: resultado.data,

      columns: [
        {
          data: "id_registro",
        },

        {
          data: "fecha_captura",
          render: function (data) {
            const fecha = new Date(data);

            return fecha.toLocaleString("es-MX");
          },
        },

        {
          data: "sucursal",
          render: function (data) {
            return `
                            <span class="badge bg-primary">
                                ${data}
                            </span>
                        `;
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

          render: function (row) {
            let botones = "";

            if (row.video_presentacion) {
              botones += `
                               <button
    class="btn btn-primary btn-sm btn-video"
    data-id="${row.id_registro}"
    data-tipo="presentacion"
>
    <i class="bi bi-play-circle"></i>
    Presentación
</button>
                            `;
            }

            return botones;
          },
        },
        {
          data: null,
          orderable: false,

          render: function (row) {
            let botones = "";

            if (row.video_contrato) {
              botones += `
                               <button
    class="btn btn-success btn-sm btn-video"
    data-id="${row.id_registro}"
    data-tipo="contrato"
>
    <i class="bi bi-camera-video"></i>
    Contrato
</button>
                            `;
            }

            return botones;
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
