$(document).on("click", ".btn-video", function () {
  const id = $(this).data("id");
  const tipo = $(this).data("tipo");

  const video = document.getElementById("videoPlayer");

  video.src = `stream.php?id=${id}&tipo=${tipo}`;

  const modal = new bootstrap.Modal(document.getElementById("videoModal"));

  modal.show();
});

document.getElementById("videoModal").addEventListener("hidden.bs.modal", function () {
  const video = document.getElementById("videoPlayer");

  video.pause();

  video.removeAttribute("src");

  video.load();
});
