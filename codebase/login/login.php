<?php
require_once "auth.php";
require_once "config.php";

$error = "";
$username = "";

if (isset($_SESSION["role"]) && isSessionIdleExpired()) {
    endIdleSession();
}

if (isset($_GET["timeout"])) {
    $error = "You were logged out after 2 minutes of inactivity. Please log in again.";
}

// If the user is already logged in, send them back to their dashboard.
if (isset($_SESSION["role"])) {
    $dashboard = getDashboard($_SESSION["role"]);

    if ($dashboard != "") {
        header("Location: " . $dashboard);
        exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = $_POST["password"];

    if ($username == "" || $password == "") {
        $error = "Please enter your username and password.";
    } else {
        // A prepared statement safely checks the entered username.
        $sql = "SELECT ua.user_id, ua.username, ua.password_hash,
                       ua.role_name AS role, ua.is_active, e.full_name
                FROM user_account ua
                LEFT JOIN employee e ON e.user_id = ua.user_id
                WHERE ua.username = ?";

        $statement = mysqli_prepare($connection, $sql);
        mysqli_stmt_bind_param($statement, "s", $username);
        mysqli_stmt_execute($statement);
        $result = mysqli_stmt_get_result($statement);
        $user = mysqli_fetch_assoc($result);

        if (
            $user &&
            $user["is_active"] == 1 &&
            password_verify($password, $user["password_hash"])
        ) {
            $_SESSION["user_id"] = $user["user_id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["full_name"] = $user["full_name"] ?: $user["username"];
            $_SESSION["role"] = $user["role"];
            $_SESSION["last_activity"] = time();

            $loginStatement = mysqli_prepare($connection, "INSERT INTO login_history (user_id, login_time) VALUES (?, NOW())");
            mysqli_stmt_bind_param($loginStatement, "i", $user["user_id"]);
            mysqli_stmt_execute($loginStatement);
            $_SESSION["login_id"] = mysqli_insert_id($connection);
            mysqli_stmt_close($loginStatement);

            $dashboard = getDashboard($user["role"]);

            if ($dashboard != "") {
                header("Location: " . $dashboard);
                exit();
            } else {
                $error = "Dashboard not found for this user role.";
            }
        } else {
            $error = "Invalid username or password.";
        }

        mysqli_stmt_close($statement);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | Natura Crop Care</title>
  <link rel="stylesheet" href="css/login.css">
</head>
<body>
  <main class="login-box">
    <img class="company-logo" src="images/ncc_logo.png" alt="Natura Crop Care logo">
    <h1>Natura Crop Care</h1>
    <p class="company-short-name">NCC</p>

    <?php if ($error != ""): ?>
      <p class="error-message"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <p class="error-message" id="clientError" style="display: none;"></p>

    <form method="post" action="login.php" onsubmit="return validateLogin()">
      <label for="username">Username</label>
      <input
        type="text"
        id="username"
        name="username"
        maxlength="50"
        autocomplete="username"
        value="<?= htmlspecialchars($username) ?>"
        autofocus
      >

      <label for="password">Password</label>
      <input
        type="password"
        id="password"
        name="password"
        autocomplete="current-password"
      >

      <button type="submit">Log in</button>
    </form>
  </main>

  <script src="js/validation.js"></script>
</body>
</html>
