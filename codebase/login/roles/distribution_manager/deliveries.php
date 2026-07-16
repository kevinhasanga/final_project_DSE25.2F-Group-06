<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Distribution Manager');

$activePage = "deliveries";
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: deliveries.php");
        exit();
    }

    $action = $_POST["action"] ?? "";

    if ($action === "create") {
        $orderId = (int) $_POST["order_id"];
        $driverId = (int) $_POST["driver_id"];
        $vehicleId = (int) $_POST["vehicle_id"];
        $scheduledDate = $_POST["scheduled_date"];
        $routeDetails = trim($_POST["route_details"] ?? "");
        $fuelCost = (float) ($_POST["fuel_cost"] ?: 0);
        $driverCost = (float) ($_POST["driver_cost"] ?: 0);
        $vehicleCost = (float) ($_POST["vehicle_cost"] ?: 0);
        $otherCost = (float) ($_POST["other_cost"] ?: 0);
        $transportCost = $fuelCost + $driverCost + $vehicleCost + $otherCost;

        $statement = mysqli_prepare(
            $connection,
            "INSERT INTO delivery (order_id, driver_id, vehicle_id, scheduled_date, route_details, status, transport_cost)
             VALUES (?, ?, ?, ?, ?, 'scheduled', ?)"
        );
        mysqli_stmt_bind_param($statement, "iiissd", $orderId, $driverId, $vehicleId, $scheduledDate, $routeDetails, $transportCost);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Delivery scheduled.");
        header("Location: deliveries.php");
        exit();
    }

    if ($action === "update") {
        $deliveryId = (int) $_POST["delivery_id"];
        $driverId = (int) $_POST["driver_id"];
        $vehicleId = (int) $_POST["vehicle_id"];
        $scheduledDate = $_POST["scheduled_date"];
        $routeDetails = trim($_POST["route_details"] ?? "");
        $status = $_POST["status"];
        $fuelCost = (float) ($_POST["fuel_cost"] ?: 0);
        $driverCost = (float) ($_POST["driver_cost"] ?: 0);
        $vehicleCost = (float) ($_POST["vehicle_cost"] ?: 0);
        $otherCost = (float) ($_POST["other_cost"] ?: 0);
        $transportCost = $fuelCost + $driverCost + $vehicleCost + $otherCost;

        $statement = mysqli_prepare(
            $connection,
            "UPDATE delivery SET driver_id = ?, vehicle_id = ?, scheduled_date = ?, route_details = ?, status = ?, transport_cost = ?
             WHERE delivery_id = ?"
        );
        mysqli_stmt_bind_param($statement, "iisssdi", $driverId, $vehicleId, $scheduledDate, $routeDetails, $status, $transportCost, $deliveryId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Delivery updated.");
        header("Location: deliveries.php");
        exit();
    }

    if ($action === "update_status") {
        $deliveryId = (int) $_POST["delivery_id"];
        $status = $_POST["status"];
        $statement = mysqli_prepare($connection, "UPDATE delivery SET status = ? WHERE delivery_id = ?");
        mysqli_stmt_bind_param($statement, "si", $status, $deliveryId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Delivery status updated.");
        header("Location: deliveries.php");
        exit();
    }

    if ($action === "delete") {
        $deliveryId = (int) $_POST["delivery_id"];
        mysqli_query($connection, "DELETE FROM delivery_issue WHERE delivery_id = $deliveryId");
        mysqli_query($connection, "DELETE FROM delivery_proof WHERE delivery_id = $deliveryId");
        $statement = mysqli_prepare($connection, "DELETE FROM delivery WHERE delivery_id = ?");
        mysqli_stmt_bind_param($statement, "i", $deliveryId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Delivery deleted.");
        header("Location: deliveries.php");
        exit();
    }
}

$drivers = getAllDrivers($connection);
$vehicles = getAllVehicles($connection);

$editRecord = null;
if (isset($_GET["edit"])) {
    $editId = (int) $_GET["edit"];
    $statement = mysqli_prepare(
        $connection,
        "SELECT d.*, cu.name AS customer_name FROM delivery d
         JOIN sales_order so ON so.order_id = d.order_id
         JOIN customer cu ON cu.customer_id = so.customer_id
         WHERE d.delivery_id = ?"
    );
    mysqli_stmt_bind_param($statement, "i", $editId);
    mysqli_stmt_execute($statement);
    $editRecord = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);
}

$prefillOrderId = (int) ($_GET["order_id"] ?? 0);
if ($prefillOrderId > 0) {
    $statement = mysqli_prepare(
        $connection,
        "SELECT so.order_id, cu.name AS customer_name FROM sales_order so
         JOIN customer cu ON cu.customer_id = so.customer_id
         WHERE so.order_id = ?"
    );
    mysqli_stmt_bind_param($statement, "i", $prefillOrderId);
    mysqli_stmt_execute($statement);
    $prefillOrder = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);
}

