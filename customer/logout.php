<?php
require_once __DIR__.'/auth.php';
unset($_SESSION['customer_auth'], $_SESSION['customer_csrf_token']);
session_regenerate_id(true);
header('Location: login.php');
exit;
