<?php
require __DIR__ . '/../app/app.php';

$format = isset($_GET['format']) ? $_GET['format'] : 'html';
$nbCalls = $config['nbHistory'];
if (isset($_GET['nbCalls'])) {
    $nbCalls = $_GET['nbCalls'];
}
$cache = true;
if (isset($_GET['cache'])) {
    $cache = false;
}
$phones = array();
$calls = array();
$account = $config['ovhAccount'];
$service = $config['ovhService'];
$api = getApi($config);

foreach(explode("\n", getPhoneBookCsv($api, $config, ($cache) ? 86400 * 7 : 0 /* 1 semaine de cache */)) as $line) {
    $data = str_getcsv($line, ";");
    if(!isset($data[2]) || !$data[2] || $data[2] == 'name') {
        continue;
    }
    $name = $data[2]." ".$data[1]. " (".$data[0].")";
    if($data[3]) {
        $phones[$data[3]] = $name;
    }
    if($data[4]) {
        $phones[$data[4]] = $name;
    }
    if($data[5]) {
        $phones[$data[5]] = $name;
    }
    if($data[6]) {
        $phones[$data[6]] = $name;
    }
}

asort($phones);
$ids = getVoiceConsumption($api, $config, false, ($cache) ? 60 : 0 /* 1 minute de cache */);
rsort($ids);
foreach($ids as $id) {
    if(count($calls) >= $nbCalls) {
        break;
    }
    $call = buildCall(getCallJson($api, $config, $id), $phones);
    $calls[$call['date'].$id] = $call;
}

$ids = getVoiceConsumption($api, $config, true, ($cache) ? 86400 : 0  /* 1 jour de cache */);
rsort($ids);
foreach($ids as $id) {
    if(count($calls) >= $nbCalls) {
        break;
    }
    $call = buildCall(getCallJson($api, $config, $id, true), $phones);
    $calls[$call['date'].$id]  = $call;
}

krsort($calls);

$options = $api->get('/telephony/'.$account.'/line/'.$service.'/options');

$transferNumber = null;
if($options['forwardUnconditional']) {
    $transferNumber = $options['forwardUnconditionalNumber'];
}
$tranfertPhoneBook = array();
foreach($phones as $phone => $name) {
    if(isset($config['transfertPhoneBookFilter']) && !preg_match('/'.$config['transfertPhoneBookFilter'].'/', $name)) {
        continue;
    }
    $tranfertPhoneBook[$phone] = $name;
}

if(!$cache) {
  header('Location: ?');
  exit;
}

?>
<?php if($format == "xml"): ?>
<?php header('Content-Type: text/xml'); ?>
<?php $link = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http').'://'.$_SERVER['HTTP_HOST'].preg_replace('|/[^/]*$|', '/', $_SERVER['REQUEST_URI']); ?>
<?xml version="1.0" encoding="utf-8"?>

<feed xmlns="http://www.w3.org/2005/Atom">
	<title>Historique des derniers appels</title>
    <?php foreach($calls as $call): ?>
    <entry>
		<title>Appel <?php echo $call['statusText'] ?> <?php echo ($call['status'] == 'EMIS') ? 'vers' : 'de' ?> <?php echo (isset($call['callerName'])) ? $call['callerName'] : formatPhoneCallTo($call['callerPhone']) ?><?php if($call['duration']): ?> d'une durée de <?php echo $call['durationMin'] ?> min et <?php echo $call['durationSec'] ?> sec<?php endif; ?> le <?php echo $call['dateObject']->format('d/m/Y') ?> à <?php echo $call['dateObject']->format('H:i') ?></title>
		<link href="<?php echo $link ?>" />
	    <id><?php echo $call['id'] ?></id>
		<updated><?php echo $call['date'] ?></updated>
		<author>
			<name><?php echo $call['callerName'] ?></name>
			<phone><?php echo formatPhoneCallTo($call['callerPhone']) ?></phone>
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
    <link rel="icon" href="favicon.ico" />

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="vendor/bootstrap.min.css?v4.6.2">
    <link rel="stylesheet" href="vendor/bootstrap-icons.min.css?v1.13.1">
