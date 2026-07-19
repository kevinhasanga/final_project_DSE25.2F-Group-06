<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Supervisor');

$activePage = "leave";
$currentEmployeeId = getCurrentEmployeeId($connection, (int) $_SESSION["user_id"]);
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: leave_requests.php");
        exit();
    }

    $action = $_POST["action"] ?? "";

    if ($action === "save") {
        $leaveId = (int) ($_POST["leave_id"] ?? 0);
        $employeeId = (int) $_POST["employee_id"];
        $startDate = $_POST["start_date"];
        $endDate = $_POST["end_date"];
        $reason = trim($_POST["reason"] ?? "");
        $status = $_POST["status"];
        $approvedBy = $status !== "pending" ? $currentEmployeeId : null;

        if ($leaveId > 0) {
            $statement = mysqli_prepare(
                $connection,
                "UPDATE leave_request SET employee_id = ?, start_date = ?, end_date = ?, reason = ?, status = ?, approved_by = ?
                 WHERE leave_id = ?"
            );
            mysqli_stmt_bind_param($statement, "isssssi", $employeeId, $startDate, $endDate, $reason, $status, $approvedBy, $leaveId);
        } else {
            $statement = mysqli_prepare(
                $connection,
                "INSERT INTO leave_request (employee_id, approved_by, start_date, end_date, reason, status, requested_date)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())"
            );
            mysqli_stmt_bind_param($statement, "iissss", $employeeId, $approvedBy, $startDate, $endDate, $reason, $status);
        }

        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Leave request saved.");
        header("Location: leave_requests.php");
        exit();
    }

    if ($action === "decide") {
        $leaveId = (int) $_POST["leave_id"];
        $status = $_POST["status"];
        $statement = mysqli_prepare(
            $connection,
            "UPDATE leave_request SET status = ?, approved_by = ? WHERE leave_id = ?"
        );
        mysqli_stmt_bind_param($statement, "sii", $status, $currentEmployeeId, $leaveId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Leave request " . htmlspecialchars($status) . ".");
        header("Location: leave_requests.php");
        exit();
    }

    if ($action === "delete") {
        $leaveId = (int) $_POST["leave_id"];
        $statement = mysqli_prepare($connection, "DELETE FROM leave_request WHERE leave_id = ?");
        mysqli_stmt_bind_param($statement, "i", $leaveId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Leave request deleted.");
        header("Location: leave_requests.php");
        exit();
    }
}

$editRecord = null;
if (isset($_GET["edit"])) {
    $editId = (int) $_GET["edit"];
    $statement = mysqli_prepare($connection, "SELECT * FROM leave_request WHERE leave_id = ?");
    mysqli_stmt_bind_param($statement, "i", $editId);
    mysqli_stmt_execute($statement);
    $editRecord = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);
}

$employees = getAllEmployees($connection);

$perPage = 5;
$totalLeaveRequests = countRows(
    $connection,
    "SELECT COUNT(*) FROM leave_request lr JOIN employee e ON e.employee_id = lr.employee_id"
);
$totalLeavePages = max(1, (int) ceil($totalLeaveRequests / $perPage));
$currentPage = min(getCurrentPage(), $totalLeavePages);
$offset = ($currentPage - 1) * $perPage;

$leaveRequests = mysqli_query(
    $connection,
    "SELECT lr.leave_id, lr.employee_id, e.full_name, lr.start_date, lr.end_date, lr.reason, lr.status
     FROM leave_request lr
     JOIN employee e ON e.employee_id = lr.employee_id
     ORDER BY lr.status = 'pending' DESC, lr.requested_date DESC
     LIMIT $perPage OFFSET $offset"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Leave Requests</title>
  <link rel="stylesheet" href="css/supervisor_style.css">
</head>
<body>
  <header class="topbar"><h1>Supervisor</h1><p>Approve leave requests</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Leave Requests</h2><p>Record and approve or reject employee leave requests.</p></section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3><?= $editRecord ? "Edit Leave Request" : "Add Leave Request" ?></h3>
        <form method="post" action="leave_requests.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="leave_id" value="<?= htmlspecialchars($editRecord["leave_id"] ?? "") ?>">
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
              <label for="startDate">From Date</label>
              <input type="date" id="startDate" name="start_date" value="<?= htmlspecialchars($editRecord["start_date"] ?? "") ?>" required>
            </div>
            <div class="form-group">
              <label for="endDate">To Date</label>
              <input type="date" id="endDate" name="end_date" value="<?= htmlspecialchars($editRecord["end_date"] ?? "") ?>" required data-after="#startDate">
            </div>
            <div class="form-group">
              <label for="status">Status</label>
              <select id="status" name="status" required>
                <option value="pending" <?= ($editRecord["status"] ?? "pending") === "pending" ? "selected" : "" ?>>Pending</option>
                <option value="approved" <?= ($editRecord["status"] ?? "") === "approved" ? "selected" : "" ?>>Approved</option>
                <option value="rejected" <?= ($editRecord["status"] ?? "") === "rejected" ? "selected" : "" ?>>Rejected</option>
              </select>
            </div>
            <div class="form-group full-width">
              <label for="reason">Reason</label>
              <textarea id="reason" name="reason"><?= htmlspecialchars($editRecord["reason"] ?? "") ?></textarea>
            </div>
          </div>
          <div class="button-row">
            <button class="btn" type="submit">Save Request</button>
            <?php if ($editRecord): ?>
              <a class="btn secondary" href="leave_requests.php">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </section>

      <section class="panel">
        <h3>Leave Requests</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Employee</th><th>From</th><th>To</th><th>Reason</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($leaveRequests) === 0): ?>
                <tr><td colspan="6">No leave requests loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($leaveRequests)): ?>
                <tr>
                  <td><?= htmlspecialchars($row["full_name"]) ?></td>
                  <td><?= htmlspecialchars($row["start_date"]) ?></td>
                  <td><?= htmlspecialchars($row["end_date"]) ?></td>
                  <td><?= htmlspecialchars($row["reason"] ?? "") ?></td>
                  <td>
                    <span class="status <?= $row["status"] === "approved" ? "resolved" : ($row["status"] === "rejected" ? "pending" : "progress") ?>">
                      <?= htmlspecialchars(ucfirst($row["status"])) ?>
                    </span>
                  </td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;">
                      <a class="btn secondary" href="leave_requests.php?edit=<?= $row["leave_id"] ?>">Edit</a>
                      <?php if ($row["status"] === "pending"): ?>
                        <form method="post" action="leave_requests.php" style="display:inline;">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                          <input type="hidden" name="action" value="decide">
                          <input type="hidden" name="leave_id" value="<?= $row["leave_id"] ?>">
                          <input type="hidden" name="status" value="approved">
                          <button class="btn" type="submit">Approve</button>
                        </form>
                        <form method="post" action="leave_requests.php" style="display:inline;">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                          <input type="hidden" name="action" value="decide">
                          <input type="hidden" name="leave_id" value="<?= $row["leave_id"] ?>">
                          <input type="hidden" name="status" value="rejected">
                          <button class="btn danger" type="submit">Reject</button>
                        </form>
                      <?php endif; ?>
                      <form method="post" action="leave_requests.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="leave_id" value="<?= $row["leave_id"] ?>">
                        <button class="btn danger" type="submit" onclick="return confirm('Delete this leave request?');">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalLeaveRequests, $perPage); ?>
      </section>
    </main>
  </div>
  <script src="js/validate.js"></script>
</body>
</html>
