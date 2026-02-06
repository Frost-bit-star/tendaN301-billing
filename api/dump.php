<?php
// Correct URL
$url = "http://192.168.0.1/js/libs/j.js";

// Local file path
$saveTo = __DIR__ . "/j.js";

// Step 1: Download
$ch = curl_init($url);

// Make it behave like a browser
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

// Browser headers
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Accept: text/javascript, application/javascript, */*; q=0.01",
    "Accept-Language: en-US,en;q=0.9",
    "Connection: keep-alive"
]);

// Fake a common browser user-agent
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36");

// Optionally, send Referer (some routers check it)
curl_setopt($ch, CURLOPT_REFERER, "http://192.168.0.1/");

// Execute cURL
$data = curl_exec($ch);

if(curl_errno($ch)) {
    die("cURL error: " . curl_error($ch));
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($httpCode !== 200) {
    die("Failed to download, HTTP status: $httpCode");
}

// Save the file
file_put_contents($saveTo, $data);
echo "File downloaded successfully to $saveTo\n";

curl_close($ch);

// Step 2: Run Prettier to clean the file
$cmd = "npx prettier --write " . escapeshellarg($saveTo);
exec($cmd, $output, $return_var);

if ($return_var === 0) {
    echo "File cleaned successfully with Prettier!\n";
} else {
    echo "Prettier failed. Output:\n";
    print_r($output);
}
