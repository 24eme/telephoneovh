<?php

function resolvePhoneName($phone, $phones) {
    if(!$phone || !isset($phones[$phone])) {

        return null;
    }

    return $phones[$phone];
}

function formatPhone($phone) {
    $phone = preg_replace('/^0033/', '', $phone);
    $phone = preg_replace('/^([0-9]+)/', '0\1', $phone);
    $phone = preg_replace('/([0-9]{2})/', '\1 ', $phone);

    return $phone;
}

function formatPhoneCall($phone) {
    $phone = preg_replace('/^00([0-9]+)/', '+\1', $phone);

    return $phone;
}

function buildCall($dataCall, $phones) {
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

    if($dataCall['wayType'] == 'incoming') {
        $call['status'] = 'RECU';
        $call['statusText'] = 'Reçu';
        $call['callerPhone'] = $dataCall['calling'];
        $call['calledPhone'] = $dataCall['called'];
    }

    if($dataCall['wayType'] == 'outgoing') {
        $call['status'] = 'EMIS';
        $call['statusText'] = 'Émis';
        $call['callerPhone'] = $dataCall['called'];
        $call['calledPhone'] = $dataCall['calling'];
    }

    if($dataCall['wayType'] == 'transfer') {
        $call['status'] = 'RECU';
        $call['statusText'] = 'Reçu';
        $call['callerPhone'] = $dataCall['calling'];
        $call['calledPhone'] = $dataCall['called'];
    }

    if(!$dataCall['duration']) {
        $call['status'] = 'MANQUE';
        $call['statusText'] = 'Manqué';
    }

    $call['callerName'] = resolvePhoneName($call['callerPhone'], $phones);
    $call['calledName'] = resolvePhoneName($call['calledPhone'], $phones);

    return $call;
}
