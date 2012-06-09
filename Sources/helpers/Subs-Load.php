<?php

/**
 * This file has helper load functions, of high utility.
 * Such loadTemplate(), loadLanguage().
 */

/**
 * Load a template - if the theme doesn't include it, use the default.
 * What this function does:
 *  - loads a template file with the name template_name from the current, default, or base theme.
 *  - detects a wrong default theme directory and tries to work around it.
 *
 * @uses the template_include() function to include the file.
 * @param string $template_name
 * @param array $style_sheets = array()
 * @param bool $fatal = true if fatal is true, dies with an error message if the template cannot be found
 * @return bool
 */
function loadTemplate($template_name, $style_sheets = array(), $fatal = true)
{
	global $context, $settings, $txt, $scripturl, $boarddir, $db_show_debug;

	// Do any style sheets first, cause we're easy with those.
	if (!empty($style_sheets))
	{
		if (!is_array($style_sheets))
			$style_sheets = array($style_sheets);

		foreach ($style_sheets as $sheet)
		{
			// Prevent the style sheet from being included twice.
			if (strpos($context['html_headers'], 'id="' . $sheet . '_css"') !== false)
				continue;

			$sheet_path = file_exists($settings['theme_dir']. '/css/' . $sheet . '.css') ? 'theme_url' : (file_exists($settings['default_theme_dir']. '/css/' . $sheet . '.css') ? 'default_theme_url' : '');
			if ($sheet_path)
			{
				$context['html_headers'] .= "\n\t" . '<link rel="stylesheet" type="text/css" id="' . $sheet . '_css" href="' . $settings[$sheet_path] . '/css/' . $sheet . '.css?alp21" />';
				if ($db_show_debug === true)
					$context['debug']['sheets'][] = $sheet . ' (' . basename($settings[$sheet_path]) . ')';
			}
		}
	}

	// No template to load?
	if ($template_name === false)
		return true;

	$loaded = false;
	foreach ($settings['template_dirs'] as $template_dir)
	{
		if (file_exists($template_dir . '/' . $template_name . '.template.php'))
		{
			$loaded = true;
			template_include($template_dir . '/' . $template_name . '.template.php', true);
			break;
		}
	}

	if ($loaded)
	{
		// For compatibility reasons, if this is the index template without new functions, include compatible stuff.
		if (substr($template_name, 0, 5) == 'index' && !function_exists('template_button_strip'))
			loadTemplate('Compat');

		if ($db_show_debug === true)
			$context['debug']['templates'][] = $template_name . ' (' . basename($template_dir) . ')';

		// If they have specified an initialization function for this template, go ahead and call it now.
		if (function_exists('template_' . $template_name . '_init'))
			call_user_func('template_' . $template_name . '_init');
	}
	// Hmmm... doesn't exist?!  I don't suppose the directory is wrong, is it?
	elseif (!file_exists($settings['default_theme_dir']) && file_exists($boarddir . '/Themes/default'))
	{
		$settings['default_theme_dir'] = $boarddir . '/Themes/default';
		$settings['template_dirs'][] = $settings['default_theme_dir'];

		if (!empty($context['user']['is_admin']) && !isset($_GET['th']))
		{
			loadLanguage('Errors');
			echo '
<div class="alert errorbox">
	<a href="', $scripturl . '?action=admin;area=theme;sa=settings;th=1;' . $context['session_var'] . '=' . $context['session_id'], '" class="alert">', $txt['theme_dir_wrong'], '</a>
</div>';
		}

		loadTemplate($template_name);
	}
	// Cause an error otherwise.
	elseif ($template_name != 'Errors' && $template_name != 'index' && $fatal)
		fatal_lang_error('theme_template_error', 'template', array((string) $template_name));
	elseif ($fatal)
		die(log_error(sprintf(isset($txt['theme_template_error']) ? $txt['theme_template_error'] : 'Unable to load Themes/default/%s.template.php!', (string) $template_name), 'template'));
	else
		return false;
}


