<?php

include('/home/minecraft/server/bin/index_wp.php');

umc_plugin_eventhandler('server_pre_reboot');

umc_users_downgrade_donators();

// reset all user lots
umc_lot_reset_process();

umc_hardcore_resetworld();

umc_usericon_get();

umc_github_wordpress_update();