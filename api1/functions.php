<?php
function verify_token($token, $my) {
	$my->query("SELECT `id` FROM `ibis_server-php`.`tokens` WHERE `token`='".$token."'");
	if($my->affected_rows == 1) {
		return 1;
	} else {
		return 0;
	}
}
?>