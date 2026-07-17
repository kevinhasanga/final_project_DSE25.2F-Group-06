<?php
require_once __DIR__ . '/../../../auth.php';
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../helpers.php';
require_login('Admin', '../../../login.php');

$activePage = "reports";
$navBasePath = "../";

$reportType = $_GET["report_type"] ?? "";
$fromDate = $_GET["from_date"] ?? "";
$toDate = $_GET["to_date"] ?? "";
$userId = (int) ($_GET["user_id"] ?? 0);

$activityRows = [];
$loginRows = [];
$errorRows = [];

$users = mysqli_fetch_all(mysqli_query($connection, "SELECT user_id, username FROM user_account ORDER BY username"), MYSQLI_ASSOC);

if (($reportType === "user_activity" || $reportType === "system_changes") && $fromDate !== "" && $toDate !== "") {
    $sql = "SELECT al.log_id, ua.user_id, ua.username, al.action, al.target_table, al.timestamp
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
    $sql = "SELECT lh.login_id, ua.user_id, ua.username, lh.login_time, lh.logout_time
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
        "SELECT * FROM system_error WHERE DATE(error_date) BETWEEN ? AND ? ORDER BY status, error_date DESC"
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

// --- Group and summarize each report the way an administrator would actually read it ---

// User Activity: group by user, ranked by activity volume — an accountability view of who's doing what.
$activityByUser = [];
foreach ($activityRows as $row) {
    $key = $row["user_id"];
    if (!isset($activityByUser[$key])) {
        $activityByUser[$key] = ["name" => $row["username"], "rows" => [], "count" => 0];
    }
    $activityByUser[$key]["rows"][] = $row;
    $activityByUser[$key]["count"]++;
}
uasort($activityByUser, fn($a, $b) => $b["count"] <=> $a["count"]);

// System Changes: group by the system area touched, ranked by volume — spot an area with unusually heavy changes.
$changesByTable = [];
foreach ($activityRows as $row) {
    $table = ($row["target_table"] ?? "") !== "" ? $row["target_table"] : "Other";
    if (!isset($changesByTable[$table])) {
        $changesByTable[$table] = ["rows" => [], "count" => 0];
    }
    $changesByTable[$table]["rows"][] = $row;
    $changesByTable[$table]["count"]++;
}
uasort($changesByTable, fn($a, $b) => $b["count"] <=> $a["count"]);

// Login History: group by user — session count, and sessions with no logout recorded (a security-relevant anomaly, not just noise).
$loginByUser = [];
$totalNoLogout = 0;
foreach ($loginRows as $row) {
    $key = $row["user_id"];
    if (!isset($loginByUser[$key])) {
        $loginByUser[$key] = ["name" => $row["username"], "rows" => [], "count" => 0, "no_logout" => 0, "totalMinutes" => 0, "completedSessions" => 0];
    }
    $loginByUser[$key]["rows"][] = $row;
    $loginByUser[$key]["count"]++;
    if ($row["logout_time"] === null) {
        $loginByUser[$key]["no_logout"]++;
        $totalNoLogout++;
    } else {
        $minutes = (strtotime($row["logout_time"]) - strtotime($row["login_time"])) / 60;
        $loginByUser[$key]["totalMinutes"] += $minutes;
        $loginByUser[$key]["completedSessions"]++;
    }
}
uasort($loginByUser, fn($a, $b) => $b["count"] <=> $a["count"]);

// Errors: group by status in priority order — Open needs action soonest.
$errorStatusLabels = ["open" => "Open", "checking" => "Checking", "resolved" => "Resolved"];
$errorStatusOrder = array_keys($errorStatusLabels);
$errorsByStatus = [];
$resolvedErrorCount = 0;
foreach ($errorRows as $row) {
    $status = $row["status"];
    if (!isset($errorsByStatus[$status])) {
        $errorsByStatus[$status] = ["rows" => [], "count" => 0];
    }
    $errorsByStatus[$status]["rows"][] = $row;
    $errorsByStatus[$status]["count"]++;
    if ($status === "resolved") {
        $resolvedErrorCount++;
    }
}
uksort($errorsByStatus, fn($a, $b) => array_search($a, $errorStatusOrder) <=> array_search($b, $errorStatusOrder));
$totalErrors = count($errorRows);
$openErrors = ($errorsByStatus["open"]["count"] ?? 0) + ($errorsByStatus["checking"]["count"] ?? 0);
$errorResolutionRate = $totalErrors > 0 ? round($resolvedErrorCount / $totalErrors * 100, 1) : 0;

// --- Printable report header content ---

$reportTitle = $reportLabels[$reportType] ?? "";
$selectedUserName = "All users";
if ($userId > 0) {
    foreach ($users as $user) {
        if ((int) $user["user_id"] === $userId) {
            $selectedUserName = $user["username"];
            break;
        }
    }
}
$filterParts = [];
if (in_array($reportType, ["user_activity", "system_changes", "login_history"], true)) {
    $filterParts[] = "User: " . $selectedUserName;
}
if ($fromDate !== "" && $toDate !== "") {
    $filterParts[] = "Period: " . $fromDate . " to " . $toDate;
}
$generatedBy = $_SESSION["full_name"] ?? $_SESSION["username"] ?? "User";
$generatedOn = date("Y-m-d H:i");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports</title>
  <link rel="stylesheet" href="../css/sa_style.css?v=<?= filemtime(__DIR__ . '/../css/sa_style.css') ?>">
</head>
<body>
  <header class="topbar no-print"><h1>System Administrator</h1><p>Generate audit reports for user activity, logins, and system changes</p></header>
  <div class="layout">
    <?php include __DIR__ . '/../nav.php'; ?>
    <main class="content">
      <section class="page-title no-print"><h2>Reports</h2><p>Monitor user activity, login history, and system errors, and generate audit reports.</p></section>

      <section class="panel no-print">
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

      <?php if ($reportType === "user_activity"): ?>
        <section class="panel">
          <?php include __DIR__ . '/report_header.php'; ?>
          <h3>User Activity <button class="btn no-print" type="button" onclick="window.print()" style="float:right;">Print / Save as PDF</button></h3>
          <div class="report-summary">
            <div class="stat"><p class="label">Total Actions</p><p class="value"><?= count($activityRows) ?></p></div>
            <div class="stat"><p class="label">Active Users</p><p class="value"><?= count($activityByUser) ?></p></div>
          </div>
          <p style="padding: 0 20px; color: #7f93b3; font-size: 13px;">Grouped by user and ranked by activity volume, so the busiest accounts show first — useful for spotting unusual activity concentrated on one account.</p>
          <div class="table-wrapper"><table>
            <thead><tr><th>Action</th><th>Target</th><th>Date</th></tr></thead>
            <tbody>
              <?php if (empty($activityByUser)): ?><tr><td colspan="3">No records found for this selection.</td></tr><?php endif; ?>
              <?php foreach ($activityByUser as $group): ?>
                <tr class="group-heading"><td colspan="3"><?= htmlspecialchars($group["name"]) ?> — <?= $group["count"] ?> actions</td></tr>
                <?php foreach ($group["rows"] as $row): ?>
                  <tr><td><?= htmlspecialchars($row["action"]) ?></td><td><?= htmlspecialchars($row["target_table"] ?? "") ?></td><td><?= htmlspecialchars($row["timestamp"]) ?></td></tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </tbody>
          </table></div>
        </section>
      <?php endif; ?>

      <?php if ($reportType === "system_changes"): ?>
        <section class="panel">
          <?php include __DIR__ . '/report_header.php'; ?>
          <h3>System Changes <button class="btn no-print" type="button" onclick="window.print()" style="float:right;">Print / Save as PDF</button></h3>
          <div class="report-summary">
            <div class="stat"><p class="label">Total Changes</p><p class="value"><?= count($activityRows) ?></p></div>
            <div class="stat"><p class="label">Areas Touched</p><p class="value"><?= count($changesByTable) ?></p></div>
          </div>
          <p style="padding: 0 20px; color: #7f93b3; font-size: 13px;">Grouped by the system area touched, ranked by volume — useful for spotting an area (e.g. user accounts, access privileges) with unusually heavy changes in the period.</p>
          <div class="table-wrapper"><table>
            <thead><tr><th>User</th><th>Action</th><th>Date</th></tr></thead>
            <tbody>
              <?php if (empty($changesByTable)): ?><tr><td colspan="3">No records found for this selection.</td></tr><?php endif; ?>
              <?php foreach ($changesByTable as $table => $group): ?>
                <tr class="group-heading"><td colspan="3"><?= htmlspecialchars(ucwords(str_replace("_", " ", $table))) ?> — <?= $group["count"] ?> changes</td></tr>
                <?php foreach ($group["rows"] as $row): ?>
                  <tr><td><?= htmlspecialchars($row["username"]) ?></td><td><?= htmlspecialchars($row["action"]) ?></td><td><?= htmlspecialchars($row["timestamp"]) ?></td></tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </tbody>
          </table></div>
        </section>
      <?php endif; ?>

      <?php if ($reportType === "login_history"): ?>
        <section class="panel">
          <?php include __DIR__ . '/report_header.php'; ?>
          <h3>Login History <button class="btn no-print" type="button" onclick="window.print()" style="float:right;">Print / Save as PDF</button></h3>
          <div class="report-summary">
            <div class="stat"><p class="label">Total Sessions</p><p class="value"><?= count($loginRows) ?></p></div>
            <div class="stat"><p class="label">Unique Users</p><p class="value"><?= count($loginByUser) ?></p></div>
            <div class="stat <?= $totalNoLogout > 0 ? "warning" : "" ?>"><p class="label">Sessions Without Logout</p><p class="value"><?= $totalNoLogout ?></p></div>
          </div>
          <p style="padding: 0 20px; color: #7f93b3; font-size: 13px;">Grouped by user and ranked by session count. Sessions Without Logout means the session ended without a recorded logout (browser closed, crash, timeout) — worth a look if concentrated on one account.</p>
          <div class="table-wrapper"><table>
            <thead><tr><th>Login Time</th><th>Logout Time</th></tr></thead>
            <tbody>
              <?php if (empty($loginByUser)): ?><tr><td colspan="2">No login records found for this selection.</td></tr><?php endif; ?>
              <?php foreach ($loginByUser as $group): ?>
                <tr class="group-heading">
                  <td colspan="2">
                    <?= htmlspecialchars($group["name"]) ?> — <?= $group["count"] ?> sessions<?= $group["no_logout"] > 0 ? " (" . $group["no_logout"] . " without logout)" : "" ?><?php if ($group["completedSessions"] > 0): ?>, avg. <?= round($group["totalMinutes"] / $group["completedSessions"]) ?> min/session<?php endif; ?>
                  </td>
                </tr>
                <?php foreach ($group["rows"] as $row): ?>
                  <tr><td><?= htmlspecialchars($row["login_time"]) ?></td><td><?= htmlspecialchars($row["logout_time"] ?? "—") ?></td></tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </tbody>
          </table></div>
        </section>
      <?php endif; ?>

      <?php if ($reportType === "errors"): ?>
        <section class="panel">
          <?php include __DIR__ . '/report_header.php'; ?>
          <h3>System Errors <button class="btn no-print" type="button" onclick="window.print()" style="float:right;">Print / Save as PDF</button></h3>
          <div class="report-summary">
            <div class="stat"><p class="label">Total Errors</p><p class="value"><?= $totalErrors ?></p></div>
            <div class="stat warning"><p class="label">Still Open</p><p class="value"><?= $openErrors ?></p></div>
            <div class="stat"><p class="label">Resolution Rate</p><p class="value"><?= $errorResolutionRate ?>%</p></div>
          </div>
          <p style="padding: 0 20px; color: #7f93b3; font-size: 13px;">Grouped by status with Open first, since those need action soonest.</p>
          <div class="table-wrapper"><table>
            <thead><tr><th>Date</th><th>Type</th><th>Message</th></tr></thead>
            <tbody>
              <?php if (empty($errorsByStatus)): ?><tr><td colspan="3">No error records found for this selection.</td></tr><?php endif; ?>
              <?php foreach ($errorsByStatus as $status => $group): ?>
                <tr class="group-heading"><td colspan="3"><?= htmlspecialchars($errorStatusLabels[$status] ?? ucfirst($status)) ?> — <?= $group["count"] ?> errors</td></tr>
                <?php foreach ($group["rows"] as $row): ?>
                  <tr><td><?= htmlspecialchars($row["error_date"]) ?></td><td><?= htmlspecialchars($row["error_type"]) ?></td><td><?= htmlspecialchars($row["error_message"]) ?></td></tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </tbody>
          </table></div>
        </section>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
