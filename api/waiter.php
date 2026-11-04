<?php
/* ============================================================
   WAITER DASHBOARD - live orders screen (v4)
   ------------------------------------------------------------
   - Orange & white theme, clean professional layout
   - Safe PREPARED statement for the Serve action
   - Stat cards: Live orders / Tables waiting / Items to deliver
   - Smart sorting: oldest waiting order on top
   - Serve asks for confirmation (it clears the order!)
   - AJAX refresh every 5s using a DOM DIFF:
       existing cards update in place (no flashing), only
       genuinely new cards animate, served cards fade out.
     Works without JavaScript too (<noscript> refresh).
   ============================================================ */
include 'config.php';

/* ---------- status -> label / badge class / state ----------
   'Pending' rows may still exist from older data; they display
   as an amber badge but there is no Pending button anymore.   */
function status_info($status) {
    $s = strtolower(trim((string)$status));
    if ($s === 'pending') return array('Pending', 'badge-pending', 'pending');
    if ($s === 'serving') return array('Serving', 'badge-serving', 'serving');
    if ($s === '')        return array('New',     'badge-new',     'new');
    return array(ucfirst($s), 'badge-new', 'new');
}

/* ---------- Items text -> chips + qty sum (costumer.php "(2x)" format) ---------- */
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
              . ($qty > 1 ? '<span class="qty">×' . $qty . '</span>' : '') . '</span>';
    }
    return $count ? $out : '<span class="chip muted">No items</span>';
}

/* ---------- render cards (initial server render; JS repaints in place) ---------- */
function cards_html(array $rows) {
    if (!$rows) {
        return '<div class="empty"><div class="empty-ico">'
             . '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>'
             . '</div><h3>No live orders</h3>'
             . '<p>New customer orders will appear here automatically.</p></div>';
    }
    $html = '';
    foreach ($rows as $row) {
        $badge = status_info($row['status']);
        $html .= '<div class="order-card state-' . $badge[2] . '" data-key="' . htmlspecialchars($row['key']) . '">'
               .   '<div class="oc-head">'
               .     '<div class="oc-ident">'
               .       '<div class="oc-table">Table <b>' . htmlspecialchars($row['table']) . '</b></div>'
               .       '<div class="oc-id">Order #' . (int)$row['id'] . '</div>'
               .     '</div>'
               .     '<span class="badge ' . $badge[1] . '"><span class="bdot"></span>' . htmlspecialchars($badge[0]) . '</span>'
               .   '</div>'
               .   '<div class="chips">' . item_chips_html($row['items']) . '</div>'
               .   '<form method="POST" class="card-actions">'
               .     '<input type="hidden" name="ID" value="' . (int)$row['id'] . '">'
               .     '<button type="submit" name="serve" class="btn">Serve</button>'
               .   '</form>'
               . '</div>';
    }
    return $html;
}

/* ---------- one data load = rows + stats (page + AJAX share this) ---------- */
function load_orders($con) {
    $rows = array();
    // `Table` in backticks (reserved word). Oldest waiting on top (classic queue).
    $sql = "SELECT * FROM orders ORDER BY ID ASC";
    $result = $con->query($sql);
    if ($result) {
        while ($r = $result->fetch_assoc()) {
            $rows[] = array(
                'key'    => 'id' . (int)$r['ID'],
                'id'     => (int)$r['ID'],
                'table'  => (string)$r['Table'],
                'items'  => (string)($r['Items'] ?? ''),
                'status' => (string)($r['status'] ?? ''),
            );
        }
        $result->free();
    }

    // Stats: live count, distinct tables waiting, total item quantities
    $tables = array();
    $items  = 0;
    foreach ($rows as $r) {
        $t = strtolower(trim($r['table']));
        if ($t !== '') { $tables[$t] = true; }
        foreach (explode(',', $r['items']) as $one) {
            $one = trim($one);
            if ($one === '') { continue; }
            $qty = 1;
            if (preg_match('/^(.*?)\((\d+)x\)$/u', $one, $m)) { $qty = max(1, (int)$m[2]); }
            $items += $qty;
        }
    }
    $stats = array('total' => count($rows), 'tables' => count($tables), 'items' => $items);

    return array('ok' => (bool)$result, 'stats' => $stats, 'rows' => $rows);
}

/* ============================================================
   DATABASE + ACTIONS
   ============================================================ */
$con = db_orders();

