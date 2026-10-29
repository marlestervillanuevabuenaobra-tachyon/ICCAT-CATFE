<?php
require_once __DIR__ . "/includes/functions.php";
require_login();

// Pull the latest statuses that may have been updated directly in the Google Sheet
sync_status_from_sheet($conn);

$user_id = $_SESSION['user_id'];
$orders = $conn->query("SELECT * FROM orders WHERE user_id = $user_id ORDER BY created_at DESC");

// Collect which orders have an unseen status change so we can show a notification
// banner for each, then mark them all as seen now that the customer is viewing this page
$ordersData = [];
$idsToMarkSeen = [];
while ($o = $orders->fetch_assoc()) {
    $o['is_update'] = ($o['last_seen_status'] !== null && $o['last_seen_status'] !== $o['status']);
    if ($o['is_update']) $idsToMarkSeen[] = $o['id'];

    $o['review'] = null;
    if ($o['status'] === 'Delivered') {
        $o['review'] = $conn->query("SELECT rating, feedback FROM reviews WHERE order_id = " . $o['id'])->fetch_assoc();
    }

    $ordersData[] = $o;
}
if (!empty($idsToMarkSeen)) {
    $conn->query("UPDATE orders SET last_seen_status = status WHERE id IN (" . implode(",", $idsToMarkSeen) . ")");
}

$statusMessages = [
    'Processing'       => 'Your order is being prepared! 🐾',
    'Out for Delivery' => 'Your order is out for delivery! 🚚',
    'Delivered'        => 'Your order has arrived — enjoy! 🎉',
    'Cancelled'        => 'This order was cancelled.',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Orders - ICCAT CATFÉ</title>
<link rel="stylesheet" href="assets/css/style.css?v=6">
</head>
<body>
<div class="topbar">
    <a href="dashboard.php" class="hamburger" style="text-decoration:none;">&#8592;</a>
    <div class="center-title">My Orders</div>
    <div class="logo-frame"><img src="assets/images/logo.jpg" alt="logo"></div>
</div>

<?php if (isset($_GET['placed'])): ?>
    <div class="success-msg" style="margin:20px;">Order placed! We'll get it ready and out for delivery soon.</div>
<?php endif; ?>

<?php if (empty($ordersData)): ?>
    <p style="text-align:center;color:#999;margin-top:40px;">You haven't placed any orders yet.</p>
<?php endif; ?>

<?php foreach ($ordersData as $o):
    $items = $conn->query("SELECT product_name, quantity, price FROM order_items WHERE order_id = " . $o['id']);
    $statusClass = "status-" . str_replace(" ", "-", $o['status']);
?>
<div class="order-card">
    <?php if ($o['is_update'] && isset($statusMessages[$o['status']])): ?>
        <div class="status-update-banner">🔔 <?php echo htmlspecialchars($statusMessages[$o['status']]); ?></div>
    <?php endif; ?>

    <div class="top-row">
        <div>
            <strong>Order #<?php echo $o['id']; ?></strong>
            <div style="font-size:12px;color:#999;"><?php echo date("M d, Y g:i A", strtotime($o['created_at'])); ?></div>
        </div>
        <span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($o['status']); ?></span>
    </div>

    <div style="margin-top:10px;font-size:13px;color:var(--brown);">
        <?php while ($it = $items->fetch_assoc()): ?>
            <div><?php echo htmlspecialchars($it['product_name']); ?> x<?php echo $it['quantity']; ?> — ₱<?php echo number_format($it['price'] * $it['quantity'], 2); ?></div>
        <?php endwhile; ?>
    </div>

    <div style="margin-top:8px;font-size:13px;">
        <strong>Deliver to:</strong> <?php echo htmlspecialchars($o['address'] . ", " . $o['city'] . ", " . $o['region']); ?><br>
        <?php if ($o['voucher_code']): ?>
            <span style="color:var(--ok);">🎟️ Voucher <?php echo htmlspecialchars($o['voucher_code']); ?> applied — saved ₱<?php echo number_format($o['discount_amount'], 2); ?></span><br>
        <?php endif; ?>
        <strong>Total:</strong> ₱<?php echo number_format($o['total_payment'], 2); ?> (Cash on Delivery)
    </div>

    <?php if ($o['status'] === 'Delivered'): ?>
        <a href="<?php echo htmlspecialchars(DISCORD_INVITE_LINK); ?>" target="_blank" class="discord-review-btn">
            🐾 Go to Discord to review your satisfaction
        </a>

        <div class="review-box" id="reviewBox<?php echo $o['id']; ?>">
            <span class="review-box-label">🐾 Rate Your Order</span>
            <?php if ($o['review']): ?>
                <div class="review-submitted">
                    <div class="review-submitted-badge">✓ Reviewed</div><br>
                    <div class="star-display"><?php echo str_repeat('★', $o['review']['rating']) . str_repeat('☆', 5 - $o['review']['rating']); ?></div>
                    <?php if ($o['review']['feedback']): ?>
                        <p class="review-feedback-text">"<?php echo htmlspecialchars($o['review']['feedback']); ?>"</p>
                    <?php endif; ?>
                    <p style="font-size:11px;color:#999;margin-top:8px;">Thanks for your feedback!</p>
                </div>
            <?php else: ?>
                <p class="review-prompt">How was your experience?</p>
                <div class="star-picker" id="starPicker<?php echo $o['id']; ?>">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="star" data-value="<?php echo $i; ?>" onclick="setStarRating(<?php echo $o['id']; ?>, <?php echo $i; ?>)">★</span>
                    <?php endfor; ?>
                </div>
                <input type="hidden" id="ratingInput<?php echo $o['id']; ?>" value="0">
                <textarea id="feedbackInput<?php echo $o['id']; ?>" class="review-textarea" placeholder="Tell us about your experience... (optional)" maxlength="500"></textarea>
                <div id="reviewError<?php echo $o['id']; ?>" style="color:#c0392b;font-size:12px;margin:6px 0;"></div>
                <button class="btn" style="margin-top:10px;" onclick="submitReview(<?php echo $o['id']; ?>)">Submit Review</button>
            <?php endif; ?>
        </div>
    <?php elseif (in_array($o['status'], ['Pending', 'Processing'])): ?>
        <button class="cancel-btn" onclick="cancelOrder(<?php echo $o['id']; ?>, this)">Cancel Order</button>
    <?php else: ?>
        <button class="cancel-btn" disabled>
            <?php echo $o['status'] === 'Cancelled' ? 'Cancelled' : 'Cannot cancel — ' . htmlspecialchars($o['status']); ?>
        </button>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<script>
function cancelOrder(orderId, btn) {
    if (!confirm('Cancel this order?')) return;
    fetch('cancel_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'order_id=' + orderId
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'ok') {
            location.reload();
        } else {
            alert(data.message || 'Could not cancel this order.');
        }
    });
}

