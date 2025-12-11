<?php
session_name('admin_session');
session_start();

$_SESSION = [];
session_unset();
session_destroy();

// Xóa cookie session của admin
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

header("Location: ../admin/login.php");
exit;
