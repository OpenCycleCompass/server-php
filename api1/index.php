<?php
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Berlin');
include('config.php');
$db = new mysqli($dbhost, $dbuser, $dbpass);
if($db->connect_error) die('Datenbankverbindung nicht möglich. ');
$db->select_db($dbname);


//$db->real_escape_string($_POST["text"]);
//$query_text = "INSERT INTO `db`.`table` (`id`, `NAME`) VALUES (NULL, '".$whatever."')";
//result = $db->query($query_text);
//
//result->close();

$db->close();
?>
