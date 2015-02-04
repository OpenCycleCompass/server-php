<?php
header ( 'Content-Type: text/html; charset=utf-8' );
date_default_timezone_set ( 'Europe/Berlin' );
include ('config.php');
include ('functions.php');
$err_level = error_reporting ( 0 );
$my = new mysqli ( $my_host, $my_user, $my_pass );
error_reporting ( $err_level );
if ($my->connect_error)
	die ( "Datenbankverbindung nicht möglich." );
$my->set_charset ( 'utf8' );
$my->select_db ( $my_name );

session_start();

$pgr = pg_connect ( $pgr_connectstr );
if(!$pgr)
	die ( "Datenbankverbindung (PostgreSQL) nicht möglich." . pg_last_error () );
if(isset($_SESSION["auth_user"]) && $_SESSION["auth_user"]=="ok") {
	if(isset($_GET["deletetrack"]) && isset($_GET['track_ids'])) {
		$track_ids = explode(";", substr($_GET['track_ids'], 0, -1));
		$success_counter = 0;
		$node_counter = 0;
		foreach($track_ids as $track_id) {
			$result = $my->query("SELECT COUNT(id) AS count FROM tracks WHERE track_id = '".$my->real_escape_string($track_id)."';");
			if($result) {
				$row = $result->fetch_assoc();
				if($row["count"]=="1") {
					// Delete track from MySQL table
					$my->query("DELETE FROM tracks WHERE track_id = '".$my->real_escape_string($track_id)."' LIMIT 1;");
					if($result && ($my->affected_rows==1)) {
						// Delete coordinates from PgSQL database:
						$query = "
						WITH moved_rows AS (
							DELETE FROM rawdata_server_php
							WHERE
							track_id = '".pg_escape_string($track_id)."'
							RETURNING *
						)
						INSERT INTO rawdata_server_php_deleted
						SELECT * FROM moved_rows;
						";
						$result = pg_query($query);
						if($result) {
							$node_counter += pg_affected_rows($result);
							pg_free_result($result);
						}
						$success_counter++;
					} else{
						$out = json_encode(array("error" => "track ".$track_id." was not deleted"));
						echo($out);
						exit;
					}
				} else {
					$out = json_encode(array("error" => "track ".$track_id." does not exist"));
					echo($out);
					exit;
				}
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
echo ($out);
pg_close ( $pgr );
$my->close ();
?>