/**
 * Load a sub-template.
 * What it does:
 * 	- loads the sub template specified by sub_template_name, which must be in an already-loaded template.
 *  - if ?debug is in the query string, shows administrators a marker after every sub template
 *    for debugging purposes.
 *
 * @todo get rid of reading $_REQUEST directly
 *
 * @param string $sub_template_name
 * @param bool $fatal = false, $fatal = true is for templates that shouldn't get a 'pretty' error screen.
 */
function loadSubTemplate($sub_template_name, $fatal = false)
{
	global $context, $settings, $options, $txt, $db_show_debug;

	if ($db_show_debug === true)
		$context['debug']['sub_templates'][] = $sub_template_name;

	// Figure out what the template function is named.
	$theme_function = 'template_' . $sub_template_name;
	if (function_exists($theme_function))
		$theme_function();
	elseif ($fatal === false)
		fatal_lang_error('theme_template_error', 'template', array((string) $sub_template_name));
	elseif ($fatal !== 'ignore')
		die(log_error(sprintf(isset($txt['theme_template_error']) ? $txt['theme_template_error'] : 'Unable to load the %s sub template!', (string) $sub_template_name), 'template'));

	// Are we showing debugging for templates?  Just make sure not to do it before the doctype...
	if (allowedTo('admin_forum') && isset($_REQUEST['debug']) && !in_array($sub_template_name, array('init', 'main_below')) && ob_get_length() > 0 && !isset($_REQUEST['xml']))
	{
		echo '
<div style="font-size: 8pt; border: 1px dashed red; background: orange; text-align: center; font-weight: bold;">---- ', $sub_template_name, ' ends ----</div>';
	}
}

/**
 * Add a CSS file for output later
 *
 * @param string $filename
 * @param array $options
 */
function loadCSSFile($filename, $options = array())
{
	global $settings, $context;

	if (strpos($filename, 'http://') === false || !empty($options['local']))
		$filename = $settings['theme_url'] . '/' . $filename;

	$context['css_files'][$filename] = $options;
}

/**
 * Add a Javascript file for output later
 *
 * @param string $filename
 * @param array $options, possible parameters:
 * 	- local (true/false): define if the file is local
 * 	- default_theme (true/false): force use of default theme url
 * 	- defer (true/false): define if the file should be load in head or before the closing <html> tag
 */
function loadJavascriptFile($filename, $options = array())
{
	global $settings, $context;

	$theme = !empty($options['default_theme']) ? 'default_theme_url' : 'theme_url';

	if (strpos($filename, 'http') === false || !empty($options['local']))
		$filename = $settings[$theme] . '/' . $filename;

	$context['javascript_files'][$filename] = $options;
}

/**
 * Load a language file.  Tries the current and default themes as well as the user and global languages.
 *
 * @param string $template_name
 * @param string $lang
 * @param bool $fatal = true
 * @param bool $force_reload = false
 * @return string The language actually loaded.
 */
