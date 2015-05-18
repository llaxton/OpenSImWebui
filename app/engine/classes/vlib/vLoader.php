<?php
/**
 * A class that loads VelusLib
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
 * @link       http://veluslib.opensource.velusuniverse.com/vLoader
 */
class vLoader
{
	// The following constants allow for nice looking callbacks to static methods
	const autoload       = 'vLoader::autoload';
	const best           = 'vLoader::best';
	const eager          = 'vLoader::eager';
	const hasOpcodeCache = 'vLoader::hasOpcodeCache';
	const lazy           = 'vLoader::lazy';
	
	
	/**
	 * The VelusLib classes in dependency order
	 * 
	 * @var array
	 */
	static private $classes = array(
		'vException',
		'vExpectedException',
		'vEmptySetException',
		'vNoRemainingException',
		'vNoRowsException',
		'vNotFoundException',
		'vValidationException',
		'vUnexpectedException',
		'vConnectivityException',
		'vEnvironmentException',
		'vProgrammerException',
		'vSQLException',
		'vActiveRecord',
		'vAuthorization',
		'vAuthorizationException',
		'vBuffer',
		'vCRUD',
		'vCache',
		'vCookie',
		'vCore',
		'vCryptography',
		'vDatabase',
		'vDate',
		'vDirectory',
		'vEmail',
		'vFile',
		'vFilesystem',
		'vGrammar',
		'vHTML',
		'vImage',
		'vJSON',
		'vMailbox',
		'vMessaging',
		'vMoney',
		'vNumber',
		'vORM',
		'vORMColumn',
		'vORMDatabase',
		'vORMDate',
		'vORMFile',
		'vORMJSON',
		'vORMMoney',
		'vORMOrdering',
		'vORMRelated',
		'vORMSchema',
		'vORMValidation',
		'vPagination',
		'vRecordSet',
		'vRequest',
		'vResult',
		'vSMTP',
		'vSQLSchemaTranslation',
		'vSQLTranslation',
		'vSchema',
		'vSession',
		'vStatement',
		'vTemplating',
		'vText',
		'vTime',
		'vTimestamp',
		'vURL',
		'vUTF8',
		'vUnbufferedResult',
		'vUpload',
		'vValidation',
		'vXML'
	);

	/**
	 * The path VelusLib is installed into
	 * 
	 * @var string
	 */
	static private $path = NULL;
	
	
	/**
	 * Tries to load a VelusLib class
	 * 
	 * @internal
	 *
	 * @param  string $class  The class to load
	 * @return void
	 */
	static public function autoload($class)
	{
		if ($class[0] != 'f' || ord($class[1]) < 65 || ord($class[1]) > 90) {
			return;
		}

		if (!in_array($class, self::$classes)) {
			return;
		}

		include self::$path . $class . '.php';
	}


	/**
	 * performs eager loading if an op-code cache is present, otherwise lazy
	 * 
	 * @return void
	 */
	static public function best()
	{
		if (self::hasOpcodeCache()) {
			return self::eager();
		}
		self::lazy();
	}


	/**
	 * Creates functions that act as chainable constructors
	 *
	 * @return void
	 */
	static private function createConstructorFunctions()
	{
		if (function_exists('vDate')) {
			return;
		}

		function vDate($date=NULL)
		{
			return new vDate($date);    
		}
		 
		function vDirectory($directory)
		{
			return new vDirectory($directory);    
		}

		function vEmail()
		{
			return new vEmail();
		}
		 
		function vFile($file)
		{
			return new vFile($file);    
		}
		 
		function vImage($file_path)
		{
			return new vImage($file_path);    
		}
		 
		function vMoney($amount, $currency=NULL)
		{
			return new vMoney($amount, $currency);    
		}
		 
		function vNumber($value, $scale=NULL)
		{
			return new vNumber($value, $scale);
		}
		 
		function vTime($time=NULL)
		{
			return new vTime($time);    
		}
		 
		function vTimestamp($datetime=NULL, $timezone=NULL)
		{
			return new vTimestamp($datetime, $timezone);    
		}
	}


	/**
	 * Loads all VelusLib classes when called
	 * 
	 * @return void
	 */
	static public function eager()
	{
		self::setPath();
		self::createConstructorFunctions();
		foreach (self::$classes as $class) {
			include self::$path . $class . '.php';
		}
	}


	/**
	 * Check if a PHP opcode cache is installed
	 * 
	 * The following opcode caches are currently detected:
	 * 
	 *  - [http://pecl.php.net/package/APC APC]
	 *  - [http://eaccelerator.net eAccelerator]
	 *  - [http://www.nusphere.com/products/phpexpress.htm Nusphere PhpExpress]
	 *  - [http://turck-mmcache.sourceforge.net/index_old.html Turck MMCache]
	 *  - [http://xcache.lighttpd.net XCache]
	 *  - [http://www.zend.com/en/products/server/ Zend Server (Optimizer+)]
	 *  - [http://www.zend.com/en/products/platform/ Zend Platform (Code Acceleration)]
	 * 
	 * @return boolean  If a PHP opcode cache is loaded
	 */
	static public function hasOpcodeCache()
	{		
		$apc              = ini_get('apc.enabled');
		$eaccelerator     = ini_get('eaccelerator.enable');
		$mmcache          = ini_get('mmcache.enable');
		$phpexpress       = function_exists('phpexpress');
		$xcache           = ini_get('xcache.size') > 0 && ini_get('xcache.cacher');
		$zend_accelerator = ini_get('zend_accelerator.enabled');
		$zend_plus        = ini_get('zend_optimizerplus.enable');
		
		return $apc || $eaccelerator || $mmcache || $phpexpress || $xcache || $zend_accelerator || $zend_plus;
	}


	/**
	 * Registers an autoloader for VelusLib via [http://php.net/spl_autoload_register `spl_autoload_register()`]
	 * 
	 * @return void
	 */
	static public function lazy()
	{
		self::setPath();
		self::createConstructorFunctions();

		if (function_exists('__autoload') && !spl_autoload_functions()) {
			throw new Exception(
				'vLoader::lazy() was called, which adds an autoload function ' .
				'via spl_autoload_register(). It appears an __autoload ' . 
				'function has already been defined, but not registered via ' .
				'spl_autoload_register(). Please call ' . 
				'spl_autoload_register("__autoload") after vLoader::lazy() ' .
				'to ensure your autoloader continues to function.'
			);
		}
		
		spl_autoload_register(array('vLoader', 'autoload'));
	}


	/**
	 * Determines where VelusLib is installed
	 * 
	 * @return void
	 */
	static private function setPath()
	{
		self::$path = realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR;
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return vLoader
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
