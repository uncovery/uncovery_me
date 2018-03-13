<?php

include('/home/minecraft/server/bin/index_wp.php');

umc_log('system', 'daily_process', "pre-reboot processes started");

umc_plugin_eventhandler('server_pre_reboot');

// reset all user lots
umc_lot_reset_process();

umc_hardcore_resetworld();

umc_usericon_get();

umc_github_wordpress_update();

umc_item_data_id2namelist();

umc_item_data_icon_getdata();

umc_plg_enum();