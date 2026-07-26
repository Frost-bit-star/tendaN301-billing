<?php
$port = intval($_GET['port'] ?? 7681);
$routerId = intval($_GET['router'] ?? 0);

if ($port < 7681 || $port > 7999 || $routerId < 1) {
    http_response_code(400);
    exit('Invalid request');
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SSH Terminal</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
html,body{height:100%;overflow:hidden;background:#1a1a2e}
iframe{width:100%;height:100%;border:none}
.loading{display:flex;align-items:center;justify-content:center;height:100%;color:#e0e0e0;font-family:monospace;font-size:14px}
</style>
</head>
<body>
<div id="loading" class="loading">Connecting to router...</div>
<iframe id="term" style="display:none" src="http://127.0.0.1:<?= $port ?>/"></iframe>
<script>
document.getElementById('term').onload = function() {
    document.getElementById('loading').style.display = 'none';
    this.style.display = 'block';
};
setTimeout(function() {
    document.getElementById('loading').innerHTML = '<span style="color:#ff6b6b">Connection failed. Router may be offline.</span>';
}, 10000);
</script>
</body>
</html>
