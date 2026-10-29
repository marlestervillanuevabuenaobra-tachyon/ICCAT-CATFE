<?php
require_once __DIR__ . "/includes/functions.php";
require_login();

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

$pastries    = $conn->query("SELECT * FROM products WHERE category = 'pastry' ORDER BY id");
$coffeeDrinks    = $conn->query("SELECT * FROM products WHERE category = 'drink' AND subcategory = 'coffee' ORDER BY id");
$nonCoffeeDrinks = $conn->query("SELECT * FROM products WHERE category = 'drink' AND subcategory = 'non-coffee' ORDER BY id");

// Build a JS-friendly map of productId -> {name, label, variants[]} for the variant picker modal
$variantMap = [];
$allProducts = $conn->query("SELECT id, name, category FROM products");
while ($row = $allProducts->fetch_assoc()) {
    $variantMap[$row['id']] = [
        "name" => $row['name'],
        "label" => $row['category'] === 'drink' ? "Iced or Hot?" : "Choose a Flavor",
        "variants" => []
    ];
}
$variantRows = $conn->query("SELECT product_id, variant_name FROM product_variants ORDER BY product_id, sort_order");
while ($row = $variantRows->fetch_assoc()) {
    $variantMap[$row['product_id']]['variants'][] = $row['variant_name'];
}

$cartCount = 0;
foreach ($_SESSION['cart'] as $item) $cartCount += $item['qty'];

// Show a welcome-code banner if this user hasn't placed an order yet and an
// active "new customers only" voucher exists
$isNewCustomer = false;
$welcomeVoucherCode = null;
$orderCountRow = $conn->query("SELECT COUNT(*) as c FROM orders WHERE user_id = " . $_SESSION['user_id'] . " AND status != 'Cancelled'")->fetch_assoc();
if ($orderCountRow['c'] == 0) {
    $isNewCustomer = true;
    $wv = $conn->query("SELECT code, discount_percent FROM vouchers WHERE is_new_user_only = 1 AND is_active = 1 ORDER BY id LIMIT 1")->fetch_assoc();
    if ($wv) $welcomeVoucherCode = $wv;
}

// Unseen order status updates (for the notification bell)
$unseenCount = $conn->query("SELECT COUNT(*) as c FROM orders WHERE user_id = " . $_SESSION['user_id'] . " AND last_seen_status IS NOT NULL AND last_seen_status != status")->fetch_assoc()['c'];

