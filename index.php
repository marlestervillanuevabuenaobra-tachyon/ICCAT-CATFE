<?php
require_once __DIR__ . "/includes/functions.php";
$next = is_logged_in() ? "dashboard.php" : "login.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>ICCAT CATFÉ</title>
<link rel="stylesheet" href="assets/css/style.css?v=6">
</head>
<body>
<div class="loading-screen">
    <div class="paw-trail">
        <span class="paw p1"><i></i><i></i><i></i></span>
        <span class="paw p2"><i></i><i></i><i></i></span>
        <span class="paw p3"><i></i><i></i><i></i></span>
    </div>
    <div class="logo-frame logo-frame-lg">
        <img src="assets/images/logo.jpg" alt="CATFÉ logo">
    </div>
    <div class="loading-text" style="margin-top:16px;">taking you to our <span>catfé</span> ...</div>
</div>
<script>
    setTimeout(function () {
        window.location.href = "<?php echo $next; ?>";
    }, 5000);
</script>
</body>
</html>
