<?php
include("mapMatching.class.php");

class processTracks {
	// variables
	private $pg;
	private $nodes_prefix = "tt_nodes_";
	private $edges_prefix = "tt_edges_";
	private $dumpedways_prefix = "tt_dumpedways_";
	private $dumpedpoints_prefix = "tt_dumpedpoints_";
	//private $pg_temp_qualifier = "TEMP";
	private $pg_temp_qualifier = "";

	public function __construct($p_pg) {
		$this->pg = $p_pg;
	}

	private function prepareTrack($track_id) {
		// verify track_id is done in superordinated function processTrack()

		// $info variable for output track processing info
		$info = array();

		// Unique ttid (temporary table identifier) generated from timestamp and track_id
		$ttunique = str_replace("-", "_", str_replace(".", "_", uniqid("", false)."__".$track_id));
		$ttid_nodes = $this->nodes_prefix.$ttunique;
		$ttid_edges = $this->edges_prefix.$ttunique;
		//$ttid_dumpedpoints2 = $this->dumpedpoints_prefix."2".$ttunique;

		// Import gps points from rawdata_server_php table into temporary table $ttid_nodes
		//$query = "CREATE ".$this->pg_temp_qualifier." TABLE ".$ttid_nodes." AS
		$query = "CREATE ".$this->pg_temp_qualifier." TABLE ".$ttid_nodes." AS
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
		$query = "CREATE ".$this->pg_temp_qualifier." TABLE ".$ttid_edges." AS
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
		$query = "ALTER TABLE ".$ttid_edges." ADD COLUMN bad boolean;
			UPDATE ".$ttid_edges." SET bad = FALSE;";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error adding and initialising bad column: ".pg_last_error($this->pg));
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
		$query = "UPDATE ".$ttid_edges." SET bad = TRUE WHERE c_dist > 100.0;";
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
		$query = "UPDATE ".$ttid_edges." SET bad = TRUE WHERE c_tdiff > 60;";
		$result = pg_query($this->pg, $query);
		if($result) {
			$info["deleted_tdiff_to_large"] = pg_affected_rows($result);
			pg_free_result($result);
		} else {
			return array("error" => "Error removing rows with c_tdiff > 60");
		}

		// remove rows with c_tdiff < 0.5 (seconds)
		$query = "UPDATE ".$ttid_edges." SET bad = TRUE WHERE c_tdiff < 0.5;";
		$result = pg_query($this->pg, $query);
		if($result) {
			$info["deleted_tdiff_to_small"] = pg_affected_rows($result);
			pg_free_result($result);
		} else {
			return array("error" => "Error removing rows with c_tdiff < 0.5");
		}

