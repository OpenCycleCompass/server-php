<?php

class ibisDem {
	// variables
	private $pg;
	
	private static $storedSqlQ_IBIS_getAltitude = "
CREATE OR REPLACE FUNCTION IBIS_getAltitude(pnt geometry)
	RETURNS numeric(16,8) AS
$$
DECLARE
	altitude numeric(16,8);
	a1 numeric(16,8);
	a2 numeric(16,8);
	a3 numeric(16,8);
	d1 numeric(16,8);
	d2 numeric(16,8);
	d3 numeric(16,8);
BEGIN
	CREATE TEMP TABLE tt AS SELECT altitude, ST_Distance(the_geom, pnt) AS dist FROM altitude ORDER BY the_geom <-> pnt LIMIT 3;
	ALTER TABLE tt ADD COLUMN c_id SERIAL;
	SELECT alt FROM tt WHERE c_id = 1 INTO a1;
	SELECT alt FROM tt WHERE c_id = 2 INTO a2;
	SELECT alt FROM tt WHERE c_id = 3 INTO a3;
	SELECT dist FROM tt WHERE c_id = 1 INTO d1;
	SELECT dist FROM tt WHERE c_id = 2 INTO d2;
	SELECT dist FROM tt WHERE c_id = 2 INTO d3;
	altitude := ( (a1*(d2 + d3)) + (a2*(d1 + d3)) + (a3*(d1 + d2)) ) / (2*(d1 + d2 + d3));
	RETURN altitude;
END;
$$
LANGUAGE 'plpgsql';";
	// Usage: "SELECT IBIS_getAltitude(ST_SetSRID(ST_Point(5.900, 55.000),4326));"
	
	public function __construct($p_pg) {
		$this->pg = $p_pg;
	}
	
	public function updateStoredSqlProcedure() {
		$result = pg_query($this->pg, $storedSqlQ_IBIS_getAltitude);
		if($result) {
			pg_free_result($result);
			return array("success" => true);
		} else {
			return array("error" => "Error creating IBIS_getAltitude procedure in database: ".pg_last_error($this->pg));
		}
	}
	
	
	private function downloadDem($url) {
		$filename = "dgm.xyz.zip";
		$ch = curl_init($url);
		$fp = fopen($filename, "w");

		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_HEADER, 0);

		curl_exec($ch);
		curl_close($ch);
		fclose($fp);
		return $filename;
	}
	
	private function unzipDem($zipname) {
		$filename = "/srv/www/tmp/dgm200.utm32s.xyzascii/dgm200/dgm200_utm32s.xyz";
		$zip = new ZipArchive;
		if ($zip->open($zipname) === TRUE) {
			$zip->extractTo("/srv/www/tmp/");
			$zip->close();
			//var_dump(scandir("/srv/www/tmp/"));
		} else {
			return -1;
		}
		return $filename;
	}
	
	private function sqlImportDem($demfile) {
		$fp = fopen($demfile,'r');
		$line_cnt = 0;
		$row_cnt = 0;
		$err_cnt = 0;
		
		$query = "";
		
		while($line = fgets($fp, 50)) {
			// Sample line: "3500000 5600000 57.10"
			$line_cnt++;
			
			$arr = explode(" ", trim($line));
			
			$lat = floatval($arr[0]);
			$lon = floatval($arr[1]);
			$alt = floatval($arr[2]);
			
			// UTM32: SRID = 2077
			$query .= "INSERT INTO altitude (the_geom, altitude, source) VALUES (ST_Transform(ST_SetSRID(ST_Point(".$lon.", ".$lat."),2077),4326), ".$alt.", 0);
";
			if(($line_cnt%10000)==0) {
				$result = pg_query($this->pg, $query);
				if($result) {
					pg_free_result($result);
					$row_cnt++;
				} else {
					$err_cnt++;
				}
			}
		}
		if(($line_cnt%10000)!=0) {
			$result = pg_query($this->pg, $query);
			if($result) {
				pg_free_result($result);
				$row_cnt++;
			} else {
				$err_cnt++;
			}
		}
		fclose($fp);
		return array("lines" => $line_cnt, "rows" => $row_cnt, "err" => $err_cnt);
	}
	
	public function importDem($url = "http://sg.geodatenzentrum.de/web_download/dgm/dgm200/utm32s/xyzascii/dgm200.utm32s.xyzascii.zip") {
		$xyzfile = $this->unzipDem($this->downloadDem($url));
		if($xyzfile != -1) {
			return $this->sqlImportDem($xyzfile);
		} else {
			return array("error" => "download/unzip failed");
		}
	}
	
	public function deleteImportedDem() {
		$query = "DELETE FROM altitude WHERE source = 0;";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
			return array("success" => "Imported values in table altitude deleted.");
		} else {
			return array("error" => "Deleting imported values in table altitude failed: ".pg_last_error($this->pg));
		}
	}
}

