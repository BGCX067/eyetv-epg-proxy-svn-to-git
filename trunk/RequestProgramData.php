<?php require_once('functions.inc.php') ?>
<?php sendHTTPHeaders() ?>
<?php echo '<?xml version="1.0" encoding="utf-8"?>' ?>

<ProgramDataCollection xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.titantv.com/services/dataservice">
<?php
if (isset($_GET['ProviderId'])) {
	$providerId = $_GET['ProviderId'];
	setBaseTime($providerId);
	if (!isset($config['providers'][$providerId])) {
		$usingCache = true;
		$numStations = 0;
		$numSchedules = 0;
		$numPrograms = 0;
		$stationsXML = '';
		$programsXML = '';
		$schedulesXML = '';
		echo "	<ErrorCode>1</ErrorCode>\n";
		echo "	<ErrorDescription>Can't find provider with ID='$providerId' in config.inc.php</ErrorDescription>\n";
	} else if ($config['providers'][$providerId]['epg-source'] == 'sd') {
		echo "	<ErrorCode>0</ErrorCode>\n";
		echo "	<ErrorDescription>OK</ErrorDescription>\n";
		parseEPGData($providerId);
	} else if ($config['providers'][$providerId]['epg-source'] == 'xmltv') {
		echo "	<ErrorCode>0</ErrorCode>\n";
		echo "	<ErrorDescription>OK</ErrorDescription>\n";
		require_once('functions-xmltv.inc.php');
		parseEPGDataXMLTV($providerId);
	}
}
?>
	<ProgramData>
		<BaseTime><?php echo $baseTimeString ?></BaseTime>
		<ProviderId><?php echo $providerId ?></ProviderId>
		<UsingCachedData><?php echo $usingCache ?></UsingCachedData>
		<StationCount><?php echo $numStations ?></StationCount>
		<ScheduleCount><?php echo $numSchedules ?></ScheduleCount>
		<ProgramCount><?php echo $numPrograms ?></ProgramCount>
<?php echo $stationsXML ?>
<?php echo $programsXML ?>
<?php echo $schedulesXML ?>
	</ProgramData>
</ProgramDataCollection>
