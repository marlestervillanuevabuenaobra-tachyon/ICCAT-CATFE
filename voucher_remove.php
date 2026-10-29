<?php
require_once __DIR__ . "/includes/functions.php";
header("Content-Type: application/json");
require_login();

unset($_SESSION['applied_voucher']);
echo json_encode(["status" => "ok"]);
