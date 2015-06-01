<?php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Berlin');
include('config.php');

$pg = pg_connect($pgr_connectstr);
if(!$pg) die(json_encode(array("error" => "Database (PostgreSQL) failed." . pg_last_error())));

// Remove expired tokens from database:
$result = pg_query($pg, "DELETE FROM tokens WHERE expired > ".time());
if(!$result) die("Löschen der abgelaufenen Token nicht möglich: ".pg_last_error());
pg_free_result($result);

pg_close($pg);
?>
