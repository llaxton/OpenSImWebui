<?php
/**
 * Wraps the session control functions and the `$_SESSION` superglobal for a more consistent and safer API
 * 
 * A `Cannot send session cache limiter` warning will be triggered if ::open(),
 * ::add(), ::clear(), ::delete(), ::get() or ::set() is called after output has
 * been sent to the browser. To prevent such a warning, explicitly call ::open()
 * before generating any output.
 * 
 * @copyright  Copyright (c) 2014-2019 Alan Johnston, Velus Universe Ltd
 * @author     Alan Johnston [aj] <alan.johnston@velusuniverse.co.uk>
 * @author     Alan Johnston, Velus Universe Ltd [aj-vu] <alan.johnston@velusuniverse.co.uk>
 * @license    http://veluslib.opensource.velusuniverse.com/license
 * 
 * @package    Velus Lib
 * 
 * @version    0.0.1b
 * @changes    0.0.1b    The initial implementation [aj, 2014-12-13]
 * 
 * @link       http://veluslib.opensource.velusuniverse.com/vSession
 */
class vSession
{
	// The following constants allow for nice looking callbacks to static methods
	const add               = 'vSession::add';
	const clear             = 'vSession::clear';
	const close             = 'vSession::close';
	const closeCache        = 'vSession::closeCache';
	const delete            = 'vSession::delete';
	const destroy           = 'vSession::destroy';
	const destroyCache      = 'vSession::destroyCache';
	const enablePersistence = 'vSession::enablePersistence';
	const gcCache           = 'vSession::gcCache';
	const get               = 'vSession::get';
	const ignoreSubdomain   = 'vSession::ignoreSubdomain';
	const open              = 'vSession::open';
	const openCache         = 'vSession::openCache';
	const readCache         = 'vSession::readCache';
	const regenerateID      = 'vSession::regenerateID';
	const reset             = 'vSession::reset';
	const set               = 'vSession::set';
	const setBackend        = 'vSession::setBackend';
	const setLength         = 'vSession::setLength';
	const setPath           = 'vSession::setPath';
	const writeCache        = 'vSession::writeCache';
	
	
	/**
	 * The vCache backend to use for the session
	 * 
	 * @var vCache
	 */
	static private $backend = NULL;

	/**
	 * The key prefix to use when saving the session to an vCache
	 *
	 * @var string
	 */
	static private $key_prefix = '';

	/**
	 * The length for a normal session
	 * 
	 * @var integer
	 */
	static private $normal_timespan = NULL;
	
	/**
	 * The name of the old session module to revent to when vSession is closed
	 * 
	 * @var string
	 */
	static private $old_session_module_name = NULL;

	/**
	 * If the session is open
	 * 
	 * @var boolean
	 */
	static private $open = FALSE;
	
	/**
	 * The length for a persistent session cookie - one that survives browser restarts
	 * 
	 * @var integer
	 */
	static private $persistent_timespan = NULL;
	
