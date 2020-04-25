<?php

require __DIR__ . '/../app/app.php';

$api = getApi($config);

$rights = array(
(object) [
    'method'    => 'GET',
    'path'      => '/telephony/*'
],
(object) [
    'method'    => 'PUT',
    'path'      => '/telephony/*'
],
(object) [
    'method'    => 'POST',
    'path'      => '/telephony/*'
]);

$credentials = $api->requestCredentials($rights);

print_r($credentials);
