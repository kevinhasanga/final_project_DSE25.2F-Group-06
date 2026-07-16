<?php
$croNavItems = [
    "dashboard" => ["Dashboard", "customer_relationship_officer_dashboard.php"],
    "customers" => ["Customers", "customers.php"],
    "purchases" => ["Purchase History", "purchase_history.php"],
    "complaints" => ["Complaints", "complaints.php"],
    "promotions" => ["Promotions", "promotions.php"],
    "reports" => ["Reports", "reports.php"],
];
?>
<nav class="sidebar">
  <h2><?= htmlspecialchars($_SESSION["full_name"] ?? $_SESSION["username"] ?? "User") ?></h2>
  <?php foreach ($croNavItems as $key => $item): ?>
    <a class="<?= ($activePage ?? "") === $key ? "active" : "" ?>" href="<?= htmlspecialchars($item[1]) ?>"><?= htmlspecialchars($item[0]) ?></a>
  <?php endforeach; ?>
  <a href="../../communications.php">Internal Mail</a>
  <a href="../../logout.php">Log out</a>
</nav>
