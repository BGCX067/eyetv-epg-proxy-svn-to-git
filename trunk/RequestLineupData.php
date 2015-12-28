<?php require_once('functions.inc.php') ?>
<?php sendHTTPHeaders() ?>
<?php echo '<?xml version="1.0" encoding="utf-8"?>' ?>

<StationCollection xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.titantv.com/services/dataservice">
<?php
if (isset($_GET['ProviderId'])) {
	$providerId = $_GET['ProviderId'];
	setBaseTime($providerId);

	if (!isset($config['providers'][$providerId])) {
		$stations = array();
		$usingCache = true;	// Response won't be cached that way
		$stationsXML = '';
		echo "	<ErrorCode>1</ErrorCode>\n";
		echo "	<ErrorDescription>Can't find provider with ID='$providerId' in config.inc.php</ErrorDescription>\n";
	} else if ($config['providers'][$providerId]['epg-source'] == 'sd') {
		echo "	<ErrorCode>0</ErrorCode>\n";
		echo "	<ErrorDescription>OK</ErrorDescription>\n";
		$response = getEPGData($providerId);
		$stations = $response->xtvd->stations->station;
		$lineups = $response->xtvd->lineups->lineup->map;
		$stationsXML = getStationsXML($stations, $lineups, $providerId);
	} else if ($config['providers'][$providerId]['epg-source'] == 'xmltv') {
		echo "	<ErrorCode>0</ErrorCode>\n";
		echo "	<ErrorDescription>OK</ErrorDescription>\n";
		require_once('functions-xmltv.inc.php');
		$response = getEPGDataXMLTV($providerId);
		$stations = $response->channel;
		$stationsXML = getStationsXMLTV($stations, $providerId);
	}
}
?>
	<StationCount><?php echo sizeof($stations) ?></StationCount>
	<UsingCachedData><?php echo $usingCache ?></UsingCachedData>
<?php echo $stationsXML ?>
</StationCollection>
