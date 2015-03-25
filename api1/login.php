<?php
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Berlin');
// Load config (for db)
include('config.php');

$pg = pg_connect($pgr_connectstr);
if(!$pg) die(json_encode(array("error" => "Datenbankverbindung (PostgreSQL) nicht möglich." . pg_last_error())));

// Start session
session_start();

if(isset($_GET["login"]) && isset($_GET["user"]) && !empty($_GET["user"]) && (isset($_POST["password"]) || isset($_GET["password"])) && (!empty($_POST["password"]) || !empty($_GET["password"])) ) {
	if(isset($_POST["password"])) {
		$pw = pg_escape_string($pg, $_POST["password"]);
	} else {
		$pw = pg_escape_string($pg, $_GET["password"]);
	}
	$success = false;
	$query = "SELECT password FROM admin_users WHERE name = '".pg_escape_string($pg, $_GET["user"])."';";
	$result = pg_query($pg, $query);
	if($result && pg_num_rows($result) >= 1){
		// possible multiple users with same name but different password -> multiple rows in MySQL db
		while($row = pg_fetch_assoc($result)){
			if(password_verify($pw, $row["password"])) {
				$success = true;
			}
		}
		pg_free_result($pg);
	}
	if($success) {
		$_SESSION["auth_user"] = "ok";
		$out = json_encode(array("success" => "User successfully authenticated"));
	} else {
		$out = json_encode(array("error" => "User or password incorrect"));
	}
} else if( // authenticated admin users can add new admin users
		isset($_SESSION["auth_user"]) && ($_SESSION["auth_user"]=="ok") 
		&& isset($_GET["new"]) && isset($_GET["user"]) && !empty($_GET["user"]) 
		&& isset($_GET["password"]) && !empty($_GET["password"])) 
{
	$pw = password_hash(pg_escape_string($pg, $_GET["password"]), PASSWORD_DEFAULT);
	$query = "INSERT INTO admin_users (id, name, password, created)
	VALUES (NULL, '".pg_escape_string($pg, $_GET["user"])."', '".$pw."', CURRENT_TIMESTAMP);";
	$result = pg_query($pg, $query);
	if($result) {
		$out = json_encode(array("success" => "User successfully created"));
	} else {
		$out = json_encode(array("error" => "User not created - Database problem :("));
	}
} else if(isset($_GET["status"])) { // is user logged in?
	if(isset($_SESSION["auth_user"]) && $_SESSION["auth_user"]=="ok") {
		$out = json_encode(array("status" => "ok"));
	} else {
		$out = json_encode(array("status" => "bad"));
	}
} else if(isset($_GET["signout"])) {
	if(isset($_SESSION["auth_user"]) && $_SESSION["auth_user"]=="ok" && session_destroy()) {
		$out = json_encode(array("success" => "User signed out"));
	} else {
		$out = json_encode(array("error" => "Sign out failed"));
	}
} else {
	$out = json_encode(array("error" => "Keine oder falsche Eingabe."));
}
echo($out);
?>
