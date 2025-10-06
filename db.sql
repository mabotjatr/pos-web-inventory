--Hostname: localhost
--Database: kotengca_db
--Username: kotengca_db
--Password: P@ssw0rd

-- web_inventory database
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    barcode VARCHAR(50) UNIQUE,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10,2),
    category VARCHAR(100),
    stock_quantity INT DEFAULT 0,
    image_path VARCHAR(500),
    last_sync TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    local_id INT -- To track original POS ID
);

CREATE TABLE stock_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT,
    old_stock INT,
    new_stock INT,
    change_type ENUM('RESTOCK', 'SALE', 'ADJUSTMENT'),
    notes TEXT,
    changed_by VARCHAR(100),
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE sync_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sync_type ENUM('PUSH', 'PULL'),
    records_processed INT,
    sync_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('SUCCESS', 'FAILED'),
    error_message TEXT
);


-- Insert some sample data for testing
INSERT INTO products (barcode, name, price, category, stock_quantity, image_path, local_id) VALUES
('123456', 'Coke', 15.00, 'Beverages', 10, '/images/coke.png', 1),
('123457', 'Fanta', 15.00, 'Beverages', 3, '/images/fanta.png', 2),
('123458', 'Steak & Pap', 120.00, 'Food', 0, '/images/steak.png', 3),
('123459', 'Chicken & Pap', 100.00, 'Food', 15, '/images/chicken.png', 4);



CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'manager', 'supervisor') DEFAULT 'manager',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- Insert default admin user (password: admin123)
INSERT INTO users (username, password_hash, full_name, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin'),
('manager1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Store Manager', 'manager'),
('supervisor1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Inventory Supervisor', 'supervisor');


USE koteng_db;

-- Create categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default categories
INSERT IGNORE INTO categories (name, description) VALUES 
('Food', 'Food items and meals'),
('Beverages', 'Drinks and beverages'),
('Cigarettes', 'Tobacco products'),
('Snacks', 'Chips, chocolates, and snacks'),
('Toiletries', 'Personal care products');

-- Add category_id to products table if it doesn't exist
ALTER TABLE products 
ADD COLUMN IF NOT EXISTS category_id INT,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE;

-- Add foreign key constraint
ALTER TABLE products 
ADD CONSTRAINT fk_products_category 
FOREIGN KEY (category_id) REFERENCES categories(id);