<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/helpers.php';
require_login('Admin');

$activePage = "privileges";
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
        header("Location: access_privileges.php");
        exit();
    }

    $action = $_POST["action"] ?? "";

    if ($action === "save") {
        $privilegeId = (int) ($_POST["privilege_id"] ?? 0);
        $roleName = $_POST["role_name"];
        $moduleName = trim($_POST["module_name"]);
        $accessLevel = $_POST["access_level"];
        $status = $_POST["status"];

        if ($privilegeId > 0) {
            $statement = mysqli_prepare(
                $connection,
                "UPDATE access_privilege SET role_name = ?, module_name = ?, access_level = ?, status = ? WHERE privilege_id = ?"
            );
            mysqli_stmt_bind_param($statement, "ssssi", $roleName, $moduleName, $accessLevel, $status, $privilegeId);
        } else {
            $statement = mysqli_prepare(
                $connection,
                "INSERT INTO access_privilege (role_name, module_name, access_level, status) VALUES (?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($statement, "ssss", $roleName, $moduleName, $accessLevel, $status);
        }
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        logAudit($connection, $currentUserId, "saved access privilege for role $roleName", "access_privilege", $privilegeId ?: mysqli_insert_id($connection));
        setFlash("Access privilege saved.");
        header("Location: access_privileges.php");
        exit();
    }

    if ($action === "delete") {
        $privilegeId = (int) $_POST["privilege_id"];
        $statement = mysqli_prepare($connection, "DELETE FROM access_privilege WHERE privilege_id = ?");
        mysqli_stmt_bind_param($statement, "i", $privilegeId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Access privilege deleted.");
        header("Location: access_privileges.php");
        exit();
    }
}

$editRecord = null;
if (isset($_GET["edit"])) {
    $editId = (int) $_GET["edit"];
    $statement = mysqli_prepare($connection, "SELECT * FROM access_privilege WHERE privilege_id = ?");
    mysqli_stmt_bind_param($statement, "i", $editId);
    mysqli_stmt_execute($statement);
    $editRecord = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);
}

$privileges = mysqli_query($connection, "SELECT * FROM access_privilege ORDER BY role_name, module_name");
$accessLevelOptions = ["view" => "View", "create" => "Create", "update" => "Update", "delete" => "Delete", "full" => "Full Access"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Access Privileges</title>
  <link rel="stylesheet" href="css/sa_style.css">
</head>
<body>
  <header class="topbar"><h1>System Administrator</h1><p>Define access privileges</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Access Privileges</h2><p>Set module permissions for each role.</p></section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3><?= $editRecord ? "Edit Privilege" : "Add Privilege" ?></h3>
        <form method="post" action="access_privileges.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="privilege_id" value="<?= htmlspecialchars($editRecord["privilege_id"] ?? "") ?>">
          <div class="form-grid">
            <div class="form-group">
              <label for="roleName">Role</label>
              <select id="roleName" name="role_name" required>
                <option value="">Select role</option>
                <?php foreach ($roleOptions as $value => $label): ?>
                  <option value="<?= $value ?>" <?= ($editRecord["role_name"] ?? "") === $value ? "selected" : "" ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="moduleName">Module Name</label>
              <input type="text" id="moduleName" name="module_name" value="<?= htmlspecialchars($editRecord["module_name"] ?? "") ?>" placeholder="e.g. Sales Orders, Payroll" required>
            </div>
            <div class="form-group">
              <label for="accessLevel">Access Level</label>
              <select id="accessLevel" name="access_level" required>
                <option value="">Select access</option>
                <?php foreach ($accessLevelOptions as $value => $label): ?>
                  <option value="<?= $value ?>" <?= ($editRecord["access_level"] ?? "") === $value ? "selected" : "" ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="status">Status</label>
              <select id="status" name="status" required>
                <option value="active" <?= ($editRecord["status"] ?? "active") === "active" ? "selected" : "" ?>>Active</option>
                <option value="inactive" <?= ($editRecord["status"] ?? "") === "inactive" ? "selected" : "" ?>>Inactive</option>
              </select>
            </div>
          </div>
          <div class="button-row">
            <button class="btn" type="submit">Save Privilege</button>
            <?php if ($editRecord): ?>
              <a class="btn secondary" href="access_privileges.php">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </section>

      <section class="panel">
        <h3>Privilege Records</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Role</th><th>Module</th><th>Access Level</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($privileges) === 0): ?>
                <tr><td colspan="5">No access privileges loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($privileges)): ?>
                <tr>
                  <td><?= htmlspecialchars($roleOptions[$row["role_name"]] ?? $row["role_name"]) ?></td>
                  <td><?= htmlspecialchars($row["module_name"]) ?></td>
                  <td><?= htmlspecialchars($accessLevelOptions[$row["access_level"]] ?? $row["access_level"]) ?></td>
                  <td><span class="status <?= $row["status"] === "active" ? "resolved" : "pending" ?>"><?= htmlspecialchars(ucfirst($row["status"])) ?></span></td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;">
                      <a class="btn secondary" href="access_privileges.php?edit=<?= $row["privilege_id"] ?>">Edit</a>
                      <form method="post" action="access_privileges.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="privilege_id" value="<?= $row["privilege_id"] ?>">
                        <button class="btn danger" type="submit" onclick="return confirm('Delete this privilege?');">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
  <script src="js/validate.js"></script>
</body>
</html>
