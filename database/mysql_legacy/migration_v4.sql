-- ============================================
-- ICCAT CATFÉ — Migration v4
-- Run after migration_v2.sql and migration_v3.sql if upgrading an existing install.
-- Adds: new-user-only vouchers, and order status notification tracking.
-- If this is a fresh install, just import catfe.sql instead.
-- ============================================

USE catfe_db;

-- ---- New-user welcome vouchers ----
ALTER TABLE vouchers ADD COLUMN IF NOT EXISTS is_new_user_only TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;

CREATE TABLE IF NOT EXISTS voucher_new_user_redemptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voucher_id INT NOT NULL,
    user_id INT NOT NULL,
    redeemed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY one_redemption_per_user (voucher_id, user_id),
    FOREIGN KEY (voucher_id) REFERENCES vouchers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT IGNORE INTO vouchers (code, discount_percent, is_new_user_only) VALUES
('NEWCAT20', 20.00, 1);

-- ---- Order status notifications ----
ALTER TABLE orders ADD COLUMN IF NOT EXISTS last_seen_status VARCHAR(30) NULL AFTER status;

-- Backfill existing orders so upgrading doesn't suddenly flood everyone with
-- "new update" notifications for orders they've already seen
UPDATE orders SET last_seen_status = status WHERE last_seen_status IS NULL;
