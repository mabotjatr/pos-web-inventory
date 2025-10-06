<?php
// create_passwords.php - Run this once to generate proper password hashes
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Password: " . $password . "\n";
echo "Hash: " . $hash . "\n";
echo "Verify: " . (password_verify($password, $hash) ? 'YES' : 'NO') . "\n";
?>