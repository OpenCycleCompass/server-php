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

$pgr = pg_connect ( $pgr_connectstr );
if(!$pgr)
	die ( "Datenbankverbindung (PostgreSQL) nicht möglich." . pg_last_error () );

if(isset($_GET["tracklist"]) && $_GET["tracklist"]=="tracklist") {
	// Return list of tracks (name and track_id) 

	if(isset($_GET["num"])){
		$start_num = $my->real_escape_string($_GET["num"]);
	} else {
		$start_num = "0";
	}
	$query = "SELECT `name`,`track_id`,`created`,`nodes`,`city`,`city_district` FROM `ibis_server-php`.`tracks` ORDER BY `created` DESC LIMIT " . $start_num . ",25;";
	$result = $my->query($query);
	if($result->num_rows >= 1){
		$data = array();
		while($row = $result->fetch_array()){
			if($row["city"]!=NULL && $row["city_district"]!=NULL) {
				// city_district can ba like "Aachen-Mitte" or "Innenstadt"
				if(stripos($row["city_district"], $row["city"])===false) {
					// city_district does not contain name of city
					$city = "/".$row["city"]."(".$row["city_district"].")";
				}
				else {
					// city_district contains name of city
					$city = "/".$row["city_district"];
				}
			}
			else if($row["city"]!=NULL) {
					$city = "/".$row["city"];
			}
			else {
				$city = "";
			}
			$data[] = array("name" => $row["name"] . $city . " (" . date("d.m. ~H", intval($row["created"])) . "h; " . $row["nodes"] ." Punkte)", "track_id" => $row["track_id"]);
		}
		$out = json_encode($data);
	} else {
		$out = json_encode ( array (
			"error" => "Keine Tracks vorhanden."
		) );
	}
	$result->free();
} else if(isset($_GET["tracknum"]) && $_GET["tracknum"]=="tracknum") {
	// Return number of tracks (scalar)

	$query = "SELECT count(`id`) FROM `ibis_server-php`.`tracks`;";
	$result = $my->query($query);
	if($result->num_rows >= 1){
		$row = $result->fetch_array();
		$data = array("num" => $row[0]);
		$out = json_encode($data);
	} else {
		$out = json_encode ( array (
			"error" => "Keine Tracks vorhanden."
		) );
	}
	$result->free();
} else if(isset($_GET["gettrack"]) && $_GET["gettrack"]=="gettrack" && isset($_GET["track_id"])){
	// Return point of track $_GET["track_id"]
	$query = "SELECT lat, lon, alt, time, speed FROM rawdata_server_php WHERE track_id = '" . pg_escape_string($pgr, $_GET["track_id"]) . "';";
	$result = pg_query ( $query );
	if ( $result ) {
		$data = array();
		$id = 1;
		while ($row = pg_fetch_row($result)) {
			$data[] = array("id" => $id, "lat" => $row[0],"lon" => $row[1],"alt" => $row[2],"timestamp" => $row[3]);
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
pg_close ( $pgr );
$my->close ();
?>
