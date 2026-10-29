<?php
require_once __DIR__ . "/includes/functions.php";
require_login();

if (empty($_SESSION['cart'])) { header("Location: dashboard.php"); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: checkout.php"); exit; }

// ---- Server-side validation (never trust the client) ----
$first_name = clean($conn, $_POST['first_name'] ?? '');
$last_name  = clean($conn, $_POST['last_name'] ?? '');
$region     = clean($conn, $_POST['region'] ?? '');
$city       = clean($conn, $_POST['city'] ?? '');
$phone      = preg_replace('/[^0-9]/', '', $_POST['phone_number'] ?? '');
$address    = preg_replace('/[^a-zA-Z0-9 ]/', '', trim($_POST['address'] ?? ''));
$payment_method = "Cash on Delivery";

$errors = [];
if (!$first_name || !$last_name || !$region || !$city || !$phone || !$address) {
    $errors[] = "All fields are required.";
}
if (strlen($address) > 35) $errors[] = "Address must be 35 characters or fewer.";
if (strlen($phone) < 10) $errors[] = "Please enter a valid phone number.";

if (!empty($errors)) {
    // Kick back to checkout with cart intact
    $_SESSION['checkout_error'] = implode(" ", $errors);
    header("Location: checkout.php");
    exit;
}

$cart = $_SESSION['cart'];
$subtotal = 0;
$items_summary = [];
foreach ($cart as $item) {
    $subtotal += $item['price'] * $item['qty'];
    $items_summary[] = $item['name'] . " x" . $item['qty'];
}
$items_summary_str = implode(", ", $items_summary);

// ---- Voucher (trust only what's in session — it was validated server-side when applied) ----
$user_id = $_SESSION['user_id'];
$voucher = $_SESSION['applied_voucher'] ?? null;
$voucher_code = null;
$discount_amount = 0;

if ($voucher) {
    // Re-check it's still valid/unused right before charging, in case someone else
    // redeemed it in the meantime
    $vcheck = $conn->query("SELECT id, is_active, is_used, is_new_user_only, discount_percent FROM vouchers WHERE id = " . (int)$voucher['id']);
    if ($vcheck && $vrow = $vcheck->fetch_assoc()) {
        $stillValid = $vrow['is_active'];
        if ($vrow['is_new_user_only']) {
            // must still be this user's first (non-cancelled) order, and not already redeemed by them
            $priorOrders = $conn->query("SELECT COUNT(*) as c FROM orders WHERE user_id = $user_id AND status != 'Cancelled'")->fetch_assoc()['c'];
            $already = $conn->query("SELECT id FROM voucher_new_user_redemptions WHERE voucher_id = " . $vrow['id'] . " AND user_id = $user_id")->num_rows;
            if ($priorOrders > 0 || $already > 0) $stillValid = false;
        } else {
            if ($vrow['is_used']) $stillValid = false;
        }

        if ($stillValid) {
            $voucher_code = $voucher['code'];
            $discount_amount = round($subtotal * ($vrow['discount_percent'] / 100), 2);
        }
    }
}

$total = $subtotal - $discount_amount;

$stmt = $conn->prepare("INSERT INTO orders (user_id, first_name, last_name, city, region, phone_number, address, subtotal, voucher_code, discount_amount, total_payment, payment_method, status, last_seen_status)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'Pending', 'Pending')");
$stmt->bind_param("issssssdsdds", $user_id, $first_name, $last_name, $city, $region, $phone, $address, $subtotal, $voucher_code, $discount_amount, $total, $payment_method);
$stmt->execute();
$order_id = $conn->insert_id;

// Redeem the voucher: new-user codes track per-user redemption (code stays
// usable for other new customers); regular codes lock globally.
if ($voucher_code) {
    if ($voucher['is_new_user_only']) {
        $conn->query("INSERT INTO voucher_new_user_redemptions (voucher_id, user_id) VALUES (" . (int)$voucher['id'] . ", $user_id) ON CONFLICT DO NOTHING");
    } else {
        $conn->query("UPDATE vouchers SET is_used = 1, used_by = $user_id, used_at = NOW() WHERE id = " . (int)$voucher['id']);
    }
}
unset($_SESSION['applied_voucher']);

foreach ($cart as $item) {
    $pid = $item['product_id'];
    $name = $conn->real_escape_string($item['name']);
    $variant = $conn->real_escape_string($item['variant'] ?? '');
    $qty = $item['qty'];
    $price = $item['price'];
    $conn->query("INSERT INTO order_items (order_id, product_id, product_name, variant_name, quantity, price)
        VALUES ($order_id, $pid, '$name', '$variant', $qty, $price)");

    // decrement stock, never below 0
    $conn->query("UPDATE products SET stock = GREATEST(stock - $qty, 0) WHERE id = $pid");
}

// Get user email for the sheet
$emailRow = $conn->query("SELECT email FROM users WHERE id = $user_id")->fetch_assoc();

$pushed = push_order_to_sheet([
    "id" => $order_id,
    "email" => $emailRow['email'] ?? '',
    "first_name" => $first_name,
    "last_name" => $last_name,
    "city" => $city,
    "region" => $region,
    "phone_number" => $phone,
    "address" => $address,
    "items_summary" => $items_summary_str,
    "total_payment" => $total,
    "status" => "Pending",
]);

if ($pushed) {
    $conn->query("UPDATE orders SET sheet_synced = 1 WHERE id = $order_id");
}

// clear cart
$_SESSION['cart'] = [];

header("Location: orders.php?placed=1");
exit;
