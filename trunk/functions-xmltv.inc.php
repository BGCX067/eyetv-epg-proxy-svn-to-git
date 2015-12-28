<?php
include_once('config.inc.php');
include_once('functions.inc.php');
include_once('series_genres.inc.php');

function convertTimeToBaseTime($p_time, $channel) {
	global $baseTime, $config;
	$time = strtotime($p_time);
	
	// Per channel TZ correction:
	if (isset($config['channel_tz_correction'][$channel])) {
		$corr = $config['channel_tz_correction'][$channel];
		if (isset($config['tz_correction'])) {
			$corr -= $config['tz_correction'];
		}
		return (($time - $baseTime) / 60) + ($corr * 60);
	}
	return ($time - $baseTime) / 60;
}

// Generate unique & numeric station Ids to replace alpha-numeric station Ids
// Will use those new numeric-only station Ids when writing the XML data
function normalizeStationId($stations) {
	global $newStationId, $config;
	$newStationId = array();
	$i = 1;
	foreach ($stations as $station) {
		$newStationId[(string) $station['id']] = $i++;
	}

	if (isset($config['channels_merge'])) {
		foreach ($config['channels_merge'] as $channels_merge) {
			if (!isset($newStationId[$channels_merge['target']['id']])) {
				$newStationId[$channels_merge['target']['id']] = $i++;
			}
		}
	}
}

function getStationsXMLTV($stations, $providerId) {
	global $newStationId, $config;
	normalizeStationId($stations);

	$resultXML = "";
	$resultXML .= "  <Stations>\n";

	$stationsRFID = array();
	$stationDisplayName = array();
	foreach ($stations as $station) {
		$stationId = (string) $station['id'];
		$stationId = $newStationId[$stationId];
		$stationsRFID[$stationId] = 0;
		foreach ($station->{'display-name'} as $displayName) {
			if (ereg("^[0-9]+$", $displayName)) {
				$stationsRFID[$stationId] = (int) $displayName;
				break;
			}
		}
		// Didn't find a rfid, try to get it from the ID, or fake it if it can't find any.
		if ($stationsRFID[$stationId] == 0) {
			if (ereg("^([0-9]+)$", (string) $station['id'], $regs)) {
				$stationsRFID[$stationId] = (int) $regs[1];
			}
			else if (ereg("^([0-9]+)\..*", (string) $station['id'], $regs)) {
				$stationsRFID[$stationId] = (int) $regs[1];
			} else {
				$stationsRFID[$stationId] = $stationId;
			}
		}
		$stationDisplayName[$stationsRFID[$stationId]] = $station->{'display-name'}[0];
	}

	if (isset($config['channels_merge'])) {
		foreach ($config['channels_merge'] as $channels_merge) {
			$stationId = $newStationId[$channels_merge['target']['id']];
			$stationsRFID[$stationId] = $channels_merge['target']['id'];
			$stationDisplayName[$stationsRFID[$stationId]] = $channels_merge['target']['name'];
		}
	}

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

	foreach ($newStationId as $stationId) {
		$resultXML .= "    <Station>
      <station_id>".$stationId."</station_id>
      <call_sign>".xmlize($stationDisplayName[$stationsRFID[$stationId]])."</call_sign>
      <rf_channel>".$stationsRFID[$stationId]."</rf_channel>
    </Station>
	";
	}

	$resultXML .= "  </Stations>\n";
	return $resultXML;
}

function getProgramHash($program) {
	$start = (string) $program['start'];
	$end = (string) $program['stop'];
	$hash = startstopToRuntime($start, $end) . $program->title;
	if (isset($program->{'sub-title'})) {
		$hash .= $program->{'sub-title'};
	}
	if (isset($program->{'episode-num'})) {
		if (is_array($program->{'episode-num'})) {
			$hash .= $program->{'episode-num'}[0];
		} else {
			$hash .= $program->{'episode-num'};
		}
	}
	if (isset($program->desc)) {
		$hash .= $program->desc;
	}
	if (isset($program->category)) {
		if (is_array($program->category)) {
			$hash .= $program->category[0];
		} else {
			$hash .= $program->category;
		}
	}
	return md5($hash);
}

