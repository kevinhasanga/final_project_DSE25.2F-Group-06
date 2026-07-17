(function () {
  "use strict";

  function showError(field, message) {
    clearError(field);
    var msg = document.createElement("div");
    msg.className = "field-error";
    msg.textContent = message;
    msg.style.color = "#d71920";
    msg.style.fontSize = "12px";
    msg.style.marginTop = "4px";
    field.insertAdjacentElement("afterend", msg);
    field.style.borderColor = "#d71920";
  }

  function clearError(field) {
    field.style.borderColor = "";
    var next = field.nextElementSibling;
    if (next && next.classList && next.classList.contains("field-error")) {
      next.parentNode.removeChild(next);
    }
  }

  function isBlank(value) {
    return value === null || value.trim() === "";
  }

  function validateField(field) {
    if (field.disabled || field.type === "hidden" || field.type === "submit" || field.type === "button") {
      return true;
    }

    clearError(field);

    if (field.required) {
      if (field.type === "checkbox" || field.type === "radio") {
        var group = field.form.querySelectorAll('input[name="' + field.name + '"]');
        var checked = Array.prototype.some.call(group, function (el) {
          return el.checked;
        });
        if (!checked) {
          showError(field, "This field is required.");
          return false;
        }
      } else if (field.type === "file") {
        if (!field.files || field.files.length === 0) {
          showError(field, "Please choose a file.");
          return false;
        }
      } else if (isBlank(field.value)) {
        showError(field, "This field is required.");
        return false;
      }
    }

    if (field.value && field.value.trim() !== "") {
      if (field.type === "email") {
        var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailPattern.test(field.value.trim())) {
          showError(field, "Enter a valid email address.");
          return false;
        }
      }

      if (field.type === "number") {
        var num = parseFloat(field.value);
        if (isNaN(num)) {
          showError(field, "Enter a valid number.");
          return false;
        }
        if (field.min !== "" && num < parseFloat(field.min)) {
          showError(field, "Value must be at least " + field.min + ".");
          return false;
        }
        if (field.max !== "" && num > parseFloat(field.max)) {
          showError(field, "Value must be at most " + field.max + ".");
          return false;
        }
      }

      if (field.pattern) {
        var re = new RegExp("^(?:" + field.pattern + ")$");
        if (!re.test(field.value.trim())) {
          showError(field, field.title || "Please match the requested format.");
          return false;
        }
      }

      if (field.minLength && field.minLength > 0 && field.value.length < field.minLength) {
        showError(field, "Must be at least " + field.minLength + " characters.");
        return false;
      }
    }

    return true;
  }

  function validateDatePair(field) {
    var otherSelector = field.getAttribute("data-after");
    if (!otherSelector) {
      return true;
    }
    var other = field.form.querySelector(otherSelector);
    if (!other || !other.value || !field.value) {
      return true;
    }
    if (field.value < other.value) {
      showError(field, "This must be the same as or after the starting value.");
      return false;
    }
    return true;
  }

  function validateForm(form) {
    var valid = true;
    var fields = form.querySelectorAll("input, select, textarea");
    Array.prototype.forEach.call(fields, function (field) {
      var fieldOk = validateField(field);
      var pairOk = validateDatePair(field);
      if (!fieldOk || !pairOk) {
        valid = false;
      }
    });
    return valid;
  }

  window.simpleValidate = validateForm;

  document.addEventListener("DOMContentLoaded", function () {
    var forms = document.querySelectorAll("form:not([data-no-validate])");
    Array.prototype.forEach.call(forms, function (form) {
      form.addEventListener("submit", function (event) {
        if (!validateForm(form)) {
          event.preventDefault();
          var firstInvalid = form.querySelector('[style*="border-color"]');
          if (firstInvalid) {
            firstInvalid.focus();
          }
        }
      });
    });
  });
})();
