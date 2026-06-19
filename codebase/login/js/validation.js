function validateLogin() {
  var username = document.getElementById("username").value.trim();
  var password = document.getElementById("password").value;
  var errorMessage = document.getElementById("clientError");

  if (username == "" || password == "") {
    errorMessage.innerHTML = "Please enter your username and password.";
    errorMessage.style.display = "block";
    return false;
  }

  return true;
}
