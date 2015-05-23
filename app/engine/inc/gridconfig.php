<?php
$gridnameconfig = new Config(array('config_name' => 'grid_name'));
$gridname = $gridnameconfig->getConfigValue();
$gridserviceuriconfig = new Config(array('config_name' => 'grid_service_uri'));
$GRID_SERVICE_URI = $gridserviceuriconfig->getConfigValue();
$gridserviceuserconfig = new Config(array('config_name' => 'grid_user_service_uri'));
$GRID_USER_SERVICE_URI = $gridserviceuserconfig->getConfigValue();
$griduseraccounturiconfig = new Config(array('config_name' => 'user_accounts_service_uri'));
$USER_ACCOUNTS_SERVICE_URI = $griduseraccounturiconfig->getConfigValue();
$gridpresenceuriconfig = new Config(array('config_name' => 'presence_service_uri'));
$PRESENCE_SERVICE_URI = $gridpresenceuriconfig->getConfigValue();
$gridavatarserviceuriconfig = new Config(array('config_name' => 'avatar_service_uri'));
$AVATAR_SERVICE_URI = $gridavatarserviceuriconfig->getConfigValue();
$gridassetserviceuriconfig = new Config(array('config_name' => 'asset_service_uri'));
$ASSET_SERVICE_URI = $gridassetserviceuriconfig->getConfigValue();
$gridinventoryserviceuriconfig = new Config(array('config_name' => 'inventory_service_uri'));
$INVENTORY_SERVICE_URI = $gridinventoryserviceuriconfig->getConfigValue();
$gridgroupserviceuriconfig = new Config(array('config_name' => 'group_service_uri'));
$GROUPS_SERVICE_URI = $gridgroupserviceuriconfig->getConfigValue();

