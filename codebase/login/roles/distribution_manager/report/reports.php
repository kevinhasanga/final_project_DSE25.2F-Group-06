<?php
require_once __DIR__ . '/../../../auth.php';
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../helpers.php';
require_login('Distribution Manager', '../../../login.php');

$activePage = "reports";
$navBasePath = "../";

$drivers = getAllDrivers($connection);
$reportType = $_GET["report_type"] ?? "";
$driverId = (int) ($_GET["driver_id"] ?? 0);
$fromDate = $_GET["from_date"] ?? "";
$toDate = $_GET["to_date"] ?? "";

$rows = [];

if ($reportType !== "" && $fromDate !== "" && $toDate !== "") {
    $sql = "SELECT d.order_id, e.employee_id AS driver_id, e.full_name AS driver_name,
                   v.vehicle_id, v.plate_number, v.vehicle_type,
                   d.scheduled_date, d.status, d.transport_cost
            FROM delivery d
            JOIN employee e ON e.employee_id = d.driver_id
            JOIN vehicle v ON v.vehicle_id = d.vehicle_id
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
    "scheduled" => "progress", "dispatched" => "progress", "in_transit" => "info",
    "delivered" => "resolved", "delayed" => "pending", "cancelled" => "pending",
];

// --- Group and summarize the way a manager would actually read it ---

$totalDeliveries = count($rows);
$totalCost = array_sum(array_column($rows, "transport_cost"));
$deliveredCount = 0;
$delayedCount = 0;
foreach ($rows as $row) {
    if ($row["status"] === "delivered") {
        $deliveredCount++;
    } elseif ($row["status"] === "delayed") {
        $delayedCount++;
    }
}
$onTimeRate = $totalDeliveries > 0 ? round($deliveredCount / $totalDeliveries * 100, 1) : 0;

// all / completed / delayed: group by driver, so it reads as a driver performance report, not a delivery log.
$driverGroups = [];
if (in_array($reportType, ["all", "completed", "delayed"], true)) {
    foreach ($rows as $row) {
        $key = $row["driver_id"];
        if (!isset($driverGroups[$key])) {
            $driverGroups[$key] = ["name" => $row["driver_name"], "rows" => [], "count" => 0, "delivered" => 0, "delayed" => 0, "cancelled" => 0, "other" => 0, "cost" => 0];
        }
        $driverGroups[$key]["rows"][] = $row;
        $driverGroups[$key]["count"]++;
        $driverGroups[$key]["cost"] += $row["transport_cost"];
        if ($row["status"] === "delivered") {
            $driverGroups[$key]["delivered"]++;
        } elseif ($row["status"] === "delayed") {
            $driverGroups[$key]["delayed"]++;
        } elseif ($row["status"] === "cancelled") {
            $driverGroups[$key]["cancelled"]++;
        } else {
            $driverGroups[$key]["other"]++;
        }
    }
    if ($reportType === "all") {
        uasort($driverGroups, fn($a, $b) => $b["delayed"] <=> $a["delayed"] ?: $b["count"] <=> $a["count"]);
    } else {
        uasort($driverGroups, fn($a, $b) => $b["count"] <=> $a["count"]);
    }
}
$worstDriverName = "";
$worstDriverDelays = 0;
foreach ($driverGroups as $group) {
    if ($group["delayed"] > $worstDriverDelays) {
        $worstDriverDelays = $group["delayed"];
        $worstDriverName = $group["name"];
    }
}

// transport_costs: group by vehicle, so fleet cost is easy to compare vehicle-to-vehicle.
$vehicleGroups = [];
if ($reportType === "transport_costs") {
    foreach ($rows as $row) {
        $key = $row["vehicle_id"];
        if (!isset($vehicleGroups[$key])) {
            $vehicleGroups[$key] = ["label" => $row["plate_number"] . " (" . $row["vehicle_type"] . ")", "rows" => [], "count" => 0, "cost" => 0];
        }
        $vehicleGroups[$key]["rows"][] = $row;
        $vehicleGroups[$key]["count"]++;
        $vehicleGroups[$key]["cost"] += $row["transport_cost"];
    }
    uasort($vehicleGroups, fn($a, $b) => $b["cost"] <=> $a["cost"]);
}
$topVehicleLabel = "";
$topVehicleCost = 0;
if (!empty($vehicleGroups)) {
    $topVehicleLabel = reset($vehicleGroups)["label"];
    $topVehicleCost = reset($vehicleGroups)["cost"];
}