function startstopToRuntime($start, $stop) {
	$timeS = strtotime($start);
	$timeE = strtotime($stop);
	return ($timeE - $timeS) / 60;
}

function isProgramSeries($program) {
	if (isset($program->{'sub-title'})) {
		return true;
	}
	if (!isset($program->category)) {
		return false;
	}
	foreach ($program->category as $cat) {
		if ($cat == 'Series') {
			return true;
		}
	}
	return false;
}

function getProgramsXMLTV($programs) {
	global $newProgramId, $numPrograms;
	$newProgramId = array();

	$resultXML = "";
	$resultXML .= "  <Programs>\n";

	$i = 1;
	foreach ($programs as $program) {
		$hash = getProgramHash($program);

		// Is this an existing program ?
		if (isset($newProgramId[$hash])) {
			continue;
		}
		$newProgramId[$hash] = $i;

		$start = (string) $program['start'];
		$end = (string) $program['stop'];

		$resultXML .= "    <Program>
      <program_id>".$i."</program_id>
      <run_time>".startstopToRuntime($start, $end)."</run_time>
      <title>".xmlize($program->title)."</title>\n";

		// Serie
		if (isProgramSeries($program)) {
			if (isset($program->{'sub-title'})) {
				$resultXML .= "      <episode_title>".xmlize($program->{'sub-title'})."</episode_title>\n";
			}
			if (isset($program->{'episode-num'})) {
				foreach ($program->{'episode-num'} as $num) {
					$resultXML .= "      <episode_id>".$num."</episode_id>\n";
					break;
				}
			}
			$resultXML .= "      <is_episodic>Y</is_episodic>\n";
		} else {
			$resultXML .= "      <is_episodic>N</is_episodic>\n";
		}

		if (isset($program->desc)) {
			$resultXML .= "      <description>".xmlize($program->desc)."</description>\n";
		} else {
			$resultXML .= "      <description />\n";
		}
		if (isset($program->category)) {
			foreach ($program->category as $cat) {
				if ($cat != 'Series') {
					$resultXML .= "      <genre>".xmlize($cat)."</genre>\n";
					break;
				}
			}
		} else {
			$resultXML .= "      <genre />\n";
		}
		$resultXML .= "      <show_type>Other</show_type>\n";

		$resultXML .= "    </Program>\n";
		$i++;
	}

	$resultXML .= "  </Programs>\n";

	$numPrograms = $i;
	return $resultXML;
}

function getSchedulesXMLTV($programs) {
	global $newProgramId, $newStationId, $config;

	$resultXML = "";
	$resultXML .= "  <Schedules>\n";

	foreach ($programs as $program) {
		$hash = getProgramHash($program);

		$channel = (string) $program['channel'];

		$start = (string) $program['start'];
		$startTime = convertTimeToBaseTime($start, $channel);
		$end = (string) $program['stop'];
		$stopTime = convertTimeToBaseTime($end, $channel);

		$skip = FALSE;
		if (isset($config['channels_merge'])) {
			$merged = FALSE;
			foreach ($config['channels_merge'] as $channels_merge) {
				foreach ($channels_merge['source'] as $source) {
					if (shouldMerge($channel, $start, $end, $source)) {
						$resultXML .= getScheduleXML($hash, $channels_merge['target']['id'], convertTimeToBaseTime($start, $channels_merge['target']['id']), convertTimeToBaseTime($end, $channels_merge['target']['id']), $program);
						$merged = TRUE;
						break;
					}
				}
				if (!$merged && $channel == $channels_merge['target']['id']) {
					$skip = TRUE;
				}
			}
		}

		if (!$skip) {
			$resultXML .= getScheduleXML($hash, $channel, $startTime, $stopTime, $program);
		}
	}
	
	$resultXML .= "  </Schedules>\n";
	return $resultXML;
}

