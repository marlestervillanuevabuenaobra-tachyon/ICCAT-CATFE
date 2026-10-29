<?php
require_once __DIR__ . "/../includes/functions.php";
require_admin();

$message = "";

// ---- Create voucher ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_voucher'])) {
    $code = strtoupper(trim($_POST['code']));
    $code = preg_replace('/[^A-Z0-9]/', '', $code); // letters/numbers only
    $discount = (float)$_POST['discount_percent'];
    $newUserOnly = isset($_POST['is_new_user_only']) ? 1 : 0;

    if (!$code) {
        $message = "Please enter a voucher code.";
    } elseif ($discount <= 0 || $discount > 100) {
        $message = "Discount must be between 1 and 100 percent.";
    } else {
        $check = $conn->prepare("SELECT id FROM vouchers WHERE code = ?");
        $check->bind_param("s", $code);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $message = "A voucher with that code already exists.";
        } else {
            $stmt = $conn->prepare("INSERT INTO vouchers (code, discount_percent, is_new_user_only) VALUES (?, ?, ?)");
            $stmt->bind_param("sdi", $code, $discount, $newUserOnly);
            $stmt->execute();
            $message = "Voucher \"$code\" created.";
        }
    }
}

// ---- Update discount / active status ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_voucher'])) {
    $id = (int)$_POST['voucher_id'];
    $discount = (float)$_POST['discount_percent'];
    $active = isset($_POST['is_active']) ? 1 : 0;

    if ($discount > 0 && $discount <= 100) {
        $stmt = $conn->prepare("UPDATE vouchers SET discount_percent = ?, is_active = ? WHERE id = ?");
        $stmt->bind_param("dii", $discount, $active, $id);
        $stmt->execute();
        $message = "Voucher updated.";
    } else {
        $message = "Discount must be between 1 and 100 percent.";
    }
}

// ---- Delete voucher ----
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM vouchers WHERE id = $id");
    header("Location: vouchers.php");
    exit;
}

// ---- Reset: standard voucher -> back to unused. New-user voucher -> clear everyone's redemption history ----
if (isset($_GET['reset'])) {
    $id = (int)$_GET['reset'];
    $conn->query("UPDATE vouchers SET is_used = 0, used_by = NULL, used_at = NULL WHERE id = $id");
    $conn->query("DELETE FROM voucher_new_user_redemptions WHERE voucher_id = $id");
    header("Location: vouchers.php");
    exit;
}

$vouchers = $conn->query("
    SELECT v.*, u.first_name, u.last_name,
           (SELECT COUNT(*) FROM voucher_new_user_redemptions r WHERE r.voucher_id = v.id) AS redemption_count
    FROM vouchers v
    LEFT JOIN users u ON v.used_by = u.id
    ORDER BY v.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vouchers - CATFÉ Admin</title>
<link rel="stylesheet" href="../assets/css/style.css?v=6">
</head>
<body>
<div class="topbar">
    <div class="center-title">🎟️ Voucher Codes</div>
    <div style="margin-left:auto;">
        <a href="orders.php" style="margin-right:16px;font-size:13px;color:var(--milk);">📦 Orders</a>
        <a href="admin_dashboard.php" style="margin-right:16px;font-size:13px;color:var(--milk);">← Products</a>
        <a href="admin_logout.php" style="font-size:13px;color:var(--milk);">Log out</a>
    </div>
</div>

<?php if ($message): ?>
    <div class="success-msg" style="margin:20px;max-width:500px;"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="voucher-create-box">
    <h3>Create a new voucher</h3>
    <form method="POST" class="voucher-create-form">
        <input type="text" name="code" placeholder="e.g. CATLOVER15" maxlength="30" required>
        <input type="number" name="discount_percent" placeholder="Discount %" min="1" max="100" step="0.01" required>
        <button type="submit" name="create_voucher" class="btn" style="width:auto;padding:11px 22px;">Create</button>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--mocha);font-weight:700;width:100%;margin-top:4px;">
            <input type="checkbox" name="is_new_user_only" style="width:16px;height:16px;">
            Make this a "new customers only" welcome code (one use per new customer, not shop-wide)
        </label>
    </form>
    <p class="voucher-hint">
        <strong>Standard codes</strong> are single-use across the whole shop — once any customer redeems one, it's marked
        used and can't be applied again.<br>
        <strong>"New customers only" codes</strong> work differently: every brand-new customer (someone with no prior
        orders) can redeem the same code once each, like a permanent welcome offer.
    </p>
</div>

<table class="admin-table">
    <thead>
        <tr>
            <th>Code</th><th>Discount</th><th>Type</th><th>Status</th><th>Active?</th><th></th>
        </tr>
    </thead>
    <tbody>
        <?php while ($v = $vouchers->fetch_assoc()):
            $formId = "vform" . $v['id'];
        ?>
        <tr>
            <td><span class="voucher-code-tag"><?php echo htmlspecialchars($v['code']); ?></span></td>
            <td>
                <input type="number" name="discount_percent" form="<?php echo $formId; ?>"
                       value="<?php echo $v['discount_percent']; ?>" min="1" max="100" step="0.01" style="width:70px;"> %
            </td>
            <td style="font-size:12px;">
                <?php if ($v['is_new_user_only']): ?>
                    <span class="voucher-status newuser">New Customers Only</span>
                <?php else: ?>
                    <span style="color:var(--brown);">Standard</span>
                <?php endif; ?>
            </td>
            <td style="font-size:12px;">
                <?php if ($v['is_new_user_only']): ?>
                    <?php echo (int)$v['redemption_count']; ?> customer<?php echo $v['redemption_count'] == 1 ? '' : 's'; ?> redeemed
                <?php elseif ($v['is_used']): ?>
                    <span class="voucher-status used">Used</span>
                    <?php if ($v['used_by']): ?>
                        <br><span style="color:#999;">by <?php echo htmlspecialchars($v['first_name'] . " " . $v['last_name']); ?> · <?php echo date("M d, Y", strtotime($v['used_at'])); ?></span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="voucher-status available">Available</span>
                <?php endif; ?>
            </td>
            <td>
                <input type="checkbox" name="is_active" form="<?php echo $formId; ?>" <?php echo $v['is_active'] ? "checked" : ""; ?>>
            </td>
            <td style="white-space:nowrap;">
                <input type="hidden" name="voucher_id" form="<?php echo $formId; ?>" value="<?php echo $v['id']; ?>">
                <button type="submit" name="update_voucher" form="<?php echo $formId; ?>" class="save-row-btn">Save</button>
                <a href="?reset=<?php echo $v['id']; ?>" onclick="return confirm('<?php echo $v['is_new_user_only'] ? 'Clear every customer\'s redemption history for this code?' : 'Reset this voucher back to unused?'; ?>')" class="voucher-mini-btn">Reset</a>
                <a href="?delete=<?php echo $v['id']; ?>" onclick="return confirm('Delete this voucher permanently?')" class="voucher-mini-btn danger">Delete</a>
            </td>
        </tr>
        <!-- Out-of-line form: the inputs above reference it via the form="" attribute,
             which keeps the table markup valid (a <form> can't legally span table cells). -->
        <form id="<?php echo $formId; ?>" method="POST"></form>
        <?php endwhile; ?>
    </tbody>
</table>

</body>
</html>
