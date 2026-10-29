# ICCAT CATFÉ — Online Bakery & Café Ordering System

> **Render / PostgreSQL version:** this copy is converted for Render hosting. See `RENDER_DEPLOY.md`. The original XAMPP/MySQL SQL files are kept in `database/mysql_legacy/`.


A brown & white cat-themed bakery/café ordering site built with **PHP + MySQL (XAMPP)**,
plain HTML/CSS/JS, voucher codes, and a Google Sheets order log.

## What's included

```
catfe/
├── index.php              → 5-second loading screen, then routes to login/dashboard
├── login.php               → split-screen login (photo left, form right), show/hide password
├── signup.php               → split-screen signup, same layout
├── logout.php
├── dashboard.php             → hamburger menu, tagline banner, products with flavor/Iced-Hot
│                                picker, cart, About Us, Join Community
├── cart_action.php           → AJAX add/update/remove cart items, keyed by product+variant
├── checkout.php               → shipping form (PH region/city, phone, 35-char address),
│                                 voucher code field, COD
├── checkout_process.php       → validates + saves order (incl. chosen variant), applies
│                                 voucher, pushes to Sheet
├── voucher_apply.php           → AJAX: checks a code, applies it if valid & unused
├── voucher_remove.php           → AJAX: clears the applied voucher from session
├── orders.php                  → view orders, live status, chosen flavor/Iced-Hot, cancel
├── cancel_order.php             → cancels order, restocks items, releases the voucher
├── admin/
│   ├── admin_login.php          → secret password check ("catfebrigida")
│   ├── admin_dashboard.php       → edit price & stock per product
│   ├── update_stock.php
│   ├── orders.php                 → change any order's delivery status
│   ├── vouchers.php               → create / edit / delete / reset voucher codes
│   └── admin_logout.php
├── includes/
│   ├── db.php                    → MySQL connection (XAMPP defaults)
│   └── functions.php              → session helpers, Google Sheet webhook, order helpers
├── assets/
│   ├── css/style.css               → brown & white cat café theme
│   ├── js/script.js                 → menu, cart, variant modal, admin modal, password toggle
│   └── images/                       → logo, auth hero photo, 10 pastry + 10 drink images
├── submit_review.php           → AJAX: saves a 1–5 star rating + feedback for a delivered order
├── database/
│   ├── catfe.sql                     → fresh install: run this in phpMyAdmin
│   ├── migration_v2.sql               → old installs: adds vouchers, drops Google column
│   ├── migration_v3.sql                → old installs: adds the new menu + flavor/Iced-Hot
│   │                                      variant system (run after migration_v2)
│   ├── migration_v4.sql                → old installs: adds new-customer welcome vouchers
│   │                                      and order status notifications (run after v3)
│   └── migration_v5.sql                → old installs: adds the reviews table (run after v4)
└── google_sheets/apps_script.gs       → paste into Google Apps Script (see below)
```

## 1. XAMPP setup

1. Copy the whole `catfe` folder into `C:\xampp\htdocs\` (or `/Applications/XAMPP/htdocs/` on Mac).
2. Start **Apache** and **MySQL** in the XAMPP control panel.
3. Open `http://localhost/phpmyadmin`:
   - **Fresh install:** click **Import** and import `database/catfe.sql`. This creates
     the `catfe_db` database, all tables (products, flavor/temperature variants,
     vouchers), and seeds the full 10-pastry / 10-drink menu below.
   - **Upgrading from an earlier version?** Don't re-import `catfe.sql` (it would wipe
     your data). Instead run, in order:
     1. `database/migration_v2.sql` (adds vouchers, drops the old Google column) — skip
        if you already ran this before.
     2. `database/migration_v3.sql` (adds the flavor/Iced-Hot variant system and
        replaces the product menu with the new one below — this part does overwrite
        your product list, but keeps users, orders, and vouchers intact).
     3. `database/migration_v4.sql` (adds new-customer welcome vouchers and order
        status notifications).
     4. `database/migration_v5.sql` (adds the reviews table).
4. Visit `http://localhost/catfe/` in your browser. You should see the loading screen,
   then land on the login page.

If your MySQL root user has a password, edit `includes/db.php` and set `$DB_PASS`.

## 2. The menu & flavor/temperature picker

**Pastries** (each with 3 flavor choices, picked before adding to cart):