<body>
    <div class="container" style="margin-top: 20px;">
        <div class="row" style="margin-bottom: 20px;">
            <div class="col-9">
            <form id="formTransfert" action="transfer.php" class="form-inline">
                <div class="custom-control custom-switch my-1 mr-sm-2">
                  <input id="transfertActive" type="checkbox" class="custom-control-input" <?php if($transferNumber): ?>checked="checked"<?php else: ?>disabled<?php endif; ?>>
                  <label class="custom-control-label" for="transfertActive">Transfert des appels</label>
                </div>
                <select name="phone" class="custom-select my-1 mr-sm-2" id="transfertNumberChoice">
                    <option value="">Désactivé</option>
                    <?php foreach($tranfertPhoneBook as $phone => $name): ?>
                    <option value="<?php echo $phone ?>" <?php if($transferNumber == $phone): ?>selected<?php endif; ?>><?php echo $name ?> - <?php echo formatPhone($phone) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            </div>
            <div class="col-3 text-right">
                <button type="button" autofocus=autofocus class="btn btn-info my-1" data-toggle="modal" data-target="#modalPhoneBook"><i class="bi bi-people-fill"></i> Carnet de contacts</button>
            </div>
        </div>

        <div class="modal fade" id="modalPhoneBook" tabindex="-1" role="dialog" aria-labelledby="modalPhoneBookLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-scrollable" role="document">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="modalPhoneBookLabel"><i class="bi bi-people-fill"></i> Carnet de contacts</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                    </button>
                  </div>
                  <div class="modal-body">
                        <input type="text" id="searchPhoneBook" class="form-control input-lg" value="" placeholder="Rechercher un contact" style="margin-bottom: 15px;" />
                        <table id="listPhoneBook" class="table">
                            <?php foreach($phones as $phone => $name): ?>
                                <tr>
                                    <td><?php echo preg_replace('/^(.+) \(.+\)$/', '\1', $name) ?></td>
                                    <td><?php echo preg_replace('/^.+\((.+)\)$/', '\1', $name) ?></td>
                                    <td class="text-right"><a href="tel:<?php echo formatPhoneCallTo($phone) ?>"><?php echo formatPhone($phone, true) ?></a></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                  </div>
                </div>
            </div>
        </div>

        <a class="float-right btn btn-sm text-muted" href="index.php?format=xml" title="Flux RSS"><i class="bi bi-rss-fill"></i></a>
        <a class="btn btn-link btn-sm text-muted float-right" href="?cache=reload" title="Recharger le cache"><i class="bi bi-arrow-repeat"></i></a>
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
                    <td><?php echo $call['dateObject']->format('d/m/Y H:i:s') ?></td>
                    <td><?php echo ($call['callerName']) ? $call['callerName'] : "<span class='text-muted'>Inconnu</span> <a href=\"https://www.ovhtelecom.fr/manager/#/telecom/telephony/".$account."/phonebook\" target=\"_blank\"><small>(Définir)</small></a>" ?></td>
                    <td><a href="tel:<?php echo formatPhoneCallTo($call['callerPhone']) ?>"><?php echo formatPhone($call['callerPhone'], true) ?></a></td>
                    <td><?php echo $call['statusText'] ?><?php if ($call['calledPhone']): ?> <small class="text-muted">par <?php echo ($call['calledName']) ? $call['calledName'] : formatPhone($call['calledPhone'], true) ?></small><?php endif; ?> <i class="<?php echo $call['icon']; ?> float-right"></i></td>
                    <td class="text-right"><?php if($call['duration']): ?><?php echo $call['durationMin'] ?>&nbsp;<small class=text-muted>min</small> <?php echo sprintf("%02d", $call['durationSec']) ?>&nbsp;<small class=text-muted>sec</small><?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </body>
    <script src="https://code.jquery.com/jquery-3.4.1.slim.min.js" integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js" integrity="sha384-wfSDF2E50Y2D1uUdj0O3uMBJnjuUD4Ih7YwaYd1iqfktj0Uod8GCExl3Og8ifwB6" crossorigin="anonymous"></script>
    <script type="text/javascript">
        document.querySelector('#transfertNumberChoice').onchange = function() {
            document.querySelector('#formTransfert').submit();
        };
        document.querySelector('#transfertActive').onchange = function() {
            document.querySelector('#transfertNumberChoice').value = "";
            document.querySelector('#transfertNumberChoice').onchange();
        };

        document.querySelector('#searchPhoneBook').onkeyup = function() {
            var lines = document.querySelectorAll('#listPhoneBook tr');
            var terms = this.value.split(' ');
            lines.forEach(function(line, index) {
                var words = line.innerText;

                for(keyTerm in terms) {
                    var termRegexp = new RegExp(terms[keyTerm], 'i');
                    if(words.search(termRegexp) < 0) {
                        line.classList.add("d-none");
                        return;
                    }
                }

                line.classList.remove("d-none");
            });
        }
        $('#modalPhoneBook').on('shown.bs.modal', function (e) {
            document.querySelector('#searchPhoneBook').focus();
        })
        $('#modalPhoneBook').on('hidden.bs.modal', function (e) {
            document.querySelector('#searchPhoneBook').value = "";
            document.querySelectorAll('#listPhoneBook tr').forEach(function(line, index) {
                line.classList.remove("d-none");
            });
        })
    </script>
</html>
<?php endif; ?>
