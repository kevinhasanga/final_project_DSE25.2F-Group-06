<?php
require_once "auth.php";
require_once "config.php";

requireAuthenticated();

$currentUserId = (int) $_SESSION["user_id"];
$allowedViews = ["inbox", "sent", "compose", "message"];
$view = $_GET["view"] ?? "inbox";
$view = in_array($view, $allowedViews, true) ? $view : "inbox";
$search = trim($_GET["search"] ?? "");
$error = "";

if (!isset($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$currentUserSql = "SELECT ua.user_id, ua.username, ua.email, ua.role_name,
                          COALESCE(e.full_name, ua.username) AS display_name
                   FROM user_account ua
                   LEFT JOIN employee e ON e.user_id = ua.user_id
                   WHERE ua.user_id = ? AND ua.is_active = 1";
$currentUserStatement = mysqli_prepare($connection, $currentUserSql);
mysqli_stmt_bind_param($currentUserStatement, "i", $currentUserId);
mysqli_stmt_execute($currentUserStatement);
$currentUser = mysqli_fetch_assoc(mysqli_stmt_get_result($currentUserStatement));
mysqli_stmt_close($currentUserStatement);

if (!$currentUser) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$_SESSION["full_name"] = $currentUser["display_name"];

function redirectWithMailMessage($message, $view = "inbox")
{
    $_SESSION["mail_flash"] = $message;
    header("Location: communications.php?view=" . urlencode($view));
    exit();
}

function getMailInitials($name)
{
    $parts = preg_split('/\s+/', trim($name));
    $initials = "";

    foreach (array_slice($parts, 0, 2) as $part) {
        if ($part !== "") {
            $initials .= strtoupper(substr($part, 0, 1));
        }
    }

    return $initials ?: "U";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrfToken = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $csrfToken)) {
        $error = "Your session expired. Refresh the page and try again.";
        $view = "compose";
    } else {
        $recipientEmail = trim($_POST["recipient_email"] ?? "");
        $subject = trim($_POST["subject"] ?? "");
        $message = trim($_POST["message"] ?? "");

        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            $error = "Select a valid employee email address.";
        } elseif ($subject === "" || strlen($subject) > 150) {
            $error = "Enter a subject of no more than 150 characters.";
        } elseif ($message === "") {
            $error = "Write a message before sending the email.";
        } else {
            $recipientSql = "SELECT user_id FROM user_account
                             WHERE email = ? AND is_active = 1";
            $recipientStatement = mysqli_prepare($connection, $recipientSql);
            mysqli_stmt_bind_param($recipientStatement, "s", $recipientEmail);
            mysqli_stmt_execute($recipientStatement);
            $recipient = mysqli_fetch_assoc(mysqli_stmt_get_result($recipientStatement));
            mysqli_stmt_close($recipientStatement);

            if (!$recipient) {
                $error = "The selected employee account is unavailable.";
            } elseif ((int) $recipient["user_id"] === $currentUserId) {
                $error = "Choose another employee as the recipient.";
            } else {
                $recipientId = (int) $recipient["user_id"];
                $insertSql = "INSERT INTO internal_email
                              (sender_id, recipient_id, subject, message, sent_at)
                              VALUES (?, ?, ?, ?, NOW())";
                $insertStatement = mysqli_prepare($connection, $insertSql);
                mysqli_stmt_bind_param(
                    $insertStatement,
                    "iiss",
                    $currentUserId,
                    $recipientId,
                    $subject,
                    $message
                );
                mysqli_stmt_execute($insertStatement);
                mysqli_stmt_close($insertStatement);
                redirectWithMailMessage("Email sent to " . $recipientEmail . ".", "sent");
            }
        }

        $view = "compose";
    }
}

$flash = $_SESSION["mail_flash"] ?? "";
unset($_SESSION["mail_flash"]);

$recipientsSql = "SELECT ua.email,
                         COALESCE(e.full_name, ua.username) AS display_name
                  FROM user_account ua
                  LEFT JOIN employee e ON e.user_id = ua.user_id
                  WHERE ua.is_active = 1 AND ua.user_id <> ?
                  ORDER BY display_name";
