<?php
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Berlin');
// Load config (for PgSQL db)
include('api1/config.php');
// Connect to PgSQL database
$pg = pg_connect ( $pgr_connectstr ) or die ( "Datenbankverbindung (PostgreSQL) nicht möglich." . pg_last_error () );
// Start session
session_start();

function existsProfile($profile, $pg) {
	$query = "SELECT COUNT(*) FROM classes WHERE profile = '".pg_escape_string($profile)."';";
	$result = pg_query($pg, $query);
	if($result) {
		if ($line = pg_fetch_array($result)) {
			if ($line[0] > 0) {
				return true;
			} else {
				return false;
			}
		} else {
			return pg_last_error($pg);
		}
		pg_free_result($result);
	} else {
		return pg_last_error($pg);
	}
}

function getProfileEditor($profile, $pg) {
	$query = "SELECT id, name, cost FROM classes WHERE profile = '".pg_escape_string($profile)."' AND id <> 99999 ORDER BY name ASC;";
	$result = pg_query($pg, $query);
	$trs = "";
	if(pg_num_rows($result) >= 1){
		while($row = pg_fetch_assoc($result)){
			$trs .= '<tr><td>' . $row["name"] . '</td><td><input class="cost" type="text" name="' . $row["name"] . '"  value="' . floatval($row["cost"]) . '" id="' . $row["id"] . '"></td></tr>' . "\n";
		}
		pg_free_result($result);
	}
	else {
		return json_encode(array("error" => "Profile not found or corrupted.".pg_last_error($pg)));
	}
	$amount_dyncost = 0;
	$query = "SELECT cost FROM classes WHERE profile = '".pg_escape_string($profile)."' AND id = 99999;";
	$result = pg_query($pg, $query);
	if(pg_num_rows($result) >= 1){
		if($row = pg_fetch_assoc($result)){
			$amount_dyncost = $row["cost"];
		}
		pg_free_result($result);
	}
	else {
		return json_encode(array("error" => "Profile not found or corrupted.".pg_last_error($pg)));
	}
	return json_encode(array("content" => '
	<h3>Profil: '.$profile.'</h3>
	<form action="#" methode="post" id="profile_update_form" onsubmit="updateProfile(event)"><table>
		'.$trs.'
		<tr><td colspan="2">&nbsp;</td></tr>
		<tr><td>Anteil der Dynamischen Kosten</td><td><input class="cost" type="text" name="amount_dyncost"  value="'.$amount_dyncost.'" id="amount_dyncost"></td></tr>
		<tr><td></td><td><input type="submit" value="Ändern"></td></tr>
		<input type="hidden" name="profile" id="profile_profile" value="'.$profile.'">
	</table></form>'));
}

// Process input
if(isset($_SESSION["auth_user"]) && $_SESSION["auth_user"]=="ok" && isset($_GET["profile"])) {
	$exists = existsProfile($_GET["profile"], $pg);
	if($exists===true) {
		$out = getProfileEditor($_GET["profile"], $pg);
	}
	else {
		$out = json_encode(array("error" => "Profile unknown: ".$exists));
	}
}
else {
	$out = json_encode(array("error" => "No authentictaion or no profile specified."));
}

echo($out);

// Close PgSQL connection
pg_close($pg);
?>