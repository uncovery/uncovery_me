<?php

include('/home/minecraft/server/bin/index_wp.php');

XMPP_ERROR_trigger("Running schedule_pre_reboot!");

umc_log('system', 'daily_process', "pre-reboot processes started");

umc_plugin_eventhandler('server_pre_reboot');

// reset all user lots
umc_lot_reset_process();

// reset user lot count in UUID table
umc_uuid_record_lotcount();

umc_usericon_get();

umc_github_wordpress_update();

umc_item_data_id2namelist();

umc_item_data_icon_getdata();

// rename old items in the databases to the new names (item_data.inc.php)
umc_item_fix_old();

umc_plg_enum();

