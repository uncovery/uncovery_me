<?php
/*
 * This file is part of Uncovery Minecraft.
 * Copyright (C) 2015 uncovery.me
 *
 * Uncovery Minecraft is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of  MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/*
 * This plugin manages the "deathlands" world in hardcore mode. It mainly allows
 * users to enter and exit the world as well as the scoring and the world reset
 */
global $UMC_SETTING, $WS_INIT;
global $HARDCORE;

$HARDCORE = array(
    'worlds' => array('deathlands'),
    'first_date' => '2015-06-09 00:00:00',
    'period_length' => 7, // in days
);


$WS_INIT['hardcore'] = array(  // the name of the plugin
    'disabled' => false,
    'events' => array(
        'server_pre_reboot' => 'umc_hardcore_resetworld',
    ),
    'default' => array(
        'help' => array(
            'title' => 'Hardcore Gameplay',  // give it a friendly title
            'short' => 'Commands related to Hardcore gameplay',  // a short description
            'long' => "Hardcore gameplay allows you to survive in a world until you die or it resets.", // a long add-on to the short  description
            ),
    ),
    'start' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Brings you into the hardcore world',
            'long' => "This will teleport you into the hardcore world.",
        ),
        'function' => 'umc_hardcore_start',
    ),
    'exit' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Allows you to leave the hardcore world',
            'long' => "This will teleport you out of the hardcore world.",
        ),
        'function' => 'umc_hardcore_exit',
        'security' => array(
            'worlds' => array('deathlands'),
        ),
    ),
    'commit' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Deposit the current block to record a score.',
            'long' => "This will take the current block away from you and register it's value as a score for your current game.",
        ),
        'function' => 'umc_hardcore_commit',
        'security' => array(
            'worlds' => array('deathlands'),
        ),
    ),
    'score' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Get the current score.',
            'long' => "This will give you the score of the current game.",
        ),
        'function' => 'umc_hardcore_score',
    ),
);

function umc_hardcore_start() {
    global $UMC_USER;
    // check if we had a start
    $uuid = $UMC_USER['uuid'];
    $player = $UMC_USER['username'];
    $P = umc_hardcore_get_period();
    $sql = "SELECT * FROM minecraft_srvr.hardcore
        WHERE `uuid`='$uuid' AND entry_date >= '{$P['start_date']}' AND entry_date < '{$P['end_date']}'
        ORDER BY entry_date DESC;";
    $D = umc_mysql_fetch_all($sql);

    if (count($D) > 0 && $D[0]['clean_exit'] <> 1) {
        umc_error("Sorry, you already died in the world this round, no more entries available!");
    } else {
        // check if this is a re-entry
        $check_sql = "SELECT * FROM minecraft_srvr.hardcore
            WHERE `uuid`='$uuid' AND entry_date >= '{$P['start_date']}' AND entry_date < '{$P['end_date']}' AND clean_exit = 1
            ORDER BY entry_date DESC;";
        $C = umc_mysql_fetch_all($check_sql);
        if (count($C) == 1) {
            $ins_sql = "UPDATE minecraft_srvr.hardcore
                SET `clean_exit` = NULL
                WHERE `uuid`='$uuid' AND entry_date >= '{$P['start_date']}' AND entry_date < '{$P['end_date']}'
                LIMIT 1;";
        } else {
            $ins_sql = "INSERT INTO minecraft_srvr.hardcore(`uuid`, `entry_date`) VALUES ('$uuid' ,NOW())";
        }

        umc_mysql_query($ins_sql, true);
        umc_ws_cmd("warp hardcore $player");
    }
}

function umc_hardcore_exit() {
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $uuid = $UMC_USER['uuid'];
    $C = $UMC_USER['coords'];

    if ((abs($C['z']) >= 100) || (abs($C['x']) >= 100)) {
        umc_error("You need to be in the deathlands spawn area (100 blocks from the center)");
    }

    // get current period
    $P = umc_hardcore_get_period();

    $score_sql = "UPDATE minecraft_srvr.hardcore
        SET `clean_exit` = 1
        WHERE `uuid`='$uuid' AND entry_date >= '{$P['start_date']}' AND entry_date < '{$P['end_date']}'
        LIMIT 1;";

    umc_mysql_query($score_sql, true);
    umc_ws_cmd("warp spawn $player");
}

