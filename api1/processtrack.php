<?php
$start_microtime = microtime(true);
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Berlin');
include('config.php');
include('functions.php');

$pg = pg_connect($pgr_connectstr);
if(!$pg) die(json_encode(array("error" => "Datenbankverbindung (PostgreSQL) nicht möglich." . pg_last_error())));


include('../classes/processTracks.class.php');
$processTracks = new processTracks($pg);


if(isset($_GET['track_id'])) {
	$out = array_merge($processTracks->processTrack(pg_escape_string($pg, $_GET['track_id'])), array("track_id" => $_GET['track_id']));
	$out["executiontime"] = (microtime(true)-$start_microtime);
	$out_r = json_encode($out);
}
else if(isset($_GET['all'])) {
	$out = $processTracks->processAllTracks();
	$out["executiontime"] = (microtime(true)-$start_microtime);
	$out_r = json_encode($out);
}
else if(isset($_GET['clear'])) {
	$out = $processTracks->deleteDynCosts();
	$out["executiontime"] = (microtime(true)-$start_microtime);
	$out_r = json_encode($out);
}
else if(isset($_GET['list'])) {
	$query = "SELECT track_id FROM tracks;";
	$out_r = "";
	if($result = pg_query($pg, $query)) {
		while($row = pg_fetch_assoc($result)) {
			$out_r .= "<a href=\"https://10.2.11.94/api1/processtrack.php?track_id=".$row["track_id"]."\">".$row["track_id"]."</a><br />\n";
		}
		pg_free_result($result);
	}
}
else if(isset($_GET['javascript'])) {
	$query = "SELECT track_id FROM tracks;";
	if($result = pg_query($pg, $query)) {
		$out_urls = "";
		while($row = pg_fetch_assoc($result)) {
			$out_urls .= "https://ibis.jufo.mytfg.de/api1/processtrack.php?track_id=".$row["track_id"].";";
		}
		$out_urls = substr($out_urls, 0, -1);
		pg_free_result($result);
	}
	$out_r = '<!doctype html>
<html>
 <head>
  <title>iBis Track Processing</title>
  <script type="text/javascript" src="../jquery/jquery-2.1.3.min.js"></script>
  <style>
   table, th, td {
    border: 1px solid black;
   }
  </style>
 </head>
 <body>
  <table id="ergebnis_table" border="2px solid" rules="all">
   <tr>
    <td><b>Nr</b></td>
    <td><b>track_id</b></td>
    <td><b>Ergebnis</b></td>
    <td><b>Nodes</b></td>
    <td><b>Matched Ways</b></td>
    <td><b>Ausführungszeit</b></td>
   </tr>
  </table>
  <br />
  <br />
  <hr />
  <br />
  <script type="text/javascript">
   var urls = "'.$out_urls.'";
   var url_array = urls.split(";");
   var i_max = url_array.length;
   function getResult(i) {
     $.ajax({
        url: url_array[i],
        success: function(data) {
          var json = jQuery.parseJSON(data);
          var tr;
          if(json.error) {
            tr = "<tr><td>"+i+"</td><td>"+json.track_id+"</td><td colspan=\"3\">"+json.error+"</td><td>"+json.executiontime+"</td></tr>";
          }
          else {
            tr = "<tr><td>"+i+"</td><td>"+json.track_id+"</td><td>Erfolg</td><td>"+json.nodes+"</td><td>"+json.matchedways_return.rows_matchedways+"</td><td>"+json.executiontime+"</td></tr>";
          }
          $("#ergebnis_table").append(tr);
          $("body").append(data+"<br /><hr />");
           if((i+1)<i_max) {
            getResult(i+1);
          }
          else {
            alert("Fertig!");
          }
        }
     });
   }
   getResult(0);
  </script>
 </body>
</html>';
}
else {
	$out = array("error" => "Eingaben fehlerhaft.");
	$out_r = json_encode($out);
}

echo($out_r);

pg_close($pg);
?>
