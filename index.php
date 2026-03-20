<?php
session_start();
require 'db.php';

$error   = '';
$step    = $_SESSION['step']             ?? 1;
$current = $_SESSION['current_visitor'] ?? null;
$is_returning = !empty($current); // true = existing visitor in DB

// ── Google Sign-In handler ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['google_token'])) {
    $token     = $_POST['google_token'];
    $client_id = '899546280282-1huled52cl4b5a9gaphmk4rtlk1vslgb.apps.googleusercontent.com';

    $response = @file_get_contents("https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($token));
    $payload  = json_decode($response, true);

    if (!$payload || ($payload['aud'] ?? '') !== $client_id) {
        $error = "Google Sign-In failed. Please try again.";
    } else {
        $google_email = strtolower(trim($payload['email'] ?? ''));
        $google_name  = $payload['name'] ?? '';

        if (!preg_match('/^[^\s@]+@neu\.edu\.ph$/i', $google_email)) {
            $error = "⛔ Only @neu.edu.ph Google accounts are allowed.";
        } else {
            $stmt = $conn->prepare("SELECT * FROM visitors WHERE email=?");
            $stmt->bind_param("s", $google_email);
            $stmt->execute();
            $visitor = $stmt->get_result()->fetch_assoc();

            if ($visitor && $visitor['blocked']) {
                $error = "⛔ Access Denied. {$visitor['name']} is not allowed to use the library.";
            } else {
                $_SESSION['rfid_input']      = $google_email;
                $_SESSION['google_name']     = $google_name;
                $_SESSION['google_email']    = $google_email;
                $_SESSION['current_visitor'] = $visitor ?: null;
                $_SESSION['step']            = 2;
                header("Location: index.php"); exit;
            }
        }
    }
}

// ── STEP 1: RFID or Email ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rfid_input'])) {
    $input        = trim($_POST['rfid_input']);
    $rfid_pattern = '/^\d{2}-\d{5}-\d{3}$/';
    $email_pat    = '/^[^\s@]+@neu\.edu\.ph$/i';

    $stmt = $conn->prepare("SELECT * FROM visitors WHERE rfid=? OR email=?");
    $stmt->bind_param("ss", $input, $input);
    $stmt->execute();
    $visitor = $stmt->get_result()->fetch_assoc();

    if ($visitor && $visitor['blocked']) {
        $error = "⛔ Access Denied. {$visitor['name']} is not allowed to use the library.";
    } elseif ($visitor) {
        // RETURNING visitor — go to step 2 (purpose only)
        $_SESSION['current_visitor'] = $visitor;
        $_SESSION['rfid_input']      = $input;
        $_SESSION['step']            = 2;
        header("Location: index.php"); exit;
    } elseif (!preg_match($rfid_pattern, $input) && !preg_match($email_pat, $input)) {
        $error = "Invalid input. Use RFID format 12-34567-890 or a @neu.edu.ph email.";
    } else {
        // NEW visitor — go to step 2 (full details)
        $_SESSION['rfid_input'] = $input;
        $_SESSION['is_new']     = true;
        $_SESSION['step']       = 2;
        header("Location: index.php"); exit;
    }
}

