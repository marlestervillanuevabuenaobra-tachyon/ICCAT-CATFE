-- ============================================
-- ICCAT CATFÉ — Migration v2
-- Only run this if you already imported catfe.sql before and don't want
-- to lose existing data. If this is a fresh install, just import catfe.sql
-- instead — it already includes everything below.
-- ============================================

USE catfe_db;

-- Remove Google sign-in leftovers
ALTER TABLE users DROP FOREIGN KEY IF EXISTS users_ibfk_1; -- harmless if it doesn't exist
ALTER TABLE users DROP COLUMN IF EXISTS google_id;
ALTER TABLE users MODIFY password VARCHAR(255) NOT NULL;

-- Add voucher fields to orders
ALTER TABLE orders ADD COLUMN IF NOT EXISTS subtotal DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER address;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS voucher_code VARCHAR(30) NULL AFTER subtotal;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER voucher_code;

-- Create vouchers table
CREATE TABLE IF NOT EXISTS vouchers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    discount_percent DECIMAL(5,2) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_used TINYINT(1) NOT NULL DEFAULT 0,
    used_by INT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (used_by) REFERENCES users(id)
);

INSERT IGNORE INTO vouchers (code, discount_percent) VALUES
('MEOW10', 10.00),
('PAWSOME20', 20.00),
('WELCOME5', 5.00);
