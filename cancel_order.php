<?php
require_once __DIR__ . "/includes/functions.php";
header("Content-Type: application/json");
require_login();

$order_id = (int)($_POST['order_id'] ?? 0);
$user_id = $_SESSION['user_id'];

$check = $conn->query("SELECT status, voucher_code, user_id FROM orders WHERE id = $order_id AND user_id = $user_id");
if ($check->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Order not found."]);
    exit;
}
$order = $check->fetch_assoc();
if (!in_array($order['status'], ['Pending', 'Processing'])) {
    echo json_encode(["status" => "error", "message" => "This order can no longer be cancelled."]);
    exit;
}

// This is the customer's own action, so mark it as "seen" immediately — no notification needed
$conn->query("UPDATE orders SET status = 'Cancelled', last_seen_status = 'Cancelled' WHERE id = $order_id");

release_order_voucher($conn, $order);
restock_order_items($conn, $order_id);
push_status_to_sheet($order_id, "Cancelled");

echo json_encode(["status" => "ok"]);
