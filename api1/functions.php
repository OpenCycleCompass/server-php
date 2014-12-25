<?php
function verify_token($token, $db) {
	$db->query("SELECT `id` FROM `ibis_server-php`.`tokens` WHERE `token`='".$token."'");
	if($db->affected_rows() == 1) {
		return 1;
	} else {
		return 0;
	}
}
?>