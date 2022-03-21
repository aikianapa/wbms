<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="/style.css" type="text/css">
    <link rel="icon" href="/favicon.png">
</head>

<body>
    <?php
session_start();
$id = session_id();
var_dump($id);
//session_close();

?>
</body>

</html>