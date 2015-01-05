<!doctype html>
<html>
<head>
<title>ibis - Map View</title>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet"
	href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.3/leaflet.css" />
</head>
<body style="height: 100%;">
	<div id="wrapper" style="min-height: 100%; padding:0px; margin:0px;">
		<div id="map" style="position:absolute; top:0; left:0; width:100%; height:100%;"></div>
	</div>
	<script
		src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.3/leaflet.js"></script>
	<script type="text/javascript">
		var map = L.map('map').setView([51.505, -0.09], 13);
	
		L.tileLayer('https://{s}.tiles.mapbox.com/v3/{id}/{z}/{x}/{y}.png', {
			maxZoom: 18,
			attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, ' +
				'<a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, ' +
				'Imagery © <a href="http://mapbox.com">Mapbox</a>',
			id: 'examples.map-i875mjb7'
		}).addTo(map);
	
	
		L.marker([51.5, -0.09]).addTo(map)
			.bindPopup("<b>Hello world!</b><br />I am a popup.").openPopup();
	
		L.circle([51.508, -0.11], 500, {
			color: 'red',
			fillColor: '#f03',
			fillOpacity: 0.5
		}).addTo(map).bindPopup("I am a circle.");
	
		L.polygon([
			[51.509, -0.08],
			[51.503, -0.06],
			[51.51, -0.047]
		]).addTo(map).bindPopup("I am a polygon.");
	
	
		var popup = L.popup();
	
		function onMapClick(e) {
			popup
				.setLatLng(e.latlng)
				.setContent("You clicked the map at " + e.latlng.toString())
				.openOn(map);
		}
	
		map.on('click', onMapClick);
	</script>
<?php
header ( 'Content-Type: text/html; charset=utf-8' );
date_default_timezone_set ( 'Europe/Berlin' );
include ('api1/config.php');
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
// $query = 'SELECT * FROM authors';
// $result = pg_query ( $query ) or die ( 'Abfrage fehlgeschlagen: ' . pg_last_error () );
// while ( $line = pg_fetch_array ( $result, null, PGSQL_ASSOC ) ) {
// foreach ( $line as $col_value ) {
// $x = $col_value;
// }
// }
// pg_free_result ( $result );

pg_close ( $pg );
$my->close ();
?>
</body>
</html>