| Item | Price | Flavors |
|---|---|---|
| Croissant | ₱60/pc | Almond Cream, Chocolate, Strawberry Cream |
| Muffins | ₱35/pc | Blueberry Crumble, Double Chocolate, Banana Walnut |
| Danish | ₱105/pc | Apple Cinnamon, Blueberry Cheese, Strawberry Custard |
| Cinnamon Roll | ₱45/pc | Classic Vanilla, Strawberry Cream, Caramel Pecan |
| Pain au Suisse | ₱170/pc | Chocolate Custard, Pistachio Cream, Raspberry Cream |
| Cookies | ₱190/box | Chocolate Chip, Matcha White Chocolate, Biscoff |
| Brownies | ₱160/box | Fudge Walnut, Salted Caramel, Dark Chocolate |
| Tarts | ₱30/pc | Lemon Curd, Strawberry Custard, Chocolate Ganache |
| Pies | ₱60/pc | Apple Cinnamon, Blueberry Crumble, Chocolate Cream |
| Cakes | ₱100/pc | Strawberry Shortcake, Chocolate Fudge, Vanilla Berry |

**Drinks** (every drink offers Iced or Hot), split into Coffee and Non-Coffee sections:

Coffee — Americano ₱90, Macchiato ₱110, Mocha ₱120, Cappuccino ₱110, Café Au Lait ₱120, Latte ₱110
Non-Coffee — Chocolate ₱100, Lemon Tea ₱80, Peach Tea ₱105, Jasmine Milk Tea ₱115

Clicking **Add to Cart** on any item opens a picker modal — pastries ask "Choose a
Flavor" (3 options), drinks ask "Iced or Hot?" (2 options). The chosen option is
required, gets appended to the item name in the cart (e.g. *"Croissant (Almond
Cream)"*), and is saved on the order (`order_items.variant_name`) so it shows up on
the receipt in **View Orders** and in the Google Sheet.

Stock is tracked per product (not per flavor) — so 20 in stock for Croissant means 20
total across all three flavors combined. Flavors/temperatures themselves are managed in
the `product_variants` table via phpMyAdmin if you ever want to add or rename one; the
admin panel's price/stock editor doesn't touch them.

## 3. Vouchers — how they work

- **Admin side:** log in through the secret `•` at the bottom of the dashboard
  (password `catfebrigida`), then click **🎟️ Vouchers** at the top. From there you can:
  - Create a new code with a discount percentage (1–100%)
  - Edit a code's discount percentage
  - Toggle a code active/inactive without deleting it
  - See at a glance whether a code is **Available** or **Used**, and who redeemed it
  - **Reset** a used code back to unused, or **Delete** it permanently
- **Customer side:** on the checkout page, right under the address field, there's a
  **Voucher Code** box. Typing a code and clicking **Apply** calls `voucher_apply.php`,
  which checks the code and returns one of:
  - ✅ *Applied* — the order total updates immediately (no page reload)
  - ❌ *"Not working — that voucher code doesn't exist"*
  - ❌ *"Already redeemed by another customer"*
  - ❌ *"This voucher is no longer active"*
- **Redemption rule:** every code is **single-use across the whole shop** — the moment
  any customer successfully checks out with it, it flips to "Used" and nobody else can
  apply it (this is re-verified server-side again at the exact moment of checkout, in
  case two people tried the same code at once).
- **Cancellations:** if a customer cancels an order that had a voucher applied, that
  voucher is automatically released back to "Available" so it isn't wasted.

Sample codes seeded by `catfe.sql`: `MEOW10` (10% off), `PAWSOME20` (20% off),
`WELCOME5` (5% off), `NEWCAT20` (20% off, new customers only).

### New-customer welcome codes

These work differently from regular codes. Check **"Make this a new customers only
welcome code"** when creating one in the admin panel, and:

- Any customer with **zero prior orders** can redeem it.
- Each customer can redeem it **once** — but unlike regular codes, the code itself
  isn't consumed, so every new customer gets their own shot at it. Think of it as a
  permanent "20% off your first order" offer rather than a one-time coupon.
- A banner automatically appears at the top of the dashboard for any customer who
  hasn't placed an order yet, showing the first active welcome code and its discount
  (e.g. *"🎉 New here? Use code NEWCAT20 for 20% off your first order!"*). If you don't
  want this banner, just deactivate or delete the welcome-type voucher(s).
- In the admin voucher table, **Reset** on a welcome code clears every customer's
  redemption history for it (mainly useful for testing).

## 4. Order status notifications

- Every order tracks the last status the customer actually saw (`last_seen_status`).
  When staff update an order's status — either directly in the admin panel (below) or
  via the Google Sheet — the next time the customer opens **View Orders**, any order
  whose status changed shows a pulsing **🔔 notification banner** at the top of its
  card — "Your order is being prepared!", "Your order is out for delivery!", "Your
  order has arrived — enjoy!" — and it's automatically marked as seen once viewed.
