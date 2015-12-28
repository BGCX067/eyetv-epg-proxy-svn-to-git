<?php
set_time_limit(600);
include_once('config.inc.php');

function useXmlCache() {
	global $config, $usingCache;
	$usingCache = false;

	// Use cached XML data if available
	$cacheFile = getXmlCacheFile();
	chdir($config['localDir']);
	if (!isExpired($cacheFile)) {
		$usingCache = true;
		$fp = fopen($cacheFile,'r')
			or die("Can't open provider cache file for reading.");
		$xmlResponse = "";
		while (!feof($fp)) {
			$xmlResponse .= fread($fp, 8192);
		}
		fclose($fp);
		echo $xmlResponse;
		exit;
	}
}

function getXmlCacheFile() {
	$file = $_SERVER["PHP_SELF"];
	$file = substr($file, strrpos($file, "/")+1);
	$tag = '';
	if (isset($_GET['ProviderId'])) {
		$tag = '_' . $_GET['ProviderId'];
	}
	if (isset($_GET['Zip'])) {
		$tag = '_' . $_GET['Zip'];
	}
	if (isset($_GET['from_day'])) {
		$tag = '_' . $_GET['from_day'];
	}
	return "./cache/$file$tag.xml.cache";
}

function deflate_handler(&$buffer, $mode) {
	global $config, $usingCache;
	if (@$config['deflateEPGData'] && isset($_GET['DaysRequested'])) {
		header("Content-Encoding: deflate");
		if (!$usingCache) {
			$buffer = gzdeflate($buffer);
		}
	}
	if (!$usingCache) {
		// Cache result
		$cacheFile = getXmlCacheFile();
		chdir($config['localDir']);
		$fp = fopen($cacheFile,'w')
			or die("Can't open XML cache file $cacheFile for writing.");
		fwrite($fp, $buffer);
		fclose($fp);
	}
	return $buffer;
}

function sendHTTPHeaders() {
	global $config, $usingCache;
	ob_start('deflate_handler');
	header("X-Powered-By: ASP.NET");
	header("X-AspNet-Version: 1.1.4322");
	header("X-Powered-By: InteSoft-ASPAccelerator/2.2");
	header("Cache-Control: private, max-age=0");
	header("Content-Type: text/xml; charset=utf-8");
	useXmlCache();
}

// Parameter: String to escape
// Returns: XML-valid string data
function xmlize($text) {
	$text = preg_replace('/&/','&amp;',$text);
	$text = preg_replace('/</','&lt;',$text);
	$text = preg_replace('/>/','&gt;',$text);
	return $text;
}

// Parameter: duration format: PTxxHyyM = xx hours + yy minutes
// Returns: runtime format: xx = xx minutes
function durationToRuntime($duration) {
	if (ereg("PT([0-9]+)H([0-9]+)M",$duration,$regs) !== false) {
		return ($regs[1]*60) + $regs[2];
	} else {
		return 0;
	}
}

// Parameter: a GMT date/time string
// Returns: the number of minutes between the specified time, and the base time
function convertTimeToStartTime($time) {
	global $baseTime;
	$time = strtotime($time." GMT");
	return ($time - $baseTime) / 60;
}

function convertTimeDurationToEndTime($startTime,$duration) {
	$runtimeMinutes = durationToRuntime($duration);
	return $startTime + $runtimeMinutes;
}

// Generate unique & numeric program Ids to replace alpha-numeric program Ids
// Will use those new numeric-only program Ids when writing the XML data
function normalizeProgramId($programs) {
	global $newProgramId;
	$newProgramId = array();
	$i = 1;
	foreach ($programs as $program) {
		$newProgramId[$program->id] = $i++;
	}
}

function getSortedSchedules($response) {
	$sc = $response->xtvd->schedules->schedule;
	$schedules = array();
	foreach ($sc as $schedule) {
		$schedules[$schedule->program] = $schedule;
	}
	return $schedules;
}

function getSortedGenres($response) {
	$ge = $response->xtvd->genres->programGenre;
	$genres = array();
	foreach ($ge as $genre) {
		$genres[$genre->program] = $genre->genre;
	}
	return $genres;
}

function getSortedLanguages(&$sortedGenres) {
	$languages = array();
	foreach ($sortedGenres as $programId => $genres) {
		if (is_array($genres)) {
			$i = 0;
			foreach($genres as $genre) {
				if ($genre->class == 'French') {
					$languages[$programId] = 'French';
					array_splice($genres, $i, 1);
					$sortedGenres[$programId] = $genres;
					break;
				}
				$i++;
			}
		} else if ($genres->class == 'French') {
			$languages[$programId] = 'French';
			$sortedGenres[$programId] = null;
		}
	}
	return $languages;
}

