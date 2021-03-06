<?php
/**
 * Provides internationlization support for strings
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
 * @link       http://veluslib.opensource.velusuniverse.com/vText
 */
class vText
{
	// The following constants allow for nice looking callbacks to static methods
	const compose                 = 'vText::compose';
	const registerComposeCallback = 'vText::registerComposeCallback';
	const reset                   = 'vText::reset';
	
	
	/**
	 * Callbacks for when messages are composed
	 * 
	 * @var array
	 */
	static private $compose_callbacks = array(
		'pre'  => array(),
		'post' => array()
	);
	
	
	/**
	 * performs an [http://php.net/sprintf sprintf()] on a string and provides a hook for modifications such as internationalization
	 * 
	 * @param  string  $message    A message to compose
	 * @param  mixed   $component  A string or number to insert into the message
	 * @param  mixed   ...
	 * @return string  The composed message
	 */
	static public function compose($message)
	{
		if (self::$compose_callbacks) {
			foreach (self::$compose_callbacks['pre'] as $callback) {
				$message = call_user_func($callback, $message);
			}
		}
		
		$components = array_slice(func_get_args(), 1);
		
		// Handles components passed as an array
		if (sizeof($components) == 1 && is_array($components[0])) {
			$components = $components[0];	
		}
		
		$message = vsprintf($message, $components);
		
		if (self::$compose_callbacks) {
			foreach (self::$compose_callbacks['post'] as $callback) {
				$message = call_user_func($callback, $message);
			}
		}
		
		return $message;
	}
	
	
	/**
	 * Adds a callback for when a message is created using ::compose()
	 * 
	 * The primary purpose of these callbacks is for internationalization of
	 * error messaging in VelusLib. The callback should accept a single
	 * parameter, the message being composed and should return the message
	 * with any modifications.
	 * 
	 * The timing parameter controls if the callback happens before or after
	 * the actual composition takes place, which is simply a call to
	 * [http://php.net/sprintf sprintf()]. Thus the message passed `'pre'`
	 * will always be exactly the same, while the message `'post'` will include
	 * the interpolated variables. Because of this, most of the time the `'pre'`
	 * timing should be chosen.
	 * 
	 * @param  string   $timing    When the callback should be executed - `'pre'` or `'post'` performing the actual composition
	 * @param  callback $callback  The callback
	 * @return void
	 */
	static public function registerComposeCallback($timing, $callback)
	{
		$valid_timings = array('pre', 'post');
		if (!in_array($timing, $valid_timings)) {
			throw new vProgrammerException(
				'The timing specified, %1$s, is not a valid timing. Must be one of: %2$s.',
				$timing,
				join(', ', $valid_timings)	
			);
		}
		
		if (is_string($callback) && strpos($callback, '::') !== FALSE) {
			$callback = explode('::', $callback);	
		}
		
		self::$compose_callbacks[$timing][] = $callback;
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
		self::$compose_callbacks = array(
			'pre'  => array(),
			'post' => array()
		);
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return vText
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