- A **🔔 bell icon** next to the cart on the dashboard shows a badge with the count of
  orders with unseen updates, linking straight to **View Orders**.
- When an order's status is **Delivered**, its card also shows a permanent
  **"🐾 Go to Discord to review your satisfaction"** button linking to your Discord
  invite (`DISCORD_INVITE_LINK` in `includes/functions.php`) — this stays visible every
  time they view a delivered order, not just on the first notification.

## 5. Admin: managing order delivery status

Log in through the secret `•` (password `catfebrigida`), then click **📦 Orders** at
the top. From there staff can:

- Filter orders by status (All / Pending / Processing / Out for Delivery / Delivered /
  Cancelled)
- See each order's customer, items, and total at a glance
- Change any order's status with a dropdown + **Update** button

Setting a status to **Cancelled** here automatically restocks the items and releases
any voucher that was used on that order — the same as when a customer cancels their
own order. Every update also pushes to your Google Sheet (if configured) and clears
the customer's "seen" flag, so they get the 🔔 notification described above.

## 6. Reviews & feedback

Once an order is marked **Delivered**, its card in **View Orders** shows (right below
the Discord button) a simple review widget: tap 1–5 stars, optionally add a comment,
and hit **Submit Review**. Each order can only be reviewed once (resubmitting just
updates the existing review instead of creating a duplicate).

Every submitted review is shown publicly in a **Reviews & Feedback** section on the
dashboard, right below **Join Our Community** — star rating, comment, and the
reviewer's first name + last initial (e.g. "Maria S."), newest first, capped at the 20
most recent. There's also a shortcut to it in the hamburger menu.

## 7. Google Sheets order sync

Every order should also land in a Google Sheet, and staff can update delivery status
directly in the Sheet, which then reflects back on the website.

1. Create a new Google Sheet. Rename the first tab to **Orders**.
2. In row 1, add headers across columns A–K:
   `order_id | email | first_name | last_name | city | region | phone_number | address | orders | total_payment | status`
3. Open **Extensions → Apps Script**, delete the placeholder code, and paste in the
   contents of `google_sheets/apps_script.gs`.
4. Click **Deploy → New deployment → Web app**.
   - Execute as: **Me**
   - Who has access: **Anyone**
5. Copy the deployment URL and paste it into `includes/functions.php`:
   ```php
   define("GOOGLE_SHEET_WEBHOOK_URL", "https://script.google.com/macros/s/XXXXXXXX/exec");
   ```

From then on:
- New checkouts append a row to the Sheet automatically.
- Editing the **status** column in the Sheet (Pending / Processing / Out for Delivery /
  Delivered / Cancelled) updates automatically on the website the next time the
  customer opens **View Orders**.
- Cancelling an order on the website also updates the Sheet's status column.

If you skip this setup, the site still works fully on local MySQL — orders just won't
mirror to a Sheet until the URL is filled in.

## 8. Secret admin access

At the very bottom of the dashboard there's a small `•` — clicking it opens a password
prompt. The password is **`catfebrigida`** (set in `admin/admin_login.php`, change it
there if you'd like). Once in, staff can edit price/stock, manage orders, and manage
voucher codes.

## 9. Customizing

- **Product images & descriptions**: replace files in `assets/images/` (keep the same
  filenames), and edit the `description` column per product in phpMyAdmin — it now
  shows right under the stock count on each card.
- **About Us team**: edit the `$team` array near the top of `dashboard.php`
  (name, student number, quote, and image path — three members as required).
- **Discord invite**: edit `$discord_link` in `dashboard.php`.
- **Logo**: replace `assets/images/logo.jpg`.
- **Login/signup photo**: replace `assets/images/auth-hero.jpg` (the image on the left
  side of the login and signup pages) with a real photo of your café for a nicer first
  impression — it's a generated placeholder right now.
- **Footer tagline / about blurb**: edit the `<footer class="site-footer">` block at
  the bottom of `dashboard.php`.

## Notes

- **Google Sign-In has been removed** — the site is email/password only now. Both the
  login and signup forms have a **Show/Hide** toggle on password fields.
- PH region/city dropdowns on checkout use the free public **PSGC API**
  (`psgc.gitlab.io/api`) — no key required, works from the browser.
- Address field enforces the 35-character, letters/numbers/spaces-only rule both in
  JS (as you type) and again in PHP on submit.
- Checkout will not proceed unless every field is filled — validated on both the
  browser and server side.
- Stock decrements on checkout and restocks automatically if an order is cancelled.
