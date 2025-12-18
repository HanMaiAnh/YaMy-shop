<?php
session_name('admin_session');
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}
include '../config/db.php';
include 'dashboard.php';
?>

