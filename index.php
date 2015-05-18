<?php
/**
 * The define will set the key VWC to 1 which through out the site will mean that
 * the index page has sent the request to subsiquent pages. The reason for this
 * is security, we need to make sure its the site sending the request and its done 
 * peoperly, so people can just go to domain.tld/app/views/admin/dashboard.php 
 * as this will bypass any information we need
 **/
define("OSWUI", 1);
/**
 * The init.php will contain all the initialisation that is needed to make the site run.
 * like the classes of the engine, the classes of the other parts and also so that
 * there is no extra stuff being loaded that aint needed.
 **/
include "./app/engine/inc/init.php";
include './app/engine/inc/gridconfig.php';
/**
 * This section will collect the data of the page and any other values and then
 * load the page, homepage or 404 page then it will store the other values in an 
 * array to be passed to the page if needed, this is security feature and will help
 * with naming. so you can have something like domain.com/cat/textures/sea this will
 * go to the page catagories and will then give the values textures and sea to the page
 * where needed.
 **/
$uriPage = explode('/',trim(vURL::get(), '/'));
$urlPage = $uriPage['0'];
$setPage = vRecordSet::build('Page', array('page_name='=>$urlPage));
$pagecount = $setPage->count();
//$records = $setPage->getRecord(0);
if($urlPage === '')
{
    $page  = new Page(array('page_name' => 'home'));
    $page_name = $page->getPageName();
    $page_file = $page->getPageFile();
    $catagory = $page->getPageCatagory();
    $title_name = $page->getPageTitle();
    $page_metta = $page->getPageMetta();
    $is_page_active = $page->getIsPageActive();
    //$needs_to_be_logged_in = $page->getNeedsToBeLoggedIn();
    $level= $page->getLevel();
    //$records = $setPage[0];
    include_once DOC_ROOT .'/app/views/pages/'.$catagory.'/'.$page_file;
    //include_once 'views/pages/index.php';
}
else if($pagecount === 1)
{
     /*
    * Here if the page is in the db then we will collect the db information 
    * and then use it for our system
    */
    $page  = new Page(array('page_name' => $urlPage));
    $page_name = $page->getPageName();
    $page_file = $page->getPageFile();
    $catagory = $page->getPageCatagory();
    $title_name = $page->getPageTitle();
    $page_metta = $page->getPageMetta();
    $is_page_active = $page->getIsPageActive();
    //$needs_to_be_logged_in = $page->getNeedsToBeLoggedIn();
    $level= $page->getLevel();
    //$records = $setPage[0];
    /*
     * Then we will load the page and have the system with it.
     * We will need to check the level of the person and also check if they need
     * to be logged in to view the page, if the level and the logged in is not
     * correct then we will show them one of two pages not logged in it will show
     * not logged in if not correct level it will then show a incorrect level page.
     */
    /*
     * Will need something like vsession::get('user_status') and vsession::get('user_level')
     * and check if user is logged in and also check if user has right level so something like
     * if needs to be logged in is = 1 then check if userstatus is = 1,
     */
  
        include_once DOC_ROOT .'/app/views/pages/'.$catagory.'/'.$page_file;
    
}
else if ($pagecount !== 1)
{
    /*
     * Here we will load the 404 page showing there was an error.
     */
    $page  = new Page(array('page_name' => '404'));
    $page_name = $page->getPageName();
    $page_file = $page->getPageFile();
    $catagory = $page->getPageCatagory();
    $page_metta = $page->getPageMetta();
    $title_name = $page->getPageTitle();
    $is_page_active = $page->getIsPageActive();
    //$needs_to_be_logged_in = $page->getNeedsToBeLoggedIn();
    $level= $page->getLevel();
    //$records = $setPage[0];
    include_once DOC_ROOT .'/app/views/pages/'.$page_file;
}