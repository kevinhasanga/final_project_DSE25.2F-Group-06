document.addEventListener("DOMContentLoaded", function () {
  var form = document.getElementById("reportFilterForm");
  if (!form) {
    return;
  }

  form.addEventListener("submit", function (event) {
    event.preventDefault();

    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }

    if (typeof window.simpleValidate === "function" && !window.simpleValidate(form)) {
      return;
    }

    var params = new URLSearchParams(new FormData(form));
    params.set("print", "1");
    window.open(form.getAttribute("action") + "?" + params.toString(), "_blank");
  });
});
