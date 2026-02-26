<?php
$password_plain = '123'; // ganti dengan password yang diinginkan
$password_hash = password_hash($password_plain, PASSWORD_DEFAULT);
echo "Password: $password_plain\n";
echo "Hash   : $password_hash\n";
?>
