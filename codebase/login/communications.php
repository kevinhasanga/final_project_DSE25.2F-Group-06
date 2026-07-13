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
            ["Assigned Routes", "roles/driver/assigned_routes.html"],
            ["Delivery Status", "roles/driver/delivery_status.html"],
            ["Delivery Issues", "roles/driver/delivery_issues.html"],
            ["Proof of Delivery", "roles/driver/proof_of_delivery.html"],
            ["Fuel Usage", "roles/driver/fuel_usage.html"],
        ],
    ],
    "order_processing_officer" => [
        "label" => "Order Processing",
        "links" => [
            ["Dashboard", "roles/order_%20processing_officer/order_processing_officer_dashboard.php"],
            ["Sales Orders", "roles/order_%20processing_officer/sales_order_management.html"],
            ["Stock Availability", "roles/order_%20processing_officer/stock_availability.html"],
            ["Credit Approval", "roles/order_%20processing_officer/credit_order_approval.html"],
            ["Invoices", "roles/order_%20processing_officer/invoice_generation.html"],
            ["Discounts & Taxes", "roles/order_%20processing_officer/discount_tax_calculation.html"],
            ["Order Status", "roles/order_%20processing_officer/order_status_update.html"],
            ["Order Reports", "roles/order_%20processing_officer/order_reports.html"],
            ["Daily Sales", "roles/order_%20processing_officer/daily_sales_totals.html"],
        ],
    ],
    "customer_relation_manager" => [
        "label" => "Customer Relations",
        "links" => [
            ["Dashboard", "roles/customer_relation_manager/customer_relationship_officer_dashboard.php"],
            ["Add Customer", "roles/customer_relation_manager/add_customer.html"],
            ["Update/Search Customer", "roles/customer_relation_manager/update_search_customer.html"],
            ["Purchase History", "roles/customer_relation_manager/customer_purchase_history.html"],
            ["Complaint Management", "roles/customer_relation_manager/complaint_management.html"],
            ["Loyalty Programs", "roles/customer_relation_manager/loyalty_program_management.html"],
            ["Promotional Notifications", "roles/customer_relation_manager/promotional_notification.html"],
            ["Activity Reports", "roles/customer_relation_manager/customer_activity_reports.html"],
        ],
    ],
    "supervisor" => [
        "label" => "Supervisor",
        "links" => [
            ["Dashboard", "roles/supervisor/supervisor_dashboard.php"],
            ["Attendance", "roles/supervisor/employee_attendance.html"],
            ["Clock Times", "roles/supervisor/clock_times.html"],
            ["Overtime", "roles/supervisor/overtime_records.html"],
            ["Leave Requests", "roles/supervisor/leave_requests.html"],
            ["Employee Details", "roles/supervisor/employee_details.html"],
            ["Salary Calculation", "roles/supervisor/salary_calculation.html"],
            ["Payroll Sheets", "roles/supervisor/payroll_sheets.html"],
            ["Salary Slips", "roles/supervisor/salary_slips.html"],
            ["Performance", "roles/supervisor/employee_performance.html"],
            ["Attendance Reports", "roles/supervisor/attendance_reports.html"],
        ],
    ],
    "financial_officer" => [
        "label" => "Finance Officer",
        "links" => [
            ["Dashboard", "roles/finance_officer/finance_officer_dashboard.php"],
            ["Income & Expenses", "roles/finance_officer/daily_income_expenses.html"],
            ["Supplier Payments", "roles/finance_officer/supplier_payments.html"],
            ["Receivables", "roles/finance_officer/receivables.html"],
            ["Profit & Loss", "roles/finance_officer/profit_loss_statement.html"],
            ["Cash Flow", "roles/finance_officer/cash_flow_report.html"],
            ["Summaries", "roles/finance_officer/financial_summaries.html"],
            ["Budget Utilization", "roles/finance_officer/budget_utilization.html"],
            ["Tax Records", "roles/finance_officer/tax_records.html"],
            ["Reconciliation", "roles/finance_officer/account_reconciliation.html"],
        ],
    ],
    "inventory_manager" => [
        "label" => "Inventory Manager",
        "links" => [
            ["Dashboard", "roles/inventory_manager/inventory_manager_dashboard.php"],
            ["Products", "roles/inventory_manager/product_management.html"],
            ["Pricing & Levels", "roles/inventory_manager/product_pricing.html"],
            ["Incoming Stock", "roles/inventory_manager/incoming_stock.html"],
            ["Expiry & Damage", "roles/inventory_manager/expiry_damage_tracking.html"],
            ["Returns & Transfers", "roles/inventory_manager/stock_returns_transfers.html"],
            ["Stock Reports", "roles/inventory_manager/stock_reports.html"],
            ["Low Stock Alerts", "roles/inventory_manager/low_stock_alerts.html"],
            ["Inventory Turnover", "roles/inventory_manager/inventory_turnover.html"],
        ],
    ],
    "distribution_manager" => [
        "label" => "Distribution Manager",
        "links" => [
            ["Dashboard", "roles/distribution_manager/distribution_manager_dashboard.php"],
            ["Confirmed Orders", "roles/distribution_manager/confirmed_orders.html"],
            ["Delivery Schedules", "roles/distribution_manager/delivery_schedule.html"],
            ["Driver & Vehicle", "roles/distribution_manager/driver_vehicle_assignment.html"],
            ["Route Planning", "roles/distribution_manager/route_optimization.html"],
            ["Dispatched Orders", "roles/distribution_manager/dispatched_orders.html"],
            ["Delivery Progress", "roles/distribution_manager/delivery_progress.html"],
            ["Transport Costs", "roles/distribution_manager/transportation_costs.html"],
            ["Reports", "roles/distribution_manager/delivery_reports.html"],
            ["Delayed Deliveries", "roles/distribution_manager/delayed_deliveries.html"],
        ],
    ],
    "system_admin" => [
        "label" => "System Administrator",
        "links" => [
            ["Dashboard", "roles/system_administrator/system_administrator_dashboard.php"],
            ["Create Users", "roles/system_administrator/create_user_accounts.html"],
            ["Assign Roles", "roles/system_administrator/assign_user_roles.html"],
            ["Access Privileges", "roles/system_administrator/access_privileges.html"],
            ["Update Users", "roles/system_administrator/update_user_information.html"],
            ["Reset Passwords", "roles/system_administrator/reset_passwords.html"],
            ["Account Status", "roles/system_administrator/account_status.html"],
            ["Activity Logs", "roles/system_administrator/user_activity_logs.html"],
            ["Login History", "roles/system_administrator/login_history.html"],
            ["System Settings", "roles/system_administrator/system_settings.html"],
            ["Notifications", "roles/system_administrator/notification_settings.html"],
            ["Backups", "roles/system_administrator/database_backups.html"],
            ["Restore Data", "roles/system_administrator/restore_system_data.html"],
            ["System Errors", "roles/system_administrator/system_errors.html"],
            ["Audit Reports", "roles/system_administrator/audit_reports.html"],
        ],
    ],
    "ceo_head_manager" => [
        "label" => "CEO / Head Manager",
        "links" => [
            ["Dashboard", "roles/ceo_head_manager/ceo_dashboard.php"],
            ["Login & Profile", "roles/ceo_head_manager/login_profile.html"],
            ["Sales Performance", "roles/ceo_head_manager/sales_performance.html"],
            ["Inventory Valuation", "roles/ceo_head_manager/inventory_valuation.html"],
            ["Stock Movement", "roles/ceo_head_manager/stock_movement_reports.html"],
            ["Delivery Reports", "roles/ceo_head_manager/delivery_performance.html"],
            ["Profit & Loss", "roles/ceo_head_manager/profit_loss_statements.html"],
            ["Revenue Growth", "roles/ceo_head_manager/revenue_growth.html"],
            ["Budget Approval", "roles/ceo_head_manager/budget_approval.html"],
            ["Major Purchases", "roles/ceo_head_manager/major_purchases.html"],
            ["Discount Policies", "roles/ceo_head_manager/discount_policies.html"],
            ["Department Targets", "roles/ceo_head_manager/departmental_targets.html"],
            ["Employee Reports", "roles/ceo_head_manager/employee_performance_reports.html"],
            ["Strategic Reports", "roles/ceo_head_manager/strategic_reports.html"],
            ["System Activities", "roles/ceo_head_manager/system_activities.html"],
            ["Audit Logs", "roles/ceo_head_manager/audit_logs.html"],
            ["Complaints", "roles/ceo_head_manager/escalated_complaints.html"],
            ["Expansion Plans", "roles/ceo_head_manager/business_expansion.html"],
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
