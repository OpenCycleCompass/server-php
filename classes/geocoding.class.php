<?php

class Geocoding {
	// variables
	private $url;
	private $httpsettings;

	public function __construct($url = "https://photon.komoot.de/", $ssl_verify = true) {
		$this->url = $url;
		$this->reverse_url = $reverse_url;
		$this->httpsettings = stream_context_create(
				array(
					"ssl" => array("verify_peer"=>$ssl_verify,"verify_peer_name"=>$ssl_verify), 
					"https" => array("user_agent" => "iBis Bike Info and Routing")
				) );
	}

	public function getCoordByAddr($str) {
		$url = $this->url . "api?limit=1&q="
				.str_replace(" ", "+", $str);
		$raw = file_get_contents($url, false, $this->httpsettings);
		$json = json_decode($raw, true);
		if(isset($json["features"][0]["geometry"]["coordinates"])){
			$lat = floatval($json["features"][0]["geometry"]["coordinates"][1]);
			$lon = floatval($json["features"][0]["geometry"]["coordinates"][0]);
			return array("lon" => $lon, "lat" => $lat);
		} else {
			return array("error" => true, "json" => $json);
		}
	}

	public function getCityByOsmId($osmid, $osmtype) {
		if(!($osmtype == 'N' || $osmtype == 'W' || $osmtype == 'R')) {
			return array("error" => "OSM type (second parameter) must be 'N', 'W' or 'R'");
		}
		$osmid = intval($osmid);
		$url = "http://nominatim.openstreetmap.org/"
				.'reverse?format=json&zoom=10&addressdetails=1'
				.'&osm_type='.$osmtype
				.'&osm_id='.$osmid;
		$raw = file_get_contents($url, false, $this->httpsettings);
		$json = json_decode($raw, true);
		if(isset($json["address"]["city"]) && isset($json["address"]["city_district"])){
			return array("city" => $json["address"]["city"],
					"city_district" => $json["address"]["city_district"]);
		}
		else if(isset($json["address"]["city"])){
			return array("city" => $json["address"]["city"]);
		}
		else {
			return array("error" => true, "json" => $json);
		}
	}

	public function getCityByOsmId_new($osmid, $osmtype) {
		return array("error" => true, "info" => "Currently not supported by photon.");
	}

	// Old (Nominatim): https://localhost/nominatim/reverse.php?format=json&zoom=10&addressdetails=1$lat={}&lon={} 
	public function getCityByCoord($lat, $lon) {
		$lon = floatval($lon);
		$lat = floatval($lat);
		$url = "http://nominatim.openstreetmap.org/"
				.'reverse?format=json&zoom=10&addressdetails=1'
				.'&lat='.$lat
				.'&lon='.$lon;
		$raw = file_get_contents($url, false, $this->httpsettings);
		$json = json_decode($raw, true);
		if(isset($json["address"]["city"]) && isset($json["address"]["city_district"])){
			return array("city" => $json["address"]["city"],
					"city_district" => $json["address"]["city_district"]);
		}
		else if(isset($json["address"]["city"])){
			return array("city" => $json["address"]["city"]);
		}
		else {
			return array("error" => true, "json" => $json);
		}
	}

	public function getCityByCoord_new($lat, $lon) {
		$lon = floatval($lon);
		$lat = floatval($lat);
		$url = $this->url
				. "reverse?"
				."&lat=".$lat
				."&lon=".$lon;
		$raw = file_get_contents($url, false, $this->httpsettings);
		$json = json_decode($raw, true);
		if(isset($json["features"][0]["properties"]["city"])) {
			return array("city" => $json["features"][0]["properties"]["city"]);
		}
		else {
			return array("error" => true, "json" => $json);
		}
	}
}