	/**
	 * If the session ID was regenerated during this script
	 * 
	 * @var boolean
	 */
	static private $regenerated = FALSE;
	
	
	/**
	 * Adds a value to an already-existing array value, or to a new array value
	 *
	 * @param  string  $key        The name to access the array under - array elements can be modified via `[sub-key]` syntax, and thus `[` and `]` can not be used in key names
	 * @param  mixed   $value      The value to add to the array
	 * @param  boolean $beginning  If the value should be added to the beginning
	 * @return void
	 */
	static public function add($key, $value, $beginning=FALSE)
	{
		self::open();
		$tip =& $_SESSION;
		
		if ($bracket_pos = strpos($key, '[')) {
			$original_key      = $key;
			$array_dereference = substr($key, $bracket_pos);
			$key               = substr($key, 0, $bracket_pos);
			
			preg_match_all('#(?<=\[)[^\[\]]+(?=\])#', $array_dereference, $array_keys, PREG_SET_ORDER);
			$array_keys = array_map('current', $array_keys);
			array_unshift($array_keys, $key);
			
			foreach (array_slice($array_keys, 0, -1) as $array_key) {
				if (!isset($tip[$array_key])) {
					$tip[$array_key] = array();
				} elseif (!is_array($tip[$array_key])) {
					throw new vProgrammerException(
						'%1$s was called for the key, %2$s, which is not an array',
						__CLASS__ . '::add()',
						$original_key
					);
				}
				$tip =& $tip[$array_key];
			}
			$key = end($array_keys);
		}
		
		
		if (!isset($tip[$key])) {
			$tip[$key] = array();
		} elseif (!is_array($tip[$key])) {
			throw new vProgrammerException(
				'%1$s was called for the key, %2$s, which is not an array',
				__CLASS__ . '::add()',
				$key
			);
		}
		
		if ($beginning) {
			array_unshift($tip[$key], $value);
		} else {
			$tip[$key][] = $value;
		}
	}
	
	
	/**
	 * Removes all session values with the provided prefix
	 * 
	 * This method will not remove session variables used by this class, which
	 * are prefixed with `vSession::`.
	 * 
	 * @param  string $prefix  The prefix to clear all session values for
	 * @return void
	 */
	static public function clear($prefix=NULL)
	{
		self::open();
		
		$session_type    = $_SESSION['vSession::type'];
		$session_expires = $_SESSION['vSession::expires'];
		
		if ($prefix) {
			foreach ($_SESSION as $key => $value) {
				if (strpos($key, $prefix) === 0) {
					unset($_SESSION[$key]);
				}
			}
		} else {
			$_SESSION = array();		
		}
		
		$_SESSION['vSession::type']    = $session_type;
		$_SESSION['vSession::expires'] = $session_expires;
	}
	
	
	/**
	 * Closes the session for writing, allowing other pages to open the session
	 * 
	 * @return void
	 */
	static public function close()
	{
		if (!self::$open) { return; }
		
		session_write_close();
		unset($_SESSION);
		self::$open = FALSE;
		if (self::$old_session_module_name) {
			session_module_name(self::$old_session_module_name);
		}
	}


	/**
	 * Callback to close the session
	 * 
	 * @internal
	 *
	 * @return boolean  If the operation succeeded
	 */
	static public function closeCache()
	{
		return TRUE;
	}
	
	
	/**
	 * Deletes a value from the session
	 * 
	 * @param  string $key            The key of the value to delete - array elements can be modified via `[sub-key]` syntax, and thus `[` and `]` can not be used in key names
	 * @param  mixed  $default_value  The value to return if the `$key` is not set
	 * @return mixed  The value of the `$key` that was deleted
	 */
	static public function delete($key, $default_value=NULL)
	{
		self::open();
		
		$value = $default_value;
		
		if ($bracket_pos = strpos($key, '[')) {
			$original_key      = $key;
			$array_dereference = substr($key, $bracket_pos);
			$key               = substr($key, 0, $bracket_pos);
			
			if (!isset($_SESSION[$key])) {
				return $value;
			}
			
			preg_match_all('#(?<=\[)[^\[\]]+(?=\])#', $array_dereference, $array_keys, PREG_SET_ORDER);
			$array_keys = array_map('current', $array_keys);
			
			$tip =& $_SESSION[$key];
			
			foreach (array_slice($array_keys, 0, -1) as $array_key) {
				if (!isset($tip[$array_key])) {
					return $value;
				} elseif (!is_array($tip[$array_key])) {
					throw new vProgrammerException(
						'%1$s was called for an element, %2$s, which is not an array',
						__CLASS__ . '::delete()',
						$original_key
					);
				}
				$tip =& $tip[$array_key];
			}
			
			$key = end($array_keys);
			
		} else {
			$tip =& $_SESSION;
		}
		
		if (isset($tip[$key])) {
			$value = $tip[$key];
			unset($tip[$key]);
		}
		
		return $value;
	}
	
	
	/**
	 * Destroys the session, removing all values
	 * 
	 * @return void
	 */
	static public function destroy()
	{
		self::open();
		$_SESSION = array();
		if (isset($_COOKIE[session_name()])) {
			$params = session_get_cookie_params();
			setcookie(session_name(), '', time()-43200, $params['path'], $params['domain'], $params['secure']);
		}
		session_destroy();
		self::regenerateID();
	}


