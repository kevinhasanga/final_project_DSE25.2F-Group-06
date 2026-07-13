CREATE DATABASE IF NOT EXISTS business_management;
USE business_management;

CREATE TABLE user_account (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL,
    created_at DATETIME NOT NULL
);

CREATE TABLE employee (
    employee_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    nic VARCHAR(20) NOT NULL UNIQUE,
    contact_no VARCHAR(20) NOT NULL,
    address TEXT,
    job_title VARCHAR(100) NOT NULL,
    hire_date DATE NOT NULL,
    base_salary DECIMAL(10,2) NOT NULL,
    employment_status VARCHAR(30) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES user_account(user_id)
);

CREATE TABLE login_history (
    login_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    login_time DATETIME NOT NULL,
    logout_time DATETIME,
    FOREIGN KEY (user_id) REFERENCES user_account(user_id)
);

CREATE TABLE audit_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_table VARCHAR(100),
    target_id INT,
    timestamp DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES user_account(user_id)
);

CREATE TABLE customer (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    contact_no VARCHAR(20) NOT NULL,
    email VARCHAR(100) UNIQUE,
    address TEXT,
    loyalty_points INT NOT NULL
);

CREATE TABLE complaint (
    complaint_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    officer_id INT,
    description TEXT NOT NULL,
    status VARCHAR(30) NOT NULL,
    created_date DATETIME NOT NULL,
    resolved_date DATETIME,
    escalated_to INT,
    FOREIGN KEY (customer_id) REFERENCES customer(customer_id),
    FOREIGN KEY (officer_id) REFERENCES employee(employee_id),
    FOREIGN KEY (escalated_to) REFERENCES employee(employee_id)
);

CREATE TABLE product (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(100) NOT NULL,
    selling_price DECIMAL(10,2) NOT NULL,
    min_stock_level INT NOT NULL
);

CREATE TABLE supplier (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(100) NOT NULL,
    contact_no VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    address TEXT,
    payment_terms VARCHAR(100)
);

CREATE TABLE stock_batch (
    batch_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    supplier_id INT,
    received_date DATE NOT NULL,
    expiry_date DATE,
    original_quantity INT NOT NULL,
    current_quantity INT NOT NULL,
    status VARCHAR(30) NOT NULL,
    FOREIGN KEY (product_id) REFERENCES product(product_id),
    FOREIGN KEY (supplier_id) REFERENCES supplier(supplier_id)
);

CREATE TABLE stock_movement (
    movement_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    batch_id INT,
    movement_type VARCHAR(30) NOT NULL,
    quantity INT NOT NULL,
    movement_date DATETIME NOT NULL,
    recorded_by INT NOT NULL,
    notes TEXT,
    FOREIGN KEY (product_id) REFERENCES product(product_id),
    FOREIGN KEY (batch_id) REFERENCES stock_batch(batch_id),
    FOREIGN KEY (recorded_by) REFERENCES employee(employee_id)
);

CREATE TABLE sales_order (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    officer_id INT NOT NULL,
    order_date DATETIME NOT NULL,
    status VARCHAR(30) NOT NULL,
    discount_amount DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    is_credit TINYINT(1) NOT NULL,
    credit_approved_by INT,
    FOREIGN KEY (customer_id) REFERENCES customer(customer_id),
    FOREIGN KEY (officer_id) REFERENCES employee(employee_id),
    FOREIGN KEY (credit_approved_by) REFERENCES employee(employee_id)
);

CREATE TABLE order_item (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    line_total DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES sales_order(order_id),
    FOREIGN KEY (product_id) REFERENCES product(product_id)
);

CREATE TABLE invoice (
    invoice_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL UNIQUE,
    issue_date DATE NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_status VARCHAR(30) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES sales_order(order_id)
);

CREATE TABLE payment (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    customer_id INT NOT NULL,
    received_by INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(30) NOT NULL,
    payment_date DATETIME NOT NULL,
    payment_status VARCHAR(30) NOT NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoice(invoice_id),
    FOREIGN KEY (customer_id) REFERENCES customer(customer_id),
    FOREIGN KEY (received_by) REFERENCES employee(employee_id)
);

CREATE TABLE vehicle (
    vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(30) NOT NULL UNIQUE,
    vehicle_type VARCHAR(50) NOT NULL,
    capacity VARCHAR(50)
);

CREATE TABLE delivery (
    delivery_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL UNIQUE,
    driver_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    scheduled_date DATE NOT NULL,
    route_details TEXT,
    status VARCHAR(30) NOT NULL,
    transport_cost DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES sales_order(order_id),
    FOREIGN KEY (driver_id) REFERENCES employee(employee_id),
    FOREIGN KEY (vehicle_id) REFERENCES vehicle(vehicle_id)
);