// ── STEP 2: Save entry ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_entry'])) {
    $rfid_in = $_SESSION['rfid_input'] ?? '';
    $rfid    = preg_match('/^\d{2}-\d{5}-\d{3}$/', $rfid_in) ? $rfid_in : '';
    $reason  = trim($_POST['reason'] ?? '');

    if ($is_returning) {
        // ── RETURNING: only need reason ───────────────────────────
        if (!$reason) {
            $error = "Please enter your reason for visiting.";
            $step  = 2;
        } else {
            $vid   = $current['id'];
            $name  = $current['name'];
            $email = $current['email'];
            $yr    = $current['year_level'];
            $prog  = $current['program'];
            $type  = $current['type'];

            $stmt = $conn->prepare("INSERT INTO visitor_logs (visitor_id,name,email,rfid,year_level,program,type,reason) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param("isssssss", $vid, $name, $email, $rfid, $yr, $prog, $type, $reason);
            $stmt->execute();

            $_SESSION['welcome_name']    = $name;
            $_SESSION['welcome_program'] = "$yr – $prog";
            $_SESSION['welcome_reason']  = $reason;
            unset($_SESSION['step'], $_SESSION['current_visitor'], $_SESSION['rfid_input'],
                  $_SESSION['is_new'], $_SESSION['google_name'], $_SESSION['google_email']);
            header("Location: welcome.php"); exit;
        }
    } else {
        // ── NEW: need full details + reason ───────────────────────
        $name     = trim($_POST['name']       ?? '');
        $email    = trim($_POST['email']      ?? '');
        $year_lvl = trim($_POST['year_level'] ?? '');
        $program  = trim($_POST['program']    ?? '');
        $no_prog  = in_array($year_lvl, ['Integrated School','Graduate','Faculty','Staff']);
        $program  = $no_prog ? 'N/A' : $program;
        $type     = in_array($year_lvl, ['Faculty','Staff']) ? strtolower($year_lvl) : 'student';
        $email_val = filter_var($rfid_in, FILTER_VALIDATE_EMAIL) ? $rfid_in : $email;

        if (!$name)                     $error = "Please enter your full name.";
        elseif (!$year_lvl)             $error = "Please select your year level.";
        elseif (!$no_prog && !$program) $error = "Please enter your program.";
        elseif (!$reason)               $error = "Please enter your reason for visiting.";
        else {
            $stmt = $conn->prepare("INSERT INTO visitors (rfid,name,email,year_level,program,type) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("ssssss", $rfid, $name, $email_val, $year_lvl, $program, $type);
            $stmt->execute();
            $vid = $conn->insert_id;

            $stmt = $conn->prepare("INSERT INTO visitor_logs (visitor_id,name,email,rfid,year_level,program,type,reason) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param("isssssss", $vid, $name, $email_val, $rfid, $year_lvl, $program, $type, $reason);
            $stmt->execute();

            $_SESSION['welcome_name']    = $name;
            $_SESSION['welcome_program'] = "$year_lvl – $program";
            $_SESSION['welcome_reason']  = $reason;
            unset($_SESSION['step'], $_SESSION['current_visitor'], $_SESSION['rfid_input'],
                  $_SESSION['is_new'], $_SESSION['google_name'], $_SESSION['google_email']);
            header("Location: welcome.php"); exit;
        }
        $step = 2;
    }
}

if (isset($_GET['back'])) { session_unset(); header("Location: index.php"); exit; }

$rfid_display  = $_SESSION['rfid_input']   ?? '';
$prefill_email = $_SESSION['google_email'] ?? (filter_var($rfid_display, FILTER_VALIDATE_EMAIL) ? $rfid_display : ($current['email'] ?? ''));
$prefill_name  = $_SESSION['google_name']  ?? ($current['name'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>NEU Library – Visitor Log</title>
<script src="https://accounts.google.com/gsi/client" async defer></script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Georgia,serif;min-height:100vh;background:#060b14;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  position:relative;overflow:hidden}
.bg-grid{position:absolute;inset:0;pointer-events:none;
  background-image:linear-gradient(rgba(26,115,232,.05) 1px,transparent 1px),
  linear-gradient(90deg,rgba(26,115,232,.05) 1px,transparent 1px);background-size:48px 48px}
.orb{position:absolute;border-radius:50%;pointer-events:none;filter:blur(60px)}
.orb1{width:500px;height:500px;top:-150px;left:-150px;background:rgba(26,115,232,.12)}
.orb2{width:380px;height:380px;bottom:-100px;right:-100px;background:rgba(13,124,95,.09)}
.header{text-align:center;margin-bottom:32px;z-index:1}
.logo{width:74px;height:74px;border-radius:18px;background:linear-gradient(135deg,#1a73e8,#0d3b66);
  display:flex;align-items:center;justify-content:center;margin:0 auto 16px;
  font-size:34px;box-shadow:0 10px 36px rgba(26,115,232,.5)}
h1{color:#fff;font-size:28px;font-weight:700;letter-spacing:-.5px}
.subtitle{color:#4a6080;margin:6px 0 0;font-size:12px;letter-spacing:3px;text-transform:uppercase}
.card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.09);
  border-radius:22px;padding:36px 40px;width:460px;z-index:1;
  box-shadow:0 28px 70px rgba(0,0,0,.5)}
.intro{color:#4a7090;font-size:13px;text-align:center;margin-bottom:20px;line-height:1.7}
label{color:#4a6080;font-size:11px;font-weight:700;letter-spacing:1.8px;
  text-transform:uppercase;display:block;margin-bottom:7px}
input,select{width:100%;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.13);
  border-radius:11px;color:#fff;padding:12px 15px;font-size:14px;outline:none;
  font-family:Georgia,serif;margin-bottom:15px;transition:border .2s}
input:focus,select:focus{border-color:rgba(26,115,232,.6)}
input::placeholder{color:#3a5070}
select{background:rgba(12,20,36,.95);cursor:pointer}
select option{background:#0d1a2e;color:#fff}
.na-box{width:100%;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);
  border-radius:11px;color:#4a6080;padding:12px 15px;font-size:14px;font-style:italic;margin-bottom:15px}
.row2{display:flex;gap:10px}
.row2>div{flex:1}.row2>div:last-child{flex:2}
.row2 input,.row2 select{margin-bottom:0}
.btn{width:100%;padding:13px;border:none;border-radius:11px;font-size:15px;
  font-weight:700;cursor:pointer;font-family:Georgia,serif;margin-top:6px;transition:all .2s}
.btn-blue{background:linear-gradient(135deg,#1a73e8,#0d52c4);color:#fff;
  box-shadow:0 5px 20px rgba(26,115,232,.45)}
.btn-blue:hover{transform:translateY(-1px)}
.btn-green{background:linear-gradient(135deg,#0d7c5f,#0a6050);color:#fff;
  box-shadow:0 5px 20px rgba(13,124,95,.4)}
.btn-green:hover{transform:translateY(-1px)}
.btn-back{width:100%;margin-top:10px;padding:10px;background:transparent;
  color:#4a6080;border:1px solid rgba(255,255,255,.09);border-radius:10px;
  font-size:13px;cursor:pointer;font-family:Georgia,serif;display:block;text-align:center;text-decoration:none}
.error{background:rgba(192,57,43,.15);border:1px solid rgba(192,57,43,.35);
  border-radius:10px;padding:12px 15px;margin-bottom:16px;color:#ff9090;font-size:13px;line-height:1.5}
.rfid-box{background:rgba(26,115,232,.1);border:1px solid rgba(26,115,232,.25);
  border-radius:12px;padding:13px 17px;margin-bottom:18px}
.rfid-box .lbl{color:#5a9fff;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:4px;font-weight:600}
.rfid-box .val{color:#fff;font-weight:700;font-size:15px;font-family:monospace}
/* Returning visitor card */
.visitor-info{background:rgba(13,124,95,.1);border:1px solid rgba(13,124,95,.3);
  border-radius:12px;padding:14px 17px;margin-bottom:14px}
.visitor-info .vi-name{color:#fff;font-weight:700;font-size:17px;margin-bottom:4px}
.visitor-info .vi-detail{color:#5affb0;font-size:13px}
.welcome-back{color:#5affb0;font-size:13px;text-align:center;margin-bottom:18px;
  background:rgba(13,124,95,.08);border-radius:8px;padding:10px;
  border:1px solid rgba(13,124,95,.2);line-height:1.5}
.chips{display:flex;flex-wrap:wrap;gap:7px;margin:9px 0 15px}
.chip{padding:5px 14px;font-size:12px;font-weight:600;border-radius:20px;cursor:pointer;
  border:1px solid rgba(26,115,232,.35);background:rgba(255,255,255,.04);
  color:#5a8bbf;transition:all .15s;font-family:Georgia,serif}
.chip:hover,.chip.active{background:rgba(26,115,232,.25);color:#fff;border-color:rgba(26,115,232,.7)}
.divider{display:flex;align-items:center;gap:12px;margin:18px 0}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:rgba(255,255,255,.1)}
.divider span{color:#3a5070;font-size:12px;white-space:nowrap}
.google-wrap{display:flex;justify-content:center;width:100%}
.admin-link{margin-top:22px;color:#1e2e3e;font-size:11px;cursor:pointer;
  letter-spacing:2px;text-transform:uppercase;z-index:1;text-decoration:none;display:block;text-align:center}
</style>
</head>
<body>
<div class="bg-grid"></div>
<div class="orb orb1"></div>
<div class="orb orb2"></div>

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
  <!-- ══ STEP 1: Login ══════════════════════════════════════════ -->
    <p class="intro">Sign in with your NEU Google account or enter your RFID</p>

    <div id="g_id_onload"
      data-client_id="899546280282-1huled52cl4b5a9gaphmk4rtlk1vslgb.apps.googleusercontent.com"
      data-callback="handleGoogleSignIn"
      data-auto_prompt="false">
    </div>
    <div class="google-wrap">
      <div class="g_id_signin"
        data-type="standard" data-size="large" data-theme="outline"
        data-text="signin_with" data-shape="rectangular"
        data-logo_alignment="left" data-width="380">
      </div>
    </div>

    <div class="divider"><span>or enter manually</span></div>

    <form method="POST">
      <label>RFID Number / Email</label>
      <input type="text" name="rfid_input"
        placeholder="e.g. 12-34567-890 or name@neu.edu.ph"
        style="font-family:monospace;letter-spacing:.8px"/>
      <button type="submit" class="btn btn-blue">Continue →</button>
    </form>

  <?php elseif ($step == 2 && $is_returning): ?>
  <!-- ══ STEP 2A: RETURNING VISITOR — Purpose only ══════════════ -->
    <div class="rfid-box">
      <div class="lbl">Logged in as</div>
      <div class="val"><?= htmlspecialchars($rfid_display) ?></div>
    </div>

    <div class="visitor-info">
      <div class="vi-name">👋 <?= htmlspecialchars($current['name']) ?></div>
      <div class="vi-detail">
        <?= htmlspecialchars($current['year_level']) ?>
        <?= ($current['program'] !== 'N/A') ? ' — ' . htmlspecialchars($current['program']) : '' ?>
      </div>
    </div>

    <p class="welcome-back">
      ✅ Welcome back! Your details are on file.<br/>
      Just tell us your purpose for today's visit.
    </p>

    <form method="POST">
      <label>Purpose of Visit</label>
      <input type="text" name="reason" id="reasonInput"
        placeholder="Type or pick below…" required autofocus/>
      <div class="chips">
        <?php foreach(['Reading','Researching','Use of computer','Meeting','Borrowing books','Study group'] as $r): ?>
          <button type="button" class="chip" onclick="pickReason('<?= $r ?>',this)"><?= $r ?></button>
        <?php endforeach; ?>
      </div>
      <input type="hidden" name="submit_entry" value="1"/>
      <button type="submit" class="btn btn-green">✓ Enter Library</button>
    </form>
    <a href="index.php?back=1" class="btn-back">← Back</a>

  <?php else: ?>
  <!-- ══ STEP 2B: NEW VISITOR — Full details ════════════════════ -->
    <div class="rfid-box">
      <div class="lbl">First time? Please fill in your details</div>
      <div class="val"><?= htmlspecialchars($rfid_display) ?></div>
    </div>

    <form method="POST">
      <label>Email Address</label>
      <input type="email" name="email" placeholder="name@neu.edu.ph"
        value="<?= htmlspecialchars($prefill_email) ?>"/>

      <label>Full Name</label>
      <input type="text" name="name" autofocus required placeholder="e.g. Juan dela Cruz"
        value="<?= htmlspecialchars($prefill_name) ?>"/>

      <div class="row2" style="margin-bottom:15px">
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
          <input type="text" name="program" id="progInput" placeholder="e.g. BSIT, BSCS…"/>
        </div>
      </div>

      <label>Purpose of Visit</label>
      <input type="text" name="reason" id="reasonInput" placeholder="Type or pick below…" required/>
      <div class="chips">
        <?php foreach(['Reading','Researching','Use of computer','Meeting','Borrowing books','Study group'] as $r): ?>
          <button type="button" class="chip" onclick="pickReason('<?= $r ?>',this)"><?= $r ?></button>
        <?php endforeach; ?>
      </div>

      <input type="hidden" name="submit_entry" value="1"/>
      <button type="submit" class="btn btn-green">✓ Enter Library</button>
    </form>
    <a href="index.php?back=1" class="btn-back">← Back</a>
  <?php endif; ?>
</div>

<a href="admin.php" class="admin-link">⚙ Admin Access</a>

<form id="googleForm" method="POST" style="display:none">
  <input type="hidden" name="google_token" id="googleToken"/>
</form>

<script>
function handleGoogleSignIn(response) {
  document.getElementById('googleToken').value = response.credential;
  document.getElementById('googleForm').submit();
}
const NO_PROG = ['Integrated School','Graduate','Faculty','Staff'];
function handleYearLevel(val) {
  const na = document.getElementById('prog-na');
  const pi = document.getElementById('progInput');
  if (!na || !pi) return;
  if (NO_PROG.includes(val)) { na.style.display='block'; pi.style.display='none'; pi.value=''; }
  else { na.style.display='none'; pi.style.display='block'; }
}
function pickReason(r, el) {
  document.getElementById('reasonInput').value = r;
  document.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
  el.classList.add('active');
}
</script>
</body>
</html>