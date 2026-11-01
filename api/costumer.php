<?php
// Order confirmation page - saves the order into phpMyAdmin (defaultdb.orders + history)
// and shows a professional bill (SN / Item / Price / Total) + a star-rating system.
include 'config.php';

// Show Nepali time on the bill, no matter where the server is hosted
date_default_timezone_set('Asia/Kathmandu');

/* ------------------------------------------------------------
   MENU PRICE LIST (Rs.)
   Keep these lines in sync with the dishes in costumer.html.
   When you add a new dish there, add its price here too.
   ------------------------------------------------------------ */
$MENU = array(
    // Suggested items
    'Margherita Pizza'   => 950,
    'Cheese Burger'      => 850,
    'Butter Chicken'     => 950,
    'Newari Khaja Set'   => 750,
    // Popular menu
    'Caesar Salad'       => 780,
    'Chocolate Cake'     => 550,
    'Ice Cream Sundae'   => 450,
    'French Fries'       => 300,
    'Chicken Tacos'      => 720,
    'Sushi Platter'      => 2200,
    'Grilled Steak'      => 2600,
    'Pepperoni Pizza'    => 1150,
    'Alfredo Pasta'      => 1050,
    // Nepali specials
    'Steamed Veg Momo'   => 250,
    'Jhol Momo'          => 350,
    'Fried Momo'         => 320,
    'Chicken Sekuwa'     => 650,
    'Dal Bhat Tarkari'   => 450,
    'Chicken Thali'      => 620,
    'Mutton Thali'       => 850,
    'Chatamari'          => 400,
    'Sel Roti'           => 120,
    'Nepali Chowmein'    => 320,
    'Aloo Tama'          => 350,
    'Gundruk ko Jhol'    => 250,
    // Indian favorites
    'Veg Samosa'         => 160,
    'Palak Paneer'       => 750,
    'Chole Bhature'      => 580,
    'Masala Dosa'        => 420,
    'Pav Bhaji'          => 550,
    'Tandoori Chicken'   => 950,
    'Butter Naan'        => 150,
    'Aloo Paratha'       => 220,
);

$con = db_orders();

/* ============================================================
   RATING ENDPOINT
   The stars at the bottom of this page send their rating here
   (AJAX). We save it into a "ratings" table (auto-created once)
   and answer with JSON - no HTML reload.
   ============================================================ */
