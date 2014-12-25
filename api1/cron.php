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

// Remove expired tokens from database:
$db->query("DELETE FROM `ibis_server-php`.`tokens` WHERE `expired`>".time());
		
$db->close();
?>
