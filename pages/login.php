<?php
// login.php

if (session_status() == PHP_SESSION_NONE) session_start();

$dbPath = __DIR__ . '/../db/routers.db';
if (!file_exists($dbPath)) {
    file_put_contents($dbPath, '');
}

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Ensure schema exists (runs CREATE TABLE IF NOT EXISTS, safe to call multiple times)
require_once __DIR__ . '/../db/schema.php';

// -------------------------
// Handle account registration
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$name || !$email || !$password) {
        $regMessage = 'All fields are required.';
    } elseif (strlen($password) < 4) {
        $regMessage = 'Password must be at least 4 characters.';
    } else {
        $check = $db->prepare("SELECT id FROM accounts WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $regMessage = 'Email already registered.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO accounts (name, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$name, $email, $hashed]);
            $regMessage = 'Account created! You can now login.';
        }
    }
}

// -------------------------
// Handle account login
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'account_login') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $db->prepare("SELECT * FROM accounts WHERE email = ?");
    $stmt->execute([$email]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($account && password_verify($password, $account['password'])) {
        $_SESSION['logged_in'] = true;
        $_SESSION['username']  = $account['name'];
        $_SESSION['role']      = 'user';
        $_SESSION['account_id'] = $account['id'];
        $_SESSION['account_email'] = $account['email'];

        header('Location: /dashboard');
        exit;
    } else {
        $errorMessage = 'Invalid email or password.';
    }
}

// -------------------------
// Handle admin login (legacy)
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? '';

    $stmt = $db->prepare("SELECT * FROM admins WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;

        header('Location: /dashboard');
        exit;
    } else {
        $errorMessage = 'Invalid username or password.';
    }
}

// -------------------------
// Handle OTP request for password recovery via API
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'forgot') {
    $email = $_POST['email'] ?? '';
    $stmt = $db->prepare("SELECT * FROM admins WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin) {
        $otp = rand(100000, 999999);
        $_SESSION['otp'] = $otp;
        $_SESSION['otp_email'] = $email;
        $_SESSION['otp_verified'] = false;

        $payload = json_encode([
            "to" => $email,
            "subject" => "Your OTP for password recovery",
            "content" => "<p>Your OTP is: <strong>$otp</strong></p>"
        ]);

        $ch = curl_init("https://email-server-flame-zeta.vercel.app/api/send-email");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $otpMessage = "OTP sent to $email. Please check your inbox.";
        } else {
            $otpMessage = "Failed to send OTP. Try again later.";
        }

    } else {
        $otpMessage = "Email not found.";
    }
}

// -------------------------
// Handle OTP verification
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
    $enteredOtp = $_POST['otp'] ?? '';
    if (isset($_SESSION['otp']) && $_SESSION['otp'] == $enteredOtp) {
        $_SESSION['otp_verified'] = true;
        $otpMessage = "OTP verified! You can now reset your password.";
    } else {
        $otpMessage = "Invalid OTP.";
    }
}

