<?php
session_start();
require 'db.php';

$error = '';
$step = isset($_SESSION['step']) ? $_SESSION['step'] : 1;
$current = isset($_SESSION['current_visitor']) ? $_SESSION['current_visitor'] : null;

// ── STEP 1: Submit RFID or Email ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rfid_input'])) {
    $input = trim($_POST['rfid_input']);
    $rfid_pattern = '/^\d{2}-\d{5}-\d{3}$/';
    $email_pattern = '/^[^\s@]+@neu\.edu\.ph$/i';

    // Check existing visitor
    $stmt = $conn->prepare("SELECT * FROM visitors WHERE rfid=? OR email=?");
    $stmt->bind_param("ss", $input, $input);
    $stmt->execute();
    $visitor = $stmt->get_result()->fetch_assoc();

    if ($visitor && $visitor['blocked']) {
        $error = "⛔ Access Denied. {$visitor['name']} is not allowed to use the library.";
        $step = 1;
    } elseif ($visitor) {
        $_SESSION['current_visitor'] = $visitor;
        $_SESSION['rfid_input'] = $input;
        $_SESSION['step'] = 2;
        header("Location: index.php");
        exit;
    } elseif (!preg_match($rfid_pattern, $input) && !preg_match($email_pattern, $input)) {
        $error = "Invalid input. Use RFID format 12-34567-890 or a @neu.edu.ph email.";
    } else {
        // New visitor – save temporarily
        $_SESSION['rfid_input'] = $input;
        $_SESSION['is_new'] = true;
        $_SESSION['step'] = 2;
        header("Location: index.php");
        exit;
    }
}