CREATE TABLE delivery_proof (
    proof_id INT AUTO_INCREMENT PRIMARY KEY,
    delivery_id INT NOT NULL UNIQUE,
    image_url VARCHAR(255),
    uploaded_at DATETIME NOT NULL,
    received_by_name VARCHAR(100) NOT NULL,
    notes TEXT,
    FOREIGN KEY (delivery_id) REFERENCES delivery(delivery_id)
);

CREATE TABLE delivery_issue (
    issue_id INT AUTO_INCREMENT PRIMARY KEY,
    delivery_id INT NOT NULL,
    reported_by INT NOT NULL,
    issue_description TEXT NOT NULL,
    issue_date DATETIME NOT NULL,
    FOREIGN KEY (delivery_id) REFERENCES delivery(delivery_id),
    FOREIGN KEY (reported_by) REFERENCES employee(employee_id)
);

CREATE TABLE attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    recorded_by INT,
    date DATE NOT NULL,
    clock_in TIME,
    clock_out TIME,
    overtime_hours DECIMAL(5,2) NOT NULL,
    FOREIGN KEY (employee_id) REFERENCES employee(employee_id),
    FOREIGN KEY (recorded_by) REFERENCES employee(employee_id)
);

CREATE TABLE leave_request (
    leave_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    approved_by INT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT,
    status VARCHAR(30) NOT NULL,
    requested_date DATETIME NOT NULL,
    FOREIGN KEY (employee_id) REFERENCES employee(employee_id),
    FOREIGN KEY (approved_by) REFERENCES employee(employee_id)
);

CREATE TABLE payroll (
    payroll_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    generated_by INT NOT NULL,
    period VARCHAR(20) NOT NULL,
    base_salary DECIMAL(10,2) NOT NULL,
    overtime_pay DECIMAL(10,2) NOT NULL,
    deductions DECIMAL(10,2) NOT NULL,
    net_pay DECIMAL(10,2) NOT NULL,
    generated_date DATETIME NOT NULL,
    FOREIGN KEY (employee_id) REFERENCES employee(employee_id),
    FOREIGN KEY (generated_by) REFERENCES employee(employee_id)
);

CREATE TABLE purchase_order (
    purchase_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    requested_by INT NOT NULL,
    approved_by INT,
    request_date DATE NOT NULL,
    approval_status VARCHAR(30) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    expected_date DATE,
    FOREIGN KEY (supplier_id) REFERENCES supplier(supplier_id),
    FOREIGN KEY (requested_by) REFERENCES employee(employee_id),
    FOREIGN KEY (approved_by) REFERENCES employee(employee_id)
);

CREATE TABLE purchase_item (
    purchase_item_id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_cost DECIMAL(10,2) NOT NULL,
    line_total DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (purchase_id) REFERENCES purchase_order(purchase_id),
    FOREIGN KEY (product_id) REFERENCES product(product_id)
);

CREATE TABLE supplier_payment (
    supplier_payment_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    purchase_id INT,
    paid_by INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATETIME NOT NULL,
    status VARCHAR(30) NOT NULL,
    FOREIGN KEY (supplier_id) REFERENCES supplier(supplier_id),
    FOREIGN KEY (purchase_id) REFERENCES purchase_order(purchase_id),
    FOREIGN KEY (paid_by) REFERENCES employee(employee_id)
);

CREATE TABLE budget_plan (
    budget_id INT AUTO_INCREMENT PRIMARY KEY,
    budget_purpose VARCHAR(150) NOT NULL,
    period VARCHAR(20) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    prepared_by INT NOT NULL,
    approved_by INT,
    status VARCHAR(30) NOT NULL,
    FOREIGN KEY (prepared_by) REFERENCES employee(employee_id),
    FOREIGN KEY (approved_by) REFERENCES employee(employee_id)
);

CREATE TABLE financial_record (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    recorded_by INT NOT NULL,
    related_payment_id INT,
    supplier_payment_id INT,
    type VARCHAR(30) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    description TEXT,
    record_date DATE NOT NULL,
    tax_amount DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (recorded_by) REFERENCES employee(employee_id),
    FOREIGN KEY (related_payment_id) REFERENCES payment(payment_id),
    FOREIGN KEY (supplier_payment_id) REFERENCES supplier_payment(supplier_payment_id)
);

CREATE TABLE announcement (
    announcement_id INT AUTO_INCREMENT PRIMARY KEY,
    sent_by INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    receiver_id INT,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (sent_by) REFERENCES employee(employee_id),
    FOREIGN KEY (receiver_id) REFERENCES employee(employee_id)
);

CREATE TABLE backup_record (
    backup_id INT AUTO_INCREMENT PRIMARY KEY,
    backup_type VARCHAR(30) NOT NULL,
    file_path VARCHAR(225),
    date DATETIME,
    note TEXT
);
