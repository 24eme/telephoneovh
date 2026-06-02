<?php

use \Ovh\Api;

function getApi($config) {
    return new Api($config['ovhApplicationKey'],
                   $config['ovhApplicationSecret'],
                   $config['ovhEndpoint'],
                   $config['ovhConsumerKey']);
}

function resolvePhoneName($phone, $phones) {
    if(!$phone || !isset($phones[$phone])) {

        return null;
    }

    return $phones[$phone];
}

function formatPhone($phone, $html = false) {
    $phone = preg_replace('/^0033/', '0', $phone);
    $phone = preg_replace('/([0-9]{2})/', '\1&nbsp;', $phone);
    $phone = preg_replace('/^0([^0-9])/', '\1', $phone);

    return $phone;
}

function formatPhoneCallTo($phone) {
    if(preg_match('/anonymous/', $phone)) {
        return null;
    }

    $phone = preg_replace('/^00([0-9]+)/', '+\1', $phone);

    return $phone;
}

function buildCall($dataCall, $phones, $config) {
    $call = array();
    $call['data'] = $dataCall;
    $call['id'] = md5($dataCall['consumptionId']);
    $call['date'] = $dataCall['creationDatetime'];
    $call['dateObject'] = new DateTime($call['date']);
    $call['status'] = null;
    $call['statusText'] = null;
    $call['duration'] = $dataCall['duration'];
    $call['durationMin'] = floor($call['duration'] / 60);
    $call['durationSec'] = $call['duration'] % 60;
    $call['callerPhone'] = null;
    $call['callerPhoneFormat'] = null;
    $call['callerName'] = null;
    $call['calledPhone'] = null;
    $call['calledPhoneFormat'] = null;
    $call['calledName'] = null;
    $call['color'] = null;
    $call['statusTextInfo'] = null;

    if($dataCall['wayType'] == 'incoming') {
        $call['status'] = 'RECU';
        $call['statusText'] = 'Reçu';
        $call['icon'] = 'bi bi-telephone-inbound';
        $call['callerPhone'] = $dataCall['calling'];
        $call['calledPhone'] = $dataCall['called'];
    }

    if($dataCall['wayType'] == 'outgoing') {
        $call['status'] = 'EMIS';
        $call['statusText'] = 'Émis';
        $call['icon'] = 'bi bi-telephone-outbound-fill';
        $call['callerPhone'] = isset($dataCall['dialed']) ? $dataCall['dialed'] : null;
        $call['calledPhone'] = $dataCall['calling'];
    }

    if($dataCall['wayType'] == 'transfer') {
        $call['status'] = 'RECU';
        $call['statusText'] = 'Reçu';
        $call['icon'] = 'bi bi-telephone-inbound';
        $call['callerPhone'] = $dataCall['calling'];
        $call['calledPhone'] = $dataCall['called'];
    }

    if($dataCall['wayType'] == 'incoming' && !$dataCall['duration']) {
        $call['status'] = 'MANQUE';
        $call['statusText'] = 'Manqué';
        $call['icon'] = 'bi bi-telephone-x-fill';
        $call['calledPhone'] = null;
        $call['color'] = 'text-danger';
    }

    if($call['calledPhone'] == $config['voiceMailNumber']) {
      $call['icon'] = 'bi bi-voicemail';
      $call['status'] = 'VOICEMAIL';
      $call['statusText'] = 'Manqué';
      $call['statusTextInfo'] = "reçu par le répondeur";
      $call['color'] = 'text-danger';
    }

    $call['callerName'] = resolvePhoneName($call['callerPhone'], $phones);
    $call['calledName'] = resolvePhoneName($call['calledPhone'], $phones);

    if(!isset($call['statusTextInfo']) && $call['statusTextInfo'] && $call['calledPhone']) {
      $call['statusTextInfo'] = "par ".(($call['calledName']) ? $call['calledName'] : formatPhone($call['calledPhone'], true));
    }

    return $call;
}

function getPhoneBookCsv($api, $config, $expiration = null) {
  $csvFile = __DIR__ . '/../cache/phonebook.csv';
  @mkdir(preg_replace("|/[^/]*$|", "", $csvFile), 0777, true);

  if(!is_null($expiration) && is_file($csvFile) && (time() - filemtime($csvFile)) > $expiration) {
    unlink($csvFile);
  }

  if(!is_file($csvFile)) {
    $csvPhoneBooks = null;
    foreach($api->get('/telephony/'.$config['ovhAccount'].'/phonebook') as $phoneBook) {
      $export = $api->get('/telephony/'.$config['ovhAccount'].'/phonebook/'.$phoneBook.'/export', array('format' => 'csv'));
      if(!isset($export['url']) || !$export['url']) {
        continue;
      }
      $csvPhoneBooks .= file_get_contents($export['url']);
    }

    if($csvPhoneBooks) {
      file_put_contents($csvFile, $csvPhoneBooks);
    }
  }

  return file_get_contents($csvFile);
}

function getCallJson($api, $config, $id, $previous = false) {
    $jsonFile = __DIR__ . '/../cache/calls/'.$id.'.json';
    @mkdir(preg_replace("|/[^/]*$|", "", $jsonFile), 0777, true);
    if(!is_file($jsonFile)) {

      file_put_contents($jsonFile, json_encode($api->get('/telephony/'.$config['ovhAccount'].'/service/'.$config['ovhService'].'/'.($previous ? 'previousVoiceConsumption' : 'voiceConsumption').'/'.$id)));
    }

    return json_decode(file_get_contents($jsonFile), true);
}

function getVoiceConsumption($api, $config, $previous = false, $expiration = null) {
  $apiMethodName = ($previous ? 'previousVoiceConsumption' : 'voiceConsumption');
  $jsonFile = __DIR__ . '/../cache/'.$apiMethodName.'.json';
  @mkdir(preg_replace("|/[^/]*$|", "", $jsonFile), 0777, true);

  if(!is_null($expiration) && is_file($jsonFile) && (time() - filemtime($jsonFile)) > $expiration) {

    unlink($jsonFile);
  }

  if(!is_file($jsonFile)) {
    file_put_contents($jsonFile, json_encode($api->get('/telephony/'.$config['ovhAccount'].'/service/'.$config['ovhService'].'/'.$apiMethodName)));
  }

  return json_decode(file_get_contents($jsonFile), true);
}
