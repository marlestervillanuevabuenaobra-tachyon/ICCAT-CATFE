<?php
require_once __DIR__ . "/../includes/functions.php";
unset($_SESSION['is_admin']);
header("Location: ../dashboard.php");
exit;
