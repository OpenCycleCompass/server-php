<?php

class processTracks {
	// variables
	private $pg;
	private $my;
	private $nodes_prefix;
	private $edges_prefix;
	
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
			b.c_id AS end_c_id
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
			return array("error" => "Error romoving rows containing NULL values.");
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
		
		// create column for gid of nearest node
		$query = "ALTER TABLE ".$ttid_edges." ADD COLUMN nnode_gid integer;";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error adding nnode_gid column.");
		}
		
		// Find nearest way from ways table
		$query = "SELECT c_id, ST_AsText(start_geom) as start_geom FROM ".$ttid_edges.";";
		$result = pg_query($this->pg, $query);
		if($result) {
			while($row = pg_fetch_assoc($result)) {
				$query1 = "SELECT id::integer FROM ways_vertices_pgr ORDER BY the_geom <-> ST_GeomFromText('".$row["start_geom"]."') LIMIT 1;";
				$result1 = pg_query($this->pg, $query1);
				if($result1) {
					$row1 = pg_fetch_row($result1);
					$nid = $row1[0];
					pg_free_result($result1);
				} else {
					$nid = 0;
				}
				$query2 = "UPDATE ".$ttid_edges."  SET nnode_gid = ".$nid." WHERE c_id = ".$row["c_id"].";";
				$result2 = pg_query($this->pg, $query2);
				if($result1) {
					pg_free_result($result2);
				}
			}
			pg_free_result($result);
		} else {
			return array("error" => "Error adding nnode_gid column.");
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
		*/
		$query = "UPDATE ".$ttid_edges." SET cost = 1.5-(0.8^(".$avg_speed."/c_speed)) WHERE c_speed != 0;";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error calculation cost column (c_speed != 0).");
		}
		$query = "UPDATE ".$ttid_edges." SET cost = 1.55 WHERE c_speed = 0;";
		$result = pg_query($this->pg, $query);
		if($result) {
			pg_free_result($result);
		} else {
			return array("error" => "Error calculation cost column (c_speed = 0).");
		}
		
		// copy cost from TEMP edges table to dyncost table
		$query = "SELECT c_id, nnode_gid, cost FROM ".$ttid_edges.";";
		$result = pg_query($this->pg, $query);
		if($result) {
			while($row = pg_fetch_assoc($result)) {
				$query1 = "
				INSERT INTO dyncost
				(node_id, track_id, cost)
				VALUES (".$row["nnode_gid"].", '".$track_id."', ".$row["cost"].")
				;";
				$result1 = pg_query($this->pg, $query1);
				if($result1) {
					pg_free_result($result1);
				}
			}
			pg_free_result($result);
		} else {
			return array("error" => "Error copy cost to dyncost table.");
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
			return array("error" => "No tracks found.");
		}
	}

	public function processTrack($track_id) {
		$query = "SELECT track_id FROM `ibis_server-php`.`tracks` WHERE track_id = '".$track_id."';";
		$this->my->query($query);
		if($this->my->affected_rows == 1) {
			return $this->prepareTrack($track_id);
		} else {
			return array("error" => "Track not found.");
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
