<?php
header("X-Powered-By: ASP.NET");
header("X-AspNet-Version: 1.1.4322");
header("X-Powered-By: InteSoft-ASPAccelerator/2.2");
header("Cache-Control: private, max-age=0");
header("Content-Type: text/xml; charset=utf-8");

$fp = fopen('RetrieveRemoteSince.xml','r');
while(!feof($fp)) {
	$line = fgets($fp, 256);
	$line = str_replace('___datetime___', date('Y-m-d\TH:i:s.000', time()), $line);
	echo $line;
}
fclose($fp);
?>
