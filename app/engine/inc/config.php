<?php
/* 
 *The if NOT defined is so it checks that the page that sent the request came from index.php.
 * all pages from the site will be run though index.php but will show relevent content.
 * the whole site will be designed with this in mind. but will have search friendly urls
 * i.e domain.com/{page} and things like domain.com/user/{username} which will show that user in the page.
 */

if (!defined("OSWUI"))
{
    echo "sorry this page can't be accessed directly";
    die();
}

/*
 * This sets the doc_root and url_root of the system
 */

define('DOC_ROOT', realpath(dirname(__FILE__) . '/../../../'));
define('URL_ROOT', substr(DOC_ROOT, strlen(realpath($_SERVER['DOCUMENT_ROOT']))) . '/');
 /*
  * this will report any errors that the site has and puts them in a log file, 
  * this will be kept on the server and will be able to be used to check errors 
  * reported to us
  */
error_reporting(E_STRICT | E_ALL);
vCore::enableErrorHandling(DOC_ROOT . '/app/engine/writeable/logs/errors.log');
vCore::enableExceptionHandling(DOC_ROOT . '/app/engine/writeable/logs/exceptions.log');

// This prevents cross-site session transfer
vSession::setPath(DOC_ROOT . '/app/engine/storage/sessions/');
vSession::ignoreSubdomain();
vSession::setLength('24hours');

/*
 * this will consruct some functions for use within the site.
 */
include DOC_ROOT . '/app/engine/inc/constructor_functions.php';


/**
 * Automatically includes classes
 * 
 * @throws Exception
 * 
 * @param  string $class  Name of the class to load
 * @return void
 */
function __autoload($class)
{
	$vlib_file = DOC_ROOT . '/app/engine/classes/vlib/' . $class . '.php';
 
	if (file_exists($vlib_file)) {
		return require $vlib_file;
	}
	
	$file = DOC_ROOT . '/app/engine/classes/app/' . $class . '.php';
 
	if (file_exists($file)) {
		return require $file;
	}
	
	throw new Exception('The class ' . $class . ' could not be loaded');
}
