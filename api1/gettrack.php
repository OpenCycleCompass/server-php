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
		$data = array();
		while($row = $result->fetch_array()){
			$data[] = array("name" => $row["name"], "track_id" => $row["track_id"]);
		}
		$out = json_encode($data);
	} else {
		$out = json_encode ( array (
				"error" => "Keine Tracks vorhanden."
		) );
	}
	$result->free();
} else if(isset($_GET["gettrack"]) && $_GET["gettrack"]=="gettrack" && isset($_GET["track_id"])){
	// Return point of track $_GET["track_id"]
	$query = "SELECT lat, lon, alt FROM rawdata_server_php WHERE track_id = '" . pg_escape_string($pg, $_GET["track_id"]) . "';";
	echo($query);
	$result = pg_query ( $query );
	if ( $result ) {
		$data = array();
		$id = 1;
		while ($row = pg_fetch_row($result)) {
			var_dump($row);
			$data[] = array("id" => $id, "lat" => $row["lat"],"lon" => $row["lon"],"alt" => $row["alt"]);
			$id++;
		}
		$out = json_encode($data);
		pg_free_result ( $result );
	} else {
		$out = json_encode ( array (
				"error" => "Track nicht vorhanden."
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
