<?php
require_once __DIR__ . "/../includes/functions.php";
require_admin();

$products = $conn->query("SELECT * FROM products ORDER BY category, id");
$pendingOrders = $conn->query("SELECT COUNT(*) as c FROM orders WHERE status = 'Pending'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin - ICCAT CATFÉ</title>
<link rel="stylesheet" href="../assets/css/style.css?v=6">
</head>
<body>
<div class="topbar">
    <div class="center-title">🔐 CATFÉ Admin Panel</div>
    <div style="margin-left:auto;">
        <a href="orders.php" style="margin-right:16px;font-size:13px;color:var(--milk);">📦 Orders</a>
        <a href="vouchers.php" style="margin-right:16px;font-size:13px;color:var(--milk);">🎟️ Vouchers</a>
        <a href="../dashboard.php" style="margin-right:16px;font-size:13px;color:var(--milk);">← Back to site</a>
        <a href="admin_logout.php" style="font-size:13px;color:var(--milk);">Log out</a>
    </div>
</div>

<p style="margin:20px;color:var(--brown);">Pending orders waiting to be processed: <strong><?php echo $pendingOrders; ?></strong> (full order tracking lives in your Google Sheet)</p>

<table class="admin-table">
    <thead>
        <tr>
            <th>Image</th><th>Name</th><th>Category</th><th>Price (₱)</th><th>Stock</th><th></th>
        </tr>
    </thead>
    <tbody>
        <?php while ($p = $products->fetch_assoc()): ?>
        <tr id="row-<?php echo $p['id']; ?>">
            <td><img src="../<?php echo htmlspecialchars($p['image']); ?>" style="width:50px;height:50px;object-fit:cover;border-radius:8px;"></td>
            <td><?php echo htmlspecialchars($p['name']); ?></td>
            <td><?php echo htmlspecialchars($p['category']); ?></td>
            <td><input type="number" step="0.01" min="0" id="price-<?php echo $p['id']; ?>" value="<?php echo $p['price']; ?>"></td>
            <td><input type="number" min="0" id="stock-<?php echo $p['id']; ?>" value="<?php echo $p['stock']; ?>"></td>
            <td><button class="save-row-btn" onclick="saveProduct(<?php echo $p['id']; ?>)">Save</button></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<p style="margin:20px;font-size:13px;color:#999;">
    To change a product image: replace the file in <code>assets/images/</code> keeping the same filename,
    or edit the <code>image</code> column for that product directly in phpMyAdmin.
</p>

<script>
function saveProduct(id) {
    const price = document.getElementById('price-' + id).value;
    const stock = document.getElementById('stock-' + id).value;
    fetch('update_stock.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}&price=${price}&stock=${stock}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'ok') {
            alert('Updated!');
        } else {
            alert('Something went wrong.');
        }
    });
}
</script>
</body>
</html>
