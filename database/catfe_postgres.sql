-- ============================================
-- ICCAT CATFÉ  |  Database Schema (PostgreSQL / Render version)
-- Run this once against your Render Postgres database (psql or DBeaver)
-- ============================================


-- ---------- USERS ----------
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------- PRODUCTS ----------
-- subcategory is only used for drinks ('coffee' / 'non-coffee') to group the menu
CREATE TABLE IF NOT EXISTS products (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(10) NOT NULL CHECK (category IN ('pastry','drink')),
    subcategory VARCHAR(30) NULL,
    price DECIMAL(8,2) NOT NULL,
    stock INT NOT NULL DEFAULT 20,
    image VARCHAR(255) NOT NULL,
    description VARCHAR(255) DEFAULT ''
);

-- ---------- PRODUCT VARIANTS ----------
-- Pastries: 3 flavor choices each. Drinks: Iced / Hot. One is required per order.
CREATE TABLE IF NOT EXISTS product_variants (
    id SERIAL PRIMARY KEY,
    product_id INT NOT NULL,
    variant_name VARCHAR(50) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- ---------- ORDERS ----------
CREATE TABLE IF NOT EXISTS orders (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    city VARCHAR(50) NOT NULL,
    region VARCHAR(50) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    address VARCHAR(35) NOT NULL,          -- 35-char limit per spec
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    voucher_code VARCHAR(30) NULL,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_payment DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(30) NOT NULL DEFAULT 'Cash on Delivery',
    status VARCHAR(30) NOT NULL DEFAULT 'Pending' CHECK (status IN ('Pending','Processing','Out for Delivery','Delivered','Cancelled')),
    last_seen_status VARCHAR(30) NULL,     -- what the customer last saw; mismatch = unseen update
    sheet_synced SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ---------- ORDER ITEMS ----------
CREATE TABLE IF NOT EXISTS order_items (
    id SERIAL PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NULL,
    product_name VARCHAR(100) NOT NULL,
    variant_name VARCHAR(50) NULL,         -- chosen flavor, or Iced/Hot
    quantity INT NOT NULL,
    price DECIMAL(8,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- ---------- SEED PRODUCTS: 10 pastries + 10 drinks ----------
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

-- ---------- SEED VARIANTS ----------
-- Pastries: 3 flavors each
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

-- Drinks: Iced / Hot for every drink (ids 11–20)
INSERT INTO product_variants (product_id, variant_name, sort_order)
SELECT id, 'Iced', 1 FROM products WHERE category = 'drink'
UNION ALL
SELECT id, 'Hot', 2 FROM products WHERE category = 'drink';

-- ---------- REVIEWS ----------
-- One review per order, only submittable once the order is Delivered.
CREATE TABLE IF NOT EXISTS reviews (
    id SERIAL PRIMARY KEY,
    order_id INT NOT NULL UNIQUE,
    user_id INT NOT NULL,
    rating SMALLINT NOT NULL,
    feedback TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ---------- ADMIN ----------
-- Secret admin access uses a fixed passphrase check in code ("catfebrigida"),
-- but we also keep a table in case you want to move to hashed DB auth later.
CREATE TABLE IF NOT EXISTS admin_log (
    id SERIAL PRIMARY KEY,
    action VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------- VOUCHERS ----------
-- Regular codes are single-use across the whole shop: once redeemed by any
-- customer, they flip to "used" and nobody else can apply them.
-- "New users only" codes work differently — every brand-new customer (someone
-- with zero prior orders) can redeem them once each; tracked in
-- voucher_new_user_redemptions instead of the shared is_used flag.
CREATE TABLE IF NOT EXISTS vouchers (
    id SERIAL PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    discount_percent DECIMAL(5,2) NOT NULL,   -- e.g. 10.00 = 10% off
    is_active SMALLINT NOT NULL DEFAULT 1,  -- admin can disable without deleting
    is_new_user_only SMALLINT NOT NULL DEFAULT 0,
    is_used SMALLINT NOT NULL DEFAULT 0,    -- only meaningful when is_new_user_only = 0
    used_by INT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (used_by) REFERENCES users(id)
);

-- Tracks per-user redemptions of "new users only" codes, so each new customer
-- can use one exactly once while the code itself stays usable for others.
CREATE TABLE IF NOT EXISTS voucher_new_user_redemptions (
    id SERIAL PRIMARY KEY,
    voucher_id INT NOT NULL,
    user_id INT NOT NULL,
    redeemed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT one_redemption_per_user UNIQUE (voucher_id, user_id),
    FOREIGN KEY (voucher_id) REFERENCES vouchers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- A few sample vouchers to try out
INSERT INTO vouchers (code, discount_percent, is_new_user_only) VALUES
('MEOW10', 10.00, 0),
('PAWSOME20', 20.00, 0),
('WELCOME5', 5.00, 0),
('NEWCAT20', 20.00, 1);   -- new-customer welcome code, 20% off, once per new user

-- ---------- Fix auto-increment counters ----------
-- Products were inserted with fixed ids (1-20), so tell PostgreSQL to continue from there.
SELECT setval(pg_get_serial_sequence('products', 'id'), (SELECT MAX(id) FROM products));
