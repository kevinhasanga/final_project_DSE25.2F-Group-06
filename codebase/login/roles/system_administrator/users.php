<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Admin');

$activePage = "users";
$currentUserId = (int) $_SESSION["user_id"];
ensureCsrfToken();

$roleOptions = [
    "system_admin" => "System Administrator",
    "ceo_head_manager" => "CEO / Head Manager",
    "supervisor" => "Supervisor",
    "inventory_manager" => "Inventory Manager",
    "order_processing_officer" => "Order Processing Officer",
    "customer_relation_manager" => "Customer Relationship Officer",
    "distribution_manager" => "Distribution Manager",
    "driver" => "Driver",
    "financial_officer" => "Finance Officer",
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: users.php");
        exit();
    }

    $action = $_POST["action"] ?? "";

    if ($action === "save") {
        $userId = (int) ($_POST["user_id"] ?? 0);
        $fullName = trim($_POST["full_name"]);
        $nic = trim($_POST["nic"] ?? "");
        $contactNo = trim($_POST["contact_no"] ?? "");
        $email = trim($_POST["email"]);
        $username = trim($_POST["username"]);
        $role = $_POST["role"];
        $isActive = (int) ($_POST["is_active"] ?? 1);
        $newPassword = $_POST["new_password"] ?? "";

        if ($userId > 0) {
            if ($newPassword !== "") {
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $statement = mysqli_prepare($connection, "UPDATE user_account SET username = ?, email = ?, role_name = ?, is_active = ?, password_hash = ? WHERE user_id = ?");
                mysqli_stmt_bind_param($statement, "sssisi", $username, $email, $role, $isActive, $passwordHash, $userId);
                mysqli_stmt_execute($statement);
                mysqli_stmt_close($statement);
                logAudit($connection, $currentUserId, "reset password for user #$userId", "user_account", $userId);
            } else {
                $statement = mysqli_prepare($connection, "UPDATE user_account SET username = ?, email = ?, role_name = ?, is_active = ? WHERE user_id = ?");
                mysqli_stmt_bind_param($statement, "sssii", $username, $email, $role, $isActive, $userId);
                mysqli_stmt_execute($statement);
                mysqli_stmt_close($statement);
            }

            $statement = mysqli_prepare($connection, "UPDATE employee SET full_name = ?, contact_no = ? WHERE user_id = ?");
            mysqli_stmt_bind_param($statement, "ssi", $fullName, $contactNo, $userId);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);

            logAudit($connection, $currentUserId, "updated user account #$userId", "user_account", $userId);
            setFlash("User account updated.");
        } else {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $statement = mysqli_prepare(
                $connection,
                "INSERT INTO user_account (role_name, username, password_hash, email, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())"
            );
            mysqli_stmt_bind_param($statement, "ssss", $role, $username, $passwordHash, $email);
            mysqli_stmt_execute($statement);
            $newUserId = mysqli_insert_id($connection);
            mysqli_stmt_close($statement);

            $statement = mysqli_prepare(
                $connection,
                "INSERT INTO employee (user_id, full_name, nic, contact_no, job_title, hire_date, base_salary, employment_status)
                 VALUES (?, ?, ?, ?, ?, CURDATE(), 0, 'active')"
            );
            mysqli_stmt_bind_param($statement, "issss", $newUserId, $fullName, $nic, $contactNo, $role);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);

            logAudit($connection, $currentUserId, "created user account #$newUserId", "user_account", $newUserId);
            setFlash("User account created.");
        }

        header("Location: users.php");
        exit();
    }
}

$editRecord = null;
if (isset($_GET["edit"])) {
    $editId = (int) $_GET["edit"];
    $statement = mysqli_prepare(
        $connection,
        "SELECT ua.user_id, ua.username, ua.email, ua.role_name, ua.is_active, e.full_name, e.nic, e.contact_no
         FROM user_account ua LEFT JOIN employee e ON e.user_id = ua.user_id
         WHERE ua.user_id = ?"
    );
    mysqli_stmt_bind_param($statement, "i", $editId);
    mysqli_stmt_execute($statement);
    $editRecord = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);
}

$perPage = 10;
$totalUsers = countRows($connection, "SELECT COUNT(*) FROM user_account");
$totalUserPages = max(1, (int) ceil($totalUsers / $perPage));
$currentPage = min(getCurrentPage(), $totalUserPages);
$offset = ($currentPage - 1) * $perPage;

