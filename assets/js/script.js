function toggleMenu() {
    document.getElementById('sideMenu').classList.toggle('open');
    document.getElementById('overlay').classList.toggle('show');
}

function toggleCart() {
    document.getElementById('cartDrawer').classList.toggle('open');
    refreshCart();
}

// ----- Variant picker (flavor for pastries, Iced/Hot for drinks) -----
let currentVariantProductId = null;

function openVariantModal(productId) {
    const p = window.PRODUCT_VARIANTS && window.PRODUCT_VARIANTS[productId];
    if (!p) return;
    currentVariantProductId = productId;

    document.getElementById('variantModalTitle').innerText = p.label;
    document.getElementById('variantModalItemName').innerText = p.name;

    const optsHtml = p.variants.map((v, i) => `
        <label class="variant-option">
            <input type="radio" name="variantChoice" value="${v}" ${i === 0 ? 'checked' : ''}>
            <span>${v}</span>
        </label>
    `).join('');
    document.getElementById('variantOptions').innerHTML = optsHtml;
    document.getElementById('variantError').innerText = '';
    document.getElementById('variantModal').classList.add('show');
}

function closeVariantModal() {
    document.getElementById('variantModal').classList.remove('show');
}

function confirmAddToCart() {
    const selected = document.querySelector('input[name="variantChoice"]:checked');
    if (!selected) {
        document.getElementById('variantError').innerText = 'Please choose one option.';
        return;
    }
    addToCart(currentVariantProductId, selected.value);
    closeVariantModal();
}

function addToCart(productId, variant) {
    fetch('cart_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=add&product_id=' + productId + '&variant=' + encodeURIComponent(variant)
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'error') {
            alert(data.message);
            return;
        }
        renderCart(data);
        document.getElementById('cartDrawer').classList.add('open');
    });
}

function changeQty(cartKey, dir) {
    fetch('cart_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=' + (dir === 1 ? 'increase' : 'decrease') + '&cart_key=' + encodeURIComponent(cartKey)
    })
    .then(res => res.json())
    .then(data => renderCart(data));
}

function refreshCart() {
    fetch('cart_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=noop'
    })
    .then(res => res.json())
    .then(data => renderCart(data));
}

function renderCart(data) {
    const list = document.getElementById('cartItemsList');
    const totalEl = document.getElementById('cartTotal');
    const badge = document.getElementById('cartBadge');

    if (!data.cart || data.cart.length === 0) {
        list.innerHTML = '<p style="text-align:center;color:#999;margin-top:30px;">Your cart is empty.</p>';
    } else {
        list.innerHTML = data.cart.map(item => `
            <div class="cart-item">
                <div>
                    <div class="name">${item.name}</div>
                    <div style="font-size:12px;color:#888;">₱${item.price.toFixed(2)} each</div>
                </div>
                <div class="qty-controls">
                    <button onclick="changeQty('${item.cart_key}', -1)">-</button>
                    <span>${item.qty}</span>
                    <button onclick="changeQty('${item.cart_key}', 1)">+</button>
                </div>
            </div>
        `).join('');
    }
    totalEl.innerText = '₱' + data.total.toFixed(2);
    badge.innerText = data.count;
}

function goCheckout() {
    fetch('cart_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=noop'
    })
    .then(res => res.json())
    .then(data => {
        if (!data.cart || data.cart.length === 0) {
            alert('Your cart is empty. Add something tasty first!');
            return;
        }
        window.location.href = 'checkout.php';
    });
}

// ----- Secret admin modal -----
function openAdminModal() {
    document.getElementById('adminModal').classList.add('show');
    document.getElementById('adminPassInput').value = '';
    document.getElementById('adminError').innerText = '';
}

function submitAdminPass() {
    const val = document.getElementById('adminPassInput').value;
    fetch('admin/admin_login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'password=' + encodeURIComponent(val)
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'ok') {
            window.location.href = 'admin/admin_dashboard.php';
        } else {
            document.getElementById('adminError').innerText = 'Incorrect password.';
        }
    });
}

// Load cart badge/count on first page load (only on pages that actually have a cart UI)
document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('cartBadge')) refreshCart();
});

// ----- Show/hide password -----
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerText = 'Hide';
    } else {
        input.type = 'password';
        btn.innerText = 'Show';
    }
}
