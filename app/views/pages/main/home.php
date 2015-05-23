<?php
if (!defined("OSWUI"))
{
    echo 'Sorry this page cann not be accessed directly';
 die();
}
include './app/engine/inc/pagedata.php';
$tmpl->set('title', $gridname.': '.$title_name);
$tmpl->place('header');
$tmpl->place('navbar');
$tmpl->place('topsection');
if (!vSession::get('user_name'))
{
    vSession::set('user_name' , 'Guest');
}

$username = vSession::get('user_name');
$pagedataheader = str_replace(array('user_name','grid_name','[b]','[/b]'),array($username,$gridname,'<b>','</b>') ,$pagedataheader );
?>

<div class="container theme-showcase">
        <div class="row">
        <div class="col-sm-8 site-main">
<div class="page-header">
    <h1><?php echo $pagedataheader?></h1>
</div>
           
<p><?php echo $pagedatamain?></p>

<!--
hopefully we will be putting a mysql table here for the welcome info
-->


      </div>
      <div class="col-sm-3 col-sm-offset-1 main-sidebar">
<?php 
//$tmpl->place('sidebar');
?>
       </div>
    </div> 
</div>
<?php         
$tmpl->place('footer'); 