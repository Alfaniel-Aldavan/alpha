<?php

/**
 * Try to retrieve a cache entry. On failure, call the appropriate function.
 *
 * @param string $key
 * @param string $file
 * @param string $function
 * @param array $params
 * @param int $level = 1
 * @return string
 */
function cache_quick_get($key, $file, $function, $params, $level = 1)
{
	global $modSettings, $sourcedir;

	// @todo Why are we doing this if caching is disabled?

	if (function_exists('call_integration_hook'))
		call_integration_hook('pre_cache_quick_get', array(&$key, &$file, &$function, &$params, &$level));

	/* Refresh the cache if either:
		1. Caching is disabled.
		2. The cache level isn't high enough.
		3. The item has not been cached or the cached item expired.
		4. The cached item has a custom expiration condition evaluating to true.
		5. The expire time set in the cache item has passed (needed for Zend).
	*/
	if (empty($modSettings['cache_enable']) || $modSettings['cache_enable'] < $level || !is_array($cache_block = cache_get_data($key, 3600)) || (!empty($cache_block['refresh_eval']) && eval($cache_block['refresh_eval'])) || (!empty($cache_block['expires']) && $cache_block['expires'] < time()))
	{
		require_once($sourcedir . '/' . $file);
		$cache_block = call_user_func_array($function, $params);

		if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= $level)
			cache_put_data($key, $cache_block, $cache_block['expires'] - time());
	}

	// Some cached data may need a freshening up after retrieval.
	if (!empty($cache_block['post_retri_eval']))
		eval($cache_block['post_retri_eval']);

	if (function_exists('call_integration_hook'))
		call_integration_hook('post_cache_quick_get', array(&$cache_block));

	return $cache_block['data'];
}

/**
 * Puts value in the cache under key for ttl seconds.
 *
 * - It may "miss" so shouldn't be depended on 
 * - Uses the cahce engine chosen in the ACP and saved in settings.php
 * - It supports:
 *     Turck MMCache: http://turck-mmcache.sourceforge.net/index_old.html#api
 *     Xcache: http://xcache.lighttpd.net/wiki/XcacheApi
 *     memcache: http://www.php.net/memcache
 *     APC: http://www.php.net/apc
 *     eAccelerator: http://bart.eaccelerator.net/doc/phpdoc/
 *     Zend: http://files.zend.com/help/Zend-Platform/output_cache_functions.htm
 *     Zend: http://files.zend.com/help/Zend-Platform/zend_cache_functions.htm
 *
 * @param string $key
 * @param mixed $value
 * @param int $ttl = 120
 */
function cache_put_data($key, $value, $ttl = 120)
{
	global $boardurl, $sourcedir, $modSettings, $memcached;
	global $cache_hits, $cache_count, $db_show_debug, $cachedir;
	global $cache_accelerator, $cache_enable;

	if (empty($cache_enable))
		return;

	$cache_count = isset($cache_count) ? $cache_count + 1 : 1;
	if (isset($db_show_debug) && $db_show_debug === true)
	{
		$cache_hits[$cache_count] = array('k' => $key, 'd' => 'put', 's' => $value === null ? 0 : strlen(serialize($value)));
		$st = microtime();
	}

	$key = md5($boardurl . filemtime($sourcedir . '/Load.php')) . '-SMF-' . strtr($key, ':/', '-_');
	$value = $value === null ? null : serialize($value);

	switch ($cache_accelerator)
	{
		case 'memcached':
			// The simple yet efficient memcached.
			if (function_exists('memcached_set') || function_exists('memcache_set') && isset($modSettings['cache_memcached']) && trim($modSettings['cache_memcached']) != '')
			{
				// Not connected yet?
				if (empty($memcached))
					get_memcached_server();
				if (!$memcached)
					return;

				memcache_set($memcached, $key, $value, 0, $ttl);
			}
			break;
		case 'eaccelerator':
			// eAccelerator...
			if (function_exists('eaccelerator_put'))
			{
				if (mt_rand(0, 10) == 1)
					eaccelerator_gc();

				if ($value === null)
					@eaccelerator_rm($key);
				else
					eaccelerator_put($key, $value, $ttl);
			}
			break;
		case 'mmcache':
			// Turck MMCache?
			if (function_exists('mmcache_put'))
			{
				if (mt_rand(0, 10) == 1)
					mmcache_gc();

				if ($value === null)
					@mmcache_rm($key);
				else
				{
					mmcache_lock($key);
					mmcache_put($key, $value, $ttl);
					mmcache_unlock($key);
				}
			}
			break;
		case 'apc':
			// Alternative PHP Cache, ahoy!
			if (function_exists('apc_store'))
			{
				// An extended key is needed to counteract a bug in APC.
				if ($value === null)
					apc_delete($key . 'smf');
				else
					apc_store($key . 'smf', $value, $ttl);
			}
			break;
		case 'zend':
			// Zend Platform/ZPS/etc.
			if (function_exists('zend_shm_cache_store'))
				zend_shm_cache_store('SMF::' . $key, $value, $ttl);
			elseif (function_exists('output_cache_put'))
				output_cache_put($key, $value);
			break;
		case 'xcache':
			if (function_exists('xcache_set') && ini_get('xcache.var_size') > 0)
			{
				if ($value === null)
					xcache_unset($key);
				else
					xcache_set($key, $value, $ttl);
			}
			break;
		default:
			// Otherwise custom cache?
			if ($value === null)
				@unlink($cachedir . '/data_' . $key . '.php');
			else
			{
				$cache_data = '<' . '?' . 'php if (!defined(\'SMF\')) die; if (' . (time() + $ttl) . ' < time()) $expired = true; else{$expired = false; $value = \'' . addcslashes($value, '\\\'') . '\';}' . '?' . '>';

				// Write out the cache file, check that the cache write was successful; all the data must be written
				// If it fails due to low diskspace, or other, remove the cache file
				if (file_put_contents($cachedir . '/data_' . $key . '.php', $cache_data, LOCK_EX) !== strlen($cache_data))
					@unlink($cachedir . '/data_' . $key . '.php');
			}
			break;
	}

	if (function_exists('call_integration_hook'))
		call_integration_hook('cache_put_data', array(&$key, &$value, &$ttl));

	if (isset($db_show_debug) && $db_show_debug === true)
		$cache_hits[$cache_count]['t'] = array_sum(explode(' ', microtime())) - array_sum(explode(' ', $st));
}

