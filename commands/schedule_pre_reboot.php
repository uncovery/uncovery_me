<?php

include('/home/minecraft/server/bin/index_wp.php');

// reset all user lots
umc_lot_reset_process();

umc_hardcore_resetworld();

umc_update_usericons();

umc_github_wordpress_update();