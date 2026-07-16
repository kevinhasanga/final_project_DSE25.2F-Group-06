<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/helpers.php';
require_login('Admin');

$activePage = "reports";

$reportType = $_GET["report_type"] ?? "";
$fromDate = $_GET["from_date"] ?? "";
$toDate = $_GET["to_date"] ?? "";
$userId = (int) ($_GET["user_id"] ?? 0);

$activityRows = [];
$loginRows = [];
$errorRows = [];

$users = mysqli_fetch_all(mysqli_query($connection, "SELECT user_id, username FROM user_account ORDER BY username"), MYSQLI_ASSOC);

if (($reportType === "user_activity" || $reportType === "system_changes") && $fromDate !== "" && $toDate !== "") {
    $sql = "SELECT al.log_id, ua.username, al.action, al.target_table, al.timestamp
            FROM audit_log al JOIN user_account ua ON ua.user_id = al.user_id
            WHERE DATE(al.timestamp) BETWEEN ? AND ?";
    $params = [$fromDate, $toDate];
    $types = "ss";
    if ($reportType === "system_changes") {
        $sql .= " AND al.target_table IS NOT NULL";
    }
    if ($userId > 0) {
        $sql .= " AND al.user_id = ?";
        $params[] = $userId;
        $types .= "i";
    }
    $sql .= " ORDER BY al.timestamp DESC";
    $statement = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($statement, $types, ...$params);
    mysqli_stmt_execute($statement);
    $activityRows = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    mysqli_stmt_close($statement);
}

if ($reportType === "login_history" && $fromDate !== "" && $toDate !== "") {
    $sql = "SELECT lh.login_id, ua.username, lh.login_time, lh.logout_time
            FROM login_history lh JOIN user_account ua ON ua.user_id = lh.user_id
            WHERE DATE(lh.login_time) BETWEEN ? AND ?";
    $params = [$fromDate, $toDate];
    $types = "ss";
    if ($userId > 0) {
        $sql .= " AND lh.user_id = ?";
        $params[] = $userId;
        $types .= "i";
    }
    $sql .= " ORDER BY lh.login_time DESC";
    $statement = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($statement, $types, ...$params);
    mysqli_stmt_execute($statement);
    $loginRows = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    mysqli_stmt_close($statement);
}

if ($reportType === "errors" && $fromDate !== "" && $toDate !== "") {
    $statement = mysqli_prepare(
        $connection,
        "SELECT * FROM system_error WHERE DATE(error_date) BETWEEN ? AND ? ORDER BY error_date DESC"
    );
    mysqli_stmt_bind_param($statement, "ss", $fromDate, $toDate);
    mysqli_stmt_execute($statement);
    $errorRows = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    mysqli_stmt_close($statement);
}

$reportLabels = [
    "user_activity" => "User Activity",
    "login_history" => "Login History",
    "system_changes" => "System Changes",
    "errors" => "System Errors",
];
$errorStatusClasses = ["open" => "pending", "checking" => "progress", "resolved" => "resolved"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports</title>
  <link rel="stylesheet" href="css/sa_style.css">
</head>
<body>
  <header class="topbar"><h1>System Administrator</h1><p>Generate audit reports for user activity, logins, and system changes</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Reports</h2><p>Monitor user activity, login history, and system errors, and generate audit reports.</p></section>

      <section class="panel">
        <h3>Generate Report</h3>
        <form method="get" action="reports.php">
          <div class="form-grid">
            <div class="form-group">
              <label for="reportType">Report Type</label>
              <select id="reportType" name="report_type" required>
                <option value="">Select type</option>
                <?php foreach ($reportLabels as $value => $label): ?>
                  <option value="<?= $value ?>" <?= $reportType === $value ? "selected" : "" ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="userId">User (optional)</label>
              <select id="userId" name="user_id">
                <option value="0">All users</option>
                <?php foreach ($users as $user): ?>
                  <option value="<?= $user["user_id"] ?>" <?= $userId === (int) $user["user_id"] ? "selected" : "" ?>><?= htmlspecialchars($user["username"]) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="fromDate">From Date</label>
              <input type="date" id="fromDate" name="from_date" value="<?= htmlspecialchars($fromDate) ?>" required>
            </div>
            <div class="form-group">
              <label for="toDate">To Date</label>
              <input type="date" id="toDate" name="to_date" value="<?= htmlspecialchars($toDate) ?>" required>
            </div>
          </div>
          <div class="button-row"><button class="btn" type="submit">Generate Report</button></div>
        </form>
      </section>

      <?php if ($reportType === "user_activity" || $reportType === "system_changes"): ?>
        <section class="panel"><h3><?= htmlspecialchars($reportLabels[$reportType]) ?></h3><div class="table-wrapper"><table>
          <thead><tr><th>User</th><th>Action</th><th>Target</th><th>Date</th></tr></thead>
          <tbody>
            <?php if (empty($activityRows)): ?><tr><td colspan="4">No records found for this selection.</td></tr><?php endif; ?>
            <?php foreach ($activityRows as $row): ?>
              <tr>
                <td><?= htmlspecialchars($row["username"]) ?></td>
                <td><?= htmlspecialchars($row["action"]) ?></td>
                <td><?= htmlspecialchars($row["target_table"] ?? "") ?></td>
                <td><?= htmlspecialchars($row["timestamp"]) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div></section>
      <?php endif; ?>

      <?php if ($reportType === "login_history"): ?>
        <section class="panel"><h3>Login History</h3><div class="table-wrapper"><table>
          <thead><tr><th>Username</th><th>Login Time</th><th>Logout Time</th></tr></thead>
          <tbody>
            <?php if (empty($loginRows)): ?><tr><td colspan="3">No login records found for this selection.</td></tr><?php endif; ?>
            <?php foreach ($loginRows as $row): ?>
              <tr>
                <td><?= htmlspecialchars($row["username"]) ?></td>
                <td><?= htmlspecialchars($row["login_time"]) ?></td>
                <td><?= htmlspecialchars($row["logout_time"] ?? "—") ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div></section>
      <?php endif; ?>

      <?php if ($reportType === "errors"): ?>
        <section class="panel"><h3>System Errors</h3><div class="table-wrapper"><table>
          <thead><tr><th>Date</th><th>Type</th><th>Message</th><th>Status</th></tr></thead>
          <tbody>
            <?php if (empty($errorRows)): ?><tr><td colspan="4">No error records found for this selection.</td></tr><?php endif; ?>
            <?php foreach ($errorRows as $row): ?>
              <tr>
                <td><?= htmlspecialchars($row["error_date"]) ?></td>
                <td><?= htmlspecialchars($row["error_type"]) ?></td>
                <td><?= htmlspecialchars($row["error_message"]) ?></td>
                <td><span class="status <?= $errorStatusClasses[$row["status"]] ?? "progress" ?>"><?= htmlspecialchars(ucfirst($row["status"])) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div></section>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
