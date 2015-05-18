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
function vDate($date=NULL) {
	return new vDate($date);    
}

function vDirectory($directory) {
	return new vDirectory($directory);    
}

function vFile($file) {
	return new vFile($file);    
}

function vImage($file_path) {
	return new vImage($file_path);    
}

function vMoney($amount, $currency=NULL) {
	return new vMoney($amount, $currency);    
}

function vNumber($value, $scale=NULL) {
	return new vNumber($value, $scale);
}

function vTime($time=NULL) {
	return new vTime($time);    
}

function vTimestamp($datetime=NULL, $timezone=NULL) {
	return new vTimestamp($datetime, $timezone);    
}