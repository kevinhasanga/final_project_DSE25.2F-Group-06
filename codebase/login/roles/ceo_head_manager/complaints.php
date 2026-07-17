<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('CEO');

$activePage = "complaints";
$currentEmployeeId = getCurrentEmployeeId($connection, (int) $_SESSION["user_id"]);
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: complaints.php");
        exit();
    }

    $complaintId = (int) $_POST["complaint_id"];

    if (($_POST["action"] ?? "") === "delete") {
        $statement = mysqli_prepare($connection, "DELETE FROM complaint WHERE complaint_id = ?");
        mysqli_stmt_bind_param($statement, "i", $complaintId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Complaint deleted.");
        header("Location: complaints.php");
        exit();
    }

    $status = $_POST["status"];
    $response = trim($_POST["response"] ?? "");

    $currentStatement = mysqli_prepare($connection, "SELECT description FROM complaint WHERE complaint_id = ?");
    mysqli_stmt_bind_param($currentStatement, "i", $complaintId);
    mysqli_stmt_execute($currentStatement);
    $current = mysqli_fetch_assoc(mysqli_stmt_get_result($currentStatement));
    mysqli_stmt_close($currentStatement);

    $newDescription = $current["description"] ?? "";
    if ($response !== "") {
        $newDescription .= " | Management response: " . $response;
    }
    $resolvedDateExpression = $status === "resolved" ? "NOW()" : "NULL";

    $statement = mysqli_prepare(
        $connection,
        "UPDATE complaint SET status = ?, description = ?, resolved_date = $resolvedDateExpression WHERE complaint_id = ?"
    );
    mysqli_stmt_bind_param($statement, "ssi", $status, $newDescription, $complaintId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    setFlash("Complaint updated.");
    header("Location: complaints.php");
    exit();
}

$perPage = 10;
$totalComplaints = countRows(
    $connection,
    "SELECT COUNT(*) FROM complaint c WHERE c.escalated_to IS NOT NULL"
);
$totalComplaintPages = max(1, (int) ceil($totalComplaints / $perPage));
$currentPage = min(getCurrentPage(), $totalComplaintPages);
$offset = ($currentPage - 1) * $perPage;

$complaints = mysqli_query(
    $connection,
    "SELECT c.complaint_id, cu.name AS customer_name, c.description, c.status, c.created_date, c.resolved_date
     FROM complaint c
     JOIN customer cu ON cu.customer_id = c.customer_id
     WHERE c.escalated_to IS NOT NULL
     ORDER BY c.status = 'resolved', c.created_date DESC
     LIMIT $perPage OFFSET $offset"
);

$statusOptions = ["pending" => "Pending", "in_progress" => "In Progress", "resolved" => "Resolved"];
$statusClasses = ["pending" => "pending", "in_progress" => "progress", "resolved" => "resolved"];
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Escalated Complaints</title><link rel="stylesheet" href="css/ceo_style.css"></head>
<body>
  <header class="topbar"><h1>CEO / Head Manager</h1><p>Handle escalated complaints</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Escalated Complaints</h2><p>Review and resolve complaints escalated to management.</p></section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3>Escalated Complaint Records</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Customer</th><th>Description</th><th>Status</th><th>Created</th><th>Resolved</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($complaints) === 0): ?>
                <tr><td colspan="6">No escalated complaints loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($complaints)): ?>
                <tr>
                  <td><?= htmlspecialchars($row["customer_name"]) ?></td>
                  <td><?= htmlspecialchars($row["description"]) ?></td>
                  <td><span class="status <?= $statusClasses[$row["status"]] ?? "progress" ?>"><?= htmlspecialchars($statusOptions[$row["status"]] ?? $row["status"]) ?></span></td>
                  <td><?= htmlspecialchars($row["created_date"]) ?></td>
                  <td><?= htmlspecialchars($row["resolved_date"] ?? "—") ?></td>
                  <td>
                    <div style="display: flex; align-items: flex-start; gap: 6px; flex-wrap: wrap;">
                      <?php if ($row["status"] !== "resolved"): ?>
                        <details>
                          <summary class="btn secondary" style="display:inline-block; cursor:pointer;">Respond</summary>
                          <form method="post" action="complaints.php" style="margin-top: 10px;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                            <input type="hidden" name="complaint_id" value="<?= $row["complaint_id"] ?>">
                            <div class="form-grid">
                              <div class="form-group">
                                <label>Status</label>
                                <select name="status" required>
                                  <option value="in_progress" <?= $row["status"] === "in_progress" ? "selected" : "" ?>>In Progress</option>
                                  <option value="resolved">Resolved</option>
                                </select>
                              </div>
                              <div class="form-group full-width">
                                <label>Management Response</label>
                                <textarea name="response" required></textarea>
                              </div>
                            </div>
                            <div class="button-row"><button class="btn" type="submit">Save</button></div>
                          </form>
                        </details>
                      <?php endif; ?>
                      <form method="post" action="complaints.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="complaint_id" value="<?= $row["complaint_id"] ?>">
                        <button class="btn danger" type="submit" onclick="return confirm('Delete this complaint record? This cannot be undone.');">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalComplaints, $perPage); ?>
      </section>
    </main>
  </div>
  <script src="js/validate.js"></script>
</body>
</html>
