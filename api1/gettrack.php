<?php
$start_microtime = microtime(true);
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Berlin');
include('config.php');
include('functions.php');

$pg = pg_connect($pgr_connectstr);
if(!$pg) die(json_encode(array("error" => "Database (PostgreSQL) failed." . pg_last_error())));

session_start();

if(isset($_GET["tracklist"])) {
	// Return list of tracks (name and track_id) 

	if(isset($_GET["num"])){
		$start_num = pg_escape_string($_GET["num"]);
	} else {
		$start_num = "0";
	}
	$only_public = "";
	if(!isset($_SESSION["auth_user"])) {
		$only_public = "WHERE public = TRUE ";
	}
	$query = "SELECT name, track_id, created, nodes, city, city_district FROM tracks ".$only_public."ORDER BY created DESC LIMIT 25 OFFSET ".$start_num.";";
	$result = pg_query($pg, $query);
	if($result && pg_num_rows($result) >= 1){
		$data = array();
		while($row = pg_fetch_assoc($result)){
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
	pg_free_result($result);
} else if(isset($_GET["tracknum"])) {
	// Return number of tracks (scalar)
	$only_public = "";
	if(!isset($_SESSION["auth_user"])) {
		$only_public = "WHERE public = TRUE";
	}
	$query = "SELECT count(id) FROM tracks ".$only_public.";";
	$result = pg_query($pg, $query);
	if(pg_num_rows($result) >= 1){
		$row = pg_fetch_array($result);
		$data = array("num" => $row[0]);
		$out = json_encode($data);
	} else {
		$out = json_encode ( array (
			"error" => "Keine Tracks vorhanden."
		) );
	}
	pg_free_result($result);
} else if(isset($_GET["gettrack"]) && isset($_GET["track_id"])){
	// Return point of track $_GET["track_id"]
	$query = "SELECT lat, lon, alt, time, speed FROM rawdata_server_php WHERE track_id = '" . pg_escape_string($pg, $_GET["track_id"]) . "';";
	$result = pg_query($query);
	if($result) {
		$data = array();
		$id = 1;
		while ($row = pg_fetch_row($result)) {
			$data[] = array("id" => $id, "lat" => $row[0],"lon" => $row[1],"alt" => $row[2],"timestamp" => $row[3]);
			$id++;
		}
		$out = json_encode($data);
		pg_free_result($result);
	} else {
		$out = json_encode(array("error" => "Track nicht vorhanden."));
	}
} else {
	$out = json_encode (array("error" => "Keine oder falsche Eingabe."));
}

echo($out);
pg_close($pg);
?>
