<?php
require_once __DIR__ . "/includes/functions.php";
if (is_logged_in()) { header("Location: dashboard.php"); exit; }

$error = "";
if (isset($_GET['error'])) $error = $_GET['error'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $email = clean($conn, $_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, first_name, last_name, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Incorrect password.";
        }
    } else {
        $error = "No account found with that email.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Log In - ICCAT CATFÉ</title>
<link rel="stylesheet" href="assets/css/style.css?v=6">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-visual">
        <div class="auth-visual-text">
            <h2>Welcome to ICCAT CATFÉ</h2>
            <p>Cozy pastries, warm drinks, and a whole lot of paw-sitivity waiting for you.</p>
        </div>
    </div>
    <div class="auth-form-panel">
        <div class="auth-card">
            <div class="logo-frame logo-frame-lg" style="margin:0 auto;">
                <img src="assets/images/logo.jpg" alt="logo">
            </div>
            <h2>Welcome Back</h2>
            <p class="sub">Log in to your CATFÉ account</p>

            <?php if ($error): ?><div class="error-msg"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <?php if (isset($_GET['registered'])): ?><div class="success-msg">Account created! Please log in.</div><?php endif; ?>

            <form method="POST">
                <input type="email" name="email" placeholder="Email" required>
                <div class="password-wrap">
                    <input type="password" name="password" id="passwordInput" placeholder="Password" required>
                    <button type="button" class="toggle-pass" onclick="togglePassword('passwordInput', this)">Show</button>
                </div>
                <button type="submit" name="login_submit" class="btn">Log In</button>
            </form>

            <p class="switch-link">Don't have an account? <a href="signup.php">Sign up</a></p>
        </div>
    </div>
</div>

<script src="assets/js/script.js"></script>
</body>
</html>