function loadLanguage($template_name, $lang = '', $fatal = true, $force_reload = false)
{
	global $user_info, $language, $settings, $context, $modSettings;
	global $cachedir, $db_show_debug, $sourcedir, $txt;
	static $already_loaded = array();

	// Default to the user's language.
	if ($lang == '')
		$lang = isset($user_info['language']) ? $user_info['language'] : $language;

	// Do we want the English version of language file as fallback?
	if (empty($modSettings['disable_language_fallback']) && $lang != 'english')
		loadLanguage($template_name, 'english', false);

	if (!$force_reload && isset($already_loaded[$template_name]) && $already_loaded[$template_name] == $lang)
		return $lang;

	// Make sure we have $settings - if not we're in trouble and need to find it!
	if (empty($settings['default_theme_dir']))
	{
		require_once($sourcedir . '/ScheduledTasks.php');
		loadEssentialThemeData();
	}

	// What theme are we in?
	$theme_name = basename($settings['theme_url']);
	if (empty($theme_name))
		$theme_name = 'unknown';

	// For each file open it up and write it out!
	foreach (explode('+', $template_name) as $template)
	{
		// Obviously, the current theme is most important to check.
		$attempts = array(
			array($settings['theme_dir'], $template, $lang, $settings['theme_url']),
			array($settings['theme_dir'], $template, $language, $settings['theme_url']),
		);

		// Do we have a base theme to worry about?
		if (isset($settings['base_theme_dir']))
		{
			$attempts[] = array($settings['base_theme_dir'], $template, $lang, $settings['base_theme_url']);
			$attempts[] = array($settings['base_theme_dir'], $template, $language, $settings['base_theme_url']);
		}

		// Fall back on the default theme if necessary.
		$attempts[] = array($settings['default_theme_dir'], $template, $lang, $settings['default_theme_url']);
		$attempts[] = array($settings['default_theme_dir'], $template, $language, $settings['default_theme_url']);

		// Fall back on the English language if none of the preferred languages can be found.
		if (!in_array('english', array($lang, $language)))
		{
			$attempts[] = array($settings['theme_dir'], $template, 'english', $settings['theme_url']);
			$attempts[] = array($settings['default_theme_dir'], $template, 'english', $settings['default_theme_url']);
		}

		// Try to find the language file.
		$found = false;
		foreach ($attempts as $k => $file)
		{
			if (file_exists($file[0] . '/languages/' . $file[1] . '.' . $file[2] . '.php'))
			{
				// Include it!
				template_include($file[0] . '/languages/' . $file[1] . '.' . $file[2] . '.php');

				// Note that we found it.
				$found = true;

				break;
			}
		}

		// That couldn't be found!  Log the error, but *try* to continue normally.
		if (!$found && $fatal)
		{
			log_error(sprintf($txt['theme_language_error'], $template_name . '.' . $lang, 'template'));
			break;
		}

		// For the sake of backward compatibility
		if (!empty($txt['emails']))
		{
			foreach ($txt['emails'] as $key => $value)
			{
				$txt[$key . '_subject'] = $value['subject'];
				$txt[$key . '_body'] = $value['body'];
			}
			$txt['emails'] = array();
		}
		if (!empty($birthdayEmails))
		{
			foreach ($birthdayEmails as $key => $value)
			{
				$txtBirthdayEmails[$key . '_subject'] = $value['subject'];
				$txtBirthdayEmails[$key . '_body'] = $value['body'];
				$txtBirthdayEmails[$key . '_author'] = $value['author'];
			}
			$birthdayEmails = array();
		}
	}

	// Keep track of what we're up to soldier.
	if ($db_show_debug === true)
		$context['debug']['language_files'][] = $template_name . '.' . $lang . ' (' . $theme_name . ')';

	// Remember what we have loaded, and in which language.
	$already_loaded[$template_name] = $lang;

	// Return the language actually loaded.
	return $lang;
}

/**
 * Load a theme, by ID.
 *
 * @param int $id_theme = 0
 * @param bool $initialize = true
 */
