document.addEventListener("DOMContentLoaded", () => {
  let file1 = null;
  let file2 = null;
  const fileInputs = [];

  const CHUNK_SIZE = 10 * 1024 * 1024; // 10 MB por fragmento (mínimo S3 es 5 MB)
  const CONCURRENCY = 4; // 4 conexiones paralelas por vídeo

  function setupDropzone(dropzoneId, browseBtnId, nameLabelId, progressBarId, onFileSelected) {
    const dropzone = document.getElementById(dropzoneId);
    const browseBtn = document.getElementById(browseBtnId);
    const nameLabel = document.getElementById(nameLabelId);
    const progressBar = document.getElementById(progressBarId);

    const input = document.createElement("input");
    input.type = "file";
    input.accept = "video/*";
    input.className = "hidden";
    document.body.appendChild(input);
    fileInputs.push(input);

    const handleFile = (file) => {
      if (!file) return;
      if (!file.type.startsWith("video/") && !/\.(mp4|mov|avi|mkv|webm|3gp)$/i.test(file.name)) {
        Swal.fire("Archivo inválido", "Por favor selecciona un archivo de video.", "warning");
        return;
      }
      const sizeMB = (file.size / (1024 * 1024)).toFixed(1);
      nameLabel.textContent = `${file.name} (${sizeMB} MB)`;
      progressBar.style.width = "0%";
      progressBar.textContent = "0%";
      onFileSelected(file);
    };

    browseBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      input.click();
    });

    dropzone.addEventListener("click", () => input.click());
    input.addEventListener("change", () => handleFile(input.files[0]));

    dropzone.addEventListener("dragover", (e) => {
      e.preventDefault();
      dropzone.classList.add("border-blue-500", "bg-blue-50");
    });

    dropzone.addEventListener("dragleave", () => {
      dropzone.classList.remove("border-blue-500", "bg-blue-50");
    });

    dropzone.addEventListener("drop", (e) => {
      e.preventDefault();
      dropzone.classList.remove("border-blue-500", "bg-blue-50");
      handleFile(e.dataTransfer.files[0]);
    });
  }

  setupDropzone("file1-dropzone", "file1-browse", "file1-name", "file1-progress", (f) => (file1 = f));
  setupDropzone("file2-dropzone", "file2-browse", "file2-name", "file2-progress", (f) => (file2 = f));

  async function postJson(url, bodyData) {
    const response = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(bodyData),
    });
    const rawText = await response.text();
    try {
      return JSON.parse(rawText);
    } catch {
      throw new Error(`Respuesta inválida de ${url}: ${rawText.substring(0, 150)}`);
    }
  }

  // Sube un fragmento individual y devuelve su ETag
  function uploadSingleChunk(url, blob, onProgress) {
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      xhr.open("PUT", url, true);

      xhr.upload.onprogress = (e) => {
        if (e.lengthComputable) onProgress(e.loaded);
      };

      xhr.onload = () => {
        if (xhr.status >= 200 && xhr.status < 300) {
          const etag = xhr.getResponseHeader("ETag");
          if (!etag) {
            reject(new Error("Falta cabecera ETag en respuesta S3. Revisa ExposeHeaders en CORS."));
            return;
          }
          resolve(etag);
        } else {
          reject(new Error(`Error HTTP ${xhr.status} subiendo fragmento.`));
        }
      };

      xhr.onerror = () => reject(new Error("Error de red al subir fragmento."));
      xhr.send(blob);
    });
  }

  // Gestiona la subida multiparte paralela completa de un archivo
  async function uploadMultipartVideo(file, metaS3, progressBarId) {
    const bar = document.getElementById(progressBarId);
    const totalParts = metaS3.parts.length;
    const loadedPerPart = new Array(totalParts).fill(0);
    const completedParts = [];
    let currentIndex = 0;

    const updateTotalProgress = () => {
      const totalLoaded = loadedPerPart.reduce((acc, v) => acc + v, 0);
      const pct = Math.min(99, Math.round((totalLoaded / file.size) * 100));
      bar.style.width = `${pct}%`;
      bar.textContent = `${pct}%`;
    };

    try {
      // Pool de trabajadores paralelos (CONCURRENCY = 4)
      const workers = Array.from({ length: Math.min(CONCURRENCY, totalParts) }, async () => {
        while (currentIndex < totalParts) {
          const idx = currentIndex++;
          const partInfo = metaS3.parts[idx];
          const start = idx * CHUNK_SIZE;
          const end = Math.min(start + CHUNK_SIZE, file.size);
          const blob = file.slice(start, end);

          const etag = await uploadSingleChunk(partInfo.url, blob, (loaded) => {
            loadedPerPart[idx] = loaded;
            updateTotalProgress();
          });

          loadedPerPart[idx] = blob.size;
          updateTotalProgress();

          completedParts.push({
            PartNumber: partInfo.partNumber,
            ETag: etag,
          });
        }
      });

      await Promise.all(workers);

      // Indicar a Hetzner que una los fragmentos
      const resComplete = await postJson("api/geturl/multipartS3.php", {
        accion: "completar",
        key: metaS3.key,
        uploadId: metaS3.uploadId,
        parts: completedParts,
      });

      if (!resComplete.ok) {
        throw new Error(resComplete.error || "No se pudo ensamblar el video en S3.");
      }

      bar.style.width = "100%";
      bar.textContent = "100%";
    } catch (err) {
      // Limpia los fragmentos huérfanos en Hetzner si algo falló
      postJson("api/geturl/multipartS3.php", {
        accion: "abortar",
        key: metaS3.key,
        uploadId: metaS3.uploadId,
      }).catch(() => {});
      throw err;
    }
  }

  const form = document.getElementById("form-inspeccion");
  const btnSubmit = document.getElementById("btn-enviar-todo");

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    if (!file1 || !file2) {
      Swal.fire("Faltan videos", "Debes adjuntar tanto el Video 1 (Presentación) como el Video 2 (Contrato).", "warning");
      return;
    }

    const parts1 = Math.max(1, Math.ceil(file1.size / CHUNK_SIZE));
    const parts2 = Math.max(1, Math.ceil(file2.size / CHUNK_SIZE));

    const payload = {
      accion: "iniciar",
      sucursal: document.getElementById("sucursal").value,
      credito: document.getElementById("id_inspeccion").value.trim(),
      cliente: document.getElementById("cliente").value.trim(),
      ejecutivo: document.getElementById("ejecutivo").value.trim(),
      fechaDesem: document.getElementById("fechaDesem").value,
      file1Name: file1.name,
      file1Type: file1.type || "video/mp4",
      parts1,
      file2Name: file2.name,
      file2Type: file2.type || "video/mp4",
      parts2,
    };

    btnSubmit.disabled = true;
    btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>Preparando subida...</span>';

    try {
      // 1. Iniciar Multipart Upload y obtener URLs de fragmentos
      const dataInit = await postJson("api/geturl/multipartS3.php", payload);
      if (!dataInit.ok) {
        throw new Error(dataInit.error || "No se pudo iniciar la subida multiparte.");
      }

      // 2. Subir Video 1 y luego Video 2 (cada uno usando 4 hilos paralelos)
      btnSubmit.innerHTML = '<i class="fa-solid fa-cloud-arrow-up fa-bounce"></i><span>Subiendo Video 1 de 2...</span>';
      await uploadMultipartVideo(file1, dataInit.video1, "file1-progress");

      btnSubmit.innerHTML = '<i class="fa-solid fa-cloud-arrow-up fa-bounce"></i><span>Subiendo Video 2 de 2...</span>';
      await uploadMultipartVideo(file2, dataInit.video2, "file2-progress");

      // 3. Guardar registro en MySQL
      btnSubmit.innerHTML = '<i class="fa-solid fa-database fa-beat"></i><span>Guardando registro...</span>';
      const dataDb = await postJson("api/registro/index.php", {
        sucursal: payload.sucursal,
        credito: payload.credito,
        cliente: payload.cliente,
        ejecutivo: payload.ejecutivo,
        fechaDesem: payload.fechaDesem,
        ruta_video1: dataInit.video1.key,
        ruta_video2: dataInit.video2.key,
      });

      if (!dataDb.ok) {
        throw new Error(dataDb.error || "Los videos subieron, pero falló el guardado en MySQL.");
      }

      await Swal.fire("¡Éxito!", "Los videos se subieron rápidamente y el registro quedó guardado.", "success");

      form.reset();
      file1 = null;
      file2 = null;
      fileInputs.forEach((inp) => (inp.value = ""));
      document.getElementById("file1-name").textContent = "";
      document.getElementById("file2-name").textContent = "";
      document.getElementById("file1-progress").style.width = "0%";
      document.getElementById("file1-progress").textContent = "0%";
      document.getElementById("file2-progress").style.width = "0%";
      document.getElementById("file2-progress").textContent = "0%";
    } catch (err) {
      Swal.fire("Error", err.message, "error");
    } finally {
      btnSubmit.disabled = false;
      btnSubmit.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i><span>Enviar Videos</span>';
    }
  });
});