// ── STEP 2: Submit Details + Reason ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_entry'])) {
    $name      = trim($_POST['name']);
    $email     = trim($_POST['email']);
    $year_lvl  = trim($_POST['year_level']);
    $program   = trim($_POST['program']);
    $reason    = trim($_POST['reason']);
    $no_prog   = in_array($year_lvl, ['Integrated School','Graduate','Faculty','Staff']);
    $program   = $no_prog ? 'N/A' : $program;
    $type      = in_array($year_lvl, ['Faculty','Staff']) ? strtolower($year_lvl) : 'student';
    $rfid_in   = $_SESSION['rfid_input'] ?? '';

    if (!$name)   { $error = "Please enter your full name."; }
    elseif (!$year_lvl) { $error = "Please select your year level."; }
    elseif (!$no_prog && !$program) { $error = "Please enter your program."; }
    elseif (!$reason) { $error = "Please enter your reason for visiting."; }
    else {
        // Upsert visitor
        $rfid  = preg_match('/^\d{2}-\d{5}-\d{3}$/', $rfid_in) ? $rfid_in : '';
        $email_val = filter_var($rfid_in, FILTER_VALIDATE_EMAIL) ? $rfid_in : $email;

        if (!empty($_SESSION['current_visitor'])) {
            $vid = $_SESSION['current_visitor']['id'];
            $stmt = $conn->prepare("UPDATE visitors SET name=?,email=?,year_level=?,program=?,type=? WHERE id=?");
            $stmt->bind_param("sssssi", $name, $email_val, $year_lvl, $program, $type, $vid);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("INSERT INTO visitors (rfid,name,email,year_level,program,type) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("ssssss", $rfid, $name, $email_val, $year_lvl, $program, $type);
            $stmt->execute();
            $vid = $conn->insert_id;
        }

        // Insert log
        $stmt = $conn->prepare("INSERT INTO visitor_logs (visitor_id,name,email,rfid,year_level,program,type,reason) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param("isssssss", $vid, $name, $email_val, $rfid, $year_lvl, $program, $type, $reason);
        $stmt->execute();

        $_SESSION['welcome_name']    = $name;
        $_SESSION['welcome_program'] = "$year_lvl – $program";
        $_SESSION['welcome_reason']  = $reason;

        // Clear session
        unset($_SESSION['step'], $_SESSION['current_visitor'], $_SESSION['rfid_input'], $_SESSION['is_new']);
        header("Location: welcome.php");
        exit;
    }
    $step = 2;
}

if (isset($_GET['back'])) {
    unset($_SESSION['step'], $_SESSION['current_visitor'], $_SESSION['rfid_input'], $_SESSION['is_new']);
    $step = 1;
    header("Location: index.php");
    exit;
}

$rfid_display = $_SESSION['rfid_input'] ?? '';
$prefill_email = (filter_var($rfid_display, FILTER_VALIDATE_EMAIL)) ? $rfid_display : ($current['email'] ?? '');
$prefill_name  = $current['name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>NEU Library – Visitor Log</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Georgia,serif;min-height:100vh;background:#0a0e1a;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  position:relative;overflow:hidden}
.grid-bg{position:absolute;inset:0;pointer-events:none;
  background-image:linear-gradient(rgba(26,115,232,.06) 1px,transparent 1px),
    linear-gradient(90deg,rgba(26,115,232,.06) 1px,transparent 1px);
  background-size:44px 44px}
.glow1{position:absolute;top:-180px;left:-180px;width:560px;height:560px;border-radius:50%;
  background:radial-gradient(circle,rgba(26,115,232,.13) 0%,transparent 70%);pointer-events:none}
.glow2{position:absolute;bottom:-120px;right:-120px;width:400px;height:400px;border-radius:50%;
  background:radial-gradient(circle,rgba(13,124,95,.1) 0%,transparent 70%);pointer-events:none}
.header{text-align:center;margin-bottom:32px;z-index:1;animation:fadeIn .6s ease}
.logo{width:72px;height:72px;border-radius:18px;background:linear-gradient(135deg,#1a73e8,#0d3b66);
  display:flex;align-items:center;justify-content:center;margin:0 auto 16px;
  font-size:34px;box-shadow:0 8px 32px rgba(26,115,232,.45)}
h1{color:#fff;font-size:28px;font-weight:700;letter-spacing:-.5px}
.subtitle{color:#607080;margin:6px 0 0;font-size:13px;letter-spacing:2.5px;text-transform:uppercase}
.card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);
  border-radius:22px;padding:36px 40px;width:440px;z-index:1;
  backdrop-filter:blur(16px);box-shadow:0 24px 64px rgba(0,0,0,.45);animation:fadeIn .5s ease}
label{color:#607080;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;display:block;margin-bottom:6px}
input,select{width:100%;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.15);
  border-radius:10px;color:#fff;padding:11px 14px;font-size:14px;outline:none;
  font-family:Georgia,serif;margin-bottom:13px}
select{background:rgba(20,30,50,.9)}
select option{background:#1a2535;color:#fff}
.btn-primary{width:100%;padding:13px;background:linear-gradient(135deg,#1a73e8,#0d52c4);
  color:#fff;border:none;border-radius:11px;font-size:15px;font-weight:700;cursor:pointer;
  box-shadow:0 4px 18px rgba(26,115,232,.45);font-family:Georgia,serif;margin-top:5px}
.btn-green{background:linear-gradient(135deg,#0d7c5f,#0a6050);box-shadow:0 4px 18px rgba(13,124,95,.4)}
.btn-back{width:100%;margin-top:10px;padding:10px;background:transparent;
  color:#607080;border:1px solid rgba(255,255,255,.09);border-radius:10px;
  font-size:13px;cursor:pointer;font-family:Georgia,serif;text-decoration:none;
  display:block;text-align:center}
.error{background:rgba(192,57,43,.15);border:1px solid rgba(192,57,43,.35);
  border-radius:9px;padding:11px 14px;margin-bottom:13px;color:#ff8888;font-size:13px;line-height:1.5}
.rfid-box{background:rgba(26,115,232,.1);border:1px solid rgba(26,115,232,.25);
  border-radius:11px;padding:13px 16px;margin-bottom:18px}
.rfid-box .lbl{color:#6aacff;font-size:12px;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px}
.rfid-box .val{color:#fff;font-weight:700;font-size:15px;font-family:monospace}
.row2{display:flex;gap:10px}
.row2>div{flex:1}
.row2>div:last-child{flex:2}
.na-box{width:100%;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);
  border-radius:10px;color:#607080;padding:11px 14px;font-size:14px;font-style:italic;margin-bottom:13px}
.chips{display:flex;flex-wrap:wrap;gap:7px;margin-top:9px;margin-bottom:13px}
.chip{padding:5px 13px;font-size:12px;border-radius:20px;cursor:pointer;
  border:1px solid rgba(26,115,232,.4);background:rgba(255,255,255,.05);
  color:#7aacff;font-family:Georgia,serif}
.chip.active{background:rgba(26,115,232,.3);color:#fff}
.admin-link{margin-top:24px;background:transparent;border:none;color:#2a3a4a;
  font-size:12px;cursor:pointer;letter-spacing:1.5px;text-transform:uppercase;
  z-index:1;font-family:Georgia,serif;text-decoration:none;display:block;text-align:center}
@keyframes fadeIn{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
</style>
</head>
<body>
<div class="grid-bg"></div>
<div class="glow1"></div>
<div class="glow2"></div>

<div class="header">
  <div class="logo">📚</div>
  <h1>NEU Library</h1>
  <p class="subtitle">Visitor Log System</p>
</div>

<div class="card">
<?php if ($error): ?>
  <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($step == 1): ?>
  <p style="color:#7a99bb;font-size:13px;text-align:center;margin-bottom:20px;line-height:1.6">
    Tap your RFID card or type your institutional email
  </p>
  <form method="POST">
    <label>RFID Number / Email</label>
    <input type="text" name="rfid_input" placeholder="e.g. 12-34567-890 or name@neu.edu.ph"
      style="font-family:monospace;letter-spacing:.8px" autofocus required/>
    <button type="submit" class="btn-primary">Continue →</button>
  </form>

<?php else: ?>
  <div class="rfid-box">
    <div class="lbl">Logged in as</div>
    <div class="val"><?= htmlspecialchars($rfid_display) ?></div>
  </div>
  <form method="POST">
    <label>Email Address</label>
    <input type="email" name="email" placeholder="e.g. name@neu.edu.ph"
      value="<?= htmlspecialchars($prefill_email) ?>"/>

    <label>Full Name</label>
    <input type="text" name="name" placeholder="e.g. Juan dela Cruz"
      value="<?= htmlspecialchars($prefill_name) ?>" autofocus required/>

    <div class="row2">
      <div>
        <label>Year Level</label>
        <select name="year_level" id="yearLevel" onchange="handleYearLevel(this.value)" required>
          <option value="" disabled selected>Select…</option>
          <option value="Integrated School">Integrated School</option>
          <option value="1st Year">1st Year</option>
          <option value="2nd Year">2nd Year</option>
          <option value="3rd Year">3rd Year</option>
          <option value="4th Year">4th Year</option>
          <option value="5th Year">5th Year</option>
          <option value="Irregular">Irregular</option>
          <option value="Graduate">Graduate</option>
          <option value="Faculty">Faculty</option>
          <option value="Staff">Staff</option>
        </select>
      </div>
      <div>
        <label>Program</label>
        <div id="prog-na" class="na-box" style="display:none">N/A</div>
        <input type="text" name="program" id="progInput" placeholder="e.g. BSIT, BSCS, BSA…"/>
      </div>
    </div>

    <label>Purpose of Visit</label>
    <input type="text" name="reason" id="reasonInput" placeholder="Type or pick below…"/>
    <div class="chips">
      <?php foreach(['Reading','Researching','Use of computer','Meeting','Borrowing books','Study group'] as $r): ?>
        <button type="button" class="chip" onclick="pickReason('<?= $r ?>')"><?= $r ?></button>
      <?php endforeach; ?>
    </div>

    <input type="hidden" name="submit_entry" value="1"/>
    <button type="submit" class="btn-primary btn-green">✓ Enter Library</button>
  </form>
  <a href="index.php?back=1" class="btn-back">← Back</a>
<?php endif; ?>
</div>

<a href="admin.php" class="admin-link">⚙ Admin Access</a>

<script>
const NO_PROG = ['Integrated School','Graduate','Faculty','Staff'];
function handleYearLevel(val) {
  const na = document.getElementById('prog-na');
  const pi = document.getElementById('progInput');
  if (NO_PROG.includes(val)) {
    na.style.display = 'block';
    pi.style.display = 'none';
    pi.value = '';
  } else {
    na.style.display = 'none';
    pi.style.display = 'block';
  }
}
function pickReason(r) {
  document.getElementById('reasonInput').value = r;
  document.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
  event.target.classList.add('active');
}
</script>
</body>
</html>
</parameter>
<parameter name="path">/mnt/user-data/outputs/neu_library/index.php</parameter>