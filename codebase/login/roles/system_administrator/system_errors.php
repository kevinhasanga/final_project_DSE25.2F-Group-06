<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Admin');

$activePage = "errors";
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: system_errors.php");
        exit();
    }

    $action = $_POST["action"] ?? "";

    if ($action === "save") {
        $errorId = (int) ($_POST["error_id"] ?? 0);
        $errorDate = $_POST["error_date"];
        $errorType = trim($_POST["error_type"]);
        $errorMessage = trim($_POST["error_message"]);
        $status = $_POST["status"];

        if ($errorId > 0) {
            $statement = mysqli_prepare(
                $connection,
                "UPDATE system_error SET error_date = ?, error_type = ?, error_message = ?, status = ? WHERE error_id = ?"
            );
            mysqli_stmt_bind_param($statement, "ssssi", $errorDate, $errorType, $errorMessage, $status, $errorId);
        } else {
            $statement = mysqli_prepare(
                $connection,
                "INSERT INTO system_error (error_date, error_type, error_message, status) VALUES (?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($statement, "ssss", $errorDate, $errorType, $errorMessage, $status);
        }
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("System error record saved.");
        header("Location: system_errors.php");
        exit();
    }

    if ($action === "delete") {
        $errorId = (int) $_POST["error_id"];
        $statement = mysqli_prepare($connection, "DELETE FROM system_error WHERE error_id = ?");
        mysqli_stmt_bind_param($statement, "i", $errorId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("System error record deleted.");
        header("Location: system_errors.php");
        exit();
    }
}

$editRecord = null;
if (isset($_GET["edit"])) {
    $editId = (int) $_GET["edit"];
    $statement = mysqli_prepare($connection, "SELECT * FROM system_error WHERE error_id = ?");
    mysqli_stmt_bind_param($statement, "i", $editId);
    mysqli_stmt_execute($statement);
    $editRecord = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);
}

$perPage = 10;
$totalErrors = countRows($connection, "SELECT COUNT(*) FROM system_error");
$totalErrorPages = max(1, (int) ceil($totalErrors / $perPage));
$currentPage = min(getCurrentPage(), $totalErrorPages);
$offset = ($currentPage - 1) * $perPage;

$errors = mysqli_query($connection, "SELECT * FROM system_error ORDER BY error_date DESC LIMIT $perPage OFFSET $offset");
$statusOptions = ["open" => "Open", "checking" => "Checking", "resolved" => "Resolved"];
$statusClasses = ["open" => "pending", "checking" => "progress", "resolved" => "resolved"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>System Errors</title>
  <link rel="stylesheet" href="css/sa_style.css">
</head>
<body>
  <header class="topbar"><h1>System Administrator</h1><p>Monitor system errors</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>System Errors</h2><p>Record and monitor system error details.</p></section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3><?= $editRecord ? "Edit Error Record" : "Add Error Record" ?></h3>
        <form method="post" action="system_errors.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="error_id" value="<?= htmlspecialchars($editRecord["error_id"] ?? "") ?>">
          <div class="form-grid">
            <div class="form-group">
              <label for="errorDate">Error Date</label>
              <input type="datetime-local" id="errorDate" name="error_date" value="<?= htmlspecialchars($editRecord ? str_replace(" ", "T", substr($editRecord["error_date"], 0, 16)) : "") ?>" required>
            </div>
            <div class="form-group">
              <label for="errorType">Error Type</label>
              <input type="text" id="errorType" name="error_type" value="<?= htmlspecialchars($editRecord["error_type"] ?? "") ?>" required>
            </div>
            <div class="form-group">
              <label for="status">Status</label>
              <select id="status" name="status" required>
                <?php foreach ($statusOptions as $value => $label): ?>
                  <option value="<?= $value ?>" <?= ($editRecord["status"] ?? "open") === $value ? "selected" : "" ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group full-width">
              <label for="errorMessage">Error Message</label>
              <textarea id="errorMessage" name="error_message" required><?= htmlspecialchars($editRecord["error_message"] ?? "") ?></textarea>
            </div>
          </div>
          <div class="button-row">
            <button class="btn" type="submit">Save Error</button>
            <?php if ($editRecord): ?>
              <a class="btn secondary" href="system_errors.php">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </section>

      <section class="panel">
        <h3>Error Records</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Date</th><th>Type</th><th>Message</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($errors) === 0): ?>
                <tr><td colspan="5">No system errors loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($errors)): ?>
                <tr>
                  <td><?= htmlspecialchars($row["error_date"]) ?></td>
                  <td><?= htmlspecialchars($row["error_type"]) ?></td>
                  <td><?= htmlspecialchars($row["error_message"]) ?></td>
                  <td><span class="status <?= $statusClasses[$row["status"]] ?? "progress" ?>"><?= htmlspecialchars($statusOptions[$row["status"]] ?? $row["status"]) ?></span></td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;">
                      <a class="btn secondary" href="system_errors.php?edit=<?= $row["error_id"] ?>">Edit</a>
                      <form method="post" action="system_errors.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="error_id" value="<?= $row["error_id"] ?>">
                        <button class="btn danger" type="submit" onclick="return confirm('Delete this error record?');">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalErrors, $perPage); ?>
      </section>
    </main>
  </div>
  <script src="js/validate.js"></script>
</body>
</html>
