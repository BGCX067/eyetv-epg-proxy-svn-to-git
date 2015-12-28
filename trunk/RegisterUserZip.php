<?php require_once('functions.inc.php') ?>
<?php sendHTTPHeaders() ?>
<?php echo '<?xml version="1.0" encoding="utf-8"?>' ?>

<?php
if (isset($_GET['Zip']) && $_GET['Zip'] == 'check') {
	readfile('check_type.xml');
	exit();
}
?>

<ProviderCollection xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.titantv.com/services/dataservice">
	<ErrorCode>0</ErrorCode>
	<ErrorDescription>OK</ErrorDescription>
<?php foreach ($config['providers'] as $providerId => $provider) {
	if (eregi($provider['zipcodes'], $_GET['Zip']) === FALSE) { continue; }
	?>
	<Provider>
		<ProviderId><?php echo $providerId ?></ProviderId>
		<ServiceType><?php echo $provider['type'] ?></ServiceType>
		<Description><?php echo $provider['desc'] . ' - ' . $provider['city'] ?></Description>
		<City><?php echo $provider['city'] ?></City>
	</Provider>
<?php } ?>
</ProviderCollection>
