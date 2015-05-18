<?php vHTML::sendHeader() ?><!DOCTYPE html>
<!-- This is the header template this will be able to be altered to your specs,
docs will be comming soon -->
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
    <head>
        
        <meta http-equiv="content-type" content="text/html; charset=utf-8" />

        <title><?php echo $this->prepare('title') ?><?php echo (strpos($this->get('title'), 'Grid:') === FALSE ? 'Grid' : '') ?></title>

        <base href="<?php echo vURL::getDomain() . URL_ROOT ?>" />
        
        <link rel="shortcut icon" href="app/assets/images/favicon.ico" type="image/x-icon" />
        
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />  
        <link rel="shortcut icon" href="app/assets/ico/favicon.png" />        
        <!-- Bootstrap core CSS -->
        <link href="app/assets/css/bootstrap.css" rel="stylesheet" />
        <link href="app/assets/css/carosel.css" rel="stylesheet" />
        <!-- Bootstrap theme -->
        <link href="app/assets/css/bootstrap-theme.min.css" rel="stylesheet" />

        <!-- Custom styles for this template -->
        <link href="app/assets/css/theme.css" rel="stylesheet" />

        <!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
        <!--[if lt IE 9]>
          <script src="app/assets/js/html5shiv.js"></script>
          <script src="app/assets/js/respond.min.js"></script>
        <![endif]-->
    </head>            
    <body>