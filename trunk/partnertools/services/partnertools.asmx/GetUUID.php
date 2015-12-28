<?php
header("X-Powered-By: ASP.NET");
header("X-AspNet-Version: 1.1.4322");
header("X-Powered-By: InteSoft-ASPAccelerator/2.2");
header("Cache-Control: private, max-age=0");
header("Content-Type: text/xml; charset=utf-8");

$fp = fopen('GetUUID.xml','r');
while(!feof($fp)) {
	$line = fgets($fp, 256);
	$line = str_replace('___username___', $_GET['LoginName'], $line);
	echo $line;
}
fclose($fp);
?>
