-- ============================================
-- ICCAT CATFÉ — Migration v3
-- Run this if you already imported catfe.sql or migration_v2.sql before.
-- This replaces the entire product menu with the new flavors/temperature
-- system, so your OLD product prices/stock edits will be overwritten.
-- Existing orders, users, and vouchers are kept.
--
-- If this is a fresh install, just import catfe.sql instead — it already
-- includes everything below.
-- ============================================

USE catfe_db;

-- Let historical order_items survive even after we replace the products table
ALTER TABLE order_items DROP FOREIGN KEY IF EXISTS order_items_ibfk_2;
ALTER TABLE order_items MODIFY product_id INT NULL;
ALTER TABLE order_items ADD CONSTRAINT order_items_ibfk_2
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS variant_name VARCHAR(50) NULL AFTER product_name;

-- Add subcategory to products (used to group drinks into Coffee / Non-Coffee)
ALTER TABLE products ADD COLUMN IF NOT EXISTS subcategory VARCHAR(30) NULL AFTER category;

-- Variants table
CREATE TABLE IF NOT EXISTS product_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    variant_name VARCHAR(50) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Wipe the old 20 products (order_items keep their product_name/price snapshot,
-- and product_id just goes NULL for old rows thanks to the FK change above)
DELETE FROM products;
ALTER TABLE products AUTO_INCREMENT = 1;

INSERT INTO products (id, name, category, subcategory, price, stock, image, description) VALUES
(1,  'Croissant',       'pastry', NULL,          60.00, 20, 'assets/images/pastry1.jpg',  'Golden, crispy layers with a soft, cozy center — purr-fectly baked.'),
(2,  'Muffins',         'pastry', NULL,          35.00, 20, 'assets/images/pastry2.jpg',  'Freshly baked muffins, made with a sprinkle of catfé magic.'),
(3,  'Danish',          'pastry', NULL,         105.00, 20, 'assets/images/pastry3.jpg',  'Sweet swirls and golden layers, made with love by the catfé.'),
(4,  'Cinnamon Roll',   'pastry', NULL,          45.00, 20, 'assets/images/pastry4.jpg',  'Cozy little rolls with sweet swirls and whisker charm.'),
(5,  'Pain au Suisse',  'pastry', NULL,         170.00, 20, 'assets/images/pastry5.jpg',  'Buttery layers hiding creamy custards and little chocolate treasures.'),
(6,  'Cookies',         'pastry', NULL,         190.00, 20, 'assets/images/pastry6.jpg',  'Crunchy edges, soft centers, and plenty of snack-time charm. (per box)'),
(7,  'Brownies',        'pastry', NULL,         160.00, 20, 'assets/images/pastry7.jpg',  'Dark, fudgy goodness tucked into every bite. (per box)'),
(8,  'Tarts',           'pastry', NULL,          30.00, 20, 'assets/images/pastry8.jpg',  'Delicate little shells filled with sweet, fruity treasures.'),
(9,  'Pies',            'pastry', NULL,          60.00, 20, 'assets/images/pastry9.jpg',  'Golden pastry wrapped around a comforting, sweet surprise.'),
(10, 'Cakes',           'pastry', NULL,         100.00, 20, 'assets/images/pastry10.jpg', 'Tender layers of sweetness for every special moment.'),
(11, 'Americano',       'drink',  'coffee',      90.00, 20, 'assets/images/drink1.jpg',   'Bold espresso, softened with water for a clean, fearless sip.'),
(12, 'Macchiato',       'drink',  'coffee',     110.00, 20, 'assets/images/drink2.jpg',   'Rich espresso kissed with a touch of creamy foam.'),
(13, 'Mocha',           'drink',  'coffee',     120.00, 20, 'assets/images/drink3.jpg',   'Espresso and rich chocolate blended into a sweet, velvety purr.'),
(14, 'Cappuccino',      'drink',  'coffee',     110.00, 20, 'assets/images/drink4.jpg',   'Bold espresso beneath a cloud of silky milk foam.'),
(15, 'Cafe Au Lait',    'drink',  'coffee',     120.00, 20, 'assets/images/drink5.jpg',   'Smooth, rich espresso blended with warm, velvety milk for a perfectly balanced cup.'),
(16, 'Latte',           'drink',  'coffee',     110.00, 20, 'assets/images/drink6.jpg',   'Smooth espresso and velvety milk, soft as a cat''s purr.'),
(17, 'Chocolate',       'drink',  'non-coffee', 100.00, 20, 'assets/images/drink7.jpg',   'Rich, velvety chocolate made for cozy little paws.'),
(18, 'Lemon Tea',       'drink',  'non-coffee',  80.00, 20, 'assets/images/drink8.jpg',   'Bright citrus and fragrant tea with a refreshing twist.'),
(19, 'Peach Tea',       'drink',  'non-coffee', 105.00, 20, 'assets/images/drink9.jpg',   'Refreshing tea infused with the delicate sweetness of ripe peach.'),
(20, 'Jasmine Milk Tea','drink',  'non-coffee', 115.00, 20, 'assets/images/drink10.jpg',  'Floral jasmine tea softened with creamy milk.');

INSERT INTO product_variants (product_id, variant_name, sort_order) VALUES
(1, 'Almond Cream', 1),      (1, 'Chocolate', 2),          (1, 'Strawberry Cream', 3),
(2, 'Blueberry Crumble', 1), (2, 'Double Chocolate', 2),   (2, 'Banana Walnut', 3),
(3, 'Apple Cinnamon', 1),    (3, 'Blueberry Cheese', 2),   (3, 'Strawberry Custard', 3),
(4, 'Classic Vanilla', 1),   (4, 'Strawberry Cream', 2),   (4, 'Caramel Pecan', 3),
(5, 'Chocolate Custard', 1), (5, 'Pistachio Cream', 2),    (5, 'Raspberry Cream', 3),
(6, 'Chocolate Chip', 1),    (6, 'Matcha White Chocolate', 2), (6, 'Biscoff', 3),
(7, 'Fudge Walnut', 1),      (7, 'Salted Caramel', 2),     (7, 'Dark Chocolate', 3),
(8, 'Lemon Curd', 1),        (8, 'Strawberry Custard', 2), (8, 'Chocolate Ganache', 3),
(9, 'Apple Cinnamon', 1),    (9, 'Blueberry Crumble', 2),  (9, 'Chocolate Cream', 3),
(10, 'Strawberry Shortcake', 1), (10, 'Chocolate Fudge', 2), (10, 'Vanilla Berry', 3);

INSERT INTO product_variants (product_id, variant_name, sort_order)
SELECT id, 'Iced', 1 FROM products WHERE category = 'drink'
UNION ALL
SELECT id, 'Hot', 2 FROM products WHERE category = 'drink';
