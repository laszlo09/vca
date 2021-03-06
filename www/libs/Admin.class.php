<?php 

/**
 * Administrator class
 * @author V_paranoiaque
 */
class Admin extends User {

	/*** VCA ***/

	/**
	 * VCA stats
	 */
	function vcaStats() {
		$link = Db::link();
	
		$sql = 'SELECT count(user_id) as nb
		        FROM uservca';
		$req = $link->prepare($sql);
		$req->execute();
		$do = $req->fetch(PDO::FETCH_OBJ);
		$nbUser = $do->nb;
	
		$sql = 'SELECT count(server_id) as nb
		        FROM server';
		$req = $link->prepare($sql);
		$req->execute();
		$do = $req->fetch(PDO::FETCH_OBJ);
		$nbServer = $do->nb;
	
		$sql = 'SELECT count(vps_id) as nb
		        FROM vps
		        WHERE nproc>0';
		$req = $link->prepare($sql);
		$req->execute();
		$do = $req->fetch(PDO::FETCH_OBJ);
		$nbVpsRun = $do->nb;
	
		$sql = 'SELECT count(vps_id) as nb
		        FROM vps
		        WHERE nproc=0';
		$req = $link->prepare($sql);
		$req->execute();
		$do = $req->fetch(PDO::FETCH_OBJ);
		$nbVpsStop = $do->nb;
	
		$sql = 'SELECT count(request_topic_id) as nb
		        FROM request_topic
		        WHERE request_topic_resolved=0';
		$req = $link->prepare($sql);
		$req->execute();
		$do = $req->fetch(PDO::FETCH_OBJ);
		$request = $do->nb;
		
		$sql = 'SELECT count(ip) as nb
		        FROM ipv4';
		$req = $link->prepare($sql);
		$req->execute();
		$do = $req->fetch(PDO::FETCH_OBJ);
		$nbIp = $do->nb;
		
		return array(
				'nbVps'    => $nbVpsRun+$nbVpsStop,
				'nbVpsRun' => $nbVpsRun,
				'nbVpsStop'=> $nbVpsStop,
				'nbServer' => $nbServer,
				'nbUser'   => $nbUser,
				'nbIp'     => $nbIp,
				'request'  => $request
		);
	}
	
	/**
	 * Define the Token configuration
	 * @param string $domainkey shared domain key
	 * @param number $key_size generated code size
	 * @param timestamp $validity generated code validity
	 */
	function configurationDefine($domainkey, $key_size, $validity) {
		if($key_size != 4  && $key_size != 8  && 
		   $key_size != 16 && $key_size != 32) {
			$key_size = 4;
		}
		
		if($validity != 15 && $validity != 30 && $validity != 45 &&
		   $validity != 60 && $validity != 90 && $validity != 120) {
			$validity = 60;
		}
		
		$link = Db::link();
		
		$sql1 = 'DELETE FROM configuration 
		         WHERE conf_index="domain_key"';
		$sql2 = 'DELETE FROM configuration
		         WHERE conf_index="key_size"';
		$sql3 = 'DELETE FROM configuration
		         WHERE conf_index="key_period"';
		
		$req1 = $link->prepare($sql1);
		$req2 = $link->prepare($sql2);
		$req3 = $link->prepare($sql3);
		
		$req1->execute();
		$req2->execute();
		$req3->execute();
		
		$sql = 'INSERT INTO configuration
		        (conf_index, conf_value) 
		        VALUES
		        ("domain_key", :domain_key),
		        ("key_size",   :key_size),
		        ("key_period", :key_period)';
		$req = $link->prepare($sql);
		$req->bindValue(':domain_key', $domainkey, PDO::PARAM_STR);
		$req->bindValue(':key_size',   $key_size, PDO::PARAM_STR);
		$req->bindValue(':key_period', $validity, PDO::PARAM_STR);
		$req->execute();
	}
	
	/**
	 * Next free ID, for a new VPS
	 * @return ID
	 */
	static function vpsNextId() {
		$link = Db::link();
		$list = array();
		$vps_new = 0;
	
		$sql = 'SELECT MAX(vps_id) as max
		        FROM vps';
		$req = $link->prepare($sql);
		$req->execute();
		$do = $req->fetch(PDO::FETCH_OBJ);
		
		if(empty($do->max)) {
			return 1;
		}
	
		$vps_max = $do->max;
	
		$sql = 'SELECT Count(vps_id) as nb
		        FROM vps';
		$req = $link->prepare($sql);
		$req->execute();
		$do = $req->fetch(PDO::FETCH_OBJ);
	
		$vps_nb = $do->nb;
	
		if($vps_nb == $vps_max) {
			$vps_new = $vps_max+1;
		}
		else {
			$sql = 'SELECT vps_id
			        FROM vps
			        ORDER BY vps_id ASC';
			$req = $link->prepare($sql);
			$req->execute();
			while ($do = $req->fetch(PDO::FETCH_OBJ)) {
				$list[] = $do->vps_id;
			}
	
			for ($i=1; $i<=$vps_max;$i++) {
				if(!in_array($i, $list)) {
					$vps_new = $i;
					break;
				}
			}
		}
	
		return $vps_new;
	}
	
	/*** Users ***/

	/**
	 * Return all users
	 */
	function userList() {
		$link = Db::link();
		$list = array();
			
		$sql = 'SELECT user_id, user_name, user_rank, user_mail, v.nb,
		               user_language, user_tokenid, user_strongauth
		        FROM uservca
		        LEFT JOIN (SELECT vps_owner, count(vps_owner) as nb
		                   FROM vps GROUP BY vps_owner) v
		        ON v.vps_owner=uservca.user_id';
		$res = $link->query($sql);
		while($do = $res->fetch(PDO::FETCH_OBJ)) {
			if(empty($do->nb)) {
				$do->nb = 0;
			}
			$list[$do->user_id] = array(
					'user_id'   => $do->user_id,
					'user_name' => $do->user_name,
					'user_rank' => $do->user_rank,
					'user_mail' => $do->user_mail,
					'user_language'  => $do->user_language,
					'user_tokenid'   => $do->user_tokenid,
					'user_strongauth'=> $do->user_strongauth,
					'nb_vps'    => $do->nb
			);
		}
		
		return $list;
	}
		
