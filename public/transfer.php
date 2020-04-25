<?php
require __DIR__ . '/../app/app.php';

$account = $config['ovhAccount'];
$service = $config['ovhService'];
$api = getApi($config);

$transferNumber = null;

if(isset($_GET['phone']) && $_GET['phone']) {
    $transferNumber = $_GET['phone'];
}

if($transferNumber) {
    $result = $api->put('/telephony/'.$account.'/line/'.$service.'/options', array(
        'forwardUnconditional' => true,
        'forwardUnconditionalNature' => 'number',
        'forwardUnconditionalNumber' => $transferNumber,
    ));
} else {
    $result = $api->put('/telephony/'.$account.'/line/'.$service.'/options', array(
        'forwardUnconditional' => false,
    ));
}

header('Location: index.php');
