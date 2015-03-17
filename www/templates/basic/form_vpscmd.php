<?php

include('../../config.php');
include('../../functions.php');
include('../../libs/Paquet.class.php');

include('../../libs/Db.class.php');
include('../../libs/Socket.class.php');
include('../../libs/Vps.class.php');
include('../../libs/Server.class.php');
include('../../libs/Guest.class.php');
include('../../libs/User.class.php');
include('../../libs/Admin.class.php');

if(!empty($_GET['vps']) && !empty($_GET['cmd'])) {
	$paquet = new Paquet();
	$paquet -> add_action('vpsCmd', array($_GET['vps'], $_GET['cmd']));
	$paquet -> send_actions();
	
	echo nl2br($paquet -> getAnswer('vpsCmd'));
}

?>
