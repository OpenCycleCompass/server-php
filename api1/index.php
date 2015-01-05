<?php
header ( 'Content-Type: text/html; charset=utf-8' );
date_default_timezone_set ( 'Europe/Berlin' );
include ('config.php');
$err_level = error_reporting ( 0 );
$my = new mysqli ( $my_host, $my_user, $my_pass );
error_reporting ( $err_level );
if ($my->connect_error)
	die ( "Datenbankverbindung (MySQL) nicht möglich." );
$my->set_charset ( 'utf8' );
$my->select_db ( $my_name );

$pg = pg_connect ( $pg_connectstr ) or die ( "Datenbankverbindung (PostgreSQL) nicht möglich." . pg_last_error () );

// MySQL Example:
// $my->real_escape_string($_POST["text"]);
// $query_text = "INSERT INTO `db`.`table` (`id`, `NAME`) VALUES (NULL, '".$whatever."')";
// result = $my->query($query_text);
//
// result->close();

// PostgreSQL Example:
// Eine SQL-Abfrge ausführen
//$query = 'SELECT * FROM authors';
//$result = pg_query ( $query ) or die ( 'Abfrage fehlgeschlagen: ' . pg_last_error () );
//while ( $line = pg_fetch_array ( $result, null, PGSQL_ASSOC ) ) {
//	foreach ( $line as $col_value ) {
//		$x = $col_value;
//	}
//}
//pg_free_result ( $result );

pg_close ( $pg );
$my->close ();
?>
