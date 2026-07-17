<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/helpers.php';
require_login('Admin');

$activePage = "settings";
$currentUserId = (int) $_SESSION["user_id"];
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: settings.php");
        exit();
    }

    $action = $_POST["action"] ?? "";

    if ($action === "save_system") {
        $settingId = (int) ($_POST["setting_id"] ?? 0);
        $settingName = trim($_POST["setting_name"]);
        $settingValue = trim($_POST["setting_value"]);
        $description = trim($_POST["description"] ?? "");
        $status = $_POST["status"];

        if ($settingId > 0) {
            $statement = mysqli_prepare(
                $connection,
                "UPDATE system_setting SET setting_name = ?, setting_value = ?, description = ?, status = ?, updated_at = NOW() WHERE setting_id = ?"
            );
            mysqli_stmt_bind_param($statement, "ssssi", $settingName, $settingValue, $description, $status, $settingId);
        } else {
            $statement = mysqli_prepare(
                $connection,
                "INSERT INTO system_setting (setting_name, setting_value, description, status, updated_at) VALUES (?, ?, ?, ?, NOW())"
            );
            mysqli_stmt_bind_param($statement, "ssss", $settingName, $settingValue, $description, $status);
        }
        mysqli_stmt_execute($statement);
        $settingId = $settingId ?: mysqli_insert_id($connection);
        mysqli_stmt_close($statement);
        logAudit($connection, $currentUserId, "saved system setting: $settingName", "system_setting", $settingId);
        setFlash("System setting saved.");
        header("Location: settings.php");
        exit();
    }

    if ($action === "delete_system") {
        $settingId = (int) $_POST["setting_id"];
        $statement = mysqli_prepare($connection, "DELETE FROM system_setting WHERE setting_id = ?");
        mysqli_stmt_bind_param($statement, "i", $settingId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("System setting deleted.");
        header("Location: settings.php");
        exit();
    }

    if ($action === "save_notification") {
        $notificationId = (int) ($_POST["notification_id"] ?? 0);
        $notificationType = trim($_POST["notification_type"]);
        $receiverRole = $_POST["receiver_role"];
        $channel = $_POST["channel"];
        $status = $_POST["status"];

        if ($notificationId > 0) {
            $statement = mysqli_prepare(
                $connection,
                "UPDATE notification_setting SET notification_type = ?, receiver_role = ?, channel = ?, status = ? WHERE notification_id = ?"
            );
            mysqli_stmt_bind_param($statement, "ssssi", $notificationType, $receiverRole, $channel, $status, $notificationId);
        } else {
            $statement = mysqli_prepare(
                $connection,
                "INSERT INTO notification_setting (notification_type, receiver_role, channel, status) VALUES (?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($statement, "ssss", $notificationType, $receiverRole, $channel, $status);
        }
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Notification setting saved.");
        header("Location: settings.php");
        exit();
    }

    if ($action === "delete_notification") {
        $notificationId = (int) $_POST["notification_id"];
        $statement = mysqli_prepare($connection, "DELETE FROM notification_setting WHERE notification_id = ?");
        mysqli_stmt_bind_param($statement, "i", $notificationId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Notification setting deleted.");
        header("Location: settings.php");
        exit();
    }
}

$roleOptions = [
    "system_admin" => "System Administrator", "ceo_head_manager" => "CEO / Head Manager", "supervisor" => "Supervisor",
    "inventory_manager" => "Inventory Manager", "order_processing_officer" => "Order Processing Officer",
    "customer_relation_manager" => "Customer Relationship Officer", "distribution_manager" => "Distribution Manager",
    "driver" => "Driver", "financial_officer" => "Finance Officer",
];

$editSystem = null;
if (isset($_GET["edit_system"])) {
    $editId = (int) $_GET["edit_system"];
    $statement = mysqli_prepare($connection, "SELECT * FROM system_setting WHERE setting_id = ?");
    mysqli_stmt_bind_param($statement, "i", $editId);
    mysqli_stmt_execute($statement);
    $editSystem = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);
}

$editNotification = null;
if (isset($_GET["edit_notification"])) {
    $editId = (int) $_GET["edit_notification"];
    $statement = mysqli_prepare($connection, "SELECT * FROM notification_setting WHERE notification_id = ?");
    mysqli_stmt_bind_param($statement, "i", $editId);
    mysqli_stmt_execute($statement);
    $editNotification = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);
}

$systemSettings = mysqli_query($connection, "SELECT * FROM system_setting ORDER BY setting_name");
$notificationSettings = mysqli_query($connection, "SELECT * FROM notification_setting ORDER BY notification_type");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Settings</title>
  <link rel="stylesheet" href="css/sa_style.css">
