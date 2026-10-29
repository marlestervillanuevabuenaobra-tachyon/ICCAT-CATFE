<?php
require_once __DIR__ . "/../includes/functions.php";
require_admin();

$message = "";
$validStatuses = ['Pending', 'Processing', 'Out for Delivery', 'Delivered', 'Cancelled'];

// ---- Update an order's delivery status ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $newStatus = $_POST['status'] ?? '';

    if (!in_array($newStatus, $validStatuses, true)) {
        $message = "Invalid status.";
    } else {
        $orderRow = $conn->query("SELECT * FROM orders WHERE id = $order_id")->fetch_assoc();
        if (!$orderRow) {
            $message = "Order not found.";
        } else {
            $oldStatus = $orderRow['status'];
            $safeStatus = $conn->real_escape_string($newStatus);
            $conn->query("UPDATE orders SET status = '$safeStatus' WHERE id = $order_id");

            // If newly cancelled, put stock back and free up any voucher used
            if ($newStatus === 'Cancelled' && $oldStatus !== 'Cancelled') {
                restock_order_items($conn, $order_id);
                release_order_voucher($conn, $orderRow);
            }

            push_status_to_sheet($order_id, $newStatus);
            $message = "Order #$order_id updated to \"$newStatus\".";
        }
    }
}

$statusFilter = $_GET['filter'] ?? 'all';
if (!in_array($statusFilter, $validStatuses, true)) $statusFilter = 'all';
$where = $statusFilter === 'all' ? '' : "WHERE o.status = '" . $conn->real_escape_string($statusFilter) . "'";

$orders = $conn->query("
    SELECT o.*, u.first_name AS cust_first, u.last_name AS cust_last, u.email,
           (SELECT STRING_AGG(product_name || ' x' || quantity, ', ')
              FROM order_items WHERE order_id = o.id) AS items_summary
    FROM orders o
    JOIN users u ON o.user_id = u.id
    $where
    ORDER BY o.created_at DESC
    LIMIT 150
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Orders - CATFÉ Admin</title>
<link rel="stylesheet" href="../assets/css/style.css?v=6">
</head>
<body>
<div class="topbar">
    <div class="center-title">📦 Manage Orders</div>
    <div style="margin-left:auto;">
        <a href="admin_dashboard.php" style="margin-right:16px;font-size:13px;color:var(--milk);">🥐 Products</a>
        <a href="vouchers.php" style="margin-right:16px;font-size:13px;color:var(--milk);">🎟️ Vouchers</a>
        <a href="admin_logout.php" style="font-size:13px;color:var(--milk);">Log out</a>
    </div>
</div>

<?php if ($message): ?>
    <div class="success-msg" style="margin:20px;max-width:500px;"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="order-filter-tabs">
    <a href="?filter=all" class="<?php echo $statusFilter === 'all' ? 'active' : ''; ?>">All</a>
    <?php foreach ($validStatuses as $s): ?>
        <a href="?filter=<?php echo urlencode($s); ?>" class="<?php echo $statusFilter === $s ? 'active' : ''; ?>"><?php echo $s; ?></a>
    <?php endforeach; ?>
</div>

<table class="admin-table">
    <thead>
        <tr>
            <th>Order</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th><th></th>
        </tr>
    </thead>
    <tbody>
        <?php if ($orders->num_rows === 0): ?>
            <tr><td colspan="6" style="text-align:center;color:#999;padding:24px;">No orders in this filter.</td></tr>
        <?php endif; ?>
        <?php while ($o = $orders->fetch_assoc()):
            $formId = "oform" . $o['id'];
            $statusClass = "status-" . str_replace(" ", "-", $o['status']);
        ?>
        <tr>
            <td>
                <strong>#<?php echo $o['id']; ?></strong><br>
                <span style="font-size:11px;color:#999;"><?php echo date("M d, Y g:i A", strtotime($o['created_at'])); ?></span>
            </td>
            <td style="font-size:12.5px;">
                <?php echo htmlspecialchars($o['cust_first'] . " " . $o['cust_last']); ?><br>
                <span style="color:#999;"><?php echo htmlspecialchars($o['email']); ?></span>
            </td>
            <td style="font-size:12px;max-width:220px;"><?php echo htmlspecialchars($o['items_summary'] ?? '—'); ?></td>
            <td style="font-family:var(--font-mono);font-size:13px;">₱<?php echo number_format($o['total_payment'], 2); ?></td>
            <td>
                <span class="status-badge <?php echo $statusClass; ?>" style="margin-bottom:6px;display:inline-flex;"><?php echo htmlspecialchars($o['status']); ?></span><br>
                <select name="status" form="<?php echo $formId; ?>" class="order-status-select">
                    <?php foreach ($validStatuses as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo $o['status'] === $s ? "selected" : ""; ?>><?php echo $s; ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <input type="hidden" name="order_id" form="<?php echo $formId; ?>" value="<?php echo $o['id']; ?>">
                <button type="submit" name="update_status" form="<?php echo $formId; ?>" class="save-row-btn">Update</button>
            </td>
        </tr>
        <!-- Out-of-line form (see vouchers.php for why) -->
        <form id="<?php echo $formId; ?>" method="POST"></form>
        <?php endwhile; ?>
    </tbody>
</table>

<p style="margin:20px;font-size:12px;color:#999;">
    Updating a status here also pushes to your Google Sheet (if configured) and clears the
    customer's "seen" state, so they'll get a 🔔 notification next time they open View Orders.
</p>

</body>
</html>
