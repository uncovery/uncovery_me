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
 * This manages the skyblock functions. The Challenges are not enabled yet.
 */
global $UMC_SETTING, $WS_INIT, $UMC_DOMAIN;

$WS_INIT['skyblock'] = array(
    'disabled' => false,
    'events' => false,
    'default' => array(
        'help' => array(
            'title' => 'Build on a skyblock',
            'short' => 'Manage your skyblock lot.',
            'long' => 'Once you reserved a skyblock lot on the website, you can use these commands to wapr there and to abandon it.',
            ),
    ),
    'warp' => array(
        'help' => array(
            'short' => 'Teleports you to a skyblock;',
            'args' => '<lot name>',
            'long' => 'See the 2D map to find the lot name you want to teleport to. Exmaple: block_k11',
        ),
        'function' => 'umc_skyblock_warp',
    ),
    'abandon' => array(
        'help' => array(
            'short' => 'Abandons your own skyblock;',
            'args' => '<lot name>',
            'long' => 'Use the name of your lot to abandon your skyblock lot. You will only be able to register another one after the next reboot!',
        ),
        'function' => 'umc_skyblock_abandon',
    ),
    'serialize' => array (
        'help' => array (
            'short' => 'Serializes your inv and sends it by email',
            'long' => "Serializes your inv and sends it by email",
            'args' => '',
        ),
        'function' => 'umc_skyblock_challenge_serialize_inv',
        'security' => array(
            'level'=>'Owner',
         ),
    ),
    'select' => array (
        'help' => array (
            'short' => 'Select a challenge to play',
            'long' => "This let's you chose a challenge that you will then play. See challenge IDs here: $UMC_DOMAIN/contests-games/skyblock-challenges/",
            'args' => '<challenge id>',
        ),
        'function' => 'umc_skyblock_challenge_select',
        'security' => array(
            'level'=>'Owner',
            'worlds' => array('skyblock'),
         ),
    ),
    'cancel' => array (
        'help' => array (
            'short' => 'Cancels your current skyblock challenge',
            'long' => "Unstarted challenges will be removed. Started ones (except finished subchallenged) will be failed.",
        ),
        'function' => 'umc_skyblock_challenge_select',
        'security' => array(
            'level'=>'Owner',
            'worlds' => array('skyblock'),
         ),
    ),
    'submit' => array (
        'help' => array (
            'short' => 'Checks your inventory against the challenge goals',
            'long' => "You need to have the winning condition items in your inventory.",
        ),
        'function' => 'umc_skyblock_challenge_submit',
        'security' => array(
            'level'=>'Owner',
            'worlds' => array('skyblock'),
         ),
    ),

);

function umc_skyblock_warp(){
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $world = $UMC_USER['world'];
    $args = $UMC_USER['args'];

    if (!isset($args[2])){
        umc_show_help($args);
        return;
    } else if ($world !=='skyblock') {
        umc_error('You need to be in the skyblock world to teleport!');
    } else {
        $lot = umc_sanitize_input($args[2], 'lot');
        $check = umc_check_lot_exists('skyblock', $lot);
        if (!$check) {
            umc_error("The lot you entered does not exist!");
        }
        if ($lot == 'block_k11') {
            umc_error('You cannot warp to that lot!');
        }
    }

    $sql = "SELECT * FROM minecraft_worldguard.world LEFT JOIN minecraft_worldguard.region ON world.id=region.world_id
        LEFT JOIN minecraft_worldguard.region_cuboid ON region.id=region_cuboid.region_id
        WHERE world.name='skyblock' AND region.id = '$lot' ";

    $D = umc_mysql_fetch_all($sql);
    $lots = $D[0];

    $c_x = $lots['min_x'] + 64;
    $c_z = $lots['min_z'] + 64;
    $c_y = 256;

    $cmd = "tppos $player $c_x $c_y $c_z 0 0 skyblock";
    umc_ws_cmd($cmd, 'asConsole');
    umc_pretty_bar("darkblue", "-", "{darkcyan} Warping to skyblock");
    umc_echo("You are now on skyblock $lot!");
    umc_footer();
}

