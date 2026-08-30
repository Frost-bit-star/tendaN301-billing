<?php
// logout

if (session_status() === PHP_SESSION_NONE) {
    // session not started, start it to destroy it
    session_start();
}

require_once __DIR__ . '/../auth/session.php';
authResolve();

// Clear ONLY the currently-active identity (role + its keys + its backup),
// leaving other identities (e.g. a user login in another tab) intact.
authLogoutActive();

// Redirect to login page
header("Location: login");
exit;
?>
