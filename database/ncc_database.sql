-- ============================================
-- NCC Business Management System
-- Database Implementation
-- Based on ER Diagram Logical Schema
-- ============================================

CREATE DATABASE IF NOT EXISTS ncc_database
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE ncc_database;

-- Drop tables if they exist (for clean setup)
DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS delivery_proof;
DROP TABLE IF EXISTS delivery;
DROP TABLE IF EXISTS order_item;
DROP TABLE IF EXISTS sales_order;
DROP TABLE IF EXISTS stock_batch;
DROP TABLE IF EXISTS product;
DROP TABLE IF EXISTS complaint;
DROP TABLE IF EXISTS customer;
DROP TABLE IF EXISTS leave_request;
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS payroll;
DROP TABLE IF EXISTS budget;
DROP TABLE IF EXISTS financial_record;
DROP TABLE IF EXISTS supplier;
DROP TABLE IF EXISTS vehicle;
DROP TABLE IF EXISTS employee;
DROP TABLE IF EXISTS user;

-- ============================================
-- 1. STRONG ENTITIES
-- ============================================

CREATE TABLE user (
    user_id         INT AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    email           VARCHAR(100) NOT NULL UNIQUE,
    role            ENUM('Admin', 'CEO', 'Inventory Manager', 'Order Processing Officer', 
                         'Customer Relationship Officer', 'Distribution Manager', 
                         'Driver', 'Supervisor', 'Finance Officer') NOT NULL,
    is_active       BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_role (role),
    INDEX idx_user_active (is_active)
) ENGINE=InnoDB COMMENT='All system users with login credentials';

