<?php
require_once __DIR__ . "/includes/functions.php";
header("Content-Type: application/json");
require_login();

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

$action = $_POST['action'] ?? '';
$product_id = (int)($_POST['product_id'] ?? 0);
$variant = trim($_POST['variant'] ?? '');
$cart_key = $_POST['cart_key'] ?? '';

function make_cart_key($product_id, $variant) {
    return $product_id . "__" . preg_replace('/[^a-zA-Z0-9]/', '', $variant);
}

// Total quantity already in the cart for this product, across all its variants —
// used so stock is enforced per product, not per flavor.
function qty_in_cart_for_product($product_id) {
    $total = 0;
    foreach ($_SESSION['cart'] as $item) {
        if ($item['product_id'] == $product_id) $total += $item['qty'];
    }
    return $total;
}

if ($action === 'add') {
    if (!$variant) {
        echo json_encode(["status" => "error", "message" => "Please choose an option first."]);
        exit;
    }

    // Validate the product and that the variant genuinely belongs to it
    $result = $conn->query("SELECT id, name, price, stock, image FROM products WHERE id = $product_id");
    $product = $result->fetch_assoc();
    if (!$product) {
        echo json_encode(["status" => "error", "message" => "Item not found."]);
        exit;
    }

    $vstmt = $conn->prepare("SELECT id FROM product_variants WHERE product_id = ? AND variant_name = ?");
    $vstmt->bind_param("is", $product_id, $variant);
    $vstmt->execute();
    if ($vstmt->get_result()->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "That option isn't available for this item."]);
        exit;
    }

    $key = make_cart_key($product_id, $variant);
    $currentTotalQty = qty_in_cart_for_product($product_id);
    if ($currentTotalQty + 1 > $product['stock']) {
        echo json_encode(["status" => "error", "message" => "Not enough stock."]);
        exit;
    }

    if (isset($_SESSION['cart'][$key])) {
        $_SESSION['cart'][$key]['qty'] += 1;
    } else {
        $_SESSION['cart'][$key] = [
            "cart_key" => $key,
            "product_id" => $product['id'],
            "variant" => $variant,
            "name" => $product['name'] . " (" . $variant . ")",
            "price" => (float)$product['price'],
            "image" => $product['image'],
            "qty" => 1
        ];
    }
} elseif ($action === 'increase') {
    if (isset($_SESSION['cart'][$cart_key])) {
        $pid = $_SESSION['cart'][$cart_key]['product_id'];
        $stockRow = $conn->query("SELECT stock FROM products WHERE id = $pid")->fetch_assoc();
        if ($stockRow && qty_in_cart_for_product($pid) + 1 <= $stockRow['stock']) {
            $_SESSION['cart'][$cart_key]['qty'] += 1;
        }
    }
} elseif ($action === 'decrease') {
    if (isset($_SESSION['cart'][$cart_key])) {
        $_SESSION['cart'][$cart_key]['qty'] -= 1;
        if ($_SESSION['cart'][$cart_key]['qty'] <= 0) {
            unset($_SESSION['cart'][$cart_key]);
        }
    }
} elseif ($action === 'remove') {
    unset($_SESSION['cart'][$cart_key]);
} elseif ($action === 'clear') {
    $_SESSION['cart'] = [];
}
// action === 'noop' (or anything else) just falls through and returns current cart state

$cart = $_SESSION['cart'];
$total = 0;
$count = 0;
foreach ($cart as $item) {
    $total += $item['price'] * $item['qty'];
    $count += $item['qty'];
}

echo json_encode(["status" => "ok", "cart" => array_values($cart), "total" => $total, "count" => $count]);
