document.addEventListener("DOMContentLoaded", function () {
  const buttons = document.querySelectorAll(".cleanseo-send-to-ai");
  buttons.forEach(function (btn) {
    btn.addEventListener("click", function () {
      const postId = this.dataset.postId;
      const field = this.dataset.field;

      if (!confirm(`Czy na pewno chcesz wygenerować: ${field}?`)) return;

      fetch(ajaxurl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
          action: "cleanseo_generate_prompt",
          _ajax_nonce: cleanseo_ai_vars.nonce,
          post_id: postId,
          field: field,
        }),
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.success) {
            alert("Wygenerowano:

" + data.data);
          } else {
            alert("Błąd: " + (data.data || "Nie udało się wykonać zadania."));
          }
        })
        .catch(() => alert("Błąd sieci."));
    });
  });
});
