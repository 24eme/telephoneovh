<?php require __DIR__ . '/../app/app.php';

$api = getApi($config);

if(count(array_filter($_POST, function($value) { return trim($value); })) != 4) {
  throw new Exception("Le formulaire n'est pas complet");
}

foreach($api->get('/telephony/'.$config['ovhAccount'].'/phonebook') as $phoneBook) {
  break;
}

try {
  $api->post('/telephony/'.$config['ovhAccount'].'/phonebook/'.$phoneBook.'/phonebookContact', $_POST);
} catch(Exception $e) {
  echo $e->getMessage();
  exit;
}

$fp = fopen(__DIR__ . '/../cache/phonebook.csv', 'aw');
fputcsv($fp, [$_POST['group'],$_POST['surname'],$_POST['name'], $_POST['workPhone'], $_POST['workMobile'], $_POST['homePhone'], $_POST['homeMobile']], ';', '"');
fclose($fp);

header('Location: index.php');
