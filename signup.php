<?php
require_once __DIR__ . "/includes/functions.php";
if (is_logged_in()) { header("Location: dashboard.php"); exit; }

$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first = clean($conn, $_POST['first_name']);
    $last  = clean($conn, $_POST['last_name']);
    $email = clean($conn, $_POST['email']);
    $pass  = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if (!$first || !$last || !$email || !$pass) {
        $error = "Please fill up all fields.";
    } elseif ($pass !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "An account with that email already exists.";
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password) VALUES (?,?,?,?)");
            $stmt->bind_param("ssss", $first, $last, $email, $hash);
            $stmt->execute();
            header("Location: login.php?registered=1");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign Up - ICCAT CATFÉ</title>
<link rel="stylesheet" href="assets/css/style.css?v=6">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-visual">
        <div class="auth-visual-text">
            <h2>Join ICCAT CATFÉ</h2>
            <p>Create an account to start ordering fresh pastries and drinks, and track every order right from your dashboard.</p>
        </div>
    </div>
    <div class="auth-form-panel">
        <div class="auth-card">
            <div class="logo-frame logo-frame-lg" style="margin:0 auto;">
                <img src="assets/images/logo.jpg" alt="logo">
            </div>
            <h2>Create an Account</h2>
            <p class="sub">Join the CATFÉ community</p>

            <?php if ($error): ?><div class="error-msg"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <form method="POST">
                <input type="text" name="first_name" placeholder="First Name" required>
                <input type="text" name="last_name" placeholder="Last Name" required>
                <input type="email" name="email" placeholder="Email" required>
                <div class="password-wrap">
                    <input type="password" name="password" id="pw1" placeholder="Password" required>
                    <button type="button" class="toggle-pass" onclick="togglePassword('pw1', this)">Show</button>
                </div>
                <div class="password-wrap">
                    <input type="password" name="confirm_password" id="pw2" placeholder="Confirm Password" required>
                    <button type="button" class="toggle-pass" onclick="togglePassword('pw2', this)">Show</button>
                </div>
                <button type="submit" class="btn">Sign Up</button>
            </form>

            <p class="switch-link">Already have an account? <a href="login.php">Log in</a></p>
        </div>
    </div>
</div>
<script src="assets/js/script.js"></script>
</body>
</html>
