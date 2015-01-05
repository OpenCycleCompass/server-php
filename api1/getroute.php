<?php
header ( 'Content-Type: text/html; charset=utf-8' );
date_default_timezone_set ( 'Europe/Berlin' );
include ('config.php');
include ('functions.php');
$err_level = error_reporting ( 0 );
$my = new mysqli ( $my_host, $my_user, $my_pass );
error_reporting ( $err_level );
if ($my->connect_error)
	die ( "Datenbankverbindung nicht möglich." );
$my->set_charset ( 'utf8' );
$my->select_db ( $my_name );

$pg = pg_connect ( $pg_connectstr );
if(!$pg)
	die ( "Datenbankverbindung (PostgreSQL) nicht möglich." . pg_last_error () );

if(isset($_GET["getroute"]) && $_GET["getroute"]=="gettrack" && isset($_GET["start_lat"]) && isset($_GET["start_lon"]) && isset($_GET["end_lat"]) && isset($_GET["end_lon"])){
	
	// TODO: Generate route
	$query = "SELECT pgrRoute(_generate_route_here_);";
	
	$result = pg_query ( $query );
	if ( $result ) {
		$data = array();
		$id = 1;
		while ($row = pg_fetch_row($result)) {
			$data[] = array("id" => $id, "lat" => $row[0],"lon" => $row[1],"alt" => $row[2]);
			$id++;
		}
		$out = json_encode($data);
		pg_free_result ( $result );
	} else {
		$out = json_encode ( array (
				"error" => "Keine Route gefunden."
		) );
	}
} else {
	$out = json_encode ( array (
			"error" => "Keine oder falsche Eingabe." 
	) );
}

echo ($out);
pg_close ( $pg );
$my->close ();
?>