	/**
	 * Callback to destroy a session
	 * 
	 * @internal
	 *
	 * @param  string $id  The session to destroy
	 * @return boolean  If the operation succeeded
	 */
	static public function destroyCache($id)
	{
		return self::$backend->delete(self::$key_prefix . $id);
	}
	
	
	/**
	 * Changed the session to use a time-based cookie instead of a session-based cookie
	 * 
	 * The length of the time-based cookie is controlled by ::setLength(). When
	 * this method is called, a time-based cookie is used to store the session
	 * ID. This means the session can persist browser restarts. Normally, a
	 * session-based cookie is used, which is wiped when a browser restart
	 * occurs.
	 * 
	 * This method should be called during the login process and will normally
	 * be controlled by a checkbox or similar where the user can indicate if
	 * they want to stay logged in for an extended period of time.
	 * 
	 * @return void
	 */
	static public function enablePersistence()
	{
		if (self::$persistent_timespan === NULL) {
			throw new vProgrammerException(
				'The method %1$s must be called with the %2$s parameter before calling %3$s',
				__CLASS__ . '::setLength()',
				'$persistent_timespan',
				__CLASS__ . '::enablePersistence()'
			);	
		}
		
		$current_params = session_get_cookie_params();
		
		$params = array(
			self::$persistent_timespan,
			$current_params['path'],
			$current_params['domain'],
			$current_params['secure']
		);
		
		call_user_func_array('session_set_cookie_params', $params);
		
		self::open();
		
		$_SESSION['vSession::type'] = 'persistent';
		
		session_regenerate_id();
		self::$regenerated = TRUE;
	}


	/**
	 * Callback to garbage-collect the session cache
	 * 
	 * @internal
	 *
	 * @return boolean  If the operation succeeded
	 */
	static public function gcCache()
	{
		self::$backend->clean();
		return TRUE;
	}
	
	
	/**
	 * Gets data from the `$_SESSION` superglobal
	 * 
	 * @param  string $key            The name to get the value for - array elements can be accessed via `[sub-key]` syntax, and thus `[` and `]` can not be used in key names
	 * @param  mixed  $default_value  The default value to use if the requested key is not set
	 * @return mixed  The data element requested
	 */
	static public function get($key, $default_value=NULL)
	{
		self::open();
		
		$array_dereference = NULL;
		if ($bracket_pos = strpos($key, '[')) {
			$array_dereference = substr($key, $bracket_pos);
			$key               = substr($key, 0, $bracket_pos);
		}
		
		if (!isset($_SESSION[$key])) {
			return $default_value;
		}
		$value = $_SESSION[$key];
		
		if ($array_dereference) {
			preg_match_all('#(?<=\[)[^\[\]]+(?=\])#', $array_dereference, $array_keys, PREG_SET_ORDER);
			$array_keys = array_map('current', $array_keys);
			foreach ($array_keys as $array_key) {
				if (!is_array($value) || !isset($value[$array_key])) {
					$value = $default_value;
					break;
				}
				$value = $value[$array_key];
			}
		}
		
		return $value;
	}
	
	
	/**
	 * Sets the session to run on the main domain, not just the specific subdomain currently being accessed
	 * 
	 * This method should be called after any calls to
	 * [http://php.net/session_set_cookie_params `session_set_cookie_params()`].
	 * 
	 * @return void
	 */
	static public function ignoreSubdomain()
	{
		if (self::$open || isset($_SESSION)) {
			throw new vProgrammerException(
				'%1$s must be called before any of %2$s, %3$s, %4$s, %5$s, %6$s, %7$s or %8$s',
				__CLASS__ . '::ignoreSubdomain()',
				__CLASS__ . '::add()',
				__CLASS__ . '::clear()',
				__CLASS__ . '::enablePersistence()',
				__CLASS__ . '::get()',
				__CLASS__ . '::open()',
				__CLASS__ . '::set()',
				'session_start()'
			);
		}
		
		$current_params = session_get_cookie_params();
		
		if (isset($_SERVER['SERVER_NAME'])) {
			$domain = $_SERVER['SERVER_NAME'];
		} elseif (isset($_SERVER['HTTP_HOST'])) {
			$domain = $_SERVER['HTTP_HOST'];
		} else {
			throw new vEnvironmentException(
				'The domain name could not be found in %1$s or %2$s. Please set one of these keys to use %3$s.',
				'$_SERVER[\'SERVER_NAME\']',
				'$_SERVER[\'HTTP_HOST\']',
				__CLASS__ . '::ignoreSubdomain()'
			);
		}
		
		$params = array(
			$current_params['lifetime'],
			$current_params['path'],
			preg_replace('#.*?([a-z0-9\\-]+\.[a-z]+)$#iD', '.\1', $domain),
			$current_params['secure']
		);
		
		call_user_func_array('session_set_cookie_params', $params);
	}
	
	
	/**
	 * Opens the session for writing, is automatically called by ::clear(), ::get() and ::set()
	 * 
	 * A `Cannot send session cache limiter` warning will be triggered if this,
	 * ::add(), ::clear(), ::delete(), ::get() or ::set() is called after output
	 * has been sent to the browser. To prevent such a warning, explicitly call
	 * this method before generating any output.
	 * 
	 * @param  boolean $cookie_only_session_id  If the session id should only be allowed via cookie - this is a security issue and should only be set to `FALSE` when absolutely necessary 
	 * @return void
	 */
	static public function open($cookie_only_session_id=TRUE)
	{
		if (self::$open) { return; }
		
		self::$open = TRUE;
		
		if (self::$normal_timespan === NULL) {
			self::$normal_timespan = ini_get('session.gc_maxlifetime');	
		}

		if (self::$backend && isset($_SESSION) && session_module_name() != 'user') {
			throw new vProgrammerException(
				'A custom backend was provided by %1$s, however the session has already been started, so it can not be used',
				__CLASS__ . '::setBackend()'
			);
		}
		
		// If the session is already open, we just piggy-back without setting options
		if (!isset($_SESSION)) {
			if ($cookie_only_session_id) {
				ini_set('session.use_cookies', 1);
				ini_set('session.use_only_cookies', 1);
			}
			// If we are using a custom backend we have to set the session handler
			if (self::$backend && session_module_name() != 'user') {
				session_set_save_handler(
					array('vSession', 'openCache'),
					array('vSession', 'closeCache'),
					array('vSession', 'readCache'),
					array('vSession', 'writeCache'),
					array('vSession', 'destroyCache'),
					array('vSession', 'gcCache')
				);
			}
			session_start();
		}
		
		// If the session has existed for too long, reset it
		if (isset($_SESSION['vSession::expires']) && $_SESSION['vSession::expires'] < $_SERVER['REQUEST_TIME']) {
			$_SESSION = array();
			self::regenerateID();
		}
		
		if (!isset($_SESSION['vSession::type'])) {
			$_SESSION['vSession::type'] = 'normal';	
		}
		
		// We store the expiration time for a session to allow for both normal and persistent sessions
		if ($_SESSION['vSession::type'] == 'persistent' && self::$persistent_timespan) {
			$_SESSION['vSession::expires'] = $_SERVER['REQUEST_TIME'] + self::$persistent_timespan;
			
		} else {
			$_SESSION['vSession::expires'] = $_SERVER['REQUEST_TIME'] + self::$normal_timespan;	
		}
	}


