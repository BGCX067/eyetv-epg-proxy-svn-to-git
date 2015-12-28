<?php

$config = array();

## Override the default 8 days of EPG data asked by EyeTV ? May I suggest 14 ?
$config['numDaysEPG'] = 14;

## Number of days before data is fetched and parsed again
## Use 2 or 3 if you have a slow CPU
## Use 0 to disable cache; only recommended while doing tests / initial setup
$config['cacheDays'] = 0;

#################################################
## Define providers here.
## epg-source: 'sd' or 'xmltv'
## type: See ReadMe for details
#################################################
$config['providers'] = array(
	'A_00001' => array('epg-source' => 'sd', 'type' => 'cable', 'desc' => 'your_provider_name_here', 'city' => 'your_city_here'),
#	'A_00002' => array('epg-source' => 'xmltv', 'type' => 'cable', 'desc' => 'Videotron (XMLTV)', 'city' => 'Montreal'),
#	'D_00003' => array('epg-source' => 'xmltv', 'type' => 'digital_cable', 'desc' => 'Shaw - Digital Cable', 'city' => 'Vancouver', 'tz_correction' => 3),
);

###############################################
## Define Schedules Direct access credentials here.
## Only needed for providers defined above with
##   'epg-source' => 'sd'
## Note: Use one account per provider.
###############################################
$config['sd_access'] = array(
	'A_00001' => array('username' => 'your_sd_username_here', 'password' => 'your_sd_password_here'),
);

## Compress EPG data ? (not needed when installed on the same Mac as EyeTV)
$config['deflateEPGData'] = false;

$config['localDir'] = '/Library/WebServer/Documents/eyetv-epg-proxy/dataservice.asmx';

## If your EyeTV EPG programs are not at the right time, you can substract or add hours here; -x will move all programs x hours earlier; just x will make all programs x hours later.
#$config['tz_correction'] = -3;

# Same as above, but only applies to one channel (XMLTV only). Repeat for as many channels as needed.
# Example: To affect only this channel: <channel id="3"> <display-name>CKMI</display-name> </channel>, use this:
#$config['channel_tz_correction']['3'] = -3;

# Merge two channels in one (XMLTV only). The source ids must be channels ids from your XMLTV file.
# The target id doesn't need to exist in your XMLTV file, but should correspond to a valid channel number in your EyeTV.
# 'from' and 'to' are times (24h format), prior to any tz_correction (i.e. as shown in the XMLTV file).
#$config['channels_merge'][] = array('source' => array(
#										array('id' => '3', 'from' => '0h00', 'to' => '18h00'),
#										array('id' => '4', 'from' => '18h00', 'to' => '0h00')),
#									'target' => array('id' => '5', 'name' => 'Merged 2 and 3'));
?>