$recipientsStatement = mysqli_prepare($connection, $recipientsSql);
mysqli_stmt_bind_param($recipientsStatement, "i", $currentUserId);
mysqli_stmt_execute($recipientsStatement);
$recipients = mysqli_fetch_all(mysqli_stmt_get_result($recipientsStatement), MYSQLI_ASSOC);
mysqli_stmt_close($recipientsStatement);

$countSql = "SELECT COUNT(*) AS inbox_count,
                    SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) AS unread_count
             FROM internal_email WHERE recipient_id = ?";
$countStatement = mysqli_prepare($connection, $countSql);
mysqli_stmt_bind_param($countStatement, "i", $currentUserId);
mysqli_stmt_execute($countStatement);
$mailCounts = mysqli_fetch_assoc(mysqli_stmt_get_result($countStatement));
mysqli_stmt_close($countStatement);
$inboxCount = (int) $mailCounts["inbox_count"];
$unreadCount = (int) ($mailCounts["unread_count"] ?? 0);

$selectedMessage = null;
$selectedFolder = "inbox";

if ($view === "message") {
    $emailId = (int) ($_GET["id"] ?? 0);
    $messageSql = "SELECT ie.email_id, ie.sender_id, ie.recipient_id, ie.subject,
                          ie.message, ie.sent_at, ie.read_at,
                          sender.email AS sender_email,
                          COALESCE(sender_employee.full_name, sender.username) AS sender_name,
                          recipient.email AS recipient_email,
                          COALESCE(recipient_employee.full_name, recipient.username) AS recipient_name
                   FROM internal_email ie
                   JOIN user_account sender ON sender.user_id = ie.sender_id
                   JOIN user_account recipient ON recipient.user_id = ie.recipient_id
                   LEFT JOIN employee sender_employee ON sender_employee.user_id = sender.user_id
                   LEFT JOIN employee recipient_employee ON recipient_employee.user_id = recipient.user_id
                   WHERE ie.email_id = ? AND (ie.sender_id = ? OR ie.recipient_id = ?)";
    $messageStatement = mysqli_prepare($connection, $messageSql);
    mysqli_stmt_bind_param($messageStatement, "iii", $emailId, $currentUserId, $currentUserId);
    mysqli_stmt_execute($messageStatement);
    $selectedMessage = mysqli_fetch_assoc(mysqli_stmt_get_result($messageStatement));
    mysqli_stmt_close($messageStatement);

    if ($selectedMessage) {
        $selectedFolder = (int) $selectedMessage["sender_id"] === $currentUserId ? "sent" : "inbox";

        if ($selectedFolder === "inbox" && $selectedMessage["read_at"] === null) {
            $readSql = "UPDATE internal_email SET read_at = NOW()
                        WHERE email_id = ? AND recipient_id = ?";
            $readStatement = mysqli_prepare($connection, $readSql);
            mysqli_stmt_bind_param($readStatement, "ii", $emailId, $currentUserId);
            mysqli_stmt_execute($readStatement);
            mysqli_stmt_close($readStatement);
            $selectedMessage["read_at"] = date("Y-m-d H:i:s");
            $unreadCount = max(0, $unreadCount - 1);
        }
    } else {
        $view = "inbox";
        $error = "That email could not be found.";
    }
}

$messages = [];
if ($view === "inbox" || $view === "sent") {
    $searchLike = "%" . $search . "%";

    if ($view === "inbox") {
        $listSql = "SELECT ie.email_id, ie.subject, ie.message, ie.sent_at, ie.read_at,
                           sender.email AS contact_email,
                           COALESCE(sender_employee.full_name, sender.username) AS contact_name
                    FROM internal_email ie
                    JOIN user_account sender ON sender.user_id = ie.sender_id
                    LEFT JOIN employee sender_employee ON sender_employee.user_id = sender.user_id
                    WHERE ie.recipient_id = ?
                      AND (? = '' OR ie.subject LIKE ? OR ie.message LIKE ?
                           OR sender.email LIKE ? OR sender_employee.full_name LIKE ?)
                    ORDER BY ie.sent_at DESC
                    LIMIT 50";
    } else {
        $listSql = "SELECT ie.email_id, ie.subject, ie.message, ie.sent_at, ie.read_at,
                           recipient.email AS contact_email,
                           COALESCE(recipient_employee.full_name, recipient.username) AS contact_name
                    FROM internal_email ie
                    JOIN user_account recipient ON recipient.user_id = ie.recipient_id
                    LEFT JOIN employee recipient_employee ON recipient_employee.user_id = recipient.user_id
                    WHERE ie.sender_id = ?
                      AND (? = '' OR ie.subject LIKE ? OR ie.message LIKE ?
                           OR recipient.email LIKE ? OR recipient_employee.full_name LIKE ?)
                    ORDER BY ie.sent_at DESC
                    LIMIT 50";
    }

    $listStatement = mysqli_prepare($connection, $listSql);
    mysqli_stmt_bind_param(
        $listStatement,
        "isssss",
        $currentUserId,
        $search,
        $searchLike,
        $searchLike,
        $searchLike,
        $searchLike
    );
    mysqli_stmt_execute($listStatement);
    $messages = mysqli_fetch_all(mysqli_stmt_get_result($listStatement), MYSQLI_ASSOC);
    mysqli_stmt_close($listStatement);
}