function shouldMerge($channel, $p_start, &$p_end, $source) {
	if ($channel != $source['id']) {
		return FALSE;
	}
	$start = strtotime($p_start);
	$hour = sprintf('%04d', str_ireplace(array('h',':'), '', $source['from']));
	$from = date('Ymd'.$hour.'00 O', $start);
	
	$hour = sprintf('%04d', str_ireplace(array('h',':'), '', $source['to']));
	if ($hour == '0000') {
		$to = date('Ym'.(date('d', $start)+1).$hour.'00 O', $start);
	} else {
		$to = date('Ymd'.$hour.'00 O', $start);
	}
	if ($start >= strtotime($from) && $start < strtotime($to)) {
		$end = strtotime($p_end);
		if ($end > strtotime($to)) {
			$p_end = $to;
		}
		return TRUE;
	}
	return FALSE;
}

function getScheduleXML($hash, $channel, $startTime, $stopTime, $program) {
	global $newProgramId, $newStationId;

	$resultXML = "    <Schedule>
    <program_id>".$newProgramId[$hash]."</program_id>
    <station_id>".$newStationId[$channel]."</station_id>
    <start_time>".$startTime."</start_time>
    <end_time>".$stopTime."</end_time>\n";
	if (isset($program->hd)) {
		$resultXML .= "      <hd>Y</hd>\n";
	} else {
		$resultXML .= "      <hd>N</hd>\n";
	}

	if (isset($program->audio) && isset($program->audio->stereo)) {
		$resultXML .= "      <stereo>Y</stereo>\n";
	} else {
		$resultXML .= "      <stereo>N</stereo>\n";
	}

	if (isset($program->subtitles) && (string) $program->subtitles['type'] == 'teletext') {
		$resultXML .= "      <cc>Y</cc>\n";
	} else {
		$resultXML .= "      <cc>N</cc>\n";
	}

	$resultXML .= "    </Schedule>\n";
	return $resultXML;
}

// Returns: Response object that contains all EPG data.
function getEPGDataXMLTV($providerId) {
	global $baseTime, $usingCache, $daysRequested, $config;

	$response = simplexml_load_file("cache/xmltv-$providerId.xml");

	$usingCache = false;
	return $response;
}

// Generates the complete EPG data as a XML file (in TitanTV format)
function parseEPGDataXMLTV($providerId) {
	global $stationsXML, $programsXML, $schedulesXML, $baseTime;
	global $config, $numStations, $numPrograms, $numSchedules;

	$response = getEPGDataXMLTV($providerId);

	$stations = $response->channel;
	$stationsXML = getStationsXMLTV($stations, $providerId);
	$numStations = sizeof($stations);

	$programs = $response->programme;
	$programsXML = getProgramsXMLTV($programs);

	$schedulesXML = getSchedulesXMLTV($programs);
	$numSchedules = sizeof($programs);
}

