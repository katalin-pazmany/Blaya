<?php
// Copy this file to config.php (gitignored) and fill in real values.
// Generate a password hash with:
//   php -r "$s=bin2hex(random_bytes(16));$i=300000;echo 'SALT='.$s.\"\n\";echo 'HASH='.hash_pbkdf2('sha256','YOUR_PASSWORD',$s,$i,32).\"\n\";"

return [
    'admin_password_salt' => 'REPLACE_ME',
    'admin_password_hash' => 'REPLACE_ME',
    'admin_password_iterations' => 300000,
];
