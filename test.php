<?php
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin';
$_SESSION['full_name'] = 'General Admin';

// Mock functions from auth.php
// We will just require the real file since we mocked session
require 'api/inventory/get_stock.php';