if (($_POST['action'] ?? '') === 'rate') {
    header('Content-Type: application/json');

    $table  = trim($_POST['table']  ?? '');
    $items  = trim($_POST['items']  ?? '');
    $rating = (int)($_POST['rating'] ?? 0);

    if ($rating >= 1 && $rating <= 5) {
        // One-time table creation (safe to call on every request)
        $con->query("CREATE TABLE IF NOT EXISTS ratings (
                        ID INT AUTO_INCREMENT PRIMARY KEY,
                        `Table` VARCHAR(20) NOT NULL DEFAULT '',
                        Items   TEXT,
                        Rating  INT NOT NULL,
                        RatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                     )");

        $stmt = $con->prepare("INSERT INTO ratings (`Table`, Items, Rating) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $table, $items, $rating);
        $ok = $stmt->execute();

        echo json_encode(array('ok' => $ok));
    } else {
        echo json_encode(array('ok' => false));
    }

    $con->close();
    exit;
}

/* ============================================================
   SAVE THE ORDER (same logic as before) + build the bill
   ============================================================ */
$order_ok   = false;
$message    = '';
$order_id   = 0;
$table      = '';
$selected   = '';
$bill       = array();   // rows for the bill table
$total      = 0;
$has_unknown = false;    // true if a dish was not found in $MENU

if (isset($_POST['selected']) && isset($_POST['table'])) {
    $selected = trim($_POST['selected']);
    $table    = trim($_POST['table']);

    if ($selected !== '' && $table !== '') {
        // Prepared statements: safe against SQL injection
        $stmt = $con->prepare("INSERT INTO orders (`Table`, `Items`) VALUES (?, ?)");
        $stmt->bind_param("ss", $table, $selected);
        $ok = $stmt->execute();
        if ($ok) { $order_id = $stmt->insert_id; }   // real order number from the database

        // Save a copy into history
        $stmt2 = $con->prepare("INSERT INTO history (`Table`, `Items`) VALUES (?, ?)");
        $stmt2->bind_param("ss", $table, $selected);
        $ok = $ok && $stmt2->execute();

        $order_ok = $ok;
        $message  = $ok ? '' : "❌ Error: " . htmlspecialchars($con->error);

        // Turn "Pizza, Momo, Naan" into bill rows with prices
        $names = explode(',', $selected);
        foreach ($names as $name) {
            $name  = trim($name);
            if ($name === '') { continue; }

            $price = isset($MENU[$name]) ? $MENU[$name] : null;
            if ($price === null) { $has_unknown = true; }
            else                  { $total += $price; }

            $bill[] = array('sn' => count($bill) + 1, 'name' => $name, 'price' => $price);
        }
    } else {
        $message = "⚠️ No order data received.";
    }
} else {
    $message = "⚠️ No order data received.";
}

$con->close();

// Handy values for the page
$order_time = date('d M Y · h:i A');
$eta_time   = date('h:i A', strtotime('+20 minutes'));   // approx. when food should arrive

// Safe values to inject into the rating JavaScript
$js_table  = json_encode($table);
$js_items  = json_encode($selected);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Confirmed - Raman Restaurant</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root{
      --brand:#ff6347; --brand-dark:#e55336; --ink:#333; --line:#ffe0d6; --good:#1e9e46;
    }
    *{ margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif; }
    body{ background:#fefcf9; color:var(--ink); min-height:100vh; }

    /* ---------- Header band ---------- */
    .topbar{
      background:linear-gradient(120deg,var(--brand),var(--brand-dark)); color:#fff;
      text-align:center; padding:20px 16px; box-shadow:0 6px 20px rgba(255,99,71,.35);
    }
    .topbar h1{ font-size:21px; letter-spacing:.5px; }
    .topbar p{ font-size:12px; opacity:.9; margin-top:2px; }

    .wrap{ max-width:1040px; margin:0 auto; padding:22px 18px 46px; }

    /* ---------- Top section: confirmation (left) + bill (right) ---------- */
    .top-grid{
      display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-top:18px; align-items:stretch;
    }
    .top-grid > .card{ margin-top:0; }
    @media (max-width:860px){
      .top-grid{ grid-template-columns:1fr; }   /* stacks vertically on phones/tablets */
      .wrap{ max-width:640px; }
    }

    .card{
      background:#fff; border-radius:20px; padding:24px 22px;
      box-shadow:0 12px 30px rgba(255,99,71,.10); margin-top:18px;
    }

    /* ---------- Confirmation header ---------- */
    .confirm-head{ text-align:center; }
    .confirm-head .check{
      width:64px; height:64px; margin:0 auto 10px; border-radius:50%;
      background:#e6f7ec; color:var(--good); font-size:32px;
      display:grid; place-items:center; border:2px solid #bfe8cd;
    }
    .confirm-head h2{ color:var(--good); font-size:22px; }
    .confirm-head .sub{ font-size:13px; color:#9a8f8a; margin-top:3px; }

    .chips{ display:flex; flex-wrap:wrap; gap:8px; justify-content:center; margin-top:16px; }
    .chip{
      background:#fff5f0; border:1.5px solid var(--line); color:#8a5a4e;
      border-radius:999px; padding:7px 14px; font-size:12.5px; font-weight:600;
    }
    .chip b{ color:var(--brand-dark); }

    /* ---------- Patience / ETA banner ---------- */
    .patience{
      background:#fff7f2; border:1.5px dashed #ffb89f; border-radius:16px;
      padding:16px; display:flex; gap:14px; align-items:flex-start; margin-top:18px;
      font-size:13.5px; line-height:1.65; color:#5a4a42;
    }
    .patience b{ color:var(--brand-dark); }
    .spinner{
      flex:0 0 26px; width:26px; height:26px; margin-top:2px; border-radius:50%;
      border:3.5px solid #ffd9cc; border-top-color:var(--brand);
      animation:spin .9s linear infinite;
    }
    @keyframes spin{ to{ transform:rotate(360deg); } }

    /* ---------- Steps tracker ---------- */
    .steps{ display:flex; justify-content:space-between; margin:20px 10px 2px; position:relative; }
    .steps::before{
      content:''; position:absolute; top:17px; left:12%; right:12%; height:3px;
      background:#f0e2dc; border-radius:2px;
    }
    .step{ position:relative; z-index:1; flex:1; display:flex; flex-direction:column; align-items:center; gap:7px;
           font-size:11.5px; font-weight:600; color:#c3b1a9; text-align:center; }
    .step .dot{ width:36px; height:36px; border-radius:50%; background:#f3ece8;
                display:grid; place-items:center; font-size:16px; transition:.3s; }
    .step.done .dot{ background:var(--good); color:#fff; }
    .step.done{ color:var(--good); }
    .step.active .dot{ background:var(--brand); color:#fff; animation:pulse 1.3s ease-in-out infinite; }
    .step.active{ color:var(--brand-dark); }
    @keyframes pulse{ 0%,100%{ box-shadow:0 0 0 0 rgba(255,99,71,.45); } 50%{ box-shadow:0 0 0 10px rgba(255,99,71,0); } }
    .estimated{ text-align:center; margin-top:14px; font-size:12px; color:#a5968f; }
    .estimated b{ color:var(--brand-dark); }

    /* ---------- Bill ---------- */
    .bill-head{ display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap; }
    .bill-head h2{ color:var(--brand); font-size:19px; white-space:nowrap; }
    .receipt-meta{ font-size:11.5px; color:#a5968f; margin:0 0 14px; text-align:right; }
    .bill-head .receipt-meta{ margin:0; }
    table.bill{ width:100%; border-collapse:separate; border-spacing:0; font-size:14px; }
    table.bill thead th{
      background:var(--brand); color:#fff; padding:10px 12px; font-size:12px;
      text-transform:uppercase; letter-spacing:.6px; text-align:left;
    }
    table.bill thead th:first-child{ border-radius:12px 0 0 12px; width:52px; text-align:center; }
    table.bill thead th:last-child{ border-radius:0 12px 12px 0; text-align:right; }
    table.bill td{ padding:10px 12px; border-bottom:1px dashed var(--line); vertical-align:top; }
    table.bill tbody tr:nth-child(even) td{ background:#fffaf7; }
    td.sn{ text-align:center; color:#9a8f8a; font-weight:600; width:52px; }
    td.pr, th.pr{ text-align:right; font-weight:700; color:#3a2c28; white-space:nowrap; }
    tr.total td{ border-bottom:none; padding-top:14px; }
    tr.total .tlabel{ text-align:right; font-weight:800; font-size:15px; color:var(--ink); }
    tr.total .tval{ font-weight:800; font-size:16.5px; color:var(--brand-dark); }
    .bill-rows + .bill-note{ margin-top:6px; }
    .bill-note{ font-size:11.5px; color:#a5968f; }
    .thanks-line{
      text-align:center; margin-top:16px; padding-top:14px; border-top:1px dashed var(--line);
      font-size:12.5px; color:#8a6a60; font-weight:600;
    }

    /* ---------- Rating ---------- */
    .rating-card{ text-align:center; }
    .rating-card h2{ color:var(--brand); font-size:19px; }
    .rating-card .sub{ font-size:12.5px; color:#9a8f8a; margin-top:4px; }
    .stars{ display:flex; justify-content:center; gap:8px; margin-top:16px; user-select:none; }
    .star{ font-size:38px; color:#eee0da; cursor:pointer; transition:transform .15s, color .15s; line-height:1; }
    .star:hover{ transform:scale(1.25) rotate(-6deg); }
    .star.on{ color:#ffb400; text-shadow:0 2px 10px rgba(255,180,0,.45); }
    .rate-label{ font-size:13px; font-weight:600; color:#8a6a60; margin-top:10px; min-height:20px; }
    #rateBtn{
      margin-top:10px; width:100%; border:none; cursor:pointer;
      background:linear-gradient(120deg,var(--brand),var(--brand-dark)); color:#fff;
      padding:13px; border-radius:14px; font-size:15px; font-weight:700;
      box-shadow:0 8px 20px rgba(255,99,71,.35); transition:.25s;
    }
    #rateBtn:hover:not(:disabled){ transform:translateY(-2px); }
    #rateBtn:disabled{ opacity:.45; cursor:not-allowed; box-shadow:none; }
    #rateDone{ color:var(--good); font-weight:700; font-size:15px; padding:14px 0 4px; }

    /* ---------- Buttons / misc ---------- */
    .actions{ margin-top:20px; }
    .back{
      display:flex; align-items:center; justify-content:center;
      width:100%; max-width:420px; margin:0 auto;
      padding:13px 26px; border-radius:14px; text-decoration:none;
      background:#fff; border:1.5px solid var(--brand); color:var(--brand);
      font-weight:700; font-size:14.5px; transition:.25s;
    }
    .back:hover{ background:var(--brand); color:#fff; box-shadow:0 8px 20px rgba(255,99,71,.35); }

    .warn{ text-align:center; font-size:14.5px; line-height:1.7; color:#a2601f;
           background:#fff8ec; border:1.5px dashed #f2d49a; border-radius:16px; padding:18px; }
    footer{ text-align:center; font-size:11.5px; color:#b9a49c; margin-top:8px; }
    [hidden]{ display:none !important; }

    @media (max-width:480px){
      .card{ padding:20px 16px; }
      .star{ font-size:32px; }
      .step{ font-size:10.5px; }
      .chip{ font-size:11.5px; padding:6px 12px; }
    }
  </style>
</head>
<body>

<div class="topbar">
  <h1>🍽️ Raman Restaurant</h1>
  <p>Nepali &amp; Indian Kitchen · Thank you for dining with us</p>
</div>

<div class="wrap">

<?php if ($order_ok): ?>

  <!-- ---------- Row 1: Order confirmation (left) + Bill (right) ---------- -->
  <div class="top-grid">

    <!-- Confirmation -->
    <div class="card">
    <div class="confirm-head">
      <div class="check">✓</div>
      <h2>Order Confirmed!</h2>
      <div class="sub">Your order has been sent to the kitchen 👨‍🍳</div>
    </div>
    <div class="chips">
      <span class="chip">🧾 Order <b>#<?php echo $order_id; ?></b></span>
      <span class="chip">🪑 Table <b><?php echo htmlspecialchars($table); ?></b></span>
      <span class="chip"><?php echo count($bill); ?> item<?php echo count($bill) > 1 ? 's' : ''; ?></span>
      <span class="chip">🕒 <?php echo $order_time; ?></span>
    </div>

    <!-- Request for patience -->
    <div class="patience">
      <div class="spinner"></div>
      <div>
        <b>Thank you for your order! 🙏</b><br>
        Please be patient — your food is being <b>freshly prepared</b> and will be delivered
        to your table shortly.
        <b>Estimated delivery: 15–20 minutes</b> (around <b><?php echo $eta_time; ?></b>).
        Please keep your table number handy. 😊
      </div>
    </div>

    <!-- Simple progress tracker -->
    <div class="steps">
      <div class="step done"><div class="dot">✓</div><span>Order Received</span></div>
      <div class="step active"><div class="dot">👨‍🍳</div><span>Being Prepared</span></div>
      <div class="step"><div class="dot">🍽️</div><span>Served at Table</span></div>
    </div>
    <div class="estimated">Estimated ready time: <b><?php echo $eta_time; ?></b> · A waiter will bring the food to Table <b><?php echo htmlspecialchars($table); ?></b></div>
    </div>

    <!-- Bill -->
    <div class="card">
      <div class="bill-head">
        <h2>🧾 Your Bill</h2>
        <div class="receipt-meta">
          Order #<?php echo $order_id; ?> · Table <?php echo htmlspecialchars($table); ?><br>
          <?php echo $order_time; ?>
        </div>
      </div>

    <table class="bill">
      <thead>
        <tr>
          <th>SN</th>
          <th>Food Item</th>
          <th class="pr">Price (Rs.)</th>
        </tr>
      </thead>
      <tbody class="bill-rows">
        <?php foreach ($bill as $row): ?>
        <tr>
          <td class="sn"><?php echo $row['sn']; ?></td>
          <td><?php echo htmlspecialchars($row['name']); ?></td>
          <td class="pr"><?php echo $row['price'] === null ? '—' : number_format($row['price']); ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total">
          <td colspan="2" class="tlabel">Total Amount Payable</td>
          <td class="pr tval">Rs. <?php echo number_format($total); ?></td>
        </tr>
      </tbody>
    </table>

    <?php if ($has_unknown): ?>
      <div class="bill-note">* Items marked with “—” are not on the price list; the final price will be confirmed at the counter.</div>
    <?php endif; ?>

      <div class="thanks-line">🙏 Please pay at the counter or ask your waiter. Thank you &amp; visit again!</div>
    </div>

  </div><!-- /top-grid -->

  <!-- ---------- Row 2 (below): Rating ---------- -->
  <div class="card rating-card">
    <h2>⭐ Rate Your Experience</h2>
    <div class="sub">How was the food and service? Your feedback helps us improve.</div>

    <div id="rateArea">
      <div class="stars" id="stars">
        <span class="star" data-v="1">★</span>
        <span class="star" data-v="2">★</span>
        <span class="star" data-v="3">★</span>
        <span class="star" data-v="4">★</span>
        <span class="star" data-v="5">★</span>
      </div>
      <div class="rate-label" id="rateLabel">Tap a star to rate</div>
      <button id="rateBtn" disabled>Submit Rating</button>
    </div>

    <div id="rateDone" hidden>💚 Thank you for your feedback! We hope to serve you again soon.</div>
  </div>

  <div class="actions">
    <a class="back" href="/costumer.html?table=<?php echo urlencode($table); ?>">⬅ Back to Menu</a>
  </div>

<?php else: ?>

  <!-- ---------- Error / empty state ---------- -->
  <div class="card">
    <div class="warn">
      <?php echo $message !== '' ? $message : "🧺 No recent order found."; ?><br>
      <span style="font-size:12.5px;color:#9a8f8a;">
        If you have not ordered yet, please pick your dishes from the menu first.
      </span>
    </div>
    <div class="actions">
      <a class="back" href="/costumer.html">⬅ Back to Menu</a>
    </div>
  </div>

<?php endif; ?>

  <footer>© 2025 Raman Restaurant · QR Ordering System</footer>
</div>

<?php if ($order_ok): ?>
<script>
/* ================= Star rating widget =================
   Click a star -> Submit -> saved into the "ratings" table
   via this same page (AJAX, no reload).                  */
const TABLE = <?php echo $js_table; ?>;
const ITEMS = <?php echo $js_items; ?>;

const LABELS = { 1:'Poor 😞', 2:'Fair 😐', 3:'Good 🙂', 4:'Very Good 😍', 5:'Excellent 🤩' };

const stars     = document.querySelectorAll('#stars .star');
const rateLabel = document.getElementById('rateLabel');
const rateBtn   = document.getElementById('rateBtn');
const rateArea  = document.getElementById('rateArea');
const rateDone  = document.getElementById('rateDone');

let picked = 0;

function paint(n){
  stars.forEach(s => s.classList.toggle('on', +s.dataset.v <= n));
}

stars.forEach(star => {
  star.addEventListener('mouseenter', () => paint(+star.dataset.v));
  star.addEventListener('mouseleave', () => paint(picked));
  star.addEventListener('click', () => {
    picked = +star.dataset.v;
    paint(picked);
    rateLabel.textContent = LABELS[picked];
    rateBtn.disabled = false;
  });
});

rateBtn.addEventListener('click', async () => {
  if (!picked) return;
  rateBtn.disabled = true;
  rateBtn.textContent = 'Saving…';

  try {
    const body = new URLSearchParams();
    body.append('action', 'rate');
    body.append('table',  TABLE);
    body.append('items',  ITEMS);
    body.append('rating', picked);

    const res  = await fetch(location.pathname, { method:'POST', body: body });
    const data = await res.json();

    if (data.ok){
      rateArea.hidden   = true;
      rateDone.hidden   = false;
      rateDone.innerHTML = '💚 Thank you! You rated us ' + picked + '/5 · ' + LABELS[picked].replace(/[^a-zA-Z ]/g,'');
    } else {
      throw new Error('not saved');
    }
  } catch (e) {
    rateBtn.disabled = false;
    rateBtn.textContent = 'Submit Rating';
    rateLabel.textContent = '⚠️ Could not save. Please try again.';
  }
});
</script>
<?php endif; ?>

</body>
</html>
