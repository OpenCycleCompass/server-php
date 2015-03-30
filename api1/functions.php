<?php
function verify_token($token, $pg) {
	$result = pg_query($pg, "SELECT id FROM tokens WHERE token = '".pg_escape_string($pg, $token)."'");
	if(pg_affected_rows($result) == 1) {
		pg_free_result($result);
		return true;
	} else {
		return false;
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