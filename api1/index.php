<?php
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Berlin');
include('config.php');

$pg = pg_connect($pg_connectstr) or die("Datenbankverbindung (PostgreSQL) nicht möglich.".pg_last_error());

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

pg_close($pg);
?>