function loadTheme($id_theme = 0, $initialize = true)
{
	global $user_info, $user_settings, $board_info, $sc, $boarddir;
	global $txt, $boardurl, $scripturl, $mbname, $modSettings, $language;
	global $context, $settings, $options, $sourcedir, $ssi_theme, $smcFunc;

	// The theme was specified by parameter.
	if (!empty($id_theme))
		$id_theme = (int) $id_theme;
	// The theme was specified by REQUEST.
	elseif (!empty($_REQUEST['theme']) && (!empty($modSettings['theme_allow']) || allowedTo('admin_forum')))
	{
		$id_theme = (int) $_REQUEST['theme'];
		$_SESSION['id_theme'] = $id_theme;
	}
	// The theme was specified by REQUEST... previously.
	elseif (!empty($_SESSION['id_theme']) && (!empty($modSettings['theme_allow']) || allowedTo('admin_forum')))
		$id_theme = (int) $_SESSION['id_theme'];
	// The theme is just the user's choice. (might use ?board=1;theme=0 to force board theme.)
	elseif (!empty($user_info['theme']) && !isset($_REQUEST['theme']) && (!empty($modSettings['theme_allow']) || allowedTo('admin_forum')))
		$id_theme = $user_info['theme'];
	// The theme was specified by the board.
	elseif (!empty($board_info['theme']))
		$id_theme = $board_info['theme'];
	// The theme is the forum's default.
	else
		$id_theme = $modSettings['theme_guests'];

	// Verify the id_theme... no foul play.
	// Always allow the board specific theme, if they are overriding.
	if (!empty($board_info['theme']) && $board_info['override_theme'])
		$id_theme = $board_info['theme'];
	// If they have specified a particular theme to use with SSI allow it to be used.
	elseif (!empty($ssi_theme) && $id_theme == $ssi_theme)
		$id_theme = (int) $id_theme;
	elseif (!empty($modSettings['knownThemes']) && !allowedTo('admin_forum'))
	{
		$themes = explode(',', $modSettings['knownThemes']);
		if (!in_array($id_theme, $themes))
			$id_theme = $modSettings['theme_guests'];
		else
			$id_theme = (int) $id_theme;
	}
	else
		$id_theme = (int) $id_theme;

	$member = empty($user_info['id']) ? -1 : $user_info['id'];

	if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2 && ($temp = cache_get_data('theme_settings-' . $id_theme . ':' . $member, 60)) != null && time() - 60 > $modSettings['settings_updated'])
	{
		$themeData = $temp;
		$flag = true;
	}
	elseif (($temp = cache_get_data('theme_settings-' . $id_theme, 90)) != null && time() - 60 > $modSettings['settings_updated'])
		$themeData = $temp + array($member => array());
	else
		$themeData = array(-1 => array(), 0 => array(), $member => array());

	if (empty($flag))
	{
		// Load variables from the current or default theme, global or this user's.
		$result = $smcFunc['db_query']('', '
			SELECT variable, value, id_member, id_theme
			FROM {db_prefix}themes
			WHERE id_member' . (empty($themeData[0]) ? ' IN (-1, 0, {int:id_member})' : ' = {int:id_member}') . '
				AND id_theme' . ($id_theme == 1 ? ' = {int:id_theme}' : ' IN ({int:id_theme}, 1)'),
			array(
				'id_theme' => $id_theme,
				'id_member' => $member,
			)
		);
		// Pick between $settings and $options depending on whose data it is.
		while ($row = $smcFunc['db_fetch_assoc']($result))
		{
			// There are just things we shouldn't be able to change as members.
			if ($row['id_member'] != 0 && in_array($row['variable'], array('actual_theme_url', 'actual_images_url', 'base_theme_dir', 'base_theme_url', 'default_images_url', 'default_theme_dir', 'default_theme_url', 'default_template', 'images_url', 'number_recent_posts', 'smiley_sets_default', 'theme_dir', 'theme_id', 'theme_layers', 'theme_templates', 'theme_url')))
				continue;

			// If this is the theme_dir of the default theme, store it.
			if (in_array($row['variable'], array('theme_dir', 'theme_url', 'images_url')) && $row['id_theme'] == '1' && empty($row['id_member']))
				$themeData[0]['default_' . $row['variable']] = $row['value'];

			// If this isn't set yet, is a theme option, or is not the default theme..
			if (!isset($themeData[$row['id_member']][$row['variable']]) || $row['id_theme'] != '1')
				$themeData[$row['id_member']][$row['variable']] = substr($row['variable'], 0, 5) == 'show_' ? $row['value'] == '1' : $row['value'];
		}
		$smcFunc['db_free_result']($result);

		if (!empty($themeData[-1]))
			foreach ($themeData[-1] as $k => $v)
			{
				if (!isset($themeData[$member][$k]))
					$themeData[$member][$k] = $v;
			}

		if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
			cache_put_data('theme_settings-' . $id_theme . ':' . $member, $themeData, 60);
		// Only if we didn't already load that part of the cache...
		elseif (!isset($temp))
			cache_put_data('theme_settings-' . $id_theme, array(-1 => $themeData[-1], 0 => $themeData[0]), 90);
	}

	$settings = $themeData[0];
	$options = $themeData[$member];

	$settings['theme_id'] = $id_theme;

	$settings['actual_theme_url'] = $settings['theme_url'];
	$settings['actual_images_url'] = $settings['images_url'];
	$settings['actual_theme_dir'] = $settings['theme_dir'];

	$settings['template_dirs'] = array();
	// This theme first.
	$settings['template_dirs'][] = $settings['theme_dir'];

	// Based on theme (if there is one).
	if (!empty($settings['base_theme_dir']))
		$settings['template_dirs'][] = $settings['base_theme_dir'];

	// Lastly the default theme.
	if ($settings['theme_dir'] != $settings['default_theme_dir'])
		$settings['template_dirs'][] = $settings['default_theme_dir'];

	if (!$initialize)
		return;

	// Check to see if they're accessing it from the wrong place.
	if (isset($_SERVER['HTTP_HOST']) || isset($_SERVER['SERVER_NAME']))
	{
		$detected_url = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on' ? 'https://' : 'http://';
		$detected_url .= empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] . (empty($_SERVER['SERVER_PORT']) || $_SERVER['SERVER_PORT'] == '80' ? '' : ':' . $_SERVER['SERVER_PORT']) : $_SERVER['HTTP_HOST'];
		$temp = preg_replace('~/' . basename($scripturl) . '(/.+)?$~', '', strtr(dirname($_SERVER['PHP_SELF']), '\\', '/'));
		if ($temp != '/')
			$detected_url .= $temp;
	}
	if (isset($detected_url) && $detected_url != $boardurl)
	{
		// Try #1 - check if it's in a list of alias addresses.
		if (!empty($modSettings['forum_alias_urls']))
		{
			$aliases = explode(',', $modSettings['forum_alias_urls']);

			foreach ($aliases as $alias)
			{
				// Rip off all the boring parts, spaces, etc.
				if ($detected_url == trim($alias) || strtr($detected_url, array('http://' => '', 'https://' => '')) == trim($alias))
					$do_fix = true;
			}
		}

		// Hmm... check #2 - is it just different by a www?  Send them to the correct place!!
		if (empty($do_fix) && strtr($detected_url, array('://' => '://www.')) == $boardurl && (empty($_GET) || count($_GET) == 1) && SMF != 'SSI')
		{
			// Okay, this seems weird, but we don't want an endless loop - this will make $_GET not empty ;).
			if (empty($_GET))
				redirectexit('wwwRedirect');
			else
			{
				list ($k, $v) = each($_GET);

				if ($k != 'wwwRedirect')
					redirectexit('wwwRedirect;' . $k . '=' . $v);
			}
		}

		// #3 is just a check for SSL...
		if (strtr($detected_url, array('https://' => 'http://')) == $boardurl)
			$do_fix = true;

		// Okay, #4 - perhaps it's an IP address?  We're gonna want to use that one, then. (assuming it's the IP or something...)
		if (!empty($do_fix) || preg_match('~^http[s]?://(?:[\d\.:]+|\[[\d:]+\](?::\d+)?)(?:$|/)~', $detected_url) == 1)
		{
			// Caching is good ;).
			$oldurl = $boardurl;

			// Fix $boardurl and $scripturl.
			$boardurl = $detected_url;
			$scripturl = strtr($scripturl, array($oldurl => $boardurl));
			$_SERVER['REQUEST_URL'] = strtr($_SERVER['REQUEST_URL'], array($oldurl => $boardurl));

			// Fix the theme urls...
			$settings['theme_url'] = strtr($settings['theme_url'], array($oldurl => $boardurl));
			$settings['default_theme_url'] = strtr($settings['default_theme_url'], array($oldurl => $boardurl));
			$settings['actual_theme_url'] = strtr($settings['actual_theme_url'], array($oldurl => $boardurl));
			$settings['images_url'] = strtr($settings['images_url'], array($oldurl => $boardurl));
			$settings['default_images_url'] = strtr($settings['default_images_url'], array($oldurl => $boardurl));
			$settings['actual_images_url'] = strtr($settings['actual_images_url'], array($oldurl => $boardurl));

			// And just a few mod settings :).
			$modSettings['smileys_url'] = strtr($modSettings['smileys_url'], array($oldurl => $boardurl));
			$modSettings['avatar_url'] = strtr($modSettings['avatar_url'], array($oldurl => $boardurl));

			// Clean up after loadBoard().
			if (isset($board_info['moderators']))
			{
				foreach ($board_info['moderators'] as $k => $dummy)
				{
					$board_info['moderators'][$k]['href'] = strtr($dummy['href'], array($oldurl => $boardurl));
					$board_info['moderators'][$k]['link'] = strtr($dummy['link'], array('"' . $oldurl => '"' . $boardurl));
				}
			}
			foreach ($context['linktree'] as $k => $dummy)
				$context['linktree'][$k]['url'] = strtr($dummy['url'], array($oldurl => $boardurl));
		}
	}
	// Set up the contextual user array.
	$context['user'] = array(
		'id' => $user_info['id'],
		'is_logged' => !$user_info['is_guest'],
		'is_guest' => &$user_info['is_guest'],
		'is_admin' => &$user_info['is_admin'],
		'is_mod' => &$user_info['is_mod'],
		// A user can mod if they have permission to see the mod center, or they are a board/group/approval moderator.
		'can_mod' => allowedTo('access_mod_center') || (!$user_info['is_guest'] && ($user_info['mod_cache']['gq'] != '0=1' || $user_info['mod_cache']['bq'] != '0=1' || ($modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap'])))),
		'username' => $user_info['username'],
		'language' => $user_info['language'],
		'email' => $user_info['email'],
		'ignoreusers' => $user_info['ignoreusers'],
	);
	if (!$context['user']['is_guest'])
		$context['user']['name'] = $user_info['name'];
	elseif ($context['user']['is_guest'] && !empty($txt['guest_title']))
		$context['user']['name'] = $txt['guest_title'];

	// Determine the current smiley set.
	$user_info['smiley_set'] = (!in_array($user_info['smiley_set'], explode(',', $modSettings['smiley_sets_known'])) && $user_info['smiley_set'] != 'none') || empty($modSettings['smiley_sets_enable']) ? (!empty($settings['smiley_sets_default']) ? $settings['smiley_sets_default'] : $modSettings['smiley_sets_default']) : $user_info['smiley_set'];
	$context['user']['smiley_set'] = $user_info['smiley_set'];

	// Some basic information...
	if (!isset($context['html_headers']))
		$context['html_headers'] = '';

	$context['menu_separator'] = !empty($settings['use_image_buttons']) ? ' ' : ' | ';
	$context['session_var'] = $_SESSION['session_var'];
	$context['session_id'] = $_SESSION['session_value'];
	$context['forum_name'] = $mbname;
	$context['forum_name_html_safe'] = $smcFunc['htmlspecialchars']($context['forum_name']);
	$context['header_logo_url_html_safe'] = empty($settings['header_logo_url']) ? '' : $smcFunc['htmlspecialchars']($settings['header_logo_url']);
	$context['current_action'] = isset($_REQUEST['action']) ? $_REQUEST['action'] : null;
	$context['current_subaction'] = isset($_REQUEST['sa']) ? $_REQUEST['sa'] : null;
	$context['can_register'] = empty($modSettings['registration_method']) || $modSettings['registration_method'] != 3;
	if (isset($modSettings['load_average']))
		$context['load_average'] = $modSettings['load_average'];

	// Set some permission related settings.
	$context['show_login_bar'] = $user_info['is_guest'] && !empty($modSettings['enableVBStyleLogin']);

	// This determines the server... not used in many places, except for login fixing.
	$context['server'] = array(
		'is_iis' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') !== false,
		'is_apache' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') !== false,
		'is_litespeed' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'LiteSpeed') !== false,
		'is_lighttpd' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'lighttpd') !== false,
		'is_nginx' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false,
		'is_cgi' => isset($_SERVER['SERVER_SOFTWARE']) && strpos(php_sapi_name(), 'cgi') !== false,
		'is_windows' => strpos(PHP_OS, 'WIN') === 0,
		'iso_case_folding' => ord(strtolower(chr(138))) === 154,
	);
	// A bug in some versions of IIS under CGI (older ones) makes cookie setting not work with Location: headers.
	$context['server']['needs_login_fix'] = $context['server']['is_cgi'] && $context['server']['is_iis'];

	// Detect the browser. This is separated out because it's also used in attachment downloads
	detectBrowser();

	// Set the top level linktree up.
	array_unshift($context['linktree'], array(
		'url' => $scripturl,
		'name' => $context['forum_name_html_safe']
	));

	// This allows sticking some HTML on the page output - useful for controls.
	$context['insert_after_template'] = '';

	if (!isset($txt))
		$txt = array();
	$simpleActions = array(
		'findmember',
		'helpadmin',
		'printpage',
		'quotefast',
	);

	$context['javascript_files'] = array();
	$context['css_files'] = array();

	// Wireless mode?  Load up the wireless stuff.
	if (WIRELESS)
	{
		$context['template_layers'] = array(WIRELESS_PROTOCOL);
		loadTemplate('Wireless');
		loadLanguage('Wireless+index+Modifications');
	}
	// Output is fully XML, so no need for the index template.
	elseif (isset($_REQUEST['xml']))
	{
		loadLanguage('index+Modifications');
		loadTemplate('Xml');
		$context['template_layers'] = array();
	}
	// These actions don't require the index template at all.
	elseif (!empty($_REQUEST['action']) && in_array($_REQUEST['action'], $simpleActions))
	{
		loadLanguage('index+Modifications');
		$context['template_layers'] = array();
	}
	else
	{
		// Custom templates to load, or just default?
		if (isset($settings['theme_templates']))
			$templates = explode(',', $settings['theme_templates']);
		else
			$templates = array('index');

		// Load each template...
		foreach ($templates as $template)
			loadTemplate($template);

		// ...and attempt to load their associated language files.
		$required_files = implode('+', array_merge($templates, array('Modifications')));
		loadLanguage($required_files, '', false);

		// Custom template layers?
		if (isset($settings['theme_layers']))
			$context['template_layers'] = explode(',', $settings['theme_layers']);
		else
			$context['template_layers'] = array('html', 'body');
	}

	// Initialize the theme.
	loadSubTemplate('init', 'ignore');

	// Load the compatibility stylesheet if the theme hasn't been updated for 2.0 RC2 (yet).
	if (isset($settings['theme_version']) && (version_compare($settings['theme_version'], '2.0 RC2', '<') || strpos($settings['theme_version'], '2.0 Beta') !== false))
		loadTemplate(false, 'compat');

	// Guests may still need a name.
	if ($context['user']['is_guest'] && empty($context['user']['name']))
		$context['user']['name'] = $txt['guest_title'];

	// Any theme-related strings that need to be loaded?
	if (!empty($settings['require_theme_strings']))
		loadLanguage('ThemeStrings', '', false);

	// We allow theme variants, because we're cool.
	$context['theme_variant'] = '';
	$context['theme_variant_url'] = '';
	if (!empty($settings['theme_variants']))
	{
		// Overriding - for previews and that ilk.
		if (!empty($_REQUEST['variant']))
			$_SESSION['id_variant'] = $_REQUEST['variant'];
		// User selection?
		if (empty($settings['disable_user_variant']) || allowedTo('admin_forum'))
			$context['theme_variant'] = !empty($_SESSION['id_variant']) ? $_SESSION['id_variant'] : (!empty($options['theme_variant']) ? $options['theme_variant'] : '');
		// If not a user variant, select the default.
		if ($context['theme_variant'] == '' || !in_array($context['theme_variant'], $settings['theme_variants']))
			$context['theme_variant'] = !empty($settings['default_variant']) && in_array($settings['default_variant'], $settings['theme_variants']) ? $settings['default_variant'] : $settings['theme_variants'][0];
	
		// Do this to keep things easier in the templates.
		$context['theme_variant'] = '_' . $context['theme_variant'];
		$context['theme_variant_url'] = $context['theme_variant'] . '/';
	}

	// Let's be compatible with old themes!
	if (!function_exists('template_html_above') && in_array('html', $context['template_layers']))
		$context['template_layers'] = array('main');

	// Allow overriding the board wide time/number formats.
	if (empty($user_settings['time_format']) && !empty($txt['time_format']))
		$user_info['time_format'] = $txt['time_format'];
	$txt['number_format'] = empty($txt['number_format']) ? empty($modSettings['number_format']) ? '' : $modSettings['number_format'] : $txt['number_format'];

	if (isset($settings['use_default_images']) && $settings['use_default_images'] == 'always')
	{
		$settings['theme_url'] = $settings['default_theme_url'];
		$settings['images_url'] = $settings['default_images_url'];
		$settings['theme_dir'] = $settings['default_theme_dir'];
	}
	// Make a special URL for the language.
	$settings['lang_images_url'] = $settings['images_url'] . '/' . (!empty($txt['image_lang']) ? $txt['image_lang'] : $user_info['language']);

	// Set the character set from the template.
	$context['character_set'] = empty($modSettings['global_character_set']) ? $txt['lang_character_set'] : $modSettings['global_character_set'];
	$context['utf8'] = $context['character_set'] === 'UTF-8';
	$context['right_to_left'] = !empty($txt['lang_rtl']);

	$context['tabindex'] = 1;

	// Fix font size with HTML 4.01, etc.
	if (isset($settings['doctype']))
		$context['browser']['needs_size_fix'] |= $settings['doctype'] == 'html' && isBrowser('ie6');

	// Compatibility.
	if (!isset($settings['theme_version']))
		$modSettings['memberCount'] = $modSettings['totalMembers'];

	// This allows us to change the way things look for the admin.
	$context['admin_features'] = isset($modSettings['admin_features']) ? explode(',', $modSettings['admin_features']) : array('cd,cp,k,w,rg,ml,pm');

	// If we think we have mail to send, let's offer up some possibilities... robots get pain (Now with scheduled task support!)
	if ((!empty($modSettings['mail_next_send']) && $modSettings['mail_next_send'] < time() && empty($modSettings['mail_queue_use_cron'])) || empty($modSettings['next_task_time']) || $modSettings['next_task_time'] < time())
	{
		if (isBrowser('possibly_robot'))
		{
			// @todo Maybe move this somewhere better?!
			require_once($sourcedir . '/ScheduledTasks.php');

			// What to do, what to do?!
			if (empty($modSettings['next_task_time']) || $modSettings['next_task_time'] < time())
				AutoTask();
			else
				ReduceMailQueue();
		}
		else
		{
			$type = empty($modSettings['next_task_time']) || $modSettings['next_task_time'] < time() ? 'task' : 'mailq';
			$ts = $type == 'mailq' ? $modSettings['mail_next_send'] : $modSettings['next_task_time'];

			$context['html_headers'] .= '
	<script type="text/javascript">
		function smfAutoTask()
		{
			var tempImage = new Image();
			tempImage.src = smf_scripturl + "?scheduled=' . $type . ';ts=' . $ts . '";
		}
		window.setTimeout("smfAutoTask();", 1);
	</script>';
		}
	}

	// Any files to include at this point?
	if (!empty($modSettings['integrate_theme_include']))
	{
		$theme_includes = explode(',', $modSettings['integrate_theme_include']);
		foreach ($theme_includes as $include)
		{
			$include = strtr(trim($include), array('$boarddir' => $boarddir, '$sourcedir' => $sourcedir, '$themedir' => $settings['theme_dir']));
			if (file_exists($include))
				require_once($include);
		}
	}

	// Call load theme integration functions.
	call_integration_hook('integrate_load_theme');

	// We are ready to go.
	$context['theme_loaded'] = true;
}
