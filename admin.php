<?php
session_start();
require 'db.php';

$admin_error = '';
$ADMIN_ACCOUNTS = [
    'jcesperanza@neu.edu.ph' => 'admin123',
];

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
    unset($_SESSION['admin_logged_in']);
    header("Location: admin.php"); exit;
}
$logged_in = !empty($_SESSION['admin_logged_in']);

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
    $from   = $conn->real_escape_string($_GET['from'] ?? '');
    $to     = $conn->real_escape_string($_GET['to']   ?? '');
    $search = $conn->real_escape_string($_GET['search'] ?? '');
    $where  = "1=1";
    if ($period==='today') $where .= " AND DATE(timestamp)=CURDATE()";
    elseif ($period==='week')  $where .= " AND timestamp>=DATE_SUB(NOW(),INTERVAL 7 DAY)";
    elseif ($period==='month') $where .= " AND MONTH(timestamp)=MONTH(NOW()) AND YEAR(timestamp)=YEAR(NOW())";
    elseif ($period==='range' && $from && $to) $where .= " AND DATE(timestamp) BETWEEN '$from' AND '$to'";
    if ($search) $where .= " AND (name LIKE '%$search%' OR program LIKE '%$search%' OR reason LIKE '%$search%')";
    $result = $conn->query("SELECT name,email,rfid,year_level,program,type,reason,timestamp FROM visitor_logs WHERE $where ORDER BY timestamp DESC");

    $rows_html = '';
    $count = 0;
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

    $period_labels = ['today'=>'Today','week'=>'This Week','month'=>'This Month','range'=>"$from to $to",'all'=>'All Time'];
    $period_label  = $period_labels[$period] ?? ucfirst($period);
    $generated     = date('F j, Y g:i A');

    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"/>
    <title>NEU Library – Visitor Logs</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Georgia, serif; padding: 40px; color: #1a1a2e; }
        .header { border-bottom: 3px solid #0d3b66; padding-bottom: 14px; margin-bottom: 16px; }
        .header h1 { color: #0d3b66; font-size: 22px; margin-bottom: 4px; }
        .header p  { color: #666; font-size: 13px; }
        .meta { display: flex; gap: 24px; margin-bottom: 20px; }
        .meta-box { background: #f0f4f8; border-radius: 8px; padding: 10px 16px; font-size: 13px; color: #555; }
        .meta-box span { color: #0d3b66; font-weight: 700; font-size: 18px; display: block; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th { background: #0d3b66; color: #fff; padding: 9px 10px; text-align: left;
             font-size: 10px; text-transform: uppercase; letter-spacing: .6px; }
        td { padding: 8px 10px; border-bottom: 1px solid #dde; }
        .footer { margin-top: 24px; color: #999; font-size: 12px; text-align: right; }
        @media print { body { padding: 20px; } .no-print { display: none; } }
    </style>
    </head><body>
    <div class="header">
        <h1>&#128218; NEU Library &ndash; Visitor Logs</h1>
        <p>New Era University &nbsp;|&nbsp; Generated: ' . $generated . '</p>
    </div>
    <div class="meta">
        <div class="meta-box"><span>' . $count . '</span>Total Records</div>
        <div class="meta-box"><span>' . $period_label . '</span>Period</div>
        <div class="meta-box"><span>' . date('F j, Y') . '</span>Date Printed</div>
    </div>
    <table>
        <thead>
            <tr>
                <th>Name</th><th>Email</th><th>RFID</th><th>Year Level</th>
                <th>Program</th><th>Type</th><th>Reason</th><th>Date &amp; Time</th>
            </tr>
        </thead>
        <tbody>' . ($rows_html ?: '<tr><td colspan="8" style="text-align:center;padding:24px;color:#aaa">No records found.</td></tr>') . '</tbody>
    </table>
    <div class="footer">NEU Library Visitor Log System &nbsp;|&nbsp; ' . $generated . '</div>
    <script>window.onload = function(){ window.print(); }<\/script>
    </body></html>';
    exit;
}

if ($logged_in) {
    $tab    = $_GET['tab']    ?? 'dashboard';
    $search = $_GET['search'] ?? '';
    $period = $_GET['period'] ?? 'today';
    $from   = $_GET['from']   ?? '';
    $to     = $_GET['to']     ?? '';

    $today_ct = $conn->query("SELECT COUNT(*) c FROM visitor_logs WHERE DATE(timestamp)=CURDATE()")->fetch_assoc()['c'];
    $week_ct  = $conn->query("SELECT COUNT(*) c FROM visitor_logs WHERE timestamp>=DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetch_assoc()['c'];
    $month_ct = $conn->query("SELECT COUNT(*) c FROM visitor_logs WHERE MONTH(timestamp)=MONTH(NOW()) AND YEAR(timestamp)=YEAR(NOW())")->fetch_assoc()['c'];
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
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Georgia,serif;background:#f0f4f8;min-height:100vh}
input,select,button,a{font-family:Georgia,serif}
/* Login */
.login-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0a0e1a;
  background-image:linear-gradient(rgba(26,115,232,.06) 1px,transparent 1px),linear-gradient(90deg,rgba(26,115,232,.06) 1px,transparent 1px);background-size:44px 44px}
.login-card{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);border-radius:20px;padding:40px;width:340px;box-shadow:0 24px 64px rgba(0,0,0,.5);text-align:center}
.login-card h2{color:#fff;margin-bottom:6px;font-size:20px}
.login-card p{color:#607080;font-size:13px;margin-bottom:24px}
.login-card input{width:100%;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.2);border-radius:9px;color:#fff;padding:12px 14px;font-size:14px;outline:none;margin-bottom:13px;font-family:Georgia,serif}
.login-card input::placeholder{color:#607080}
.login-card button{width:100%;padding:12px;background:linear-gradient(135deg,#1a73e8,#0d52c4);color:#fff;border:none;border-radius:9px;font-size:15px;font-weight:700;cursor:pointer}
.login-err{color:#ff8888;font-size:13px;margin-bottom:12px}
.back-link{display:block;margin-top:16px;color:#607080;font-size:13px;text-decoration:none}
/* Nav */
nav{background:#0d3b66;padding:0 24px;display:flex;align-items:center;height:60px;gap:4px;box-shadow:0 2px 14px rgba(0,0,0,.25);position:sticky;top:0;z-index:50}
.nav-brand{color:#fff;font-weight:700;font-size:17px;margin-right:auto}
nav a.nav-tab{padding:8px 16px;border-radius:8px;color:rgba(255,255,255,.6);text-decoration:none;font-size:14px}
nav a.nav-tab:hover{color:#fff;background:rgba(255,255,255,.1)}
nav a.nav-tab.active{background:rgba(255,255,255,.18);color:#fff;font-weight:700}
.logout-btn{margin-left:8px;padding:8px 16px;border-radius:8px;background:rgba(192,57,43,.22);border:1px solid rgba(192,57,43,.4);color:#ffaaaa;text-decoration:none;font-size:13px}
/* Layout */
.content{max-width:1140px;margin:0 auto;padding:28px 24px}
.page-title{color:#0d3b66;font-size:22px;margin-bottom:22px}
/* Cards */
.cards3{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:22px}
.stat-card{background:#fff;border-radius:16px;padding:24px 28px;box-shadow:0 2px 12px rgba(0,0,0,.07)}
.stat-card .icon{font-size:30px;margin-bottom:8px}
.stat-card .num{font-size:40px;font-weight:800;line-height:1.1}
.stat-card .lbl{color:#777;font-size:14px;margin-top:6px}
.mini-card{background:#fff;border-radius:14px;padding:20px 24px;box-shadow:0 2px 12px rgba(0,0,0,.07);text-align:center}
.mini-card .num{font-size:30px;font-weight:800}
.mini-card .lbl{color:#777;font-size:13px;margin-top:5px}
/* Panels & tables */
.panel{background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.07);overflow:hidden;margin-bottom:24px}
.panel-head{padding:16px 20px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #eee}
.panel-head strong{color:#0d3b66;font-size:15px}
.panel-head a{color:#1a73e8;font-size:13px;text-decoration:none}
.count-bar{padding:12px 20px;border-bottom:1px solid #eee;color:#888;font-size:13px}
.overflow{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:620px}
thead tr{background:#f8fafc;border-bottom:2px solid #eee}
th{text-align:left;padding:11px 16px;color:#999;font-size:11px;text-transform:uppercase;letter-spacing:.8px;white-space:nowrap}
td{padding:11px 16px;border-bottom:1px solid #f2f2f2;color:#555;font-size:14px;vertical-align:middle}
td.nc{color:#1a1a2e;font-weight:600}
tbody tr:hover td{background:#fafbff}
.no-rec td{text-align:center;padding:40px;color:#bbb;font-size:15px}
/* Filters */
.filters{background:#fff;border-radius:14px;padding:18px 20px;margin-bottom:20px;box-shadow:0 2px 12px rgba(0,0,0,.07);display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end}
.filters label{color:#999;font-size:11px;text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:6px}
.filters input[type=text],.filters input[type=date],.filters select{border:1px solid #dde;border-radius:8px;padding:9px 12px;font-size:14px;outline:none;color:#333;background:#fff}
.f-search{flex:2 1 180px}.f-search input{width:100%}
.f-period{flex:1 1 140px}.f-period select{width:100%}
.btn-p{padding:10px 20px;background:#0d3b66;color:#fff;border:none;border-radius:9px;cursor:pointer;font-size:13px;font-weight:700;text-decoration:none;display:inline-block}
.btn-p:hover{background:#0a2d52}
.btn-pdf{background:#c0392b}.btn-pdf:hover{background:#a93226}
.top-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
.block-btn{padding:6px 16px;border-radius:7px;border:none;cursor:pointer;font-size:12px;font-weight:700}
</style>
</head>
<body>

<?php if (!$logged_in): ?>
<div class="login-wrap">
  <div class="login-card">
    <div style="font-size:40px;margin-bottom:12px">🔒</div>
    <h2>Admin Login</h2>
    <p>NEU Library Management System</p>
    <?php if ($admin_error): ?><p class="login-err"><?= htmlspecialchars($admin_error) ?></p><?php endif; ?>
    <form method="POST">
      <input type="email" name="admin_email" placeholder="Admin email address" autofocus
        value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>"/>
      <input type="password" name="admin_password" placeholder="Password"/>
      <button type="submit">Login →</button>
    </form>
    <a href="index.php" class="back-link">← Back to Visitor Login</a>
  </div>
</div>

<?php else: ?>
<nav>
  <div class="nav-brand">📚 NEU Library Admin</div>
  <a href="admin.php?tab=dashboard" class="nav-tab <?= $tab==='dashboard'?'active':'' ?>">📊 Dashboard</a>
  <a href="admin.php?tab=logs"      class="nav-tab <?= $tab==='logs'     ?'active':'' ?>">📋 Visitor Logs</a>
  <a href="admin.php?tab=visitors"  class="nav-tab <?= $tab==='visitors' ?'active':'' ?>">👥 Visitors</a>
  <span style="color:rgba(255,255,255,.45);font-size:13px;margin-left:8px">
    <?= htmlspecialchars($_SESSION['admin_email'] ?? '') ?>
  </span>
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
<div class="cards3" style="grid-template-columns:repeat(5,1fr)">
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
  <a href="admin.php?tab=logs&export_pdf=<?= urlencode($period) ?>&search=<?= urlencode($search) ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
     class="btn-p btn-pdf" target="_blank">🖨️ Export PDF</a>
</div>
<form method="GET" action="admin.php">
  <input type="hidden" name="tab" value="logs"/>
  <div class="filters">
    <div class="f-search"><label>Search</label><input type="text" name="search" placeholder="Name, email, program, reason…" value="<?= htmlspecialchars($search) ?>"/></div>
    <div class="f-period"><label>Period</label>
      <select name="period" onchange="this.form.submit()">
        <option value="today" <?= $period==='today'?'selected':'' ?>>Today</option>
        <option value="week"  <?= $period==='week' ?'selected':'' ?>>This Week</option>
        <option value="month" <?= $period==='month'?'selected':'' ?>>This Month</option>
        <option value="range" <?= $period==='range'?'selected':'' ?>>Custom Range</option>
        <option value="all"   <?= $period==='all'  ?'selected':'' ?>>All Time</option>
      </select>
    </div>
    <?php if ($period==='range'): ?>
    <div><label>From</label><input type="date" name="from" value="<?= htmlspecialchars($from) ?>"/></div>
    <div><label>To</label><input type="date" name="to" value="<?= htmlspecialchars($to) ?>"/></div>
    <?php endif; ?>
    <div style="padding-bottom:1px"><button type="submit" class="btn-p">🔍 Search</button></div>
  </div>
</form>
<div class="panel">
  <div class="count-bar">Showing <strong style="color:#0d3b66"><?= $logs->num_rows ?></strong> record<?= $logs->num_rows!==1?'s':'' ?></div>
  <div class="overflow"><table>
    <thead><tr><th>Name</th><th>Email</th><th>RFID</th><th>Year Level</th><th>Program</th><th>Type</th><th>Reason</th><th>Date &amp; Time</th></tr></thead>
    <tbody>
    <?php if ($logs->num_rows===0): ?><tr class="no-rec"><td colspan="8">No records found.</td></tr>
    <?php else: while ($r=$logs->fetch_assoc()): ?>
    <tr>
      <td class="nc"><?= htmlspecialchars($r['name']) ?></td>
      <td style="font-size:13px"><?= htmlspecialchars($r['email']) ?></td>
      <td style="font-family:monospace;font-size:13px"><?= htmlspecialchars($r['rfid']) ?></td>
      <td><?= htmlspecialchars($r['year_level']) ?></td>
      <td><?= htmlspecialchars($r['program']) ?></td>
      <td><?= badge($r['type'],$r['type']) ?></td>
      <td><?= htmlspecialchars($r['reason']) ?></td>
      <td style="color:#999;font-size:13px;white-space:nowrap"><?= date('M j, Y g:i A',strtotime($r['timestamp'])) ?></td>
    </tr>
    <?php endwhile; endif; ?>
    </tbody>
  </table></div>
</div>

<?php elseif ($tab==='visitors'): ?>
<h2 class="page-title">Visitor Management</h2>
<div class="panel">
  <div class="overflow"><table>
    <thead><tr><th>RFID</th><th>Name</th><th>Email</th><th>Year Level</th><th>Program</th><th>Type</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
    <?php if ($all_visitors->num_rows===0): ?><tr class="no-rec"><td colspan="8">No visitors yet.</td></tr>
    <?php else: while ($v=$all_visitors->fetch_assoc()): ?>
    <tr style="background:<?= $v['blocked']?'#fff8f8':'transparent' ?>">
      <td style="font-family:monospace;font-size:13px;color:#555"><?= htmlspecialchars($v['rfid'])?:'-' ?></td>
      <td class="nc"><?= htmlspecialchars($v['name']) ?></td>
      <td style="font-size:13px;color:#666"><?= htmlspecialchars($v['email']) ?></td>
      <td><?= htmlspecialchars($v['year_level']) ?></td>
      <td><?= htmlspecialchars($v['program']) ?></td>
      <td><?= badge($v['type'],$v['type']) ?></td>
      <td><?= badge($v['blocked']?'Blocked':'Active',$v['blocked']?'blocked':'active') ?></td>
      <td>
        <form method="POST" style="display:inline" onsubmit="return confirm('<?= $v['blocked']?'Unblock':'Block' ?> <?= addslashes($v['name']) ?>?')">
          <input type="hidden" name="visitor_id" value="<?= $v['id'] ?>"/>
          <input type="hidden" name="current_blocked" value="<?= $v['blocked'] ?>"/>
          <button type="submit" name="toggle_block" value="1" class="block-btn"
            style="background:<?= $v['blocked']?'rgba(13,124,95,.12)':'rgba(192,57,43,.1)' ?>;color:<?= $v['blocked']?'#0d7c5f':'#c0392b' ?>">
            <?= $v['blocked']?'Unblock':'Block' ?>
          </button>
        </form>
      </td>
    </tr>
    <?php endwhile; endif; ?>
    </tbody>
  </table></div>
</div>

<?php endif; ?>
</div>
<?php endif; ?>
</body>
</html>