$unscheduledOrders = mysqli_query(
    $connection,
    "SELECT so.order_id, cu.name AS customer_name
     FROM sales_order so
     JOIN customer cu ON cu.customer_id = so.customer_id
     LEFT JOIN delivery d ON d.order_id = so.order_id
     WHERE so.status IN ('completed', 'invoiced') AND d.delivery_id IS NULL
     ORDER BY so.order_date DESC"
);
$unscheduledOrders = mysqli_fetch_all($unscheduledOrders, MYSQLI_ASSOC);

$perPage = 10;
$totalDeliveries = countRows($connection, "SELECT COUNT(*) FROM delivery");
$totalDeliveryPages = max(1, (int) ceil($totalDeliveries / $perPage));
$currentPage = min(getCurrentPage(), $totalDeliveryPages);
$offset = ($currentPage - 1) * $perPage;

$deliveries = mysqli_query(
    $connection,
    "SELECT d.delivery_id, d.order_id, cu.name AS customer_name, e.full_name AS driver_name,
            v.plate_number, d.scheduled_date, d.status, d.transport_cost
     FROM delivery d
     JOIN sales_order so ON so.order_id = d.order_id
     JOIN customer cu ON cu.customer_id = so.customer_id
     JOIN employee e ON e.employee_id = d.driver_id
     JOIN vehicle v ON v.vehicle_id = d.vehicle_id
     ORDER BY d.scheduled_date DESC, d.delivery_id DESC
     LIMIT $perPage OFFSET $offset"
);

