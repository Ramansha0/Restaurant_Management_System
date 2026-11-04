<?php
/* ============================================================
   WAITER DASHBOARD - live orders screen
   ------------------------------------------------------------
   What is standard/professional about this version:
   - Safe PREPARED statements for the Serve / Pending actions
   - Stat cards at the top (Live / Pending / Needs attention)
   - Status badge + item chips on every order card
   - Smart sorting: new orders first, oldest waiting at top
   - Serve click asks for confirmation (it clears the order!)
   - AJAX live refresh every 5s -> NO page flashing anymore.
     (works without JavaScript too, via <noscript> fallback)
   ============================================================ */
include 'config.php';

/* ---------- status -> badge text / class ----------
   'Pending' = waiter pressed the Pending button
   ''        = fresh customer order (New), anything else keeps its text */
function status_info($status) {
    $s = strtolower(trim((string)$status));
    if ($s === 'pending') return array('⏳ Pending',  'badge-pending', 'pending');
    if ($s === 'serving') return array('🍽️ Serving', 'badge-serving', 'serving');
    if ($s === '')        return array('🔔 New',     'badge-new',     'new');
    return array('🔔 ' . ucfirst($s), 'badge-new', 'new');
}

/* ---------- Items text -> pretty chips ----------
   Same "(2x)" format used by costumer.php: "Jhol Momo, Butter Chicken(2x)" */
function item_chips_html($items_text) {
    $parts = explode(',', (string)$items_text);
    $out   = '';
    $count = 0;
    foreach ($parts as $one) {
        $one = trim($one);
        if ($one === '') { continue; }
        $qty = 0;
        if (preg_match('/^(.*?)\((\d+)x\)$/u', $one, $m)) {
            $one = trim($m[1]);
            $qty = max(1, (int)$m[2]);
        }
        $count++;
        $out .= '<span class="chip">' . htmlspecialchars($one)
              . ($qty > 1 ? ' <b>×' . $qty . '</b>' : '') . '</span>';
    }
    return $count ? $out : '<span class="chip muted">no item details</span>';
}

/* ---------- render all order cards (used by page AND by AJAX) ---------- */
function cards_html(array $rows) {
    if (!$rows) {
        return '<div class="empty"><div class="empty-ico">🎉</div>'
             . '<h3>All caught up!</h3>'
             . '<p>No live orders right now. New customer orders will appear here automatically.</p></div>';
    }
    $html = '';
    foreach ($rows as $row) {
        $badge = status_info($row['status'] ?? '');
        $html .= '<div class="order-card state-' . $badge[2] . '">';
        $html .=   '<div class="card-top">'
                 .   '<div class="order-id">Order #' . (int)$row['ID'] . '</div>'
                 .   '<span class="badge ' . $badge[1] . '">' . $badge[0] . '</span>'
                 . '</div>';
        $html .=   '<div class="order-table">🪑 Table <b>' . htmlspecialchars($row['Table']) . '</b></div>';
        $html .=   '<div class="chips">' . item_chips_html($row['Items'] ?? '') . '</div>';
        $html .=   '<form method="POST" class="card-actions">'
                 .   '<input type="hidden" name="ID" value="' . (int)$row['ID'] . '">'
                 .   '<button type="submit" name="serve"   class="btn">✔ Serve</button>'
                 .   '<button type="submit" name="pending" class="btn2">⏳ Pending</button>'
                 . '</form>';
        $html .= '</div>';
    }
    return $html;
}

/* ---------- one data load = rows + stats + html, reused everywhere ---------- */
function load_orders($con) {
    $rows = array();
    // `Table` in backticks (reserved word). Sort: fresh first, then Pending; oldest on top.
    $sql = "SELECT * FROM orders
            ORDER BY CASE WHEN LOWER(status) = 'pending' THEN 1 ELSE 0 END ASC, ID ASC";
    $result = $con->query($sql);
    if ($result) {
        while ($r = $result->fetch_assoc()) { $rows[] = $r; }
        $result->free();
    }

    $stats = array('total' => count($rows), 'pending' => 0, 'new' => 0);
    foreach ($rows as $r) {
        $s = strtolower(trim((string)($r['status'] ?? '')));
        if ($s === 'pending') { $stats['pending']++; } else { $stats['new']++; }
    }

    return array('ok' => (bool)$result, 'stats' => $stats, 'html' => cards_html($rows));
}

