<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Supervisor');

$activePage = "performance";
$currentEmployeeId = getCurrentEmployeeId($connection, (int) $_SESSION["user_id"]);
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: performance.php");
        exit();
    }

    $action = $_POST["action"] ?? "";

    if ($action === "save") {
        $performanceId = (int) ($_POST["performance_id"] ?? 0);
        $employeeId = (int) $_POST["employee_id"];
        $reviewDate = $_POST["review_date"];
        $rating = max(1, min(5, (int) $_POST["rating"]));
        $status = $_POST["status"];
        $comments = trim($_POST["comments"] ?? "");

        if ($performanceId > 0) {
            $statement = mysqli_prepare(
                $connection,
                "UPDATE performance_review SET employee_id = ?, review_date = ?, rating = ?, status = ?, comments = ?
                 WHERE performance_id = ?"
            );
            mysqli_stmt_bind_param($statement, "isissi", $employeeId, $reviewDate, $rating, $status, $comments, $performanceId);
        } else {
            $statement = mysqli_prepare(
                $connection,
                "INSERT INTO performance_review (employee_id, reviewed_by, review_date, rating, status, comments)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($statement, "iisiss", $employeeId, $currentEmployeeId, $reviewDate, $rating, $status, $comments);
        }

        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Performance review saved.");
        header("Location: performance.php");
        exit();
    }

    if ($action === "delete") {
        $performanceId = (int) $_POST["performance_id"];
        $statement = mysqli_prepare($connection, "DELETE FROM performance_review WHERE performance_id = ?");
        mysqli_stmt_bind_param($statement, "i", $performanceId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Performance review deleted.");
        header("Location: performance.php");
        exit();
    }
}

$editRecord = null;
if (isset($_GET["edit"])) {
    $editId = (int) $_GET["edit"];
    $statement = mysqli_prepare($connection, "SELECT * FROM performance_review WHERE performance_id = ?");
    mysqli_stmt_bind_param($statement, "i", $editId);
    mysqli_stmt_execute($statement);
    $editRecord = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);
}

$employees = getAllEmployees($connection);

$perPage = 10;
$totalPerformanceRecords = countRows(
    $connection,
    "SELECT COUNT(*) FROM performance_review pr JOIN employee e ON e.employee_id = pr.employee_id"
);
$totalPerformancePages = max(1, (int) ceil($totalPerformanceRecords / $perPage));
$currentPage = min(getCurrentPage(), $totalPerformancePages);
$offset = ($currentPage - 1) * $perPage;

$performanceRecords = mysqli_query(
    $connection,
    "SELECT pr.performance_id, pr.employee_id, e.full_name, pr.review_date, pr.rating, pr.status, pr.comments
     FROM performance_review pr
     JOIN employee e ON e.employee_id = pr.employee_id
     ORDER BY pr.review_date DESC
     LIMIT $perPage OFFSET $offset"
);

$statusClasses = [
    "excellent" => "resolved",
    "good" => "resolved",
    "average" => "progress",
    "needs_improvement" => "pending",
];
$statusLabels = [
    "excellent" => "Excellent",
    "good" => "Good",
    "average" => "Average",
    "needs_improvement" => "Needs Improvement",
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Performance</title>
  <link rel="stylesheet" href="css/supervisor_style.css">
</head>
<body>
  <header class="topbar"><h1>Supervisor</h1><p>Track employee performance</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Performance</h2><p>Record employee performance ratings and comments.</p></section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3><?= $editRecord ? "Edit Performance Review" : "Add Performance Review" ?></h3>
        <form method="post" action="performance.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="performance_id" value="<?= htmlspecialchars($editRecord["performance_id"] ?? "") ?>">
          <div class="form-grid">
            <div class="form-group">
              <label for="employeeId">Employee</label>
              <select id="employeeId" name="employee_id" required>
                <option value="">Select employee</option>
                <?php foreach ($employees as $employee): ?>
                  <option value="<?= $employee["employee_id"] ?>" <?= ($editRecord["employee_id"] ?? null) == $employee["employee_id"] ? "selected" : "" ?>>
                    <?= htmlspecialchars($employee["full_name"]) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="reviewDate">Review Date</label>
              <input type="date" id="reviewDate" name="review_date" value="<?= htmlspecialchars($editRecord["review_date"] ?? "") ?>" required>
            </div>
            <div class="form-group">
              <label for="rating">Rating (1-5)</label>
              <input type="number" id="rating" name="rating" min="1" max="5" value="<?= htmlspecialchars($editRecord["rating"] ?? "") ?>" required>
            </div>
            <div class="form-group">
              <label for="status">Status</label>
              <select id="status" name="status" required>
                <option value="">Select status</option>
                <?php foreach ($statusLabels as $value => $label): ?>
                  <option value="<?= $value ?>" <?= ($editRecord["status"] ?? "") === $value ? "selected" : "" ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group full-width">
              <label for="comments">Comments</label>
              <textarea id="comments" name="comments"><?= htmlspecialchars($editRecord["comments"] ?? "") ?></textarea>
            </div>
          </div>
          <div class="button-row">
            <button class="btn" type="submit">Save Performance</button>
            <?php if ($editRecord): ?>
              <a class="btn secondary" href="performance.php">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </section>

      <section class="panel">
        <h3>Performance Records</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Employee</th><th>Review Date</th><th>Rating</th><th>Status</th><th>Comments</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($performanceRecords) === 0): ?>
                <tr><td colspan="6">No performance records loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($performanceRecords)): ?>
                <tr>
                  <td><?= htmlspecialchars($row["full_name"]) ?></td>
                  <td><?= htmlspecialchars($row["review_date"]) ?></td>
                  <td><?= (int) $row["rating"] ?>/5</td>
                  <td><span class="status <?= $statusClasses[$row["status"]] ?? "progress" ?>"><?= htmlspecialchars($statusLabels[$row["status"]] ?? $row["status"]) ?></span></td>
                  <td><?= htmlspecialchars($row["comments"] ?? "") ?></td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;">
                      <a class="btn secondary" href="performance.php?edit=<?= $row["performance_id"] ?>">Edit</a>
                      <form method="post" action="performance.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="performance_id" value="<?= $row["performance_id"] ?>">
                        <button class="btn danger" type="submit" onclick="return confirm('Delete this performance review?');">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalPerformanceRecords, $perPage); ?>
      </section>
    </main>
  </div>
  <script src="js/validate.js"></script>
</body>
</html>