	/**
	 * Update user information
	 * @param number $id user id
	 * @param string $user_name user name
	 * @param string $user_mail user mail
	 * @param string $language user language
	 * @param number $rank user rank (-1, 0 or 1)
	 * @return error
	 */
	function userUpdate($id, $user_name='', $user_mail='', $language='', $rank=-1) {
	
		if(empty($id)) {
			$id = $this->getId();
		}
		
		if(empty($user_name) or empty($user_mail)) {
			return 1;
		}
		elseif(!filter_var($user_mail, FILTER_VALIDATE_EMAIL)) {
			return 9;
		}
		elseif(strlen($user_name) < 4 or strlen($user_name) > 25) {
			return 10;
		}
		
		$link = Db::link();
		$sql = 'SELECT user_id, user_language
		        FROM uservca
		        WHERE user_name= :user_name';
		$req = $link->prepare($sql);
		$req->bindValue(':user_name', $user_name, PDO::PARAM_STR);
		$req->execute();
		$do = $req->fetch(PDO::FETCH_OBJ);
	
		if(!empty($do->user_id) && $do->user_id != $id) {
			return 2;
		}
		
		$languageList = User::languageList();
		
		if(empty($language) or empty($languageList[$language])) {
			$language = $do->user_language;
		}
		
		if($rank != 0 && $rank != 1) {
			$rank = $do->user_rank;
		}
		
		if($id == $this->getId() && $rank != 1) {
			return 15;
		}
		
		$sql = 'UPDATE uservca
		        SET user_name= :user_name,
		            user_mail= :user_mail,
		            user_rank= :user_rank,
		            user_language=:user_language
		        WHERE user_id= :user_id';
		$req = $link->prepare($sql);
		$req->bindValue(':user_name', $user_name, PDO::PARAM_STR);
		$req->bindValue(':user_mail', $user_mail, PDO::PARAM_STR);
		$req->bindValue(':user_rank', $rank, PDO::PARAM_STR);
		$req->bindValue(':user_language', $language, PDO::PARAM_STR);
		$req->bindValue(':user_id', $id, PDO::PARAM_INT);
		$req->execute();
		
		return 5;
	}
	
	/**
	 * Update password
	 * @param string $password new password
	 * @param number $id user id
	 */
	function userDefinePassword($password, $id=0) {
		$link = Db::link();
		
		if(empty($id)) {
			return null;
		}
		
		$sql = 'UPDATE uservca
		        SET user_password=:user_password
		        WHERE user_id=:user_id';
		$req = $link->prepare($sql);
		$req->bindValue(':user_password', hash('sha512', $id.$password), PDO::PARAM_STR);
		$req->bindValue(':user_id', $id, PDO::PARAM_INT);
		$req->execute();
		
		return 13;
	}
	
	/**
	 * Define user token information
	 * @param number $tokenId user token id
	 * @param number $pin user pin
	 * @param boolean $activated activated or not
	 * @param number $userId user id
	 */
	function userDefineToken($tokenId, $pin, $activated, $userId) {
		$link = Db::link();
		
		if(empty($userId)) {
			return null;
		}
		if(empty($tokenId)) {
			$tokenId = $this->getId();
		}
		
		$sql = 'UPDATE uservca
		        SET user_strongauth=:user_strongauth,
		            user_tokenid=:user_tokenid
		        WHERE user_id=:user_id';
		$req = $link->prepare($sql);
		$req->bindValue(':user_strongauth', $activated, PDO::PARAM_INT);
		$req->bindValue(':user_tokenid', $tokenId, PDO::PARAM_STR);
		$req->bindValue(':user_id', $userId, PDO::PARAM_INT);
		$req->execute();
		
		if(!empty($pin)) {
			$sql = 'UPDATE uservca
			        SET user_pin=:user_pin
			        WHERE user_id=:user_id';
			$req = $link->prepare($sql);
			$req->bindValue(':user_pin', $pin, PDO::PARAM_STR);
			$req->bindValue(':user_id', $userId, PDO::PARAM_INT);
			$req->execute();
		}
		
		return 14;
	}
	
	/**
	 * Create a new user
	 * @param string $user_name user name
	 * @param string $user_mail user mail
	 * @param string $user_password user password
	 * @return number error
	 */
	function userNew($user_name='', $user_mail='', $user_password='') {
	
		if(empty($user_name) or empty($user_mail) or empty($user_password)) {
			return 1;
		}
	
		$link = Db::link();
		$sql = 'SELECT user_id
		        FROM uservca
		        WHERE user_name= :user_name';
		$req = $link->prepare($sql);
		$req->bindValue(':user_name', $user_name, PDO::PARAM_STR);
		$req->execute();
		$do = $req->fetch(PDO::FETCH_OBJ);
	
		if(!empty($do->user_id)) {
			return 2;
		}
	
		$sql = 'INSERT INTO uservca
		        (user_name, user_mail, user_created)
		        VALUES
		        (:user_name, :user_mail, :user_created)';
		$req = $link->prepare($sql);
		$req->bindValue(':user_name', $user_name, PDO::PARAM_STR);
		$req->bindValue(':user_mail', $user_mail, PDO::PARAM_STR);
		$req->bindValue(':user_created', $_SERVER['REQUEST_TIME'], PDO::PARAM_INT);
		$req->execute();
		
		if(DB_TYPE == 'PGSQL') {
			$sql = 'SELECT currval(\'uservca_user_id_seq\') as user_id
			        FROM uservca';
			$req = $link->prepare($sql);
			$req->execute();
			$do = $req->fetch(PDO::FETCH_OBJ);
			
			$user_id = $do->user_id;
		}
		else {
			$user_id = $link->lastInsertId();
		}
		
		$sql = 'UPDATE uservca
		        SET user_password=:user_password
		        WHERE user_id=:user_id';
		$req = $link->prepare($sql);
		$req->bindValue(':user_password', hash('sha512', $user_id.$user_password), PDO::PARAM_STR);
		$req->bindValue(':user_id', $user_id, PDO::PARAM_INT);
		$req->execute();
		
		return 8;
	}
	
	/**
	 * Delete an user
	 * @param number $id user id
	 * @return error number
	 */
	function userDelete($id) {
		if($id == $this->getId()) {
			return 6;
		}
	
		$link = Db::link();
	
		$sql = 'UPDATE vps
		        SET vps_owner=0
		        WHERE vps_owner= :user_id';
		$req = $link->prepare($sql);
		$req->bindValue(':user_id', $id, PDO::PARAM_INT);
		$req->execute();
		
		$sql = 'DELETE FROM uservca
		        WHERE user_id= :user_id';
		$req = $link->prepare($sql);
		$req->bindValue(':user_id', $id, PDO::PARAM_INT);
		$req->execute();
	
		if($req->rowCount() == 0) {
			return 3;
		}
		else {
			return 7;
		}
	}
	
	/**
	 * Return User Vps
	 * @param number $id user id
	 */
	function userVps($id) {
		$user = new User($id);
		return $user-> vpsList();
	}

	/*** Requests ***/
	
	/**
	 * Return all the requests
	 */
	function requestList() {
		$list = array();
	
		$link = Db::link();
		$sql = 'SELECT request_topic_id, request_topic_title, request_topic_created,
		               request_topic_resolved, request_topic_author, user_name, user_id
		        FROM request_topic
		        JOIN uservca ON request_topic_author=user_id
		        ORDER BY request_topic_id DESC';
		$req = $link->prepare($sql);
		$req->execute();
		
		while ($do = $req->fetch(PDO::FETCH_OBJ)) {
			$list[$do->request_topic_id] = array(
				'topic'    => $do->request_topic_id,
				'title'    => $do->request_topic_title,
				'created'  => $do->request_topic_created,
				'resolved' => $do->request_topic_resolved,
				'user_name'=> $do->user_name,
				'user_id'  => $do->user_id
			);
		}
	
		return $list;
	}
	
