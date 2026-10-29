<?php
require_once __DIR__ . "/includes/functions.php";
header("Content-Type: application/json");
require_login();

$code = strtoupper(trim($_POST['code'] ?? ''));
$code = preg_replace('/[^A-Z0-9]/', '', $code);
$user_id = $_SESSION['user_id'];

if (!$code) {
    echo json_encode(["status" => "error", "message" => "Please enter a voucher code."]);
    exit;
}

$stmt = $conn->prepare("SELECT id, code, discount_percent, is_active, is_used, is_new_user_only FROM vouchers WHERE code = ?");
$stmt->bind_param("s", $code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Not working — that voucher code doesn't exist."]);
    exit;
}

$voucher = $result->fetch_assoc();

if (!$voucher['is_active']) {
    echo json_encode(["status" => "error", "message" => "This voucher is no longer active."]);
    exit;
}

if ($voucher['is_new_user_only']) {
    // New-customer welcome code: usable once by any customer who has never
    // placed an order, and only once per person (tracked separately, not shared).
    $orderCount = $conn->query("SELECT COUNT(*) as c FROM orders WHERE user_id = $user_id AND status != 'Cancelled'")->fetch_assoc()['c'];
    if ($orderCount > 0) {
        echo json_encode(["status" => "error", "message" => "This welcome code is only for new customers placing their first order."]);
        exit;
    }

    $check = $conn->prepare("SELECT id FROM voucher_new_user_redemptions WHERE voucher_id = ? AND user_id = ?");
    $check->bind_param("ii", $voucher['id'], $user_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Already redeemed by another customer."]);
        exit;
    }
} else {
    // Regular code: single-use across the whole shop
    if ($voucher['is_used']) {
        echo json_encode(["status" => "error", "message" => "Already redeemed by another customer."]);
        exit;
    }
}

// Save to session so checkout_process.php can trust it without re-parsing client input
$_SESSION['applied_voucher'] = [
    "id" => $voucher['id'],
    "code" => $voucher['code'],
    "discount_percent" => (float)$voucher['discount_percent'],
    "is_new_user_only" => (bool)$voucher['is_new_user_only']
];

echo json_encode([
    "status" => "ok",
    "message" => "Applied! " . $voucher['discount_percent'] . "% off.",
    "code" => $voucher['code'],
    "discount_percent" => (float)$voucher['discount_percent']
]);
