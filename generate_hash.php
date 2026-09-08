<?php
// Replace "da123" with the password you want to hash
$hash = password_hash("da123", PASSWORD_DEFAULT);
echo $hash;
?>
