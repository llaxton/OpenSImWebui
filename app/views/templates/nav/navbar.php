<?php
if (!defined("OSWUI"))
{
    echo 'Sorry this page cann not be accessed directly';
 die();
}
?>
        <div class="navbar navbar-default navbar-fixed-top">
      <div class="container">
        <div class="navbar-header">
          <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
            <a class="navbar-brand" href="<?php echo vURL::getDomain()?>"><img src="app/assets/images/logo.png" width="32" height="32" alt="OpenSim WEBUI"></a>
        </div>
        <div class="navbar-collapse collapse">
          <ul class="nav navbar-nav">
            <li><a href="/home">Home</a></li>
            <li><a href="/about">About</a></li>
            <li><a href="/contact">Contact</a></li>
            <!--<li class="dropdown">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown">Services <b class="caret"></b></a>
              <ul class="dropdown-menu">
                <li><a href="/webdesign">Web Design</a></li>
                <li><a href="/webhosting">Web Hosting</a></li>
                <li><a href="/ssl">SSL Certificates</a></li>
                <li><a href="/domainnames">Domain Names</a></li>
                <li class="dropdown-header">IP PBX SERVICES</li>
                <li><a href="/hostedippbx">Hosted IP PBX Systems</a></li>
                <li><a href="/mannagedippbx">Managed IP PBX Systems</a></li>
                <li><a href="/didnmbers">DID Numbers</a></li>
                <li class="dropdown-header">OpenSim Servers</li>
                <li><a href="/hostedopensim">Hosted OpenSim Servers</a></li>
                <li><a href="/managedopensim">Managed OpenSim Servers</a></li>
              </ul>
            </li>
           <li class="dropdown">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown">Previous Customers<b class="caret"></b></a>
              <ul class="dropdown-menu">
                <li><a href="#">Our Past Customers</a></li>
                <li><a href="#">Our Current Customers</a></li>
              </ul>
            </li>
            <li class="dropdown">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown">Dropdown <b class="caret"></b></a>
              <ul class="dropdown-menu">
                <li><a href="#">Action</a></li>
                <li><a href="#">Another action</a></li>
                <li><a href="#">Something else here</a></li>
                <li class="divider"></li>
                <li class="dropdown-header">Nav header</li>
                <li><a href="#">Separated link</a></li>
                <li><a href="#">One more separated link</a></li>
              </ul>
            </li>
            <li class="dropdown">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown">Dropdown <b class="caret"></b></a>
              <ul class="dropdown-menu">
                <li><a href="#">Action</a></li>
                <li><a href="#">Another action</a></li>
                <li><a href="#">Something else here</a></li>
                <li class="divider"></li>
                <li class="dropdown-header">Nav header</li>
                <li><a href="#">Separated link</a></li>
                <li><a href="#">One more separated link</a></li>
              </ul>
            </li>-->
          </ul>
            <ul class="nav navbar-nav pull-right">
                <?php if (!vAuthorization::checkLoggedIn()) {?>
                 <li><a href="/login">login</a></li>
                <?php } else { ?>
                 <li><a href=""><?php echo $_SESSION['user_name'] ?></a></li>
                 <li><a href="/logout">Logout</a></li>
                <?php } ?>
            </ul>
        </div><!--/.nav-collapse -->
      </div>
    </div>