function getStationsXML($stations, $lineups, $providerId) {
	$resultXML = "";
	$resultXML .= "  <Stations>\n";

	$stationDisplayName = array();
	if (file_exists("$providerId.channels")) {
		$fp = fopen("$providerId.channels", "r")
			or die("Can't open $providerId.channels file for reading.");
		while (!feof($fp)) {
			$line = ereg_replace("[\r\n]*","",fgets($fp, 1024));
			if (ereg("^([0-9]+)\t(.*)$", $line, $regs) !== false) {
				$rfId = $regs[1];
				$name = $regs[2];
				$stationDisplayName[$rfId] = $name;
			}
		}
		fclose($fp);
	}

	$stationsRFID = array();
	$channels = array();
	foreach ($lineups as $lineup) {
		$stationId = $lineup->station;
		$channel = $lineup->channel;
		$stationsRFID[$stationId] = $channel;
		$channels[$lineup->channel] = $lineup->station;
	}

	$stationsNames = array();
	foreach ($stations as $station) {
		$stationsNames[$station->id] = xmlize(isset($stationDisplayName[$stationsRFID[$station->id]]) ? $stationDisplayName[$stationsRFID[$station->id]] : $station->name);
	}

	ksort($channels);
	foreach ($channels as $channel => $stationId) {
		$displayName = xmlize($stationsNames[$stationId]);
		$resultXML .= "    <Station>
      <station_id>".$stationId."</station_id>
      <call_sign>".$displayName."</call_sign>
      <rf_channel>".$channel."</rf_channel>
    </Station>
	";
	}

	$resultXML .= "  </Stations>\n";
	return $resultXML;
}

function getProgramsXML($programs, $schedules, $genres, $languages) {
	global $newProgramId;

	$resultXML = "";
	$resultXML .= "  <Programs>\n";

	foreach ($programs as $program) {
		$resultXML .= "    <Program>
      <program_id>".$newProgramId[$program->id]."</program_id>
      <run_time>".durationToRuntime($schedules[$program->id]->duration)."</run_time>
      <title>".xmlize($program->title)."</title>\n";

		// Serie
		if (isset($program->showType) && $program->showType == 'Series') {
			if (isset($program->subtitle)) {
				$resultXML .= "      <episode_title>".xmlize($program->subtitle)."</episode_title>\n";
			}
			if (isset($program->syndicatedEpisodeNumber)) {
				$resultXML .= "      <episode_id>".$program->syndicatedEpisodeNumber."</episode_id>\n";
			}
			$resultXML .= "      <is_episodic>Y</is_episodic>\n";
		} else {
			$resultXML .= "      <is_episodic>N</is_episodic>\n";
		}

		if (isset($program->description)) {
			$resultXML .= "      <description>".xmlize($program->description)."</description>\n";
		} else {
			$resultXML .= "      <description />\n";
		}
		if (isset($genres[$program->id])) {
			if (is_array($genres[$program->id])) {
				$resultXML .= "      <genre>".xmlize($genres[$program->id][0]->class)."</genre>\n";
			} else {
				$resultXML .= "      <genre>".xmlize($genres[$program->id]->class)."</genre>\n";
			}
		} else {
			$resultXML .= "      <genre />\n";
		}
		$resultXML .= "      <show_type>Other</show_type>\n";
		if (isset($languages[$program->id])) {
			$resultXML .= "      <language>".$languages[$program->id]."</language>\n";
		} else {
			$resultXML .= "      <language>English</language>\n";
		}

		$resultXML .= "    </Program>\n";
	}

	$resultXML .= "  </Programs>\n";
	return $resultXML;
}

function getSchedulesXML($schedules) {
	global $newProgramId;

	$resultXML = "";
	$resultXML .= "  <Schedules>\n";

	foreach ($schedules as $schedule) {
		$startTime = convertTimeToStartTime($schedule->time);
		$resultXML .= "    <Schedule>
      <program_id>".$newProgramId[$schedule->program]."</program_id>
      <station_id>".$schedule->station."</station_id>
      <start_time>".$startTime."</start_time>
      <end_time>".convertTimeDurationToEndTime($startTime, $schedule->duration)."</end_time>
      <hd>N</hd>\n";

		if (isset($schedule->stereo)) {
			$resultXML .= "      <stereo>Y</stereo>\n";
		} else {
			$resultXML .= "      <stereo>N</stereo>\n";
		}

		if (isset($schedule->closeCaptioned)) {
			$resultXML .= "      <cc>Y</cc>\n";
		} else {
			$resultXML .= "      <cc>N</cc>\n";
		}

		$resultXML .= "    </Schedule>\n";
	}
	
	$resultXML .= "  </Schedules>\n";
	return $resultXML;
}