	/**
	 * Callback to open the session
	 * 
	 * @internal
	 *
	 * @return boolean  If the operation succeeded
	 */
	static public function openCache()
	{
		return TRUE;
	}


	/**
	 * Callback to read a session's values
	 * 
	 * @internal
	 *
	 * @param  string $id  The session to read
	 * @return string  The session's serialized data
	 */
	static public function readCache($id)
	{
		return self::$backend->get(self::$key_prefix . $id, '');
	}
	
	
	/**
	 * Regenerates the session ID, but only once per script execution
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	static public function regenerateID()
	{
		if (!self::$regenerated){
			session_regenerate_id();
			self::$regenerated = TRUE;
		}
	}
	
	
	/**
	 * Removes and returns the value from the end of an array value
	 *
	 * @param  string  $key        The name of the element to remove the value from - array elements can be modified via `[sub-key]` syntax, and thus `[` and `]` can not be used in key names
	 * @param  boolean $beginning  If the value should be removed to the beginning
	 * @return mixed  The value that was removed
	 */
	static public function remove($key, $beginning=FALSE)
	{
		self::open();
		$tip =& $_SESSION;
		
		if ($bracket_pos = strpos($key, '[')) {
			$original_key      = $key;
			$array_dereference = substr($key, $bracket_pos);
			$key               = substr($key, 0, $bracket_pos);
			
			preg_match_all('#(?<=\[)[^\[\]]+(?=\])#', $array_dereference, $array_keys, PREG_SET_ORDER);
			$array_keys = array_map('current', $array_keys);
			array_unshift($array_keys, $key);
			
			foreach (array_slice($array_keys, 0, -1) as $array_key) {
				if (!isset($tip[$array_key])) {
					return NULL;
				} elseif (!is_array($tip[$array_key])) {
					throw new vProgrammerException(
						'%1$s was called for the key, %2$s, which is not an array',
						__CLASS__ . '::remove()',
						$original_key
					);
				}
				$tip =& $tip[$array_key];
			}
			$key = end($array_keys);
		}
		
		
		if (!isset($tip[$key])) {
			return NULL;
		} elseif (!is_array($tip[$key])) {
			throw new vProgrammerException(
				'%1$s was called for the key, %2$s, which is not an array',
				__CLASS__ . '::remove()',
				$key
			);
		}
		
		if ($beginning) {
			return array_shift($tip[$key]);
		}
		
		return array_pop($tip[$key]);
	}	
	
	
	/**
	 * Resets the configuration of the class
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	static public function reset()
	{
		self::$normal_timespan     = NULL;
		self::$persistent_timespan = NULL;
		self::$regenerated         = FALSE;
		self::destroy();
		self::close();
		self::$backend             = NULL;
		self::$key_prefix          = '';
	}
	
	
	/**
	 * Sets data to the `$_SESSION` superglobal
	 * 
	 * @param  string $key     The name to save the value under - array elements can be modified via `[sub-key]` syntax, and thus `[` and `]` can not be used in key names
	 * @param  mixed  $value   The value to store
	 * @return void
	 */
	static public function set($key, $value)
	{
		self::open();
		$tip =& $_SESSION;
		
		if ($bracket_pos = strpos($key, '[')) {
			$array_dereference = substr($key, $bracket_pos);
			$key               = substr($key, 0, $bracket_pos);
			
			preg_match_all('#(?<=\[)[^\[\]]+(?=\])#', $array_dereference, $array_keys, PREG_SET_ORDER);
			$array_keys = array_map('current', $array_keys);
			array_unshift($array_keys, $key);
			
			foreach (array_slice($array_keys, 0, -1) as $array_key) {
				if (!isset($tip[$array_key]) || !is_array($tip[$array_key])) {
					$tip[$array_key] = array();
				}
				$tip =& $tip[$array_key];
			}
			$tip[end($array_keys)] = $value;
			
		} else {
			$tip[$key] = $value;
		}
	}


