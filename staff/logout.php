<?php
require_once __DIR__ . '/includes/staff_auth.php';
logout_staff();
header('Location: /');
exit;
