<?php

class mapMatching {
	// variables
	private $pg;
	private $dumpedways_prefix = "tt_dumpedways_";
	private $dumpedpoints_prefix = "tt_dumpedpoints_";
	private $matchedways_prefix = "tt_matchedways_";
	private $routedways_prefix = "tt_routedways_";
	private $pg_temp_qualifier = "";
	private $ttid_nodes;
	private $ttid_edges;
	private $ttid_dumpedways;
	//private $ttid_dumpedpoints;
	private $ttid_matchedways;
	private $ttid_routedways;
	private $node_cnt;
	
	public function __construct($p_pg, $p_ttid_nodes, $p_ttid_edges, $ttunique) {
		$this->pg = $p_pg;
		$this->ttid_nodes = $p_ttid_nodes;
		$this->ttid_edges = $p_ttid_edges;
		$this->ttid_dumpedways = $this->dumpedways_prefix.$ttunique;
		//$this->ttid_dumpedpoints = $this->dumpedpoints_prefix.$ttunique;
		$this->ttid_matchedways = $this->matchedways_prefix.$ttunique;
		$this->ttid_routedways = $this->routedways_prefix.$ttunique;
	}

	public function matchTrack() {
	
		// Get min/max lat/lon (bounding box)
		$lat_min = 0;
		$lat_max = 0;
		$lon_min = 0;
		$lno_max = 0;
		$query = "SELECT lat FROM ".$this->ttid_nodes." ORDER BY lat DESC LIMIT 1;";
		$result = pg_query($this->pg, $query);
		if($result) {
			$row = pg_fetch_assoc($result);
			$lat_max = $row["lat"];
			pg_free_result($result);
		} else {
			return array("error" => "Error calculating track bounds: ".pg_last_error($this->pg));
		}
		$query = "SELECT lat FROM ".$this->ttid_nodes." ORDER BY lat ASC LIMIT 1;";
		$result = pg_query($this->pg, $query);
		if($result) {
			$row = pg_fetch_assoc($result);
			$lat_min = $row["lat"];
			pg_free_result($result);
		} else {
			return array("error" => "Error calculating track bounds: ".pg_last_error($this->pg));
		}
		$query = "SELECT lon FROM ".$this->ttid_nodes." ORDER BY lat DESC LIMIT 1;";
		$result = pg_query($this->pg, $query);
		if($result) {
			$row = pg_fetch_assoc($result);
			$lon_max = $row["lon"];
			pg_free_result($result);
		} else {
			return array("error" => "Error calculating track bounds: ".pg_last_error($this->pg));
		}
		$query = "SELECT lon FROM ".$this->ttid_nodes." ORDER BY lat ASC LIMIT 1;";
		$result = pg_query($this->pg, $query);
		if($result) {
			$row = pg_fetch_assoc($result);
			$lon_min = $row["lon"];
			pg_free_result($result);
		} else {
			return array("error" => "Error calculating track bounds: ".pg_last_error($this->pg));
		}
		$return["lat_min"] = $lat_min;
		$return["lat_max"] = $lat_max;
		$return["lon_min"] = $lon_min;
		$return["lon_max"] = $lon_max;

		// Dump all point from ways in bounding box around the Track into temp table ".$ttid_dumpedways."
// 		$query = "CREATE ".$this->pg_temp_qualifier." TABLE ".$this->ttid_dumpedways." AS
// 			SELECT the_geom, gid, source, target, osm_id
// 			FROM ways
// 			WHERE ways.the_geom && ST_MakeEnvelope(".$lon_min.", ".$lat_min.", ".$lon_max.", ".$lat_max.", 4326);";
		$query = "CREATE ".$this->pg_temp_qualifier." TABLE ".$this->ttid_dumpedways." AS
			SELECT * FROM ways
			WHERE ways.the_geom && ST_MakeEnvelope(".$lon_min.", ".$lat_min.", ".$lon_max.", ".$lat_max.", 4326);";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error creating temporary table ".$this->ttid_dumpedways." in database: ".pg_last_error($this->pg));
		}
		// create id column: c_id: autoincrements as of typ SERIAL
		$query = "ALTER TABLE ".$this->ttid_dumpedways." ADD COLUMN c_id SERIAL;";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error adding c_id column to ".$this->ttid_dumpedways." table.");
		}

	/*
		// Create temp table ".$ttid_dumpedpoints."
		$query = "CREATE ".$this->pg_temp_qualifier." TABLE ".$this->ttid_dumpedpoints."(the_geom geometry(Point,4326), bearing double precision, way_id integer, c_id SERIAL);";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error creating temporary table ".$this->ttid_dumpedpoints." in database: ".pg_last_error($this->pg));
		}

		// Create (or update) PL/PgSQL function to extract information from linestrings needed to match track
		$query = "
		CREATE OR REPLACE FUNCTION IBIS_prepareLineString(lnestr geometry, id integer)
			RETURNS TABLE (the_geom geometry, bearing real, way_id integer) AS
		$$
		BEGIN
			DROP TABLE IF EXISTS tt_way_points;
			CREATE TEMP TABLE tt_way_points AS
				SELECT (dp).path[1] AS index, (dp).geom AS t_the_geom
				FROM (SELECT ST_DumpPoints(lnestr) AS dp
				) AS foo;
			ALTER TABLE tt_way_points ADD COLUMN c_id SERIAL;
			ALTER TABLE tt_way_points ADD COLUMN t_way_id integer;
			UPDATE tt_way_points SET t_way_id = id;
			ALTER TABLE tt_way_points ADD COLUMN t_bearing real;
			UPDATE tt_way_points SET t_bearing = 0;
			UPDATE tt_way_points AS a
				SET t_bearing = ST_Azimuth(a.t_the_geom, b.t_the_geom)
				FROM tt_way_points AS b
				WHERE b.c_id = a.c_id+1;
			UPDATE tt_way_points AS a
				SET t_bearing = b.t_bearing
				FROM tt_way_points AS b
				WHERE a.t_bearing = 0 AND b.c_id+1 = a.c_id;
			RETURN QUERY SELECT t_the_geom, t_bearing, t_way_id FROM tt_way_points;
		END;
		$$
		LANGUAGE 'plpgsql';";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error creating IBIS_prepareLineString function: ".pg_last_error($this->pg));
		}

		// Extract points (and bearing) from every LineString from table ".$ttid_dumpedways.", calculate bearing and insert into table ".$ttid_dumpedpoints."
		//$query = "CREATE ".$this->pg_temp_qualifier." TABLE tt_prepared_linestring AS SELECT IBIS_prepareLineString(the_geom, id) FROM ".$ttid_dumpedways.";";
		$query = "INSERT INTO ".$this->ttid_dumpedpoints." SELECT (IBIS_prepareLineString(t.the_geom, t.way_id)).* FROM ".$this->ttid_dumpedways." t;
		CREATE INDEX the_geom_gist_idx ON ".$this->ttid_dumpedpoints." USING GIST ( the_geom );";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error executing IBIS_prepareLineString() on ".$this->ttid_dumpedways.": ".pg_last_error($this->pg));
		}
	*/

		// Create (or update) PL/PgSQL function to extract information from linestrings needed to match track
	/*	$query = "CREATE ".$this->pg_temp_qualifier." TABLE ".$this->ttid_matchedways."
			(gid integer,
			 osm_id bigint,
			 cost numeric(16,8),
			 c_id SERIAL
			);";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error creating matchedways table: ".pg_last_error($this->pg));
		}
	*/

		$query = "SELECT COUNT(*) FROM ".$this->ttid_nodes.";";
		$result = pg_query($this->pg, $query);
		if($result) {
			if ($line = pg_fetch_array($result)) {
				$node_cnt = $line[0];
			} else {
				return array("error" => "Error reading from temporary table in database: ".pg_last_error($this->pg));
			}
			pg_free_result($result);
		} else {
			return array("error" => "Error reading from temporary table in database: ".pg_last_error($this->pg));
		}
		
		$segment_cnt = 0;
		do {
			$segment_cnt++;
			$segment_size = $node_cnt / $segment_cnt;
		} while($segment_size > 5000);
		$start = 1;
		$end = $segment_size;
		$return = array();
		for($i = 1; $i <= $segment_cnt; $i++) {
			$return[] = $this->matchTrackSegment(
				round(($i-1)*$segment_size)-10,
				round(($i)*$segment_size)+10 );
		}

		// Get num of rows in temp tables
		$query = "SELECT COUNT(*) FROM ".$this->ttid_dumpedways.";";
		$result = pg_query($this->pg, $query);
		if($result) {
			if ($line = pg_fetch_array($result)) {
				$return["rows_dumpedways"] = $line[0];
			} else {
				return array("error" => "Error reading from temporary table in database: ".pg_last_error($this->pg));
			}
			pg_free_result($result);
		} else {
			return array("error" => "Error reading from temporary table in database: ".pg_last_error($this->pg));
		}
		/*$query = "SELECT COUNT(*) FROM ".$this->ttid_dumpedpoints.";";
		$result = pg_query($this->pg, $query);
		if($result) {
			if ($line = pg_fetch_array($result)) {
				$return["rows_dumpedpoints"] = $line[0];
			} else {
				return array("error" => "Error reading from temporary table in database: ".pg_last_error($this->pg));
			}
			pg_free_result($result);
		} else {
			return array("error" => "Error reading from temporary table in database: ".pg_last_error($this->pg));
		}*/
		$query = "SELECT COUNT(*) FROM ".$this->ttid_routedways.";";
		$result = pg_query($this->pg, $query);
		if($result) {
			if ($line = pg_fetch_array($result)) {
				$return["rows_matchedways"] = $line[0];
			} else {
				return array("error" => "Error reading from temporary table in database: ".pg_last_error($this->pg));
			}
			pg_free_result($result);
		} else {
			return array("error" => "Error reading from temporary table in database: ".pg_last_error($this->pg));
		}

		// drop temp tables
		$query = "DROP TABLE ".$this->ttid_dumpedways.";";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error dropping ".$this->ttid_dumpedways." table. ".pg_last_error($this->pg));
		}

		$return["ttid"] = $this->ttid_routedways;
		$return["success"] = true;
		return $return;
	}

	private function matchTrackSegment($start_c_id, $end_c_id) {
		$return = array();

		// Import gps points from rawdata_server_php table into temporary table $ttid_nodes
		//$query = "CREATE TEMP TABLE ".$ttid_nodes." AS
		$query = "ALTER TABLE ".$this->ttid_dumpedways." ADD COLUMN dist double precision;";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error creating temporary table in database: ".pg_last_error($this->pg));
		}

		// Add column for osm_id of nearest way (if matched)
		$query = "ALTER TABLE ".$this->ttid_nodes." ADD COLUMN near_osm_id bigint;";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error creating temporary table in database: ".pg_last_error($this->pg));
		}

		// Set dist column in ttid_dumpedways to 100000 for every way
		$query = "UPDATE ".$this->ttid_dumpedways." SET dist = 100000;";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error setting dist to 100000  in ".$this->ttid_dumpedways.". ".pg_last_error($this->pg));
		}

		// Set dist to low value (1...10) where ways are near to nodes in track segment
		$query = "SELECT c_id, ST_AsText(the_geom) AS the_geom FROM ".$this->ttid_nodes.";";
		$result = pg_query($this->pg, $query);
		if($result) {
			while($row = pg_fetch_assoc($result)) {
				// GIST index instead of ORDER BY ST_Distance() ?
				$query1 = "SELECT osm_id, (ST_Distance(the_geom, ST_SetSRID(ST_GeomFromText('".$row["the_geom"]."'),4326))) AS dist
					FROM ".$this->ttid_dumpedways."
					ORDER BY dist ASC LIMIT 1;";
					//ORDER BY (ST_Distance(the_geom, ST_SetSRID(ST_GeomFromText('".$row["the_geom"]."'),4326))) ASC LIMIT 1;";
				//echo($query1);
				$result1 = pg_query($this->pg, $query1);
				if($result1) {
					$row1 = pg_fetch_assoc($result1);
					$osm_id = $row1["osm_id"];
					$dist = $row1["dist"];
					pg_free_result($result1);
				} else {
					$nid = 0;
					return array("error" => "Error finding nearest way from ways table: ".pg_last_error()." || ".$query1);
				}
				
				// TODO: calcutate $dist ??
				
				//$query2 = "UPDATE ".$ttid_edges."  SET osm_id = ".$nid." WHERE c_id = ".$row["c_id"].";";
				//$query2 = "INSERT INTO ".$this->ttid_matchedways." (osm_id, cost) VALUES (".$osm_id.", ".$row["cost"].");";
				$query2 = "UPDATE ".$this->ttid_dumpedways." SET dist = ".$dist." WHERE osm_id = ".$osm_id.";";
				// TODO: Update only if $dist is lower than dist in row
				$result2 = pg_query($this->pg, $query2);
				if(!$result2) {
					return array("error" => "Error saving nearest way to ways table: ".pg_last_error());
				}
				//if(pg_affected_rows($this->pg)==1) {
				if(true) {
					$query3 = "UPDATE ".$this->ttid_nodes." SET near_osm_id = ".$osm_id." WHERE c_id = ".$row["c_id"].";";
					$result3 = pg_query($this->pg, $query3);
					if($result3) {
						pg_free_result($result3);
					} else {
						return array("error" => "Error updating nodes table with osm_id of nearest node: ".pg_last_error());
					}
				}
				pg_free_result($result2);
			}
			pg_free_result($result);
		} else {
			return array("error" => "Error selecting from ttid_nodes table for dist calculation. ".pg_last_error($this->pg));
		}


		// Find vertex id from nearest node of start node
		$query = "SELECT ST_AsText(the_geom) AS the_geom FROM ".$this->ttid_nodes." ORDER BY c_id DESC LIMIT 1;";
		$result = pg_query($this->pg, $query);
		if(!$result) {
			return array("error" => "Error finding way near start point: ".pg_last_error());
		}
		$row = pg_fetch_assoc($result);
		$query = "SELECT source FROM ".$this->ttid_dumpedways." ORDER BY ST_Distance(the_geom, ST_SetSRID(ST_GeomFromText('".$row["the_geom"]."'),4326)) ASC LIMIT 1;";
		$result = pg_query($this->pg, $query);
		if(!$result) {
			return array("error" => "Error finding start_gid: ".pg_last_error());
		}
		$row = pg_fetch_assoc($result);
		$start_id = $row["source"];
		$return["start_id"] = $start_id;

		// Find vertex id from nearest node of end node
		$query = "SELECT ST_AsText(the_geom) AS the_geom FROM ".$this->ttid_nodes." ORDER BY c_id ASC LIMIT 1;";
		$result = pg_query($this->pg, $query);
		if(!$result) {
			return array("error" => "Error finding way near start point: ".pg_last_error());
		}
		$row = pg_fetch_assoc($result);
		$query = "SELECT source FROM ".$this->ttid_dumpedways." ORDER BY ST_Distance(the_geom, ST_SetSRID(ST_GeomFromText('".$row["the_geom"]."'),4326)) ASC LIMIT 1;";
		$result = pg_query($this->pg, $query);
		if(!$result) {
			return array("error" => "Error finding end_gid: ".pg_last_error());
		}
		$row = pg_fetch_assoc($result);
		$end_id = $row["source"];
		$return["end_id"] = $end_id;


		// Djikstra (or A* ?) routing over ttid_dumpedways table
		// Generate route
		$query = "CREATE ".$this->pg_temp_qualifier." TABLE ".$this->ttid_routedways." AS
		SELECT seq, id1 AS node, id2 AS edge, b.gid AS gid, b.osm_id AS osm_id, -1::numeric(16,8) AS cost, b.source AS source, b.target AS target
		FROM pgr_dijkstra('SELECT gid AS id, source::integer, target::integer, dist AS cost
		 FROM ".$this->ttid_dumpedways." ',"
		  .$start_id.", ".$end_id.", false, false) a 
		LEFT JOIN ".$this->ttid_dumpedways." b ON (a.id2 = b.gid);
		--SELECT seq, gid, osm_id FROM ".$this->ttid_routedways." ORDER BY seq;";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error routing in routedways table: ".pg_last_error()." || ".$query);
		}

		// Set dist column in ttid_dumpedways to 100000 for every way
		$query = "ALTER TABLE ".$this->ttid_routedways." ADD COLUMN reverse boolean;";
		$result = pg_query($this->pg, $query);
		if(!$result) {
			return array("error" => "Error adding reverse column to routedways table. ".pg_last_error($this->pg));
		}
		pg_free_result($result);

		// Set dist column in ttid_dumpedways to 100000 for every way
		$query = "UPDATE ".$this->ttid_routedways." SET reverse = TRUE;";
		$result = pg_query($this->pg, $query);
		if(!$result) {
			return array("error" => "Error setting reverse column to FALSE in routedways table. ".pg_last_error($this->pg));
		}
		pg_free_result($result);

		$query = "SELECT seq, source, target FROM ".$this->ttid_routedways." ORDER BY seq ASC;";
		$result = pg_query($this->pg, $query);
		if(!$result) {
			return array("error" => "Error reading from routedways table: ".pg_last_error());
		}
		$last_source= 0;
		$last_target = 0;
		while($row = pg_fetch_assoc($result)) {
			if(intval($row["seq"]) != 0) {
				if(($last_target == intval($row["source"])) || ($last_source == intval($row["source"]))) {
					// Set reverse column in ttid_routedways to TRUE
					$query2 = "UPDATE ".$this->ttid_routedways." SET reverse = FALSE WHERE seq = ".$row["seq"].";";
					$result2 = pg_query($this->pg, $query2);
					if(!$result2) {
						return array("error" => "Error setting reverse column to TRUE where seq = ".$row["seq"]." in routedways table. ".pg_last_error($this->pg));
					}
					pg_free_result($result2);
				}
			}
			if(intval($row["seq"]) == 1) {
				if(($last_target == intval($row["source"])) || ($last_target == intval($row["target"]))) {
					// Set reverse column in ttid_routedways to TRUE
					$query2 = "UPDATE ".$this->ttid_routedways." SET reverse = FALSE WHERE seq = 0;";
					$result2 = pg_query($this->pg, $query2);
					if(!$result2) {
						return array("error" => "Error setting reverse column to TRUE where seq = 0 (fix) in routedways table. ".pg_last_error($this->pg));
					}
					pg_free_result($result2);
				}
			}
			$last_source = intval($row["source"]);
			$last_target = intval($row["target"]);
		}
		pg_free_result($result);
		
		// Calc average cost from nodes for each way
		// TODO: Implement more efficient in SQL
		$query = "SELECT seq, osm_id FROM ".$this->ttid_routedways." WHERE osm_id IS NOT NULL;";
		$result = pg_query($this->pg, $query);
		if(!$result) {
			return array("error" => "Error reading from routedways table: ".pg_last_error());
		}
		while($row = pg_fetch_assoc($result)) {
			$query2 = "SELECT AVG(cost) AS cost, near_osm_id FROM ".$this->ttid_nodes." WHERE near_osm_id = ".$row["osm_id"]." AND cost IS NOT NULL GROUP BY near_osm_id;";
			$result2 = pg_query($this->pg, $query2);
			if(!$result2) {
				return array("error" => "Error reading cost from nodes table: ".pg_last_error());
			}
			if($row2 = pg_fetch_assoc($result2)) {
				$query3 = "UPDATE ".$this->ttid_routedways." SET cost = ".$row2["cost"]." WHERE osm_id = ".$row["osm_id"].";";
				$result3 = pg_query($this->pg, $query3);
				if(!$result3) {
					return array("error" => "Error update cost in routedways table: ".pg_last_error());
				}
				pg_free_result($result3);
			}
			pg_free_result($result2);
		}
		pg_free_result($result);
		$return["success"] = true;
		return $return;
	}
}
