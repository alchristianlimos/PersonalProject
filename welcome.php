<?php
session_start();
date_default_timezone_set('Asia/Manila'); // Philippine Standard Time

if (empty($_SESSION['welcome_name'])) {
    header("Location: index.php"); exit;
}
$name    = $_SESSION['welcome_name'];
$program = $_SESSION['welcome_program'];
$reason  = $_SESSION['welcome_reason'];
$time    = date('F j, Y — g:i A'); // e.g. March 20, 2026 — 2:35 PM
unset($_SESSION['welcome_name'], $_SESSION['welcome_program'], $_SESSION['welcome_reason']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Welcome – NEU Library</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Georgia,serif;min-height:100vh;
  background:linear-gradient(135deg,#0d3b66 0%,#0a6050 100%);
  display:flex;flex-direction:column;align-items:center;
  justify-content:center;color:#fff;text-align:center;padding:20px}
.wave{font-size:90px;margin-bottom:24px;
  animation:pop .5s cubic-bezier(.36,.07,.19,.97) both}
h1{font-size:40px;font-weight:700;margin-bottom:10px}
.name{font-size:24px;color:rgba(255,255,255,.88);margin-bottom:6px;font-weight:600}
.prog{font-size:16px;color:rgba(255,255,255,.58);margin-bottom:24px}
.info-box{background:rgba(255,255,255,.12);border-radius:14px;
  padding:16px 36px;margin-bottom:14px;display:inline-block}
.info-box p{font-size:15px;color:rgba(255,255,255,.82);margin-bottom:4px}
.info-box .time{font-size:13px;color:rgba(255,255,255,.5);margin-top:6px}
.timer{color:rgba(255,255,255,.32);font-size:13px;letter-spacing:.5px;margin-top:24px}
@keyframes pop{0%{transform:scale(0)}80%{transform:scale(1.15)}100%{transform:scale(1)}}
</style>
</head>
<body>
<div class="wave">👋</div>
<h1>Welcome to NEU Library!</h1>
<p class="name"><?= htmlspecialchars($name) ?></p>
<p class="prog"><?= htmlspecialchars($program) ?></p>
<div class="info-box">
  <p>Purpose: <strong><?= htmlspecialchars($reason) ?></strong></p>
  <p class="time">🕐 <?= $time ?></p>
</div>
<p class="timer" id="timer">Returning in 5 seconds…</p>

<script>
let s = 5;
const t = document.getElementById('timer');
const iv = setInterval(() => {
  s--;
  t.textContent = `Returning in ${s} second${s !== 1 ? 's' : ''}…`;
  if (s <= 0) { clearInterval(iv); location.href = 'index.php'; }
}, 1000);
</script>
</body>
</html>