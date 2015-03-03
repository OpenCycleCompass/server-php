<?php
function verify_token($token, $my) {
	$my->query("SELECT `id` FROM `ibis_server-php`.`tokens` WHERE `token`='".$token."'");
	if($my->affected_rows == 1) {
		return 1;
	} else {
		return 0;
	}
}

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

?>