<?php
ob_start();
$pageTitle = 'Settings';
$activePage = 'settings';

require_once __DIR__ . '/../db/schema.php';
// schema.php defines $db (single shared connection)

$role    = $_SESSION['role'] ?? 'user';
$accountId = $_SESSION['account_id'] ?? null;

$msgSuccess = '';
$msgError   = '';

$currencyOptions = [
    'TZS' => 'TZS - Tanzanian Shilling (TSh)',
    'KES' => 'KES - Kenyan Shilling (KES)',
    'UGX' => 'UGX - Ugandan Shilling (UGX)',
    'RWF' => 'RWF - Rwandan Franc (RWF)',
    'NGN' => 'NGN - Nigerian Naira (NGN)',
    'USD' => 'USD - US Dollar ($)',
    'ZMW' => 'ZMW - Zambian Kwacha (K)',
    'GHS' => 'GHS - Ghanaian Cedi (GH₵)',
    'XOF' => 'XOF - West African Franc (FCFA)',
    'MWK' => 'MWK - Malawian Kwacha (MK)',
];

// Load current profile
if ($role === 'user' && $accountId) {
    $stmt = $db->prepare("SELECT * FROM accounts WHERE id = ?");
    $stmt->execute([$accountId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    $idCol = 'id';
} else {
    $stmt = $db->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
    $stmt->execute([$_SESSION['username'] ?? '']);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // -------------------------
    // Update profile (email, name, currency)
    // -------------------------
    if ($action === 'profile' && $profile) {
        $currentPass = $_POST['current_password'] ?? '';

        if (!password_verify($currentPass, $profile['password'])) {
            $msgError = 'Current password is incorrect.';
        } else {
            $newEmail = strtolower(trim($_POST['email'] ?? ''));
            $newName  = trim($_POST['name'] ?? '');
            $newCurrency = $_POST['currency'] ?? 'TZS';
            if (!array_key_exists($newCurrency, $currencyOptions)) {
                $newCurrency = 'TZS';
            }

            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $msgError = 'Please enter a valid email address.';
            } elseif ($role === 'user' && empty($newName)) {
                $msgError = 'Name is required.';
            } else {
                // Email uniqueness check
                if ($role === 'user') {
                    $stmt = $db->prepare("SELECT id FROM accounts WHERE email = ? AND id != ?");
                    $stmt->execute([$newEmail, $profile['id']]);
                    if ($stmt->fetch()) {
                        $msgError = 'Email is already registered to another account.';
                    }
                } else {
                    $stmt = $db->prepare("SELECT username FROM admins WHERE email = ? AND username != ?");
                    $stmt->execute([$newEmail, $profile['username']]);
                    if ($stmt->fetch()) {
                        $msgError = 'Email is already in use.';
                    }
                }

                if (empty($msgError)) {
                    if ($role === 'user') {
                        $db->prepare("UPDATE accounts SET name = ?, email = ?, currency = ? WHERE id = ?")
                           ->execute([$newName, $newEmail, $newCurrency, $profile['id']]);
                        $_SESSION['username'] = $newName;
                    } else {
                        $db->prepare("UPDATE admins SET email = ?, currency = ? WHERE username = ?")
                           ->execute([$newEmail, $newCurrency, $profile['username']]);
                    }
                    $_SESSION['account_email'] = $newEmail;
                    $_SESSION['currency'] = $newCurrency;
                    $msgSuccess = 'Profile updated. Currency is now ' . $newCurrency . ' and will persist for your dashboard.';
                    $profile['email'] = $newEmail;
                    $profile['currency'] = $newCurrency;
                    if ($role === 'user') $profile['name'] = $newName;
                }
            }
        }
    }

    // -------------------------
    // Change password
    // -------------------------
    if ($action === 'password' && $profile) {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPass, $profile['password'])) {
            $msgError = 'Current password is incorrect.';
        } elseif (strlen($newPass) < 4) {
            $msgError = 'New password must be at least 4 characters.';
        } elseif ($newPass !== $confirmPass) {
            $msgError = 'New password and confirmation do not match.';
        } else {
            $hashed = password_hash($newPass, PASSWORD_DEFAULT);
            if ($role === 'user') {
                $db->prepare("UPDATE accounts SET password = ? WHERE id = ?")->execute([$hashed, $profile['id']]);
            } else {
                $db->prepare("UPDATE admins SET password = ? WHERE username = ?")->execute([$hashed, $profile['username']]);
            }
            $msgSuccess = 'Password updated successfully.';
        }
    }
}

include __DIR__ . '/../components/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title"><i class="fas fa-cog"></i> Settings</h1>
        <p class="page-subtitle">Manage your profile, password and dashboard currency</p>
    </div>
</div>

<?php if ($msgSuccess): ?>
    <div class="alert alert-success" style="margin-bottom:20px;"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msgSuccess) ?></div>
<?php endif; ?>
<?php if ($msgError): ?>
    <div class="alert alert-danger" style="margin-bottom:20px;"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($msgError) ?></div>
<?php endif; ?>

<div class="form-row">
    <!-- Profile -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-user"></i> Profile</span>
        </div>
        <div class="card-body">
            <p class="card-subtitle">Update your name, email and dashboard currency. Enter your current password to save.</p>
            <form method="POST">
                <input type="hidden" name="action" value="profile">
                <?php if ($role === 'user'): ?>
                <div class="form-group">
                    <label class="form-label" for="profileName">Full Name</label>
                    <input type="text" class="form-control" id="profileName" name="name" value="<?= htmlspecialchars($profile['name'] ?? '') ?>" required>
                </div>
                <?php endif; ?>
                <div class="form-group">
                    <label class="form-label" for="profileEmail">Email Address</label>
                    <input type="email" class="form-control" id="profileEmail" name="email" value="<?= htmlspecialchars($profile['email'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="profileCurrency">Dashboard Currency</label>
                    <select class="form-control" id="profileCurrency" name="currency">
                        <?php foreach ($currencyOptions as $code => $label): ?>
                            <option value="<?= $code ?>" <?= (($profile['currency'] ?? 'TZS') === $code) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color:var(--on-surface-med);">Applied to your dashboard, revenue and vouchers. Saved for your account.</small>
                </div>
                <div class="form-group">
                    <label class="form-label" for="profileCurrentPass">Current Password</label>
                    <input type="password" class="form-control" id="profileCurrentPass" name="current_password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Profile</button>
            </form>
        </div>
    </div>

    <!-- Password -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-lock"></i> Change Password</span>
        </div>
        <div class="card-body">
            <p class="card-subtitle">Enter your current password, then a new one.</p>
            <form method="POST">
                <input type="hidden" name="action" value="password">
                <div class="form-group">
                    <label class="form-label" for="passCurrent">Current Password</label>
                    <input type="password" class="form-control" id="passCurrent" name="current_password" required autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label class="form-label" for="passNew">New Password</label>
                    <input type="password" class="form-control" id="passNew" name="new_password" required autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label class="form-label" for="passConfirm">Confirm New Password</label>
                    <input type="password" class="form-control" id="passConfirm" name="confirm_password" required autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Update Password</button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>
<?php ob_end_flush(); ?>
