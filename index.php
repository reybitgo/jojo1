<?php
// index.php – allow logged-in users to visit home.php
require_once 'config/config.php';
require_once 'config/session.php';

// Always route to home.php
header('Location: home.php');
exit;
?>