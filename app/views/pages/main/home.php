<?php
if (!defined("OSWUI"))
{
    echo 'Sorry this page cann not be accessed directly';
 die();
}
$tmpl->set('title', $gridname.': '.$title_name);
$tmpl->place('header');
$tmpl->place('navbar');
$tmpl->place('topsection');
if (!vSession::get('user_name'))
{
    vSession::set('user_name' , 'Guest');
}

$username = vSession::get('user_name');
?>

<div class="container theme-showcase">
        <div class="row">
        <div class="col-sm-8 site-main">
<div class="page-header">
    <h1>Welcome <?php echo $username?> To <?php echo $gridname?></h1>
</div>
           
<p>Welcome To <?php echo $gridname?>. Some welcome text will go here. there may be a mysql table for this to display.??.</p>
<?php var_dump($gridname);?>
<!--
hopefully we will be putting a mysql table here for the welcome info
-->
<?php
//vSession::set('user_name' , 'Alan Johnston');
var_dump($_SESSION);
var_dump($_COOKIE);
?>

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