/* ============================================================
   DATABASE + ACTIONS
   ============================================================ */
$con = db_orders();

/* --- Serve / Pending (safe prepared statements) --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['ID']) ? (int)$_POST['ID'] : 0;

    if ($id > 0 && isset($_POST['serve'])) {
        $stmt = $con->prepare("DELETE FROM orders WHERE ID = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

    } elseif ($id > 0 && isset($_POST['pending'])) {
        $stmt = $con->prepare("UPDATE orders SET status = 'Pending' WHERE ID = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }

    // Post/Redirect/Get: browser forgets the form, no "resend?" popups on refresh
    $con->close();
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

/* --- AJAX endpoint: returns the fresh cards + stats as JSON --- */
if (($_GET['ajax'] ?? '') === 'view') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(load_orders($con), JSON_UNESCAPED_UNICODE);
    $con->close();
    exit;
}

/* --- normal page load --- */
$view = load_orders($con);
$con->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Waiter Dashboard · Raman Restaurant</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='88'%3E%F0%9F%8D%B4%3C/text%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<noscript><meta http-equiv="refresh" content="15"></noscript>
<style>
  :root{
    --brand:#ff6347; --brand-dark:#e55336; --brand-soft:#fff0eb;
    --good:#1e9e46; --good-soft:#e6f7ec; --good-line:#bfe8cd;
    --amber:#b45309; --amber-soft:#fdf1dd; --amber-line:#fcdca8;
    --red:#c0392b; --red-soft:#fdeaea;
    --ink:#3a2c28; --muted:#9a8f8a; --paper:#fff8f5; --line:#ffe0d6;
  }
  *{ margin:0; padding:0; box-sizing:border-box; font-family:'Poppins','Segoe UI',Tahoma,sans-serif; }
  body{ background:var(--paper); color:var(--ink); }

  /* ---------- Sticky header ---------- */
  header{
    position:sticky; top:0; z-index:600;
    background:linear-gradient(120deg,var(--brand),var(--brand-dark)); color:#fff;
    padding:13px 20px; display:flex; align-items:center; justify-content:space-between; gap:12px;
    box-shadow:0 6px 20px rgba(255,99,71,.35);
  }
  .brand{ display:flex; align-items:center; gap:12px; }
  .brand .logo{ width:42px; height:42px; background:rgba(255,255,255,.18); border-radius:12px;
                display:grid; place-items:center; font-size:22px; }
  .brand h1{ font-size:19px; font-weight:700; line-height:1.15; }
  .brand p{ font-size:11px; opacity:.85; }
  .head-right{ display:flex; align-items:center; gap:9px; }
  .live-pill{
    display:flex; align-items:center; gap:7px; background:rgba(255,255,255,.16);
    border:1px solid rgba(255,255,255,.35); padding:6px 12px; border-radius:999px;
    font-size:11.5px; font-weight:600; white-space:nowrap;
  }
  .live-pill .dot{ width:8px; height:8px; border-radius:50%; background:#6cf07f; animation:pulse 1.6s infinite; }
  @keyframes pulse{ 0%,100%{ box-shadow:0 0 0 0 rgba(108,240,127,.5); } 50%{ box-shadow:0 0 0 6px rgba(108,240,127,0); } }
  .live-pill.offline{ background:#b91c1c; }
  .live-pill.offline .dot{ background:#fca5a5; animation:none; }
  .icon-btn{
    background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.35); color:#fff;
    width:34px; height:34px; border-radius:10px; font-size:16px; cursor:pointer; transition:.2s;
  }
  .icon-btn:hover{ background:rgba(255,255,255,.3); }
  .hamburger{ display:none; font-size:22px; cursor:pointer; user-select:none; }

  /* ---------- Layout ---------- */
  .container{ display:grid; grid-template-columns:200px 1fr; min-height:calc(100vh - 74px); }

  .sidebar{ background:#fff0eb; border-right:2px solid var(--brand); padding:18px 10px; }
  .sidebar h2{ color:var(--brand); font-size:13px; margin-bottom:14px; letter-spacing:1.2px; text-transform:uppercase; }
  .sidebar ul{ list-style:none; }
  .sidebar li{ margin:8px 0; }
  .sidebar a{
    text-decoration:none; color:var(--ink); font-weight:500; font-size:14px;
    padding:9px 12px; display:flex; gap:9px; border-radius:8px; transition:.2s;
  }
  .sidebar a:hover{ background:var(--brand); color:#fff; }
  .sidebar a.active{ background:var(--brand); color:#fff; font-weight:600; box-shadow:0 4px 12px rgba(255,99,71,.35); }

  .main{ padding:22px; }

  /* ---------- Stats row ---------- */
  .section-title{ font-size:20px; font-weight:700; color:var(--brand); display:flex; align-items:baseline; gap:10px; flex-wrap:wrap; margin-bottom:14px; }
  .section-sub{ font-size:12px; color:var(--muted); font-weight:500; }

  .stats{ display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:20px; }
  .stat{
    background:#fff; border:1.5px solid var(--line); border-radius:14px; padding:14px;
    display:flex; align-items:center; gap:12px; box-shadow:0 6px 18px rgba(255,99,71,.08);
  }
  .s-ico{ width:42px; height:42px; border-radius:12px; display:grid; place-items:center; font-size:19px; }
  .stat.total   .s-ico{ background:var(--brand-soft); }  .stat.total   .s-num{ color:var(--brand); }
  .stat.pending .s-ico{ background:var(--amber-soft); }  .stat.pending .s-num{ color:#d97706; }
  .stat.new     .s-ico{ background:var(--red-soft); }    .stat.new     .s-num{ color:#e11d48; }
  .s-num{ font-size:25px; font-weight:800; line-height:1; }
  .s-label{ font-size:11.5px; color:var(--muted); font-weight:600; margin-top:3px; }
  .s-num.pop{ animation:pop .35s ease; }
  @keyframes pop{ 0%{ transform:scale(1); } 40%{ transform:scale(1.35); } 100%{ transform:scale(1); } }

  /* ---------- Order cards ---------- */
  .orders{ display:grid; gap:14px; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); }
  .order-card{
    background:#fff; border:1.5px solid #f4e3dc; border-left-width:5px; border-radius:14px;
    padding:14px 14px 12px; display:flex; flex-direction:column; gap:10px;
    box-shadow:0 8px 22px rgba(63,42,32,.08);
    animation:fadeUp .4s ease both; transition:transform .2s, box-shadow .2s;
  }
  @keyframes fadeUp{ from{ opacity:0; transform:translateY(6px); } to{ opacity:1; transform:none; } }
  .order-card:hover{ transform:translateY(-3px); box-shadow:0 12px 26px rgba(63,42,32,.14); }
  .order-card.state-new{     border-left-color:var(--brand); }
  .order-card.state-pending{ border-left-color:#d97706; background:#fffdf6; }
  .order-card.state-serving{ border-left-color:var(--good); background:#fbfffc; }

  .card-top{ display:flex; align-items:center; justify-content:space-between; gap:8px; }
  .order-id{ font-size:14.5px; font-weight:700; color:var(--brand); }
  .badge{ font-size:11px; font-weight:700; padding:4px 10px; border-radius:999px; white-space:nowrap; }
  .badge-new{     background:var(--red-soft);    color:var(--red); }
  .badge-pending{ background:var(--amber-soft);  color:var(--amber); }
  .badge-serving{ background:var(--good-soft);   color:var(--good); }

  .order-table{ font-size:15.5px; font-weight:600; }
  .order-table b{ font-size:17px; color:var(--brand-dark); }

  .chips{ display:flex; flex-wrap:wrap; gap:6px; }
  .chip{
    background:#fff7f3; border:1.5px solid var(--line); color:#8a5a4e;
    border-radius:999px; padding:5px 11px; font-size:12px; font-weight:600;
  }
  .chip b{ color:var(--brand-dark); }
  .chip.muted{ opacity:.7; font-style:italic; font-weight:500; }

  .card-actions{ display:flex; gap:8px; margin-top:auto; padding-top:6px; }
  .btn{
    flex:1; padding:9px 0; border:none; border-radius:10px; cursor:pointer;
    background:linear-gradient(120deg,var(--brand),var(--brand-dark)); color:#fff;
    font-weight:700; font-size:13.5px; transition:.2s; box-shadow:0 4px 10px rgba(255,99,71,.3);
  }
  .btn:hover{ transform:translateY(-2px); box-shadow:0 6px 14px rgba(255,99,71,.4); }
  .btn2{
    flex:1; padding:9px 0; border-radius:10px; cursor:pointer; transition:.2s;
    background:var(--good-soft); border:1.5px solid var(--good-line); color:var(--good);
    font-weight:700; font-size:13.5px;
  }
  .btn2:hover{ background:var(--good); color:#fff; }
  button:disabled{ opacity:.6; cursor:wait; }

  /* ---------- Friendly empty state ---------- */
  .empty{
    grid-column:1/-1; text-align:center; padding:46px 20px;
    background:#fff; border:2px dashed var(--line); border-radius:18px;
  }
  .empty-ico{ font-size:44px; }
  .empty h3{ color:var(--good); margin-top:8px; font-size:19px; }
  .empty p{ color:var(--muted); font-size:13px; margin-top:5px; }

  footer{ text-align:center; padding:14px; background:var(--brand); color:#fff; font-weight:600; font-size:13px; }

  /* ---------- Responsive: tablets ---------- */
  @media (max-width:1024px){
    .container{ grid-template-columns:170px 1fr; }
  }

  /* ---------- Responsive: mobiles ---------- */
  .overlay{ display:none; }
  @media (max-width:600px){
    .container{ grid-template-columns:1fr; }
    .main{ padding:16px 12px; }
    .brand h1{ font-size:15px; } .brand p{ font-size:9.5px; }
    .live-pill{ font-size:10.5px; padding:5px 9px; }
    .hamburger{ display:block; }
    .stat{ padding:11px; gap:9px; }
    .s-ico{ width:34px; height:34px; font-size:16px; }
    .s-num{ font-size:20px; }
    .s-label{ font-size:10px; }
    .sidebar{
      position:fixed; top:0; left:-102%; width:74%; max-width:250px; height:100%;
      z-index:700; transition:left .3s ease; box-shadow:8px 0 24px rgba(0,0,0,.15);
    }
    .sidebar.active{ left:0; }
    .overlay.show{ display:block; position:fixed; inset:0; background:rgba(0,0,0,.35); z-index:650; }
  }
</style>
</head>
<body>

<header>
  <div class="brand">
    <span class="logo">🍴</span>
    <div>
      <h1>Waiter Dashboard</h1>
      <p>Raman Restaurant · Live Kitchen Screen</p>
    </div>
  </div>
  <div class="head-right">
    <span class="live-pill" id="livePill"><span class="dot"></span><span id="liveNote">Live</span></span>
    <button class="icon-btn" id="refreshBtn" title="Refresh now">↻</button>
    <span class="hamburger" id="hamburger">☰</span>
  </div>
</header>

<div class="container">
  <aside class="sidebar">
    <h2>Navigation</h2>
    <ul>
      <li><a class="active" href="/api/waiter.php">📋 Orders</a></li>
      <li><a href="/api/history.php">⚙️ History</a></li>
    </ul>
  </aside>

  <main class="main">
    <div class="section-title">Live Orders <span class="section-sub">fresh orders appear automatically · oldest waiting on top</span></div>

    <div class="stats">
      <div class="stat total">
        <span class="s-ico">🍽️</span>
        <div><div class="s-num" id="stTotal"><?php echo (int)$view['stats']['total']; ?></div><div class="s-label">Live Orders</div></div>
      </div>
      <div class="stat pending">
        <span class="s-ico">⏳</span>
        <div><div class="s-num" id="stPending"><?php echo (int)$view['stats']['pending']; ?></div><div class="s-label">Pending</div></div>
      </div>
      <div class="stat new">
        <span class="s-ico">🔔</span>
        <div><div class="s-num" id="stNew"><?php echo (int)$view['stats']['new']; ?></div><div class="s-label">Needs Attention</div></div>
      </div>
    </div>

    <div class="orders" id="orderGrid"><?php echo $view['html']; ?></div>
  </main>
</div>

<div class="overlay" id="overlay"></div>

<footer>© 2025 Raman Restaurant — Waiter Interface</footer>

<script>
/* ================= Live dashboard (AJAX, no page flashing) ================= */
const grid      = document.getElementById('orderGrid');
const note      = document.getElementById('liveNote');
const pill      = document.getElementById('livePill');
const stTotal   = document.getElementById('stTotal');
const stPending = document.getElementById('stPending');
const stNew     = document.getElementById('stNew');

let lastSync = Date.now();

function refreshNote() {
  if (pill.classList.contains('offline')) return;
  const s = Math.round((Date.now() - lastSync) / 1000);
  note.textContent = s <= 0 ? 'Updated just now' : 'Updated ' + s + 's ago';
}

function popStat(el, value) {
  if (!el || el.textContent === String(value)) return;
  el.textContent = value;
  el.classList.remove('pop');
  void el.offsetWidth;            /* restart the pop animation */
  el.classList.add('pop');
}

/* The same PHP page answers JSON when we ask ?ajax=view */
async function poll() {
  try {
    const res  = await fetch(location.pathname + '?ajax=view', { cache: 'no-store' });
    const data = await res.json();
    if (data.ok) {
      grid.innerHTML = data.html;
      popStat(stTotal,   data.stats.total);
      popStat(stPending, data.stats.pending);
      popStat(stNew,     data.stats.new);
      pill.classList.remove('offline');
      lastSync = Date.now();
      refreshNote();
    }
  } catch (e) {
    pill.classList.add('offline');
    note.textContent = 'Reconnecting…';
  }
}

setInterval(() => { if (!document.hidden) poll(); }, 5000);   /* pause when tab hidden */
setInterval(refreshNote, 1000);
document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
document.getElementById('refreshBtn').addEventListener('click', poll);

/* ================= Confirm before destructive actions =================
   Delegated on <document> so it still works after AJAX card swaps.      */
document.addEventListener('submit', (e) => {
  const form = e.target;
  if (form.dataset.busy) { e.preventDefault(); return; }   /* double-click guard */

  const action = e.submitter ? e.submitter.name : '';
  let msg = '';
  if (action === 'serve')   msg = 'Mark this order as SERVED?\nIt will be cleared from the live list.';
  if (action === 'pending') msg = 'Move this order back to ⏳ Pending?';
  if (msg && !window.confirm(msg)) { e.preventDefault(); return; }

  form.dataset.busy = '1';
  setTimeout(() => { delete form.dataset.busy; }, 4000);
});

/* ================= Sidebar (mobile) ================= */
const hamburger = document.getElementById('hamburger');
const sidebar   = document.querySelector('.sidebar');
const overlay   = document.getElementById('overlay');

function setMenu(open) {
  sidebar.classList.toggle('active', open);
  overlay.classList.toggle('show', open);
  hamburger.textContent = open ? '✕' : '☰';
}
hamburger.addEventListener('click', () => setMenu(!sidebar.classList.contains('active')));
overlay.addEventListener('click', () => setMenu(false));
</script>

</body>
</html>
