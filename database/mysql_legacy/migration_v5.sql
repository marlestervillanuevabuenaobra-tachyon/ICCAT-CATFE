-- ============================================
-- ICCAT CATFÉ — Migration v5
-- Run after migration_v2/v3/v4.sql if upgrading an existing install.
-- Adds the reviews table (1-5 star rating + feedback per delivered order).
-- If this is a fresh install, just import catfe.sql instead.
-- ============================================

USE catfe_db;

CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL UNIQUE,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL,
    feedback TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