function umc_skyblock_abandon(){
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $args = $UMC_USER['args'];

    if (!isset($args[2])){
        umc_show_help($args);
        return;
    } else {
        $lot = umc_sanitize_input($args[2], 'lot');
        $check = umc_check_lot_exists('skyblock', $lot);
        if (!$check) {
            umc_error("The lot you entered does not exist!");
        }
        if ($lot == 'block_k11') {
            umc_error('You cannot warp to that lot!');
        }
    }

    //check if the user abandoned already
    $abandon_id = umc_get_worldguard_id('user', '_abandoned_');
    $abandon_sql = "SELECT * FROM minecraft_worldguard.region_players WHERE region_id = '$lot' AND Owner=1 AND user_id=$abandon_id;";
    $D3 = umc_mysql_fetch_all($abandon_sql);
    $num = count($D3);
    if  ($num > 0) {
        umc_error("You abandoned the entry $lot already!");
    }

    // make sure the user actually owns this enrty
    $user_id = umc_get_worldguard_id('user', strtolower($player));
    // find out if the user can have additional contest entries in this contest
    $sql = "SELECT * FROM minecraft_worldguard.world LEFT JOIN minecraft_worldguard.region ON world.id=region.world_id
        LEFT JOIN minecraft_worldguard.region_cuboid ON region.id=region_cuboid.region_id
        LEFT JOIN minecraft_worldguard.region_players ON region_cuboid.region_id=region_players.region_id
        LEFT JOIN minecraft_worldguard.user ON region_players.user_id=user.id
        WHERE region.id LIKE '$lot' AND Owner=1 AND user.id=$user_id";
    $D = umc_mysql_fetch_all($sql);
    if (count($D) != 1) {
        umc_error("You do not own the lot $lot in skyblock!");
    } else {
        $row = $D[0];
    }

    $world_id = $row['world_id'];
    $ins_user_sql = "INSERT INTO minecraft_worldguard.region_players (region_id, world_id, user_id, Owner)
        VALUES ('$lot', $world_id, $abandon_id, 1);";
    umc_mysql_query($ins_user_sql, true);
    umc_ws_cmd('region load -w skyblock', 'asConsole');
    umc_echo("You have succcessfully abandoned the lot $lot! It will be reset with the next reboot. You can then register a new one!");
}

/*
 * This lets the user chose a challenge. That will give him the lot, potentially abandon others and allow him to teleport there
 * but it will only copy in the biome etc after the next restart so the user has to wait for that
 */
function umc_skyblock_challenge_select() {
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $args = $UMC_USER['args'];

    if (!is_numeric($args[2])) {
        umc_error("Your challenge ID needs to be a number!");
    } else {
        $lot_sql = "SELECT region_cuboid.region_id as lot FROM `region_cuboid`
            LEFT JOIN region_players ON region_cuboid.region_id=region_players.region_id
            WHERE user_id IS NULL
		AND region_cuboid.`region_id` LIKE 'block%'
		AND min_z<-768
		AND min_x>=-1152
		AND max_x<1024;";
        $D = umc_mysql_fetch_all($lot_sql);
        if (count($D) == 0) {
            XMPP_ERROR_trigger("We ran out of challenge lots!");
            umc_error("Sorry, there are currently no challenge lots free!");
        } else {
            $lot_row = $D[0];
            $challenge_lot = $lot;
        }


        $id = $args[2];
        $sql = "SELECT * FROM minecraft_quiz.block_challenges WHERE challenge_id=$id;";
        $rst = umc_mysql_query($sql);
        $row = umc_mysql_fetch_array($rst);
        $lot = $row['lot'];
        $biome = $row['biome'];
        $inventory = $row['inventory'];
        $name = $row['name'];
        $desc = $row['desc'];
        $win_conditions = $row['win_conditions'];
        umc_header("Challenge $id: $name");
        umc_echo("{white}$desc");
        $lot_str = $lot;
        if ($lot == null) {
            $lot_str = 'standard';
        }
        umc_echo("{green}Lot type: {white}$lot_str");
        $biome_str = $biome;
        if ($biome == null) {
            $biome_str = 'standard';
        }
        umc_echo("{green}Lot type: {white}$biome_str");
        if (umc_skyblock_web_display_table($id)) {
            umc_echo("{green}Sub challenges: {white}This challenge has subchallenges. Please see the website for details.");
        }
        $inv_str = umc_skyblock_inv_to_desc($inventory);
        umc_echo("{green}Starting Inventory:{white}$inv_str");
        $winstr = umc_skyblock_inv_to_desc($win_conditions);
        umc_echo("{green}Winning conditions:{white}$winstr");

        $sub_challenge = $row['sub_challenge'];
        $challenge = $id;
        if ($sub_challenge !== null) {
            $challenge = $sub_challenge;
        }
        $sql = "INSERT INTO `minecraft_quiz`.`block_games` (`game_id`, `username`, `start`, `end`, `status`, `challenge_id`, `sub_challenge_id`, `lot`)
            VALUES (NULL, '$player', NOW(), NULL, 'selected', '$challenge', '$sub_challenge', '$challenge_lot');";
        umc_mysql_query($sql, true);
        umc_echo("Please type {green}/skyblock start{white} or {green}/skyblock cancel");
        umc_footer();
    }
}

