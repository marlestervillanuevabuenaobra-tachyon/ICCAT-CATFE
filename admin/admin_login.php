<?php
require_once __DIR__ . "/../includes/functions.php";
header("Content-Type: application/json");

$ADMIN_PASSWORD = getenv('ADMIN_PASSWORD') ?: "catfebrigida";

$password = $_POST['password'] ?? '';
if ($password === $ADMIN_PASSWORD) {
    $_SESSION['is_admin'] = true;
    $conn->query("INSERT INTO admin_log (action) VALUES ('Admin logged in')");
    echo json_encode(["status" => "ok"]);
} else {
    echo json_encode(["status" => "error"]);
}
