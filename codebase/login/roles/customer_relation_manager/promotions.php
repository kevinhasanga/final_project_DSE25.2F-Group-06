<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Customer Relationship Officer');

$activePage = "promotions";
$currentEmployeeId = getCurrentEmployeeId($connection, (int) $_SESSION["user_id"]);
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: promotions.php");
        exit();
    }

    $action = $_POST["action"] ?? "";

    if ($action === "send") {
        $title = trim($_POST["title"]);
        $message = trim($_POST["message"]);
        $customerGroup = $_POST["customer_group"];

        $statement = mysqli_prepare(
            $connection,
            "INSERT INTO promotional_notification (title, message, customer_group, sent_by, sent_at)
             VALUES (?, ?, ?, ?, NOW())"
        );
        mysqli_stmt_bind_param($statement, "sssi", $title, $message, $customerGroup, $currentEmployeeId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Promotional notification sent.");
        header("Location: promotions.php");
        exit();
    }

    if ($action === "delete") {
        $notificationId = (int) $_POST["notification_id"];
        $statement = mysqli_prepare($connection, "DELETE FROM promotional_notification WHERE notification_id = ?");
        mysqli_stmt_bind_param($statement, "i", $notificationId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Notification deleted.");
        header("Location: promotions.php");
        exit();
    }
}

$perPage = 10;
$totalNotifications = countRows($connection, "SELECT COUNT(*) FROM promotional_notification pn JOIN employee e ON e.employee_id = pn.sent_by");
$totalNotificationPages = max(1, (int) ceil($totalNotifications / $perPage));
$currentPage = min(getCurrentPage(), $totalNotificationPages);
$offset = ($currentPage - 1) * $perPage;

$notifications = mysqli_query(
    $connection,
    "SELECT pn.notification_id, pn.title, pn.message, pn.customer_group, pn.sent_at, e.full_name AS sent_by_name
     FROM promotional_notification pn
     JOIN employee e ON e.employee_id = pn.sent_by
     ORDER BY pn.sent_at DESC
     LIMIT $perPage OFFSET $offset"
);

$groupOptions = ["all" => "All Customers", "loyalty" => "Loyalty Members", "corporate" => "Corporate Customers"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Promotions</title><link rel="stylesheet" href="css/cro_style.css">
</head>
<body>
  <header class="topbar"><h1>Customer Relationship Officer</h1><p>Send promotional notifications to customer groups</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Promotions</h2><p>Create and send promotional messages to customer groups.</p></section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3>Create Notification</h3>
        <form method="post" action="promotions.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="send">
          <div class="form-grid">
            <div class="form-group">
              <label for="title">Title</label>
              <input id="title" name="title" required>
            </div>
            <div class="form-group">
              <label for="customerGroup">Customer Group</label>
              <select id="customerGroup" name="customer_group" required>
                <?php foreach ($groupOptions as $value => $label): ?>
                  <option value="<?= $value ?>"><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group full-width">
              <label for="message">Message</label>
              <textarea id="message" name="message" required></textarea>
            </div>
          </div>
          <div class="button-row"><button class="btn" type="submit">Send Notification</button></div>
        </form>
      </section>

      <section class="panel">
        <h3>Sent Notifications</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Title</th><th>Group</th><th>Message</th><th>Sent By</th><th>Sent At</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($notifications) === 0): ?>
                <tr><td colspan="6">No promotional notifications sent yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($notifications)): ?>
                <tr>
                  <td><?= htmlspecialchars($row["title"]) ?></td>
                  <td><?= htmlspecialchars($groupOptions[$row["customer_group"]] ?? $row["customer_group"]) ?></td>
                  <td><?= htmlspecialchars($row["message"]) ?></td>
                  <td><?= htmlspecialchars($row["sent_by_name"]) ?></td>
                  <td><?= htmlspecialchars($row["sent_at"]) ?></td>
                  <td>
                    <form method="post" action="promotions.php" style="display:inline;">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="notification_id" value="<?= $row["notification_id"] ?>">
                      <button class="btn danger" type="submit" onclick="return confirm('Delete this notification record?');">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalNotifications, $perPage); ?>
      </section>
    </main>
  </div>
  <script src="js/validate.js"></script>
</body>
</html>
