<?php
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Berlin');
// Load config (for MySQL db)
include('api1/config.php');
// Connect to MySQL database
$err_level = error_reporting(0);
$my = new mysqli($my_host, $my_user, $my_pass);
error_reporting($err_level);
if($my->connect_error)
	die("Datenbankverbindung (MySQL) nicht möglich.");
$my->set_charset('utf8');
$my->select_db($my_name);
// Connect to PgSQL database
//$pgr = pg_connect ( $pgr_connectstr ) or die ( "Datenbankverbindung (PostgreSQL) nicht möglich." . pg_last_error () );
// Start session
session_start();

// Process input
if(isset($_GET["content_get"])) {
	if($_GET["content_get"] == "login") {
		$out = json_encode(array("content" => '
		<h3>Bitte zuerst Anmelden:</h3>
		<form id="cleanmap_form">
			<input type="text" value="User" id="user_user">
			<br />
			<input type="password" value="Passwort" id="user_pw">
			<br />
			<input type="submit" value="Anmelden">
			<br />
		</form>
		<br />'));
	} else if($_GET["content_get"] == "delete") {
		if(isset($_SESSION["auth_user"]) && $_SESSION["auth_user"]=="ok") {
			$query = "SELECT `name`,`track_id`,`created`,`nodes` FROM `ibis_server-php`.`tracks` ORDER BY `created` DESC;";
			$result = $my->query($query);
			$options = "";
			if($result->num_rows >= 1){
				$data = array();
				while($row = $result->fetch_assoc()){
					$options .= "\t\t\t\t\t\t<option value=\"" . $row["track_id"] . "\">" .
					$row["name"] . " (" . date("d.m. ~H", intval($row["created"])) . "h; " . $row["nodes"] ." Punkte)" 
					. "(" . $row["nodes"] . " Punkte)</option>\n";
				}
			}
			$out = json_encode(array("content" => '
			<h1>iBis Tracks Löschen</h1>
			<form id="admin_delete_form">
				<label for="admin_delete_select">Track(s) löschen</label>
				<br />
				<select id="admin_delete_select" multiple="multiple" size="35" style="overflow: hidden; width: 100%;">
				'.$options.'
				</select>
				<br />
				<input type="submit" value="Tracks Löschen">
				<br />
			</form>
			<br />'));
		} else {
			$out = json_encode(array("error" => "User not authenticated"));
		}
	} else {
		$out = json_encode(array("error" => "Content unknown"));
	}
}

echo($out);

// Close PgSQL connection
//pg_close($pgr);

// Close MySQL connection
$my->close();
?>