		// calculate speed: c_speed
		$query = "UPDATE ".$ttid_edges." SET c_speed = c_dist/c_tdiff WHERE bad <> TRUE;";
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
		$query = "UPDATE ".$ttid_edges." SET bad = TRUE WHERE c_speed > 25.0;";
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
		$query = "ALTER TABLE ".$ttid_edges." ADD COLUMN near_gid integer;";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error adding nnode_gid column.");
		}

		$query = "SELECT COUNT(*) FROM ".$ttid_edges." e1 
			INNER JOIN ".$ttid_edges." e2 ON e2.start_id = e1.end_id
			WHERE 	e1.bad = TRUE AND e2.bad = TRUE;";
		$result = pg_query($this->pg, $query);
		if($result) {
			if ($line = pg_fetch_array($result)) {
				$info["bad_rows"] = $line[0];
			}
		} else {
			return array("error" => "Error counting bad rows : ".pg_last_error($this->pg) . " || " . $query);
		}

		// remove rows with bearing = NULL
		$query = "DELETE FROM ".$ttid_nodes."
			USING 	".$ttid_edges." AS e1,
				".$ttid_edges." AS e2
			WHERE 	e2.start_id = e1.end_id 
				AND e2.start_id = id 
				AND e1.bad = TRUE 
				AND e2.bad = TRUE;
		";
		$query = "DELETE FROM ".$ttid_nodes."
			USING 	".$ttid_edges." AS e1,
				".$ttid_edges." AS e2
			WHERE 	e2.start_id = e1.end_id 
				AND e2.start_id = id 
				AND e1.bad = TRUE 
				AND e2.bad = TRUE;
		";
		$result = pg_query($this->pg, $query);
		if($result) {
			$info["deleted_bad_rows"] = pg_affected_rows($result);
			pg_free_result($result);
		} else {
			return array("error" => "Error removing bad rows : ".pg_last_error($this->pg) . " || " . $query);
		}

		// Add cost column
		$query = "ALTER TABLE ".$ttid_edges." ADD COLUMN cost numeric(16,8);";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error adding cost column.");
		}
		// Add cost column
		$query = "ALTER TABLE ".$ttid_nodes." ADD COLUMN cost numeric(16,8);";
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

		$query = "UPDATE ".$ttid_nodes."
			SET cost = NULL;
			UPDATE ".$ttid_nodes." n
			SET cost = e.cost
			FROM ".$ttid_edges." e
			WHERE n.c_id = e.start_c_id;";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error copying cost column to nodes table.".pg_last_error($this->pg));
		}

		$mapmatching = new mapMatching($this->pg, $ttid_nodes, $ttid_edges, $ttunique);
		$matchedways = $mapmatching->matchTrack();
		if(!isset($matchedways["success"])) {
			return array("error" => "Error matching track.", "matchedways_return" => $matchedways);
		}
		$info["matchedways_return"] = $matchedways;
		$ttid_matchedways = $matchedways["ttid"];

		// copy cost (forward) from ".$this->pg_temp_qualifier." edges table to dyncost table
		$query = "SELECT osm_id, cost FROM ".$ttid_matchedways." WHERE cost <> -1::numeric(16,8) AND reverse = FALSE;";
		$result = pg_query($this->pg, $query);
		if($result) {
			while($row = pg_fetch_assoc($result)) {
				$query1 = "INSERT INTO dyncost (track_id, cost, osm_id)
				 VALUES ('".$track_id."', ".$row["cost"].", ".$row["osm_id"].");";
				$result1 = pg_query($this->pg, $query1);
				if($result1) {
					pg_free_result($result1);
				}
			}
			pg_free_result($result);
		} else {
			return array("error" => "Error copy (forward) cost to dyncost table: ".pg_last_error($this->pg));
		}
		// copy reverse cost from ".$this->pg_temp_qualifier." edges table to dyncost table
		$query = "SELECT osm_id, cost FROM ".$ttid_matchedways." WHERE cost <> -1::numeric(16,8) AND reverse = TRUE;";
		$result = pg_query($this->pg, $query);
		if($result) {
			while($row = pg_fetch_assoc($result)) {
				$query1 = "INSERT INTO dyncost (track_id, reverse_cost, osm_id)
				 VALUES ('".$track_id."', ".$row["cost"].", ".$row["osm_id"].");";
				$result1 = pg_query($this->pg, $query1);
				if($result1) {
					pg_free_result($result1);
				}
			}
			pg_free_result($result);
		} else {
			return array("error" => "Error copy reverse cost to dyncost table: ".pg_last_error($this->pg));
		}

		if(!isset($_GET["nodrop"])) {
			// drop temp tables
			$query = "DROP TABLE ".$ttid_edges.";
			DROP TABLE ".$ttid_nodes.";
			DROP TABLE ".$ttid_matchedways.";";
			$result = pg_query($this->pg, $query);
			if($result) {
				pg_free_result($result);
			} else {
				return array("error" => "Error dropping ".$ttid_edges.", ".$ttid_matchedways." and ".$ttid_nodes." table. ".pg_last_error($this->pg));
			}
		}

		$info["success"] = true;
		return $info;
	}

	public function processAllTracks() {
		// Check whether dyncost table is empty?
		$query = "SELECT track_id FROM tracks;";
		if($result = pg_query($this->pg, $query)) {
			$tracknum = 0;
			$error_cnt = 0;
			$success_cnt = 0;
			$res = array();
			while($row = pg_fetch_assoc($result)) {
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
			pg_free_result($result);
			return $res;
		} else {
			return array("error" => "No tracks found. ".pg_last_error($this->pg));
		}
	}

	public function processTrack($track_id) {
		$query = "SELECT track_id FROM tracks WHERE track_id = '".$track_id."';";
		$result = pg_query($this->pg, $query);
		if($result && pg_affected_rows($result) == 1) {
			pg_free_result($result);
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

	public function getDynCostNumSeg() {
		$query = "SELECT COUNT(*) AS count FROM dyncost;";
		$result = pg_query($this->pg, $query);
		if($result && $row = pg_fetch_assoc($result)) {
			pg_free_result($result);
			return array("numseg" => intval($row["count"]));
		} else {
			return array("error" => "Select count(*) from table dyncost failed: ".pg_last_error($this->pg));
		}
	}
}