/**
 * Gets the value from the cache specified by key, so long as it is not older than ttl seconds.
 * - It may often "miss", so shouldn't be depended on.
 * - It supports the same as cache_put_data().
 *
 * @param string $key
 * @param int $ttl = 120
 * @return string
 */
function cache_get_data($key, $ttl = 120)
{
	global $boardurl, $sourcedir, $modSettings, $memcached;
	global $cache_hits, $cache_count, $db_show_debug, $cachedir;
	global $cache_accelerator, $cache_enable;

	if (empty($cache_enable))
		return;

	$cache_count = isset($cache_count) ? $cache_count + 1 : 1;
	if (isset($db_show_debug) && $db_show_debug === true)
	{
		$cache_hits[$cache_count] = array('k' => $key, 'd' => 'get');
		$st = microtime();
	}

	$key = md5($boardurl . filemtime($sourcedir . '/Load.php')) . '-SMF-' . strtr($key, ':/', '-_');

	switch ($cache_accelerator)
	{
		case 'memcache':
			// Okay, let's go for it memcached!
			if ((function_exists('memcache_get') || function_exists('memcached_get')) && isset($modSettings['cache_memcached']) && trim($modSettings['cache_memcached']) != '')
			{
				// Not connected yet?
				if (empty($memcached))
					get_memcached_server();
				if (!$memcached)
					return null;

				$value = (function_exists('memcache_get')) ? memcache_get($cache['connection'], $key) : memcached_get($cache['connection'], $key);
			}
			break;
		case 'eaccelerator':
			// Again, eAccelerator.
			if (function_exists('eaccelerator_get'))
				$value = eaccelerator_get($key);
			break;
		case 'mmcache':
			// The older, but ever-stable, Turck MMCache...
			if (function_exists('mmcache_get'))
				$value = mmcache_get($key);
			break;
		case 'apc':
			// This is the free APC from PECL.
			if (function_exists('apc_fetch'))
				$value = apc_fetch($key . 'smf');
			break;
		case 'zend':
			// Zend's pricey stuff.
			if (function_exists('zend_shm_cache_fetch'))
				$value = zend_shm_cache_fetch('SMF::' . $key, $ttl);
			elseif (function_exists('output_cache_get'))
				$value = output_cache_get($key, $ttl);
			break;
		case 'xcache':
			if (function_exists('xcache_get') && ini_get('xcache.var_size') > 0)
				$value = xcache_get($key);
			break;
		default:
			// Otherwise it's SMF data!
			if (file_exists($cachedir . '/data_' . $key . '.php') && filesize($cachedir . '/data_' . $key . '.php') > 10)
			{
				// php will cache file_exists et all, we can't 100% depend on its results so proceed with caution
				@include($cachedir . '/data_' . $key . '.php');
				if (!empty($expired) && isset($value))
				{
					@unlink($cachedir . '/data_' . $key . '.php');
					unset($value);
				}
			}
			break;
	}

	if (isset($db_show_debug) && $db_show_debug === true)
	{
		$cache_hits[$cache_count]['t'] = array_sum(explode(' ', microtime())) - array_sum(explode(' ', $st));
		$cache_hits[$cache_count]['s'] = isset($value) ? strlen($value) : 0;
	}

	if (function_exists('call_integration_hook'))
		call_integration_hook('cache_get_data', array(&$key, &$ttl, &$value));

	return empty($value) ? null : @unserialize($value);
}

/**
 * Get memcache servers.
 *
 * - This function is used by cache_get_data() and cache_put_data().
 * - It attempts to connect to a random server in the cache_memcached setting.
 * - It recursively calls itself up to $level times.
 *
 * @param int $level = 3
 */
function get_memcached_server($level = 3)
{
	global $modSettings, $memcached, $db_persist, $cache_memcached;

	$servers = explode(',', $cache_memcached);
	$server = explode(':', trim($servers[array_rand($servers)]));
	$cache = (function_exists('memcache_get')) ? 'memcache' : ((function_exists('memcached_get') ? 'memcached' : ''));

	// Don't try more times than we have servers!
	$level = min(count($servers), $level);

	// Don't wait too long: yes, we want the server, but we might be able to run the query faster!
	if (empty($db_persist))
	{
		if ($cache === 'memcached')
			$memcached = memcached_connect($server[0], empty($server[1]) ? 11211 : $server[1]);
		if ($cache === 'memcache')
			$memcached = memcache_connect($server[0], empty($server[1]) ? 11211 : $server[1]);
	}
	else
	{
		if ($cache === 'memcached')
			$memcached = memcached_pconnect($server[0], empty($server[1]) ? 11211 : $server[1]);
		if ($cache === 'memcache')
			$memcached = memcache_pconnect($server[0], empty($server[1]) ? 11211 : $server[1]);
	}

	if (!$memcached && $level > 0)
		get_memcached_server($level - 1);
}