CREATE TABLE employee (
    employee_id     INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL UNIQUE,
    name            VARCHAR(100) NOT NULL,
    department      ENUM('Inventory', 'Finance', 'Distribution', 'HR', 'Sales', 'Admin') NOT NULL,
    position        VARCHAR(50) NOT NULL,
    salary_base     DECIMAL(10, 2) NOT NULL CHECK (salary_base >= 0),
    
    FOREIGN KEY (user_id) REFERENCES user(user_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    
    INDEX idx_employee_dept (department),
    INDEX idx_employee_position (position)
) ENGINE=InnoDB COMMENT='HR records for all staff members';

CREATE TABLE customer (
    customer_id     INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    contact_no      VARCHAR(20),
    address         TEXT,
    email           VARCHAR(100),
    loyalty_points  INT DEFAULT 0 CHECK (loyalty_points >= 0),
    registered_date DATE NOT NULL,
    
    INDEX idx_customer_name (name),
    INDEX idx_customer_email (email)
) ENGINE=InnoDB COMMENT='All clients and their contact details';

CREATE TABLE product (
    product_id      INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    category        ENUM('Grains', 'Oils', 'Spices', 'Other') NOT NULL,
    price           DECIMAL(10, 2) NOT NULL CHECK (price >= 0),
    min_stock_level INT NOT NULL DEFAULT 0 CHECK (min_stock_level >= 0),
    unit            VARCHAR(20) NOT NULL,
    
    INDEX idx_product_category (category),
    INDEX idx_product_name (name)
) ENGINE=InnoDB COMMENT='Master catalogue of all products';

CREATE TABLE vehicle (
    vehicle_id      INT AUTO_INCREMENT PRIMARY KEY,
    plate_number    VARCHAR(20) NOT NULL UNIQUE,
    type            ENUM('Lorry', 'Van', 'Motorbike') NOT NULL,
    capacity        DECIMAL(10, 2) NOT NULL CHECK (capacity > 0),
    
    INDEX idx_vehicle_type (type)
) ENGINE=InnoDB COMMENT='Fleet register for deliveries';

CREATE TABLE supplier (
    supplier_id     INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    contact         VARCHAR(100),
    payment_terms   VARCHAR(50),
    
    INDEX idx_supplier_name (name)
) ENGINE=InnoDB COMMENT='All product suppliers';

CREATE TABLE sales_order (
    order_id        INT AUTO_INCREMENT PRIMARY KEY,
    customer_id     INT NOT NULL,
    officer_id      INT NOT NULL,
    order_date      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status          ENUM('Pending', 'Confirmed', 'Dispatched', 'Delivered', 'Cancelled') 
                    NOT NULL DEFAULT 'Pending',
    discount        DECIMAL(10, 2) DEFAULT 0.00 CHECK (discount >= 0),
    tax             DECIMAL(10, 2) DEFAULT 0.00 CHECK (tax >= 0),
    total_amount    DECIMAL(12, 2) NOT NULL CHECK (total_amount >= 0),
    is_credit       BOOLEAN DEFAULT FALSE,
    
    FOREIGN KEY (customer_id) REFERENCES customer(customer_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (officer_id) REFERENCES employee(employee_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    
    INDEX idx_order_customer (customer_id),
    INDEX idx_order_status (status),
    INDEX idx_order_date (order_date),
    INDEX idx_order_officer (officer_id)
) ENGINE=InnoDB COMMENT='Central transaction record for all customer orders';

CREATE TABLE financial_record (
    record_id       INT AUTO_INCREMENT PRIMARY KEY,
    recorded_by     INT NOT NULL,
    supplier_id     INT,
    type            ENUM('Sales Income', 'Supplier Payment', 'Operational Expense', 
                         'Tax Payment', 'Other') NOT NULL,
    amount          DECIMAL(12, 2) NOT NULL,
    description     TEXT,
    date            DATE NOT NULL,
    
    FOREIGN KEY (recorded_by) REFERENCES employee(employee_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES supplier(supplier_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    
    INDEX idx_fin_type (type),
    INDEX idx_fin_date (date),
    INDEX idx_fin_supplier (supplier_id),
    INDEX idx_fin_recorded_by (recorded_by)
) ENGINE=InnoDB COMMENT='All income and expense transactions';

CREATE TABLE budget (
    budget_id       INT AUTO_INCREMENT PRIMARY KEY,
    approved_by     INT NOT NULL,
    department      ENUM('Inventory', 'Finance', 'Distribution', 'HR', 'Sales', 'Admin') NOT NULL,
    amount          DECIMAL(12, 2) NOT NULL CHECK (amount >= 0),
    period          VARCHAR(20) NOT NULL,
    status          ENUM('Draft', 'Pending Approval', 'Approved') NOT NULL DEFAULT 'Draft',
    
    FOREIGN KEY (approved_by) REFERENCES employee(employee_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    
    INDEX idx_budget_dept (department),
    INDEX idx_budget_period (period),
    INDEX idx_budget_status (status)
) ENGINE=InnoDB COMMENT='Departmental financial budgets';

-- ============================================
-- 2. WEAK ENTITIES
-- ============================================

CREATE TABLE stock_batch (
    batch_id        INT NOT NULL,
    product_id      INT NOT NULL,
    quantity        INT NOT NULL CHECK (quantity >= 0),
    received_date   DATE NOT NULL,
    expiry_date     DATE,
    location        VARCHAR(50),
    status          ENUM('Available', 'Damaged', 'Expired') DEFAULT 'Available',
    
    PRIMARY KEY (product_id, batch_id),
    
    FOREIGN KEY (product_id) REFERENCES product(product_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    INDEX idx_batch_expiry (expiry_date),
    INDEX idx_batch_status (status),
    INDEX idx_batch_received (received_date)
) ENGINE=InnoDB COMMENT='Individual incoming stock shipments per product';

CREATE TABLE order_item (
    item_id         INT NOT NULL,
    order_id        INT NOT NULL,
    product_id      INT NOT NULL,
    quantity        INT NOT NULL CHECK (quantity > 0),
    unit_price      DECIMAL(10, 2) NOT NULL CHECK (unit_price >= 0),
    
    PRIMARY KEY (order_id, item_id),
    
    FOREIGN KEY (order_id) REFERENCES sales_order(order_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (product_id) REFERENCES product(product_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    
    INDEX idx_item_product (product_id)
) ENGINE=InnoDB COMMENT='Individual products within each sales order';

CREATE TABLE complaint (
    complaint_id    INT NOT NULL,
    customer_id     INT NOT NULL,
    officer_id      INT NOT NULL,
    description     TEXT NOT NULL,
    status          ENUM('Open', 'In Progress', 'Resolved') NOT NULL DEFAULT 'Open',
    resolved_date   DATE,
    
    PRIMARY KEY (customer_id, complaint_id),
    
    FOREIGN KEY (customer_id) REFERENCES customer(customer_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (officer_id) REFERENCES employee(employee_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    
    INDEX idx_complaint_status (status),
    INDEX idx_complaint_officer (officer_id)
) ENGINE=InnoDB COMMENT='Customer complaints and their resolution status';

CREATE TABLE delivery (
    delivery_id     INT NOT NULL,
    order_id        INT NOT NULL,
    driver_id       INT NOT NULL,
    vehicle_id      INT NOT NULL,
    manager_id      INT NOT NULL,
    scheduled_date  DATE NOT NULL,
    status          ENUM('Planned', 'Dispatched', 'Delivered', 'Delayed') 
                    NOT NULL DEFAULT 'Planned',
    route           VARCHAR(255),
    transport_cost  DECIMAL(10, 2) DEFAULT 0.00,
    
    PRIMARY KEY (order_id, delivery_id),
    UNIQUE KEY uk_delivery_order (order_id), -- Enforces 1:1 with sales_order
    
    FOREIGN KEY (order_id) REFERENCES sales_order(order_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES employee(employee_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicle(vehicle_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (manager_id) REFERENCES employee(employee_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    
    INDEX idx_delivery_status (status),
    INDEX idx_delivery_scheduled (scheduled_date),
    INDEX idx_delivery_driver (driver_id),
    INDEX idx_delivery_vehicle (vehicle_id)
) ENGINE=InnoDB COMMENT='Delivery arrangements for each confirmed order';

CREATE TABLE delivery_proof (
    proof_id        INT NOT NULL,
    order_id        INT NOT NULL,
    delivery_id     INT NOT NULL,
    image_url       VARCHAR(255),
    timestamp       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fuel_used       DECIMAL(8, 2) DEFAULT 0.00,
    notes           TEXT,
    
    PRIMARY KEY (order_id, delivery_id, proof_id),
    UNIQUE KEY uk_proof_delivery (order_id, delivery_id), -- Enforces 1:1 with delivery
    
    FOREIGN KEY (order_id, delivery_id) REFERENCES delivery(order_id, delivery_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    INDEX idx_proof_timestamp (timestamp)
) ENGINE=InnoDB COMMENT='Proof of successful delivery completion';

CREATE TABLE attendance (
    attendance_id   INT NOT NULL,
    employee_id     INT NOT NULL,
    approved_by     INT,
    date            DATE NOT NULL,
    clock_in        TIME,
    clock_out       TIME,
    is_overtime     BOOLEAN DEFAULT FALSE,
    
    PRIMARY KEY (employee_id, attendance_id),
    
    FOREIGN KEY (employee_id) REFERENCES employee(employee_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES employee(employee_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    
    INDEX idx_attendance_date (date),
    INDEX idx_attendance_approved_by (approved_by),
    CONSTRAINT chk_clock_times CHECK (clock_out IS NULL OR clock_out > clock_in)
) ENGINE=InnoDB COMMENT='Daily clock-in/out records per employee';

CREATE TABLE leave_request (
    leave_id        INT NOT NULL,
    employee_id     INT NOT NULL,
    approved_by     INT,
    start_date      DATE NOT NULL,
    end_date        DATE NOT NULL,
    reason          VARCHAR(255),
    status          ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
    
    PRIMARY KEY (employee_id, leave_id),
    
    FOREIGN KEY (employee_id) REFERENCES employee(employee_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES employee(employee_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    
    INDEX idx_leave_status (status),
    INDEX idx_leave_dates (start_date, end_date),
    CONSTRAINT chk_leave_dates CHECK (end_date >= start_date)
) ENGINE=InnoDB COMMENT='Employee leave requests';

CREATE TABLE payroll (
    payroll_id      INT NOT NULL,
    employee_id     INT NOT NULL,
    generated_by    INT NOT NULL,
    period          VARCHAR(20) NOT NULL,
    base_salary     DECIMAL(10, 2) NOT NULL CHECK (base_salary >= 0),
    overtime_pay    DECIMAL(10, 2) DEFAULT 0.00 CHECK (overtime_pay >= 0),
    deductions      DECIMAL(10, 2) DEFAULT 0.00 CHECK (deductions >= 0),
    net_pay         DECIMAL(10, 2) NOT NULL CHECK (net_pay >= 0),
    
    PRIMARY KEY (employee_id, payroll_id),
    
    FOREIGN KEY (employee_id) REFERENCES employee(employee_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES employee(employee_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    
    INDEX idx_payroll_period (period),
    INDEX idx_payroll_generated_by (generated_by)
) ENGINE=InnoDB COMMENT='Monthly salary calculations per employee';

CREATE TABLE audit_log (
    log_id          INT NOT NULL,
    user_id         INT NOT NULL,
    action          VARCHAR(100) NOT NULL,
    target_table    VARCHAR(50) NOT NULL,
    timestamp       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address      VARCHAR(45),
    
    PRIMARY KEY (user_id, log_id),
    
    FOREIGN KEY (user_id) REFERENCES user(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    INDEX idx_audit_timestamp (timestamp),
    INDEX idx_audit_target (target_table),
    INDEX idx_audit_action (action)
) ENGINE=InnoDB COMMENT='System activity log for all users';

-- ============================================
-- 3. VIEWS FOR COMMON REPORTS
-- ============================================

CREATE VIEW vw_customer_order_summary AS
SELECT 
    c.customer_id,
    c.name,
    c.email,
    COUNT(DISTINCT so.order_id) AS total_orders,
    SUM(so.total_amount) AS total_spent,
    c.loyalty_points
FROM customer c
LEFT JOIN sales_order so ON c.customer_id = so.customer_id
GROUP BY c.customer_id, c.name, c.email, c.loyalty_points;

CREATE VIEW vw_employee_payroll_summary AS
SELECT 
    e.employee_id,
    e.name,
    e.department,
    e.position,
    COUNT(DISTINCT p.payroll_id) AS payroll_records,
    AVG(p.net_pay) AS avg_net_pay,
    SUM(p.overtime_pay) AS total_overtime
FROM employee e
LEFT JOIN payroll p ON e.employee_id = p.employee_id
GROUP BY e.employee_id, e.name, e.department, e.position;

CREATE VIEW vw_low_stock_alert AS
SELECT 
    p.product_id,
    p.name,
    p.category,
    p.min_stock_level,
    COALESCE(SUM(sb.quantity), 0) AS current_stock,
    p.unit
FROM product p
LEFT JOIN stock_batch sb ON p.product_id = sb.product_id AND sb.status = 'Available'
GROUP BY p.product_id, p.name, p.category, p.min_stock_level, p.unit
HAVING COALESCE(SUM(sb.quantity), 0) < p.min_stock_level;

CREATE VIEW vw_pending_deliveries AS
SELECT 
    so.order_id,
    c.name AS customer_name,
    c.address,
    d.scheduled_date,
    d.status,
    e.name AS driver_name,
    v.plate_number,
    v.type AS vehicle_type
FROM sales_order so
JOIN customer c ON so.customer_id = c.customer_id
LEFT JOIN delivery d ON so.order_id = d.order_id
LEFT JOIN employee e ON d.driver_id = e.employee_id
LEFT JOIN vehicle v ON d.vehicle_id = v.vehicle_id
WHERE so.status IN ('Confirmed', 'Dispatched');

-- ============================================
-- 4. STORED PROCEDURES
-- ============================================

DELIMITER //

CREATE PROCEDURE sp_create_order(
    IN p_customer_id INT,
    IN p_officer_id INT,
    IN p_discount DECIMAL(10,2),
    IN p_tax DECIMAL(10,2),
    IN p_is_credit BOOLEAN,
    OUT p_order_id INT
)
BEGIN
    INSERT INTO sales_order (customer_id, officer_id, discount, tax, total_amount, is_credit)
    VALUES (p_customer_id, p_officer_id, p_discount, p_tax, 0, p_is_credit);
    
    SET p_order_id = LAST_INSERT_ID();
END //

CREATE PROCEDURE sp_add_order_item(
    IN p_order_id INT,
    IN p_product_id INT,
    IN p_quantity INT,
    IN p_unit_price DECIMAL(10,2)
)
BEGIN
    DECLARE v_item_id INT;
    DECLARE v_total DECIMAL(12,2);
    
    -- Get next item_id for this order
    SELECT COALESCE(MAX(item_id), 0) + 1 INTO v_item_id 
    FROM order_item WHERE order_id = p_order_id;
    
    INSERT INTO order_item (item_id, order_id, product_id, quantity, unit_price)
    VALUES (v_item_id, p_order_id, p_product_id, p_quantity, p_unit_price);
    
    -- Recalculate order total
    SELECT SUM(quantity * unit_price) INTO v_total
    FROM order_item WHERE order_id = p_order_id;
    
    UPDATE sales_order 
    SET total_amount = v_total + tax - discount
    WHERE order_id = p_order_id;
END //

CREATE PROCEDURE sp_generate_payroll(
    IN p_employee_id INT,
    IN p_period VARCHAR(20),
    IN p_generated_by INT
)
BEGIN
    DECLARE v_base DECIMAL(10,2);
    DECLARE v_overtime DECIMAL(10,2);
    DECLARE v_deductions DECIMAL(10,2);
    DECLARE v_payroll_id INT;
    DECLARE v_net DECIMAL(10,2);
    
    -- Get base salary
    SELECT salary_base INTO v_base FROM employee WHERE employee_id = p_employee_id;
    
    -- Calculate overtime from attendance
    SELECT COALESCE(SUM(
        CASE WHEN is_overtime THEN 
            TIME_TO_SEC(TIMEDIFF(clock_out, clock_in)) / 3600 * (v_base / 160 * 1.5)
        ELSE 0 END
    ), 0) INTO v_overtime
    FROM attendance 
    WHERE employee_id = p_employee_id 
    AND DATE_FORMAT(date, '%Y-%m') = p_period;
    
    -- Standard deductions (simplified)
    SET v_deductions = v_base * 0.08; -- EPF approximation
    
    SET v_net = v_base + v_overtime - v_deductions;
    
    -- Get next payroll_id
    SELECT COALESCE(MAX(payroll_id), 0) + 1 INTO v_payroll_id 
    FROM payroll WHERE employee_id = p_employee_id;
    
    INSERT INTO payroll (payroll_id, employee_id, generated_by, period, 
                       base_salary, overtime_pay, deductions, net_pay)
    VALUES (v_payroll_id, p_employee_id, p_generated_by, p_period,
            v_base, v_overtime, v_deductions, v_net);
END //

DELIMITER ;

-- ============================================
-- 5. TRIGGERS
-- ============================================

DELIMITER //

CREATE TRIGGER trg_audit_user_insert
AFTER INSERT ON user
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (log_id, user_id, action, target_table, timestamp, ip_address)
    VALUES (
        (SELECT COALESCE(MAX(log_id), 0) + 1 FROM audit_log WHERE user_id = NEW.user_id),
        NEW.user_id,
        'Created account',
        'USER',
        NOW(),
        NULL
    );
END //

CREATE TRIGGER trg_check_stock_before_order
BEFORE INSERT ON order_item
FOR EACH ROW
BEGIN
    DECLARE v_available INT;
    
    SELECT COALESCE(SUM(quantity), 0) INTO v_available
    FROM stock_batch
    WHERE product_id = NEW.product_id AND status = 'Available';
    
    IF v_available < NEW.quantity THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Insufficient stock for this product';
    END IF;
END //

DELIMITER ;

-- ============================================
-- 6. SAMPLE DATA (Optional - for testing)
-- ============================================

-- Sample login password for the users below: password
-- Replace these credentials before using the system outside development.
INSERT INTO user (username, password_hash, email, role, is_active) VALUES
('admin', '$2y$10$kMicTDg9Mwya6O7N9KW.VOvJ1Q7DVXgy86yBUfqutHU8LkGYSggVW', 'admin@ncc.lk', 'Admin', TRUE);

-- Insert sample employees with user accounts
INSERT INTO user (username, password_hash, email, role) VALUES
('cro1', '$2y$10$kMicTDg9Mwya6O7N9KW.VOvJ1Q7DVXgy86yBUfqutHU8LkGYSggVW', 'cro@ncc.lk', 'Customer Relationship Officer'),
('driver1', '$2y$10$kMicTDg9Mwya6O7N9KW.VOvJ1Q7DVXgy86yBUfqutHU8LkGYSggVW', 'driver@ncc.lk', 'Driver'),
('finance1', '$2y$10$kMicTDg9Mwya6O7N9KW.VOvJ1Q7DVXgy86yBUfqutHU8LkGYSggVW', 'finance@ncc.lk', 'Finance Officer');

INSERT INTO employee (user_id, name, department, position, salary_base) VALUES
(2, 'John Smith', 'Sales', 'Customer Relationship Officer', 85000.00),
(3, 'Kamal Perera', 'Distribution', 'Driver', 65000.00),
(4, 'Samanthi Silva', 'Finance', 'Finance Officer', 95000.00);

-- Insert sample products
INSERT INTO product (name, category, price, min_stock_level, unit) VALUES
('Rice 5kg', 'Grains', 1250.00, 100, 'bag'),
('Coconut Oil 1L', 'Oils', 450.00, 50, 'bottle'),
('Turmeric 100g', 'Spices', 120.00, 200, 'packet');

-- Insert sample customer
INSERT INTO customer (name, contact_no, address, email, registered_date) VALUES
('Cargills Food City', '011-2345678', 'Colombo 03', 'orders@cargills.lk', '2025-01-15');

-- Insert sample vehicle
INSERT INTO vehicle (plate_number, type, capacity) VALUES
('WP-CAB-1234', 'Lorry', 5000.00);

-- Insert sample supplier
INSERT INTO supplier (name, contact, payment_terms) VALUES
('Lanka Rice Mills', '011-9876543', '30 days');

-- ============================================
-- END OF DATABASE IMPLEMENTATION
-- ============================================
