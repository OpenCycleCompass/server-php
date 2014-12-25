<?php
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Berlin');
include('config.php');
$err_level = error_reporting(0);
$db = new mysqli($dbhost, $dbuser, $dbpass);
error_reporting($err_level);
if($db->connect_error) die("Datenbankverbindung nicht möglich.");
$db->set_charset('utf8');
$db->select_db($dbname);


//$db->real_escape_string($_POST["text"]);
//$query_text = "INSERT INTO `db`.`table` (`id`, `NAME`) VALUES (NULL, '".$whatever."')";
//result = $db->query($query_text);
//
//result->close();

$db->close();
?>
