<?php
require __DIR__ . '/includes/config.php';
header('Location: ' . (is_logged_in() ? home_url() : 'login.php'));
exit;
