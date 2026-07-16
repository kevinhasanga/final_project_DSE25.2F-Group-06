<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/helpers.php';
require_login('Customer Relationship Officer');

$activePage = "dashboard";

$totalCustomers = (int) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COUNT(*) FROM customer"
))[0];

$openComplaints = (int) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COUNT(*) FROM complaint WHERE status != 'resolved'"
))[0];

$loyaltyMembers = (int) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COUNT(*) FROM customer WHERE loyalty_points > 0"
))[0];

$promotionsSent = (int) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COUNT(*) FROM promotional_notification"
))[0];

$recentComplaints = mysqli_query(
    $connection,
    "SELECT c.complaint_id, cu.name AS customer_name, c.created_date, c.status
     FROM complaint c
     JOIN customer cu ON cu.customer_id = c.customer_id
     ORDER BY c.created_date DESC
     LIMIT 8"
);

$statusClasses = ["open" => "pending", "in_progress" => "progress", "resolved" => "resolved"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Customer Relationship Officer Dashboard</title><link rel="stylesheet" href="css/cro_style.css">
</head>
<body>
  <header class="topbar"><h1>Customer Relationship Officer</h1><p>Customer records, complaints, loyalty programs, promotions, and reports</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Dashboard</h2><p>Overview of customer relationship activities.</p></section>
      <section class="cards">
        <div class="card"><h3>Total Customers</h3><p class="number"><?= $totalCustomers ?></p></div>
        <div class="card"><h3>Open Complaints</h3><p class="number"><?= $openComplaints ?></p></div>
        <div class="card"><h3>Loyalty Members</h3><p class="number"><?= $loyaltyMembers ?></p></div>
        <div class="card"><h3>Promotions Sent</h3><p class="number"><?= $promotionsSent ?></p></div>
      </section>
      <section class="panel">
        <h3>Recent Complaints</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Complaint ID</th><th>Customer</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($recentComplaints) === 0): ?>
                <tr><td colspan="4">No complaint records loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($recentComplaints)): ?>
                <tr>
                  <td><?= $row["complaint_id"] ?></td>
                  <td><?= htmlspecialchars($row["customer_name"]) ?></td>
                  <td><?= htmlspecialchars($row["created_date"]) ?></td>
                  <td><span class="status <?= $statusClasses[$row["status"]] ?? "progress" ?>"><?= htmlspecialchars(ucfirst(str_replace("_", " ", $row["status"]))) ?></span></td>
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
