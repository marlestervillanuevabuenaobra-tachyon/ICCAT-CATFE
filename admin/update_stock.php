<?php
require_once __DIR__ . "/../includes/functions.php";
header("Content-Type: application/json");
require_admin();

$id = (int)($_POST['id'] ?? 0);
$price = (float)($_POST['price'] ?? 0);
$stock = (int)($_POST['stock'] ?? 0);

if ($id <= 0 || $price < 0 || $stock < 0) {
    echo json_encode(["status" => "error"]);
    exit;
}

$stmt = $conn->prepare("UPDATE products SET price = ?, stock = ? WHERE id = ?");
$stmt->bind_param("dii", $price, $stock, $id);
$stmt->execute();

echo json_encode(["status" => "ok"]);
