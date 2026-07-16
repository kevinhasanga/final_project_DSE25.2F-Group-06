<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/helpers.php';
require_login('CEO');

$activePage = "profile";
$currentUserId = (int) $_SESSION["user_id"];
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: profile.php");
        exit();
    }

    $fullName = trim($_POST["full_name"]);
    $email = trim($_POST["email"]);
    $contactNo = trim($_POST["contact_no"] ?? "");
    $username = trim($_POST["username"]);
    $newPassword = $_POST["new_password"] ?? "";

    $statement = mysqli_prepare($connection, "UPDATE employee SET full_name = ?, contact_no = ? WHERE user_id = ?");
    mysqli_stmt_bind_param($statement, "ssi", $fullName, $contactNo, $currentUserId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    if ($newPassword !== "") {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $statement = mysqli_prepare($connection, "UPDATE user_account SET username = ?, email = ?, password_hash = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($statement, "sssi", $username, $email, $passwordHash, $currentUserId);
    } else {
        $statement = mysqli_prepare($connection, "UPDATE user_account SET username = ?, email = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($statement, "ssi", $username, $email, $currentUserId);
    }
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    $_SESSION["full_name"] = $fullName;
    $_SESSION["username"] = $username;

    setFlash("Profile updated.");
    header("Location: profile.php");
    exit();
}

$statement = mysqli_prepare(
    $connection,
    "SELECT e.employee_id, e.full_name, e.contact_no, ua.username, ua.email
     FROM employee e JOIN user_account ua ON ua.user_id = e.user_id
     WHERE e.user_id = ?"
);
mysqli_stmt_bind_param($statement, "i", $currentUserId);
mysqli_stmt_execute($statement);
$profile = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
mysqli_stmt_close($statement);

$loginStatement = mysqli_prepare(
    $connection,
    "SELECT login_id, login_time, logout_time FROM login_history WHERE user_id = ? ORDER BY login_time DESC LIMIT 10"
);
mysqli_stmt_bind_param($loginStatement, "i", $currentUserId);
mysqli_stmt_execute($loginStatement);
$loginHistory = mysqli_stmt_get_result($loginStatement);
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Profile</title><link rel="stylesheet" href="css/ceo_style.css"></head>
<body>
  <header class="topbar"><h1>CEO / Head Manager</h1><p>Secure login and profile management</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Login and Profile Management</h2><p>Update your profile and login details.</p></section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3>Profile Details</h3>
        <form method="post" action="profile.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <div class="form-grid">
            <div class="form-group">
              <label for="fullName">Full Name</label>
              <input type="text" id="fullName" name="full_name" value="<?= htmlspecialchars($profile["full_name"]) ?>" required>
            </div>
            <div class="form-group">
              <label for="email">Email</label>
              <input type="email" id="email" name="email" value="<?= htmlspecialchars($profile["email"]) ?>" required>
            </div>
            <div class="form-group">
              <label for="contactNo">Phone</label>
              <input type="tel" id="contactNo" name="contact_no" value="<?= htmlspecialchars($profile["contact_no"]) ?>">
            </div>
            <div class="form-group">
              <label for="username">Username</label>
              <input type="text" id="username" name="username" value="<?= htmlspecialchars($profile["username"]) ?>" required>
            </div>
            <div class="form-group">
              <label for="newPassword">New Password</label>
              <input type="password" id="newPassword" name="new_password" placeholder="Leave blank to keep current password">
            </div>
          </div>
          <div class="button-row"><button class="btn" type="submit">Update Profile</button></div>
        </form>
      </section>

      <section class="panel">
        <h3>Recent Login Records</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Login ID</th><th>Login Time</th><th>Logout Time</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($loginHistory) === 0): ?>
                <tr><td colspan="3">No login records loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($loginHistory)): ?>
                <tr>
                  <td><?= $row["login_id"] ?></td>
                  <td><?= htmlspecialchars($row["login_time"]) ?></td>
                  <td><?= htmlspecialchars($row["logout_time"] ?? "—") ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
</body>
</html>