/* --- Serve (safe prepared statement) --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['ID']) ? (int)$_POST['ID'] : 0;

    if ($id > 0 && isset($_POST['serve'])) {
        $stmt = $con->prepare("DELETE FROM orders WHERE ID = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }

    // Post/Redirect/Get: no "resend form?" popup on refresh
    $con->close();
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

/* --- AJAX endpoint: rows + stats as JSON (client diffs the DOM) --- */
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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<noscript><meta http-equiv="refresh" content="15"></noscript>
<style>
  :root{
    --bg:#fdf8f5; --card:#fff; --line:#f2e7e1; --line2:#f9efe9;
    --ink:#38302b; --ink2:#5c514a; --muted:#8b7d74; --faint:#b8aaa1;
    --brand:#ff6347; --brand-dark:#e8563a; --brand-soft:#fff0eb; --brand-line:#ffd9cc;
    --amber:#92400e; --amber-dot:#f59e0b; --amber-soft:#fdf3e0;
    --green:#15803d; --green-dot:#22c55e; --green-soft:#e2f8ea;
    --red:#b91c1c; --red-soft:#fef2f2; --red-line:#fecaca;
  }
  *{ margin:0; padding:0; box-sizing:border-box; font-family:'Inter',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif; }
  body{ background:var(--bg); color:var(--ink); min-height:100vh; }
  a{ text-decoration:none; }
  button{ font-family:inherit; }
  a:focus-visible, button:focus-visible{ outline:2px solid var(--brand-dark); outline-offset:2px; border-radius:8px; }

  /* ---------- Top bar ---------- */
  header{
    position:sticky; top:0; z-index:700; background:var(--card);
    height:60px; padding:0 18px; display:flex; align-items:center; justify-content:space-between; gap:12px;
    border-bottom:1px solid var(--line);
  }
  .brand{ display:flex; align-items:center; gap:11px; min-width:0; }
  .brand .logo{
    width:34px; height:34px; border-radius:9px; background:var(--brand-soft);
    display:grid; place-items:center; color:var(--brand-dark); flex:0 0 auto;
  }
  .brand h1{ font-size:15px; font-weight:600; letter-spacing:-.1px; white-space:nowrap; }
  .brand p{ font-size:11.5px; color:var(--muted); margin-top:1px; white-space:nowrap; }
  .head-right{ display:flex; align-items:center; gap:9px; }
  .live-pill{
    display:flex; align-items:center; gap:7px; background:var(--card);
    border:1px solid var(--line); padding:6px 11px; border-radius:999px;
    font-size:11.5px; font-weight:500; color:var(--muted); white-space:nowrap;
  }
  .live-pill .dot{ width:7px; height:7px; border-radius:50%; background:var(--green-dot); animation:pulse 2s infinite; }
  @keyframes pulse{ 0%,100%{ box-shadow:0 0 0 0 rgba(34,197,94,.4); } 50%{ box-shadow:0 0 0 5px rgba(34,197,94,0); } }
  .live-pill.offline .dot{ background:#ef4444; animation:none; }
  .live-pill.offline{ color:var(--red); border-color:var(--red-line); }
  .icon-btn{
    background:var(--card); border:1px solid var(--line); color:var(--ink2);
    width:32px; height:32px; border-radius:8px; display:grid; place-items:center;
    cursor:pointer; transition:.15s;
  }
  .icon-btn:hover{ background:var(--line2); }
  .hamburger{ display:none; flex-direction:column; gap:4px; cursor:pointer; padding:8px; border-radius:8px; }
  .hamburger span{ width:16px; height:2px; background:var(--ink2); border-radius:2px; display:block; transition:.25s; }
  .hamburger.open span:nth-child(1){ transform:translateY(6px) rotate(45deg); }
  .hamburger.open span:nth-child(2){ opacity:0; }
  .hamburger.open span:nth-child(3){ transform:translateY(-6px) rotate(-45deg); }

  /* ---------- Layout ---------- */
  .container{ display:grid; grid-template-columns:222px minmax(0,1fr); min-height:calc(100vh - 60px); }

  /* ---------- Sidebar (warm peach) ---------- */
  .sidebar{
    background: linear-gradient(180deg,#fff5f0,#ffece5); border-right:1px solid var(--brand-line);
    display:flex; flex-direction:column;
    position:sticky; top:60px; height:calc(100vh - 60px); overflow:auto;
  }
  .side-brand{
    display:flex; align-items:center; gap:10px;
    padding:16px 16px 14px; border-bottom:1px solid var(--brand-line);
    color:var(--ink); font-size:12.5px; font-weight:600;
  }
  .side-logo{ color:var(--brand-dark); display:grid; place-items:center; }
  .side-label{
    padding:16px 18px 8px; font-size:10.5px; font-weight:600; letter-spacing:1px;
    text-transform:uppercase; color:var(--faint);
  }
  .sidebar nav{ padding:0 12px; }
  .sidebar nav a{
    display:flex; align-items:center; gap:10px; padding:9px 11px; margin:2px 0;
    border-radius:8px; font-size:13.5px; font-weight:500; color:var(--ink2);
    transition:.15s;
  }
  .sidebar nav a svg{ flex:0 0 auto; opacity:.85; }
  .sidebar nav a:hover{ color:var(--brand-dark); background:rgba(255,99,71,.08); }
  .sidebar nav a.active{
    color:#fff; background:linear-gradient(120deg,var(--brand),var(--brand-dark));
    font-weight:600; box-shadow:0 4px 12px rgba(255,99,71,.35);
  }
  .side-count{
    margin-left:auto; min-width:20px; height:20px; padding:0 6px; border-radius:999px;
    background:var(--brand-soft); color:var(--brand-dark); font-size:11px; font-weight:600;
    display:grid; place-items:center;
  }
  .sidebar nav a.active .side-count{ background:rgba(255,255,255,.22); color:#fff; }
  .side-foot{ margin-top:auto; padding:14px 18px; font-size:10.5px; color:var(--faint); border-top:1px solid var(--brand-line); }

  .main{ padding:22px 24px; }
  .page{ max-width:1200px; margin:0 auto; }

  /* ---------- Stats ---------- */
  .stats{ display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:22px; }
  .stat{
    background:var(--card); border:1px solid var(--line); border-radius:12px; padding:14px 16px;
    display:flex; align-items:center; gap:13px;
  }
  .s-ico{ width:38px; height:38px; border-radius:9px; display:grid; place-items:center; flex:0 0 auto; }
  .stat.total  .s-ico{ background:var(--brand-soft); color:var(--brand-dark); }
  .stat.tables .s-ico{ background:var(--amber-soft); color:var(--amber-dot); }
  .stat.items  .s-ico{ background:var(--green-soft); color:var(--green-dot); }
  .s-label{ font-size:10.5px; font-weight:600; letter-spacing:.5px; text-transform:uppercase; color:var(--muted); }
  .s-num{ font-size:22px; font-weight:700; letter-spacing:-.3px; margin-top:2px; font-variant-numeric:tabular-nums; }
  .s-num.pop{ animation:pop .3s ease; }
  @keyframes pop{ 0%{ transform:scale(1); } 45%{ transform:scale(1.14); } 100%{ transform:scale(1); } }

  /* ---------- Section title ---------- */
  .section-title{ display:flex; align-items:center; gap:10px; margin-bottom:14px; }
  .section-title h2{ font-size:15.5px; font-weight:600; }
  .section-sub{ font-size:12px; color:var(--muted); }

  /* ---------- Order cards ---------- */
  .orders{ display:grid; gap:14px; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); }
  .order-card{
    background:var(--card); border:1px solid var(--line); border-left:3px solid var(--faint);
    border-radius:12px; padding:15px 16px 13px; display:flex; flex-direction:column;
    transition:transform .15s, box-shadow .15s, opacity .25s;
  }
  .order-card:hover{ transform:translateY(-1px); box-shadow:0 4px 14px rgba(120,72,50,.08); }
  .state-new     { border-left-color:var(--brand); }
  .state-pending { border-left-color:var(--amber-dot); }
  .state-serving { border-left-color:var(--green-dot); }
  .order-card.entering{ animation:enter .3s ease; }
  @keyframes enter{ from{ opacity:0; transform:translateY(8px); } to{ opacity:1; transform:none; } }
  .order-card.leaving{ opacity:0; transform:scale(.98); }

  .oc-head{ display:flex; align-items:flex-start; justify-content:space-between; gap:10px; }
  .oc-table{ font-size:15px; font-weight:600; }
  .oc-table b{ font-weight:700; }
  .oc-id{ font-size:11.5px; color:var(--faint); margin-top:2px; font-variant-numeric:tabular-nums; }

  .badge{
    display:inline-flex; align-items:center; gap:6px; padding:3px 10px; border-radius:999px;
    font-size:11.5px; font-weight:600; white-space:nowrap; flex:0 0 auto;
  }
  .badge .bdot{ width:6px; height:6px; border-radius:50%; background:currentColor; opacity:.55; }
  .badge-new{ background:var(--brand-soft); color:var(--brand-dark); }
  .badge-pending{ background:var(--amber-soft); color:var(--amber); }
  .badge-serving{ background:var(--green-soft); color:var(--green); }

  .chips{ display:flex; flex-wrap:wrap; gap:6px; margin-top:11px; }
  .chip{
    background:var(--line2); border:1px solid var(--line); color:var(--ink2);
    border-radius:7px; padding:4px 9px; font-size:12px; font-weight:500;
  }
  .chip .qty{ color:var(--brand-dark); font-weight:600; margin-left:4px; }
  .chip.muted{ color:var(--faint); font-style:italic; font-weight:400; border-style:dashed; }

  .card-actions{ display:flex; gap:8px; margin-top:auto; padding-top:13px; }
  .btn{
    width:100%; height:38px; border:none; border-radius:8px; cursor:pointer;
    background:linear-gradient(120deg,var(--brand),var(--brand-dark)); color:#fff;
    font-weight:600; font-size:13px; transition:.15s; box-shadow:0 3px 10px rgba(255,99,71,.3);
  }
  .btn:hover{ box-shadow:0 5px 14px rgba(255,99,71,.4); transform:translateY(-1px); }
  button:disabled{ opacity:.55; cursor:wait; }

  /* ---------- Empty state ---------- */
  .empty{ grid-column:1/-1; text-align:center; background:var(--card); border:1px solid var(--line); border-radius:12px; padding:44px 20px; }
  .empty-ico{ width:46px; height:46px; border-radius:50%; background:var(--brand-soft); color:var(--brand-dark); display:grid; place-items:center; margin:0 auto 10px; }
  .empty h3{ font-size:15px; font-weight:600; color:var(--ink); }
  .empty p{ color:var(--muted); font-size:12.5px; margin-top:4px; }

  footer{
    text-align:center; padding:16px; font-size:11.5px; color:var(--faint);
    border-top:1px solid var(--line2);
  }

  /* ---------- Responsive: tablets ---------- */
  @media (max-width:1024px){
    .container{ grid-template-columns:200px minmax(0,1fr); }
  }

  /* ---------- Responsive: mobiles ---------- */
  .overlay{ display:none; }
  @media (max-width:600px){
    .container{ grid-template-columns:1fr; }
    .main{ padding:16px 14px; }
    .brand h1{ font-size:13.5px; } .brand p{ font-size:10px; }
    .live-pill{ font-size:10.5px; padding:5px 9px; }
    .hamburger{ display:flex; }
    .icon-btn{ display:none; }
    .stats{ gap:9px; }
    .stat{ padding:10px 11px; gap:9px; }
    .s-ico{ width:32px; height:32px; border-radius:8px; }
    .s-ico svg{ width:15px; height:15px; }
    .s-num{ font-size:18px; }
    .s-label{ font-size:9px; }
    .orders{ grid-template-columns:1fr; }
    .sidebar{
      position:fixed; top:0; left:-104%; width:76%; max-width:250px; height:100vh;
      z-index:800; transition:left .28s ease; box-shadow:0 0 40px rgba(0,0,0,.25);
    }
    .sidebar.active{ left:0; }
    .overlay.show{ display:block; position:fixed; inset:0; background:rgba(56,48,43,.4); z-index:750; }
  }

  @media (prefers-reduced-motion:reduce){
    .order-card.entering, .live-pill .dot, .s-num.pop{ animation:none !important; }
    .order-card{ transition:none; }
  }
</style>
</head>
<body>

<header>
  <div class="brand">
    <span class="logo">
      <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="3.5"/></svg>
    </span>
    <div>
      <h1>Waiter Dashboard</h1>
      <p>Raman Restaurant</p>
    </div>
  </div>
  <div class="head-right">
    <span class="live-pill" id="livePill"><span class="dot"></span><span id="liveNote">Live</span></span>
    <button class="icon-btn" id="refreshBtn" title="Refresh now" aria-label="Refresh">
      <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v5h-5"/></svg>
    </button>
    <span class="hamburger" id="hamburger" role="button" aria-label="Menu"><span></span><span></span><span></span></span>
  </div>
</header>

<div class="container">
  <aside class="sidebar" id="sidebar">
    <div class="side-brand">
      <span class="side-logo">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="3.5"/></svg>
      </span>
      Raman Restaurant
    </div>
    <div class="side-label">Workspace</div>
    <nav>
      <a class="active" href="/api/waiter.php">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M9 5h12M9 12h12M9 19h12M4 5h.01M4 12h.01M4 19h.01"/></svg>
        Orders <span class="side-count" id="sideCount"><?php echo (int)$view['stats']['total']; ?></span>
      </a>
      <a href="/api/history.php">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="8.5"/><path d="M12 8v4l2.5 2"/></svg>
        History
      </a>
    </nav>
    <div class="side-foot">Staff view · QR ordering system</div>
  </aside>

  <main class="main">
    <div class="page">

      <div class="stats">
        <div class="stat total">
          <span class="s-ico"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 9h6M9 13h6M9 17h4"/></svg></span>
          <div><div class="s-label">Live orders</div><div class="s-num" id="stTotal"><?php echo (int)$view['stats']['total']; ?></div></div>
        </div>
        <div class="stat tables">
          <span class="s-ico"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/></svg></span>
          <div><div class="s-label">Tables waiting</div><div class="s-num" id="stTables"><?php echo (int)$view['stats']['tables']; ?></div></div>
        </div>
        <div class="stat items">
          <span class="s-ico"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m12 2 10 5-10 5L2 7l10-5z"/><path d="m2 12 10 5 10-5"/><path d="m2 17 10 5 10-5"/></svg></span>
          <div><div class="s-label">Items to deliver</div><div class="s-num" id="stItems"><?php echo (int)$view['stats']['items']; ?></div></div>
        </div>
      </div>

      <div class="section-title">
        <h2>Live Orders</h2>
        <span class="section-sub">oldest waiting on top</span>
      </div>

      <div class="orders" id="orderGrid"><?php echo cards_html($view['rows']); ?></div>

    </div>
  </main>
</div>

<div class="overlay" id="overlay"></div>

<footer>Raman Restaurant · Staff interface</footer>

<script>
/* ================= Helpers ================= */
function esc(s){ return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

/* Same status mapping as the PHP side */
function statusOf(s){
  s = String(s || '').toLowerCase().trim();
  if (s === 'pending') return { label:'Pending', badge:'badge-pending', state:'pending' };
  if (s === 'serving') return { label:'Serving', badge:'badge-serving', state:'serving' };
  if (!s)              return { label:'New',     badge:'badge-new',     state:'new' };
  return { label: s.charAt(0).toUpperCase() + s.slice(1), badge:'badge-new', state:'new' };
}

/* Same "(2x)" parsing as the PHP side */
function chipsHtml(items){
  const out = [];
  let count = 0;
  String(items || '').split(',').forEach(part => {
    part = part.trim();
    if (!part) return;
    let name = part, qty = 0;
    const m = part.match(/^(.*?)\((\d+)x\)$/u);
    if (m) { name = m[1].trim(); qty = Math.max(1, parseInt(m[2], 10)); }
    count++;
    out.push('<span class="chip">' + esc(name) + (qty > 1 ? '<span class="qty">×' + qty + '</span>' : '') + '</span>');
  });
  return count ? out.join('') : '<span class="chip muted">No items</span>';
}

/* ================= Card DOM ================= */
const grid = document.getElementById('orderGrid');

function buildCard(row){
  const st = statusOf(row.status);
  const el = document.createElement('div');
  el.className = 'order-card state-' + st.state + ' entering';
  el.dataset.key = String(row.key);
  el.innerHTML =
    '<div class="oc-head">' +
      '<div class="oc-ident">' +
        '<div class="oc-table">Table <b>' + esc(row.table) + '</b></div>' +
        '<div class="oc-id">Order #' + row.id + '</div>' +
      '</div>' +
      '<span class="badge ' + st.badge + '"><span class="bdot"></span>' + esc(st.label) + '</span>' +
    '</div>' +
    '<div class="chips">' + chipsHtml(row.items) + '</div>' +
    '<form method="POST" class="card-actions">' +
      '<input type="hidden" name="ID" value="' + row.id + '">' +
      '<button type="submit" name="serve" class="btn">Serve</button>' +
    '</form>';
  el.addEventListener('animationend', () => el.classList.remove('entering'), { once:true });
  return el;
}

/* Update an existing card in place - no re-animation */
function paintCard(el, row){
  const st = statusOf(row.status);
  el.dataset.key = String(row.key);
  el.classList.remove('state-new', 'state-pending', 'state-serving');
  el.classList.add('state-' + st.state);
  el.querySelector('.oc-table b').textContent = row.table;
  el.querySelector('.oc-id').textContent = 'Order #' + row.id;
  const badge = el.querySelector('.badge');
  badge.className = 'badge ' + st.badge;
  badge.textContent = '';
  const dot = document.createElement('span'); dot.className = 'bdot';
  badge.append(dot, document.createTextNode(st.label));
  el.querySelector('.chips').innerHTML = chipsHtml(row.items);
  const f = el.querySelector('input[name=ID]'); if (f) f.value = row.id;
}

/* ================= The anti-flash diff =================
   Only NEW cards animate. Changed cards update in place.
   Served orders fade out. Order is mirrored from the data. */
function reconcile(rows){
  const pools = new Map();
  grid.querySelectorAll('.order-card').forEach(el => {
    if (el.classList.contains('leaving')) return;
    const k = el.dataset.key || el.dataset.id || '';
    if (!pools.has(k)) pools.set(k, []);
    pools.get(k).push(el);
  });
  const take = k => {
    const q = pools.get(k);
    const el = q && q.shift();
    if (q && !q.length) pools.delete(k);
    return el || null;
  };

  rows.forEach((row, idx) => {
    const el = take(String(row.key));
    const at = grid.children[idx] || null;
    if (el) {
      paintCard(el, row);
      if (grid.children[idx] !== el) grid.insertBefore(el, at);
    } else {
      const card = buildCard(row);
      grid.insertBefore(card, at);
    }
  });

  pools.forEach(q => q.forEach(el => {
    el.classList.remove('entering');
    el.classList.add('leaving');
    setTimeout(() => el.remove(), 260);
  }));
}

/* ================= Stats ================= */
const stTotal  = document.getElementById('stTotal');
const stTables = document.getElementById('stTables');
const stItems  = document.getElementById('stItems');

function popNum(el, value){
  if (!el || el.textContent === String(value)) return;
  el.textContent = value;
  el.classList.remove('pop');
  void el.offsetWidth;
  el.classList.add('pop');
}
function setStats(s){
  popNum(stTotal, s.total);
  popNum(stTables, s.tables);
  popNum(stItems, s.items);
  const sc = document.getElementById('sideCount');
  if (sc) sc.textContent = s.total;
}

/* ================= Live polling ================= */
const note = document.getElementById('liveNote');
const pill = document.getElementById('livePill');
let lastSync = Date.now();

function refreshNote(){
  if (pill.classList.contains('offline')) return;
  const s = Math.round((Date.now() - lastSync) / 1000);
  note.textContent = s <= 0 ? 'Updated just now' : 'Updated ' + s + 's ago';
}

async function poll(){
  try {
    const res  = await fetch(location.pathname + '?ajax=view', { cache:'no-store' });
    const data = await res.json();
    if (data.ok) {
      reconcile(data.rows);
      setStats(data.stats);
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

/* ================= Confirm before serving =================
   Delegated on document so it still works after DOM updates. */
document.addEventListener('submit', (e) => {
  const form = e.target;
  if (form.dataset.busy) { e.preventDefault(); return; }      /* double-click guard */

  let action = e.submitter ? e.submitter.name : '';
  if (!action) { const b = form.querySelector('button[type=submit][name]'); if (b) action = b.name; }

  if (action === 'serve') {
    if (!window.confirm('Mark this order as SERVED?\nIt will be cleared from the live list.')) {
      e.preventDefault(); return;
    }
  }
  form.dataset.busy = '1';
  setTimeout(() => { delete form.dataset.busy; }, 4000);
});

/* ================= Sidebar (mobile) ================= */
const hamburger = document.getElementById('hamburger');
const sidebar   = document.getElementById('sidebar');
const overlay   = document.getElementById('overlay');

function setMenu(open){
  sidebar.classList.toggle('active', open);
  overlay.classList.toggle('show', open);
  hamburger.classList.toggle('open', open);
}
hamburger.addEventListener('click', () => setMenu(!sidebar.classList.contains('active')));
overlay.addEventListener('click', () => setMenu(false));
</script>

</body>
</html>
