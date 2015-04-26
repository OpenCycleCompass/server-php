<?php
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Berlin');
include('config.php');
include('functions.php');

$pg = pg_connect($pgr_connectstr);
if(!$pg) die("Datenbankverbindung (PostgreSQL) nicht möglich. ".pg_last_error());

session_start();

if(isset($_SESSION["auth_user"]) && $_SESSION["auth_user"]=="ok") {
	if(isset($_GET["deletetrack"]) && isset($_GET['track_ids'])) {
		$track_ids = explode(";", substr($_GET['track_ids'], 0, -1));
		$success_counter = 0;
		$node_counter = 0;
		foreach($track_ids as $track_id) {
			$result = pg_query($pg, "SELECT COUNT(id) AS count FROM tracks WHERE track_id = '".pg_escape_string($pg, $track_id)."';");
			if($result) {
				$row = pg_fetch_assoc($result);
				if($row["count"]=="1") {
					// Delete track from MySQL table
					$result2 = pg_query($pg, "DELETE FROM tracks WHERE track_id = '".pg_escape_string($pg, $track_id)."';");
					if($result2 && (pg_affected_rows($result2)==1)) {
						// Delete coordinates from PgSQL database:
						$query3 = "
						WITH moved_rows AS (
							DELETE FROM rawdata_server_php
							WHERE
							track_id = '".pg_escape_string($track_id)."'
							RETURNING *
						)
						INSERT INTO rawdata_server_php_deleted
						SELECT * FROM moved_rows;
						";
						$result3 = pg_query($query3);
						if($result3) {
							$node_counter += pg_affected_rows($result3);
							pg_free_result($result3);
						}
						$success_counter++;
						pg_free_result($result2);
					} else{
						$out = json_encode(array("error" => "track ".$track_id." was not deleted: ".pg_last_error($pg)));
						echo($out);
						exit;
					}
				} else {
					$out = json_encode(array("error" => "track ".$track_id." does not exist: ".pg_last_error($pg)));
					echo($out);
					exit;
				}
				pg_free_result($result);
			} else {
				$out = json_encode(array("error" => "database problem"));
				echo($out);
				exit;
			}
		}
		$out = json_encode(array("success" => $success_counter." Track(s) [".$node_counter." Coordinates] successfully deleted"));
	}
} else {
	$out = json_encode(array("error" => "User not authenticated"));
}
echo($out);
pg_close($pg);
?>
