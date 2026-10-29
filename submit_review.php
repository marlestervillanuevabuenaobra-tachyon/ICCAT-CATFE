<?php
require_once __DIR__ . "/includes/functions.php";
header("Content-Type: application/json");
require_login();

$order_id = (int)($_POST['order_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$feedback = trim($_POST['feedback'] ?? '');
$user_id = $_SESSION['user_id'];

if ($rating < 1 || $rating > 5) {
    echo json_encode(["status" => "error", "message" => "Please select a star rating."]);
    exit;
}

$order = $conn->query("SELECT id, status FROM orders WHERE id = $order_id AND user_id = $user_id")->fetch_assoc();
if (!$order) {
    echo json_encode(["status" => "error", "message" => "Order not found."]);
    exit;
}
if ($order['status'] !== 'Delivered') {
    echo json_encode(["status" => "error", "message" => "You can only review orders that have been delivered."]);
    exit;
}

if (strlen($feedback) > 500) $feedback = substr($feedback, 0, 500);

$stmt = $conn->prepare("INSERT INTO reviews (order_id, user_id, rating, feedback) VALUES (?, ?, ?, ?)
    ON CONFLICT (order_id) DO UPDATE SET rating = EXCLUDED.rating, feedback = EXCLUDED.feedback");
$stmt->bind_param("iiis", $order_id, $user_id, $rating, $feedback);
$stmt->execute();

echo json_encode(["status" => "ok", "message" => "Thanks for your feedback!"]);
