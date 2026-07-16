<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Distribution Manager');

$activePage = "delayed";
$currentEmployeeId = getCurrentEmployeeId($connection, (int) $_SESSION["user_id"]);
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: delayed_deliveries.php");
        exit();
    }

    $action = $_POST["action"] ?? "";

    if ($action === "record_delay") {
        $deliveryId = (int) $_POST["delivery_id"];
        $reason = $_POST["reason"];
        $newExpectedDate = $_POST["new_expected_date"];
        $actionTaken = trim($_POST["action_taken"] ?? "");
        $description = "Reason: " . $reason . (($actionTaken !== "") ? ". Action taken: " . $actionTaken : "");

        $statement = mysqli_prepare(
            $connection,
            "INSERT INTO delivery_issue (delivery_id, reported_by, issue_description, issue_date)
             VALUES (?, ?, ?, NOW())"
        );
        mysqli_stmt_bind_param($statement, "iis", $deliveryId, $currentEmployeeId, $description);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);

        $statement = mysqli_prepare(
            $connection,
            "UPDATE delivery SET status = 'delayed', scheduled_date = ? WHERE delivery_id = ?"
        );
        mysqli_stmt_bind_param($statement, "si", $newExpectedDate, $deliveryId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);

        setFlash("Delay recorded and delivery rescheduled.");
        header("Location: delayed_deliveries.php");
        exit();
    }

    if ($action === "resolve") {
        $deliveryId = (int) $_POST["delivery_id"];
        $statement = mysqli_prepare($connection, "UPDATE delivery SET status = 'scheduled' WHERE delivery_id = ?");
        mysqli_stmt_bind_param($statement, "i", $deliveryId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Delivery marked as back on schedule.");
        header("Location: delayed_deliveries.php");
        exit();
    }
}

$activeDeliveries = mysqli_query(
    $connection,
    "SELECT d.delivery_id, d.order_id, cu.name AS customer_name, e.full_name AS driver_name, d.scheduled_date
     FROM delivery d
     JOIN sales_order so ON so.order_id = d.order_id
     JOIN customer cu ON cu.customer_id = so.customer_id
     JOIN employee e ON e.employee_id = d.driver_id
     WHERE d.status != 'delivered' AND d.status != 'cancelled'
     ORDER BY d.scheduled_date ASC"
);
$activeDeliveries = mysqli_fetch_all($activeDeliveries, MYSQLI_ASSOC);

$perPage = 10;

$totalDelayed = countRows($connection, "SELECT COUNT(*) FROM delivery d WHERE d.status = 'delayed'");
$totalDelayedPages = max(1, (int) ceil($totalDelayed / $perPage));
$delayedPage = min(getCurrentPage("delayed_page"), $totalDelayedPages);
$delayedOffset = ($delayedPage - 1) * $perPage;

$delayedDeliveries = mysqli_query(
    $connection,
    "SELECT d.delivery_id, d.order_id, cu.name AS customer_name, e.full_name AS driver_name, d.scheduled_date
     FROM delivery d
     JOIN sales_order so ON so.order_id = d.order_id
     JOIN customer cu ON cu.customer_id = so.customer_id
     JOIN employee e ON e.employee_id = d.driver_id
     WHERE d.status = 'delayed'
     ORDER BY d.scheduled_date ASC
     LIMIT $perPage OFFSET $delayedOffset"
);

$totalDelayIssues = countRows($connection, "SELECT COUNT(*) FROM delivery_issue");
$totalDelayIssuePages = max(1, (int) ceil($totalDelayIssues / $perPage));
$historyPage = min(getCurrentPage("history_page"), $totalDelayIssuePages);
$historyOffset = ($historyPage - 1) * $perPage;

$delayIssues = mysqli_query(
    $connection,
    "SELECT di.delivery_id, d.order_id, di.issue_description, di.issue_date
     FROM delivery_issue di
     JOIN delivery d ON d.delivery_id = di.delivery_id
     ORDER BY di.issue_date DESC
     LIMIT $perPage OFFSET $historyOffset"
);

$reasonOptions = ["traffic" => "Traffic", "vehicle_issue" => "Vehicle Issue", "weather" => "Weather", "customer_unavailable" => "Customer Unavailable", "other" => "Other"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Delayed Deliveries</title>
  <link rel="stylesheet" href="css/dm_style.css">
</head>
<body>
  <header class="topbar"><h1>Distribution Management</h1><p>Identify and manage delayed deliveries</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Delayed Deliveries</h2><p>Record delay reasons and monitor delayed order actions.</p></section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3>Record Delay</h3>
        <form method="post" action="delayed_deliveries.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="record_delay">
          <div class="form-grid">
            <div class="form-group">
              <label for="deliveryId">Delivery</label>
              <select id="deliveryId" name="delivery_id" required>
                <option value="">Select delivery</option>
                <?php foreach ($activeDeliveries as $delivery): ?>
                  <option value="<?= $delivery["delivery_id"] ?>">
                    Order #<?= $delivery["order_id"] ?> — <?= htmlspecialchars($delivery["customer_name"]) ?> (<?= htmlspecialchars($delivery["driver_name"]) ?>, <?= htmlspecialchars($delivery["scheduled_date"]) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="reason">Delay Reason</label>
              <select id="reason" name="reason" required>
                <option value="">Select reason</option>
                <?php foreach ($reasonOptions as $value => $label): ?>
                  <option value="<?= $value ?>"><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="newExpectedDate">New Expected Date</label>
              <input type="date" id="newExpectedDate" name="new_expected_date" required>
            </div>
            <div class="form-group full-width">
              <label for="actionTaken">Action Taken</label>
              <textarea id="actionTaken" name="action_taken"></textarea>
            </div>
          </div>
          <div class="button-row"><button class="btn" type="submit">Save Delay</button></div>
        </form>
      </section>

      <section class="panel">
        <h3>Currently Delayed</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Order ID</th><th>Customer</th><th>Driver</th><th>New Date</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (empty($delayedDeliveries) || mysqli_num_rows($delayedDeliveries) === 0): ?>
                <tr><td colspan="5">No delayed deliveries.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($delayedDeliveries)): ?>
                <tr>
                  <td>#<?= $row["order_id"] ?></td>
                  <td><?= htmlspecialchars($row["customer_name"]) ?></td>
                  <td><?= htmlspecialchars($row["driver_name"]) ?></td>
                  <td><?= htmlspecialchars($row["scheduled_date"]) ?></td>
                  <td>
                    <form method="post" action="delayed_deliveries.php" style="display:inline;">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                      <input type="hidden" name="action" value="resolve">
                      <input type="hidden" name="delivery_id" value="<?= $row["delivery_id"] ?>">
                      <button class="btn" type="submit">Back on Schedule</button>
                    </form>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($delayedPage, $totalDelayed, $perPage, "delayed_page"); ?>
      </section>

      <section class="panel">
        <h3>Delay History</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Order ID</th><th>Description</th><th>Recorded</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($delayIssues) === 0): ?>
                <tr><td colspan="3">No delay records yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($delayIssues)): ?>
                <tr>
                  <td>#<?= $row["order_id"] ?></td>
                  <td><?= htmlspecialchars($row["issue_description"]) ?></td>
                  <td><?= htmlspecialchars($row["issue_date"]) ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($historyPage, $totalDelayIssues, $perPage, "history_page"); ?>
      </section>
    </main>
  </div>
</body>
</html>
