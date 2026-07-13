<?php
require_once __DIR__ . '/../../auth.php';
require_login('Finance Officer');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Finance Officer Dashboard</title>
  <link rel="stylesheet" href="css/fo_style.css">
</head>
<body>
  <header class="topbar">
    <h1>Finance Officer</h1>
    <p>Income, expenses, payments, receivables, reports, and tax records</p>
  </header>

  <div class="layout">
    <nav class="sidebar">
      <h2><?= htmlspecialchars($_SESSION["full_name"] ?? $_SESSION["username"] ?? "User") ?></h2>
      <a class="active" href="finance_officer_dashboard.php">Dashboard</a>
      <a href="daily_income_expenses.html">Income & Expenses</a>
      <a href="supplier_payments.html">Supplier Payments</a>
      <a href="receivables.html">Receivables</a>
      <a href="profit_loss_statement.html">Profit & Loss</a>
      <a href="cash_flow_report.html">Cash Flow</a>
      <a href="financial_summaries.html">Summaries</a>
      <a href="budget_utilization.html">Budget Utilization</a>
      <a href="tax_records.html">Tax Records</a>
      <a href="account_reconciliation.html">Reconciliation</a>
      <a href="../../communications.php">Internal Mail</a>
      <a href="../../logout.php">Log out</a>
    </nav>

    <main class="content">
      <section class="page-title">
        <h2>Dashboard</h2>
        <p>Overview of daily finance activities.</p>
      </section>

      <section class="cards">
        <div class="card">
          <h3>Daily Income</h3>
          <p class="number" id="dailyIncome">0</p>
          <p>Income recorded today</p>
        </div>
        <div class="card">
          <h3>Daily Expenses</h3>
          <p class="number" id="dailyExpenses">0</p>
          <p>Expenses recorded today</p>
        </div>
        <div class="card">
          <h3>Receivables</h3>
          <p class="number" id="outstandingReceivables">0</p>
          <p>Outstanding amount</p>
        </div>
        <div class="card">
          <h3>Budget Used</h3>
          <p class="number" id="budgetUsed">0%</p>
          <p>Current utilization</p>
        </div>
      </section>

      <section class="panel">
        <h3>Recent Financial Records</h3>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Record ID</th>
                <th>Date</th>
                <th>Type</th>
                <th>Description</th>
                <th>Amount</th>
              </tr>
            </thead>
            <tbody id="recentFinanceTable">
              <tr>
                <td colspan="5">No financial records loaded yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
</body>
</html>