	/**
	 * Sets an vCache object to store sessions in
	 * 
	 * While any type of vCache backend should technically work, it would be
	 * unwise to use the `file` and `directory` types. The `file` caching
	 * backend stores all values in a single file, which would quickly become a
	 * performance bottleneck and could cause data loss with many concurrent
	 * users. The `directory` caching backend would not make sense since it is
	 * the same general functionality as the default session handler, but it
	 * would be slightly slower since it is written in PHP and not C.
	 *
	 * It is recommended to set the `serializer` and `unserializer` `$config`
	 * settings on the vCache object to `string` for the best performance and
	 * minimal storage space.
	 *
	 * For better performance, check out using the built-in session handlers
	 * that are bundled with the following extensions:
	 *
	 *  - [http://php.net/memcached.sessions memcached]
	 *  - [http://php.net/memcache.examples-overview#example-3596 memcache]
	 *  - [https://github.com/nicolasff/phpredis redis]
	 *
	 * The [http://pecl.php.net/package/igbinary igbinary] extension can
	 * provide even more of a performance boost by storing serialized data in
	 * binary format instead of as text.
	 * 
	 * @param  vCache $backend     An vCache object to store session values in
	 * @param  string $key_prefix  A prefix to add to all session IDs before storing them in the cache
	 * @return void
	 */
	static public function setBackend($backend, $key_prefix='')
	{
		if (self::$open || isset($_SESSION)) {
			throw new vProgrammerException(
				'%1$s must be called before any of %2$s, %3$s, %4$s, %5$s, %6$s, %7$s or %8$s',
				__CLASS__ . '::setLength()',
				__CLASS__ . '::add()',
				__CLASS__ . '::clear()',
				__CLASS__ . '::enablePersistence()',
				__CLASS__ . '::get()',
				__CLASS__ . '::open()',
				__CLASS__ . '::set()',
				'session_start()'
			);
		}

		self::$old_session_module_name = session_module_name();

		self::$backend    = $backend;
		self::$key_prefix = $key_prefix;

		session_set_save_handler(
			array('vSession', 'openCache'),
			array('vSession', 'closeCache'),
			array('vSession', 'readCache'),
			array('vSession', 'writeCache'),
			array('vSession', 'destroyCache'),
			array('vSession', 'gcCache')
		);

		// This ensures the session is closed before the vCache object is destructed
		register_shutdown_function(array('vSession', 'close'));
	}
	
	
	/**
	 * Sets the minimum length of a session - PHP might not clean up the session data right away once this timespan has elapsed
	 * 
	 * Please be sure to set a custom session path via ::setPath() to ensure
	 * another site on the server does not garbage collect the session files
	 * from this site!
	 * 
	 * Both of the timespan can accept either a integer timespan in seconds,
	 * or an english description of a timespan (e.g. `'30 minutes'`, `'1 hour'`,
	 * `'1 day 2 hours'`).
	 * 
	 * @param  string|integer $normal_timespan      The normal, session-based cookie, length for the session
	 * @param  string|integer $persistent_timespan  The persistent, timed-based cookie, length for the session - this is enabled by calling ::enabledPersistence() during login
	 * @return void
	 */
	static public function setLength($normal_timespan, $persistent_timespan=NULL)
	{
		if (self::$open || isset($_SESSION)) {
			throw new vProgrammerException(
				'%1$s must be called before any of %2$s, %3$s, %4$s, %5$s, %6$s, %7$s or %8$s',
				__CLASS__ . '::setLength()',
				__CLASS__ . '::add()',
				__CLASS__ . '::clear()',
				__CLASS__ . '::enablePersistence()',
				__CLASS__ . '::get()',
				__CLASS__ . '::open()',
				__CLASS__ . '::set()',
				'session_start()'
			);
		}
		
		$seconds = (!is_numeric($normal_timespan)) ? strtotime($normal_timespan) - time() : $normal_timespan;
		self::$normal_timespan = $seconds;
		
		if ($persistent_timespan) {
			$seconds = (!is_numeric($persistent_timespan)) ? strtotime($persistent_timespan) - time() : $persistent_timespan;	
			self::$persistent_timespan = $seconds;
		}
		
		ini_set('session.gc_maxlifetime', $seconds);
	}
	
	
	/**
	 * Sets the path to store session files in
	 * 
	 * This method should always be called with a non-standard directory
	 * whenever ::setLength() is called to ensure that another site on the
	 * server does not garbage collect the session files for this site.
	 * 
	 * Standard session directories usually include `/tmp` and `/var/tmp`. 
	 * 
	 * @param  string|vDirectory $directory  The directory to store session files in
	 * @return void
	 */
	static public function setPath($directory)
	{
		if (self::$open || isset($_SESSION)) {
			throw new vProgrammerException(
				'%1$s must be called before any of %2$s, %3$s, %4$s, %5$s, %6$s, %7$s or %8$s',
				__CLASS__ . '::setPath()',
				__CLASS__ . '::add()',
				__CLASS__ . '::clear()',
				__CLASS__ . '::enablePersistence()',
				__CLASS__ . '::get()',
				__CLASS__ . '::open()',
				__CLASS__ . '::set()',
				'session_start()'
			);
		}
		
		if (!$directory instanceof vDirectory) {
			$directory = new vDirectory($directory);	
		}
		
		if (!$directory->isWritable()) {
			throw new vEnvironmentException(
				'The directory specified, %s, is not writable',
				$directory->getPath()
			);	
		}
		
		session_save_path($directory->getPath());
	}


	/**
	 * Callback to write a session's values
	 * 
	 * @internal
	 *
	 * @param  string $id      The session to write
	 * @param  string $values  The serialized values
	 * @return string  The session's serialized data
	 */
	static public function writeCache($id, $values)
	{
		return self::$backend->set(self::$key_prefix . $id, $values);
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return vSession
	 */
	private function __construct() { }
}



/**
 * Copyright (c) Alan Johnston of Velus Universe Ltd <alan.johnston@velusuniverse.co.uk>
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */