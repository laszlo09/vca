<?php

include('../../config.php');
include('../../functions.php');
include('../../libs/Paquet.class.php');

$paquet = new Paquet();
$paquet -> add_action('serverRestart', array($_GET['server']));
$paquet -> send_actions();

?>