function umc_hardcore_commit() {
    global $UMC_USER;
    $uuid = $UMC_USER['uuid'];

    $rates = array(
        'diamond_block' => 100,
    );

    // always the current item
    $all_inv = $UMC_USER['inv'];
    $item_slot = $UMC_USER['current_item'];
    if (!isset($all_inv[$item_slot])) {
        umc_error("{red}You need to hold the item you want to commit! (current slot: $item_slot);");
    }
    // current held item
    $curr_item = $all_inv[$item_slot];

    if (isset($rates[$curr_item['item_name']])) {
        $inv = umc_check_inventory($curr_item['item_name'], $curr_item['data'], $curr_item['meta']);
        $amount = $inv;
        $block_value = $rates[$curr_item['item_name']];
        $item_txt = umc_goods_get_text($curr_item['item_name'], 0);
        umc_echo("{yellow}[!]{gray} You have {yellow}$inv {$item_txt['full_clean']}{gray} in your inventory, committing {yellow}$amount{gray} for {yellow}$block_value{gray} points each");
        umc_clear_inv($curr_item['item_name'], 0, $inv);
        $points = $inv * $block_value;
        umc_echo("{yellow}[!]{gray} You received $points points for this commit!");

        // get current period
        $P = umc_hardcore_get_period();

        $score_sql = "UPDATE minecraft_srvr.hardcore
            SET `score`=score+'$points'
            WHERE `uuid`='$uuid' AND entry_date >= '{$P['start_date']}' AND entry_date < '{$P['end_date']}'
            LIMIT 1;";

        umc_mysql_query($score_sql, true);
    } else {
        $itemlist = '';
        foreach ($rates as $item_name => $value) {
            $item_txt = umc_goods_get_text($item_name, 0);
            $itemlist .=  $item_txt['full_clean'] . "($value points) ";
        }
        umc_error("You cannot commit this item. The list of acceptable items are:" . $itemlist);
    }
}


function umc_hardcore_score() {
    $C = umc_hardcore_get_period();
    // query all scores inbetween the two dates
    $sql = "SELECT username, score FROM minecraft_srvr.hardcore
        LEFT JOIN minecraft_srvr.UUID ON hardcore.UUID=UUID.UUID
        WHERE entry_date >= '{$C['start_date']}' AND entry_date < '{$C['end_date']}'
        ORDER BY score DESC;";
    $D = umc_mysql_fetch_all($sql);

    umc_header("Hardcore Game score week {$C['number']}");
    $i = 1;
    foreach ($D as $d) {
        umc_echo("{yellow}$i {white}{$d['username']}{gray}: {$d['score']}");
        $i++;
    }
    umc_footer();
}

/**
 * Check if the current period's end date is today and if so delete the deathlands contents
 */
function umc_hardcore_resetworld() {
    $dates = umc_hardcore_get_period();

    $today_obj = umc_datetime();
    $today_str = $today_obj->format('Y-m-d 00:00:00');

    $end_date = $dates['end_date'];

    if ($end_date == $today_str) {
        $cmd = '"rm -R /home/minecraft/server/bukkit/deathlands/*"';
        exec($cmd);
        echo "Rest the hardcore world with the commend $cmd\n";
    }
}

/*
 * This determines which period we are in so that we know if it was used already
 *
 */
function umc_hardcore_get_period() {
    global $HARDCORE;
    $first_date_obj = umc_datetime($HARDCORE['first_date']);
    $interval = umc_timer_array_diff($first_date_obj);
    $days_count = $interval->format('%a');

    $period_length = $HARDCORE['period_length'];
    $period_no = floor($days_count / $period_length);
    $period_days_since = $period_no * $period_length;

    $first_date_obj->add(new DateInterval('P'.$period_days_since.'D'));
    $period_start_date = $first_date_obj->format('Y-m-d 00:00:00');
    $first_date_obj->add(new DateInterval('P'.$period_length.'D'));
    $period_end_date = $first_date_obj->format('Y-m-d 00:00:00');

    $retval = array(
        'number' => $period_no,
        'start_date' => $period_start_date,
        'end_date' => $period_end_date,
    );

    return $retval;
}


/**
CREATE TABLE IF NOT EXISTS `hardcore` (
  `hc_id` int(11) NOT NULL,
  `uuid` varchar(128) NOT NULL,
  `entry_date` datetime NOT NULL,
  `exit_date` datetime NOT NULL,
  `score` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `hardcore`
  ADD PRIMARY KEY (`hc_id`);

ALTER TABLE `hardcore`
  MODIFY `hc_id` int(11) NOT NULL AUTO_INCREMENT;
 */
