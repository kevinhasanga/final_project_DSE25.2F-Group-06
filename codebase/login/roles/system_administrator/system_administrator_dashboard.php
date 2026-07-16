<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/helpers.php';
require_login('Admin');

$activePage = "dashboard";

$totalUsers = (int) mysqli_fetch_row(mysqli_query($connection, "SELECT COUNT(*) FROM user_account"))[0];
$inactiveUsers = (int) mysqli_fetch_row(mysqli_query($connection, "SELECT COUNT(*) FROM user_account WHERE is_active = 0"))[0];
$todayLogins = (int) mysqli_fetch_row(mysqli_query($connection, "SELECT COUNT(*) FROM login_history WHERE DATE(login_time) = CURDATE()"))[0];
$systemErrors = (int) mysqli_fetch_row(mysqli_query($connection, "SELECT COUNT(*) FROM system_error WHERE status != 'resolved'"))[0];

$recentActivity = mysqli_query(
    $connection,
    "SELECT al.log_id, ua.username, al.action, al.timestamp
     FROM audit_log al JOIN user_account ua ON ua.user_id = al.user_id
     ORDER BY al.timestamp DESC LIMIT 8"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>System Administrator Dashboard</title>
  <link rel="stylesheet" href="css/sa_style.css">
</head>
<body>
  <header class="topbar">
    <h1>System Administrator</h1>
    <p>User accounts, roles, access, system settings, backups, and audit reports</p>
  </header>

  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="content">
      <section class="page-title">
        <h2>Dashboard</h2>
        <p>Overview of system administration activities.</p>
      </section>

      <section class="cards">
        <div class="card"><h3>Total Users</h3><p class="number"><?= $totalUsers ?></p><p>Registered accounts</p></div>
        <div class="card"><h3>Inactive Users</h3><p class="number"><?= $inactiveUsers ?></p><p>Need review</p></div>
        <div class="card"><h3>Logins Today</h3><p class="number"><?= $todayLogins ?></p><p>Successful logins</p></div>
        <div class="card"><h3>System Errors</h3><p class="number"><?= $systemErrors ?></p><p>Open error records</p></div>
      </section>

      <section class="panel">
        <h3>Recent User Activity</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>User</th><th>Activity</th><th>Date</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($recentActivity) === 0): ?>
                <tr><td colspan="3">No activity records loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($recentActivity)): ?>
                <tr>
                  <td><?= htmlspecialchars($row["username"]) ?></td>
                  <td><?= htmlspecialchars($row["action"]) ?></td>
                  <td><?= htmlspecialchars($row["timestamp"]) ?></td>
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