// -------------------------
// Handle password reset
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($_SESSION['otp_verified'] && $newPassword === $confirmPassword) {
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE admins SET password = :password WHERE email = :email");
        $stmt->execute([
            ':password' => $hashed,
            ':email' => $_SESSION['otp_email']
        ]);

        $otpMessage = "Password updated successfully!";
        unset($_SESSION['otp'], $_SESSION['otp_email'], $_SESSION['otp_verified']);
    } else {
        $otpMessage = "Passwords do not match or OTP not verified.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - WiFiBilling</title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
<style>
body { font-family: 'Lato', sans-serif; }
.tab-btn { padding: 10px 20px; cursor: pointer; border: none; background: #e5e7eb; font-weight: 600; border-radius: 8px 8px 0 0; transition: all 0.2s; }
.tab-btn.active { background: #10b981; color: white; }
.tab-content { display: none; }
.tab-content.active { display: block; }
.lds-ring, .lds-ring div { box-sizing: border-box; }
.lds-ring { display: inline-block; position: relative; width: 80px; height: 80px; }
.lds-ring div { box-sizing: border-box; display: block; position: absolute; width: 64px; height: 64px; margin: 8px; border: 8px solid currentColor; border-radius: 50%; animation: lds-ring 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite; border-color: #1FC69D transparent transparent transparent; }
.lds-ring div:nth-child(1){ animation-delay: -0.45s; }
.lds-ring div:nth-child(2){ animation-delay: -0.3s; }
.lds-ring div:nth-child(3){ animation-delay: -0.15s; }
@keyframes lds-ring { 0%{transform:rotate(0deg);} 100%{transform:rotate(360deg);} }
</style>
</head>
<body>

<div id="loading" class="hidden fixed top-0 left-0 right-0 bottom-0 flex justify-center items-center bg-green-900 bg-opacity-50 z-50">
    <div class="lds-ring"><div></div><div></div><div></div><div></div></div>
</div>

<div class="w-full max-w-md mx-auto p-4 min-h-screen flex flex-col justify-center bg-zinc-100 dark:bg-zinc-900">

<div class="flex justify-center mb-4">
    <img src="https://res.cloudinary.com/dib5bkbsy/image/upload/v1716487950/download_5_qc1uff.jpg" alt="Logo" class="rounded-full w-20 h-20">
</div>

<?php if(isset($errorMessage)): ?><div class="text-red-500 text-sm text-center mb-4"><?= $errorMessage ?></div><?php endif; ?>
<?php if(isset($regMessage)): ?><div class="text-green-600 text-sm text-center mb-4"><?= $regMessage ?></div><?php endif; ?>
<?php if(isset($otpMessage)): ?><div class="text-green-500 text-sm text-center mb-4"><?= $otpMessage ?></div><?php endif; ?>

<!-- Tabs -->
<div class="flex gap-1 mb-0">
    <button class="tab-btn active" onclick="showTab('account')">Account Login</button>
    <button class="tab-btn" onclick="showTab('register')">Register</button>
    <button class="tab-btn" onclick="showTab('admin')">Admin</button>
</div>

<!-- Account Login -->
<div id="tab-account" class="tab-content active bg-white p-6 rounded-b-lg shadow-md">
    <form method="POST">
        <input type="hidden" name="action" value="account_login">
        <div class="mb-4">
            <label class="mb-2 font-semibold block">Email</label>
            <div class="flex items-center bg-zinc-50 p-3 rounded-lg border">
                <i class="fas fa-envelope text-green-500 mr-2"></i>
                <input type="email" name="email" placeholder="Enter email" class="flex-grow bg-transparent border-none focus:ring-0" required>
            </div>
        </div>
        <div class="mb-4">
            <label class="mb-2 font-semibold block">Password</label>
            <div class="flex items-center bg-zinc-50 p-3 rounded-lg border">
                <i class="fas fa-lock text-green-500 mr-2"></i>
                <input type="password" name="password" placeholder="Enter password" class="flex-grow bg-transparent border-none focus:ring-0" required>
            </div>
        </div>
        <button type="submit" class="w-full bg-green-500 text-white py-3 rounded-full font-semibold hover:bg-green-600">Login</button>
    </form>
</div>

<!-- Register -->
<div id="tab-register" class="tab-content bg-white p-6 rounded-b-lg shadow-md">
    <form method="POST">
        <input type="hidden" name="action" value="register">
        <div class="mb-4">
            <label class="mb-2 font-semibold block">Full Name</label>
            <div class="flex items-center bg-zinc-50 p-3 rounded-lg border">
                <i class="fas fa-user text-green-500 mr-2"></i>
                <input type="text" name="name" placeholder="Enter your name" class="flex-grow bg-transparent border-none focus:ring-0" required>
            </div>
        </div>
        <div class="mb-4">
            <label class="mb-2 font-semibold block">Email</label>
            <div class="flex items-center bg-zinc-50 p-3 rounded-lg border">
                <i class="fas fa-envelope text-green-500 mr-2"></i>
                <input type="email" name="email" placeholder="Enter email" class="flex-grow bg-transparent border-none focus:ring-0" required>
            </div>
        </div>
        <div class="mb-4">
            <label class="mb-2 font-semibold block">Password</label>
            <div class="flex items-center bg-zinc-50 p-3 rounded-lg border">
                <i class="fas fa-lock text-green-500 mr-2"></i>
                <input type="password" name="password" placeholder="Create password" class="flex-grow bg-transparent border-none focus:ring-0" required>
            </div>
        </div>
        <button type="submit" class="w-full bg-blue-500 text-white py-3 rounded-full font-semibold hover:bg-blue-600">Create Account</button>
    </form>
</div>

<!-- Admin Login (Legacy) -->
<div id="tab-admin" class="tab-content bg-white p-6 rounded-b-lg shadow-md">
    <form method="POST">
        <input type="hidden" name="action" value="login">
        <div class="mb-4">
            <label class="mb-2 font-semibold block">Login as:</label>
            <div class="flex gap-4">
                <label class="flex items-center gap-1"><input type="radio" name="role" value="superadmin" required> Super Admin</label>
                <label class="flex items-center gap-1"><input type="radio" name="role" value="admin" required> Admin</label>
            </div>
        </div>
        <div class="mb-4">
            <label class="mb-2 font-semibold block">Username</label>
            <div class="flex items-center bg-zinc-50 p-3 rounded-lg border">
                <i class="fas fa-user text-green-500 mr-2"></i>
                <input type="text" name="username" placeholder="Username" class="flex-grow bg-transparent border-none focus:ring-0" required>
            </div>
        </div>
        <div class="mb-4">
            <label class="mb-2 font-semibold block">Password</label>
            <div class="flex items-center bg-zinc-50 p-3 rounded-lg border">
                <i class="fas fa-lock text-green-500 mr-2"></i>
                <input type="password" name="password" placeholder="Password" class="flex-grow bg-transparent border-none focus:ring-0" required>
            </div>
        </div>
        <div class="text-right mb-4">
            <button type="button" id="forgotBtn" class="text-green-500 underline text-sm">Forgot Password?</button>
        </div>
        <button type="submit" class="w-full bg-green-500 text-white py-3 rounded-full font-semibold hover:bg-green-600">Admin Login</button>
    </form>

    <form method="POST" id="forgotForm" class="mt-4 hidden">
        <input type="hidden" name="action" value="forgot">
        <label class="mb-2 font-semibold block">Email for OTP:</label>
        <input type="email" name="email" placeholder="Email" class="mb-4 p-2 border rounded w-full">
        <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded">Send OTP</button>
    </form>

    <?php if(isset($_SESSION['otp'])): ?>
    <form method="POST" class="mt-4">
        <input type="hidden" name="action" value="verify_otp">
        <label class="mb-2 font-semibold block">Enter OTP:</label>
        <input type="text" name="otp" placeholder="OTP" class="mb-4 p-2 border rounded w-full" required>
        <button type="submit" class="w-full bg-orange-500 text-white py-2 rounded">Verify OTP</button>
    </form>
    <?php endif; ?>

    <?php if(isset($_SESSION['otp_verified']) && $_SESSION['otp_verified']): ?>
    <form method="POST" class="mt-4">
        <input type="hidden" name="action" value="reset_password">
        <label class="mb-2 font-semibold block">New Password:</label>
        <input type="password" name="new_password" placeholder="New Password" class="mb-2 p-2 border rounded w-full" required>
        <label class="mb-2 font-semibold block">Confirm Password:</label>
        <input type="password" name="confirm_password" placeholder="Confirm Password" class="mb-4 p-2 border rounded w-full" required>
        <button type="submit" class="w-full bg-purple-500 text-white py-2 rounded">Reset Password</button>
    </form>
    <?php endif; ?>
</div>

</div>

<script>
function showTab(name) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    event.target.classList.add('active');
}
document.getElementById('forgotBtn').addEventListener('click', () => {
    document.getElementById('forgotForm').classList.toggle('hidden');
});
</script>
</body>
</html>
