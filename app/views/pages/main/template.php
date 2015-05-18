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

?>

<div class="container theme-showcase">
        <div class="row">
        <div class="col-sm-8 site-main">
<div class="page-header">
    <h1>Header Text</h1>
</div>
           
<p>This is the main info for the page</p>


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