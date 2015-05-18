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
 * Include th config file
 * the config file maybe updated as time goes on so then there will be other configs in it
 */
include dirname(__FILE__) . '/config.php';
/*
 * enable debugging TRUE or commented out
 * This will debug the site, it is used mainly for development.
 */
//vCore::enableDebugging(TRUE);
/*
 * This is the template settings for the site it will have all the tmeplate settings
 * it will set the templates for the header navigation, sidebars and also the footer
 * and the topsection which will be a corosel.
 */
$tmpl = new vTemplating(DOC_ROOT . '/app/views/');
$tmpl->set('header', 'header.php');
$tmpl->set('footer', 'footer.php');
$tmpl->set('navbar', 'nav/navbar.php');
$tmpl->set('sidebar', 'sidebar.php');
$tmpl->set('sidebar2', 'sidebar2.php');
$tmpl->set('topsection', 'topsection.php');
/*
 * This will open the session and start to keep track of the views movements and 
 * such while the site is open
 */
vSession::open();
/*
 * This will be the db connection and configs. we will be using a ORM approach 
 * to the site for ease of use and maintainence
 * this will be iun the format conection type, dbname , dbusername , dbpassword , host
 * then it will attach the db to the site using the attach 
 */
$database = new vDatabase('mysql','velusuni_portal','velusuni_portal','we9529xn@5','localhost');
vORMDatabase::attach($database);
