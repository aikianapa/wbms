<?php
http_response_code(500);
header('Content-Type: text/html; charset=UTF-8');
header("Cache-control: public");
header("Expires: " . gmdate("D, d M Y H:i:s", time() + 60*60*24) . " GMT");


echo "<html><a href='/test.php?fff=zero'>Click</a></html>";

?>