$dashboard = getDashboard($_SESSION["role"]);
$pageTitle = $view === "sent" ? "Sent" : ($view === "compose" ? "New message" : "Inbox");
$role = normalizeRole($_SESSION["role"]);
$roleNavigation = [
    "driver" => [
        "label" => "Driver",
        "links" => [
            ["Dashboard", "roles/driver/driver_dashboard.php"],
            ["My Deliveries", "roles/driver/my_deliveries.php"],
            ["Delivery Issues", "roles/driver/delivery_issues.php"],
            ["Proof of Delivery", "roles/driver/proof_of_delivery.php"],
            ["Fuel Usage", "roles/driver/fuel_usage.php"],
        ],
    ],
    "order_processing_officer" => [
        "label" => "Order Processing",
        "links" => [
            ["Dashboard", "roles/order_%20processing_officer/order_processing_officer_dashboard.php"],
            ["Sales Orders", "roles/order_%20processing_officer/sales_orders.php"],
            ["Stock Availability", "roles/order_%20processing_officer/stock_availability.php"],
            ["Credit Approval", "roles/order_%20processing_officer/credit_approval.php"],
            ["Invoices", "roles/order_%20processing_officer/invoices.php"],
            ["Reports", "roles/order_%20processing_officer/reports.php"],
        ],
    ],
    "customer_relation_manager" => [
        "label" => "Customer Relations",
        "links" => [
            ["Dashboard", "roles/customer_relation_manager/customer_relationship_officer_dashboard.php"],
            ["Customers", "roles/customer_relation_manager/customers.php"],
            ["Purchase History", "roles/customer_relation_manager/purchase_history.php"],
            ["Complaints", "roles/customer_relation_manager/complaints.php"],
            ["Promotions", "roles/customer_relation_manager/promotions.php"],
            ["Reports", "roles/customer_relation_manager/reports.php"],
        ],
    ],
    "supervisor" => [
        "label" => "Supervisor",
        "links" => [
            ["Dashboard", "roles/supervisor/supervisor_dashboard.php"],
            ["Attendance", "roles/supervisor/attendance.php"],
            ["Leave Requests", "roles/supervisor/leave_requests.php"],
            ["Employees", "roles/supervisor/employees.php"],
            ["Payroll", "roles/supervisor/payroll.php"],
            ["Performance", "roles/supervisor/performance.php"],
        ],
    ],
    "financial_officer" => [
        "label" => "Finance Officer",
        "links" => [
            ["Dashboard", "roles/finance_officer/finance_officer_dashboard.php"],
            ["Income & Expenses", "roles/finance_officer/income_expenses.php"],
            ["Supplier Payments", "roles/finance_officer/supplier_payments.php"],
            ["Receivables", "roles/finance_officer/receivables.php"],
            ["Reports", "roles/finance_officer/reports.php"],
            ["Budget Utilization", "roles/finance_officer/budget_utilization.php"],
            ["Reconciliation", "roles/finance_officer/reconciliation.php"],
        ],
    ],
    "inventory_manager" => [
        "label" => "Inventory Manager",
        "links" => [
            ["Dashboard", "roles/inventory_manager/inventory_manager_dashboard.php"],
            ["Products", "roles/inventory_manager/products.php"],
            ["Stock Batches", "roles/inventory_manager/stock_batches.php"],
            ["Returns & Transfers", "roles/inventory_manager/stock_movements.php"],
            ["Reports", "roles/inventory_manager/reports.php"],
            ["Low Stock Alerts", "roles/inventory_manager/low_stock_alerts.php"],
        ],
    ],
    "distribution_manager" => [
        "label" => "Distribution Manager",
        "links" => [
            ["Dashboard", "roles/distribution_manager/distribution_manager_dashboard.php"],
            ["Confirmed Orders", "roles/distribution_manager/confirmed_orders.php"],
            ["Deliveries", "roles/distribution_manager/deliveries.php"],
            ["Delayed Deliveries", "roles/distribution_manager/delayed_deliveries.php"],
            ["Reports", "roles/distribution_manager/reports.php"],
        ],
    ],
    "system_admin" => [
        "label" => "System Administrator",
        "links" => [
            ["Dashboard", "roles/system_administrator/system_administrator_dashboard.php"],
            ["Users", "roles/system_administrator/users.php"],
            ["Access Privileges", "roles/system_administrator/access_privileges.php"],
            ["Settings", "roles/system_administrator/settings.php"],
            ["Backups", "roles/system_administrator/backups.php"],
            ["System Errors", "roles/system_administrator/system_errors.php"],
            ["Reports", "roles/system_administrator/reports.php"],
        ],
    ],
    "ceo_head_manager" => [
        "label" => "CEO / Head Manager",
        "links" => [
            ["Dashboard", "roles/ceo_head_manager/ceo_dashboard.php"],
            ["Profile", "roles/ceo_head_manager/profile.php"],
            ["Reports", "roles/ceo_head_manager/reports.php"],
            ["Approvals", "roles/ceo_head_manager/approvals.php"],
            ["Department Targets", "roles/ceo_head_manager/department_targets.php"],
            ["Complaints", "roles/ceo_head_manager/complaints.php"],
        ],
    ],
];
$navigation = $roleNavigation[$role] ?? ["label" => "NCC", "links" => [["Dashboard", $dashboard]]];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NCC Internal Mail</title>
  <link rel="stylesheet" href="css/communications.css">