function isExpired($filename) {
	global $config;
	$cacheExpiration = time() - $config['cacheDays']*24*60*60;
	if (file_exists($filename) && filemtime($filename) > $cacheExpiration) {
		return false;
	}
	return true;
}

// Either use the cached data (if less than 24h old), or fetch the data from the Schedules Direct SOAP service.
// Returns: Response object that contains all EPG data.
function getEPGData($providerId) {
	global $baseTime, $daysRequested, $config;
	$cacheFile = "./cache/$providerId.cache";
	chdir($config['localDir']);
	if (!isExpired($cacheFile)) {
		$fp = fopen($cacheFile,'r')
			or die("Can't open provider cache file for reading.");
		$serializedResponse = "";
		while (!feof($fp)) {
			$serializedResponse .= fread($fp, 8192);
		}
		fclose($fp);
		$response = unserialize($serializedResponse);
		return $response;
	}

	$username = $config['sd_access'][$providerId]['username'];
	$password = $config['sd_access'][$providerId]['password'];

	$client = new SoapClient("http://docs.tms.tribune.com/tech/tmsdatadirect/schedulesdirect/tvDataDelivery.wsdl", 
		array(
			'login' => $username,
			'password' => $password,
			'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_DEFLATE
		)
	);

	if (!isset($daysRequested)) {
		$daysRequested = 8;
	}
	if (isset($config['numDaysEPG'])) {
		$daysRequested = $config['numDaysEPG'];
	}
	if (isset($config['providers'][$providerId]['numDaysEPG'])) {
		$daysRequested = $config['providers'][$providerId]['numDaysEPG'];
	}

	$startTime = gmstrftime("%Y-%m-%dT00:00:00Z", $baseTime);
	$endTime = gmstrftime("%Y-%m-%dT00:00:00Z", strtotime("+$daysRequested days",time()));
	$response = $client->download($startTime, $endTime);

	$fp = fopen("./cache/".$providerId.'.cache','w')
		or die("Can't open provider cache file for writing.");
	fwrite($fp,serialize($response));
	fclose($fp);

	return $response;
}

// Generates the complete EPG data as a XML file (in TitanTV format)
function parseEPGData($providerId) {
	global $stationsXML, $programsXML, $schedulesXML, $baseTime;
	global $config, $numStations, $numPrograms, $numSchedules;

	$response = getEPGData($providerId);

	$stations = $response->xtvd->stations->station;
	$lineups = $response->xtvd->lineups->lineup->map;
	$stationsXML = getStationsXML($stations, $lineups, $providerId);
	$numStations = sizeof($stations);

	$programs = $response->xtvd->programs->program;
	normalizeProgramId($programs);
	$sortedSchedules = getSortedSchedules($response);
	$sortedGenres = getSortedGenres($response);
	$sortedLanguages = getSortedLanguages($sortedGenres);
	$programsXML = getProgramsXML($programs, $sortedSchedules, $sortedGenres, $sortedLanguages);
	$numPrograms = sizeof($programs);

	$schedules = $response->xtvd->schedules->schedule;
	$schedulesXML = getSchedulesXML($schedules);
	$numSchedules = sizeof($schedules);
}

function setBaseTime($providerId) {
	global $baseTime, $baseTimeString, $daysRequested, $config;
	$baseTime = strtotime(date("Y-m-d H:i:00",time()));

	if (isset($config['providers'][$providerId]['tz_correction'])) {
		$baseTimeString = gmstrftime("%Y-%m-%d %H:%M:00", strtotime($config['providers'][$providerId]['tz_correction']." hours", $baseTime));
	} else if (isset($config['tz_correction'])) {
		$baseTimeString = gmstrftime("%Y-%m-%d %H:%M:00", strtotime($config['tz_correction']." hours", $baseTime));
	} else {
		$baseTimeString = gmstrftime("%Y-%m-%d %H:%M:00", $baseTime);
	}

	if (!isset($daysRequested)) {
		$daysRequested = 8;
	}
	if (isset($_GET['DaysRequested'])) {
		$daysRequested = $_GET['DaysRequested'];
	}
	if (isset($config['numDaysEPG'])) {
		$daysRequested = $config['numDaysEPG'];
	}
}
?>
