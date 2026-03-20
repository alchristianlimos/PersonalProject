<?php
session_start();
require 'db.php';

$ADMIN_ACCOUNTS = [
    'jcesperanza@neu.edu.ph' => 'admin123',
];

$admin_error = '';

// ── Google Sign-In for Admin ──────────────────────────────────────────────
if (isset($_POST['google_token'])) {
    $token     = $_POST['google_token'];
    $client_id = 'YOUR_GOOGLE_CLIENT_ID_HERE.apps.googleusercontent.com';

    $response = @file_get_contents("https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($token));
    $payload  = json_decode($response, true);

    if (!$payload || ($payload['aud'] ?? '') !== $client_id) {
        $admin_error = "Google Sign-In failed. Please try again.";
    } else {
        $google_email = strtolower(trim($payload['email'] ?? ''));

        if (!isset($ADMIN_ACCOUNTS[$google_email])) {
            $admin_error = "⛔ This Google account is not registered as an admin.";
        } else {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_email']     = $google_email;
            header("Location: admin.php"); exit;
        }
    }
}

// ── Email + Password Login ────────────────────────────────────────────────
if (isset($_POST['admin_email'], $_POST['admin_password'])) {
    $input_email = strtolower(trim($_POST['admin_email']));
    $input_pass  = $_POST['admin_password'];

    if (!filter_var($input_email, FILTER_VALIDATE_EMAIL)) {
        $admin_error = "Please enter a valid email address.";
    } elseif (!isset($ADMIN_ACCOUNTS[$input_email])) {
        $admin_error = "This email is not registered as an admin.";
    } elseif ($ADMIN_ACCOUNTS[$input_email] !== $input_pass) {
        $admin_error = "Incorrect password.";
    } else {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_email']     = $input_email;
        header("Location: admin.php"); exit;
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in'], $_SESSION['admin_email']);
    header("Location: admin.php"); exit;
}
$logged_in = !empty($_SESSION['admin_logged_in']);

// ── Block/Unblock ─────────────────────────────────────────────────────────
if ($logged_in && isset($_POST['toggle_block'])) {
    $vid     = (int)$_POST['visitor_id'];
    $blocked = (int)$_POST['current_blocked'];
    $new_val = $blocked ? 0 : 1;
    $stmt = $conn->prepare("UPDATE visitors SET blocked=? WHERE id=?");
    $stmt->bind_param("ii", $new_val, $vid);
    $stmt->execute();
    header("Location: admin.php?tab=visitors"); exit;
}

// ── Export PDF ────────────────────────────────────────────────────────────
if ($logged_in && isset($_GET['export_pdf'])) {
    $period = $_GET['export_pdf'];
    $from   = $conn->real_escape_string($_GET['from']   ?? '');
    $to     = $conn->real_escape_string($_GET['to']     ?? '');
    $search = $conn->real_escape_string($_GET['search'] ?? '');
    $where  = "1=1";
    if ($period==='today') $where .= " AND DATE(timestamp)=CURDATE()";
    elseif ($period==='week')  $where .= " AND timestamp>=DATE_SUB(NOW(),INTERVAL 7 DAY)";
    elseif ($period==='month') $where .= " AND MONTH(timestamp)=MONTH(NOW()) AND YEAR(timestamp)=YEAR(NOW())";
    elseif ($period==='range' && $from && $to) $where .= " AND DATE(timestamp) BETWEEN '$from' AND '$to'";
    if ($search) $where .= " AND (name LIKE '%$search%' OR program LIKE '%$search%' OR reason LIKE '%$search%' OR email LIKE '%$search%')";
    $result = $conn->query("SELECT name,email,rfid,year_level,program,type,reason,timestamp FROM visitor_logs WHERE $where ORDER BY timestamp DESC");
    $rows_html = ''; $count = 0;
    while ($row = $result->fetch_assoc()) {
        $count++;
        $bg = $count % 2 === 0 ? '#f0f4f8' : '#ffffff';
        $rows_html .= "<tr style='background:{$bg}'>
            <td>".htmlspecialchars($row['name'])."</td>
            <td>".htmlspecialchars($row['email'])."</td>
            <td>".htmlspecialchars($row['rfid'])."</td>
            <td>".htmlspecialchars($row['year_level'])."</td>
            <td>".htmlspecialchars($row['program'])."</td>
            <td>".htmlspecialchars($row['type'])."</td>
            <td>".htmlspecialchars($row['reason'])."</td>
            <td>".date('M j, Y g:i A', strtotime($row['timestamp']))."</td>
        </tr>";
    }
    $labels = ['today'=>'Today','week'=>'This Week','month'=>'This Month','range'=>"$from to $to",'all'=>'All Time'];
    $period_lbl = $labels[$period] ?? ucfirst($period);
    $generated  = date('F j, Y g:i A');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"/><title>NEU Library – Visitor Logs</title>
    <style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:Georgia,serif;padding:40px;color:#1a1a2e}
    .hdr{border-bottom:3px solid #0d3b66;padding-bottom:14px;margin-bottom:16px}
    .hdr h1{color:#0d3b66;font-size:22px;margin-bottom:4px}.hdr p{color:#666;font-size:13px}
    .meta{display:flex;gap:20px;margin-bottom:20px}
    .mb{background:#f0f4f8;border-radius:8px;padding:10px 16px;font-size:13px;color:#555}
    .mb span{color:#0d3b66;font-weight:800;font-size:20px;display:block}
    table{width:100%;border-collapse:collapse;font-size:12px}
    th{background:#0d3b66;color:#fff;padding:9px 10px;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.5px}
    td{padding:8px 10px;border-bottom:1px solid #dde}
    .ft{margin-top:22px;color:#999;font-size:12px;text-align:right}
    @media print{body{padding:20px}}</style></head><body>
    <div class="hdr"><h1>&#128218; NEU Library &ndash; Visitor Logs</h1>
    <p>New Era University &nbsp;|&nbsp; Generated: '.$generated.'</p></div>
    <div class="meta">
    <div class="mb"><span>'.$count.'</span>Total Records</div>
    <div class="mb"><span>'.$period_lbl.'</span>Period</div>
    <div class="mb"><span>'.date('F j, Y').'</span>Date Printed</div></div>
    <table><thead><tr><th>Name</th><th>Email</th><th>RFID</th><th>Year Level</th>
    <th>Program</th><th>Type</th><th>Reason</th><th>Date &amp; Time</th></tr></thead>
    <tbody>'.($rows_html ?: '<tr><td colspan="8" style="text-align:center;padding:24px;color:#aaa">No records.</td></tr>').'</tbody></table>
    <div class="ft">NEU Library Visitor Log System &nbsp;|&nbsp; '.$generated.'</div>
    <script>window.onload=()=>window.print();<\/script></body></html>';
    exit;
}

// ── Load dashboard data ───────────────────────────────────────────────────
if ($logged_in) {
    $tab    = $_GET['tab']    ?? 'dashboard';
    $search = $_GET['search'] ?? '';
    $period = $_GET['period'] ?? 'today';
    $from   = $_GET['from']   ?? '';
    $to     = $_GET['to']     ?? '';
    $today_ct       = $conn->query("SELECT COUNT(*) c FROM visitor_logs WHERE DATE(timestamp)=CURDATE()")->fetch_assoc()['c'];
    $week_ct        = $conn->query("SELECT COUNT(*) c FROM visitor_logs WHERE timestamp>=DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetch_assoc()['c'];
    $month_ct       = $conn->query("SELECT COUNT(*) c FROM visitor_logs WHERE MONTH(timestamp)=MONTH(NOW()) AND YEAR(timestamp)=YEAR(NOW())")->fetch_assoc()['c'];
    $total_visitors = $conn->query("SELECT COUNT(*) c FROM visitors")->fetch_assoc()['c'];
    $blocked_ct     = $conn->query("SELECT COUNT(*) c FROM visitors WHERE blocked=1")->fetch_assoc()['c'];
    $type_today = [];
    $tr = $conn->query("SELECT type,COUNT(*) c FROM visitor_logs WHERE DATE(timestamp)=CURDATE() GROUP BY type");
    while ($row = $tr->fetch_assoc()) $type_today[$row['type']] = $row['c'];
    $where = "1=1";
    if ($search) { $s=$conn->real_escape_string($search); $where.=" AND (l.name LIKE '%$s%' OR l.program LIKE '%$s%' OR l.reason LIKE '%$s%' OR l.email LIKE '%$s%')"; }
    if ($period==='today')     $where.=" AND DATE(l.timestamp)=CURDATE()";
    elseif ($period==='week')  $where.=" AND l.timestamp>=DATE_SUB(NOW(),INTERVAL 7 DAY)";
    elseif ($period==='month') $where.=" AND MONTH(l.timestamp)=MONTH(NOW()) AND YEAR(l.timestamp)=YEAR(NOW())";
    elseif ($period==='range'&&$from&&$to) $where.=" AND DATE(l.timestamp) BETWEEN '".$conn->real_escape_string($from)."' AND '".$conn->real_escape_string($to)."'";
    $logs         = $conn->query("SELECT l.* FROM visitor_logs l WHERE $where ORDER BY l.timestamp DESC");
    $recent       = $conn->query("SELECT * FROM visitor_logs ORDER BY timestamp DESC LIMIT 7");
    $all_visitors = $conn->query("SELECT * FROM visitors ORDER BY created_at DESC");
}

function badge($label,$type){
    $c=['student'=>'#1a73e8','faculty'=>'#0d7c5f','staff'=>'#7b2d8b','employee'=>'#7b2d8b','active'=>'#0d7c5f','blocked'=>'#c0392b'];
    $bg=$c[strtolower($type)]??'#555';
    return "<span style='background:{$bg};color:#fff;padding:3px 11px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap'>".htmlspecialchars($label)."</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>NEU Library – Admin</title>
<script src="https://accounts.google.com/gsi/client" async defer></script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Georgia,serif;background:#eef2f7;min-height:100vh}
input,select,button,a{font-family:Georgia,serif}
/* ── Login ── */
.login-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;
  background:#060b14;
  background-image:linear-gradient(rgba(26,115,232,.05) 1px,transparent 1px),
  linear-gradient(90deg,rgba(26,115,232,.05) 1px,transparent 1px);background-size:48px 48px}
.login-card{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);
  border-radius:20px;padding:40px;width:380px;box-shadow:0 24px 64px rgba(0,0,0,.5);text-align:center}
.login-card h2{color:#fff;margin-bottom:6px;font-size:20px}
.login-card p{color:#607080;font-size:13px;margin-bottom:22px}
.login-card input{width:100%;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.2);
  border-radius:9px;color:#fff;padding:12px 14px;font-size:14px;outline:none;margin-bottom:13px}
.login-card input::placeholder{color:#3a5070}
.login-card button[type=submit]{width:100%;padding:12px;
  background:linear-gradient(135deg,#1a73e8,#0d52c4);color:#fff;border:none;
  border-radius:9px;font-size:15px;font-weight:700;cursor:pointer}
.login-err{color:#ff8888;font-size:13px;margin-bottom:13px}
.back-link{display:block;margin-top:16px;color:#607080;font-size:13px;text-decoration:none}
.divider{display:flex;align-items:center;gap:12px;margin:18px 0}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:rgba(255,255,255,.1)}
.divider span{color:#3a5070;font-size:12px;white-space:nowrap}
.google-wrap{display:flex;justify-content:center;margin-bottom:4px}
/* ── Nav ── */
nav{background:#0d3b66;padding:0 24px;display:flex;align-items:center;height:60px;gap:4px;
  box-shadow:0 2px 14px rgba(0,0,0,.25);position:sticky;top:0;z-index:50}
.nav-brand{color:#fff;font-weight:700;font-size:17px;margin-right:auto}
nav a.nav-tab{padding:8px 16px;border-radius:8px;color:rgba(255,255,255,.6);text-decoration:none;font-size:14px}
nav a.nav-tab:hover{color:#fff;background:rgba(255,255,255,.1)}
nav a.nav-tab.active{background:rgba(255,255,255,.18);color:#fff;font-weight:700}
.admin-email{color:rgba(255,255,255,.4);font-size:13px;margin:0 6px}
.logout-btn{padding:8px 16px;border-radius:8px;background:rgba(192,57,43,.22);
  border:1px solid rgba(192,57,43,.4);color:#ffaaaa;text-decoration:none;font-size:13px}
/* ── Content ── */
.content{max-width:1160px;margin:0 auto;padding:28px 24px}
.page-title{color:#0d3b66;font-size:22px;margin-bottom:22px}
/* ── Cards ── */
.cards3{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:22px}
.stat-card{background:#fff;border-radius:16px;padding:24px 28px;box-shadow:0 2px 12px rgba(0,0,0,.07)}
.stat-card .icon{font-size:28px;margin-bottom:8px}
.stat-card .num{font-size:42px;font-weight:800;line-height:1.1}
.stat-card .lbl{color:#777;font-size:14px;margin-top:6px}
.mini-cards{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:22px}
.mini-card{background:#fff;border-radius:14px;padding:20px 16px;
  box-shadow:0 2px 12px rgba(0,0,0,.07);text-align:center}
.mini-card .num{font-size:30px;font-weight:800}
.mini-card .lbl{color:#777;font-size:12px;margin-top:5px}
/* ── Panels ── */
.panel{background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.07);overflow:hidden;margin-bottom:24px}
.panel-head{padding:16px 20px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #eee}
.panel-head strong{color:#0d3b66;font-size:15px}
.panel-head a{color:#1a73e8;font-size:13px;text-decoration:none}
.count-bar{padding:12px 20px;border-bottom:1px solid #eee;color:#888;font-size:13px}
.overflow{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:640px}
thead tr{background:#f8fafc;border-bottom:2px solid #eee}
th{text-align:left;padding:11px 16px;color:#999;font-size:11px;text-transform:uppercase;letter-spacing:.8px;white-space:nowrap}
td{padding:11px 16px;border-bottom:1px solid #f2f2f2;color:#555;font-size:14px;vertical-align:middle}
td.nc{color:#1a1a2e;font-weight:600}
tbody tr:hover td{background:#fafbff}
.no-rec td{text-align:center;padding:40px;color:#bbb;font-size:15px}
/* ── Filters ── */
.filters{background:#fff;border-radius:14px;padding:18px 20px;margin-bottom:20px;
  box-shadow:0 2px 12px rgba(0,0,0,.07);display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end}
.filters label{color:#999;font-size:11px;text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:6px}
.filters input[type=text],.filters input[type=date],.filters select{
  border:1.5px solid #dde;border-radius:8px;padding:9px 12px;font-size:14px;outline:none;color:#333;background:#fff}
.f-search{flex:2 1 180px}.f-search input{width:100%}
.f-period{flex:1 1 140px}.f-period select{width:100%}
.btn-p{padding:10px 20px;background:#0d3b66;color:#fff;border:none;border-radius:9px;
  cursor:pointer;font-size:13px;font-weight:700;text-decoration:none;display:inline-block}
.btn-p:hover{background:#0a2d52}
.btn-pdf{background:#c0392b}.btn-pdf:hover{background:#a93226}
.top-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
.block-btn{padding:6px 16px;border-radius:7px;border:none;cursor:pointer;font-size:12px;font-weight:700}
</style>
</head>
<body>

<?php if (!$logged_in): ?>
<!-- ── ADMIN LOGIN ── -->
<div class="login-wrap">
  <div class="login-card">
    <div style="font-size:42px;margin-bottom:12px">🔒</div>
    <h2>Admin Login</h2>
    <p>NEU Library Management System</p>

    <?php if ($admin_error): ?>
      <p class="login-err"><?= htmlspecialchars($admin_error) ?></p>
    <?php endif; ?>

    <!-- Google Sign-In -->
    <div id="g_id_onload"
      data-client_id="YOUR_GOOGLE_CLIENT_ID_HERE.apps.googleusercontent.com"
      data-callback="handleAdminGoogleSignIn"
      data-auto_prompt="false">
    </div>
    <div class="google-wrap">
      <div class="g_id_signin"
        data-type="standard"
        data-size="large"
        data-theme="outline"
        data-text="signin_with"
        data-shape="rectangular"
        data-logo_alignment="left"
        data-width="300">
      </div>
    </div>

    <div class="divider"><span>or use email &amp; password</span></div>

    <!-- Email + Password -->
    <form method="POST">
      <input type="email" name="admin_email" placeholder="Admin email address"
        value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>"/>
      <input type="password" name="admin_password" placeholder="Password"/>
      <button type="submit">Login →</button>
    </form>
    <a href="index.php" class="back-link">← Back to Visitor Login</a>
  </div>
</div>

<?php else: ?>
<!-- ── NAV ── -->
<nav>
  <div class="nav-brand">📚 NEU Library Admin</div>
  <a href="admin.php?tab=dashboard" class="nav-tab <?= $tab==='dashboard'?'active':'' ?>">📊 Dashboard</a>
  <a href="admin.php?tab=logs"      class="nav-tab <?= $tab==='logs'     ?'active':'' ?>">📋 Visitor Logs</a>
  <a href="admin.php?tab=visitors"  class="nav-tab <?= $tab==='visitors' ?'active':'' ?>">👥 Visitors</a>
  <span class="admin-email"><?= htmlspecialchars($_SESSION['admin_email'] ?? '') ?></span>
  <a href="admin.php?logout=1" class="logout-btn">Sign Out</a>
</nav>

<div class="content">

<?php if ($tab==='dashboard'): ?>
<h2 class="page-title">Library Statistics</h2>
<div class="cards3">
  <div class="stat-card" style="border-left:5px solid #1a73e8"><div class="icon">🌅</div><div class="num" style="color:#1a73e8"><?= $today_ct ?></div><div class="lbl">Today's Visitors</div></div>
  <div class="stat-card" style="border-left:5px solid #0d7c5f"><div class="icon">📅</div><div class="num" style="color:#0d7c5f"><?= $week_ct ?></div><div class="lbl">This Week</div></div>
  <div class="stat-card" style="border-left:5px solid #7b2d8b"><div class="icon">🗓️</div><div class="num" style="color:#7b2d8b"><?= $month_ct ?></div><div class="lbl">This Month</div></div>
</div>
<div class="mini-cards">
  <div class="mini-card" style="border:2px solid #1a73e822"><div class="num" style="color:#1a73e8"><?= $type_today['student']??0 ?></div><div class="lbl">Students today</div></div>
  <div class="mini-card" style="border:2px solid #0d7c5f22"><div class="num" style="color:#0d7c5f"><?= $type_today['faculty']??0 ?></div><div class="lbl">Faculty today</div></div>
  <div class="mini-card" style="border:2px solid #7b2d8b22"><div class="num" style="color:#7b2d8b"><?= $type_today['staff']??0 ?></div><div class="lbl">Staff today</div></div>
  <div class="mini-card" style="border:2px solid #1a73e822"><div class="num" style="color:#1a73e8"><?= $total_visitors ?></div><div class="lbl">Total Registered</div></div>
  <div class="mini-card" style="border:2px solid #c0392b22"><div class="num" style="color:#c0392b"><?= $blocked_ct ?></div><div class="lbl">Blocked</div></div>
</div>
<div class="panel">
  <div class="panel-head"><strong>Recent Visits</strong><a href="admin.php?tab=logs&period=all">View all →</a></div>
  <div class="overflow"><table>
    <thead><tr><th>Name</th><th>Email</th><th>Year Level</th><th>Program</th><th>Type</th><th>Reason</th><th>Time</th></tr></thead>
    <tbody>
    <?php while ($r=$recent->fetch_assoc()): ?>
    <tr>
      <td class="nc"><?= htmlspecialchars($r['name']) ?></td>
      <td style="font-size:13px"><?= htmlspecialchars($r['email']) ?></td>
      <td><?= htmlspecialchars($r['year_level']) ?></td>
      <td><?= htmlspecialchars($r['program']) ?></td>
      <td><?= badge($r['type'],$r['type']) ?></td>
      <td><?= htmlspecialchars($r['reason']) ?></td>
      <td style="color:#999;font-size:13px;white-space:nowrap"><?= date('M j, Y g:i A',strtotime($r['timestamp'])) ?></td>
    </tr>
    <?php endwhile; ?>
    </tbody>
  </table></div>
</div>

<?php elseif ($tab==='logs'): ?>
<div class="top-bar">
  <h2 class="page-title" style="margin:0">Visitor Logs</h2>
  <button onclick="exportPDF()" class="btn-p btn-pdf">🖨️ Export PDF</button>
</div>
<div class="filters">
  <div class="f-search">
    <label>Search</label>
    <input type="text" id="logSearch" placeholder="Type name, email, program, reason…"
      oninput="liveSearchLogs()" autocomplete="off"/>
  </div>
  <div class="f-period">
    <label>Period</label>
    <select id="logPeriod" onchange="liveSearchLogs()">
      <option value="today">Today</option>
      <option value="week">This Week</option>
      <option value="month">This Month</option>
      <option value="range">Custom Range</option>
      <option value="all">All Time</option>
    </select>
  </div>
  <div id="rangeFields" style="display:none;gap:12px;display:none">
    <div><label>From</label><input type="date" id="logFrom" onchange="liveSearchLogs()"/></div>
    <div><label>To</label><input type="date" id="logTo" onchange="liveSearchLogs()"/></div>
  </div>
</div>
<div class="panel">
  <div class="count-bar">Showing <strong id="logCount" style="color:#0d3b66">0</strong> records</div>
  <div class="overflow">
    <table>
      <thead><tr><th>Name</th><th>Email</th><th>RFID</th><th>Year Level</th><th>Program</th><th>Type</th><th>Reason</th><th>Date &amp; Time</th></tr></thead>
      <tbody id="logsBody">
        <tr><td colspan="8" style="text-align:center;padding:32px;color:#bbb">Loading…</td></tr>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab==='visitors'): ?>
<h2 class="page-title">Visitor Management</h2>
<div class="filters" style="margin-bottom:20px">
  <div class="f-search">
    <label>Search Visitors</label>
    <input type="text" id="visitorSearch" placeholder="Type name, email, RFID, program…"
      oninput="liveSearchVisitors()" autocomplete="off"/>
  </div>
</div>
<div class="panel">
  <div class="overflow">
    <table>
      <thead><tr><th>RFID</th><th>Name</th><th>Email</th><th>Year Level</th><th>Program</th><th>Type</th><th>Status</th><th>Action</th></tr></thead>
      <tbody id="visitorsBody">
        <tr><td colspan="8" style="text-align:center;padding:32px;color:#bbb">Loading…</td></tr>
      </tbody>
    </table>
  </div>
</div>

<?php endif; ?>
</div>
<?php endif; ?>

<!-- Hidden form for Google Admin token -->
<form id="googleAdminForm" method="POST" style="display:none">
  <input type="hidden" name="google_token" id="googleAdminToken"/>
</form>

<script>
// ── Google Sign-In ────────────────────────────────────────────────────────
function handleAdminGoogleSignIn(response) {
  document.getElementById('googleAdminToken').value = response.credential;
  document.getElementById('googleAdminForm').submit();
}

// ── Badge helper ──────────────────────────────────────────────────────────
const BADGE = {
  student:'#1a73e8', faculty:'#0d7c5f', staff:'#7b2d8b',
  employee:'#7b2d8b', active:'#0d7c5f', blocked:'#c0392b'
};
function badge(label, type) {
  const bg = BADGE[type?.toLowerCase()] || '#555';
  return `<span style="background:${bg};color:#fff;padding:3px 11px;border-radius:20px;
          font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;
          white-space:nowrap">${label}</span>`;
}

// ── Live Search: Logs ─────────────────────────────────────────────────────
let logTimer = null;
function liveSearchLogs() {
  clearTimeout(logTimer);
  logTimer = setTimeout(fetchLogs, 250); // 250ms debounce

  // Show/hide range fields
  const period = document.getElementById('logPeriod')?.value;
  const rf = document.getElementById('rangeFields');
  if (rf) rf.style.display = period === 'range' ? 'flex' : 'none';
}

async function fetchLogs() {
  const q      = document.getElementById('logSearch')?.value   || '';
  const period = document.getElementById('logPeriod')?.value   || 'today';
  const from   = document.getElementById('logFrom')?.value     || '';
  const to     = document.getElementById('logTo')?.value       || '';
  const tbody  = document.getElementById('logsBody');
  const count  = document.getElementById('logCount');

  if (tbody) tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:28px;color:#bbb">🔍 Searching…</td></tr>`;

  try {
    const res  = await fetch(`search.php?type=logs&q=${encodeURIComponent(q)}&period=${period}&from=${from}&to=${to}`);
    const data = await res.json();

    if (count) count.textContent = data.count;

    if (!data.rows || data.rows.length === 0) {
      tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:36px;color:#bbb;font-size:15px">No records found.</td></tr>`;
      return;
    }

    tbody.innerHTML = data.rows.map(r => `
      <tr>
        <td style="font-weight:600;color:#1a1a2e">${esc(r.name)}</td>
        <td style="font-size:13px;color:#555">${esc(r.email)}</td>
        <td style="font-family:monospace;font-size:13px">${esc(r.rfid)}</td>
        <td>${esc(r.year_level)}</td>
        <td>${esc(r.program)}</td>
        <td>${badge(r.type, r.type)}</td>
        <td>${esc(r.reason)}</td>
        <td style="color:#999;font-size:13px;white-space:nowrap">${esc(r.timestamp)}</td>
      </tr>`).join('');
  } catch(e) {
    tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:32px;color:#c0392b">Error loading data. Please try again.</td></tr>`;
  }
}

// ── Live Search: Visitors ─────────────────────────────────────────────────
let visitorTimer = null;
function liveSearchVisitors() {
  clearTimeout(visitorTimer);
  visitorTimer = setTimeout(fetchVisitors, 250);
}

async function fetchVisitors() {
  const q     = document.getElementById('visitorSearch')?.value || '';
  const tbody = document.getElementById('visitorsBody');

  if (tbody) tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:28px;color:#bbb">🔍 Searching…</td></tr>`;

  try {
    const res  = await fetch(`search.php?type=visitors&q=${encodeURIComponent(q)}`);
    const data = await res.json();

    if (!data.rows || data.rows.length === 0) {
      tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:36px;color:#bbb;font-size:15px">No visitors found.</td></tr>`;
      return;
    }

    tbody.innerHTML = data.rows.map(v => `
      <tr style="background:${v.blocked ? '#fff8f8' : 'transparent'}">
        <td style="font-family:monospace;font-size:13px;color:#555">${esc(v.rfid) || '—'}</td>
        <td style="font-weight:600;color:#1a1a2e">${esc(v.name)}</td>
        <td style="font-size:13px;color:#666">${esc(v.email)}</td>
        <td>${esc(v.year_level)}</td>
        <td>${esc(v.program)}</td>
        <td>${badge(v.type, v.type)}</td>
        <td>${badge(v.blocked ? 'Blocked' : 'Active', v.blocked ? 'blocked' : 'active')}</td>
        <td>
          <form method="POST" style="display:inline"
            onsubmit="return confirm('${v.blocked ? 'Unblock' : 'Block'} ${v.name.replace(/'/g,"\\'")}?')">
            <input type="hidden" name="visitor_id" value="${v.id}"/>
            <input type="hidden" name="current_blocked" value="${v.blocked ? 1 : 0}"/>
            <button type="submit" name="toggle_block" value="1"
              style="padding:6px 16px;border-radius:7px;border:none;cursor:pointer;
                     font-size:12px;font-weight:700;font-family:Georgia,serif;
                     background:${v.blocked ? 'rgba(13,124,95,.12)' : 'rgba(192,57,43,.1)'};
                     color:${v.blocked ? '#0d7c5f' : '#c0392b'}">
              ${v.blocked ? 'Unblock' : 'Block'}
            </button>
          </form>
        </td>
      </tr>`).join('');
  } catch(e) {
    tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:32px;color:#c0392b">Error loading data.</td></tr>`;
  }
}

// ── Export PDF ─────────────────────────────────────────────────────────────
async function exportPDF() {
  const q      = document.getElementById('logSearch')?.value   || '';
  const period = document.getElementById('logPeriod')?.value   || 'today';
  const from   = document.getElementById('logFrom')?.value     || '';
  const to     = document.getElementById('logTo')?.value       || '';
  window.open(`admin.php?tab=logs&export_pdf=${encodeURIComponent(period)}&search=${encodeURIComponent(q)}&from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`, '_blank');
}

// ── Escape HTML ────────────────────────────────────────────────────────────
function esc(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Auto-load on page load ─────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const tab = new URLSearchParams(window.location.search).get('tab');
  if (tab === 'logs')     fetchLogs();
  if (tab === 'visitors') fetchVisitors();
});
</script>
</body>
</html>