function setStarRating(orderId, value) {
    document.getElementById('ratingInput' + orderId).value = value;
    const stars = document.querySelectorAll('#starPicker' + orderId + ' .star');
    stars.forEach(s => {
        s.classList.toggle('filled', parseInt(s.dataset.value) <= value);
    });
}

function submitReview(orderId) {
    const rating = document.getElementById('ratingInput' + orderId).value;
    const feedback = document.getElementById('feedbackInput' + orderId).value;
    const errorEl = document.getElementById('reviewError' + orderId);

    if (!rating || rating < 1) {
        errorEl.innerText = 'Please select a star rating first.';
        return;
    }
    errorEl.innerText = '';

    fetch('submit_review.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'order_id=' + orderId + '&rating=' + rating + '&feedback=' + encodeURIComponent(feedback)
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'ok') {
            const stars = '★'.repeat(rating) + '☆'.repeat(5 - rating);
            const feedbackHtml = feedback.trim()
                ? `<p class="review-feedback-text">"${feedback.replace(/</g, '&lt;')}"</p>`
                : '';
            document.getElementById('reviewBox' + orderId).innerHTML =
                `<span class="review-box-label">🐾 Rate Your Order</span>
                <div class="review-submitted">
                    <div class="review-submitted-badge">✓ Reviewed</div><br>
                    <div class="star-display">${stars}</div>
                    ${feedbackHtml}
                    <p style="font-size:11px;color:#999;margin-top:8px;">Thanks for your feedback!</p>
                </div>`;
        } else {
            errorEl.innerText = data.message;
        }
    });
}
</script>
</body>
</html>
