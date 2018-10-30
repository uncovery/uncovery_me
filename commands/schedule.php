<?php
include('/home/minecraft/server/bin/index_wp.php');

XMPP_ERROR_trace("Running schedule.php");

umc_log('system', 'daily_process', "post-reboot processes started");

// make a new ID file in case item data has changed
umc_item_search_create();

run_umc_scheduler();

umc_plugin_eventhandler('server_post_reboot');

echo "Done!\n";

function run_umc_scheduler() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    // list of what to do & undo or temp permissions
    $chat_command = 'broadcast';

    $schedule_arr = array(
        1 => array( // Monday
            'on_cmd' => array(
                0 => "$chat_command Today is Monday, nothing special!",
                //0 => "$chat_command Today bloody is Monday, beware of the mobs!",
                //1 => 'bloodmoon start darklands',
            ),
            'off_cmd' => array(
            ),
        ),
        2 => array( // Tuesday
            'on_cmd' => array(
                0 => "$chat_command Today is Tuesday, nothing special!",
                //1 => 'bloodmoon stop darklands',
		//1 => 'time 00:00 darklands', // set it to midnight^
            ),
            'off_cmd' => array(
            ),
        ),
        3 => array( // Wednesday
            'on_cmd' => array(
                0 => "$chat_command Hungry Wednesday started!",
                1 => 'mv modify set autoheal false darklands',
                2 => 'mv gamerule naturalRegeneration false darklands',
                3 => 'region flag darklands_spawn greeting -w darklands ATTENTION: Today Darklands autoheal is OFF! You being fed does not heal you!',
            ),
            'off_cmd' => array(
                0 => "$chat_command Hungry Wednesday is over!",
                1 => 'mv modify set autoheal true darklands',
                2 => 'mv gamerule naturalRegeneration true darklands',
                3 => 'region flag darklands_spawn greeting -w darklands Welcome to the Darklands! Today everything is normal. Whatever that means.',
            ),
        ),
        4 => array( // Thursday
            'on_cmd' => array(
                0 => "$chat_command Today is Thursday, nothing special!",
            ),
            'off_cmd' => array(
            ),
        ),
        5 => array( // Friday
            'on_cmd' => array(
                0 => "$chat_command Freaky Frydays started!",
                1 => 'time 12:00 darklands', // set it to lunchtime
                2 => 'sunburn darklands on', // sunburn on
                3 => 'region flag darklands_spawn greeting -w darklands ATTENTION: Today it\'s daylight in the Darklands, but the sun will burn you! find shadow!',
            ),
            'off_cmd' => array(
                0 => "$chat_command Freaky Frydays is over!",
                1 => 'time 00:00 darklands', // set it to midnight
                2 => 'sunburn darklands off', // sunburn off
                3 => 'region flag darklands_spawn greeting -w darklands Welcome to the Darklands! Today everything is normal. Whatever that means.',
            ),
        ),
        6 => array( // Saturday
            'on_cmd' => array(
                0 => "$chat_command Today is Saturday, nothing special!",
            ),
            'off_cmd' => array(
            ),
        ),
        0 => array( // Sunday
            'on_cmd' => array(
                0 => 'mv modify set pvp true darklands',
                1 => 'region flag darklands_spawn greeting -w darklands ATTENTION: Today Darklands is PVP ON! You are safe here, but further out it\'s dangerous!',
                2 => "$chat_command ATTENTION: PVP is now ON in the darklands (except the area around spawn)!",
                3 => 'region flag darklands_spawn farewell -w darklands ATTENTION: You are now entering a PVP area!!',
            ),
            'off_cmd' => array(
                0 => 'mv modify set pvp false darklands',
                1 => 'region flag darklands_spawn greeting -w darklands Today Darklands is PVP OFF!',
                2 => "$chat_command ATTENTION: PVP is now OFF in the darklands. You are safe!",
                3 => 'region flag darklands_spawn farewell -w darklands Welcome to the Darklands! Today everything is normal. Whatever that means.',
            ),
        ),
    );
    // find current day

    $date_new = umc_datetime();
    $today = $date_new->format('w');

    // echo "Echo today is $today: " . $date_new->format("Y-m-d H:i:s");
    if ($today == 0) {
        $yesterday = 6;
    } else {
        $yesterday = $today - 1;
    }

    // execute last day's off-commands
    $cmds1 = $schedule_arr[$yesterday]['off_cmd'];
    // var_dump($cmds);

    $default_commands = array(
        "mv gamerule doDaylightCycle false darklands",
        "time set 00:00 darklands",
        "mv gamerule naturalRegeneration false deathlands", //TODO: move this to an event in the hardcore plugin
    );
    umc_schedule_exec($default_commands);

    umc_schedule_exec($cmds1);
    umc_log('scheduler', "yesterday", "executing commands for yesterday: $yesterday");

    // execute todays on-commands
    $cmds = $schedule_arr[$today]['on_cmd'];
    umc_log('scheduler', "today", "executing commands for today: $today");

    umc_schedule_exec($cmds);
}

function umc_schedule_exec($cmds) {
    foreach ($cmds as $command) {
        // echo $command;
        umc_exec_command($command);
    }
}
