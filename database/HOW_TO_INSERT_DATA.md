# How to insert data

Run `ncc_database.sql` first. After that, select the database:

```sql
USE ncc_database;
```

## Basic INSERT format

Write the table name, list the columns, and then provide one value for each column:

```sql
INSERT INTO table_name (column_1, column_2, column_3)
VALUES (value_1, value_2, value_3);
```

For an `AUTO_INCREMENT` ID, leave the ID column out. MySQL creates it automatically.

For example, this is the shape of an insert for the `customer` table. Replace every angle-bracket item with your real data:

```sql
INSERT INTO customer (name, contact_no, email, address, loyalty_points)
VALUES ('<customer name>', '<contact number>', '<email>', '<address>', <points>);
```

If an optional value is unknown, write `NULL` without quotation marks:

```sql
INSERT INTO supplier (supplier_name, contact_no, email, address, payment_terms)
VALUES ('<supplier name>', '<contact number>', NULL, NULL, '<payment terms>');
```

## Important value formats

- Text: `'your text'`
- Date: `'YYYY-MM-DD'`
- Date and time: `'YYYY-MM-DD HH:MM:SS'`
- Time: `'HH:MM:SS'`
- Numbers: `100` or `1250.50` without quotation marks
- Boolean fields such as `is_active` and `is_credit`: use `1` for yes and `0` for no
- Missing optional values: `NULL` without quotation marks

Do not store a plain password in `password_hash`. Insert a hash created by your application.

## Insert parent records first

A foreign key value must already exist in its parent table. A useful insertion order is:

1. `user_account`
2. `employee`, `customer`, `product`, `supplier`, `vehicle`, `backup_record`
3. `complaint`, `stock_batch`, `sales_order`, `attendance`, `leave_request`, `payroll`, `purchase_order`, `budget_plan`, `announcement`
4. `stock_movement`, `order_item`, `invoice`, `delivery`, `purchase_item`, `supplier_payment`
5. `payment`, `delivery_proof`, `delivery_issue`, `financial_record`
6. `login_history` and `audit_log` whenever user activity occurs

To see an automatically created ID after an insert, run:

```sql
SELECT LAST_INSERT_ID();
```

Use that ID when inserting a related record. For instance, an `order_item.order_id` must match an existing `sales_order.order_id`.

## Insert several rows at once

Use one pair of parentheses per row:

```sql
INSERT INTO table_name (column_1, column_2)
VALUES
    (value_1, value_2),
    (value_3, value_4);
```

The database creation script also inserts the mock `user_account` and `employee`
records used to test login and role-based dashboard routing.
