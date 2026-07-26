<?php
$deviceId = $_GET['device'] ?? '';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Jasiri WiFi</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:linear-gradient(135deg,#667eea,#764ba2);min-height:100vh;display:flex;align-items:center;justify-content:center}
.card{background:#fff;border-radius:20px;padding:32px;max-width:380px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.3);text-align:center}
h1{font-size:24px;color:#333;margin-bottom:4px}
.sub{color:#888;font-size:13px;margin-bottom:20px}
.icon{font-size:40px;margin-bottom:10px}
label{display:block;text-align:left;font-size:12px;color:#666;margin-bottom:4px;font-weight:500}
input[type=text],input[type=password]{width:100%;padding:12px;border:2px solid #e0e0e0;border-radius:10px;font-size:15px;outline:none;margin-bottom:14px;transition:border .2s}
input:focus{border-color:#667eea}
.btn{width:100%;padding:12px;border:none;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;color:#fff;margin-bottom:10px;transition:transform .2s}
.btn:hover{transform:translateY(-1px)}
.btn-connect{background:linear-gradient(135deg,#667eea,#764ba2)}
.btn-voucher{background:transparent;color:#667eea;border:2px solid #667eea}
.voucher-link{margin-top:10px;font-size:13px}
.voucher-link a{color:#667eea;text-decoration:none;font-weight:500}
</style>
</head>
<body>
<div class="card">
    <div class="icon">📶</div>
    <h1>Jasiri WiFi</h1>
    <p class="sub">Sign in to access the internet</p>
    <form name="login" action="/login" method="post">
        <label>Username</label>
        <input type="text" name="username" placeholder="Voucher code" required>
        <label>Password</label>
        <input type="password" name="password" placeholder="Voucher code" required>
        <input type="hidden" name="dst" value="">
        <button type="submit" class="btn btn-connect">Connect</button>
    </form>
    <div class="voucher-link">
        <p>Don't have a voucher? <a href="https://jasiri.stackverify.site/captive?router=<?= htmlspecialchars($deviceId) ?>">Get one here</a></p>
    </div>
</div>
<script>
var params = new URLSearchParams(window.location.search);
document.querySelector('input[name=dst]').value = params.get('dst') || '';
</script>
</body>
</html>