$users = mysqli_query(
    $connection,
    "SELECT ua.user_id, ua.username, ua.email, ua.role_name, ua.is_active, e.full_name
     FROM user_account ua LEFT JOIN employee e ON e.user_id = ua.user_id
     ORDER BY ua.user_id DESC
     LIMIT $perPage OFFSET $offset"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Users</title>
  <link rel="stylesheet" href="css/sa_style.css">
</head>
<body>
  <header class="topbar"><h1>System Administrator</h1><p>Create accounts, assign roles, update information, reset passwords, and manage status</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Users</h2><p>Manage user accounts: create, edit, assign roles, reset passwords, and activate or deactivate.</p></section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3><?= $editRecord ? "Edit User" : "Create User" ?></h3>
        <form method="post" action="users.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="user_id" value="<?= htmlspecialchars($editRecord["user_id"] ?? "") ?>">
          <div class="form-grid">
            <div class="form-group">
              <label for="fullName">Full Name</label>
              <input type="text" id="fullName" name="full_name" value="<?= htmlspecialchars($editRecord["full_name"] ?? "") ?>" required>
            </div>
            <?php if (!$editRecord): ?>
            <div class="form-group">
              <label for="nic">NIC</label>
              <input type="text" id="nic" name="nic" required>
            </div>
            <?php endif; ?>
            <div class="form-group">
              <label for="contactNo">Phone</label>
              <input type="tel" id="contactNo" name="contact_no" value="<?= htmlspecialchars($editRecord["contact_no"] ?? "") ?>">
            </div>
            <div class="form-group">
              <label for="username">Username</label>
              <input type="text" id="username" name="username" value="<?= htmlspecialchars($editRecord["username"] ?? "") ?>" required>
            </div>
            <div class="form-group">
              <label for="email">Email</label>
              <input type="email" id="email" name="email" value="<?= htmlspecialchars($editRecord["email"] ?? "") ?>" required>
            </div>
            <div class="form-group">
              <label for="role">Role</label>
              <select id="role" name="role" required>
                <option value="">Select role</option>
                <?php foreach ($roleOptions as $value => $label): ?>
                  <option value="<?= $value ?>" <?= ($editRecord["role_name"] ?? "") === $value ? "selected" : "" ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="newPassword"><?= $editRecord ? "Reset Password" : "Password" ?></label>
              <input type="password" id="newPassword" name="new_password" placeholder="<?= $editRecord ? "Leave blank to keep current password" : "" ?>" <?= $editRecord ? "" : "required" ?>>
            </div>
            <?php if ($editRecord): ?>
            <div class="form-group">
              <label for="isActive">Account Status</label>
              <select id="isActive" name="is_active">
                <option value="1" <?= $editRecord["is_active"] ? "selected" : "" ?>>Active</option>
                <option value="0" <?= !$editRecord["is_active"] ? "selected" : "" ?>>Inactive</option>
              </select>
            </div>
            <?php else: ?>
              <input type="hidden" name="is_active" value="1">
            <?php endif; ?>
          </div>
          <div class="button-row">
            <button class="btn" type="submit"><?= $editRecord ? "Save Changes" : "Create Account" ?></button>
            <?php if ($editRecord): ?>
              <a class="btn secondary" href="users.php">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </section>

      <section class="panel">
        <h3>User Accounts</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>User ID</th><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($users) === 0): ?>
                <tr><td colspan="7">No user accounts loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($users)): ?>
                <tr>
                  <td><?= $row["user_id"] ?></td>
                  <td><?= htmlspecialchars($row["full_name"] ?? "") ?></td>
                  <td><?= htmlspecialchars($row["username"]) ?></td>
                  <td><?= htmlspecialchars($row["email"]) ?></td>
                  <td><?= htmlspecialchars($roleOptions[$row["role_name"]] ?? $row["role_name"]) ?></td>
                  <td><span class="status <?= $row["is_active"] ? "resolved" : "pending" ?>"><?= $row["is_active"] ? "Active" : "Inactive" ?></span></td>
                  <td><a class="btn secondary" href="users.php?edit=<?= $row["user_id"] ?>">Edit</a></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalUsers, $perPage); ?>
      </section>
    </main>
  </div>
</body>
</html>
