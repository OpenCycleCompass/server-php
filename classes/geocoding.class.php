<?php

class Geocoding {
	// variables
	private $nominatim_url;
	private $httpsettings;
	
	public function __construct($nominatim_url = "https://localhost/nominatim/", $ssl_verify = false) {
		$this->nominatim_url = $nominatim_url;
		$this->httpsettings = stream_context_create(
				array(
					"ssl" => array("verify_peer"=>$ssl_verify,"verify_peer_name"=>$ssl_verify), 
					"https" => array("user_agent" => "iBis Bike Info and Routing")
				) );
	}
	
	public function getCoordByAddr($str) {
		$url = $this->nominatim_url
				.'search.php?format=json&polygon=0&addressdetails=0&limit=1&q='
				.str_replace(" ", "+", $str);
		$raw = file_get_contents($url, false, $this->httpsettings);
		$json = json_decode($raw, true);
		if(isset($json[0]["lat"]) && isset($json[0]["lon"])){
			$lat = floatval($json[0]["lat"]);
			$lon = floatval($json[0]["lon"]);
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
		
		$url = $this->nominatim_url
				.'reverse.php?format=json&zoom=10&addressdetails=1'
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
	
	public function getCityByCoord($lat, $lon) {
		$lon = floatval($lon);
		$lat = floatval($lat);
		$url = $this->nominatim_url
				.'reverse.php?format=json&zoom=10&addressdetails=1'
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
}

