<?php
$hash = '$2y$10$PzrBSqsrCjxOm3AXRDjxruNgEHGZzTxw8m8J45Q2B8/1QVzvaS.Uy';
$pw = 'password123';
var_dump(password_verify($pw, $hash));
