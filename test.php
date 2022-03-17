<?php
header('Content-Type: text/html; charset=UTF-8');
echo "<html><a href='/test.php?fff=zero'>Click</a></html>";
print_r($_SERVER['ROUTE']);
?>