<?php
require_once __DIR__ . '/../login/auth.php';
require_login('Admin');
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
    <nav class="sidebar">
      <h2>System Admin</h2>
      <a class="active" href="system_administrator_dashboard.php">Dashboard</a>
      <a href="create_user_accounts.html">Create Users</a>
      <a href="assign_user_roles.html">Assign Roles</a>
      <a href="access_privileges.html">Access Privileges</a>
      <a href="update_user_information.html">Update Users</a>
      <a href="reset_passwords.html">Reset Passwords</a>
      <a href="account_status.html">Account Status</a>
      <a href="user_activity_logs.html">Activity Logs</a>
      <a href="login_history.html">Login History</a>
      <a href="system_settings.html">System Settings</a>
      <a href="notification_settings.html">Notifications</a>
      <a href="database_backups.html">Backups</a>
      <a href="restore_system_data.html">Restore Data</a>
      <a href="system_errors.html">System Errors</a>
      <a href="audit_reports.html">Audit Reports</a>
      <a href="../login/logout.php">Log out</a>
    </nav>

    <main class="content">
      <section class="page-title">
        <h2>Dashboard</h2>
        <p>Overview of system administration activities.</p>
      </section>

      <section class="cards">
        <div class="card">
          <h3>Total Users</h3>
          <p class="number" id="totalUsers">0</p>
          <p>Registered accounts</p>
        </div>
        <div class="card">
          <h3>Inactive Users</h3>
          <p class="number" id="inactiveUsers">0</p>
          <p>Need review</p>
        </div>
        <div class="card">
          <h3>Logins Today</h3>
          <p class="number" id="todayLogins">0</p>
          <p>Successful logins</p>
        </div>
        <div class="card">
          <h3>System Errors</h3>
          <p class="number" id="systemErrors">0</p>
          <p>Open error records</p>
        </div>
      </section>

      <section class="panel">
        <h3>Recent User Activity</h3>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Log ID</th>
                <th>User ID</th>
                <th>Activity</th>
                <th>Date</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="recentActivityTable">
              <tr>
                <td colspan="5">No activity records loaded yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
</body>
</html>
