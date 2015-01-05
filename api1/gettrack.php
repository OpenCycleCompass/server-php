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

if(isset($_GET["tracklist"]) && $_GET["tracklist"]=="tracklist") {
	// Return list of tracks (name and track_id) 

	$query = "SELECT `name`,`track_id` FROM `ibis_server-php`.`tracks` LIMIT 10000;";
	$result = $my->query($query);
	if($result->num_rows >= 1){
		$list = array();
		while($row = $result->fetch_array()){
			$list[] = array("name" => $row["name"], "track_id" => $row["track_id"]);
		}
		$out = json_encode($list);
	} else {
		$out = json_encode ( array (
				"error" => "Keine Tracks vorhanden."
		) );
	}
	$result->free();
} else if(isset($_GET["gettrack"]) && $_GET["gettrack"]=="gettrack" && isset($_GET["track_id"])){
	// Return point of track $_GET["track_id"]
	
} else {
	$out = json_encode ( array (
			"error" => "Keine oder falsche Eingabe." 
	) );
}

echo ($out);
pg_close ( $pg );
$my->close ();
?>