	/*** IP ***/
	
	/**
	 * Return Free IP
	 * @return IP list
	 */
	function ipFree() {
		$link = Db::link();
	
		$ipList = $this->ipList();
		$ipUsed = array();
		$ipFree = array();
	
		$sql = 'SELECT vps_ipv4
		        FROM vps';
		$req = $link->prepare($sql);
		$req->execute();
		while ($do = $req->fetch(PDO::FETCH_OBJ)) {
			$ipUsed[] = $do->vps_ipv4;
		}
	
		foreach($ipList as $ip) {
			if(!in_array($ip['ip'], $ipUsed)) {
				$ipFree[] = $ip['ip'];
			}
		}
	
		return $ipFree;
	}
	
	/**
	 * Return allowed IP
	 * @return Ip list
	 */
	function ipList() {
		$link = Db::link();
		$list = array();
		
		if(DB_TYPE == 'MYSQL') {
			$sql = 'SELECT ip, vps_id, vps_name
			        FROM ipv4
			        LEFT JOIN vps ON vps.vps_ipv4=ip
			        ORDER BY INET_ATON(ip)';
		}
		elseif(DB_TYPE == 'PGSQL') {
			$sql = 'SELECT ip, vps_id, vps_name
			        FROM ipv4
			        LEFT JOIN vps ON vps.vps_ipv4=ip
			        ORDER BY INET(ip)';
		}
		else {
			$sql = 'SELECT ip, vps_id, vps_name
			        FROM ipv4
			        LEFT JOIN vps ON vps.vps_ipv4=ip
			        ORDER BY ip';
		}
		$req = $link->prepare($sql);
		$req->execute();
		while ($do = $req->fetch(PDO::FETCH_OBJ)) {
			if(empty($do->vps_name)) {
				$do->vps_name = '';
			}
			if(empty($do->vps_id)) {
				$do->vps_id = 0;
			}
			$list[$do->ip] = array(
					'ip'   => $do->ip,
					'id'   => $do->vps_id,
					'name' => $do->vps_name
			);
		}
	
		return $list;
	}
	
