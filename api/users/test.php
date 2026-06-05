<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'super_admin';
$_GET['month'] = '2024-05';
include 'get_overall_summary.php';
