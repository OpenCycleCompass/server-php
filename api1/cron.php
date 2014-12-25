<?php
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Berlin');
include('config.php');
$err_level = error_reporting(0);
$my = new mysqli($my_host, $my_user, $my_pass);
error_reporting($err_level);
if($my->connect_error) die("Datenbankverbindung nicht möglich.");
$my->set_charset('utf8');
$my->select_db($my_name);

// Remove expired tokens from database:
$my->query("DELETE FROM `ibis_server-php`.`tokens` WHERE `expired`>".time());
		
$my->close();
?>