</head>
<body>
  <header class="topbar">
    <h1><?= htmlspecialchars($navigation["label"]) ?></h1>
    <p>Internal company email</p>
  </header>

  <nav class="sidebar" aria-label="Main dashboard navigation">
    <div class="profile-avatar" aria-hidden="true">
      <?= htmlspecialchars(getMailInitials($currentUser["display_name"])) ?>
    </div>
    <h2><?= htmlspecialchars($currentUser["display_name"]) ?></h2>
    <p class="profile-email"><?= htmlspecialchars($currentUser["email"]) ?></p>

    <?php foreach ($navigation["links"] as $link): ?>
      <a href="<?= htmlspecialchars($link[1]) ?>"><?= htmlspecialchars($link[0]) ?></a>
    <?php endforeach; ?>
    <a class="active" href="communications.php">Internal Mail</a>
    <a href="logout.php">Log out</a>
  </nav>

  <main class="content">
    <section class="page-title">
      <h2>Internal Mail</h2>
      <p>Send and receive secure email using NCC employee addresses.</p>
    </section>

    <section class="mail-app">
      <header class="mail-header">
    <a class="brand" href="communications.php">
          <span class="brand-mark" aria-hidden="true">&#9993;</span>
          <span>NCC Internal Mail</span>
    </a>

    <form class="search" method="get">
      <input type="hidden" name="view" value="<?= htmlspecialchars($view === "sent" ? "sent" : "inbox") ?>">
      <label class="visually-hidden" for="mailSearch">Search mail</label>
          <span aria-hidden="true">&#128269;</span>
      <input
        id="mailSearch"
        name="search"
        type="search"
        placeholder="Search mail"
        value="<?= htmlspecialchars($search) ?>"
      >
    </form>

        <div class="mail-address"><?= htmlspecialchars($currentUser["email"]) ?></div>
      </header>

      <div class="mail-workspace">
        <aside class="mail-sidebar">
          <a class="compose-button" href="communications.php?view=compose">
            <span aria-hidden="true">+</span>
            Compose
          </a>

          <nav aria-label="Mailbox folders">
            <a class="<?= $view === "inbox" ? "active" : "" ?>" href="communications.php?view=inbox">
              <span aria-hidden="true">&#9993;</span>
              Inbox
              <?php if ($unreadCount > 0): ?><strong><?= $unreadCount ?></strong><?php endif; ?>
            </a>
            <a class="<?= $view === "sent" ? "active" : "" ?>" href="communications.php?view=sent">
              <span aria-hidden="true">&#10148;</span>
              Sent
            </a>
          </nav>
        </aside>

        <div class="mail-main">
    <?php if ($flash !== ""): ?>
      <div class="toast"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
      <div class="toast error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($view === "compose"): ?>
      <section class="compose-window">
        <div class="compose-title">New message</div>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">

          <div class="compose-row">
            <label for="recipientEmail">To</label>
            <select id="recipientEmail" name="recipient_email" required>
              <option value="">Select an employee email</option>
              <?php foreach ($recipients as $recipient): ?>
                <option value="<?= htmlspecialchars($recipient["email"]) ?>">
                  <?= htmlspecialchars($recipient["display_name"] . " <" . $recipient["email"] . ">") ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="compose-row">
            <label for="mailSubject">Subject</label>
            <input id="mailSubject" name="subject" type="text" maxlength="150" required>
          </div>

          <label class="visually-hidden" for="mailMessage">Message</label>
          <textarea id="mailMessage" name="message" placeholder="Write your message" required></textarea>

          <div class="compose-actions">
            <button type="submit">Send</button>
            <a href="communications.php?view=inbox">Discard</a>
          </div>
        </form>
      </section>
    <?php elseif ($view === "message" && $selectedMessage): ?>
      <section class="message-view">
        <div class="mail-toolbar">
          <a class="icon-link" href="communications.php?view=<?= htmlspecialchars($selectedFolder) ?>" aria-label="Back">←</a>
          <span><?= ucfirst(htmlspecialchars($selectedFolder)) ?></span>
        </div>
        <article>
          <h1><?= htmlspecialchars($selectedMessage["subject"]) ?></h1>
          <div class="message-sender">
            <div class="sender-avatar"><?= htmlspecialchars(getMailInitials($selectedMessage["sender_name"])) ?></div>
            <div>
              <strong><?= htmlspecialchars($selectedMessage["sender_name"]) ?></strong>
              <span>&lt;<?= htmlspecialchars($selectedMessage["sender_email"]) ?>&gt;</span>
              <p>to <?= htmlspecialchars($selectedMessage["recipient_email"]) ?></p>
            </div>
            <time><?= htmlspecialchars(date("M j, Y, g:i A", strtotime($selectedMessage["sent_at"]))) ?></time>
          </div>
          <div class="message-body"><?= nl2br(htmlspecialchars($selectedMessage["message"])) ?></div>
        </article>
      </section>
    <?php else: ?>
      <section class="mailbox">
        <div class="mail-toolbar">
          <h1><?= htmlspecialchars($pageTitle) ?></h1>
          <span><?= count($messages) ?> message<?= count($messages) === 1 ? "" : "s" ?></span>
        </div>

        <div class="message-list">
          <?php if (!$messages): ?>
            <div class="empty-mailbox">
              <span aria-hidden="true">✉</span>
              <h2>No emails here</h2>
              <p><?= $search !== "" ? "No messages match your search." : "Your " . strtolower($pageTitle) . " is empty." ?></p>
            </div>
          <?php endif; ?>

          <?php foreach ($messages as $message): ?>
            <?php
              $isUnread = $view === "inbox" && $message["read_at"] === null;
              $snippet = preg_replace('/\s+/', ' ', $message["message"]);
            ?>
            <a
              class="message-row <?= $isUnread ? "unread" : "" ?>"
              href="communications.php?view=message&id=<?= (int) $message["email_id"] ?>"
            >
              <div class="row-avatar"><?= htmlspecialchars(getMailInitials($message["contact_name"])) ?></div>
              <div class="row-contact">
                <strong><?= htmlspecialchars($message["contact_name"]) ?></strong>
                <span><?= htmlspecialchars($message["contact_email"]) ?></span>
              </div>
              <div class="row-content">
                <strong><?= htmlspecialchars($message["subject"]) ?></strong>
                <span>— <?= htmlspecialchars($snippet) ?></span>
              </div>
              <time><?= htmlspecialchars(date("M j", strtotime($message["sent_at"]))) ?></time>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
        </div>
      </div>
    </section>
  </main>
</body>
</html>
