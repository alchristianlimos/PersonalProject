<?php
session_start();
if (empty($_SESSION['welcome_name'])) { header("Location: index.php"); exit; }
$name    = $_SESSION['welcome_name'];
$program = $_SESSION['welcome_program'];
$reason  = $_SESSION['welcome_reason'];
unset($_SESSION['welcome_name'], $_SESSION['welcome_program'], $_SESSION['welcome_reason']);
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"/><title>Welcome</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Georgia,serif;min-height:100vh;background:linear-gradient(135deg,#0d3b66,#0a6050);
  display:flex;flex-direction:column;align-items:center;justify-content:center;color:#fff;text-align:center;padding:20px}
.wave{font-size:90px;margin-bottom:24px;animation:pop .5s cubic-bezier(.36,.07,.19,.97)}
@keyframes pop{0%{transform:scale(0)}80%{transform:scale(1.15)}100%{transform:scale(1)}}
</style></head>
<body>
<div class="wave">👋</div>
<h1 style="font-size:38px;margin-bottom:10px">Welcome to NEU Library!</h1>
<p style="font-size:22px;color:rgba(255,255,255,.88);margin-bottom:6px"><?= htmlspecialchars($name) ?></p>
<p style="font-size:16px;color:rgba(255,255,255,.6);margin-bottom:28px"><?= htmlspecialchars($program) ?></p>
<div style="background:rgba(255,255,255,.12);border-radius:12px;padding:14px 36px;font-size:15px;margin-bottom:36px">
  Purpose: <strong><?= htmlspecialchars($reason) ?></strong>
</div>
<p id="t" style="color:rgba(255,255,255,.35);font-size:13px">Returning in 5 seconds…</p>
<script>
let s=5; const t=document.getElementById('t');
setInterval(()=>{s--;t.textContent=`Returning in ${s} second${s!==1?'s':''}…`;if(s<=0)location='index.php';},1000);
</script>
</body></html>