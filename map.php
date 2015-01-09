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
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<link href="http://maxcdn.bootstrapcdn.com/font-awesome/4.1.0/css/font-awesome.min.css" rel="stylesheet">
<link rel="stylesheet"
	href="leaflet/leaflet.css" />
<link rel="stylesheet" href="leaflet-sidebar-v2/leaflet-sidebar.min.css" />
<style>
	body {
		padding: 0;
		margin: 0;
	}
	html, body, #map {
		height: 100%;
		font: 10pt "Helvetica Neue", Helvetica, sans-serif;
	}
</style>
</head>
<body style="height: 100%;">
	<div id="sidebar" class="sidebar collapsed">
		<!-- Nav tab(s) -->
		<ul class="sidebar-tabs" role="tablist">
			<li><a href="#gettrack" role="tab">O<i class="fa fa-user"></i></a></li>
			<li><a href="#routing" role="tab">O<i class="fa fa-bars"></i></a></li>
		</ul>
		<!-- Tab pane(s) -->
		<div class="sidebar-content active">
			<div class="sidebar-pane" id="gettrack">
				<h1>View iBis Tracks</h1>
				<form id="show_track">
					<label for="track_select">Track anzeigen</label><select id="track_select">
					<?php
					// options are generated with JS when document is ready to handle thousends of tracks an multiple pages
					/*$query = "SELECT `name`,`track_id`, `nodes` FROM `ibis_server-php`.`tracks` LIMIT 10000;";
					$result = $my->query($query);
					if($result->num_rows >= 1){
						$data = array();
						while($row = $result->fetch_array()){
							echo("\t\t\t\t\t\t<option value=\"" . $row["track_id"] . "\">" . $row["name"] . "  " . "(" . $row["nodes"] . " Punkte)</option>\n");
						}
					}*/
					?>
					</select> <input type="submit" value="Anzeigen">
				</form>
				Tracks:

				<form id="show_track_num">
					<label for="track_select_num">Track anzeigen</label><select id="track_select_num">
						<option value="0">0..99</option>
						<option value="100">100..199</option>
						<option value="200">200..299</option>
						<option value="300">300..399</option>
						<option value="400">400..499</option>
					</select> <input type="submit" value="Anzeigen">
				</form>
			</div>
			<div class="sidebar-pane" id="routing">
				<h1>iBis Routing Preview</h1>
				<p>Zum Auswählen des Start und Ziel-Punktes in die Karte klicken!</p>
				<form id="generate_route">
				 <table>
					<tr><td><p>Von</p></td></tr>
					<tr><td><label for="start_lat">Breite:</label><input type="text" name="start_lat" id="start_lat"></td></tr>
					<tr><td><label for="start_lon">Länge:</label><input type="text" name="start_lon" id="start_lon"></td></tr>
					<tr><td><p>Nach</p></td></tr>
					<tr><td><label for="end_lat">Breite:</label><input type="text" name="end_lat" id="end_lat"></td></tr>
					<tr><td><label for="end_lon">Länge:</label><input type="text" name="end_lon" id="end_lon"></td></tr>
					<tr><td><input type="submit" value="Route generieren"></td></tr>
				 </table>
				</form>
			</div>
		</div>
	</div>
	
	<div id="map" class="sidebar-map"></div>
	
	<script type="text/javascript" src="jquery/jquery-2.1.3.min.js"></script>
	<script type="text/javascript" src="leaflet/leaflet.js"></script>
	<script type="text/javascript" src="leaflet-sidebar-v2/leaflet-sidebar.min.js"></script>
	<script type="text/javascript">
		$( document ).ready(function() {
			setTrackSelectOptions($("#track_select_num select"));
		});

		function setTrackSelectOptions(num) {
			var options_uri = "api1/gettrack.php?tracklist=tracklist&num=" + num;
			$.getJSON(options_uri, function (json) {
				var options = "";
				for (var i = 0; i< json.length; i++) {
					options += "<option value=\"" + json[i].track_id + "\">" + json[i].name + "</option>";
				}
				$('select #show_track').append(options);
			});
		}

		function onMapClick(e) {
			if(clickTyp == 0){
				$("#start_lat").val(e.latlng.lat);
				$("#start_lon").val(e.latlng.lng);
				popup_start
					.setLatLng(e.latlng)
					.setContent("Start at " + e.latlng.toString())
					.openOn(map);
				clickTyp = clickTyp+1;
			} else if(clickTyp == 1){
				$("#end_lat").val(e.latlng.lat);
				$("#end_lon").val(e.latlng.lng);
				popup_end
					.setLatLng(e.latlng)
					.setContent("End at " + e.latlng.toString())
					.openOn(map);
				clickTyp = clickTyp+1;
			} else if(clickTyp >= 2){
				clickTyp = 0;
			}
		}
			
		function drawPolyline(urlJsonData){
			// Get points of selected track an show it on map
			// Create array of lat,lon points
			var line_points = [];
			$.getJSON(urlJsonData, function (json) {
				for (var i = 0; i < json.length; i++) {
					//line_points.push(L.latLng(parseFloat(json[i].lat), parseFloat(json[i].lon), parseFloat(json[i].alt)));
					line_points.push(L.latLng(parseFloat(json[i].lat), parseFloat(json[i].lon)));
				}

				// create a red polyline from an array of LatLng points
				var polyline = L.polyline(line_points, {color: 'red'}).addTo(map);
				// zoom the map to the polyline
				map.fitBounds(polyline.getBounds());
			});
		}
		var map = L.map('map').setView([51.505, -0.09], 13);

		// http://{s}.tile.thunderforest.com/cycle (OpenCycleMap) ist leider nicht über https verfügbar
		// -> leider MixedContent 
		L.tileLayer('http://{s}.tile.thunderforest.com/cycle/{z}/{x}/{y}.png', {
			maxZoom: 18
		}).addTo(map);
	
		var sidebar = L.control.sidebar('sidebar').addTo(map);

	
		var clickTyp = 0;
		var popup_start = L.popup();
		var popup_end = L.popup();


		map.on('click', onMapClick);

		$( "#show_track" ).submit(function( event ) {
			$('#track_select option:selected').each(function( ) {
				drawPolyline("api1/gettrack.php?gettrack=gettrack&track_id=" + $(this).val());
			});

			//drawPolyline("api1/gettrack.php?gettrack=gettrack&track_id="+$("#track_select").val());
			event.preventDefault();
		});
		
		$( "#generate_route" ).submit(function( event ) {
			drawPolyline( "api1/getroute.php?getroute=getroute"
				+"&start_lat="+$("#start_lat").val()
				+"&start_lon="+$("#start_lon").val()
				+"&end_lat="+$("#end_lon").val()
				+"&end_lon="+$("#end_lon").val() );
			event.preventDefault();
		});
	</script>
<?php
pg_close ( $pg );
$my->close ();
?>
</body>
</html>
