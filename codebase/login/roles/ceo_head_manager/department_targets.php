<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('CEO');

$activePage = "targets";
$currentEmployeeId = getCurrentEmployeeId($connection, (int) $_SESSION["user_id"]);
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: department_targets.php");
        exit();
    }

    $action = $_POST["action"] ?? "";

    if ($action === "save") {
        $targetId = (int) ($_POST["target_id"] ?? 0);
        $department = trim($_POST["department"]);
        $targetType = trim($_POST["target_type"]);
        $targetValue = (float) $_POST["target_value"];
        $deadline = $_POST["deadline"];
        $status = $_POST["status"];

        if ($targetId > 0) {
            $statement = mysqli_prepare(
                $connection,
                "UPDATE department_target SET department = ?, target_type = ?, target_value = ?, deadline = ?, status = ?
                 WHERE target_id = ?"
            );
            mysqli_stmt_bind_param($statement, "ssdssi", $department, $targetType, $targetValue, $deadline, $status, $targetId);
        } else {
            $statement = mysqli_prepare(
                $connection,
                "INSERT INTO department_target (department, target_type, target_value, deadline, status, assigned_by)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($statement, "ssdssi", $department, $targetType, $targetValue, $deadline, $status, $currentEmployeeId);
        }
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Department target saved.");
        header("Location: department_targets.php");
        exit();
    }

    if ($action === "delete") {
        $targetId = (int) $_POST["target_id"];
        $statement = mysqli_prepare($connection, "DELETE FROM department_target WHERE target_id = ?");
        mysqli_stmt_bind_param($statement, "i", $targetId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Department target deleted.");
        header("Location: department_targets.php");
        exit();
    }
}

$editRecord = null;
if (isset($_GET["edit"])) {
    $editId = (int) $_GET["edit"];
    $statement = mysqli_prepare($connection, "SELECT * FROM department_target WHERE target_id = ?");
    mysqli_stmt_bind_param($statement, "i", $editId);
    mysqli_stmt_execute($statement);
    $editRecord = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);
}

$perPage = 10;
$totalTargets = countRows($connection, "SELECT COUNT(*) FROM department_target");
$totalTargetPages = max(1, (int) ceil($totalTargets / $perPage));
$currentPage = min(getCurrentPage(), $totalTargetPages);
$offset = ($currentPage - 1) * $perPage;

$targets = mysqli_query($connection, "SELECT * FROM department_target ORDER BY deadline ASC LIMIT $perPage OFFSET $offset");
$statusClasses = ["active" => "progress", "completed" => "resolved", "cancelled" => "pending"];
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Department Targets</title><link rel="stylesheet" href="css/ceo_style.css"></head>
<body>
  <header class="topbar"><h1>CEO / Head Manager</h1><p>Assign departmental targets</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Departmental Targets</h2><p>Assign performance, revenue, or operational targets to departments.</p></section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3><?= $editRecord ? "Edit Target" : "Add Target" ?></h3>
        <form method="post" action="department_targets.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="target_id" value="<?= htmlspecialchars($editRecord["target_id"] ?? "") ?>">
          <div class="form-grid">
            <div class="form-group">
              <label for="department">Department</label>
              <input type="text" id="department" name="department" value="<?= htmlspecialchars($editRecord["department"] ?? "") ?>" required>
            </div>
            <div class="form-group">
              <label for="targetType">Target Type</label>
              <input type="text" id="targetType" name="target_type" value="<?= htmlspecialchars($editRecord["target_type"] ?? "") ?>" placeholder="e.g. Revenue, Deliveries, Complaints Resolved" required>
            </div>
            <div class="form-group">
              <label for="targetValue">Target Value</label>
              <input type="number" id="targetValue" name="target_value" min="0" step="0.01" value="<?= htmlspecialchars($editRecord["target_value"] ?? "") ?>" required>
            </div>
            <div class="form-group">
              <label for="deadline">Deadline</label>
              <input type="date" id="deadline" name="deadline" value="<?= htmlspecialchars($editRecord["deadline"] ?? "") ?>" required>
            </div>
            <div class="form-group">
              <label for="status">Status</label>
              <select id="status" name="status" required>
                <option value="active" <?= ($editRecord["status"] ?? "active") === "active" ? "selected" : "" ?>>Active</option>
                <option value="completed" <?= ($editRecord["status"] ?? "") === "completed" ? "selected" : "" ?>>Completed</option>
                <option value="cancelled" <?= ($editRecord["status"] ?? "") === "cancelled" ? "selected" : "" ?>>Cancelled</option>
              </select>
            </div>
          </div>
          <div class="button-row">
            <button class="btn" type="submit">Save Target</button>
            <?php if ($editRecord): ?>
              <a class="btn secondary" href="department_targets.php">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </section>

      <section class="panel">
        <h3>Assigned Targets</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Department</th><th>Type</th><th>Value</th><th>Deadline</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($targets) === 0): ?>
                <tr><td colspan="6">No departmental targets loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($targets)): ?>
                <tr>
                  <td><?= htmlspecialchars($row["department"]) ?></td>
                  <td><?= htmlspecialchars($row["target_type"]) ?></td>
                  <td><?= number_format($row["target_value"], 2) ?></td>
                  <td><?= htmlspecialchars($row["deadline"]) ?></td>
                  <td><span class="status <?= $statusClasses[$row["status"]] ?? "progress" ?>"><?= htmlspecialchars(ucfirst($row["status"])) ?></span></td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;">
                      <a class="btn secondary" href="department_targets.php?edit=<?= $row["target_id"] ?>">Edit</a>
                      <form method="post" action="department_targets.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="target_id" value="<?= $row["target_id"] ?>">
                        <button class="btn danger" type="submit" onclick="return confirm('Delete this target?');">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalTargets, $perPage); ?>
      </section>
    </main>
  </div>
  <script src="js/validate.js"></script>
</body>
</html>
