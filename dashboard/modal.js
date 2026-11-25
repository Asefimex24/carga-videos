document.addEventListener("DOMContentLoaded", () => {
  const modalEl = document.getElementById("videoModal");
  const videoPlayer = document.getElementById("videoPlayer");
  const modalTitle = modalEl.querySelector(".modal-title");
  const bsModal = new bootstrap.Modal(modalEl);

  const nombresTipo = {
    video1: "Video 1: Presentación",
    video2: "Video 2: Firma de Contrato",
    video3: "Video 3: Firma de Aval(es)",
  };

  // Delegación de clics para botones generados por DataTables
  document.addEventListener("click", async (e) => {
    const btn = e.target.closest(".btn-video");
    if (!btn) return;

    const id = btn.dataset.id;
    const tipo = btn.dataset.tipo;
    const htmlOriginal = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    try {
      const response = await fetch(`../api/videos/ver.php?id=${encodeURIComponent(id)}&tipo=${encodeURIComponent(tipo)}`);
      const data = await response.json();

      if (!response.ok || !data.ok) {
        throw new Error(data.error || "No se pudo obtener el enlace del video.");
      }

      // Actualizar título del modal con contexto
      modalTitle.textContent = `${nombresTipo[tipo] || "Reproductor"} — ${data.cliente} (${data.credito})`;

      // Cargar URL firmada y abrir modal
      videoPlayer.src = data.url;
      videoPlayer.load();
      bsModal.show();

      // Intentar reproducción automática al abrir
      videoPlayer.play().catch(() => {
        // Algunos navegadores requieren clic manual en Play si no está muteado
      });
    } catch (error) {
      Swal.fire({
        icon: "error",
        title: "Error al reproducir",
        text: error.message,
      });
    } finally {
      btn.disabled = false;
      btn.innerHTML = htmlOriginal;
    }
  });

  // Detener y descargar el buffer del video cuando el usuario cierre el modal
  modalEl.addEventListener("hidden.bs.modal", () => {
    videoPlayer.pause();
    videoPlayer.removeAttribute("src");
    videoPlayer.load();
  });
});
