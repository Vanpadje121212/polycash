<?php
if (isset($_COOKIE['my_session_global'])) {
	$session_key = $_COOKIE['my_session_global'];
}
else {
	$session_key = $app->random_string(24);
	setcookie('my_session_global', $session_key, time()+24*3600, "/");
}

$thisuser = FALSE;
$game = FALSE;

if (strlen($session_key) > 0) {
	$sessions = $app->run_query("SELECT * FROM user_sessions WHERE session_key=".$app->quote_escape($session_key)." AND expire_time > '".time()."' AND logout_time=0;");
	
	if ($sessions->rowCount() == 1) {
		$session = $sessions->fetch();
		
		$thisuser = new User($app, $session['user_id']);
	}
	else {
		while ($session = $sessions->fetch()) {
			$app->run_query("UPDATE user_sessions SET logout_time='".time()."' WHERE session_id='".$session['session_id']."';");
		}
		$session = false;
	}
	
	$card_sessions_q = "SELECT * FROM cards c JOIN card_users u ON c.card_id=u.card_id JOIN card_sessions s ON s.card_user_id=u.card_user_id WHERE s.session_key=".$app->quote_escape($session_key);
	if (AppSettings::getParam('pageview_tracking_enabled')) $card_sessions_q .= " AND s.ip_address=".$app->quote_escape($_SERVER['REMOTE_ADDR']);
	$card_sessions_q .= " AND ".time()." < s.expire_time AND s.logout_time IS NULL GROUP BY c.card_id;";
	$card_sessions = $app->run_query($card_sessions_q);
	
	if ($card_sessions->rowCount() > 0) {
		$j=0;
		while($card_session = $card_sessions->fetch()) {
			if ($j == 0) $this_card_session = $card_session;
			
			// Make sure the user has a maximum of 1 active gift card session
			if ($j > 0) {
				$app->run_query("UPDATE card_sessions SET logout_time='".(time()-1)."' WHERE session_id='".$card_session['session_id']."';");
			}
			
			if (empty($thisuser) && !empty($card_session['user_id'])) {
				$thisuser = new User($app, $card_session['user_id']);
			}
			
			$j++;
		}
	}
}

if ($thisuser && !empty($_REQUEST['redirect_key'])) {
	$redirect_url = $app->get_redirect_by_key($_REQUEST['redirect_key']);
	
	if ($redirect_url) {
		header("Location: ".$redirect_url['url']);
		die();
	}
}

if ($thisuser && !empty($_REQUEST['game_id'])) {
	$game_id = intval($_REQUEST['game_id']);
	
	$db_game = $app->run_query("SELECT g.* FROM games g JOIN user_games ug ON g.game_id=ug.game_id WHERE ug.user_id='".$thisuser->db_user['user_id']."' AND g.game_id='".$game_id."';")->fetch();
	
	if ($db_game) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
	}
}

if (AppSettings::getParam('pageview_tracking_enabled')) $viewer_id = $pageview_controller->insert_pageview($thisuser);
else $viewer_id = false;
?>