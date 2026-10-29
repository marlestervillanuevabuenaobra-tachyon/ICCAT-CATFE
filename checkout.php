<?php
require_once __DIR__ . "/includes/functions.php";
require_login();

if (empty($_SESSION['cart'])) {
    header("Location: dashboard.php");
    exit;
}

// Start each checkout visit with a clean slate — re-apply the code if still wanted
unset($_SESSION['applied_voucher']);

$cart = $_SESSION['cart'];
$subtotal = 0;
foreach ($cart as $item) $subtotal += $item['price'] * $item['qty'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Checkout - ICCAT CATFÉ</title>
<link rel="stylesheet" href="assets/css/style.css?v=6">
</head>
<body>
<div class="topbar">
    <a href="dashboard.php" class="hamburger" style="text-decoration:none;">&#8592;</a>
    <div class="center-title">Checkout</div>
    <div class="logo-frame"><img src="assets/images/logo.jpg" alt="logo"></div>
</div>

<div class="checkout-wrap">
    <h2>Shipping Address</h2>
    <?php if (!empty($_SESSION['checkout_error'])): ?>
        <div class="error-msg"><?php echo htmlspecialchars($_SESSION['checkout_error']); unset($_SESSION['checkout_error']); ?></div>
    <?php endif; ?>
    <div id="formError" class="error-msg" style="display:none;"></div>

    <form id="checkoutForm" method="POST" action="checkout_process.php">
        <div class="form-row">
            <div class="full">
                <label>First Name</label>
                <input type="text" name="first_name" value="<?php echo htmlspecialchars($_SESSION['first_name']); ?>" required>
            </div>
            <div class="full">
                <label>Last Name</label>
                <input type="text" name="last_name" value="<?php echo htmlspecialchars($_SESSION['last_name']); ?>" required>
            </div>
        </div>

        <div class="form-row">
            <div class="full">
                <label>Region</label>
                <select name="region" id="regionSelect" required>
                    <option value="">Loading regions...</option>
                </select>
            </div>
            <div class="full">
                <label>City / Municipality</label>
                <select name="city" id="citySelect" required>
                    <option value="">Select region first</option>
                </select>
            </div>
        </div>

        <label>Phone Number</label>
        <input type="tel" name="phone_number" id="phoneInput" placeholder="09XXXXXXXXX" maxlength="11" required>

        <label>Complete Address (max 35 characters, letters & numbers only)</label>
        <input type="text" name="address" id="addressInput" maxlength="35" placeholder="House No., Street, Barangay" required>
        <div class="char-count"><span id="addrCount">0</span>/35</div>

        <div class="voucher-box">
            <label style="margin-top:0;">Voucher Code</label>
            <div class="voucher-row">
                <input type="text" id="voucherInput" placeholder="VOUCHER CODE HERE" maxlength="30">
                <button type="button" class="voucher-apply-btn" onclick="applyVoucher()">Apply</button>
            </div>
            <div id="voucherFeedback" class="voucher-feedback"></div>
        </div>

        <div class="order-summary">
            <strong>Orders</strong>
            <?php foreach ($cart as $item): ?>
                <div class="row">
                    <span><?php echo htmlspecialchars($item['name']); ?> x<?php echo $item['qty']; ?></span>
                    <span>₱<?php echo number_format($item['price'] * $item['qty'], 2); ?></span>
                </div>
            <?php endforeach; ?>
            <div class="row" style="border-top:1px dashed var(--caramel);margin-top:6px;padding-top:6px;">
                <span>Subtotal</span>
                <span id="subtotalDisplay">₱<?php echo number_format($subtotal, 2); ?></span>
            </div>
            <div class="row" id="discountRow" style="display:none;color:var(--ok);">
                <span id="discountLabel">Voucher discount</span>
                <span id="discountDisplay">-₱0.00</span>
            </div>
            <div class="row" style="font-weight:800;font-size:15px;">
                <span>Total Payment</span>
                <span id="totalDisplay">₱<?php echo number_format($subtotal, 2); ?></span>
            </div>
        </div>

        <label>Payment Method</label>
        <input type="text" value="Cash on Delivery" disabled style="background:#f5f5f5;">
        <input type="hidden" name="payment_method" value="Cash on Delivery">

        <button type="submit" class="btn" style="margin-top:20px;">DONE</button>
    </form>
</div>

<script>
const SUBTOTAL = <?php echo json_encode($subtotal); ?>;

const addressInput = document.getElementById('addressInput');
const addrCount = document.getElementById('addrCount');
addressInput.addEventListener('input', () => {
    // Only letters, numbers, and spaces allowed — strip anything else
    addressInput.value = addressInput.value.replace(/[^a-zA-Z0-9 ]/g, '');
    addrCount.innerText = addressInput.value.length;
});

const phoneInput = document.getElementById('phoneInput');
phoneInput.addEventListener('input', () => {
    phoneInput.value = phoneInput.value.replace(/[^0-9]/g, '');
});

// ---- PH Regions / Cities via PSGC public API ----
const regionSelect = document.getElementById('regionSelect');
const citySelect = document.getElementById('citySelect');

fetch('https://psgc.gitlab.io/api/regions/')
    .then(res => res.json())
    .then(regions => {
        regionSelect.innerHTML = '<option value="">Select Region</option>' +
            regions.map(r => `<option value="${r.name}" data-code="${r.code}">${r.name}</option>`).join('');
    })
    .catch(() => {
        regionSelect.innerHTML = '<option value="">Could not load regions - type manually below</option>';
    });

regionSelect.addEventListener('change', () => {
    const opt = regionSelect.options[regionSelect.selectedIndex];
    const code = opt.getAttribute('data-code');
    citySelect.innerHTML = '<option value="">Loading cities...</option>';
    if (!code) return;

    fetch(`https://psgc.gitlab.io/api/regions/${code}/cities-municipalities/`)
        .then(res => res.json())
        .then(cities => {
            citySelect.innerHTML = '<option value="">Select City / Municipality</option>' +
                cities.map(c => `<option value="${c.name}">${c.name}</option>`).join('');
        })
        .catch(() => {
            citySelect.innerHTML = '<option value="">Could not load cities - type manually below</option>';
        });
});

// ---- Voucher apply ----
function applyVoucher() {
    const code = document.getElementById('voucherInput').value.trim();
    const feedback = document.getElementById('voucherFeedback');
    if (!code) {
        feedback.className = 'voucher-feedback error';
        feedback.innerText = 'Type a code first.';
        return;
    }
    feedback.className = 'voucher-feedback';
    feedback.innerText = 'Checking...';

    fetch('voucher_apply.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'code=' + encodeURIComponent(code)
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'ok') {
            feedback.className = 'voucher-feedback ok';
            feedback.innerText = data.message;
            updateTotals(data.discount_percent, data.code);
        } else {
            feedback.className = 'voucher-feedback error';
            feedback.innerText = data.message;
            updateTotals(0, null);
        }
    });
}