/*
 * this cancels the challenge. If it has been started, it will fail.
 * If it has only been selected, it will be removed.
 */
function umc_skyblock_challenge_cancel() {
    $sql = "UPDATE `minecraft_quiz`.`block_games` SET status='cancelled' WHERE status IN ('selected','started') AND username='$player';";
    umc_mysql_query($sql);
    umc_echo("Your unfinished challenges have been cancelled");
}

/*
 * This will clear the users inventory and fill it with the challenge-inventory.
 * requires the user to be in his lot
 * This has to check if the right biome for the challenge is in place or if we have to wait for reboot.
 */
function umc_skyblock_challenge_start() {


}

/*
 * will check the user if he has the required items in his inventory and finishes the challenge
 * otherwise challenge will stay open
 */
function umc_skyblock_challenge_submit() {
    global $UMC_USER;
    $inv = $UMC_USER['inv'];

}


/*
 * This checks if the winning conditions for a challenge are met
 */
function umc_skyblock_challenge_win_check(){

}


/*
 * This is a moderator function that serializes the inventory and sends it by email
 * so that it can be added to the database
 */
function umc_skyblock_challenge_serialize_inv() {
    global $UMC_USER;
    $inv = $UMC_USER['inv'];
    umc_echo("Done!");
}

/*
 * Lists the challenges and best times on the website
 */
function umc_skyblock_web_display() {
    return '';
    $sub_id = 0;
    $out = "<table>\n<tr><th>ID & Name</th><th colspan=2>Description</th></tr>\n";
    $out .= umc_skyblock_web_display_table($sub_id);
    $out .= "</table>\n";
    return $out;
}

/*
 * recursive table display
 */
function umc_skyblock_web_display_table($sub_id) {
    $sql = "SELECT * FROM minecraft_quiz.block_challenges WHERE sub_challenge=$sub_id ORDER BY challenge_id";
    $D = umc_mysql_fetch_all($sql);
    $out = '';
    if (count($D) == 0) {
        return false;
    }
    $padding = ' colspan=2';
    $tab = '';
    if ($sub_id > 0) {
        $padding = '';
        $tab = '<td style="width:50px"></td>';
    }

    foreach ($D as $row) {
        if ($row['win_conditions'] == NULL) {
            $winning = 'No Winning conditions, open game';
        } else {
            $winning = umc_skyblock_inv_to_desc($row['win_conditions']);
        }
        if ($sub_id == 0) {
            $out .= "<tr style=\"background-color:#99ccff; font-weight:bold;\">$tab<td style=\"white-space:nowrap;\">{$row['challenge_id']}: {$row['name']}</td><td$padding>{$row['desc']}</td></tr>\n";
            $inventory = umc_skyblock_inv_to_desc($row['inventory']);
            $out .= "<tr style=\"font-size:70%;\"><td><strong>Starting Inventory:</strong></td><td$padding>$inventory</td></tr>\n";
            $out .= "<tr style=\"font-size:70%;\">$tab<td><strong>Winning items:</strong></td><td$padding>$winning</td></tr>\n";
        } else {
            $out .= "<tr style=\"font-size:70%;\"><td><strong>Name: {$row['name']}</strong></td><td><strong>Winning items:</strong> $winning</td></tr>\n";
        }

        $id = $row['challenge_id'];
        $sub_challenge = umc_skyblock_web_display_table($id);
        if ($sub_challenge) {
            $out .= "<tr style=\"background-color:#C2E0FF; font-size:90%;\"><td>Sub-challenges:</td><td style=\"font-size:70%\">(continue to above challenge until you get all below targets!)</td></tr>" . $sub_challenge;
        }
    }
    return $out;
}

function umc_skyblock_inv_to_desc($inv) {
    $inv_arr = unserialize($inv);
    // aggregate items
    $out_arr = array();
    foreach ($inv_arr as $data) {
        $id = $data['id'];
        $type = $data['data'];
        $amount = $data['amount'];
        $meta = $data['meta'];
        if (isset($out_arr[$id][$type][$meta])) {
            $out_arr[$id][$type][$meta] += $amount;
        }

    }
    // now iterate and get text
    $meta = '';
    $final_arr = array();
    foreach ($out_arr as $id => $types) {
        foreach ($types as $type => $metas) {
            $item = umc_goods_get_text($id, $type);
            $final_arr[] = "$amount " . $item['full'];
        }
    }
    return implode(", ", $final_arr);
}
?>