</head>
<body>
  <header class="topbar"><h1>System Administrator</h1><p>Configure system settings and manage notification settings</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Settings</h2><p>Update application configuration and notification preferences.</p></section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3><?= $editSystem ? "Edit System Setting" : "Add System Setting" ?></h3>
        <form method="post" action="settings.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="save_system">
          <input type="hidden" name="setting_id" value="<?= htmlspecialchars($editSystem["setting_id"] ?? "") ?>">
          <div class="form-grid">
            <div class="form-group">
              <label for="settingName">Setting Name</label>
              <input type="text" id="settingName" name="setting_name" value="<?= htmlspecialchars($editSystem["setting_name"] ?? "") ?>" required>
            </div>
            <div class="form-group">
              <label for="settingValue">Setting Value</label>
              <input type="text" id="settingValue" name="setting_value" value="<?= htmlspecialchars($editSystem["setting_value"] ?? "") ?>" required>
            </div>
            <div class="form-group">
              <label for="systemStatus">Status</label>
              <select id="systemStatus" name="status" required>
                <option value="active" <?= ($editSystem["status"] ?? "active") === "active" ? "selected" : "" ?>>Active</option>
                <option value="inactive" <?= ($editSystem["status"] ?? "") === "inactive" ? "selected" : "" ?>>Inactive</option>
              </select>
            </div>
            <div class="form-group full-width">
              <label for="description">Description</label>
              <textarea id="description" name="description"><?= htmlspecialchars($editSystem["description"] ?? "") ?></textarea>
            </div>
          </div>
          <div class="button-row">
            <button class="btn" type="submit">Save Setting</button>
            <?php if ($editSystem): ?><a class="btn secondary" href="settings.php">Cancel</a><?php endif; ?>
          </div>
        </form>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Name</th><th>Value</th><th>Status</th><th>Updated</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($systemSettings) === 0): ?><tr><td colspan="5">No system settings loaded yet.</td></tr><?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($systemSettings)): ?>
                <tr>
                  <td><?= htmlspecialchars($row["setting_name"]) ?></td>
                  <td><?= htmlspecialchars($row["setting_value"]) ?></td>
                  <td><span class="status <?= $row["status"] === "active" ? "resolved" : "pending" ?>"><?= htmlspecialchars(ucfirst($row["status"])) ?></span></td>
                  <td><?= htmlspecialchars($row["updated_at"]) ?></td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;">
                      <a class="btn secondary" href="settings.php?edit_system=<?= $row["setting_id"] ?>">Edit</a>
                      <form method="post" action="settings.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="action" value="delete_system">
                        <input type="hidden" name="setting_id" value="<?= $row["setting_id"] ?>">
                        <button class="btn danger" type="submit" onclick="return confirm('Delete this setting?');">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section class="panel">
        <h3><?= $editNotification ? "Edit Notification Setting" : "Add Notification Setting" ?></h3>
        <form method="post" action="settings.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="save_notification">
          <input type="hidden" name="notification_id" value="<?= htmlspecialchars($editNotification["notification_id"] ?? "") ?>">
          <div class="form-grid">
            <div class="form-group">
              <label for="notificationType">Notification Type</label>
              <input type="text" id="notificationType" name="notification_type" value="<?= htmlspecialchars($editNotification["notification_type"] ?? "") ?>" placeholder="e.g. Low Stock Alert" required>
            </div>
            <div class="form-group">
              <label for="receiverRole">Receiver Role</label>
              <select id="receiverRole" name="receiver_role" required>
                <option value="">Select role</option>
                <?php foreach ($roleOptions as $value => $label): ?>
                  <option value="<?= $value ?>" <?= ($editNotification["receiver_role"] ?? "") === $value ? "selected" : "" ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="channel">Channel</label>
              <select id="channel" name="channel" required>
                <option value="email" <?= ($editNotification["channel"] ?? "") === "email" ? "selected" : "" ?>>Email</option>
                <option value="system" <?= ($editNotification["channel"] ?? "") === "system" ? "selected" : "" ?>>System</option>
                <option value="sms" <?= ($editNotification["channel"] ?? "") === "sms" ? "selected" : "" ?>>SMS</option>
              </select>
            </div>
            <div class="form-group">
              <label for="notificationStatus">Status</label>
              <select id="notificationStatus" name="status" required>
                <option value="enabled" <?= ($editNotification["status"] ?? "enabled") === "enabled" ? "selected" : "" ?>>Enabled</option>
                <option value="disabled" <?= ($editNotification["status"] ?? "") === "disabled" ? "selected" : "" ?>>Disabled</option>
              </select>
            </div>
          </div>
          <div class="button-row">
            <button class="btn" type="submit">Save Setting</button>
            <?php if ($editNotification): ?><a class="btn secondary" href="settings.php">Cancel</a><?php endif; ?>
          </div>
        </form>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Type</th><th>Receiver Role</th><th>Channel</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($notificationSettings) === 0): ?><tr><td colspan="5">No notification settings loaded yet.</td></tr><?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($notificationSettings)): ?>
                <tr>
                  <td><?= htmlspecialchars($row["notification_type"]) ?></td>
                  <td><?= htmlspecialchars($roleOptions[$row["receiver_role"]] ?? $row["receiver_role"]) ?></td>
                  <td><?= htmlspecialchars(ucfirst($row["channel"])) ?></td>
                  <td><span class="status <?= $row["status"] === "enabled" ? "resolved" : "pending" ?>"><?= htmlspecialchars(ucfirst($row["status"])) ?></span></td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;">
                      <a class="btn secondary" href="settings.php?edit_notification=<?= $row["notification_id"] ?>">Edit</a>
                      <form method="post" action="settings.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="action" value="delete_notification">
                        <input type="hidden" name="notification_id" value="<?= $row["notification_id"] ?>">
                        <button class="btn danger" type="submit" onclick="return confirm('Delete this notification setting?');">Delete</button>
                      </form>
                    </div>
                  </td>
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