// Generates the complete EPG data as a XML file (in TV Guide format)
function parseEPGDataXMLTVTVGuide($providerId) {
	global $config, $programsXML, $system_timezone;

	$response = getEPGDataXMLTV($providerId);

	$start_time = strtotime($_GET['from_day']);
	$end_time = strtotime("+".$_GET['DaysRequested']."days", $start_time);

	//$_GET['from_day']
	$programsXML = '';
	$programIds = array();
	$i = 1;
	
	foreach ($response->channel as $channel) {
		$source_id = (strpos($channel['id'], '.') !== FALSE ? '99' : '') . str_replace('.', '', $channel['id']);

		$programsXML .= '		<source>
			<source_ID>'.$source_id.'</source_ID>
			<guide>' . "\n";
		
		foreach ($response->programme as $program) {
			if ((string) $program['channel'] != (string) $channel['id']) { continue; }

			$start = (string) $program['start'];
			$end = (string) $program['stop'];
			
			$program_start_time = substr($start, 0, 4).'-'.substr($start, 4, 2).'-'.substr($start, 6, 2).' '.substr($start, 8, 2).':'.substr($start, 10, 2);
			$program_start_time = strtotime("$program_start_time $system_timezone");
			if ($program_start_time < $start_time || $program_start_time >= $end_time) { continue; }
			
			unset($category);
	      	$runtime = startstopToRuntime($start, $end) * 60;
			if (!empty($program->category)) {
				$category = (string) $program->category[0];
			}

			$program->title = win2ascii($program->title);
			if (isset($program->{'sub-title'})) {
				$program->{'sub-title'} = win2ascii($program->{'sub-title'});
			}

			$hash = getProgramHash($program);
			if (!isset($programIds[$hash])) {
				$programIds[$hash] = $i++;
			}
			
			$programsXML .= '			<program>
				<start_date>'.date('Ymd', $program_start_time).'</start_date>
				<start_time>'.date('Hi', $program_start_time).'</start_time>
				<duration>'.$runtime.'</duration>
				<program_ID>'.$programIds[$hash].'</program_ID>
				<tv_rating>None</tv_rating>
				<tv_advisory>None</tv_advisory>
				<program_showing_type>None</program_showing_type>
				<audio_level_name>'.(@$program->hd ? 'Dolby 5.1' : 'Stereo').'</audio_level_name>
				<closed_captioned>Y</closed_captioned>
				<program_airing_type>None</program_airing_type>
				<program_color_type>Color</program_color_type>
				<joined_in_progress>N</joined_in_progress>
				<letter_box>N</letter_box>
				<black_white>N</black_white>
				<continued>N</continued>
				<hdtv_level>'.(@$program->hd ? '1080i' : 'None').'</hdtv_level>
				<subtitled>N</subtitled>
				<genre>'.(@$category == 'Sports' ? 'sports' : (@$category == 'News' ? 'newscast' : getSerieGenre($program->title))).'</genre>
				<show_type>'.(isProgramSeries($program) ? 'EP' : (@$category == 'Movies' ? 'MV' : (@$category == 'Sports' ? 'SP' : 'SM'))).'</show_type>
				<grid_title>'.xmlize(substr($program->title, 0, 15)).'</grid_title>
				<short_title>'.xmlize(substr($program->title, 0, 30)).'</short_title>
				<short_grid_title>'.xmlize(substr($program->title, 0, 8)).'</short_grid_title>
				<medium_title>'.xmlize(substr($program->title, 0, 50)).'</medium_title>
				<long_title>'.xmlize($program->title).'</long_title>
				'.(isset($program->{'sub-title'}) ? '<episode_title>'.xmlize($program->{'sub-title'}).'</episode_title>'."\n\t\t\t\t" : '').
				'<half_stars_rating>0</half_stars_rating>
				<release_year>0</release_year>
				<mpaa_rating_reason />
				<audio_level>'.(@$program->hd ? 'Dolby 5.1' : 'Stereo').'</audio_level>
			</program>' . "\n";
		}

		$programsXML .= '			</guide>
			</source>'."\n";
	}
}

function getSerieGenre($title) {
	global $series_genres;
	if (isset($series_genres[(string) $title])) {
		return $series_genres[(string) $title];
	}
	return 'other';
}

function win2ascii($str) {
	$search = explode(",","ç,é,æ,œ,á,í,ó,ú,à,è,ì,ò,ù,ä,ë,ï,ö,ü,ÿ,â,ê,î,ô,û,å,e,i,ø,u,ñ,Ç,É,Æ,Œ,Á,Í,Ó,Ú,À,È,Ì,Ò,Ù,Ä,Ë,Ï,Ö,Ü,Ÿ,Â,Ê,Î,Ô,Û,Å,E,I,Ø,U,Ñ");
	$replace = explode(",","c,e,ae,oe,a,i,o,u,a,e,i,o,u,a,e,i,o,u,y,a,e,i,o,u,a,e,i,o,u,n,C,E,AE,OE,A,I,O,U,A,E,I,O,U,A,E,I,O,U,Y,A,E,I,O,U,A,E,I,O,U,N");
	return str_replace($search, $replace, $str);
}
?>