function updateTotals(discountPercent, code) {
    const discountRow = document.getElementById('discountRow');
    const discountLabel = document.getElementById('discountLabel');
    const discountDisplay = document.getElementById('discountDisplay');
    const totalDisplay = document.getElementById('totalDisplay');

    if (discountPercent > 0) {
        const discountAmount = SUBTOTAL * (discountPercent / 100);
        const newTotal = SUBTOTAL - discountAmount;
        discountRow.style.display = 'flex';
        discountLabel.innerText = 'Voucher (' + code + ') -' + discountPercent + '%';
        discountDisplay.innerText = '-₱' + discountAmount.toFixed(2);
        totalDisplay.innerText = '₱' + newTotal.toFixed(2);
    } else {
        discountRow.style.display = 'none';
        totalDisplay.innerText = '₱' + SUBTOTAL.toFixed(2);
    }
}

// ---- Validation before submit ----
document.getElementById('checkoutForm').addEventListener('submit', function (e) {
    const form = e.target;
    const required = form.querySelectorAll('[required]');
    let filled = true;
    required.forEach(f => { if (!f.value.trim()) filled = false; });

    if (phoneInput.value.length < 10) filled = false;

    if (!filled) {
        e.preventDefault();
        const err = document.getElementById('formError');
        err.style.display = 'block';
        err.innerText = 'Please fill up all fields before proceeding.';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
});
</script>
</body>
</html>
