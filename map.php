<?php
//header("HTTP/1.1 301 Moved Permanently");
http_response_code(301);
header("Location: map.html");
exit;
?>
