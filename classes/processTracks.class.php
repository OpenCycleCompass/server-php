<?php

class processTracks {
	// variables
	private $pg;
	private $my;
	private $nodes_prefix;
	private $edges_prefix;
	private $dumpedways_prefix = "tt_dumpedways_";
	private $dumpedpoints_prefix = "tt_dumpedpoints_";
	
	public function __construct($p_pg, $p_my, $p_nodes_prefix, $p_edges_prefix) {
		$this->pg = $p_pg;
		$this->my = $p_my;
		$this->nodes_prefix = $p_nodes_prefix;
		$this->edges_prefix = $p_edges_prefix;
	}
	
	
	private function prepareTrack($track_id) {
		// verify track_id ??
		
		// $info variable for output track processing info
		$info = array();

		// Unique ttid (temporary table identifier) generated from timestamp and track_id
		$ttunique = str_replace("-", "_", str_replace(".", "_", uniqid("", true)."__".$track_id));
		$ttid_nodes = $this->nodes_prefix.$ttunique;
		$ttid_edges = $this->edges_prefix.$ttunique;
		$ttid_dumpedways = $this->dumpedways_prefix.$ttunique;
		$ttid_dumpedpoints = $this->dumpedpoints_prefix.$ttunique;
		$ttid_dumpedpoints2 = $this->dumpedpoints_prefix."2".$ttunique;

		// Import gps points from rawdata_server_php table into temporary table $ttid_nodes
		//$query = "CREATE TEMP TABLE ".$ttid_nodes." AS
		$query = "CREATE TEMP TABLE ".$ttid_nodes." AS
			SELECT lat, lon, alt, time, id, speed, the_geom
			FROM rawdata_server_php
			WHERE track_id = '".pg_escape_string($this->pg, $track_id)."'
			ORDER BY time;";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error creating temporary table in database: ".pg_last_error($this->pg));
		}
		
		// check if track is large enougth: more than 10 gps points
		$query = "SELECT COUNT(id) FROM ".$ttid_nodes.";";
		$result = pg_query($this->pg, $query);
		if($result) {
			if ($line = pg_fetch_array($result)) {
				if ($line[0] < 10) {
					return array("error" => "To less rows/points in database (".$line[0]." rows in table ".$ttid_nodes."; at least 10 rows needed)");
				} else {
					$info["nodes"] = $line[0];
				}
			} else {
				return array("error" => "Error reading from temporary table in database.");
			}
			pg_free_result($result);
		} else {
			return array("error" => "Error reading from temporary table in database.");
		}
		
		// create id column: c_id: autoincrements as of typ SERIAL
		$query = "ALTER TABLE ".$ttid_nodes." ADD COLUMN c_id SERIAL;";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error adding c_id column.");
		}
		
		// create edge table 
		$query = "CREATE TEMP TABLE ".$ttid_edges." AS
		SELECT 
			a.the_geom AS start_geom, 
			b.the_geom AS end_geom, 
			a.time AS start_time, 
			b.time AS end_time, 
			a.id AS start_id, 
			b.id AS end_id, 
			a.speed AS start_speed, 
			b.speed AS end_speed, 
			a.c_id AS start_c_id, 
			b.c_id AS end_c_id,
			ST_Azimuth(a.the_geom, b.the_geom) AS bearing
		FROM ".$ttid_nodes." a 
		LEFT JOIN ".$ttid_nodes." b 
			ON b.c_id-a.c_id=1;
		";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error creating temporary edges table in database: ".pg_last_error($this->pg));
		}
		
		// create id column: c_id: autoincrements as of typ SERIAL
		$query = "ALTER TABLE ".$ttid_edges." ADD COLUMN c_id SERIAL;";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error adding c_id column.");
		}
		
		// create id column: c_id: autoincrements as of typ SERIAL
		$query = "DELETE FROM ".$ttid_edges." WHERE start_c_id IS NULL OR end_c_id IS NULL;";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error removing rows containing NULL values.");
		}
		
		// check if track is large enougth: more than 10 gps points
		$query = "SELECT COUNT(c_id) FROM ".$ttid_edges.";";
		$result = pg_query($this->pg, $query);
		if($result) {
			if ($line = pg_fetch_array($result)) {
				if ($line[0] < 10) {
					return array("error" => "To less rows/points in database (".$line[0]." rows in table ".$ttid_edges."; at least 10 rows needed)");
				} else {
					$info["edges"] = $line[0];
				}
			} else {
				return array("error" => "Error reading from temporary table in database: ".pg_last_error($this->pg));
			}
			pg_free_result($result);
		} else {
			return array("error" => "Error reading from temporary table in database: ".pg_last_error($this->pg));
		}
		
		// create column for calculated speed: c_speed
		$query = "ALTER TABLE ".$ttid_edges." ADD COLUMN c_speed numeric(16,8);";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error adding c_speed column: ".pg_last_error($this->pg));
		}
		
		// create column for calculated distance: c_dist
		$query = "ALTER TABLE ".$ttid_edges." ADD COLUMN c_dist numeric(16,8);";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error adding c_dist column: ".pg_last_error($this->pg));
		}
		
		// create column for calculated time difference: c_tdiff
		$query = "ALTER TABLE ".$ttid_edges." ADD COLUMN c_tdiff bigint;";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error adding c_tdiff column: ".pg_last_error($this->pg));
		}
		
		// calculate speed: c_dist
		$query = "UPDATE ".$ttid_edges." SET c_dist = ST_Distance_Sphere(end_geom, start_geom);";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error calculation c_dist column: ".pg_last_error($this->pg));
		}
		
		// remove rows with c_dist > 100.0 (meters)
		$query = "DELETE FROM ".$ttid_edges." WHERE c_dist > 100.0;";
		$result = pg_query($this->pg, $query);
		if($result) {
			$info["deleted_dist_to_large"] = pg_affected_rows($result);
			pg_free_result($result);
		} else {
			return array("error" => "Error removing rows with c_dist > 100.0");
		}
		
		// calculate speed: c_tdiff
		$query = "UPDATE ".$ttid_edges." SET c_tdiff = end_time-start_time;";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error calculation c_tdiff column: ".pg_last_error($this->pg));
		}
		
		// remove rows with c_tdiff > 60 (seconds)
		$query = "DELETE FROM ".$ttid_edges." WHERE c_tdiff > 60;";
		$result = pg_query($this->pg, $query);
		if($result) {
			$info["deleted_tdiff_to_large"] = pg_affected_rows($result);
			pg_free_result($result);
		} else {
			return array("error" => "Error removing rows with c_tdiff > 60");
		}
		
		// remove rows with c_tdiff < 0.5 (seconds)
		$query = "DELETE FROM ".$ttid_edges." WHERE c_tdiff < 0.5;";
		$result = pg_query($this->pg, $query);
		if($result) {
			$info["deleted_tdiff_to_small"] = pg_affected_rows($result);
			pg_free_result($result);
		} else {
			return array("error" => "Error removing rows with c_tdiff < 0.5");
		}
		
		// calculate speed: c_speed
		$query = "UPDATE ".$ttid_edges." SET c_speed = c_dist/c_tdiff;";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error calculation c_speed column: ".pg_last_error($this->pg));
		}
		
		// check if average speed seems serious
		$query = "SELECT AVG(c_speed) FROM ".$ttid_edges.";";
		$result = pg_query($this->pg, $query);
		if($result) {
			if ($line = pg_fetch_array($result)) {
				if ($line[0] > 100) {
					return array("error" => "Avg. speed > 100m/s (".$line[0].")");
				} else if($line[0] < 0.5) {
					return array("error" => "Avg. speed < 1m/s (".$line[0].")");
				} else {
					$info["avg_velocity"] = $line[0];
				}
			} else {
				return array("error" => "Error reading from temporary table in database: ".pg_last_error($this->pg));
			}
			pg_free_result($result);
		} else {
			return array("error" => "Error reading from temporary table in database: ".pg_last_error($this->pg));
		}
		
		// Get total distance
		$query = "SELECT SUM(c_dist) FROM ".$ttid_edges.";";
		$result = pg_query($this->pg, $query);
		if($result) {
			if ($line = pg_fetch_array($result)) {
				$info["total_dist"] = $line[0];
			} else {
				return array("error" => "Error reading from temporary table in database: ".pg_last_error($this->pg));
			}
			pg_free_result($result);
		} else {
			return array("error" => "Error reading from temporary table in database: ".pg_last_error($this->pg));
		}
		
		// remove rows with c_speed > 25.0 m/s = 90 km/h
		$query = "DELETE FROM ".$ttid_edges." WHERE c_speed > 25.0;";
		$result = pg_query($this->pg, $query);
		if($result) {
			$info["deleted_cspeed_to_large"] = pg_affected_rows($result);
			pg_free_result($result);
		} else {
			return array("error" => "Error removing rows with c_speed > 25.0");
		}
		
		// check if average speed seems serious
		$query = "SELECT AVG(c_speed) FROM ".$ttid_edges.";";
		$result = pg_query($this->pg, $query);
		if($result) {
			if ($line = pg_fetch_array($result)) {
				$avg_speed = $line[0];
				if ($line[0] > 100) {
					return array("error" => "Avg. speed > 100m/s (".$line[0].")");
				} else if($line[0] < 0.5) {
					return array("error" => "Avg. speed < 1m/s (".$line[0].")");
				} else {
					$info["avg_velocity_processed"] = $line[0];
				}
			} else {
				return array("error" => "Error reading from temporary table in database.");
			}
			pg_free_result($result);
		} else {
			return array("error" => "Error reading from temporary table in database.");
		}
		
		// Get total distance
		$query = "SELECT SUM(c_dist) FROM ".$ttid_edges.";";
		$result = pg_query($this->pg, $query);
		if($result) {
			if ($line = pg_fetch_array($result)) {
				$info["total_dist_processed"] = $line[0];
			} else {
				return array("error" => "Error reading from temporary table in database.");
			}
			pg_free_result($result);
		} else {
			return array("error" => "Error reading from temporary table in database.");
		}

		// create column for id of nearest way
		$query = "ALTER TABLE ".$ttid_edges." ADD COLUMN nway_id integer;";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error adding nnode_gid column.");
		}

		// Get min/max lat/lon (bounding box)
		$lat_min = 0;
		$lat_max = 0;
		$lon_min = 0;
		$lno_max = 0;
		$query = "SELECT lat FROM ".$ttid_nodes." ORDER BY lat DESC LIMIT 1;";
		$result = pg_query($this->pg, $query);
		if($result) {
			$row = pg_fetch_assoc($result);
			$lat_max = $row["lat"];
			pg_free_result($result);
		} else {
			return array("error" => "Error calculating track bounds: ".pg_last_error($this->pg));
		}
		$query = "SELECT lat FROM ".$ttid_nodes." ORDER BY lat ASC LIMIT 1;";
		$result = pg_query($this->pg, $query);
		if($result) {
			$row = pg_fetch_assoc($result);
			$lat_min = $row["lat"];
			pg_free_result($result);
		} else {
			return array("error" => "Error calculating track bounds: ".pg_last_error($this->pg));
		}
		$query = "SELECT lon FROM ".$ttid_nodes." ORDER BY lat DESC LIMIT 1;";
		$result = pg_query($this->pg, $query);
		if($result) {
			$row = pg_fetch_assoc($result);
			$lon_max = $row["lon"];
			pg_free_result($result);
		} else {
			return array("error" => "Error calculating track bounds: ".pg_last_error($this->pg));
		}
		$query = "SELECT lon FROM ".$ttid_nodes." ORDER BY lat ASC LIMIT 1;";
		$result = pg_query($this->pg, $query);
		if($result) {
			$row = pg_fetch_assoc($result);
			$lon_min = $row["lon"];
			pg_free_result($result);
		} else {
			return array("error" => "Error calculating track bounds: ".pg_last_error($this->pg));
		}
		$info["lat_min"] = $lat_min;
		$info["lat_max"] = $lat_max;
		$info["lon_min"] = $lon_min;
		$info["lon_max"] = $lon_max;

		// Dump all point from ways in bounding box around the Track into temp table ".$ttid_dumpedways."
		$query = "CREATE TEMP TABLE ".$ttid_dumpedways." AS
			SELECT the_geom, gid AS way_id
			FROM ways
			WHERE ways.the_geom && ST_MakeEnvelope(".$lon_min.", ".$lat_min.", ".$lon_max.", ".$lat_max.", 4326);";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error creating temporary table ".$ttid_dumpedways." in database: ".pg_last_error($this->pg));
		}
		// create id column: c_id: autoincrements as of typ SERIAL
		$query = "ALTER TABLE ".$ttid_dumpedways." ADD COLUMN c_id SERIAL;";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error adding c_id column to ".$ttid_dumpedways." table.");
		}
		
		// Create temp table ".$ttid_dumpedpoints."
		$query = "CREATE TEMP TABLE ".$ttid_dumpedpoints."(the_geom geometry(Point,4326), bearing double precision, way_id integer, c_id SERIAL);";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error creating temporary table ".$ttid_dumpedpoints." in database: ".pg_last_error($this->pg));
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
		//$query = "CREATE TABLE tt_prepared_linestring AS SELECT IBIS_prepareLineString(the_geom, id) FROM ".$ttid_dumpedways.";";
		$query = "INSERT INTO ".$ttid_dumpedpoints." SELECT (IBIS_prepareLineString(t.the_geom, t.way_id)).* FROM ".$ttid_dumpedways." t;
		CREATE INDEX the_geom_gist_idx ON ".$ttid_dumpedpoints." USING GIST ( the_geom );";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error executing IBIS_prepareLineString() on ".$ttid_dumpedways.": ".pg_last_error($this->pg));
		}

		// copy entrys from ttid_dumpedpoints table two times into ttid_dumpedpoints2 table, with bearing and bearing-180° 
		// (180° deg = PI rad = 3.14159265359)
		$query = "CREATE TABLE ".$ttid_dumpedpoints2." AS
		  SELECT the_geom, bearing, way_id FROM ".$ttid_dumpedpoints."
		  UNION
		  SELECT the_geom, (bearing-3.14159265359), way_id FROM ".$ttid_dumpedpoints.";";
		$result = pg_query($this->pg, $query);
		if(!$result) {
			return array("error" => "Error creating ttid_dumpedpoints2 table from ttid_dumpedpoints".pg_last_error());
		}
		pg_free_result($result);

		// remove rows with bearing = NULL
		$query = "DELETE FROM ".$ttid_edges." WHERE bearing IS NULL;";
		$result = pg_query($this->pg, $query);
		if($result) {
			$info["deleted_bearing_null"] = pg_affected_rows($result);
			pg_free_result($result);
		} else {
			return array("error" => "Error removing rows with bearing = NULL");
		}

		// Find nearest way from ways table
		$query = "SELECT c_id, ST_AsText(start_geom) AS start_geom, bearing FROM ".$ttid_edges.";";
		$result = pg_query($this->pg, $query);
		if($result) {
			while($row = pg_fetch_assoc($result)) {
				//$query1 = "SELECT way_id FROM ".$ttid_dumpedpoints2." ORDER BY (ST_Distance(the_geom, ST_SetSRID(ST_GeomFromText('".$row["start_geom"]."'),4326)) + ABS(bearing-".$row["bearing"].")*5 ) ASC LIMIT 1;";
				$query1 = "SELECT way_id FROM ".$ttid_dumpedpoints2." ORDER BY (ST_Distance(the_geom, ST_SetSRID(ST_GeomFromText('".$row["start_geom"]."'),4326))) ASC LIMIT 1;";
				echo($query1);
				$result1 = pg_query($this->pg, $query1);
				if($result1) {
					$row1 = pg_fetch_row($result1);
					$nid = $row1[0];
					pg_free_result($result1);
				} else {
					$nid = 0;
					return array("error" => "Error finding nearest way from ways table: ".pg_last_error()." || ".$query1);
				}
				$query2 = "UPDATE ".$ttid_edges."  SET nway_id = ".$nid." WHERE c_id = ".$row["c_id"].";";
				$result2 = pg_query($this->pg, $query2);
				if($result1) {
					pg_free_result($result2);
				} else {
					return array("error" => "Error saving nearest way to ways table: ".pg_last_error());
				}
			}
			pg_free_result($result);
		} else {
			return array("error" => "Error populating way_id column.");
		}

		// Add cost column
		$query = "ALTER TABLE ".$ttid_edges." ADD COLUMN cost numeric(16,8);";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error adding cost column.");
		}

		// Calculate cost
		/*
		1.5-(1/(1-exp(".$avg_speed."/c_speed))) ??
		1.5-(1/(".$avg_speed."/c_speed))
		> 1.5-(1/x) -> [0.5, 1.5)
		> 1.5-(0.7^x): [0, Inf] -> [0.5, 1.5)
		> 1.2-(0.7^x)*0.4: [0, Inf] -> [0.5, 1.5)
		*/
		$query = "UPDATE ".$ttid_edges." SET cost = 1.2-((0.8^(".$avg_speed."/c_speed))*0.4) WHERE c_speed != 0;";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error calculation cost column (c_speed != 0).".pg_last_error($this->pg));
		}
		$query = "UPDATE ".$ttid_edges." SET cost = 1.55 WHERE c_speed = 0;";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error calculation cost column (c_speed = 0).".pg_last_error($this->pg));
		}

		// copy cost from TEMP edges table to dyncost table
		$query = "SELECT c_id, nway_id, cost FROM ".$ttid_edges.";";
		$result = pg_query($this->pg, $query);
		if($result) {
			while($row = pg_fetch_assoc($result)) {
				$query1 = "
				INSERT INTO dyncost
				(way_id, track_id, cost)
				VALUES (".$row["nway_id"].", '".$track_id."', ".$row["cost"].")
				;";
				$result1 = pg_query($this->pg, $query1);
				if($result1) {
					pg_free_result($result1);
				}
			}
			pg_free_result($result);
		} else {
			return array("error" => "Error copy cost to dyncost table.".pg_last_error($this->pg));
		}
		
		// Get num of rows in temp tables
		$query = "SELECT COUNT(*) FROM ".$ttid_dumpedways.";";
		$result = pg_query($this->pg, $query);
		if($result) {
			if ($line = pg_fetch_array($result)) {
				$info["rows_dumpedways"] = $line[0];
			} else {
				return array("error" => "Error reading from temporary table in database: ".pg_last_error($this->pg));
			}
			pg_free_result($result);
		} else {
			return array("error" => "Error reading from temporary table in database: ".pg_last_error($this->pg));
		}
		$query = "SELECT COUNT(*) FROM ".$ttid_dumpedpoints.";";
		$result = pg_query($this->pg, $query);
		if($result) {
			if ($line = pg_fetch_array($result)) {
				$info["rows_dumpedpoints"] = $line[0];
			} else {
				return array("error" => "Error reading from temporary table in database: ".pg_last_error($this->pg));
			}
			pg_free_result($result);
		} else {
			return array("error" => "Error reading from temporary table in database: ".pg_last_error($this->pg));
		}
		$query = "SELECT COUNT(*) FROM ".$ttid_dumpedpoints2.";";
		$result = pg_query($this->pg, $query);
		if($result) {
			if ($line = pg_fetch_array($result)) {
				$info["rows_dumpedpoints2"] = $line[0];
			} else {
				return array("error" => "Error reading from temporary table in database: ".pg_last_error($this->pg));
			}
			pg_free_result($result);
		} else {
			return array("error" => "Error reading from temporary table in database: ".pg_last_error($this->pg));
		}
		
		// create id column: c_id: autoincrements as of typ SERIAL
		$query = "DROP TABLE ".$ttid_dumpedways.";
		DROP TABLE ".$ttid_dumpedpoints.";";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error adding c_id column.");
		}
		
		$info["success"] = true;
		return $info;
	}

	public function processAllTracks() {
		// Check whether dyncost table is empty?
		$query = "SELECT track_id FROM `ibis_server-php`.`tracks`;";
		if($result = $this->my->query($query)) {
			$tracknum = 0;
			$error_cnt = 0;
			$success_cnt = 0;
			$res = array();
			while($row = $result->fetch_assoc()) {
				$res[$tracknum] = $this->prepareTrack($row["track_id"]);
				$res[$tracknum]["track_id"] = $row["track_id"];
				$res[$tracknum]["track_num"] = $tracknum;
				if(isset($res[$tracknum]["success"])) {
					$success_cnt++;
				} else {
					$error_cnt++;
				}
				$tracknum++;
			}
			$res["success"] = $success_cnt." of ".$tracknum." tracks successfully processed.";
			$res["error"] = $error_cnt." of ".$tracknum." tracks failed.";
			return $res;
		} else {
			return array("error" => "No tracks found. ".pg_last_error($this->pg));
		}
	}

	public function processTrack($track_id) {
		$query = "SELECT track_id FROM `ibis_server-php`.`tracks` WHERE track_id = '".$track_id."';";
		$this->my->query($query);
		if($this->my->affected_rows == 1) {
			return $this->prepareTrack($track_id);
		} else {
			return array("error" => "Track not found. ".pg_last_error($this->pg));
		}
	}

	public function deleteDynCosts() {
		$query = "TRUNCATE TABLE dyncost;";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
			return array("success" => "Table dyncost truncated.");
		} else {
			return array("error" => "Truncate table dyncost failed: ".pg_last_error($this->pg));
		}
	}
}
