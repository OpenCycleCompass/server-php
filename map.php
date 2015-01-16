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
					<label for="track_select">Track(s) anzeigen</label>
					<br />
					<select id="track_select" multiple="multiple" size="25" style="overflow: hidden;">

					</select>
					<br />
					<input type="submit" value="Tracks Anzeigen">
				</form>
				<br />
				<form id="track_select_num_form">
					<label for="track_select_num">Track Auswahl:</label>
					<p id="track_select_num_p">Es sind unbekannt viele Tracks vorhanden</p>
					<select id="track_select_num">
						<option value="0">0..24</option>
					</select>
					<input type="submit" value="Wechseln">
				</form>
			</div>


			<div class="sidebar-pane" id="routing">
				<h1>iBis Routing Preview</h1>
				<p>Zum Auswählen des Start und Ziel-Punktes in die Karte klicken!</p>
				<form id="generate_route">
				 <table>
					<tr><td><p id="routing_start_p">Von</p></td></tr>
					<tr><td><label for="start_lat">Breite:</label><input type="text" name="start_lat" id="start_lat"></td></tr>
					<tr><td><label for="start_lon">Länge:</label><input type="text" name="start_lon" id="start_lon"></td></tr>
					<tr><td><p id="routing_start_id">(id=?)</p></td></tr>
					<tr><td><p id="routing_end_p">Nach</p></td></tr>
					<tr><td><label for="end_lat">Breite:</label><input type="text" name="end_lat" id="end_lat"></td></tr>
					<tr><td><label for="end_lon">Länge:</label><input type="text" name="end_lon" id="end_lon"></td></tr>
					<tr><td><p id="routing_end_id">(id=?)</p></td></tr>
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
		$.ajaxSetup({'async': false});

		$( document ).ready( setTrackSelectOptions($("#track_select_num").val()));

		$("#track_select_num_form").submit( function () {
			setTrackSelectOptions($("#track_select_num").val());
		});

		function setTrackSelectOptions(num) {
			var options_uri = "api1/gettrack.php?tracklist=tracklist&num=" + num;
			$.getJSON(options_uri, function (json) {
				var options = "";
				for (var i = 0; i< json.length; i++) {
					options += "<option value=\"" + json[i].track_id + "\">" + json[i].name + "</option>";
				}
				$('#track_select').find("option").remove().end()
				.append(options);
			});
			var num_uri = "api1/gettrack.php?tracknum=tracknum";
			$.getJSON(num_uri, function (json) {
				$('#track_select_num_p').replaceWith("<p id=\"track_select_num_p\">Es sind " + json.num + " Tracks vorhanden.</p>");
				var options = "";
				for (var i = 0; i < json.num; i = i+25) {
					options += "<option value=\"" + i + "\">" + i + "..." + (Math.min((i+24),json.num)) + "</option>";
				}
				var s_num = $("#track_select_num").val();
				$('#track_select_num').find("option").remove().end()
				.append(options);
				$("#track_select_num").val(s_num);
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
				// add lat and lon to array:
				lats.push(polyline.getBounds().getSouth());
				lats.push(polyline.getBounds().getNorth());
				lons.push(polyline.getBounds().getWest());
				lons.push(polyline.getBounds().getEast());
			});
		}
		
		
		function distance(lat1, lon1, lat2, lon2) {
			var radlat1 = Math.PI * lat1/180;
			var radlat2 = Math.PI * lat2/180;
			var radlon1 = Math.PI * lon1/180;
			var radlon2 = Math.PI * lon2/180;
			var theta = lon1-lon2;
			var radtheta = Math.PI * theta/180;
			var dist = Math.sin(radlat1) * Math.sin(radlat2) + Math.cos(radlat1) * Math.cos(radlat2) * Math.cos(radtheta);
			dist = Math.acos(dist);
			dist = dist * 180/Math.PI;
			dist = dist * 60 * 1.1515;
			dist = dist * 1.609344;
			return dist * 1000;
		}
		function drawColorPolyline(urlJsonData){
			$.getJSON(urlJsonData, function (json) {
				for (var i = 0; i < json.length-1; i++) {
					var line_points = [2];
					// Line from point i to point i+1
					line_points[0] = L.latLng(parseFloat(json[i].lat), parseFloat(json[i].lon)); 
					line_points[1] = L.latLng(parseFloat(json[i+1].lat), parseFloat(json[i+1].lon));
					
					// Distance between point i and point i+1
					var dist = distance(json[i].lat, json[i].lon, json[i+1].lat, json[i+1].lon); // in meters
					
					// Speed calculation based on  timestamp difference and distance
					var dtime = json[i+1].timestamp-json[i].timestamp; 		// in seconds
					var speed = dist/dtime;		// in m/s (meter/second)
					
					// Color of line dependung on Speed
					var color;
					var speed_ko = 0.1000;
					if(speed<1) {
						color = "#FF0000";
					} else if(speed<(3*speed_ko)) {
						color = "#FF4000";
					} else if(speed<(5*speed_ko)) {
						color = "#FF8000";
					} else if(speed<(8*speed_ko)) {
						color = "#FFC000";
					} else if(speed<(11*speed_ko)) {
						color = "#FFFF00";
					} else if(speed<(14*speed_ko)) {
						color = "#C0FF00";
					} else if(speed<(17*speed_ko)) {
						color = "#80FF00";
					} else if(speed<(20*speed_ko)) {
						color = "#40FF00";
					} else if(speed<(25*speed_ko)) {
						color = "#10FF00";
					} else {
						color = "#0000FF";
					}
					
					var polyline = L.polyline(line_points, {color: color}).addTo(map);
					lats.push(polyline.getBounds().getSouth());
					lats.push(polyline.getBounds().getNorth());
					lons.push(polyline.getBounds().getWest());
					lons.push(polyline.getBounds().getEast());
				}
			});
		}
		
		
		function clearMap() {
			for(i in map._layers) {
				if(map._layers[i]._path != undefined) {
					try {
						map.removeLayer(map._layers[i]);
					} catch(e) {
						console.log("problem with " + e + map._layers[i]);
					}
				}
			}
		}
		
		var map = L.map('map').setView([50, 7], 7);

		// http://{s}.tile.thunderforest.com/cycle (OpenCycleMap) ist leider nicht über https verfügbar
		// -> leider MixedContent 
		L.tileLayer('http://{s}.tile.thunderforest.com/cycle/{z}/{x}/{y}.png', {
			maxZoom: 18
		}).addTo(map);

		navigator.geolocation.getCurrentPosition( function GetLocation(location) {
			map.panTo([location.coords.latitude, location.coords.longitude]);
			map.zoomIn(2);
		});

		var sidebar = L.control.sidebar('sidebar').addTo(map);

	
		var clickTyp = 0;
		var popup_start = L.popup();
		var popup_end = L.popup();

		var lats = [];
		var lons = [];

		map.on('click', onMapClick);

		$( "#show_track" ).submit(function( event ) {
			// Remove all polylines
			clearMap();
			lats = [];
			lons = [];
			// Draw ploylines for any sleected track
			$('#track_select option:selected').each(function() {
				drawColorPolyline("api1/gettrack.php?gettrack=gettrack&track_id=" + $(this).val());
			});
			$('#track_select option:selected').promise().done(function() {
				var latSouth = Math.max.apply(Math, lats);
				var latNorth = Math.min.apply(Math, lats);
				var lngWest = Math.max.apply(Math, lons);
				var lngEast = Math.min.apply(Math, lons);
				var southWest = L.latLng(latSouth, lngWest);
				var northEast = L.latLng(latNorth, lngEast);
				map.fitBounds(L.latLngBounds(southWest, northEast));
			});
			// prevent reload
			event.preventDefault();
			if (!(window.matchMedia('(min-width: 768px)').matches)) {
				sidebar.close();
			}
		});
		
		$( "#generate_route" ).submit(function( event ) {
			// Remove all polylines
			clearMap();
			lats = [];
			lons = [];
			// draw polyline for route
			drawPolyline( "api1/getroute.php?getroute=getroute"
				+"&start_lat="+$("#start_lat").val()
				+"&start_lon="+$("#start_lon").val()
				+"&end_lat="+$("#end_lat").val()
				+"&end_lon="+$("#end_lon").val() );
			// prevent reload
			var latSouth = Math.max.apply(Math, lats);
			var latNorth = Math.min.apply(Math, lats);
			var lngWest = Math.max.apply(Math, lons);
			var lngEast = Math.min.apply(Math, lons);
			var southWest = L.latLng(latSouth, lngWest);
			var northEast = L.latLng(latNorth, lngEast);
			map.fitBounds(L.latLngBounds(southWest, northEast));
			
			event.preventDefault();
		});
		
		$("#routing_start_p").click(function() {
			var uri = "api1/getroute.php?getid=getid&lat=" + $("#start_lat").val() + "&lon=" + $("#start_lon").val();
			$.getJSON(uri, function (json) {
				$('#routing_start_id').replaceWith("<p id=\"routing_start_id\">(id=" + json.id + ")</p>");
			});
		});
		
		$("#routing_end_p").click(function() {
			var uri = "api1/getroute.php?getid=getid&lat=" + $("#end_lat").val() + "&lon=" + $("#end_lon").val();
			$.getJSON(uri, function (json) {
				$('#routing_end_id').replaceWith("<p id=\"routing_end_id\">(id=" + json.id + ")</p>");
			});
		});
	</script>
<?php
pg_close ( $pg );
$my->close ();
?>
</body>
</html>