// Public reviews & feedback feed
$reviews = $conn->query("
    SELECT r.rating, r.feedback, r.created_at, u.first_name, u.last_name
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    ORDER BY r.created_at DESC
    LIMIT 20
");

// ---- EDIT THESE for the About Us section (3 members) ----
$team = [
    ["name" => "Juan Dela Cruz", "student_no" => "2023-00001", "quote" => "Every cup tells a story.", "image" => "assets/images/logo.jpg"],
    ["name" => "Maria Santos",   "student_no" => "2023-00002", "quote" => "Baked with love, served with purrr-pose.", "image" => "assets/images/logo.jpg"],
    ["name" => "Jose Rizal Jr.", "student_no" => "2023-00003", "quote" => "Coffee, cats, community.", "image" => "assets/images/logo.jpg"],
];
// ---- EDIT THIS to your actual Discord invite link (also change it in includes/functions.php) ----
$discord_link = DISCORD_INVITE_LINK;

function render_product_card($p) {
    echo '<div class="product-card">';
    echo '<img src="' . htmlspecialchars($p['image']) . '" alt="' . htmlspecialchars($p['name']) . '">';
    echo '<div class="product-info">';
    echo '<h4>' . htmlspecialchars($p['name']) . '</h4>';
    echo '<p class="price">₱' . number_format($p['price'], 2) . '</p>';
    echo '<p class="stock-note">' . ($p['stock'] > 0 ? $p['stock'] . " left in stock" : "Out of stock") . '</p>';
    echo '<p class="item-desc">' . htmlspecialchars($p['description']) . '</p>';
    $disabled = $p['stock'] <= 0 ? "disabled" : "";
    echo '<button class="add-btn" ' . $disabled . ' onclick="openVariantModal(' . $p['id'] . ')">Add to Cart</button>';
    echo '</div></div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard - ICCAT CATFÉ</title>
<link rel="stylesheet" href="assets/css/style.css?v=6">
</head>
<body>

<!-- TOP BAR -->
<div class="topbar">
    <button class="hamburger" onclick="toggleMenu()">&#9776;</button>
    <div class="center-title">ICCAT CATFÉ</div>
    <div style="display:flex; align-items:center; gap:16px;">
        <button class="bell-icon" onclick="location.href='orders.php'" title="Order updates">
            &#128276;
            <?php if ($unseenCount > 0): ?><span class="cart-badge"><?php echo $unseenCount; ?></span><?php endif; ?>
        </button>
        <button class="cart-icon" onclick="toggleCart()">
            &#128722;
            <span class="cart-badge" id="cartBadge"><?php echo $cartCount; ?></span>
        </button>
        <div class="logo-frame"><img src="assets/images/logo.jpg" alt="CATFÉ logo"></div>
    </div>
</div>

<!-- SIDE SLIDE MENU -->
<div class="overlay" id="overlay" onclick="toggleMenu()"></div>
<div class="side-menu" id="sideMenu">
    <button class="close-btn" onclick="toggleMenu()">&times;</button>
    <div class="user-info">
        Logged in as <br>
        <strong><?php echo htmlspecialchars($_SESSION['first_name'] . " " . $_SESSION['last_name']); ?></strong>
    </div>
    <h3>Menu</h3>
    <ul>
        <li onclick="location.href='#pastries'">🥐 View Pastries & Café Selections</li>
        <li onclick="location.href='orders.php'">📦 View My Orders</li>
        <li onclick="location.href='#about'">ℹ️ About Us</li>
        <li onclick="location.href='#community'">💬 Join Our Community</li>
        <li onclick="location.href='#reviews'">⭐ Reviews & Feedback</li>
        <li onclick="location.href='logout.php'">🚪 Log Out</li>
    </ul>
</div>

<!-- CART DRAWER -->
<div class="cart-drawer" id="cartDrawer">
    <div class="cart-header">
        <strong>My Cart</strong>
        <button class="close-btn" onclick="toggleCart()">&times;</button>
    </div>
    <div class="cart-items" id="cartItemsList"></div>
    <div class="cart-footer">
        <div class="total-line"><span>Total</span><span id="cartTotal">₱0.00</span></div>
        <button class="btn" onclick="goCheckout()">Proceed to Payment</button>
    </div>
</div>

<!-- SHOP INTRO — tagline, logo, about our shop (top of page, before the menu) -->
<section class="shop-intro">
    <span class="corner-paw tl">🐾</span>
    <span class="corner-paw tr">🐾</span>
    <span class="corner-paw bl">🐾</span>
    <span class="corner-paw br">🐾</span>
    <h1 class="shop-tagline">Brewed with Love, Baked with Paws</h1>
    <div class="logo-frame logo-frame-lg shop-intro-logo">
        <img src="assets/images/logo.jpg" alt="CATFÉ logo">
    </div>
    <p class="shop-about-text">
        ICCAT CATFÉ started as a small student project with one big dream: a cozy corner
        where good coffee, fresh-baked treats, and cat cuddles all live under one roof.
        Every pastry is made in small batches, every drink is brewed to order, and every
        visit is meant to feel like coming home to your favorite café cat.
    </p>
</section>

<?php if ($isNewCustomer && $welcomeVoucherCode): ?>
<div class="welcome-banner">
    🎉 New here? Use code <span class="welcome-code"><?php echo htmlspecialchars($welcomeVoucherCode['code']); ?></span>
    at checkout for <?php echo (int)$welcomeVoucherCode['discount_percent']; ?>% off your first order!
</div>
<?php endif; ?>

<!-- PASTRIES -->
<h2 class="section-title" id="pastries">🥐 Pastries</h2>
<div class="grid">
<?php while ($p = $pastries->fetch_assoc()): render_product_card($p); endwhile; ?>
</div>

<!-- DRINKS -->
<h2 class="section-title" id="drinks">☕ Café Selections</h2>

<h3 class="subsection-title">☕ Coffee</h3>
<div class="grid">
<?php while ($d = $coffeeDrinks->fetch_assoc()): render_product_card($d); endwhile; ?>
</div>

<h3 class="subsection-title">🍵 Non-Coffee</h3>
<div class="grid">
<?php while ($d = $nonCoffeeDrinks->fetch_assoc()): render_product_card($d); endwhile; ?>
</div>

<!-- ABOUT US -->
<div class="about-section" id="about">
    <h2 class="section-title" style="display:inline-block;">About Us</h2>
    <p>
        ICCAT CATFÉ is a student-run cat-themed bakery and café, serving cozy pastries
        and drinks with a purr-fect touch. Meet the team behind the counter:
    </p>
    <div class="circle-row">
        <?php foreach ($team as $member): ?>
        <div class="circle-item">
            <img src="<?php echo htmlspecialchars($member['image']); ?>" alt="<?php echo htmlspecialchars($member['name']); ?>">
            <h4><?php echo htmlspecialchars($member['name']); ?></h4>
            <p><?php echo htmlspecialchars($member['student_no']); ?></p>
            <p class="quote">"<?php echo htmlspecialchars($member['quote']); ?>"</p>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- JOIN COMMUNITY -->
<div class="community-section" id="community">
    <h2 class="section-title" style="display:inline-block;">Join Our Community</h2>
    <p>
        We'd love to have you around! Hop into our Discord to get updates, share your CATFÉ
        moments, and chat with fellow cat & coffee lovers.
    </p>
    <a href="<?php echo htmlspecialchars($discord_link); ?>" target="_blank" class="discord-btn">Join our Discord →</a>
</div>

<!-- REVIEWS & FEEDBACK -->
<div class="reviews-section" id="reviews">
    <h2 class="section-title" style="display:inline-block;">Reviews & Feedback</h2>
    <p>See what fellow cat café lovers are saying about their orders.</p>
    <?php if ($reviews->num_rows === 0): ?>
        <p style="color:#999;">No reviews yet — be the first to share your experience after your order is delivered!</p>
    <?php else: ?>
    <div class="reviews-grid">
        <?php while ($r = $reviews->fetch_assoc()): ?>
        <div class="review-card">
            <span class="review-card-stamp">🐾</span>
            <div class="review-card-stars"><?php echo str_repeat('★', $r['rating']) . str_repeat('☆', 5 - $r['rating']); ?></div>
            <?php if ($r['feedback']): ?>
                <p class="review-card-text">"<?php echo htmlspecialchars($r['feedback']); ?>"</p>
            <?php endif; ?>
            <p class="review-card-author"><?php echo htmlspecialchars($r['first_name'] . " " . substr($r['last_name'], 0, 1) . "."); ?>, <?php echo date("M Y", strtotime($r['created_at'])); ?></p>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>
</div>

<!-- SECRET ADMIN ACCESS -->
<div class="footer-admin" onclick="openAdminModal()">•</div>

<!-- ADMIN PASSWORD MODAL -->
<div class="modal-overlay" id="adminModal">
    <div class="modal-box">
        <h3>Admin Access</h3>
        <input type="password" id="adminPassInput" placeholder="Enter admin password">
        <div id="adminError" style="color:#c0392b;font-size:12px;margin-bottom:10px;"></div>
        <button class="btn" onclick="submitAdminPass()">Enter</button>
    </div>
</div>

<!-- VARIANT PICKER MODAL (flavor for pastries, Iced/Hot for drinks) -->
<div class="modal-overlay" id="variantModal">
    <div class="modal-box">
        <button class="close-btn" style="float:right;color:var(--espresso);" onclick="closeVariantModal()">&times;</button>
        <h3 id="variantModalTitle">Choose a Flavor</h3>
        <p id="variantModalItemName" style="color:var(--caramel-dark);font-weight:700;margin-bottom:10px;"></p>
        <div id="variantOptions" class="variant-options"></div>
        <div id="variantError" style="color:#c0392b;font-size:12px;margin:8px 0;"></div>
        <button class="btn" onclick="confirmAddToCart()">Add to Cart</button>
    </div>
</div>

<script>
    window.PRODUCT_VARIANTS = <?php echo json_encode($variantMap); ?>;
</script>
<script src="assets/js/script.js"></script>
</body>
</html>
