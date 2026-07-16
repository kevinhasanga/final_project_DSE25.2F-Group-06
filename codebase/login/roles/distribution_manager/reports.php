<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/helpers.php';
require_login('Distribution Manager');

$activePage = "reports";

$drivers = getAllDrivers($connection);
$reportType = $_GET["report_type"] ?? "";
$driverId = (int) ($_GET["driver_id"] ?? 0);
$fromDate = $_GET["from_date"] ?? "";
$toDate = $_GET["to_date"] ?? "";

$rows = [];

if ($reportType !== "" && $fromDate !== "" && $toDate !== "") {
    $sql = "SELECT d.order_id, e.full_name AS driver_name, d.scheduled_date, d.status, d.transport_cost
            FROM delivery d
            JOIN employee e ON e.employee_id = d.driver_id
            WHERE d.scheduled_date BETWEEN ? AND ?";
    $params = [$fromDate, $toDate];
    $types = "ss";

    if ($driverId > 0) {
        $sql .= " AND d.driver_id = ?";
        $params[] = $driverId;
        $types .= "i";
    }

    if ($reportType === "completed") {
        $sql .= " AND d.status = 'delivered'";
    } elseif ($reportType === "delayed") {
        $sql .= " AND d.status = 'delayed'";
    }

    $sql .= " ORDER BY d.scheduled_date DESC";
    $statement = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($statement, $types, ...$params);
    mysqli_stmt_execute($statement);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    mysqli_stmt_close($statement);
}

$reportLabels = ["all" => "All Deliveries", "completed" => "Completed Deliveries", "delayed" => "Delayed Deliveries", "transport_costs" => "Transportation Costs"];
$statusClasses = [
    "scheduled" => "progress", "dispatched" => "progress", "in_transit" => "progress",
    "delivered" => "resolved", "delayed" => "pending", "cancelled" => "pending",
];
$totalCost = array_sum(array_column($rows, "transport_cost"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports</title>
  <link rel="stylesheet" href="css/dm_style.css">
</head>
<body>
  <header class="topbar"><h1>Distribution Management</h1><p>Generate delivery performance reports</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Reports</h2><p>Filter delivery records and generate performance reports.</p></section>

      <section class="panel">
        <h3>Report Filters</h3>
        <form method="get" action="reports.php">
          <div class="form-grid">
            <div class="form-group">
              <label for="reportType">Report Type</label>
              <select id="reportType" name="report_type" required>
                <option value="">Select report type</option>
                <?php foreach ($reportLabels as $value => $label): ?>
                  <option value="<?= $value ?>" <?= $reportType === $value ? "selected" : "" ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="driverId">Driver (optional)</label>
              <select id="driverId" name="driver_id">
                <option value="0">All drivers</option>
                <?php foreach ($drivers as $driver): ?>
                  <option value="<?= $driver["employee_id"] ?>" <?= $driverId === (int) $driver["employee_id"] ? "selected" : "" ?>>
                    <?= htmlspecialchars($driver["full_name"]) ?>
                  </option>
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

      <?php if ($reportType !== ""): ?>
        <section class="panel">
          <h3><?= htmlspecialchars($reportLabels[$reportType] ?? "Report") ?></h3>
          <div class="table-wrapper">
            <table>
              <thead><tr><th>Order ID</th><th>Driver</th><th>Delivery Date</th><th>Status</th><th>Cost</th></tr></thead>
              <tbody>
                <?php if ($fromDate === "" || $toDate === ""): ?>
                  <tr><td colspan="5">Select a date range to generate this report.</td></tr>
                <?php elseif (empty($rows)): ?>
                  <tr><td colspan="5">No deliveries found for this selection.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                  <tr>
                    <td>#<?= $row["order_id"] ?></td>
                    <td><?= htmlspecialchars($row["driver_name"]) ?></td>
                    <td><?= htmlspecialchars($row["scheduled_date"]) ?></td>
                    <td><span class="status <?= $statusClasses[$row["status"]] ?? "progress" ?>"><?= htmlspecialchars(ucfirst(str_replace("_", " ", $row["status"]))) ?></span></td>
                    <td><?= number_format($row["transport_cost"], 2) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if ($reportType === "transport_costs" && !empty($rows)): ?>
            <p style="padding: 14px 20px; color: #00153a; font-weight: bold;">Total transport cost: <?= number_format($totalCost, 2) ?></p>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