$statusOptions = [
    "scheduled" => "Scheduled",
    "dispatched" => "Dispatched",
    "in_transit" => "In Transit",
    "delivered" => "Delivered",
    "delayed" => "Delayed",
    "cancelled" => "Cancelled",
];
$statusClasses = [
    "scheduled" => "progress",
    "dispatched" => "progress",
    "in_transit" => "progress",
    "delivered" => "resolved",
    "delayed" => "pending",
    "cancelled" => "pending",
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Deliveries</title>
  <link rel="stylesheet" href="css/dm_style.css">
</head>
<body>
  <header class="topbar"><h1>Distribution Management</h1><p>Plan schedules, assign drivers and vehicles, track progress, and record costs</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Deliveries</h2><p>Schedule deliveries, assign drivers and vehicles, plan routes, and track progress and cost.</p></section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <?php if ($editRecord): ?>
      <section class="panel">
        <h3>Edit Delivery #<?= $editRecord["delivery_id"] ?> — Order #<?= $editRecord["order_id"] ?> (<?= htmlspecialchars($editRecord["customer_name"]) ?>)</h3>
        <form method="post" action="deliveries.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="delivery_id" value="<?= $editRecord["delivery_id"] ?>">
          <div class="form-grid">
            <div class="form-group">
              <label for="driverId">Driver</label>
              <select id="driverId" name="driver_id" required>
                <?php foreach ($drivers as $driver): ?>
                  <option value="<?= $driver["employee_id"] ?>" <?= $editRecord["driver_id"] == $driver["employee_id"] ? "selected" : "" ?>>
                    <?= htmlspecialchars($driver["full_name"]) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="vehicleId">Vehicle</label>
              <select id="vehicleId" name="vehicle_id" required>
                <?php foreach ($vehicles as $vehicle): ?>
                  <option value="<?= $vehicle["vehicle_id"] ?>" <?= $editRecord["vehicle_id"] == $vehicle["vehicle_id"] ? "selected" : "" ?>>
                    <?= htmlspecialchars($vehicle["plate_number"]) ?> (<?= htmlspecialchars($vehicle["vehicle_type"]) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="scheduledDate">Scheduled Date</label>
              <input type="date" id="scheduledDate" name="scheduled_date" value="<?= htmlspecialchars($editRecord["scheduled_date"]) ?>" required>
            </div>
            <div class="form-group">
              <label for="status">Status</label>
              <select id="status" name="status" required>
                <?php foreach ($statusOptions as $value => $label): ?>
                  <option value="<?= $value ?>" <?= $editRecord["status"] === $value ? "selected" : "" ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="fuelCost">Fuel Cost</label>
              <input type="number" id="fuelCost" name="fuel_cost" min="0" step="0.01" value="0">
            </div>
            <div class="form-group">
              <label for="driverCost">Driver Cost</label>
              <input type="number" id="driverCost" name="driver_cost" min="0" step="0.01" value="0">
            </div>
            <div class="form-group">
              <label for="vehicleCost">Vehicle Cost</label>
              <input type="number" id="vehicleCost" name="vehicle_cost" min="0" step="0.01" value="0">
            </div>
            <div class="form-group">
              <label for="otherCost">Other Cost</label>
              <input type="number" id="otherCost" name="other_cost" min="0" step="0.01" value="<?= htmlspecialchars($editRecord["transport_cost"]) ?>">
            </div>
            <div class="form-group full-width">
              <label for="routeDetails">Route Details</label>
              <textarea id="routeDetails" name="route_details"><?= htmlspecialchars($editRecord["route_details"] ?? "") ?></textarea>
            </div>
          </div>
          <p style="padding: 0 20px; color: #7f93b3; font-size: 13px;">
            Current transport cost on file: <?= number_format($editRecord["transport_cost"], 2) ?>.
            Fuel, driver, vehicle, and other cost fields above are summed into the new transport cost when you save
            (the existing total has been pre-filled into "Other Cost" so saving without changes keeps it unchanged).
          </p>
          <div class="button-row">
            <button class="btn" type="submit">Save Delivery</button>
            <a class="btn secondary" href="deliveries.php">Back to Deliveries</a>
          </div>
        </form>
      </section>
      <?php else: ?>

      <section class="panel">
        <h3>Schedule Delivery</h3>
        <form method="post" action="deliveries.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="create">
          <div class="form-grid">
            <div class="form-group">
              <label for="orderId">Order</label>
              <select id="orderId" name="order_id" required>
                <option value="">Select confirmed order</option>
                <?php foreach ($unscheduledOrders as $order): ?>
                  <option value="<?= $order["order_id"] ?>" <?= $prefillOrderId === (int) $order["order_id"] ? "selected" : "" ?>>
                    #<?= $order["order_id"] ?> — <?= htmlspecialchars($order["customer_name"]) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="driverId">Driver</label>
              <select id="driverId" name="driver_id" required>
                <option value="">Select driver</option>
                <?php foreach ($drivers as $driver): ?>
                  <option value="<?= $driver["employee_id"] ?>"><?= htmlspecialchars($driver["full_name"]) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="vehicleId">Vehicle</label>
              <select id="vehicleId" name="vehicle_id" required>
                <option value="">Select vehicle</option>
                <?php foreach ($vehicles as $vehicle): ?>
                  <option value="<?= $vehicle["vehicle_id"] ?>"><?= htmlspecialchars($vehicle["plate_number"]) ?> (<?= htmlspecialchars($vehicle["vehicle_type"]) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="scheduledDate">Scheduled Date</label>
              <input type="date" id="scheduledDate" name="scheduled_date" required>
            </div>
            <div class="form-group">
              <label for="fuelCost">Fuel Cost</label>
              <input type="number" id="fuelCost" name="fuel_cost" min="0" step="0.01" value="0">
            </div>
            <div class="form-group">
              <label for="driverCost">Driver Cost</label>
              <input type="number" id="driverCost" name="driver_cost" min="0" step="0.01" value="0">
            </div>
            <div class="form-group">
              <label for="vehicleCost">Vehicle Cost</label>
              <input type="number" id="vehicleCost" name="vehicle_cost" min="0" step="0.01" value="0">
            </div>
            <div class="form-group">
              <label for="otherCost">Other Cost</label>
              <input type="number" id="otherCost" name="other_cost" min="0" step="0.01" value="0">
            </div>
            <div class="form-group full-width">
              <label for="routeDetails">Route Details</label>
              <textarea id="routeDetails" name="route_details" placeholder="Route name, start/end location, distance, estimated time"></textarea>
            </div>
          </div>
          <div class="button-row"><button class="btn" type="submit">Schedule Delivery</button></div>
        </form>
      </section>
      <?php endif; ?>

      <section class="panel">
        <h3>Deliveries</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Order ID</th><th>Customer</th><th>Driver</th><th>Vehicle</th><th>Date</th><th>Cost</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($deliveries) === 0): ?>
                <tr><td colspan="8">No deliveries scheduled yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($deliveries)): ?>
                <tr>
                  <td>#<?= $row["order_id"] ?></td>
                  <td><?= htmlspecialchars($row["customer_name"]) ?></td>
                  <td><?= htmlspecialchars($row["driver_name"]) ?></td>
                  <td><?= htmlspecialchars($row["plate_number"]) ?></td>
                  <td><?= htmlspecialchars($row["scheduled_date"]) ?></td>
                  <td><?= number_format($row["transport_cost"], 2) ?></td>
                  <td><span class="status <?= $statusClasses[$row["status"]] ?? "progress" ?>"><?= htmlspecialchars($statusOptions[$row["status"]] ?? $row["status"]) ?></span></td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;">
                      <a class="btn secondary" href="deliveries.php?edit=<?= $row["delivery_id"] ?>">Edit</a>
                      <form method="post" action="deliveries.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="delivery_id" value="<?= $row["delivery_id"] ?>">
                        <select name="status" onchange="this.form.submit()" style="min-width: 110px;">
                          <?php foreach ($statusOptions as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $row["status"] === $value ? "selected" : "" ?>><?= $label ?></option>
                          <?php endforeach; ?>
                        </select>
                      </form>
                      <form method="post" action="deliveries.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="delivery_id" value="<?= $row["delivery_id"] ?>">
                        <button class="btn danger" type="submit" onclick="return confirm('Delete this delivery record?');">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalDeliveries, $perPage); ?>
      </section>
    </main>
  </div>
</body>
</html>
