<?php
session_start();
require_once __DIR__ . "/db.php";

// ---------- Google Sheets webhook ----------
// Paste the deployed Google Apps Script Web App URL here (see /google_sheets/apps_script.gs)
define("GOOGLE_SHEET_WEBHOOK_URL", "PASTE_YOUR_APPS_SCRIPT_WEB_APP_URL_HERE");

// Your Discord invite link — shown on the About/Community section and on
// delivered orders as a "review your satisfaction" prompt
define("DISCORD_INVITE_LINK", "https://discord.gg/YOUR_INVITE_CODE");

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }
}

function is_admin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

function require_admin() {
    if (!is_admin()) {
        header("Location: ../login.php");
        exit;
    }
}

function clean($conn, $str) {
    return htmlspecialchars(trim($conn->real_escape_string($str)));
}

// Push a new order to the Google Sheet (fire-and-forget, silent fail so checkout never breaks)
function push_order_to_sheet($order) {
    $url = GOOGLE_SHEET_WEBHOOK_URL;
    if (!$url || strpos($url, "PASTE_YOUR") !== false) return false;

    $payload = json_encode([
        "action" => "add_order",
        "order_id" => $order['id'],
        "email" => $order['email'],
        "first_name" => $order['first_name'],
        "last_name" => $order['last_name'],
        "city" => $order['city'],
        "region" => $order['region'],
        "phone_number" => $order['phone_number'],
        "address" => $order['address'],
        "orders" => $order['items_summary'],
        "total_payment" => $order['total_payment'],
        "status" => $order['status'],
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

// Push a status change to the Google Sheet (used by both customer cancellation
// and the admin order-status manager)
function push_status_to_sheet($order_id, $status) {
    $url = GOOGLE_SHEET_WEBHOOK_URL;
    if (!$url || strpos($url, "PASTE_YOUR") !== false) return false;

    $payload = json_encode(["action" => "update_status", "order_id" => $order_id, "status" => $status]);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

// Puts an order's items back into stock (used when an order is cancelled)
function restock_order_items($conn, $order_id) {
    $items = $conn->query("SELECT product_id, quantity FROM order_items WHERE order_id = " . (int)$order_id);
    while ($it = $items->fetch_assoc()) {
        if ($it['product_id']) {
            $conn->query("UPDATE products SET stock = stock + " . (int)$it['quantity'] . " WHERE id = " . (int)$it['product_id']);
        }
    }
}

// Releases any voucher used on an order so it can be redeemed again
// (used when an order is cancelled). $order needs voucher_code and user_id.
function release_order_voucher($conn, $order) {
    if (empty($order['voucher_code'])) return;
    $vc = $conn->real_escape_string($order['voucher_code']);
    $vRow = $conn->query("SELECT id, is_new_user_only FROM vouchers WHERE code = '$vc'")->fetch_assoc();
    if (!$vRow) return;

    if ($vRow['is_new_user_only']) {
        $conn->query("DELETE FROM voucher_new_user_redemptions WHERE voucher_id = " . $vRow['id'] . " AND user_id = " . (int)$order['user_id']);
    } else {
        $conn->query("UPDATE vouchers SET is_used = 0, used_by = NULL, used_at = NULL WHERE id = " . $vRow['id']);
    }
}

// Pull status updates that were made directly in the Google Sheet
// (call this whenever the user opens View Orders)
function sync_status_from_sheet($conn) {
    $url = GOOGLE_SHEET_WEBHOOK_URL;
    if (!$url || strpos($url, "PASTE_YOUR") !== false) return;

    $ch = curl_init($url . "?action=get_statuses");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $result = curl_exec($ch);
    curl_close($ch);
    if (!$result) return;

    $rows = json_decode($result, true);
    if (!is_array($rows)) return;

    foreach ($rows as $row) {
        if (!isset($row['order_id']) || !isset($row['status'])) continue;
        $oid = (int)$row['order_id'];
        $status = $conn->real_escape_string($row['status']);
        $conn->query("UPDATE orders SET status='$status' WHERE id=$oid AND status != 'Cancelled'");
    }
}
