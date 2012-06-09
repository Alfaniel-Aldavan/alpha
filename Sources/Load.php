<?php

/**
 * This file has the hefty job of loading information for the forum.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

if (!defined('SMF'))
	die('Hacking attempt...');
	
/**
 * Load the $modSettings array.
 *
 * @todo okay question of the day: why a function for loading settings is called reloadSettings()
 *
 * @global array $modSettings is a giant array of all of the forum-wide settings and statistics.
 */
function reloadSettings()
{
	global $modSettings, $boarddir, $smcFunc, $txt, $db_character_set, $context, $sourcedir;

	// Most database systems have not set UTF-8 as their default input charset.
	if (!empty($db_character_set))
		$smcFunc['db_query']('set_character_set', '
			SET NAMES ' . $db_character_set,
			array(
			)
		);

	// Try to load it from the cache first; it'll never get cached if the setting is off.
	if (($modSettings = cache_get_data('modSettings', 90)) == null)
	{
		$request = $smcFunc['db_query']('', '
			SELECT variable, value
			FROM {db_prefix}settings',
			array(
			)
		);
		$modSettings = array();
		if (!$request)
			display_db_error();
		while ($row = $smcFunc['db_fetch_row']($request))
			$modSettings[$row[0]] = $row[1];
		$smcFunc['db_free_result']($request);

		// Do a few things to protect against missing settings or settings with invalid values...
		if (empty($modSettings['defaultMaxTopics']) || $modSettings['defaultMaxTopics'] <= 0 || $modSettings['defaultMaxTopics'] > 999)
			$modSettings['defaultMaxTopics'] = 20;
		if (empty($modSettings['defaultMaxMessages']) || $modSettings['defaultMaxMessages'] <= 0 || $modSettings['defaultMaxMessages'] > 999)
			$modSettings['defaultMaxMessages'] = 15;
		if (empty($modSettings['defaultMaxMembers']) || $modSettings['defaultMaxMembers'] <= 0 || $modSettings['defaultMaxMembers'] > 999)
			$modSettings['defaultMaxMembers'] = 30;

		if (!empty($modSettings['cache_enable']))
			cache_put_data('modSettings', $modSettings, 90);
	}

	// UTF-8 in regular expressions is unsupported on PHP(win) versions < 4.2.3.
	$utf8 = (empty($modSettings['global_character_set']) ? $txt['lang_character_set'] : $modSettings['global_character_set']) === 'UTF-8';

	// Set a list of common functions.
	$ent_list = empty($modSettings['disableEntityCheck']) ? '&(#\d{1,7}|quot|amp|lt|gt|nbsp);' : '&(#021|quot|amp|lt|gt|nbsp);';
	$ent_check = empty($modSettings['disableEntityCheck']) ? array('preg_replace(\'~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~e\', \'$smcFunc[\\\'entity_fix\\\'](\\\'\\2\\\')\', ', ')') : array('', '');

	// Preg_replace can handle complex characters only for higher PHP versions.
	$space_chars = $utf8 ? '\x{A0}\x{AD}\x{2000}-\x{200F}\x{201F}\x{202F}\x{3000}\x{FEFF}' : '\x00-\x08\x0B\x0C\x0E-\x19\xA0';

	// global array of anonymous helper functions, used mosly to properly handle multi byte strings
	$smcFunc += array(
		'entity_fix' => create_function('$string', '
			$num = $string[0] === \'x\' ? hexdec(substr($string, 1)) : (int) $string;
			return $num < 0x20 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF) || $num == 0x202E ? \'\' : \'&#\' . $num . \';\';'),
		'htmlspecialchars' => create_function('$string, $quote_style = ENT_COMPAT, $charset = \'ISO-8859-1\'', '
			global $smcFunc;
			return ' . strtr($ent_check[0], array('&' => '&amp;')) . 'htmlspecialchars($string, $quote_style, ' . ($utf8 ? '\'UTF-8\'' : '$charset') . ')' . $ent_check[1] . ';'),
		'htmltrim' => create_function('$string', '
			global $smcFunc;
			return preg_replace(\'~^(?:[ \t\n\r\x0B\x00' . $space_chars . ']|&nbsp;)+|(?:[ \t\n\r\x0B\x00' . $space_chars . ']|&nbsp;)+$~' . ($utf8 ? 'u' : '') . '\', \'\', ' . implode('$string', $ent_check) . ');'),
		'strlen' => create_function('$string', '
			global $smcFunc;
			return strlen(preg_replace(\'~' . $ent_list . ($utf8 ? '|.~u' : '~') . '\', \'_\', ' . implode('$string', $ent_check) . '));'),
		'strpos' => create_function('$haystack, $needle, $offset = 0', '
			global $smcFunc;
			$haystack_arr = preg_split(\'~(&#' . (empty($modSettings['disableEntityCheck']) ? '\d{1,7}' : '021') . ';|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~' . ($utf8 ? 'u' : '') . '\', ' . implode('$haystack', $ent_check) . ', -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
			$haystack_size = count($haystack_arr);
			if (strlen($needle) === 1)
			{
				$result = array_search($needle, array_slice($haystack_arr, $offset));
				return is_int($result) ? $result + $offset : false;
			}
			else
			{
				$needle_arr = preg_split(\'~(&#' . (empty($modSettings['disableEntityCheck']) ? '\d{1,7}' : '021') . ';|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~' . ($utf8 ? 'u' : '') . '\',  ' . implode('$needle', $ent_check) . ', -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
				$needle_size = count($needle_arr);

				$result = array_search($needle_arr[0], array_slice($haystack_arr, $offset));
				while ((int) $result === $result)
				{
					$offset += $result;
					if (array_slice($haystack_arr, $offset, $needle_size) === $needle_arr)
						return $offset;
					$result = array_search($needle_arr[0], array_slice($haystack_arr, ++$offset));
				}
				return false;
			}'),
		'substr' => create_function('$string, $start, $length = null', '
			global $smcFunc;
			$ent_arr = preg_split(\'~(&#' . (empty($modSettings['disableEntityCheck']) ? '\d{1,7}' : '021') . ';|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~' . ($utf8 ? 'u' : '') . '\', ' . implode('$string', $ent_check) . ', -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
			return $length === null ? implode(\'\', array_slice($ent_arr, $start)) : implode(\'\', array_slice($ent_arr, $start, $length));'),
		'strtolower' => $utf8 ? (function_exists('mb_strtolower') ? create_function('$string', '
			return mb_strtolower($string, \'UTF-8\');') : create_function('$string', '
			global $sourcedir;
			require_once($sourcedir . \'/Subs-Charset.php\');
			return utf8_strtolower($string);')) : 'strtolower',
		'strtoupper' => $utf8 ? (function_exists('mb_strtoupper') ? create_function('$string', '
			return mb_strtoupper($string, \'UTF-8\');') : create_function('$string', '
			global $sourcedir;
			require_once($sourcedir . \'/Subs-Charset.php\');
			return utf8_strtoupper($string);')) : 'strtoupper',
		'truncate' => create_function('$string, $length', (empty($modSettings['disableEntityCheck']) ? '
			global $smcFunc;
			$string = ' . implode('$string', $ent_check) . ';' : '') . '
			preg_match(\'~^(' . $ent_list . '|.){\' . $smcFunc[\'strlen\'](substr($string, 0, $length)) . \'}~'.  ($utf8 ? 'u' : '') . '\', $string, $matches);
			$string = $matches[0];
			while (strlen($string) > $length)
				$string = preg_replace(\'~(?:' . $ent_list . '|.)$~'.  ($utf8 ? 'u' : '') . '\', \'\', $string);
			return $string;'),
		'ucfirst' => $utf8 ? create_function('$string', '
			global $smcFunc;
			return $smcFunc[\'strtoupper\']($smcFunc[\'substr\']($string, 0, 1)) . $smcFunc[\'substr\']($string, 1);') : 'ucfirst',
		'ucwords' => $utf8 ? create_function('$string', '
			global $smcFunc;
			$words = preg_split(\'~([\s\r\n\t]+)~\', $string, -1, PREG_SPLIT_DELIM_CAPTURE);
			for ($i = 0, $n = count($words); $i < $n; $i += 2)
				$words[$i] = $smcFunc[\'ucfirst\']($words[$i]);
			return implode(\'\', $words);') : 'ucwords',
	);

	// Setting the timezone is a requirement for some functions in PHP >= 5.1.
	if (isset($modSettings['default_timezone']) && function_exists('date_default_timezone_set'))
		date_default_timezone_set($modSettings['default_timezone']);

	// Check the load averages?
	if (!empty($modSettings['loadavg_enable']))
	{
		if (($modSettings['load_average'] = cache_get_data('loadavg', 90)) == null)
		{
			$modSettings['load_average'] = @file_get_contents('/proc/loadavg');
			if (!empty($modSettings['load_average']) && preg_match('~^([^ ]+?) ([^ ]+?) ([^ ]+)~', $modSettings['load_average'], $matches) != 0)
				$modSettings['load_average'] = (float) $matches[1];
			elseif (($modSettings['load_average'] = @`uptime`) != null && preg_match('~load average[s]?: (\d+\.\d+), (\d+\.\d+), (\d+\.\d+)~i', $modSettings['load_average'], $matches) != 0)
				$modSettings['load_average'] = (float) $matches[1];
			else
				unset($modSettings['load_average']);

			if (!empty($modSettings['load_average']))
				cache_put_data('loadavg', $modSettings['load_average'], 90);
		}

		if (!empty($modSettings['load_average']))
			call_integration_hook('integrate_load_average', array($modSettings['load_average']));

		if (!empty($modSettings['loadavg_forum']) && !empty($modSettings['load_average']) && $modSettings['load_average'] >= $modSettings['loadavg_forum'])
			display_loadavg_error();
	}

	// Is post moderation alive and well?
	$modSettings['postmod_active'] = isset($modSettings['admin_features']) ? in_array('pm', explode(',', $modSettings['admin_features'])) : true;

	// Integration is cool.
	if (defined('SMF_INTEGRATION_SETTINGS'))
	{
		$integration_settings = unserialize(SMF_INTEGRATION_SETTINGS);
		foreach ($integration_settings as $hook => $function)
			add_integration_function($hook, $function, false);
	}

	// Any files to pre include?
	if (!empty($modSettings['integrate_pre_include']))
	{
		$pre_includes = explode(',', $modSettings['integrate_pre_include']);
		foreach ($pre_includes as $include)
		{
			$include = strtr(trim($include), array('$boarddir' => $boarddir, '$sourcedir' => $sourcedir));
			if (file_exists($include))
				require_once($include);
		}
	}

	// Call pre load integration functions.
	call_integration_hook('integrate_pre_load');
}

/**
 * Load all the important user information.
 * What it does:
 * 	- sets up the $user_info array
 * 	- assigns $user_info['query_wanna_see_board'] for what boards the user can see.
 * 	- first checks for cookie or integration validation.
 * 	- uses the current session if no integration function or cookie is found.
 * 	- checks password length, if member is activated and the login span isn't over.
 * 		- if validation fails for the user, $id_member is set to 0.
 * 		- updates the last visit time when needed.
 */
function loadUserSettings()
{
	global $modSettings, $user_settings, $sourcedir, $smcFunc;
	global $cookiename, $user_info, $language, $context;

	// Check first the integration, then the cookie, and last the session.
	if (count($integration_ids = call_integration_hook('integrate_verify_user')) > 0)
	{
		$id_member = 0;
		foreach ($integration_ids as $integration_id)
		{
			$integration_id = (int) $integration_id;
			if ($integration_id > 0)
			{
				$id_member = $integration_id;
				$already_verified = true;
				break;
			}
		}
	}
	else
		$id_member = 0;

	if (empty($id_member) && isset($_COOKIE[$cookiename]))
	{
		// Fix a security hole in PHP 4.3.9 and below...
		if (preg_match('~^a:[34]:\{i:0;(i:\d{1,6}|s:[1-8]:"\d{1,8}");i:1;s:(0|40):"([a-fA-F0-9]{40})?";i:2;[id]:\d{1,14};(i:3;i:\d;)?\}$~i', $_COOKIE[$cookiename]) == 1)
		{
			list ($id_member, $password) = @unserialize($_COOKIE[$cookiename]);
			$id_member = !empty($id_member) && strlen($password) > 0 ? (int) $id_member : 0;
		}
		else
			$id_member = 0;
	}
	elseif (empty($id_member) && isset($_SESSION['login_' . $cookiename]) && ($_SESSION['USER_AGENT'] == $_SERVER['HTTP_USER_AGENT'] || !empty($modSettings['disableCheckUA'])))
	{
		// @todo Perhaps we can do some more checking on this, such as on the first octet of the IP?
		list ($id_member, $password, $login_span) = @unserialize($_SESSION['login_' . $cookiename]);
		$id_member = !empty($id_member) && strlen($password) == 40 && $login_span > time() ? (int) $id_member : 0;
	}

	// Only load this stuff if the user isn't a guest.
	if ($id_member != 0)
	{
		// Is the member data cached?
		if (empty($modSettings['cache_enable']) || $modSettings['cache_enable'] < 2 || ($user_settings = cache_get_data('user_settings-' . $id_member, 60)) == null)
		{
			$request = $smcFunc['db_query']('', '
				SELECT mem.*, IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type
				FROM {db_prefix}members AS mem
					LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = {int:id_member})
				WHERE mem.id_member = {int:id_member}
				LIMIT 1',
				array(
					'id_member' => $id_member,
				)
			);
			$user_settings = $smcFunc['db_fetch_assoc']($request);
			$smcFunc['db_free_result']($request);

			if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
				cache_put_data('user_settings-' . $id_member, $user_settings, 60);
		}

		// Did we find 'im?  If not, junk it.
		if (!empty($user_settings))
		{
			// As much as the password should be right, we can assume the integration set things up.
			if (!empty($already_verified) && $already_verified === true)
				$check = true;
			// SHA-1 passwords should be 40 characters long.
			elseif (strlen($password) == 40)
				$check = sha1($user_settings['passwd'] . $user_settings['password_salt']) == $password;
			else
				$check = false;

			// Wrong password or not activated - either way, you're going nowhere.
			$id_member = $check && ($user_settings['is_activated'] == 1 || $user_settings['is_activated'] == 11) ? $user_settings['id_member'] : 0;
		}
		else
			$id_member = 0;

		// If we no longer have the member maybe they're being all hackey, stop brute force!
		if (!$id_member)
		{
			require_once($sourcedir . '/LogInOut.php');
			validatePasswordFlood(!empty($user_settings['id_member']) ? $user_settings['id_member'] : $id_member, !empty($user_settings['passwd_flood']) ? $user_settings['passwd_flood'] : false, $id_member != 0);
		}
	}

	// Found 'im, let's set up the variables.
	if ($id_member != 0)
	{
		// Let's not update the last visit time in these cases...
		// 1. SSI doesn't count as visiting the forum.
		// 2. RSS feeds and XMLHTTP requests don't count either.
		// 3. If it was set within this session, no need to set it again.
		// 4. New session, yet updated < five hours ago? Maybe cache can help.
		if (SMF != 'SSI' && !isset($_REQUEST['xml']) && (!isset($_REQUEST['action']) || $_REQUEST['action'] != '.xml') && empty($_SESSION['id_msg_last_visit']) && (empty($modSettings['cache_enable']) || ($_SESSION['id_msg_last_visit'] = cache_get_data('user_last_visit-' . $id_member, 5 * 3600)) === null))
		{
			// @todo can this be cached?
			// Do a quick query to make sure this isn't a mistake.
			$result = $smcFunc['db_query']('', '
				SELECT poster_time
				FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}
				LIMIT 1',
				array(
					'id_msg' => $user_settings['id_msg_last_visit'],
				)
			);
			list ($visitTime) = $smcFunc['db_fetch_row']($result);
			$smcFunc['db_free_result']($result);

			$_SESSION['id_msg_last_visit'] = $user_settings['id_msg_last_visit'];

			// If it was *at least* five hours ago...
			if ($visitTime < time() - 5 * 3600)
			{
				updateMemberData($id_member, array('id_msg_last_visit' => (int) $modSettings['maxMsgID'], 'last_login' => time(), 'member_ip' => $_SERVER['REMOTE_ADDR'], 'member_ip2' => $_SERVER['BAN_CHECK_IP']));
				$user_settings['last_login'] = time();

				if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
					cache_put_data('user_settings-' . $id_member, $user_settings, 60);

				if (!empty($modSettings['cache_enable']))
					cache_put_data('user_last_visit-' . $id_member, $_SESSION['id_msg_last_visit'], 5 * 3600);
			}
		}
		elseif (empty($_SESSION['id_msg_last_visit']))
			$_SESSION['id_msg_last_visit'] = $user_settings['id_msg_last_visit'];

		$username = $user_settings['member_name'];

		if (empty($user_settings['additional_groups']))
			$user_info = array(
				'groups' => array($user_settings['id_group'], $user_settings['id_post_group'])
			);
		else
			$user_info = array(
				'groups' => array_merge(
					array($user_settings['id_group'], $user_settings['id_post_group']),
					explode(',', $user_settings['additional_groups'])
				)
			);

		// Because history has proven that it is possible for groups to go bad - clean up in case.
		foreach ($user_info['groups'] as $k => $v)
			$user_info['groups'][$k] = (int) $v;

		// This is a logged in user, so definitely not a spider.
		$user_info['possibly_robot'] = false;
	}
	// If the user is a guest, initialize all the critical user settings.
	else
	{
		// This is what a guest's variables should be.
		$username = '';
		$user_info = array('groups' => array(-1));
		$user_settings = array();

		if (isset($_COOKIE[$cookiename]))
			$_COOKIE[$cookiename] = '';

		// Create a login token if it doesn't exist yet.
		if (!isset($_SESSION['token']['post-login']))
			createToken('login');
		else
			list ($context['login_token_var'],,, $context['login_token']) = $_SESSION['token']['post-login'];

		// Do we perhaps think this is a search robot? Check every five minutes just in case...
		if ((!empty($modSettings['spider_mode']) || !empty($modSettings['spider_group'])) && (!isset($_SESSION['robot_check']) || $_SESSION['robot_check'] < time() - 300))
		{
			require_once($sourcedir . '/ManageSearchEngines.php');
			$user_info['possibly_robot'] = SpiderCheck();
		}
		elseif (!empty($modSettings['spider_mode']))
			$user_info['possibly_robot'] = isset($_SESSION['id_robot']) ? $_SESSION['id_robot'] : 0;
		// If we haven't turned on proper spider hunts then have a guess!
		else
		{
			$ci_user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
			$user_info['possibly_robot'] = (strpos($_SERVER['HTTP_USER_AGENT'], 'Mozilla') === false && strpos($_SERVER['HTTP_USER_AGENT'], 'Opera') === false) || strpos($ci_user_agent, 'googlebot') !== false || strpos($ci_user_agent, 'slurp') !== false || strpos($ci_user_agent, 'crawl') !== false || strpos($ci_user_agent, 'msnbot') !== false;
		}
	}

	// Set up the $user_info array.
	$user_info += array(
		'id' => $id_member,
		'username' => $username,
		'name' => isset($user_settings['real_name']) ? $user_settings['real_name'] : '',
		'email' => isset($user_settings['email_address']) ? $user_settings['email_address'] : '',
		'passwd' => isset($user_settings['passwd']) ? $user_settings['passwd'] : '',
		'language' => empty($user_settings['lngfile']) || empty($modSettings['userLanguage']) ? $language : $user_settings['lngfile'],
		'is_guest' => $id_member == 0,
		'is_admin' => in_array(1, $user_info['groups']),
		'theme' => empty($user_settings['id_theme']) ? 0 : $user_settings['id_theme'],
		'last_login' => empty($user_settings['last_login']) ? 0 : $user_settings['last_login'],
		'ip' => $_SERVER['REMOTE_ADDR'],
		'ip2' => $_SERVER['BAN_CHECK_IP'],
		'posts' => empty($user_settings['posts']) ? 0 : $user_settings['posts'],
		'time_format' => empty($user_settings['time_format']) ? $modSettings['time_format'] : $user_settings['time_format'],
		'time_offset' => empty($user_settings['time_offset']) ? 0 : $user_settings['time_offset'],
		'avatar' => array(
			'url' => isset($user_settings['avatar']) ? $user_settings['avatar'] : '',
			'filename' => empty($user_settings['filename']) ? '' : $user_settings['filename'],
			'custom_dir' => !empty($user_settings['attachment_type']) && $user_settings['attachment_type'] == 1,
			'id_attach' => isset($user_settings['id_attach']) ? $user_settings['id_attach'] : 0
		),
		'smiley_set' => isset($user_settings['smiley_set']) ? $user_settings['smiley_set'] : '',
		'messages' => empty($user_settings['instant_messages']) ? 0 : $user_settings['instant_messages'],
		'unread_messages' => empty($user_settings['unread_messages']) ? 0 : $user_settings['unread_messages'],
		'total_time_logged_in' => empty($user_settings['total_time_logged_in']) ? 0 : $user_settings['total_time_logged_in'],
		'buddies' => !empty($modSettings['enable_buddylist']) && !empty($user_settings['buddy_list']) ? explode(',', $user_settings['buddy_list']) : array(),
		'ignoreboards' => !empty($user_settings['ignore_boards']) && !empty($modSettings['allow_ignore_boards']) ? explode(',', $user_settings['ignore_boards']) : array(),
		'ignoreusers' => !empty($user_settings['pm_ignore_list']) ? explode(',', $user_settings['pm_ignore_list']) : array(),
		'warning' => isset($user_settings['warning']) ? $user_settings['warning'] : 0,
		'permissions' => array(),
	);
	$user_info['groups'] = array_unique($user_info['groups']);
	// Make sure that the last item in the ignore boards array is valid.  If the list was too long it could have an ending comma that could cause problems.
	if (!empty($user_info['ignoreboards']) && empty($user_info['ignoreboards'][$tmp = count($user_info['ignoreboards']) - 1]))
		unset($user_info['ignoreboards'][$tmp]);

	// Do we have any languages to validate this?
	if (!empty($modSettings['userLanguage']) && (!empty($_GET['language']) || !empty($_SESSION['language'])))
		$languages = getLanguages();

	// Allow the user to change their language if its valid.
	if (!empty($modSettings['userLanguage']) && !empty($_GET['language']) && isset($languages[strtr($_GET['language'], './\\:', '____')]))
	{
		$user_info['language'] = strtr($_GET['language'], './\\:', '____');
		$_SESSION['language'] = $user_info['language'];
	}
	elseif (!empty($modSettings['userLanguage']) && !empty($_SESSION['language']) && isset($languages[strtr($_SESSION['language'], './\\:', '____')]))
		$user_info['language'] = strtr($_SESSION['language'], './\\:', '____');

	// Just build this here, it makes it easier to change/use - administrators can see all boards.
	if ($user_info['is_admin'])
		$user_info['query_see_board'] = '1=1';
	// Otherwise just the groups in $user_info['groups'].
	else
		$user_info['query_see_board'] = '((FIND_IN_SET(' . implode(', b.member_groups) != 0 OR FIND_IN_SET(', $user_info['groups']) . ', b.member_groups) != 0)' . (!empty($modSettings['deny_boards_access']) ? ' AND (FIND_IN_SET(' . implode(', b.deny_member_groups) = 0 AND FIND_IN_SET(', $user_info['groups']) . ', b.deny_member_groups) = 0)' : '') . (isset($user_info['mod_cache']) ? ' OR ' . $user_info['mod_cache']['mq'] : '') . ')';
	// Build the list of boards they WANT to see.
	// This will take the place of query_see_boards in certain spots, so it better include the boards they can see also

	// If they aren't ignoring any boards then they want to see all the boards they can see
	if (empty($user_info['ignoreboards']))
		$user_info['query_wanna_see_board'] = $user_info['query_see_board'];
	// Ok I guess they don't want to see all the boards
	else
		$user_info['query_wanna_see_board'] = '(' . $user_info['query_see_board'] . ' AND b.id_board NOT IN (' . implode(',', $user_info['ignoreboards']) . '))';

	call_integration_hook('integrate_user_info');
}

/**
 * Check for moderators and see if they have access to the board.
 * What it does:
 * - sets up the $board_info array for current board information.
 * - if cache is enabled, the $board_info array is stored in cache.
 * - redirects to appropriate post if only message id is requested.
 * - is only used when inside a topic or board.
 * - determines the local moderators for the board.
 * - adds group id 3 if the user is a local moderator for the board they are in.
 * - prevents access if user is not in proper group nor a local moderator of the board.
 */
function loadBoard()
{
	global $txt, $scripturl, $context, $modSettings;
	global $board_info, $board, $topic, $user_info, $smcFunc;

	// Assume they are not a moderator.
	$user_info['is_mod'] = false;
	$context['user']['is_mod'] = &$user_info['is_mod'];

	// Start the linktree off empty..
	$context['linktree'] = array();

	// Have they by chance specified a message id but nothing else?
	if (empty($_REQUEST['action']) && empty($topic) && empty($board) && !empty($_REQUEST['msg']))
	{
		// Make sure the message id is really an int.
		$_REQUEST['msg'] = (int) $_REQUEST['msg'];

		// Looking through the message table can be slow, so try using the cache first.
		if (($topic = cache_get_data('msg_topic-' . $_REQUEST['msg'], 120)) === NULL)
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_topic
				FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}
				LIMIT 1',
				array(
					'id_msg' => $_REQUEST['msg'],
				)
			);

			// So did it find anything?
			if ($smcFunc['db_num_rows']($request))
			{
				list ($topic) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);
				// Save save save.
				cache_put_data('msg_topic-' . $_REQUEST['msg'], $topic, 120);
			}
		}

		// Remember redirection is the key to avoiding fallout from your bosses.
		if (!empty($topic))
			redirectexit('topic=' . $topic . '.msg' . $_REQUEST['msg'] . '#msg' . $_REQUEST['msg']);
		else
		{
			loadPermissions();
			loadTheme();
			fatal_lang_error('topic_gone', false);
		}
	}

	// Load this board only if it is specified.
	if (empty($board) && empty($topic))
	{
		$board_info = array('moderators' => array());
		return;
	}

	if (!empty($modSettings['cache_enable']) && (empty($topic) || $modSettings['cache_enable'] >= 3))
	{
		// @todo SLOW?
		if (!empty($topic))
			$temp = cache_get_data('topic_board-' . $topic, 120);
		else
			$temp = cache_get_data('board-' . $board, 120);

		if (!empty($temp))
		{
			$board_info = $temp;
			$board = $board_info['id'];
		}
	}

	if (empty($temp))
	{
		$request = $smcFunc['db_query']('', '
			SELECT
				c.id_cat, b.name AS bname, b.description, b.num_topics, b.member_groups, b.deny_member_groups,
				b.id_parent, c.name AS cname, IFNULL(mem.id_member, 0) AS id_moderator,
				mem.real_name' . (!empty($topic) ? ', b.id_board' : '') . ', b.child_level,
				b.id_theme, b.override_theme, b.count_posts, b.id_profile, b.redirect,
				b.unapproved_topics, b.unapproved_posts' . (!empty($topic) ? ', t.approved, t.id_member_started' : '') . '
			FROM {db_prefix}boards AS b' . (!empty($topic) ? '
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})' : '') . '
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
				LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = {raw:board_link})
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
			WHERE b.id_board = {raw:board_link}',
			array(
				'current_topic' => $topic,
				'board_link' => empty($topic) ? $smcFunc['db_quote']('{int:current_board}', array('current_board' => $board)) : 't.id_board',
			)
		);
		// If there aren't any, skip.
		if ($smcFunc['db_num_rows']($request) > 0)
		{
			$row = $smcFunc['db_fetch_assoc']($request);

			// Set the current board.
			if (!empty($row['id_board']))
				$board = $row['id_board'];

			// Basic operating information. (globals... :/)
			$board_info = array(
				'id' => $board,
				'moderators' => array(),
				'cat' => array(
					'id' => $row['id_cat'],
					'name' => $row['cname']
				),
				'name' => $row['bname'],
				'description' => $row['description'],
				'num_topics' => $row['num_topics'],
				'unapproved_topics' => $row['unapproved_topics'],
				'unapproved_posts' => $row['unapproved_posts'],
				'unapproved_user_topics' => 0,
				'parent_boards' => getBoardParents($row['id_parent']),
				'parent' => $row['id_parent'],
				'child_level' => $row['child_level'],
				'theme' => $row['id_theme'],
				'override_theme' => !empty($row['override_theme']),
				'profile' => $row['id_profile'],
				'redirect' => $row['redirect'],
				'posts_count' => empty($row['count_posts']),
				'cur_topic_approved' => empty($topic) || $row['approved'],
				'cur_topic_starter' => empty($topic) ? 0 : $row['id_member_started'],
			);

			// Load the membergroups allowed, and check permissions.
			$board_info['groups'] = $row['member_groups'] == '' ? array() : explode(',', $row['member_groups']);
			$board_info['deny_groups'] = $row['deny_member_groups'] == '' ? array() : explode(',', $row['deny_member_groups']);

			do
			{
				if (!empty($row['id_moderator']))
					$board_info['moderators'][$row['id_moderator']] = array(
						'id' => $row['id_moderator'],
						'name' => $row['real_name'],
						'href' => $scripturl . '?action=profile;u=' . $row['id_moderator'],
						'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_moderator'] . '">' . $row['real_name'] . '</a>'
					);
			}
			while ($row = $smcFunc['db_fetch_assoc']($request));

			// If the board only contains unapproved posts and the user isn't an approver then they can't see any topics.
			// If that is the case do an additional check to see if they have any topics waiting to be approved.
			if ($board_info['num_topics'] == 0 && $modSettings['postmod_active'] && !allowedTo('approve_posts'))
			{
				// Free the previous result
				$smcFunc['db_free_result']($request);

				// @todo why is this using id_topic?
				// @todo Can this get cached?
				$request = $smcFunc['db_query']('', '
					SELECT COUNT(id_topic)
					FROM {db_prefix}topics
					WHERE id_member_started={int:id_member}
						AND approved = {int:unapproved}
						AND id_board = {int:board}',
					array(
						'id_member' => $user_info['id'],
						'unapproved' => 0,
						'board' => $board,
					)
				);

				list ($board_info['unapproved_user_topics']) = $smcFunc['db_fetch_row']($request);
			}

			if (!empty($modSettings['cache_enable']) && (empty($topic) || $modSettings['cache_enable'] >= 3))
			{
				// @todo SLOW?
				if (!empty($topic))
					cache_put_data('topic_board-' . $topic, $board_info, 120);
				cache_put_data('board-' . $board, $board_info, 120);
			}
		}
		else
		{
			// Otherwise the topic is invalid, there are no moderators, etc.
			$board_info = array(
				'moderators' => array(),
				'error' => 'exist'
			);
			$topic = null;
			$board = 0;
		}
		$smcFunc['db_free_result']($request);
	}

	if (!empty($topic))
		$_GET['board'] = (int) $board;

	if (!empty($board))
	{
		// Now check if the user is a moderator.
		$user_info['is_mod'] = isset($board_info['moderators'][$user_info['id']]);

		if (count(array_intersect($user_info['groups'], $board_info['groups'])) == 0 && !$user_info['is_admin'])
			$board_info['error'] = 'access';
		if (count(array_intersect($user_info['groups'], $board_info['deny_groups'])) != 0 && !$user_info['is_admin'])
			$board_info['error'] = 'access';

		// Build up the linktree.
		$context['linktree'] = array_merge(
			$context['linktree'],
			array(array(
				'url' => $scripturl . '#c' . $board_info['cat']['id'],
				'name' => $board_info['cat']['name']
			)),
			array_reverse($board_info['parent_boards']),
			array(array(
				'url' => $scripturl . '?board=' . $board . '.0',
				'name' => $board_info['name']
			))
		);
	}

	// Set the template contextual information.
	$context['user']['is_mod'] = &$user_info['is_mod'];
	$context['current_topic'] = $topic;
	$context['current_board'] = $board;

	// Hacker... you can't see this topic, I'll tell you that. (but moderators can!)
	if (!empty($board_info['error']) && ($board_info['error'] != 'access' || !$user_info['is_mod']))
	{
		// The permissions and theme need loading, just to make sure everything goes smoothly.
		loadPermissions();
		loadTheme();

		$_GET['board'] = '';
		$_GET['topic'] = '';

		// The linktree should not give the game away mate!
		$context['linktree'] = array(
			array(
				'url' => $scripturl,
				'name' => $context['forum_name_html_safe']
			)
		);

		// If it's a prefetching agent or we're requesting an attachment.
		if ((isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch') || (!empty($_REQUEST['action']) && $_REQUEST['action'] === 'dlattach'))
		{
			ob_end_clean();
			header('HTTP/1.1 403 Forbidden');
			die;
		}
		elseif ($user_info['is_guest'])
		{
			loadLanguage('Errors');
			is_not_guest($txt['topic_gone']);
		}
		else
			fatal_lang_error('topic_gone', false);
	}

	if ($user_info['is_mod'])
		$user_info['groups'][] = 3;
}

/**
 * Load this user's permissions.
 *
 */
function loadPermissions()
{
	global $user_info, $board, $board_info, $modSettings, $smcFunc, $sourcedir;

	if ($user_info['is_admin'])
	{
		banPermissions();
		return;
	}

	if (!empty($modSettings['cache_enable']))
	{
		$cache_groups = $user_info['groups'];
		asort($cache_groups);
		$cache_groups = implode(',', $cache_groups);
		// If it's a spider then cache it different.
		if ($user_info['possibly_robot'])
			$cache_groups .= '-spider';

		if ($modSettings['cache_enable'] >= 2 && !empty($board) && ($temp = cache_get_data('permissions:' . $cache_groups . ':' . $board, 240)) != null && time() - 240 > $modSettings['settings_updated'])
		{
			list ($user_info['permissions']) = $temp;
			banPermissions();

			return;
		}
		elseif (($temp = cache_get_data('permissions:' . $cache_groups, 240)) != null && time() - 240 > $modSettings['settings_updated'])
			list ($user_info['permissions'], $removals) = $temp;
	}

	// If it is detected as a robot, and we are restricting permissions as a special group - then implement this.
	$spider_restrict = $user_info['possibly_robot'] && !empty($modSettings['spider_group']) ? ' OR (id_group = {int:spider_group} AND add_deny = 0)' : '';

	if (empty($user_info['permissions']))
	{
		// Get the general permissions.
		$request = $smcFunc['db_query']('', '
			SELECT permission, add_deny
			FROM {db_prefix}permissions
			WHERE id_group IN ({array_int:member_groups})
				' . $spider_restrict,
			array(
				'member_groups' => $user_info['groups'],
				'spider_group' => !empty($modSettings['spider_group']) ? $modSettings['spider_group'] : 0,
			)
		);
		$removals = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (empty($row['add_deny']))
				$removals[] = $row['permission'];
			else
				$user_info['permissions'][] = $row['permission'];
		}
		$smcFunc['db_free_result']($request);

		if (isset($cache_groups))
			cache_put_data('permissions:' . $cache_groups, array($user_info['permissions'], $removals), 240);
	}

	// Get the board permissions.
	if (!empty($board))
	{
		// Make sure the board (if any) has been loaded by loadBoard().
		if (!isset($board_info['profile']))
			fatal_lang_error('no_board');

		$request = $smcFunc['db_query']('', '
			SELECT permission, add_deny
			FROM {db_prefix}board_permissions
			WHERE (id_group IN ({array_int:member_groups})
				' . $spider_restrict . ')
				AND id_profile = {int:id_profile}',
			array(
				'member_groups' => $user_info['groups'],
				'id_profile' => $board_info['profile'],
				'spider_group' => !empty($modSettings['spider_group']) ? $modSettings['spider_group'] : 0,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (empty($row['add_deny']))
				$removals[] = $row['permission'];
			else
				$user_info['permissions'][] = $row['permission'];
		}
		$smcFunc['db_free_result']($request);
	}

	// Remove all the permissions they shouldn't have ;).
	if (!empty($modSettings['permission_enable_deny']))
		$user_info['permissions'] = array_diff($user_info['permissions'], $removals);

	if (isset($cache_groups) && !empty($board) && $modSettings['cache_enable'] >= 2)
		cache_put_data('permissions:' . $cache_groups . ':' . $board, array($user_info['permissions'], null), 240);

	// Banned?  Watch, don't touch..
	banPermissions();

	// Load the mod cache so we can know what additional boards they should see, but no sense in doing it for guests
	if (!$user_info['is_guest'])
	{
		if (!isset($_SESSION['mc']) || $_SESSION['mc']['time'] <= $modSettings['settings_updated'])
		{
			require_once($sourcedir . '/Subs-Auth.php');
			rebuildModCache();
		}
		else
			$user_info['mod_cache'] = $_SESSION['mc'];
	}
}

/**
 * Loads an array of users' data by ID or member_name.
 *
 * @param mixed $users An array of users by id or name
 * @param bool $is_name = false $users is by name or by id
 * @param string $set = 'normal' What kind of data to load (normal, profile, minimal)
 * @return array|bool The ids of the members loaded or false
 */
function loadMemberData($users, $is_name = false, $set = 'normal')
{
	global $user_profile, $modSettings, $board_info, $smcFunc;

	// Can't just look for no users :P.
	if (empty($users))
		return false;

	// Make sure it's an array.
	$users = !is_array($users) ? array($users) : array_unique($users);
	$loaded_ids = array();

	if (!$is_name && !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 3)
	{
		$users = array_values($users);
		for ($i = 0, $n = count($users); $i < $n; $i++)
		{
			$data = cache_get_data('member_data-' . $set . '-' . $users[$i], 240);
			if ($data == null)
				continue;

			$loaded_ids[] = $data['id_member'];
			$user_profile[$data['id_member']] = $data;
			unset($users[$i]);
		}
	}

	if ($set == 'normal')
	{
		$select_columns = '
			IFNULL(lo.log_time, 0) AS is_online, IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type,
			mem.signature, mem.personal_text, mem.location, mem.gender, mem.avatar, mem.id_member, mem.member_name,
			mem.real_name, mem.email_address, mem.hide_email, mem.date_registered, mem.website_title, mem.website_url,
			mem.birthdate, mem.member_ip, mem.member_ip2, mem.icq, mem.aim, mem.yim, mem.msn, mem.posts, mem.last_login,
			mem.id_post_group, mem.lngfile, mem.id_group, mem.time_offset, mem.show_online,
			mem.buddy_list, mg.online_color AS member_group_color, IFNULL(mg.group_name, {string:blank_string}) AS member_group,
			pg.online_color AS post_group_color, IFNULL(pg.group_name, {string:blank_string}) AS post_group, mem.is_activated, mem.warning,
			CASE WHEN mem.id_group = 0 OR mg.icons = {string:blank_string} THEN pg.icons ELSE mg.icons END AS icons' . (!empty($modSettings['titlesEnable']) ? ',
			mem.usertitle' : '');
		$select_tables = '
			LEFT JOIN {db_prefix}log_online AS lo ON (lo.id_member = mem.id_member)
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = mem.id_member)
			LEFT JOIN {db_prefix}membergroups AS pg ON (pg.id_group = mem.id_post_group)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)';
	}
	elseif ($set == 'profile')
	{
		$select_columns = '
			IFNULL(lo.log_time, 0) AS is_online, IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type,
			mem.signature, mem.personal_text, mem.location, mem.gender, mem.avatar, mem.id_member, mem.member_name,
			mem.real_name, mem.email_address, mem.hide_email, mem.date_registered, mem.website_title, mem.website_url,
			mem.openid_uri, mem.birthdate, mem.icq, mem.aim, mem.yim, mem.msn, mem.posts, mem.last_login,
			mem.member_ip, mem.member_ip2, mem.lngfile, mem.id_group, mem.id_theme, mem.buddy_list,
			mem.pm_ignore_list, mem.pm_email_notify, mem.pm_receive_from, mem.time_offset' . (!empty($modSettings['titlesEnable']) ? ', mem.usertitle' : '') . ',
			mem.time_format, mem.secret_question, mem.is_activated, mem.additional_groups, mem.smiley_set, mem.show_online,
			mem.total_time_logged_in, mem.id_post_group, mem.notify_announcements, mem.notify_regularity, mem.notify_send_body,
			mem.notify_types, lo.url, mg.online_color AS member_group_color, IFNULL(mg.group_name, {string:blank_string}) AS member_group,
			pg.online_color AS post_group_color, IFNULL(pg.group_name, {string:blank_string}) AS post_group, mem.ignore_boards, mem.warning,
			CASE WHEN mem.id_group = 0 OR mg.icons = {string:blank_string} THEN pg.icons ELSE mg.icons END AS icons, mem.password_salt, mem.pm_prefs';
		$select_tables = '
			LEFT JOIN {db_prefix}log_online AS lo ON (lo.id_member = mem.id_member)
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = mem.id_member)
			LEFT JOIN {db_prefix}membergroups AS pg ON (pg.id_group = mem.id_post_group)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)';
	}
	elseif ($set == 'minimal')
	{
		$select_columns = '
			mem.id_member, mem.member_name, mem.real_name, mem.email_address, mem.hide_email, mem.date_registered,
			mem.posts, mem.last_login, mem.member_ip, mem.member_ip2, mem.lngfile, mem.id_group';
		$select_tables = '';
	}
	else
		trigger_error('loadMemberData(): Invalid member data set \'' . $set . '\'', E_USER_WARNING);

	// Allow mods to easily add to the selected member data
	call_integration_hook('integrate_load_member_data', array(&$select_columns, &$select_tables));
	
	if (!empty($users))
	{
		// Load the member's data.
		$request = $smcFunc['db_query']('', '
			SELECT' . $select_columns . '
			FROM {db_prefix}members AS mem' . $select_tables . '
			WHERE mem.' . ($is_name ? 'member_name' : 'id_member') . (count($users) == 1 ? ' = {' . ($is_name ? 'string' : 'int') . ':users}' : ' IN ({' . ($is_name ? 'array_string' : 'array_int') . ':users})'),
			array(
				'blank_string' => '',
				'users' => count($users) == 1 ? current($users) : $users,
			)
		);
		$new_loaded_ids = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$new_loaded_ids[] = $row['id_member'];
			$loaded_ids[] = $row['id_member'];
			$row['options'] = array();
			$user_profile[$row['id_member']] = $row;
		}
		$smcFunc['db_free_result']($request);
	}

	if (!empty($new_loaded_ids) && $set !== 'minimal')
	{
		$request = $smcFunc['db_query']('', '
			SELECT *
			FROM {db_prefix}themes
			WHERE id_member' . (count($new_loaded_ids) == 1 ? ' = {int:loaded_ids}' : ' IN ({array_int:loaded_ids})'),
			array(
				'loaded_ids' => count($new_loaded_ids) == 1 ? $new_loaded_ids[0] : $new_loaded_ids,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$user_profile[$row['id_member']]['options'][$row['variable']] = $row['value'];
		$smcFunc['db_free_result']($request);
	}

	if (!empty($new_loaded_ids) && !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 3)
	{
		for ($i = 0, $n = count($new_loaded_ids); $i < $n; $i++)
			cache_put_data('member_data-' . $set . '-' . $new_loaded_ids[$i], $user_profile[$new_loaded_ids[$i]], 240);
	}

	// Are we loading any moderators?  If so, fix their group data...
	if (!empty($loaded_ids) && !empty($board_info['moderators']) && $set === 'normal' && count($temp_mods = array_intersect($loaded_ids, array_keys($board_info['moderators']))) !== 0)
	{
		if (($row = cache_get_data('moderator_group_info', 480)) == null)
		{
			$request = $smcFunc['db_query']('', '
				SELECT group_name AS member_group, online_color AS member_group_color, icons
				FROM {db_prefix}membergroups
				WHERE id_group = {int:moderator_group}
				LIMIT 1',
				array(
					'moderator_group' => 3,
				)
			);
			$row = $smcFunc['db_fetch_assoc']($request);
			$smcFunc['db_free_result']($request);

			cache_put_data('moderator_group_info', $row, 480);
		}

		foreach ($temp_mods as $id)
		{
			// By popular demand, don't show admins or global moderators as moderators.
			if ($user_profile[$id]['id_group'] != 1 && $user_profile[$id]['id_group'] != 2)
				$user_profile[$id]['member_group'] = $row['member_group'];

			// If the Moderator group has no color or icons, but their group does... don't overwrite.
			if (!empty($row['icons']))
				$user_profile[$id]['icons'] = $row['icons'];
			if (!empty($row['member_group_color']))
				$user_profile[$id]['member_group_color'] = $row['member_group_color'];
		}
	}

	return empty($loaded_ids) ? false : $loaded_ids;
}

/**
 * Loads the user's basic values... meant for template/theme usage.
 *
 * @param int $user
 * @param bool $display_custom_fields = false
 * @return bool
 */
function loadMemberContext($user, $display_custom_fields = false)
{
	global $memberContext, $user_profile, $txt, $scripturl, $user_info;
	global $context, $modSettings, $board_info, $settings;
	global $smcFunc;
	static $dataLoaded = array();

	// If this person's data is already loaded, skip it.
	if (isset($dataLoaded[$user]))
		return true;

	// We can't load guests or members not loaded by loadMemberData()!
	if ($user == 0)
		return false;
	if (!isset($user_profile[$user]))
	{
		trigger_error('loadMemberContext(): member id ' . $user . ' not previously loaded by loadMemberData()', E_USER_WARNING);
		return false;
	}

	// Well, it's loaded now anyhow.
	$dataLoaded[$user] = true;
	$profile = $user_profile[$user];

	// Censor everything.
	censorText($profile['signature']);
	censorText($profile['personal_text']);
	censorText($profile['location']);

	// Set things up to be used before hand.
	$gendertxt = $profile['gender'] == 2 ? $txt['female'] : ($profile['gender'] == 1 ? $txt['male'] : '');
	$profile['signature'] = str_replace(array("\n", "\r"), array('<br />', ''), $profile['signature']);
	$profile['signature'] = parse_bbc($profile['signature'], true, 'sig' . $profile['id_member']);

	$profile['is_online'] = (!empty($profile['show_online']) || allowedTo('moderate_forum')) && $profile['is_online'] > 0;
	$profile['icons'] = empty($profile['icons']) ? array('', '') : explode('#', $profile['icons']);
	// Setup the buddy status here (One whole in_array call saved :P)
	$profile['buddy'] = in_array($profile['id_member'], $user_info['buddies']);
	$buddy_list = !empty($profile['buddy_list']) ? explode(',', $profile['buddy_list']) : array();

	// If we're always html resizing, assume it's too large.
	if ($modSettings['avatar_action_too_large'] == 'option_html_resize' || $modSettings['avatar_action_too_large'] == 'option_js_resize')
	{
		$avatar_width = !empty($modSettings['avatar_max_width_external']) ? ' width="' . $modSettings['avatar_max_width_external'] . '"' : '';
		$avatar_height = !empty($modSettings['avatar_max_height_external']) ? ' height="' . $modSettings['avatar_max_height_external'] . '"' : '';
	}
	else
	{
		$avatar_width = '';
		$avatar_height = '';
	}

	// What a monstrous array...
	$memberContext[$user] = array(
		'username' => $profile['member_name'],
		'name' => $profile['real_name'],
		'id' => $profile['id_member'],
		'is_buddy' => $profile['buddy'],
		'is_reverse_buddy' => in_array($user_info['id'], $buddy_list),
		'buddies' => $buddy_list,
		'title' => !empty($modSettings['titlesEnable']) ? $profile['usertitle'] : '',
		'href' => $scripturl . '?action=profile;u=' . $profile['id_member'],
		'link' => '<a href="' . $scripturl . '?action=profile;u=' . $profile['id_member'] . '" title="' . $txt['profile_of'] . ' ' . $profile['real_name'] . '">' . $profile['real_name'] . '</a>',
		'email' => $profile['email_address'],
		'show_email' => showEmailAddress(!empty($profile['hide_email']), $profile['id_member']),
		'registered' => empty($profile['date_registered']) ? $txt['not_applicable'] : timeformat($profile['date_registered']),
		'registered_timestamp' => empty($profile['date_registered']) ? 0 : forum_time(true, $profile['date_registered']),
		'blurb' => $profile['personal_text'],
		'gender' => array(
			'name' => $gendertxt,
			'image' => !empty($profile['gender']) ? '<img class="gender" src="' . $settings['images_url'] . '/' . ($profile['gender'] == 1 ? 'Male' : 'Female') . '.png" alt="' . $gendertxt . '" />' : ''
		),
		'website' => array(
			'title' => $profile['website_title'],
			'url' => $profile['website_url'],
		),
		'birth_date' => empty($profile['birthdate']) || $profile['birthdate'] === '0001-01-01' ? '0000-00-00' : (substr($profile['birthdate'], 0, 4) === '0004' ? '0000' . substr($profile['birthdate'], 4) : $profile['birthdate']),
		'signature' => $profile['signature'],
		'location' => $profile['location'],
		'icq' => $profile['icq'] != '' && (empty($modSettings['guest_hideContacts']) || !$user_info['is_guest']) ? array(
			'name' => $profile['icq'],
			'href' => 'http://www.icq.com/whitepages/about_me.php?uin=' . $profile['icq'],
			'link' => '<a class="icq new_win" href="http://www.icq.com/whitepages/about_me.php?uin=' . $profile['icq'] . '" target="_blank" title="' . $txt['icq_title'] . ' - ' . $profile['icq'] . '"><img src="http://status.icq.com/online.png?img=5&amp;icq=' . $profile['icq'] . '" alt="' . $txt['icq_title'] . ' - ' . $profile['icq'] . '" width="18" height="18" /></a>',
			'link_text' => '<a class="icq extern" href="http://www.icq.com/whitepages/about_me.php?uin=' . $profile['icq'] . '" title="' . $txt['icq_title'] . ' - ' . $profile['icq'] . '">' . $profile['icq'] . '</a>',
		) : array('name' => '', 'add' => '', 'href' => '', 'link' => '', 'link_text' => ''),
		'aim' => $profile['aim'] != '' && (empty($modSettings['guest_hideContacts']) || !$user_info['is_guest']) ? array(
			'name' => $profile['aim'],
			'href' => 'aim:goim?screenname=' . urlencode(strtr($profile['aim'], array(' ' => '%20'))) . '&amp;message=' . $txt['aim_default_message'],
			'link' => '<a class="aim" href="aim:goim?screenname=' . urlencode(strtr($profile['aim'], array(' ' => '%20'))) . '&amp;message=' . $txt['aim_default_message'] . '" title="' . $txt['aim_title'] . ' - ' . $profile['aim'] . '"><img src="' . $settings['images_url'] . '/aim.png" alt="' . $txt['aim_title'] . ' - ' . $profile['aim'] . '" /></a>',
			'link_text' => '<a class="aim" href="aim:goim?screenname=' . urlencode(strtr($profile['aim'], array(' ' => '%20'))) . '&amp;message=' . $txt['aim_default_message'] . '" title="' . $txt['aim_title'] . ' - ' . $profile['aim'] . '">' . $profile['aim'] . '</a>'
		) : array('name' => '', 'href' => '', 'link' => '', 'link_text' => ''),
		'yim' => $profile['yim'] != '' && (empty($modSettings['guest_hideContacts']) || !$user_info['is_guest']) ? array(
			'name' => $profile['yim'],
			'href' => 'http://edit.yahoo.com/config/send_webmesg?.target=' . urlencode($profile['yim']),
			'link' => '<a class="yim" href="http://edit.yahoo.com/config/send_webmesg?.target=' . urlencode($profile['yim']) . '" title="' . $txt['yim_title'] . ' - ' . $profile['yim'] . '"><img src="http://opi.yahoo.com/online?u=' . urlencode($profile['yim']) . '&amp;m=g&amp;t=0" alt="' . $txt['yim_title'] . ' - ' . $profile['yim'] . '" /></a>',
			'link_text' => '<a class="yim" href="http://edit.yahoo.com/config/send_webmesg?.target=' . urlencode($profile['yim']) . '" title="' . $txt['yim_title'] . ' - ' . $profile['yim'] . '">' . $profile['yim'] . '</a>'
		) : array('name' => '', 'href' => '', 'link' => '', 'link_text' => ''),
		'msn' => $profile['msn'] !='' && (empty($modSettings['guest_hideContacts']) || !$user_info['is_guest']) ? array(
			'name' => $profile['msn'],
			'href' => 'http://members.msn.com/' . $profile['msn'],
			'link' => '<a class="msn new_win" href="http://members.msn.com/' . $profile['msn'] . '" title="' . $txt['msn_title'] . ' - ' . $profile['msn'] . '"><img src="' . $settings['images_url'] . '/msntalk.png" alt="' . $txt['msn_title'] . ' - ' . $profile['msn'] . '" /></a>',
			'link_text' => '<a class="msn new_win" href="http://members.msn.com/' . $profile['msn'] . '" title="' . $txt['msn_title'] . ' - ' . $profile['msn'] . '">' . $profile['msn'] . '</a>'
		) : array('name' => '', 'href' => '', 'link' => '', 'link_text' => ''),
		'real_posts' => $profile['posts'],
		'posts' => $profile['posts'] > 500000 ? $txt['geek'] : comma_format($profile['posts']),
		'avatar' => array(
			'name' => $profile['avatar'],
			'image' => $profile['avatar'] == '' ? ($profile['id_attach'] > 0 ? '<img class="avatar" src="' . (empty($profile['attachment_type']) ? $scripturl . '?action=dlattach;attach=' . $profile['id_attach'] . ';type=avatar' : $modSettings['custom_avatar_url'] . '/' . $profile['filename']) . '" alt="" />' : '') : (stristr($profile['avatar'], 'http://') ? '<img class="avatar" src="' . $profile['avatar'] . '"' . $avatar_width . $avatar_height . ' alt="" />' : '<img class="avatar" src="' . $modSettings['avatar_url'] . '/' . htmlspecialchars($profile['avatar']) . '" alt="" />'),
			'href' => $profile['avatar'] == '' ? ($profile['id_attach'] > 0 ? (empty($profile['attachment_type']) ? $scripturl . '?action=dlattach;attach=' . $profile['id_attach'] . ';type=avatar' : $modSettings['custom_avatar_url'] . '/' . $profile['filename']) : '') : (stristr($profile['avatar'], 'http://') ? $profile['avatar'] : $modSettings['avatar_url'] . '/' . $profile['avatar']),
			'url' => $profile['avatar'] == '' ? '' : (stristr($profile['avatar'], 'http://') ? $profile['avatar'] : $modSettings['avatar_url'] . '/' . $profile['avatar'])
		),
		'last_login' => empty($profile['last_login']) ? $txt['never'] : timeformat($profile['last_login']),
		'last_login_timestamp' => empty($profile['last_login']) ? 0 : forum_time(0, $profile['last_login']),
		'ip' => htmlspecialchars($profile['member_ip']),
		'ip2' => htmlspecialchars($profile['member_ip2']),
		'online' => array(
			'is_online' => $profile['is_online'],
			'text' => $txt[$profile['is_online'] ? 'online' : 'offline'],
			'href' => $scripturl . '?action=pm;sa=send;u=' . $profile['id_member'],
			'link' => '<a href="' . $scripturl . '?action=pm;sa=send;u=' . $profile['id_member'] . '">' . $txt[$profile['is_online'] ? 'online' : 'offline'] . '</a>',
			'image_href' => $settings['images_url'] . '/' . ($profile['buddy'] ? 'buddy_' : '') . ($profile['is_online'] ? 'useron' : 'useroff') . '.png',
			'label' => $txt[$profile['is_online'] ? 'online' : 'offline']
		),
		'language' => $smcFunc['ucwords'](strtr($profile['lngfile'], array('_' => ' ', '-utf8' => ''))),
		'is_activated' => isset($profile['is_activated']) ? $profile['is_activated'] : 1,
		'is_banned' => isset($profile['is_activated']) ? $profile['is_activated'] >= 10 : 0,
		'options' => $profile['options'],
		'is_guest' => false,
		'group' => $profile['member_group'],
		'group_color' => $profile['member_group_color'],
		'group_id' => $profile['id_group'],
		'post_group' => $profile['post_group'],
		'post_group_color' => $profile['post_group_color'],
		'group_icons' => str_repeat('<img src="' . str_replace('$language', $context['user']['language'], isset($profile['icons'][1]) ? $settings['images_url'] . '/' . $profile['icons'][1] : '') . '" alt="*" />', empty($profile['icons'][0]) || empty($profile['icons'][1]) ? 0 : $profile['icons'][0]),
		'warning' => $profile['warning'],
		'warning_status' => !empty($modSettings['warning_mute']) && $modSettings['warning_mute'] <= $profile['warning'] ? 'mute' : (!empty($modSettings['warning_moderate']) && $modSettings['warning_moderate'] <= $profile['warning'] ? 'moderate' : (!empty($modSettings['warning_watch']) && $modSettings['warning_watch'] <= $profile['warning'] ? 'watch' : (''))),
		'local_time' => timeformat(time() + ($profile['time_offset'] - $user_info['time_offset']) * 3600, false),
	);

	// First do a quick run through to make sure there is something to be shown.
	$memberContext[$user]['has_messenger'] = false;
	foreach (array('icq', 'msn', 'aim', 'yim') as $messenger)
	{
		if (!isset($context['disabled_fields'][$messenger]) && !empty($memberContext[$user][$messenger]['link']))
		{
			$memberContext[$user]['has_messenger'] = true;
			break;
		}
	}

	// Are we also loading the members custom fields into context?
	if ($display_custom_fields && !empty($modSettings['displayFields']))
	{
		$memberContext[$user]['custom_fields'] = array();
		if (!isset($context['display_fields']))
			$context['display_fields'] = unserialize($modSettings['displayFields']);

		foreach ($context['display_fields'] as $custom)
		{
			if (!isset($custom['title']) || trim($custom['title']) == '' || empty($profile['options'][$custom['colname']]))
				continue;

			$value = $profile['options'][$custom['colname']];

			// BBC?
			if ($custom['bbc'])
				$value = parse_bbc($value);
			// ... or checkbox?
			elseif (isset($custom['type']) && $custom['type'] == 'check')
				$value = $value ? $txt['yes'] : $txt['no'];

			// Enclosing the user input within some other text?
			if (!empty($custom['enclose']))
				$value = strtr($custom['enclose'], array(
					'{SCRIPTURL}' => $scripturl,
					'{IMAGES_URL}' => $settings['images_url'],
					'{DEFAULT_IMAGES_URL}' => $settings['default_images_url'],
					'{INPUT}' => $value,
				));

			$memberContext[$user]['custom_fields'][] = array(
				'title' => $custom['title'],
				'colname' => $custom['colname'],
				'value' => $value,
				'placement' => !empty($custom['placement']) ? $custom['placement'] : 0,
			);
		}
	}

	call_integration_hook('integrate_member_context', array(&$user, $display_custom_fields));
	return true;
}

/**
 * Loads information about what browser the user is viewing with and places it in $context
 *  - uses the class from Class-BrowerDetect.php
 *
 */
function detectBrowser()
{
	// Load the current user's browser of choice
	$detector = new browser_detector;
	$detector->detectBrowser();
}

/**
 * Are we using this browser?
 *
 * Wrapper function for detectBrowser
 * @param $browser: browser we are checking for.
*/
function isBrowser($browser)
{
	global $context;

	// @todo REMOVE THIS BEFORE BETA 1 RELEASE.
	if (in_array($browser, array('ie7', 'ie6', 'ie5.5', 'ie5', 'ie5', 'ie4', 'mac_ie', 'firefox1')))
	{
		$line = $file = null;
		foreach (debug_backtrace() as $step)
		{
			// Found it?
			if (strpos($step['function'], 'query') === false && !in_array(substr($step['function'], 0, 7), array('smf_db_', 'preg_re', 'db_erro', 'call_us')) && strpos($step['function'], '__') !== 0)
			{
				$function = '<br />Function: ' . $step['function'];
				break;
			}

			if (isset($step['line']))
			{
				$file = $step['file'];
				$line = $step['line'];
			}
		}

		log_error('Old browser support' . $function, 'debug', $file, $line);
	}

	// Don't know any browser!
	if (empty($context['browser']))
		detectBrowser();

	return !empty($context['browser'][$browser]) || !empty($context['browser']['is_' . $browser]) ? true : false;
}

/**
 * Get all parent boards (requires first parent as parameter)
 * It finds all the parents of id_parent, and that board itself.
 * Additionally, it detects the moderators of said boards.
 *
 * @param int $id_parent
 * @return an array of information about the boards found.
 */
function getBoardParents($id_parent)
{
	global $scripturl, $smcFunc;

	// First check if we have this cached already.
	if (($boards = cache_get_data('board_parents-' . $id_parent, 480)) === null)
	{
		$boards = array();
		$original_parent = $id_parent;

		// Loop while the parent is non-zero.
		while ($id_parent != 0)
		{
			$result = $smcFunc['db_query']('', '
				SELECT
					b.id_parent, b.name, {int:board_parent} AS id_board, IFNULL(mem.id_member, 0) AS id_moderator,
					mem.real_name, b.child_level
				FROM {db_prefix}boards AS b
					LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board)
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
				WHERE b.id_board = {int:board_parent}',
				array(
					'board_parent' => $id_parent,
				)
			);
			// In the EXTREMELY unlikely event this happens, give an error message.
			if ($smcFunc['db_num_rows']($result) == 0)
				fatal_lang_error('parent_not_found', 'critical');
			while ($row = $smcFunc['db_fetch_assoc']($result))
			{
				if (!isset($boards[$row['id_board']]))
				{
					$id_parent = $row['id_parent'];
					$boards[$row['id_board']] = array(
						'url' => $scripturl . '?board=' . $row['id_board'] . '.0',
						'name' => $row['name'],
						'level' => $row['child_level'],
						'moderators' => array()
					);
				}
				// If a moderator exists for this board, add that moderator for all children too.
				if (!empty($row['id_moderator']))
					foreach ($boards as $id => $dummy)
					{
						$boards[$id]['moderators'][$row['id_moderator']] = array(
							'id' => $row['id_moderator'],
							'name' => $row['real_name'],
							'href' => $scripturl . '?action=profile;u=' . $row['id_moderator'],
							'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_moderator'] . '">' . $row['real_name'] . '</a>'
						);
					}
			}
			$smcFunc['db_free_result']($result);
		}

		cache_put_data('board_parents-' . $original_parent, $boards, 480);
	}

	return $boards;
}

/**
 * Attempt to reload our known languages.
 * It will try to choose only utf8 or non-utf8 languages.
 *
 * @param bool $use_cache = true
 * @param bool $favor_utf8 = true
 * @return array
 */
function getLanguages($use_cache = true, $favor_utf8 = true)
{
	global $context, $smcFunc, $settings, $modSettings;

	// Either we don't use the cache, or its expired.
	if (!$use_cache || ($context['languages'] = cache_get_data('known_languages' . ($favor_utf8 ? '' : '_all'), !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] < 1 ? 86400 : 3600)) == null)
	{
		// If we don't have our theme information yet, lets get it.
		if (empty($settings['default_theme_dir']))
			loadTheme(0, false);

		// Default language directories to try.
		$language_directories = array(
			$settings['default_theme_dir'] . '/languages',
			$settings['actual_theme_dir'] . '/languages',
		);

		// We possibly have a base theme directory.
		if (!empty($settings['base_theme_dir']))
			$language_directories[] = $settings['base_theme_dir'] . '/languages';

		// Remove any duplicates.
		$language_directories = array_unique($language_directories);

		foreach ($language_directories as $language_dir)
		{
			// Can't look in here... doesn't exist!
			if (!file_exists($language_dir))
				continue;

			$dir = dir($language_dir);
			while ($entry = $dir->read())
			{
				// Look for the index language file....
				if (!preg_match('~^index\.(.+)\.php$~', $entry, $matches))
					continue;

				$context['languages'][$matches[1]] = array(
					'name' => $smcFunc['ucwords'](strtr($matches[1], array('_' => ' '))),
					'selected' => false,
					'filename' => $matches[1],
					'location' => $language_dir . '/index.' . $matches[1] . '.php',
				);

			}
			$dir->close();
		}

		// Favoring UTF8? Then prevent us from selecting non-UTF8 versions.
		if ($favor_utf8)
		{
			foreach ($context['languages'] as $lang)
				if (substr($lang['filename'], strlen($lang['filename']) - 5, 5) != '-utf8' && isset($context['languages'][$lang['filename'] . '-utf8']))
					unset($context['languages'][$lang['filename']]);
		}

		// Lets cash in on this deal.
		if (!empty($modSettings['cache_enable']))
			cache_put_data('known_languages' . ($favor_utf8 ? '' : '_all'), $context['languages'], !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] < 1 ? 86400 : 3600);
	}

	return $context['languages'];
}

/**
 * Replace all vulgar words with respective proper words. (substring or whole words..)
 * What this function does:
 *  - it censors the passed string.
 *  - if the theme setting allow_no_censored is on, and the theme option
 *    show_no_censored is enabled, does not censor, unless force is also set.
 *  - it caches the list of censored words to reduce parsing.
 *
 * @param string &$text
 * @param bool $force = false
 * @return string The censored text
 */
function censorText(&$text, $force = false)
{
	global $modSettings, $options, $settings, $txt;
	static $censor_vulgar = null, $censor_proper;

	if ((!empty($options['show_no_censored']) && $settings['allow_no_censored'] && !$force) || empty($modSettings['censor_vulgar']) || trim($text) === '')
		return $text;

	// If they haven't yet been loaded, load them.
	if ($censor_vulgar == null)
	{
		$censor_vulgar = explode("\n", $modSettings['censor_vulgar']);
		$censor_proper = explode("\n", $modSettings['censor_proper']);

		// Quote them for use in regular expressions.
		if (!empty($modSettings['censorWholeWord']))
		{
			for ($i = 0, $n = count($censor_vulgar); $i < $n; $i++)
			{
				$censor_vulgar[$i] = str_replace(array('\\\\\\*', '\\*', '&', '\''), array('[*]', '[^\s]*?', '&amp;', '&#039;'), preg_quote($censor_vulgar[$i], '/'));
				$censor_vulgar[$i] = '/(?<=^|\W)' . $censor_vulgar[$i] . '(?=$|\W)/' . (empty($modSettings['censorIgnoreCase']) ? '' : 'i') . ((empty($modSettings['global_character_set']) ? $txt['lang_character_set'] : $modSettings['global_character_set']) === 'UTF-8' ? 'u' : '');

				// @todo I'm thinking the old way is some kind of bug and this is actually fixing it.
				//if (strpos($censor_vulgar[$i], '\'') !== false)
					//$censor_vulgar[$i] = str_replace('\'', '&#039;', $censor_vulgar[$i]);
			}
		}
	}

	// Censoring isn't so very complicated :P.
	if (empty($modSettings['censorWholeWord']))
		$text = empty($modSettings['censorIgnoreCase']) ? str_ireplace($censor_vulgar, $censor_proper, $text) : str_replace($censor_vulgar, $censor_proper, $text);
	else
		$text = preg_replace($censor_vulgar, $censor_proper, $text);

	return $text;
}

/**
 * Load the template/language file using eval or require? (with eval we can show an error message!)
 * 	- loads the template or language file specified by filename.
 * 	- uses eval unless disableTemplateEval is enabled.
 * 	- outputs a parse error if the file did not exist or contained errors.
 * 	- attempts to detect the error and line, and show detailed information.
 *
 * @param string $filename
 * @param bool $once = false, if true only includes the file once (like include_once)
 */
function template_include($filename, $once = false)
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;
	global $user_info, $boardurl, $boarddir, $sourcedir;
	global $maintenance, $mtitle, $mmessage;
	static $templates = array();

	// We want to be able to figure out any errors...
	@ini_set('track_errors', '1');

	// Don't include the file more than once, if $once is true.
	if ($once && in_array($filename, $templates))
		return;
	// Add this file to the include list, whether $once is true or not.
	else
		$templates[] = $filename;

	// Are we going to use eval?
	if (empty($modSettings['disableTemplateEval']))
	{
		$file_found = file_exists($filename) && eval('?' . '>' . rtrim(file_get_contents($filename))) !== false;
		$settings['current_include_filename'] = $filename;
	}
	else
	{
		$file_found = file_exists($filename);

		if ($once && $file_found)
			require_once($filename);
		elseif ($file_found)
			require($filename);
	}

	if ($file_found !== true)
	{
		ob_end_clean();
		if (!empty($modSettings['enableCompressedOutput']))
			@ob_start('ob_gzhandler');
		else
			ob_start();

		if (isset($_GET['debug']) && !WIRELESS)
			header('Content-Type: application/xhtml+xml; charset=' . (empty($context['character_set']) ? 'ISO-8859-1' : $context['character_set']));

		// Don't cache error pages!!
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: no-cache');

		if (!isset($txt['template_parse_error']))
		{
			$txt['template_parse_error'] = 'Template Parse Error!';
			$txt['template_parse_error_message'] = 'It seems something has gone sour on the forum with the template system.  This problem should only be temporary, so please come back later and try again.  If you continue to see this message, please contact the administrator.<br /><br />You can also try <a href="javascript:location.reload();">refreshing this page</a>.';
			$txt['template_parse_error_details'] = 'There was a problem loading the <tt><strong>%1$s</strong></tt> template or language file.  Please check the syntax and try again - remember, single quotes (<tt>\'</tt>) often have to be escaped with a slash (<tt>\\</tt>).  To see more specific error information from PHP, try <a href="' . $boardurl . '%1$s" class="extern">accessing the file directly</a>.<br /><br />You may want to try to <a href="javascript:location.reload();">refresh this page</a> or <a href="' . $scripturl . '?theme=1">use the default theme</a>.';
		}

		// First, let's get the doctype and language information out of the way.
		echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"', !empty($context['right_to_left']) ? ' dir="rtl"' : '', '>
	<head>';
		if (isset($context['character_set']))
			echo '
		<meta http-equiv="Content-Type" content="text/html; charset=', $context['character_set'], '" />';

		if (!empty($maintenance) && !allowedTo('admin_forum'))
			echo '
		<title>', $mtitle, '</title>
	</head>
	<body>
		<h3>', $mtitle, '</h3>
		', $mmessage, '
	</body>
</html>';
		elseif (!allowedTo('admin_forum'))
			echo '
		<title>', $txt['template_parse_error'], '</title>
	</head>
	<body>
		<h3>', $txt['template_parse_error'], '</h3>
		', $txt['template_parse_error_message'], '
	</body>
</html>';
		else
		{
			require_once($sourcedir . '/Subs-Package.php');

			$error = fetch_web_data($boardurl . strtr($filename, array($boarddir => '', strtr($boarddir, '\\', '/') => '')));
			if (empty($error) && ini_get('track_errors'))
				$error = $php_errormsg;

			$error = strtr($error, array('<b>' => '<strong>', '</b>' => '</strong>'));

			echo '
		<title>', $txt['template_parse_error'], '</title>
	</head>
	<body>
		<h3>', $txt['template_parse_error'], '</h3>
		', sprintf($txt['template_parse_error_details'], strtr($filename, array($boarddir => '', strtr($boarddir, '\\', '/') => '')));

			if (!empty($error))
				echo '
		<hr />

		<div style="margin: 0 20px;"><tt>', strtr(strtr($error, array('<strong>' . $boarddir => '<strong>...', '<strong>' . strtr($boarddir, '\\', '/') => '<strong>...')), '\\', '/'), '</tt></div>';

			// I know, I know... this is VERY COMPLICATED.  Still, it's good.
			if (preg_match('~ <strong>(\d+)</strong><br( /)?' . '>$~i', $error, $match) != 0)
			{
				$data = file($filename);
				$data2 = highlight_php_code(implode('', $data));
				$data2 = preg_split('~\<br( /)?\>~', $data2);

				// Fix the PHP code stuff...
				if (isBrowser('ie4') || isBrowser('ie5') || isBrowser('ie5.5'))
					$data2 = str_replace("\t", '<pre style="display: inline;">' . "\t" . '</pre>', $data2);
				elseif (!isBrowser('gecko'))
					$data2 = str_replace("\t", '<span style="white-space: pre;">' . "\t" . '</span>', $data2);
				else
					$data2 = str_replace('<pre style="display: inline;">' . "\t" . '</pre>', "\t", $data2);

				// Now we get to work around a bug in PHP where it doesn't escape <br />s!
				$j = -1;
				foreach ($data as $line)
				{
					$j++;

					if (substr_count($line, '<br />') == 0)
						continue;

					$n = substr_count($line, '<br />');
					for ($i = 0; $i < $n; $i++)
					{
						$data2[$j] .= '&lt;br /&gt;' . $data2[$j + $i + 1];
						unset($data2[$j + $i + 1]);
					}
					$j += $n;
				}
				$data2 = array_values($data2);
				array_unshift($data2, '');

				echo '
		<div style="margin: 2ex 20px; width: 96%; overflow: auto;"><pre style="margin: 0;">';

				// Figure out what the color coding was before...
				$line = max($match[1] - 9, 1);
				$last_line = '';
				for ($line2 = $line - 1; $line2 > 1; $line2--)
					if (strpos($data2[$line2], '<') !== false)
					{
						if (preg_match('~(<[^/>]+>)[^<]*$~', $data2[$line2], $color_match) != 0)
							$last_line = $color_match[1];
						break;
					}

				// Show the relevant lines...
				for ($n = min($match[1] + 4, count($data2) + 1); $line <= $n; $line++)
				{
					if ($line == $match[1])
						echo '</pre><div style="background-color: #ffb0b5;"><pre style="margin: 0;">';

					echo '<span style="color: black;">', sprintf('%' . strlen($n) . 's', $line), ':</span> ';
					if (isset($data2[$line]) && $data2[$line] != '')
						echo substr($data2[$line], 0, 2) == '</' ? preg_replace('~^</[^>]+>~', '', $data2[$line]) : $last_line . $data2[$line];

					if (isset($data2[$line]) && preg_match('~(<[^/>]+>)[^<]*$~', $data2[$line], $color_match) != 0)
					{
						$last_line = $color_match[1];
						echo '</', substr($last_line, 1, 4), '>';
					}
					elseif ($last_line != '' && strpos($data2[$line], '<') !== false)
						$last_line = '';
					elseif ($last_line != '' && $data2[$line] != '')
						echo '</', substr($last_line, 1, 4), '>';

					if ($line == $match[1])
						echo '</pre></div><pre style="margin: 0;">';
					else
						echo "\n";
				}

				echo '</pre></div>';
			}

			echo '
	</body>
</html>';
		}

		die;
	}
}

/**
 * Initialize a database connection.
 */
function loadDatabase()
{
	global $db_persist, $db_connection, $db_server, $db_user, $db_passwd;
	global $db_type, $db_name, $ssi_db_user, $ssi_db_passwd, $sourcedir, $db_prefix;

	// Figure out what type of database we are using.
	if (empty($db_type) || !file_exists($sourcedir . '/Subs-Db-' . $db_type . '.php'))
		$db_type = 'mysql';

	// Load the file for the database.
	require_once($sourcedir . '/Subs-Db-' . $db_type . '.php');

	// If we are in SSI try them first, but don't worry if it doesn't work, we have the normal username and password we can use.
	if (SMF == 'SSI' && !empty($ssi_db_user) && !empty($ssi_db_passwd))
		$db_connection = smf_db_initiate($db_server, $db_name, $ssi_db_user, $ssi_db_passwd, $db_prefix, array('persist' => $db_persist, 'non_fatal' => true, 'dont_select_db' => true));

	// Either we aren't in SSI mode, or it failed.
	if (empty($db_connection))
		$db_connection = smf_db_initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, array('persist' => $db_persist, 'dont_select_db' => SMF == 'SSI'));

	// Safe guard here, if there isn't a valid connection lets put a stop to it.
	if (!$db_connection)
		display_db_error();

	// If in SSI mode fix up the prefix.
	if (SMF == 'SSI')
		db_fix_prefix($db_prefix, $db_name);
}
