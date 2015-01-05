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
?>
<!doctype html>
<html>
<head>
<title>ibis - Map View</title>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet"
	href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.3/leaflet.css" />
<link rel="stylesheet" href="leaflet-sidebar-v2/leaflet-sidebar.min.css" />
</head>
<body style="height: 100%;">
	<div id="sidebar" class="sidebar collapsed">
		<!-- Nav tab(s) -->
		<ul class="sidebar-tabs" role="tablist">
			<li><a href="#gettrack" role="tab"><i class="fa fa-bars"></i></a></li>
			<li><a href="#routing" role="tab"><i class="fa fa-bars"></i></a></li>
		</ul>
		<!-- Tab pane(s) -->
		<div class="sidebar-content active">
			<div class="sidebar-pane" id="gettrack">
				<h1>View iBis Tracks</h1>
				<form id="show_track">
					<select id="track_select">
					<?php 
					$query = "SELECT `name`,`track_id` FROM `ibis_server-php`.`tracks` LIMIT 10000;";
					$result = $my->query($query);
					if($result->num_rows >= 1){
						$data = array();
						while($row = $result->fetch_array()){
							echo("\t\t\t\t\t\t<option value=\"" . $row["track_id"] . "\">" . $row["name"] . "</option>\n");
						}
					}
					?>
					</select> <input type="submit" value="Anzeigen">
				</form>
			</div>
			<div class="sidebar-pane" id="routing">
				<h1>iBis Routing Preview</h1>
				<p>Routing ...</p>
			</div>
		</div>
	</div>
	<div id="wrapper" style="min-height: 100%; padding: 0px; margin: 0px;">
		<div id="map"
			style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;"></div>
	</div>
	<script src="jquery-2.1.3.min.js"></script>
	<script
		src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.3/leaflet.js"></script>
	<script src="leaflet-sidebar-v2/leaflet-sidebar.min.js"></script>
	<script type="text/javascript">
		var map = L.map('map').setView([51.505, -0.09], 13);

		//L.tileLayer('https://{s}.tiles.mapbox.com/v3/{id}/{z}/{x}/{y}.png', {
		// http://{s}.tile.thunderforest.com/cycle (OpenCycleMap) ist leider nicht über https verfügbar
		// -> leider MixedContent 
		L.tileLayer('http://{s}.tile.thunderforest.com/cycle/{z}/{x}/{y}.png', {
			maxZoom: 18,
			//attribution: 'Map data &copy; <a href="http://opencyclemap.org">OpenStreetMap</a> contributors, ' +
			//	'<a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, ' +
			//	'Imagery © <a href="http://mapbox.com">Mapbox</a>',
			//id: 'examples.map-i875mjb7'
		}).addTo(map);
	
		var sidebar = L.control.sidebar('sidebar').addTo(map);

		var line_points = [
		                   [38.893596444352134, -77.0381498336792],
		                   [38.89337933372204, -77.03792452812195],
		                   [38.89316222242831, -77.03761339187622],
		                   [38.893028615148424, -77.03731298446655],
		                   [38.892920059048464, -77.03691601753235],
		                   [38.892903358095296, -77.03637957572937],
		                   [38.89301191422077, -77.03592896461487],
		                   [38.89316222242831, -77.03549981117249],
		                   [38.89340438498248, -77.03514575958252],
		                   [38.893596444352134, -77.0349633693695]
		               ];
        
		
		// create a red polyline from an array of LatLng points
		var polyline = L.polyline(line_points, {color: 'red'}).addTo(map);

		// zoom the map to the polyline
		map.fitBounds(polyline.getBounds());
	
		var popup = L.popup();
		function onMapClick(e) {
			popup
				.setLatLng(e.latlng)
				.setContent("You clicked the map at " + e.latlng.toString())
				.openOn(map);
		}
	
		map.on('click', onMapClick);
	</script>
	<script type="text/javascript">
		$( "#show_track" ).submit(function( event ) {
			// Get points of selected track an show it on map
			var line_points = [];
			$.getJSON("api1/gettrack.php?gettrack=gettrack&track_id="+$("#track_select").val(), function (json) {
		        for (var i = 0; i < json.length; i++) {
		            //line_points.push([json[i].lat, json[i].lon, json[i].alt]);
		            line_points[i] = [parseFloat(json[i].lat), parseFloat(json[i].lon)];
		        }
			});
			// Create array of lat,lon points.
			var line_points2 = [
			    [38.893596444352134, -77.0381498336792],
			    [38.89337933372204, -77.03792452812195],
			    [38.89340438498248, -77.03514575958252],
			    [38.893596444352134, -77.0349633693695]
			];
			var line_points3 = array(line_points.length);
			line_points3 = line_points;
			console.log(line_points2);
			console.log(line_points3);
			console.log(line_points);
			// create a red polyline from an array of LatLng points
			var polyline = L.polyline(line_points, {color: 'green'}).addTo(map);
			// zoom the map to the polyline
			map.fitBounds(polyline.getBounds());
		});
		
	</script>
<?php
pg_close ( $pg );
$my->close ();
?>
</body>
</html>
