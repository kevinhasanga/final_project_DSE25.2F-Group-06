<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Driver');

$activePage = "deliveries";
$currentDriverId = getCurrentEmployeeId($connection, (int) $_SESSION["user_id"]);
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: my_deliveries.php");
        exit();
    }

    $action = $_POST["action"] ?? "";

    if ($action === "update_status") {
        $deliveryId = (int) $_POST["delivery_id"];
        $status = $_POST["status"];
        $statement = mysqli_prepare(
            $connection,
            "UPDATE delivery SET status = ? WHERE delivery_id = ? AND driver_id = ?"
        );
        mysqli_stmt_bind_param($statement, "sii", $status, $deliveryId, $currentDriverId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Delivery status updated.");
        header("Location: my_deliveries.php");
        exit();
    }
}

$search = trim($_GET["q"] ?? "");
$deliveries = getDriverDeliveries($connection, $currentDriverId);

if ($search !== "") {
    $deliveries = array_filter($deliveries, function ($row) use ($search) {
        return stripos((string) $row["order_id"], $search) !== false
            || stripos($row["route_details"] ?? "", $search) !== false
            || stripos($row["customer_name"], $search) !== false;
    });
}

$perPage = 10;
$totalDeliveries = count($deliveries);
$totalDeliveryPages = max(1, (int) ceil($totalDeliveries / $perPage));
$currentPage = min(getCurrentPage(), $totalDeliveryPages);
$offset = ($currentPage - 1) * $perPage;
$deliveries = array_slice($deliveries, $offset, $perPage);

$statusOptions = [
    "scheduled" => "Scheduled",
    "dispatched" => "Dispatched",
    "in_transit" => "In Transit",
    "delivered" => "Delivered",
    "delayed" => "Delayed",
    "cancelled" => "Cancelled",
];
$statusClasses = [
    "scheduled" => "progress", "dispatched" => "progress", "in_transit" => "progress",
    "delivered" => "resolved", "delayed" => "pending", "cancelled" => "pending",
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Deliveries</title>
  <link rel="stylesheet" href="css/driver_style.css">
</head>
<body>
  <header class="topbar">
    <h1>Driver</h1>
    <p>View assigned routes and update delivery status</p>
  </header>

  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title">
        <h2>My Deliveries</h2>
        <p>View assigned delivery routes and update delivery status.</p>
      </section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3>Search</h3>
        <form method="get" action="my_deliveries.php">
          <div class="form-grid">
            <div class="form-group full-width">
              <label for="q">Order ID or route</label>
              <input type="text" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search my deliveries">
            </div>
          </div>
          <div class="button-row">
            <button class="btn" type="submit">Search</button>
            <?php if ($search !== ""): ?>
              <a class="btn secondary" href="my_deliveries.php">Clear</a>
            <?php endif; ?>
          </div>
        </form>

        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Address</th>
                <th>Vehicle</th>
                <th>Route</th>
                <th>Date</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($deliveries)): ?>
                <tr><td colspan="7">No assigned routes loaded yet.</td></tr>
              <?php endif; ?>
              <?php foreach ($deliveries as $row): ?>
                <tr>
                  <td>#<?= $row["order_id"] ?></td>
                  <td><?= htmlspecialchars($row["customer_name"]) ?></td>
                  <td><?= htmlspecialchars($row["address"] ?? "") ?></td>
                  <td><?= htmlspecialchars($row["plate_number"]) ?></td>
                  <td><?= htmlspecialchars($row["route_details"] ?? "") ?></td>
                  <td><?= htmlspecialchars($row["scheduled_date"]) ?></td>
                  <td>
                    <?php if (in_array($row["status"], ["delivered", "cancelled"], true)): ?>
                      <span class="status <?= $statusClasses[$row["status"]] ?? "progress" ?>"><?= htmlspecialchars($statusOptions[$row["status"]] ?? $row["status"]) ?></span>
                    <?php else: ?>
                      <form method="post" action="my_deliveries.php">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="delivery_id" value="<?= $row["delivery_id"] ?>">
                        <select name="status" onchange="this.form.submit()">
                          <?php foreach ($statusOptions as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $row["status"] === $value ? "selected" : "" ?>><?= $label ?></option>
                          <?php endforeach; ?>
                        </select>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalDeliveries, $perPage); ?>
      </section>
    </main>
  </div>
</body>
</html>
