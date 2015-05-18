<?php
/**
 * Provides session-based messaging for page-to-page communication
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
 * @link       http://veluslib.opensource.velusuniverse.com/vMessaging
 */
class vMessaging
{
	// The following constants allow for nice looking callbacks to static methods
	const check     = 'vMessaging::check';
	const create    = 'vMessaging::create';
	const reset     = 'vMessaging::reset';
	const retrieval = 'vMessaging::retrieval';
	const show      = 'vMessaging::show';
	
	
	/**
	 * Checks to see if a message exists of the name specified for the recipient specified
	 * 
	 * @param  string $name       The name or array of names of the message(s) to check for, or `'*'` to check for any
	 * @param  string $recipient  The intended recipient
	 * @return boolean  If a message of the name and recipient specified exists
	 */
	static public function check($name, $recipient=NULL)
	{
		if ($recipient === NULL) {
			$recipient = '{default}';	
		}
		
		// Check all messages if * is specified
		if (is_string($name) && $name == '*') {
			vSession::open();
			$prefix = __CLASS__ . '::' . $recipient . '::';
			$keys   = array_keys($_SESSION);
			foreach ($keys as $key) {
				if (strpos($key, $prefix) === 0) {
					return TRUE;
				}
			}
			return FALSE;
		}
		
		// Handle checking multiple messages
		if (is_array($name)) {
			foreach ($names as $name) {
				if (self::check($name, $recipient)) {
					return TRUE;	
				}
			}
			return FALSE;
		}
		
		return vSession::get(__CLASS__ . '::' . $recipient . '::' . $name, NULL) !== NULL;
	}
	
	
	/**
	 * Creates a message that is stored in the session and retrieved by another page
	 * 
	 * @param  string $name       A name for the message
	 * @param  string $recipient  The intended recipient - this may be ommitted
	 * @param  string $message    The message to send
	 * @param  string :$name
	 * @param  string :$message
	 * @return void
	 */
	static public function create($name, $recipient, $message=NULL)
	{                                  
		// This allows for the $recipient parameter to be optional
		if ($message === NULL) {
			$message   = $recipient;
			$recipient = '{default}';	
		}
		
		vSession::set(__CLASS__ . '::' . $recipient . '::' . $name, $message);
	}
	
	
	/**
	 * Resets the data of the class
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	static public function reset()
	{
		vSession::clear(__CLASS__ . '::');
	}
	
	
	/**
	 * Retrieves and removes a message from the session
	 * 
	 * @param  string $name       The name of the message to retrieve
	 * @param  string $recipient  The intended recipient
	 * @return string  The message contents
	 */
	static public function retrieve($name, $recipient=NULL)
	{
		if ($recipient === NULL) {
			$recipient = '{default}';	
		}
		
		$key     = __CLASS__ . '::' . $recipient . '::' . $name;
		$message = vSession::get($key, NULL);
		vSession::delete($key);
		return $message;
	}
	
	
	/**
	 * Retrieves a message, removes it from the session and prints it - will not print if no content
	 * 
	 * The message will be printed in a `p` tag if it does not contain
	 * any block level HTML, otherwise it will be printed in a `div` tag.
	 * 
	 * @param  mixed  $name       The name or array of names of the message(s) to show, or `'*'` to show all
	 * @param  string $recipient  The intended recipient
	 * @param  string $css_class  Overrides using the `$name` as the CSS class when displaying the message - only used if a single `$name` is specified
	 * @return boolean  If one or more messages was shown
	 */
	static public function show($name, $recipient=NULL, $css_class=NULL)
	{
		if ($recipient === NULL) {
			$recipient = '{default}';	
		}
		
		// Find all messages if * is specified
		if (is_string($name) && $name == '*') {
			vSession::open();
			$prefix = __CLASS__ . '::' . $recipient . '::';
			$keys   = array_keys($_SESSION);
			$name   = array();
			foreach ($keys as $key) {
				if (strpos($key, $prefix) === 0) {
					$name[] = substr($key, strlen($prefix));
				}
			}
		}
		
		// Handle showing multiple messages
		if (is_array($name)) {
			$shown = FALSE;
			$names = $name;
			foreach ($names as $name) {
				$shown = vHTML::show(
					self::retrieve($name, $recipient),
					$name
				) || $shown;
			}
			return $shown;
		}
		
		// Handle a single message
		return vHTML::show(
			self::retrieve($name, $recipient),
			($css_class === NULL) ? $name : $css_class
		);
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return vMessaging
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