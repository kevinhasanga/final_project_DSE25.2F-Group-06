<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Driver');

$activePage = "fuel";
$currentDriverId = getCurrentEmployeeId($connection, (int) $_SESSION["user_id"]);
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: fuel_usage.php");
        exit();
    }

    $action = $_POST["action"] ?? "";

    if ($action === "save") {
        $deliveryId = (int) $_POST["delivery_id"];
        $fuelDate = $_POST["fuel_date"];
        $liters = (float) $_POST["liters"];
        $cost = (float) $_POST["cost"];
        $odometerReading = $_POST["odometer_reading"] !== "" ? (int) $_POST["odometer_reading"] : null;

        $statement = mysqli_prepare(
            $connection,
            "INSERT INTO fuel_usage (delivery_id, driver_id, fuel_date, liters, cost, odometer_reading)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($statement, "iisddi", $deliveryId, $currentDriverId, $fuelDate, $liters, $cost, $odometerReading);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Fuel usage recorded.");
        header("Location: fuel_usage.php");
        exit();
    }

    if ($action === "delete") {
        $fuelId = (int) $_POST["fuel_id"];
        $statement = mysqli_prepare($connection, "DELETE FROM fuel_usage WHERE fuel_id = ? AND driver_id = ?");
        mysqli_stmt_bind_param($statement, "ii", $fuelId, $currentDriverId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Fuel usage record deleted.");
        header("Location: fuel_usage.php");
        exit();
    }
}

$deliveries = getDriverDeliveries($connection, $currentDriverId);

$perPage = 10;
$totalFuelRecords = countRows(
    $connection,
    "SELECT COUNT(*) FROM fuel_usage fu JOIN delivery d ON d.delivery_id = fu.delivery_id JOIN vehicle v ON v.vehicle_id = d.vehicle_id WHERE fu.driver_id = ?",
    "i",
    [$currentDriverId]
);
$totalFuelPages = max(1, (int) ceil($totalFuelRecords / $perPage));
$currentPage = min(getCurrentPage(), $totalFuelPages);
$offset = ($currentPage - 1) * $perPage;

$fuelRecords = mysqli_query(
    $connection,
    "SELECT fu.fuel_id, d.order_id, v.plate_number, fu.fuel_date, fu.liters, fu.cost, fu.odometer_reading
     FROM fuel_usage fu
     JOIN delivery d ON d.delivery_id = fu.delivery_id
     JOIN vehicle v ON v.vehicle_id = d.vehicle_id
     WHERE fu.driver_id = $currentDriverId
     ORDER BY fu.fuel_date DESC, fu.fuel_id DESC
     LIMIT $perPage OFFSET $offset"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Fuel Usage</title>
  <link rel="stylesheet" href="css/driver_style.css">
</head>
<body>
  <header class="topbar">
    <h1>Driver</h1>
    <p>Record fuel usage for deliveries</p>
  </header>

  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title">
        <h2>Fuel Usage</h2>
        <p>Record fuel amount and cost for assigned delivery trips.</p>
      </section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3>Record Fuel Usage</h3>
        <form method="post" action="fuel_usage.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="save">
          <div class="form-grid">
            <div class="form-group">
              <label for="deliveryId">Delivery / Vehicle</label>
              <select id="deliveryId" name="delivery_id" required>
                <option value="">Select delivery</option>
                <?php foreach ($deliveries as $delivery): ?>
                  <option value="<?= $delivery["delivery_id"] ?>">
                    Order #<?= $delivery["order_id"] ?> — <?= htmlspecialchars($delivery["plate_number"]) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="fuelDate">Fuel Date</label>
              <input type="date" id="fuelDate" name="fuel_date" required>
            </div>
            <div class="form-group">
              <label for="liters">Fuel Liters</label>
              <input type="number" id="liters" name="liters" min="0" step="0.01" required>
            </div>
            <div class="form-group">
              <label for="cost">Fuel Cost</label>
              <input type="number" id="cost" name="cost" min="0" step="0.01" required>
            </div>
            <div class="form-group">
              <label for="odometerReading">Odometer Reading</label>
              <input type="number" id="odometerReading" name="odometer_reading" min="0">
            </div>
          </div>
          <div class="button-row"><button class="btn" type="submit">Save Fuel Usage</button></div>
        </form>
      </section>

      <section class="panel">
        <h3>Fuel Usage Records</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Order ID</th><th>Vehicle</th><th>Date</th><th>Liters</th><th>Cost</th><th>Odometer</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($fuelRecords) === 0): ?>
                <tr><td colspan="7">No fuel usage records loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($fuelRecords)): ?>
                <tr>
                  <td>#<?= $row["order_id"] ?></td>
                  <td><?= htmlspecialchars($row["plate_number"]) ?></td>
                  <td><?= htmlspecialchars($row["fuel_date"]) ?></td>
                  <td><?= number_format($row["liters"], 2) ?></td>
                  <td><?= number_format($row["cost"], 2) ?></td>
                  <td><?= $row["odometer_reading"] !== null ? (int) $row["odometer_reading"] : "—" ?></td>
                  <td>
                    <form method="post" action="fuel_usage.php" style="display:inline;">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="fuel_id" value="<?= $row["fuel_id"] ?>">
                      <button class="btn danger" type="submit" onclick="return confirm('Delete this fuel usage record?');">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalFuelRecords, $perPage); ?>
      </section>
    </main>
  </div>
  <script src="js/validate.js"></script>
</body>
</html>
