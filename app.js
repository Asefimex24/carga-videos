document.addEventListener("DOMContentLoaded", function () {
  // Objeto global para almacenar las rutas locales de los videos ensamblados
  let subidasCompletadas = {
    file1_path: null,
    file2_path: null,
  };

  // ------------------------------------------------------------------
  // 1. CONFIGURACIÓN RESUMABLE PARA VIDEO 1 (PRESENTACIÓN)
  // ------------------------------------------------------------------

  let basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf("/") + 1);
  let targetUrl = window.location.origin + basePath + "upload.php";

  console.log(targetUrl);

  // Opciones recomendadas para evitar bloqueos
  const opcionesResumable = {
    target: "upload.php",
    chunkSize: 10 * 1024 * 1024, // Reducir a fragmentos de 1MB para asegurar que PHP los procese rápido
    simultaneousUploads: 5, // Subir de 1 en 1 para evitar colisiones en Windows/Laragon
    testChunks: false,
    forceChunkSize: true, // Forzar a subir directamente sin hacer pruebas previas
    method: "multipart",
  };

  let r1 = new Resumable({
    ...opcionesResumable,
    query: { tipo_video: "presentacion" },
  });

  if (r1.support) {
    r1.assignBrowse(document.getElementById("file1-browse"));
    r1.assignDrop(document.getElementById("file1-dropzone"));
  }

  r1.on("fileAdded", function (file) {
    document.getElementById("file1-name").innerText = "📹 " + file.fileName;
    document.getElementById("file1-progress").style.width = "0%";
    document.getElementById("file1-progress").innerText = "0%";
  });

  r1.on("fileProgress", function (file) {
    let progress = Math.floor(file.progress() * 100);
    let bar = document.getElementById("file1-progress");
    if (bar) {
      bar.style.width = progress + "%";
      bar.innerText = progress + "%";
    }
  });

  r1.on("fileSuccess", function (file, message) {
    try {
      let response = JSON.parse(message);
      subidasCompletadas.file1_path = response.file_path;
      comprobarYFinalizar();
    } catch (e) {
      console.error("Error procesando respuesta de Video 1:", e);
    }
  });

  r1.on("fileError", function (file, message) {
    alert("Error al procesar localmente el Video 1 (Presentación).");
    restablecerBoton();
  });

  // ------------------------------------------------------------------
  // 2. CONFIGURACIÓN RESUMABLE PARA VIDEO 2 (CONTRATO)
  // ------------------------------------------------------------------
  let r2 = new Resumable({
    ...opcionesResumable,
    query: { tipo_video: "contrato" },
  });

  if (r2.support) {
    r2.assignBrowse(document.getElementById("file2-browse"));
    r2.assignDrop(document.getElementById("file2-dropzone"));
  }

  r2.on("fileAdded", function (file) {
    document.getElementById("file2-name").innerText = "📹 " + file.fileName;
    document.getElementById("file2-progress").style.width = "0%";
    document.getElementById("file2-progress").innerText = "0%";
  });

  r2.on("fileProgress", function (file) {
    let progress = Math.floor(file.progress() * 100);
    let bar = document.getElementById("file2-progress");
    if (bar) {
      bar.style.width = progress + "%";
      bar.innerText = progress + "%";
    }
  });

  r2.on("fileSuccess", function (file, message) {
    try {
      let response = JSON.parse(message);
      subidasCompletadas.file2_path = response.file_path;
      comprobarYFinalizar();
    } catch (e) {
      console.error("Error procesando respuesta de Video 2:", e);
    }
  });

  r2.on("fileError", function (file, message) {
    alert("Error al procesar localmente el Video 2 (Contrato).");
    restablecerBoton();
  });

  // ------------------------------------------------------------------
  // 3. EVENTO DEL BOTÓN PRINCIPAL DE ENVÍO
  // ------------------------------------------------------------------
  const form = document.getElementById("form-inspeccion");
  const btnEnviar = document.getElementById("btn-enviar-todo");

  form.addEventListener("submit", function (e) {
    e.preventDefault();

    // Validar que se hayan seleccionado ambos archivos
    if (r1.files.length === 0 || r2.files.length === 0) {
      alert("Por favor selecciona ambos videos antes de enviar la inspección.");
      return;
    }

    // Bloquear botón y mostrar estado
    btnEnviar.disabled = true;
    btnEnviar.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Procesando archivos ...';

    // Iniciar las subidas en paralelo a Laragon
    r1.upload();
    r2.upload();
  });

  // ------------------------------------------------------------------
  // 4. FUNCIÓN DISPARADORA HACIA HETZNER STORAGE BOX
  // ------------------------------------------------------------------
  function comprobarYFinalizar() {
    // Solo ejecuta la transferencia remota cuando AMBOS videos hayan finalizado el ensamblado local
    if (subidasCompletadas.file1_path && subidasCompletadas.file2_path) {
      btnEnviar.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Subiendo Videos...';

      // Preparar el FormData con los campos de metadata y las rutas devueltas
      let formData = new FormData();
      formData.append("action", "finalize");
      formData.append("sucursal", document.getElementById("sucursal").value);
      formData.append("cliente", document.getElementById("cliente").value);
      formData.append("id", document.getElementById("id_inspeccion").value);
      formData.append("file1_path", subidasCompletadas.file1_path);
      formData.append("file2_path", subidasCompletadas.file2_path);

      let basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf("/") + 1);
      let targetUrl = window.location.origin + basePath + "upload.php";

      // Petición final a upload.php (ejecuta phpseclib y sube por SFTP)
      fetch("upload.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            // MODAL DE ÉXITO EN EL CENTRO
            Swal.fire({
              icon: "success",
              title: "Videos Enviados!",
              html: `<p>Datos de envío:</p> <ul class="text-start ms-4"> <li><b>Sucursal:</b> ${formData.get("sucursal")}</li>
            <li><b>Cliente:</b> ${formData.get("cliente")}</li>
            <li><b>No. Crédito:</b> ${formData.get("id")}</li>
        </ul>`,
              confirmButtonText: "Aceptar",
              confirmButtonColor: "#0d6efd",
            });

            // Limpiar interfaz
            form.reset();
            document.getElementById("file1-name").innerText = "";
            document.getElementById("file2-name").innerText = "";
            document.getElementById("file1-progress").style.width = "0%";
            document.getElementById("file1-progress").innerText = "0%";
            document.getElementById("file2-progress").style.width = "0%";
            document.getElementById("file2-progress").innerText = "0%";

            // Reiniciar objetos Resumable y temporales
            subidasCompletadas.file1_path = null;
            subidasCompletadas.file2_path = null;
            r1.cancel();
            r2.cancel();
          } else {
            // MODAL DE ERROR DE SERVIDOR
            Swal.fire({
              icon: "error",
              title: "Error de Transferencia",
              text: "No se pudo completar el envío de los Videos: " + data.error,
              confirmButtonColor: "#dc3545",
            });
          }
          restablecerBoton();
        })
        .catch((error) => {
          console.error("Error en la petición final:", error);
          // MODAL DE ERROR DE RED
          Swal.fire({
            icon: "error",
            title: "Error de Red",
            text: "Ocurrió un problema de conexión durante la transferencia final.",
            confirmButtonColor: "#dc3545",
          });
          restablecerBoton();
        });
    }
  }

  function restablecerBoton() {
    btnEnviar.disabled = false;
    btnEnviar.innerHTML = "Enviar Ambos Videos y Registro";
  }
});