// --- Printable report header content ---

$reportTitle = $reportLabels[$reportType] ?? "";

$selectedDriverName = "All drivers";
if ($driverId > 0) {
    foreach ($drivers as $driver) {
        if ((int) $driver["employee_id"] === $driverId) {
            $selectedDriverName = $driver["full_name"];
            break;
        }
    }
}
$filterParts = ["Driver: " . $selectedDriverName];
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
  <link rel="stylesheet" href="../css/dm_style.css?v=<?= filemtime(__DIR__ . '/../css/dm_style.css') ?>">
</head>
<body>
  <header class="topbar no-print"><h1>Distribution Management</h1><p>Generate delivery performance reports</p></header>
  <div class="layout">
    <?php include __DIR__ . '/../nav.php'; ?>
    <main class="content">
      <section class="page-title no-print"><h2>Reports</h2><p>Filter delivery records and generate performance reports.</p></section>

      <section class="panel no-print">
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

      <?php if ($reportType !== "" && $fromDate !== "" && $toDate !== ""): ?>
        <section class="panel">
          <?php include __DIR__ . '/report_header.php'; ?>
          <h3>
            <?= htmlspecialchars($reportLabels[$reportType] ?? "Report") ?>
            <button class="btn no-print" type="button" onclick="window.print()" style="float:right;">Print / Save as PDF</button>
          </h3>

          <?php if ($reportType === "all"): ?>
            <div class="report-summary">
              <div class="stat">
                <p class="label">Total Deliveries</p>
                <p class="value"><?= $totalDeliveries ?></p>
              </div>
              <div class="stat">
                <p class="label">On-Time Rate</p>
                <p class="value"><?= $onTimeRate ?>%</p>
              </div>
              <div class="stat warning">
                <p class="label">Delayed</p>
                <p class="value"><?= $delayedCount ?></p>
              </div>
              <div class="stat">
                <p class="label">Total Transport Cost</p>
                <p class="value"><?= number_format($totalCost, 2) ?></p>
              </div>
            </div>
            <p style="padding: 0 20px; color: #7f93b3; font-size: 13px;">Grouped by driver and ranked by delay count, so drivers who need coaching or support surface first instead of getting lost in a flat delivery log.</p>
          <?php elseif ($reportType === "completed"): ?>
            <div class="report-summary">
              <div class="stat">
                <p class="label">Total Completed</p>
                <p class="value"><?= $totalDeliveries ?></p>
              </div>
              <div class="stat">
                <p class="label">Total Cost</p>
                <p class="value"><?= number_format($totalCost, 2) ?></p>
              </div>
              <div class="stat">
                <p class="label">Avg. Cost / Delivery</p>
                <p class="value"><?= $totalDeliveries > 0 ? number_format($totalCost / $totalDeliveries, 2) : "0.00" ?></p>
              </div>
            </div>
            <p style="padding: 0 20px; color: #7f93b3; font-size: 13px;">Grouped by driver and ranked by completed volume — a quick read on who's carrying the most delivery load.</p>
          <?php elseif ($reportType === "delayed"): ?>
            <div class="report-summary">
              <div class="stat warning">
                <p class="label">Total Delayed</p>
                <p class="value"><?= $totalDeliveries ?></p>
              </div>
              <div class="stat warning">
                <p class="label">Cost Tied Up in Delays</p>
                <p class="value"><?= number_format($totalCost, 2) ?></p>
              </div>
              <div class="stat">
                <p class="label">Most Delayed Driver</p>
                <p class="value" style="font-size: 15px;"><?= $worstDriverName !== "" ? htmlspecialchars($worstDriverName) . " (" . $worstDriverDelays . ")" : "—" ?></p>
              </div>
            </div>
            <p style="padding: 0 20px; color: #7f93b3; font-size: 13px;">Grouped by driver and ranked by delay count — an accountability view for coaching conversations, not just a list of late jobs.</p>
          <?php elseif ($reportType === "transport_costs"): ?>
            <div class="report-summary">
              <div class="stat">
                <p class="label">Total Cost</p>
                <p class="value"><?= number_format($totalCost, 2) ?></p>
              </div>
              <div class="stat">
                <p class="label">Vehicles Used</p>
                <p class="value"><?= count($vehicleGroups) ?></p>
              </div>
              <div class="stat warning">
                <p class="label">Highest-Cost Vehicle</p>
                <p class="value" style="font-size: 15px;"><?= $topVehicleLabel !== "" ? htmlspecialchars($topVehicleLabel) : "—" ?></p>
              </div>
            </div>
            <p style="padding: 0 20px; color: #7f93b3; font-size: 13px;">Grouped by vehicle and ranked by cost — useful for spotting a vehicle that's getting expensive to run before it shows up as a maintenance emergency.</p>
          <?php endif; ?>

          <div class="table-wrapper">
            <?php if ($reportType === "transport_costs"): ?>
              <table>
                <thead><tr><th>Order ID</th><th>Driver</th><th>Delivery Date</th><th>Status</th><th>Cost</th></tr></thead>
                <tbody>
                  <?php if (empty($vehicleGroups)): ?>
                    <tr><td colspan="5">No deliveries found for this selection.</td></tr>
                  <?php endif; ?>
                  <?php foreach ($vehicleGroups as $group): ?>
                    <tr class="group-heading">
                      <td colspan="5"><?= htmlspecialchars($group["label"]) ?> — <?= $group["count"] ?> deliveries, cost <?= number_format($group["cost"], 2) ?></td>
                    </tr>
                    <?php foreach ($group["rows"] as $row): ?>
                      <tr>
                        <td>#<?= $row["order_id"] ?></td>
                        <td><?= htmlspecialchars($row["driver_name"]) ?></td>
                        <td><?= htmlspecialchars($row["scheduled_date"]) ?></td>
                        <td><span class="status <?= $statusClasses[$row["status"]] ?? "progress" ?>"><?= htmlspecialchars(ucfirst(str_replace("_", " ", $row["status"]))) ?></span></td>
                        <td><?= number_format($row["transport_cost"], 2) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endforeach; ?>
                </tbody>
                <?php if (!empty($vehicleGroups)): ?>
                  <tfoot>
                    <tr class="grand-total"><td colspan="4">Grand Total</td><td><?= number_format($totalCost, 2) ?></td></tr>
                  </tfoot>
                <?php endif; ?>
              </table>
            <?php else: ?>
              <table>
                <thead><tr><th>Order ID</th><th>Delivery Date</th><th>Status</th><th>Cost</th></tr></thead>
                <tbody>
                  <?php if (empty($driverGroups)): ?>
                    <tr><td colspan="4">No deliveries found for this selection.</td></tr>
                  <?php endif; ?>
                  <?php foreach ($driverGroups as $group): ?>
                    <tr class="group-heading">
                      <td colspan="4">
                        <?= htmlspecialchars($group["name"]) ?> —
                        <?php if ($reportType === "all"): ?>
                          <?= $group["count"] ?> deliveries (<?= $group["delivered"] ?> delivered, <?= $group["delayed"] ?> delayed<?= $group["cancelled"] > 0 ? ", " . $group["cancelled"] . " cancelled" : "" ?>), cost <?= number_format($group["cost"], 2) ?>
                        <?php elseif ($reportType === "completed"): ?>
                          <?= $group["count"] ?> completed, cost <?= number_format($group["cost"], 2) ?>
                        <?php else: ?>
                          <?= $group["count"] ?> delayed, cost <?= number_format($group["cost"], 2) ?>
                        <?php endif; ?>
                      </td>
                    </tr>
                    <?php foreach ($group["rows"] as $row): ?>
                      <tr>
                        <td>#<?= $row["order_id"] ?></td>
                        <td><?= htmlspecialchars($row["scheduled_date"]) ?></td>
                        <td><span class="status <?= $statusClasses[$row["status"]] ?? "progress" ?>"><?= htmlspecialchars(ucfirst(str_replace("_", " ", $row["status"]))) ?></span></td>
                        <td><?= number_format($row["transport_cost"], 2) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endforeach; ?>
                </tbody>
                <?php if (!empty($driverGroups)): ?>
                  <tfoot>
                    <tr class="grand-total"><td colspan="3">Grand Total</td><td><?= number_format($totalCost, 2) ?></td></tr>
                  </tfoot>
                <?php endif; ?>
              </table>
            <?php endif; ?>
          </div>
        </section>
      <?php elseif ($reportType !== ""): ?>
        <section class="panel"><p style="padding: 14px 20px; color: #7f93b3;">Select a date range to generate this report.</p></section>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
