<?php
require __DIR__ . '/../app/app.php';

use \Ovh\Api;

$format = isset($_GET['format']) ? $_GET['format'] : 'html';
$nbCalls = $config['nbHistory'];
$phones = array();
$calls = array();
$ovh = new Api($config['ovhApplicationKey'],
               $config['ovhApplicationSecret'],
               $config['ovhEndpoint'],
               $config['ovhConsumerKey']);
$account = $config['ovhAccount'];
$service = $config['ovhService'];

foreach($ovh->get('/telephony/'.$account.'/phonebook') as $phoneBook) {
    $ids = $ovh->get('/telephony/'.$account.'/phonebook/'.$phoneBook.'/phonebookContact');
    foreach($ids as $id) {
        $contact = $ovh->get('/telephony/'.$account.'/phonebook/'.$phoneBook.'/phonebookContact/'.$id);
        foreach($contact as $key => $phone) {
            if(!preg_match("/(Phone|Mobile)/", $key) || !$phone) {
                continue;
            }

            $phones[$phone] = $contact['name'].' '.$contact['surname'].' ('.$contact['group'].')';
        }
    }
}

$ids = $ovh->get('/telephony/'.$account.'/service/'.$service.'/voiceConsumption');
rsort($ids);
foreach($ids as $id) {
    if(count($calls) >= $nbCalls) {
        break;
    }
    $call = buildCall($ovh->get('/telephony/'.$account.'/service/'.$service.'/voiceConsumption/'.$id), $phones);
    $calls[$call['date'].$id] = $call;
}

$ids = $ovh->get('/telephony/'.$account.'/service/'.$service.'/previousVoiceConsumption');
rsort($ids);
foreach($ids as $id) {
    if(count($calls) >= $nbCalls) {
        break;
    }
    $call = buildCall($ovh->get('/telephony/'.$account.'/service/'.$service.'/previousVoiceConsumption/'.$id), $phones);
    $calls[$call['date'].$id] = $call;
}

krsort($calls);

?>
<?php if($format == "xml"): ?>
<?php header('Content-Type: text/xml'); ?>
<?xml version="1.0" encoding="utf-8"?>

<feed xmlns="http://www.w3.org/2005/Atom">
	<title>Historique des derniers appels</title>
	<subtitle></subtitle>
	<link href="http://example.org/feed/" rel="self" />
	<link href="http://example.org/" />
	<id></id>
	<updated><?php echo current($calls)['date'] ?></updated>

    <?php foreach($calls as $call): ?>
    <entry>
		<title>Appel <?php echo $call['statusText'] ?> <?php echo (isset($call['callerName'])) ? "de ".$call['callerName'] : "du ".formatPhoneCall($call['callerPhone']) ?><?php if($call['duration']): ?> d'une durée de <?php echo $call['durationMin'] ?> min et <?php echo $call['durationSec'] ?> sec<?php endif; ?></title>
		<link href="" />
	<id><?php echo $call['id'] ?></id>
		<updated><?php echo $call['date'] ?></updated>
		<author>
			<name><?php echo $call['callerName'] ?></name>
			<phone><?php echo formatPhoneCall($call['callerPhone']) ?></phone>
		</author>
	</entry>
    <?php endforeach; ?>
</feed>
<?php else: ?>
<!DOCTYPE html>
<html lang="fr_FR">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta/css/bootstrap.min.css" integrity="sha384-/Y6pD6FV/Vv2HJnA6t+vslU6fwYXjCFtcEpHbNJ0lyAFsXTsjBbfaDjzALeQsN6M" crossorigin="anonymous">
</head>
<body>
    <div class="container" style="margin-top: 20px;">
        <a class="float-right btn btn-sm btn-link" href="index.php?format=xml">Feed</a>
        <h2 style="margin-bottom: 20px;">Historique des <?php echo $nbCalls ?> derniers appels</h2>
        <table class="table table-striped table-bordered table-sm">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Nom</th>
                    <th>Numéro</th>
                    <th>État</th>
                    <th>Durée</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($calls as $call): ?>
                <tr>
                    <td><?php echo $call['dateObject']->format('d/m/Y H:m:s') ?></td>
                    <td><?php echo ($call['callerName']) ? $call['callerName'] : "<span class='text-muted'>Inconnu</span> <a href=\"https://www.ovhtelecom.fr/manager/#/telephony/".$account."/phonebook\" target=\"_blank\"><small>(Définir)</small></a>" ?></td>
                    <td><a href="callto:<?php echo formatPhoneCall($call['callerPhone']) ?>"><?php echo formatPhone($call['callerPhone']) ?></a></td>
                    <td><?php echo $call['statusText'] ?><?php if ($call['status'] == 'RECU'): ?> <small class="text-muted">par <?php echo ($call['calledName']) ? $call['calledName'] : formatPhone($call['calledPhone']) ?></small><?php endif; ?></td>
                    <td class="text-right"><?php if($call['duration']): ?><?php echo $call['durationMin'] ?>&nbsp;<small class=text-muted>min</small> <?php echo sprintf("%02d", $call['durationSec']) ?>&nbsp;<small class=text-muted>sec</small><?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </body>
</html>
<?php endif; ?>