	/**
	 * Add a new IP
	 * @param string $ip new IP to add
	 */
	function ipNew($ip) {
		$link = Db::link();
		$ip = trim($ip);
		
		if(!empty($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
			$sql = 'SELECT ip FROM ipv4 WHERE ip= :ip';
			$req = $link->prepare($sql);
			$req->bindValue(':ip', $ip, PDO::PARAM_STR);
			$req->execute();
			$do = $req->fetch(PDO::FETCH_OBJ);
	
			if(empty($do->ip)) {
				$sql = 'INSERT INTO ipv4
				        (ip)
				        VALUES
				        (:ip)';
				$req = $link->prepare($sql);
				$req->bindValue(':ip', $ip, PDO::PARAM_STR);
				$req->execute();
			}
		}
	}
	
	/**
	 * Delete an IP
	 * @param string $ip an IP
	 */
	function ipDelete($ip) {
		$link = Db::link();
		$ip = trim($ip);
		$list = $this->ipList();
		
		if(!empty($ip) && !empty($list[$ip]) && empty($list[$ip]['id'])) {
			$sql = 'DELETE FROM ipv4
			        WHERE ip= :ip';
			$req = $link->prepare($sql);
			$req->bindValue(':ip', $ip, PDO::PARAM_STR);
			$req->execute();
		}
	}
	
	/*** Serveur ***/
	
	/**
	 * Return all physical servers
	 */
	function serverList() {
		$list = array();
	
		$link = Db::link();
		$sql = 'SELECT server.server_id, server_name, server_address,
		               server_description, v.nb, server_key, server_port
		        FROM server
		        LEFT JOIN (SELECT server_id, count(server_id) as nb
		                  FROM vps GROUP BY server_id) v
		        ON v.server_id=server.server_id';
		$res = $link->query($sql);
		while($do = $res->fetch(PDO::FETCH_OBJ)) {
			if(empty($do->nb)) {
				$do->nb = 0;
			}
			$list[$do->server_id] = array(
					'id'          => $do->server_id,
					'name'        => $do->server_name,
					'address'     => $do->server_address,
					'port'        => $do->server_port,
					'description' => $do->server_description,
					'nbvps'       => $do->nb,
					'key'         => $do->server_key
			);
		}
	
		return $list;
	}

	/**
	 * Add a new server on the panel
	 * @param string $name name
	 * @param string $address address (name or IP)
	 * @param number $port communication port
	 * @param string $key key
	 * @param string $description description
	 */
	function serverAdd($name,$address,$port,$key,$description='') {
		$link = Db::link();
	
		$sql = 'INSERT INTO server
		        (server_name, server_address, server_port, server_description, server_key)
		        VALUES
				(:name, :address, :port, :description, :key)';
		$req = $link->prepare($sql);
		$req->bindValue(':name', $name, PDO::PARAM_STR);
		$req->bindValue(':address', $address, PDO::PARAM_STR);
		$req->bindValue(':port', $port, PDO::PARAM_INT);
		$req->bindValue(':description', $description, PDO::PARAM_STR);
		$req->bindValue(':key', $key, PDO::PARAM_STR);
		$req->execute();
		
		if(DB_TYPE == 'PGSQL') {
			$sql = 'SELECT currval(\'server_server_id_seq\') as server_id
			        FROM server';
			$req = $link->prepare($sql);
			$req->execute();
			$do = $req->fetch(PDO::FETCH_OBJ);
			
			$server_id = $do->server_id;
		}
		else {
			$server_id = $link->lastInsertId();
		}
		
		$this->serverReload($server_id);
	}
	
	/**
	 * Remove a server from the panel
	 * @param number $id server id
	 */
	function serverDelete($id) {
		$link = Db::link();
	
		$sql = 'DELETE FROM server
		        WHERE server_id= :server_id';
		$req = $link->prepare($sql);
		$req->bindValue(':server_id', $id, PDO::PARAM_INT);
		$req->execute();
	
		$sql = 'DELETE FROM vps
		        WHERE server_id= :server_id';
		$req = $link->prepare($sql);
		$req->bindValue(':server_id', $id, PDO::PARAM_INT);
		$req->execute();
	}
	
	/**
	 * Update server information
	 * @param number $id server id
	 * @param string $var all information
	 */
	function serverUpdate($id, $var)  {
		$servers = $this->serverList();
	
		if($servers == null) {
			return null;
		}
	
		$server = $servers[$id];
	
		if(empty($server)) {
			return null;
		}
	
		if(!empty($var['name'])) {
			$serverInfo['name'] = $var['name'];
		}
		else {
			$serverInfo['name'] = $server['name'];
		}
	
		if(!empty($var['address'])) {
			$serverInfo['address'] = $var['address'];
		}
		else {
			$serverInfo['address'] = $server['address'];
		}
		
		if(!empty($var['port']) && $var['port'] > 0) {
			$serverInfo['port'] = $var['port'];
		}
		else {
			$serverInfo['port'] = $server['port'];
		}
		
		if(!empty($var['key'])) {
			$serverInfo['key'] = $var['key'];
		}
		else {
			$serverInfo['key'] = $server['key'];
		}
		
		if(!empty($var['description'])) {
			$serverInfo['description'] = $var['description'];
		}
		else {
			$serverInfo['description'] = $server['description'];
		}
		
		$link = Db::link();
		$sql = 'UPDATE server
		        SET server_name= :name,
		            server_address= :address,
		            server_port= :port,
		            server_description= :description,
		            server_key = :key
		        WHERE server_id= :server_id';
		$req = $link->prepare($sql);
		$req->bindValue(':name', $serverInfo['name'], PDO::PARAM_STR);
		$req->bindValue(':address', $serverInfo['address'], PDO::PARAM_STR);
		$req->bindValue(':port', $serverInfo['port'], PDO::PARAM_INT);
		$req->bindValue(':description', $serverInfo['description'], PDO::PARAM_STR);
		$req->bindValue(':key', $serverInfo['key'], PDO::PARAM_STR);
		$req->bindValue(':server_id', $server['id'], PDO::PARAM_INT);
		$req->execute();
	}
	
	/**
	 * Reload server information
	 * @param number $id server id, all servers by default
	 */
	function serverReload($id=0) {
	
		$link = Db::link();
		if($id == 0) {
			$sql = 'SELECT server_id, server_name, server_address,
		               server_description, server_key, server_port
			        FROM server';
			$req = $link->query($sql);
		}
		else {
			$sql = 'SELECT server_id, server_name, server_address,
		               server_description, server_key, server_port
			        FROM server
			        WHERE server_id= :server_id';
			$req = $link->prepare($sql);
			$req->bindValue(':server_id', $id, PDO::PARAM_INT);
			$req->execute();
		}
	
		while($do = $req->fetch(PDO::FETCH_OBJ)) {
			$server = new Server($do->server_id);
			$server -> setAddress($do->server_address);
			$server -> setPort($do->server_port);
			$server -> setKey($do->server_key);
			$server -> vpsReload();
		}
	}

	/**
	 * Restart server
	 * @param number $id server id
	 */
	function serverRestart($id=0) {
	
		$link = Db::link();
		$sql = 'SELECT server_id, server_name, server_address,
		               server_description, server_key, server_port
		        FROM server
		        WHERE server_id= :server_id';
		$req = $link->prepare($sql);
		$req->bindValue(':server_id', $id, PDO::PARAM_INT);
		$req->execute();
	
		$do = $req->fetch(PDO::FETCH_OBJ);
	
		if(!empty($do->server_id)) {
			$server = new Server($do->server_id);
			$server -> setAddress($do->server_address);
			$server -> setPort($do->server_port);
			$server -> setKey($do->server_key);
			$server -> restart();
		}
	}
	
	/**
	 * Return server template
	 * @param number $id server id
	 * @return template list
	 */
	function serverTemplate($id=0) {
		$link = Db::link();
		$sql = 'SELECT server_id, server_name, server_address,
		               server_description, server_key, server_port
		        FROM server
		        WHERE server_id= :server_id';
		$req = $link->prepare($sql);
		$req->bindValue(':server_id', $id, PDO::PARAM_INT);
		$req->execute();
	
		$do = $req->fetch(PDO::FETCH_OBJ);
	
		if(!empty($do->server_id)) {
			
			if(apc_exists(CACHE.'_TPL_'.$do->server_id)) {
				return unserialize(apc_fetch(CACHE.'_TPL_'.$do->server_id));
			}
			
			$server = new Server($do->server_id);
			$server -> setAddress($do->server_address);
			$server -> setPort($do->server_port);
			$server -> setKey($do->server_key);
			$list = array();
	
			$tpl_list = $server -> templateList();
	
			if(empty($tpl_list)) {
				return null;
			}
				
			//Get all templates names without extension
			foreach ($tpl_list as $template) {
				if(substr($template, -7) == '.tar.gz') {
					$list[] = substr($template, 0, -7);
				}
				elseif(substr($template, -8) == '.tar.bz2') {
					$list[] = substr($template, 0, -8);
				}
				elseif(substr($template, -7) == '.tar.xz') {
					$list[] = substr($template, 0, -7);
				}
				elseif(substr($template, -4) == '.tar') {
					$list[] = substr($template, 0, -4);
				}
				elseif(substr($template, -3) == '.xz') {
					$list[] = substr($template, 0, -3);
				}
				else {
					$list[] = $template;
				}
			}
			
			if(!empty($list) && sizeof($list) > 0) {
				apc_store(CACHE.'_TPL_'.$do->server_id, serialize($list));
			}
			
			return $list;
		}
	
		return null;
	}
	
	/**
	 * Rename an OS template
	 * @param number $id server's id
	 * @param string $old old name
	 * @param string $new new name
	 */
	function serverTemplateRename($id, $old, $new) {
		$link = Db::link();
		$sql = 'SELECT server_id, server_name, server_address,
		               server_description, server_key, server_port
		        FROM server
		        WHERE server_id= :server_id';
		$req = $link->prepare($sql);
		$req->bindValue(':server_id', $id, PDO::PARAM_INT);
		$req->execute();
		
		$do = $req->fetch(PDO::FETCH_OBJ);
		
		if(!empty($do->server_id) && checkValideName($old) && checkValideName($new)) {
			if(apc_exists(CACHE.'_TPL_'.$do->server_id)) {
				apc_delete(CACHE.'_TPL_'.$do->server_id);
			}
			$server = new Server($do->server_id);
			$server -> setAddress($do->server_address);
			$server -> setPort($do->server_port);
			$server -> setKey($do->server_key);
			$server -> templateRename($old, $new);
		}
	}
	
	/**
	 * Download/Upload a template
	 * @param number $id server id
	 * @param string $name template name
	 */
	function serverTemplateAdd($id, $name) {
		$link = Db::link();
		$sql = 'SELECT server_id, server_name, server_address,
		               server_description, server_key, server_port
		        FROM server
		        WHERE server_id= :server_id';
		$req = $link->prepare($sql);
		$req->bindValue(':server_id', $id, PDO::PARAM_INT);
		$req->execute();
		
		$do = $req->fetch(PDO::FETCH_OBJ);
		if(!empty($do->server_id)) {
			if(apc_exists(CACHE.'_TPL_'.$do->server_id)) {
				apc_delete(CACHE.'_TPL_'.$do->server_id);
			}
			$ch = curl_init('http://download.openvz.org/template/precreated/'.$name);
			curl_setopt($ch, CURLOPT_NOBODY, true);
			curl_exec($ch);
			$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
				
			if($code == 200){
				$server = new Server($do->server_id);
				$server -> setAddress($do->server_address);
				$server -> setPort($do->server_port);
				$server -> setKey($do->server_key);
				$server -> templateAdd($name);
			}
		}
	}
	
	/**
	 * Delete a template
	 * @param number $id server id
	 * @param string $name template name
	 */
	function serverTemplateDelete($id, $name) {
		$link = Db::link();
		$sql = 'SELECT server_id, server_name, server_address,
		               server_description, server_key, server_port
		        FROM server
		        WHERE server_id= :server_id';
		$req = $link->prepare($sql);
		$req->bindValue(':server_id', $id, PDO::PARAM_INT);
		$req->execute();
		
		$do = $req->fetch(PDO::FETCH_OBJ);
		
		if(!empty($do->server_id) && checkValideName($name)) {
			if(apc_exists(CACHE.'_TPL_'.$do->server_id)) {
				apc_delete(CACHE.'_TPL_'.$do->server_id);
			}
			$server = new Server($do->server_id);
			$server -> setAddress($do->server_address);
			$server -> setPort($do->server_port);
			$server -> setKey($do->server_key);
			$server -> templateDelete($name);
		}
	}
	
	/**
	 * Refresh the template list
	 * @param number $server server id
	 */
	function serverTemplateRefresh($server) {
		$link = Db::link();
		$sql = 'SELECT server_id
		        FROM server
		        WHERE server_id= :server_id';
		$req = $link->prepare($sql);
		$req->bindValue(':server_id', $server, PDO::PARAM_INT);
		$req->execute();
		
		$do = $req->fetch(PDO::FETCH_OBJ);
		
		if(!empty($do->server_id) && apc_exists(CACHE.'_TPL_'.$do->server_id)) {
			apc_delete(CACHE.'_TPL_'.$do->server_id);
		}
	}
	
	/**
	 * List VPS backups
	 * @param number $server server id
	 */
	function serverBackup($server) {
		$link = Db::link();
		$sql = 'SELECT server_id, server_name, server_address,
		               server_description, server_key, server_port
		        FROM server
		        WHERE server_id= :server_id';
		$req = $link->prepare($sql);
		$req->bindValue(':server_id', $server, PDO::PARAM_INT);
		$req->execute();
		
		$do = $req->fetch(PDO::FETCH_OBJ);
		
		if(!empty($do->server_id)) {
			$connect = new Socket($do->server_address, $do->server_port, $do->server_key);
			$connect -> write('backupList');
			$data = json_decode($connect -> read());
			$list = array();
			foreach ($data as $backup) {
				$tab = explode('.', $backup);
				$list[] = array(
					'date' => $tab[2],
					'vps'  => $tab[1]
				);
			}
			return $list;
		}
	}
	
	/**
	 * Delete a backup
	 * @param number $server server id
	 * @param number $idVps vps id
	 * @param string $name backup name
	 */
	function serverBackupDelete($server, $idVps, $name) {
		$link = Db::link();
	
		$sql = 'SELECT server.server_id, server_address,
		               server_key, server_port
		        FROM server
		        WHERE server.server_id= :id';
		$req = $link->prepare($sql);
		$req->bindValue(':id', $server, PDO::PARAM_INT);
		$req->execute();
		$do = $req->fetch(PDO::FETCH_OBJ);
	
		if(!empty($do->server_id)) {
			$connect = new Socket($do->server_address, $do->server_port, $do->server_key);
			$connect -> write('backupDelete', $idVps, $name);
			return $data = json_decode($connect -> read());
		}
	}
	
	/**
	 * Scan a server with clamav
	 * @param number $server the server's id
	 */
	function serverScan($server) {
		$link = Db::link();
		
		$sql = 'SELECT server.server_id, server_address,
		               server_key, server_port
		        FROM server
		        WHERE server.server_id= :id';
		$req = $link->prepare($sql);
		$req->bindValue(':id', $server, PDO::PARAM_INT);
		$req->execute();
		$do = $req->fetch(PDO::FETCH_OBJ);
		
		if(empty($do->server_id)) {
			return '';
		}
		else {
			$server = new Server($do->server_id);
			return $server->scanResult();
		}
	}
	
	/*** VPS ***/
	
	/**
	 * Return Vps list
	 * @param number $server server id, if egal to 0, return all Vps of all Servers
	 */
	function vpsList($server=0) {
	
		$list = array();
		$link = Db::link();
		
		if($server == 0) {
			$sql = 'SELECT vps_id, vps_name, vps_ipv4, vps_description,
			               user_id, vps.server_id, last_maj, ram,
			               ram_current, ostemplate, diskspace, nproc,
			               vps.server_id, user_name, vps_cpus as cpus,
			               diskspace_current, loadavg,
			               swap, onboot, diskinodes, vps_cpulimit,
			               vps_cpuunits, backup_limit, server_name,
			               vps_protected
			        FROM vps
			        LEFT JOIN uservca ON vps_owner=user_id
			        JOIN server ON server.server_id=vps.server_id';
			$req = $link->prepare($sql);
			$req->execute();
		}
		else {
			$sql = 'SELECT vps_id, vps_name, vps_ipv4, vps_description,
			               user_id, vps.server_id, last_maj, ram,
			               ram_current, ostemplate, diskspace, nproc,
			               vps.server_id, user_name, vps_cpus as cpus,
			               diskspace_current, loadavg,
			               swap, onboot, diskinodes, vps_cpulimit,
			               vps_cpuunits, backup_limit, server_name,
			               vps_protected
			        FROM vps
			        LEFT JOIN uservca ON vps_owner=user_id
			        JOIN server ON server.server_id=vps.server_id
			        WHERE vps.server_id= :server_id';
			$req = $link->prepare($sql);
			$req->bindValue(':server_id', $server, PDO::PARAM_INT);
			$req->execute();
		}
		while ($do = $req->fetch(PDO::FETCH_OBJ)) {
			$list[$do->vps_id] = array(
				'id'          => $do->vps_id,
				'name'        => $do->vps_name,
				'ipv4'        => $do->vps_ipv4,
				'description' => $do->vps_description,
				'ostemplate'  => $do->ostemplate,
				'ram'         => $do->ram,
				'ramCurrent'  => $do->ram_current,
				'disk'        => $do->diskspace,
				'nproc'       => $do->nproc,
				'serverId'    => $do->server_id,
				'serverName'  => $do->server_name,
				'ownerId'     => $do->user_id,
				'ownerName'   => $do->user_name,
				'loadavg'     => $do->loadavg,
				'cpus'        => $do->cpus,
				'diskspace'   => $do->diskspace,
				'diskspaceCurrent' => $do->diskspace_current,
				'swap'        => $do->swap,
				'onboot'      => $do->onboot,
				'protected'   => $do->vps_protected,
				'diskinodes'  => $do->diskinodes,
				'cpulimit'    => $do->vps_cpulimit,
				'cpuunits'    => $do->vps_cpuunits,
				'backup_limit'=> $do->backup_limit
			);
		}
	
		return $list;
	}
	
	/**
	 * Set VPS configuration
	 * @param number $id server's id
	 * @param array $var all configuration
	 */
	function vpsAdd($id, $var) {
		$link = Db::link();
		$sql = 'SELECT server_id, server_name, server_address,
		               server_description, server_key, server_port
		        FROM server
		        WHERE server_id= :server_id';
		$req = $link->prepare($sql);
		$req->bindValue(':server_id', $id, PDO::PARAM_INT);
		$req->execute();
		$do = $req->fetch(PDO::FETCH_OBJ);
		
		if(empty($do->server_id)) {
			return null;
		}
	
		$server = new Server($do->server_id);
		$server -> setAddress($do->server_address);
		$server -> setPort($do->server_port);
		$server -> setKey($do->server_key);
	
		//Trim
		$var = array_map('trim', $var);
	
		$para = array();
	
		if(!empty($var['name']) && checkHostname($var['name'])) {
			$para['name'] = $var['name'];
		}
	
		if(!empty($var['onboot'])) {
			$para['onboot'] = 1;
		}
		else {
			$para['onboot'] = 0;
		}
	
		if(!empty($var['ipv4']) && filter_var($var['ipv4'], FILTER_VALIDATE_IP)) {
			$para['ipv4'] = $var['ipv4'];
		}
	
		if (!empty($var['ram'])) {
			$ram = strtolower(str_replace(' ', '', $var['ram']));
			//Go and Mo compatibility
			$ram = str_replace('mo', 'mb', $ram);
			$ram = str_replace('go', 'gb', $ram);
	
			//Unlimited
			if($ram == 0 or !preg_match('`[0-9]`', $ram)) {
				$ram = 0;
			}
			//GB
			elseif(substr($ram, -2) == 'gb') {
				$ram = substr($ram, 0, -2)*1024;
			}
			//MB
			elseif(substr($ram, -2) == 'mb') {
				$ram = substr($ram, 0, -2);
			}
			//MB
			elseif(substr($ram, -1) == 'm') {
				$ram = substr($ram, 0, -1);
			}
	
			if(is_numeric($ram) && $ram >= 0) {
				$para['ram'] = $ram;
			}
		}
	
		if (!empty($var['swap'])) {
			$swap = strtolower(str_replace(' ', '', $var['swap']));
			//Go and Mo compatibility
			$swap = str_replace('mo', 'mb', $swap);
			$swap = str_replace('go', 'gb', $swap);
	
			//Unlimited
			if($swap == 0 or !preg_match('`[0-9]`', $swap)) {
				$swap = 0;
			}
			//GB
			elseif(substr($swap, -2) == 'gb') {
				$swap = substr($swap, 0, -2)*1024;
			}
			//MB
			elseif(substr($swap, -2) == 'mb') {
				$swap = substr($swap, 0, -2);
			}
			//MB
			elseif(substr($swap, -1) == 'm') {
				$swap = substr($swap, 0, -1);
			}
	
			if(is_numeric($swap) && $swap >= 0) {
				$para['swap'] = $swap;
			}
		}
	
		if (!empty($var['diskspace'])) {
			$diskspace = strtolower(str_replace(' ', '', $var['diskspace']));
			//Go and Mo compatibility
			$diskspace = str_replace('mo', 'mb', $diskspace);
			$diskspace = str_replace('go', 'gb', $diskspace);
	
			//Unlimited
			if($diskspace == 0 or !preg_match('`[0-9]`', $diskspace)) {
				$diskspace = 0;
			}
			//GB
			elseif(substr($diskspace, -2) == 'gb') {
				$diskspace = substr($diskspace, 0, -2)*1024;
			}
			//MB
			elseif(substr($diskspace, -2) == 'mb') {
				$diskspace = substr($diskspace, 0, -2);
			}
			//MB
			elseif(substr($diskspace, -1) == 'm') {
				$diskspace = substr($diskspace, 0, -1);
			}
	
			if(is_numeric($diskspace) && $diskspace >= 0) {
				$para['diskspace'] = $diskspace;
			}
		}
	
		if(!empty($var['diskinodes']) && $var['diskinodes'] > 0) {
			$para['diskinodes'] = $var['diskinodes'];
		}
	
		if(!empty($var['cpus']) && $var['cpus'] > 0) {
			$para['cpus'] = $var['cpus'];
		}
	
		if(!empty($var['cpulimit']) && $var['cpulimit'] > 0) {
			$para['cpulimit'] = $var['cpulimit'];
		}
	
		if(!empty($var['cpuunits']) && $var['cpuunits'] > 0) {
			$para['cpuunits'] = $var['cpuunits'];
		}
	
		if(!empty($var['os'])) {
			$para['os'] = $var['os'];
		}
	
		$vpsId=self::vpsNextId();
	
		if($vpsId > 0) {
			$server -> setVpsAdd($vpsId, $para);
		}
	}
	
	/**
	 * Set VPS configuration
	 * @param number $id Vps's id
	 * @param array $var all the parameters
	 */
	function vpsUpdate($id, $var) {
		$link = Db::link();
	
		//Trim
		$var = array_map('trim', $var);
	
		$para = array();
		$vpsList = $this->vpsList();
		$vps = $vpsList[$id];
	
		if(!empty($var['name']) && $var['name'] != $vps['name'] && checkHostname($var['name'])) {
			$para['name'] = $var['name'];
		}
	
		if(isset($var['onboot'])) {
			if(!empty($var['onboot'])) {
				$para['onboot'] = 1;
			}
			else {
				$para['onboot'] = 0;
			}
		}
		
		if(empty($var['protected']) or $var['protected'] != 1) {
			$var['protected'] = 0;
		}
		
		if(!empty($var['ipv4']) && $var['ipv4'] != $vps['ipv4'] &&
		filter_var($var['ipv4'], FILTER_VALIDATE_IP)) {
			$para['ipv4'] = $var['ipv4'];
		}
	
		if (isset($var['ram'])) {
			$ram = strtolower(str_replace(' ', '', $var['ram']));
			//Go and Mo compatibility
			$ram = str_replace('mo', 'mb', $ram);
			$ram = str_replace('go', 'gb', $ram);
	
			//Unlimited
			if($ram == 0 or !preg_match('`[0-9]`', $ram)) {
				$ram = 0;
			}
			//GB
			elseif(substr($ram, -2) == 'gb') {
				$ram = substr($ram, 0, -2)*1024;
			}
			//MB
			elseif(substr($ram, -2) == 'mb') {
				$ram = substr($ram, 0, -2);
			}
			//MB
			elseif(substr($ram, -1) == 'm') {
				$ram = substr($ram, 0, -1);
			}
	
			if(is_numeric($ram) && $ram >= 0 && $ram != $vps['ram']) {
				$para['ram'] = $ram;
			}
		}
	
		if (isset($var['swap'])) {
			$swap = strtolower(str_replace(' ', '', $var['swap']));
			//Go and Mo compatibility
			$swap = str_replace('mo', 'mb', $swap);
			$swap = str_replace('go', 'gb', $swap);
	
			//Unlimited
			if($swap == 0 or !preg_match('`[0-9]`', $swap)) {
				$swap = 0;
			}
			//GB
			elseif(substr($swap, -2) == 'gb') {
				$swap = substr($swap, 0, -2)*1024;
			}
			//MB
			elseif(substr($swap, -2) == 'mb') {
				$swap = substr($swap, 0, -2);
			}
			//MB
			elseif(substr($swap, -1) == 'm') {
				$swap = substr($swap, 0, -1);
			}
	
			if(is_numeric($swap) && $swap >= 0 && $swap != $vps['swap']) {
				$para['swap'] = $swap;
			}
		}
	
		if (!empty($var['diskspace'])) {
			$diskspace = strtolower(str_replace(' ', '', $var['diskspace']));
			//Go and Mo compatibility
			$diskspace = str_replace('mo', 'mb', $diskspace);
			$diskspace = str_replace('go', 'gb', $diskspace);
	
			//Unlimited
			if($diskspace == 0 or !preg_match('`[0-9]`', $diskspace)) {
				$diskspace = 0;
			}
			//GB
			elseif(substr($diskspace, -2) == 'gb') {
				$diskspace = substr($diskspace, 0, -2)*1024;
			}
			//MB
			elseif(substr($diskspace, -2) == 'mb') {
				$diskspace = substr($diskspace, 0, -2);
			}
			//MB
			elseif(substr($diskspace, -1) == 'm') {
				$diskspace = substr($diskspace, 0, -1);
			}
	
			if(is_numeric($diskspace) && $diskspace >= 0 && $diskspace != $vps['diskspace']) {
				$para['diskspace'] = $diskspace;
			}
		}
	
		if(!empty($var['diskinodes']) && is_numeric($var['diskinodes']) &&
		$var['diskinodes'] != $vps['diskinodes'] && $var['diskinodes'] > 0) {
			$para['diskinodes'] = $var['diskinodes'];
		}
	
		if(!empty($var['cpus']) && is_numeric($var['cpus']) &&
		$var['cpus'] != $vps['cpus'] && $var['cpus'] > 0) {
			$para['cpus'] = $var['cpus'];
		}
	
		if(!empty($var['cpulimit']) && is_numeric($var['cpulimit']) &&
		$var['cpulimit'] != $vps['cpulimit'] && $var['cpulimit'] > 0) {
			$para['cpulimit'] = $var['cpulimit'];
		}
	
		if(!empty($var['cpuunits']) && is_numeric($var['cpuunits']) &&
		$var['cpuunits'] != $vps['cpuunits'] && $var['cpuunits'] > 0) {
			$para['cpuunits'] = $var['cpuunits'];
		}
		
		if(empty($var['backup_limit']) or !($var['backup_limit'] > 0)) {
			$var['backup_limit'] = 0;
		}
		
		if(!empty($vps['serverId'])) {
			$sql = 'SELECT server.server_id, server_address, vps_id,
			               server_key, server_port
			        FROM vps
					JOIN server ON vps.server_id=server.server_id
			        WHERE vps_id=:vps_id';
			$req = $link->prepare($sql);
			$req->bindValue(':vps_id', $vps['id'], PDO::PARAM_INT);
			$req->execute();
			$do = $req->fetch(PDO::FETCH_OBJ);
			
			$server = new Server($do-> server_id);
			$server -> setAddress($do-> server_address);
			$server -> setPort($do-> server_port);
			$server -> setKey($do->server_key);
			$server -> vpsUpdate($do-> vps_id, $para);
	
			if(!empty($var['owner']) && $var['owner'] > 0) {
				$list = $this->userList();
	
				if(!empty($list[$var['owner']])) {
					$sql = 'UPDATE vps
					        SET vps_owner=:owner,
					            backup_limit=:backup_limit,
					            vps_protected=:protected
					        WHERE vps_id= :vps';
					$req = $link->prepare($sql);
					$req->bindValue(':owner', $var['owner'], PDO::PARAM_INT);
					$req->bindValue(':vps', $vps['id'], PDO::PARAM_INT);
					$req->bindValue(':backup_limit', $var['backup_limit'], PDO::PARAM_INT);
					$req->bindValue(':protected', $var['protected'], PDO::PARAM_INT);
					$req->execute();
				}
			}
			else {
				$sql = 'UPDATE vps
				        SET backup_limit=:backup_limit,
				            vps_protected=:protected
				        WHERE vps_id= :vps';
				$req = $link->prepare($sql);
				$req->bindValue(':vps', $vps['id'], PDO::PARAM_INT);
				$req->bindValue(':backup_limit', $var['backup_limit'], PDO::PARAM_INT);
				$req->bindValue(':protected', $var['protected'], PDO::PARAM_INT);
				$req->execute();
			}
		}
	}
	
	/**
	 * Delete a Vps
	 * @param number $id vps id
	 */
	function vpsDelete($id) {
		$link = Db::link();
	
		$sql = 'SELECT vps_id, server.server_id, server_address,
		               server_key, server_port, vps_protected
		        FROM vps
		        JOIN server ON server.server_id=vps.server_id
		        WHERE vps_id= :id';
		$req = $link->prepare($sql);
		$req->bindValue(':id', $id, PDO::PARAM_INT);
		$req->execute();
		$do = $req->fetch(PDO::FETCH_OBJ);
	
		if(!empty($do->server_id) && empty($do->vps_protected)) {
			$server = new Server($do->server_id);
			$server -> setAddress($do->server_address);
			$server -> setPort($do->server_port);
			$server -> setKey($do->server_key);
			$server -> delete($do->vps_id);
		}
	}
	
	/**
	 * Start a Vps
	 * @param number $id vps id
	 */
	function vpsStart($id) {
		$link = Db::link();
	
		$sql = 'SELECT vps_id, server.server_id, server_address,
		               server_key, vps_owner, server_port
		        FROM vps
		        JOIN server ON server.server_id=vps.server_id
		        WHERE vps_id= :id';
		$req = $link->prepare($sql);
		$req->bindValue(':id', $id, PDO::PARAM_INT);
		$req->execute();
		$do = $req->fetch(PDO::FETCH_OBJ);
	
		if(!empty($do->server_id)) {
			$server = new Server($do->server_id);
			$server -> setAddress($do->server_address);
			$server -> setPort($do->server_port);
			$server -> setKey($do->server_key);
			$server -> start($do->vps_id);
		}
	}
	
	/**
	 * Stop a Vps
	 * @param number $id vps id
	 */
	function vpsStop($id) {
		$link = Db::link();
	
		$sql = 'SELECT vps_id, server.server_id, server_address,
		               server_key, server_port
		        FROM vps
		        JOIN server ON server.server_id=vps.server_id
		        WHERE vps_id= :id';
		$req = $link->prepare($sql);
		$req->bindValue(':id', $id, PDO::PARAM_INT);
		$req->execute();
		$do = $req->fetch(PDO::FETCH_OBJ);
	
		if(!empty($do->server_id)) {
			$server = new Server($do->server_id);
			$server -> setAddress($do->server_address);
			$server -> setPort($do->server_port);
			$server -> setKey($do->server_key);
			$server -> stop($do->vps_id);
		}
	}
	
	/**
	 * Restart a Vps
	 * @param number $id vps id
	 */
	function vpsRestart($id) {
		$link = Db::link();
	
		$sql = 'SELECT vps_id, server.server_id, server_address,
		               server_key, server_port
		        FROM vps
		        JOIN server ON server.server_id=vps.server_id
		        WHERE vps_id= :id';
		$req = $link->prepare($sql);
		$req->bindValue(':id', $id, PDO::PARAM_INT);
		$req->execute();
		$do = $req->fetch(PDO::FETCH_OBJ);
	
		if(!empty($do->server_id)) {
			$server = new Server($do->server_id);
			$server -> setAddress($do->server_address);
			$server -> setPort($do->server_port);
			$server -> setKey($do->server_key);
			$server -> restart($do->vps_id);
		}
	}
	
	/**
	 * Clone a VPS
	 * @param number $idVps Vps's id
	 * @param string $ip Ip of the new Vps
	 * @param string $hostname Hostname of the new Vps
	 */
	function vpsClone($idVps, $ip, $hostname) {
		$link = Db::link();
	
		$sql = 'SELECT vps_id, server.server_id, server_address,
		               server_key, server_port
		        FROM vps
		        JOIN server ON server.server_id=vps.server_id
		        WHERE vps_id= :id';
		$req = $link->prepare($sql);
		$req->bindValue(':id', $idVps, PDO::PARAM_INT);
		$req->execute();
		$do = $req->fetch(PDO::FETCH_OBJ);
	
		if(!empty($do->server_id) && in_array($ip, $this->ipFree())) {
			$vpsNewsId=self::vpsNextId();
	
			$para = array(
					'dest'     => $vpsNewsId,
					'ip'       => $ip,
					'hostname' => $hostname
			);
	
			$connect = new Socket($do->server_address, $do->server_port, $do->server_key);
			$connect -> write('clone', $do->vps_id, $para);
			$data = json_decode($connect -> read());
		}
	}
	
	/**
	 * Execute a command on a VPS
	 * @param number $idVps vps id
	 * @param string $command command
	 * @return command answer
	 */
	function vpsCmd($idVps, $command) {
		$link = Db::link();
	
		$sql = 'SELECT vps_id, server.server_id, server_address,
		               server_key, server_port
		        FROM vps
		        JOIN server ON server.server_id=vps.server_id
		        WHERE vps_id= :id';
		$req = $link->prepare($sql);
		$req->bindValue(':id', $idVps, PDO::PARAM_INT);
		$req->execute();
		$do = $req->fetch(PDO::FETCH_OBJ);
	
		if(!empty($do->server_id)) {
			$connect = new Socket($do->server_address, $do->server_port, $do->server_key);
			$connect -> write('cmd', $do->vps_id, $command);
			return $data = json_decode($connect -> read());
		}
		return '';
	}
	
	/**
	 * Modifie Vps root password
	 * @param number $idVps vps id
	 * @param string $password new root password
	 */
	function vpsPassword($idVps, $password) {
		$link = Db::link();
	
		$sql = 'SELECT vps_id, server.server_id, server_address,
		               server_key, server_port
		        FROM vps
		        JOIN server ON server.server_id=vps.server_id
		        WHERE vps_id= :id';
		$req = $link->prepare($sql);
		$req->bindValue(':id', $idVps, PDO::PARAM_INT);
		$req->execute();
		$do = $req->fetch(PDO::FETCH_OBJ);
	
		if(!empty($do->server_id)) {
			$connect = new Socket($do->server_address, $do->server_port, $do->server_key);
			$connect -> write('password', $do->vps_id, $password);
			$data = json_decode($connect -> read());
		}
	}
	
	/**
	 * Reinstall a VPS
	 * @param number $idVps vps id
	 * @param string $os operating system template
	 */
	function vpsReinstall($idVps, $os) {
		$link = Db::link();
	
		$sql = 'SELECT vps_id, server.server_id, server_address,
		               server_key, server_port, vps_protected
		        FROM vps
		        JOIN server ON server.server_id=vps.server_id
		        WHERE vps_id= :id';
		$req = $link->prepare($sql);
		$req->bindValue(':id', $idVps, PDO::PARAM_INT);
		$req->execute();
		$do = $req->fetch(PDO::FETCH_OBJ);
	
		if(!empty($do->server_id) && empty($do->vps_protected)) {
			$templates = $this->serverTemplate($do->server_id);
	
			if(in_array($os, $templates)) {
				$connect = new Socket($do->server_address, $do->server_port, $do->server_key);
				$connect -> write('reinstall', $do->vps_id, $os);
				return $data = json_decode($connect -> read());
			}
		}
	
		return null;
	}
	
	/**
	 * Move a VPS
	 * @param number $serverFrom server id source
	 * @param number $vps vps id
	 * @param number $serverDest server id destination
	 */
	function vpsMove($serverFrom, $vps, $serverDest) {
		$link = Db::link();
		
		$sql = 'SELECT vps_id, server.server_id, server_address,
		               server_key, server_port, vps_protected
		        FROM vps
		        JOIN server ON server.server_id=vps.server_id
		        WHERE vps_id= :id';
		$req = $link->prepare($sql);
		$req->bindValue(':id', $idVps, PDO::PARAM_INT);
		$req->execute();
		$do_from = $req->fetch(PDO::FETCH_OBJ);
		
		$sql = 'SELECT server_id, server_address
		        FROM server
		        WHERE server_id= :server_id';
		$req = $link->prepare($sql);
		$req->bindValue(':server_id', $serverDest, PDO::PARAM_INT);
		$req->execute();
		$do_dest = $req->fetch(PDO::FETCH_OBJ);
		
		if(empty($do_from->server_id) or //Vps does not exist
		   empty($do_dest->server_id) or //Server dest does not exist
		   !empty($do_from->vps_protected) or //Vps protected
		   $serverFrom != $do_from->server_id or //Server !=
		   $serverFrom == $serverDest) { // Same server{
			return null;
		}
		
		$connect = new Socket($do_from->server_address, $do_from->server_port, $do_from->server_key);
		$connect -> write('move', $do_from->vps_id, $do_dest->server_address);
		$data = json_decode($connect -> read());
	}
	
	/**
	 * Scan server with Clamav
	 * @param number $id the server's id, scan all servers if the id is empty
	 */
	function avScan($id=0) {
		$link = Db::link();
		if($id == 0) {
			$sql = 'SELECT server_id, server_name, server_address,
			               server_description, server_key, server_port
			        FROM server';
			$req = $link->query($sql);
		}
		else {
			$sql = 'SELECT server_id, server_name, server_address,
			               server_description, server_key, server_port
			        FROM server
			        WHERE server_id= :server_id';
			$req = $link->prepare($sql);
			$req->bindValue(':server_id', $id, PDO::PARAM_INT);
			$req->execute();
		}
	
		while($do = $req->fetch(PDO::FETCH_OBJ)) {
			$server = new Server($do->server_id);
			$server -> setAddress($do->server_address);
			$server -> setPort($do->server_port);
			$server -> setKey($do->server_key);
			$server -> avScan();
		}
	}
	
	/**
	 * Return if Dropbox is configured
	 */
	static function dropboxPossible() {
		if(APP_KEY == '' or APP_SECRET == '') {
			return 0;
		}
		else {
			return 1;
		}
	}
}

?>
