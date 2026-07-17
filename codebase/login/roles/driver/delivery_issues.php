<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/helpers.php';
require_login('Driver');

$activePage = "issues";
$currentDriverId = getCurrentEmployeeId($connection, (int) $_SESSION["user_id"]);
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: delivery_issues.php");
        exit();
    }

    $action = $_POST["action"] ?? "";

    if ($action === "report") {
        $deliveryId = (int) $_POST["delivery_id"];
        $issueType = $_POST["issue_type"];
        $priority = $_POST["priority"];
        $description = trim($_POST["description"]);
        $fullDescription = "[" . ucfirst($priority) . " priority] " . str_replace("_", " ", ucfirst($issueType)) . ": " . $description;

        $statement = mysqli_prepare(
            $connection,
            "INSERT INTO delivery_issue (delivery_id, reported_by, issue_description, issue_date)
             VALUES (?, ?, ?, NOW())"
        );
        mysqli_stmt_bind_param($statement, "iis", $deliveryId, $currentDriverId, $fullDescription);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Delivery issue reported.");
        header("Location: delivery_issues.php");
        exit();
    }
}

$deliveries = getDriverDeliveries($connection, $currentDriverId);

$issues = mysqli_query(
    $connection,
    "SELECT di.issue_id, d.order_id, di.issue_description, di.issue_date
     FROM delivery_issue di
     JOIN delivery d ON d.delivery_id = di.delivery_id
     WHERE d.driver_id = $currentDriverId
     ORDER BY di.issue_date DESC"
);

$issueTypeOptions = ["wrong_address" => "Wrong Address", "customer_unavailable" => "Customer Unavailable", "vehicle_issue" => "Vehicle Issue", "traffic_delay" => "Traffic Delay", "damaged_goods" => "Damaged Goods", "other" => "Other"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Delivery Issues</title>
  <link rel="stylesheet" href="css/driver_style.css">
</head>
<body>
  <header class="topbar">
    <h1>Driver</h1>
    <p>Report delivery issues</p>
  </header>

  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title">
        <h2>Delivery Issues</h2>
        <p>Report problems such as wrong address, vehicle issue, delay, or customer unavailable.</p>
      </section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3>Report Issue</h3>
        <form method="post" action="delivery_issues.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="report">
          <div class="form-grid">
            <div class="form-group">
              <label for="deliveryId">Delivery</label>
              <select id="deliveryId" name="delivery_id" required>
                <option value="">Select delivery</option>
                <?php foreach ($deliveries as $delivery): ?>
                  <option value="<?= $delivery["delivery_id"] ?>">
                    Order #<?= $delivery["order_id"] ?> — <?= htmlspecialchars($delivery["customer_name"]) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="issueType">Issue Type</label>
              <select id="issueType" name="issue_type" required>
                <option value="">Select issue type</option>
                <?php foreach ($issueTypeOptions as $value => $label): ?>
                  <option value="<?= $value ?>"><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="priority">Priority</label>
              <select id="priority" name="priority" required>
                <option value="">Select priority</option>
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
              </select>
            </div>
            <div class="form-group full-width">
              <label for="description">Issue Description</label>
              <textarea id="description" name="description" required></textarea>
            </div>
          </div>
          <div class="button-row"><button class="btn" type="submit">Submit Issue</button></div>
        </form>
      </section>

      <section class="panel">
        <h3>Reported Issues</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Order ID</th><th>Description</th><th>Reported</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($issues) === 0): ?>
                <tr><td colspan="3">No delivery issues loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($issues)): ?>
                <tr>
                  <td>#<?= $row["order_id"] ?></td>
                  <td><?= htmlspecialchars($row["issue_description"]) ?></td>
                  <td><?= htmlspecialchars($row["issue_date"]) ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
  <script src="js/validate.js"></script>
</body>
</html>
