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

if (isset ( $_GET ["newtrack"] ) && $_GET ['newtrack'] == "newtrack" && isset ( $_GET ['user_token'] ) && isset ( $_GET ['length'] ) && isset ( $_GET ['duration'] ) && isset ( $_GET ['name'] ) && isset ( $_GET ['comment'] ) && isset ( $_GET ['data'] )) {
	// user_token passed by the app.
	$user_token = $my->real_escape_string ( $_GET ['user_token'] );
	if (verify_token ( $user_token, $my )) {
		// Create new unique track_id
		// uniqid() generates a 23-character unique string with the giver prefix (ibis_)
		$track_id = uniqid ( "tra_", true );
		
		// Created UNIX-timestamp
		$created = time ();
		
		// Länge (in Metern) des Tracks
		$length = intval($my->real_escape_string ( $_GET ['length'] ));
		
		// Dauer (in Sekunden) des Tracks
		$duration = intval($my->real_escape_string ( $_GET ['duration'] ));
		
		// Name (vom User festgelegt) des Tracks; max. 49 chars
		$name = substr ( $my->real_escape_string ( $_GET ['name'] ), 0, 48 );
		
		// Beschreibung (vom User festgelegt) des Tracks; max. 249 chars
		$comment = substr ( $my->real_escape_string ( $_GET ['comment'] ), 0, 248 );
		
		// data: json-encoded user track
		// array of (lat, lon, alt, time, speed, additional-info (not used so far))

	/*
	 * sample json data:
	{
			"1": {
			"lat": 1.515651,
			"lon": 2.515651,
			"alt": 3.515651,
			"time": 66684584686
			},
			"2": {
			"lat": 7.515651,
				"lon": 8.515651,
				"alt": 9.515651,
				"time": 141445846864
			},
  "3": {
		  "lat": 4.515651,
		    "lon": 5.515651,
		    "alt": 6.515651,
		    "time": 1515458468
		    }
		    }*/

		$data_raw = $_GET ['data'];
		$data = json_decode($data_raw, true, 3);
		if(count($data)>=1){
			foreach ($data as $element) {
				if(!isset($element["lat"]) || !isset($element["lon"]) || !isset($element["time"]))
					break;
				$time = intval($element["time"]); 	// UNIX timestamp ist ganzzahlig
				$lat = floatval($element["lat"]); 	// lat, lon und alt sind Gleitkommazahlen
				$lon = floatval($element["lon"]);
				if(isset($element["alt"]))
					$alt = floatval($element["alt"]);
				else 
					$alt = NULL;
				$query = "INSERT INTO rawdata_server_php (lat, lon, alt, time, track_id)
				VALUES (" . $lat . ",  " . $lon . ",  " . $alt . ", " . $time . ", '" . $track_id . "')";
				$result = pg_query ( $query );
				if ( $result )
					pg_free_result ( $result );
			}
			
			$my->query ( "INSERT INTO `ibis_server-php`.`tracks` (`user_token`, `track_id`, `created`, `length`, `duration`, `name`, `comment`) 
			VALUES ('" . $user_token . "', '" . $track_id . "',  '" . $created . "',  '" . $length . "',  '" . $duration . "',  '" . $name . "', '" . $comment . "')" );
			// Hier wird user_token mit track_id verknüpft: DATENSCHUTZ/SPARSAMKEIT? (TODO)
			
			// Return/echo token with created and expiry timestamp as json
			$out = json_encode ( array (
					'track_id' => $track_id,
					'created' => $created 
			) );

		} else {
			$out = json_encode ( array (
					"error" => "Keine Track-Daten enthalten." 
			) );
		}
	} else {
		$out = json_encode ( array (
				"error" => "Der Token kann nicht verifiziert werden." 
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
