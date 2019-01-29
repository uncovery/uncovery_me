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
 * This manges several mod commands such as banning users
 */

global $UMC_SETTING, $WS_INIT;

$WS_INIT['mod'] = array(  // the name of the plugin
    'disabled' => false,
    'events' => array(
        // we cannot read the ban file since we don't have JSON capability
        // 'server_post_reboot' => 'umc_mod_ban_to_database',
    ),
    'default' => array(
        'help' => array(
            'title' => 'Moderator Commands',  // give it a friendly title
            'short' => 'Different moderator commands.',  // a short description
            'long' => "Only Masters and Elders can execute most these.", // a long add-on to the short  description
            ),
        'security' => array(
            'level'=>'Master',
         ),
    ),
    'mute' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Mute a player.',
            'long' => "The player will be muted for a selected time (1h 30m 15m 10m 5m 1m) or for 1 hour.",
            'args' => '<player> [time]',
        ),
        'function' => 'umc_mod_mute',
        'security' => array(
            'level'=>'Master',
        ),
    ),
    'unmute' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Un-mute a player.',
            'long' => "This un-mutes a player.",
            'args' => '<player>',
        ),
        'function' => 'umc_mod_unmute',
        'security' => array(
            'level' =>'Master',
        ),
    ),
    'ban' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Bans a player.',
            'long' => "This bans a player from the server and sends a report & logfile to uncovery. If the user is not online, it automatically sends a ban request instead.",
            'args' => '<player> <reason>',
        ),
        'function' => 'umc_mod_ban',
        'security' => array(
            'level' => 'Elder',
        ),
    ),
    'banrequest' => array (
        'help' => array (
            'short' => 'Request a player to be banned',
            'long' => "If a user is annoying, you can request a ban. An email will be sent with the chat log to an admin and they will evaluate if a ban is in order.",
            'args' => '<player> [reason]',
        ),
        'function' => 'umc_mod_banrequest',
        'security' => array(
            'level'=>'Settler',
         ),
    ),
    'reclag' => array (
        'help' => array (
            'short' => 'Records current user locations to trace lag',
            'long' => "Records current user locations to trace lag",
            'args' => '',
        ),
        'function' => 'umc_mod_record_lag',
        'security' => array(
            'level'=>'Owner',
         ),
    ),
    'errormsg' => array (
        'help' => array (
            'short' => 'Trigger an error message',
            'long' => "Trigger an error message",
            'args' => '<message>',
        ),
        'function' => 'umc_mod_error_message',
        'security' => array(
           'level' => 'Elder'
        ),
    ),
    'whatsit' => array (
        'help' => array (
            'short' => 'Get details on the item in hand',
            'long' => "Get details on the item in hand",
            'args' => '<message>',
        ),
        'function' => 'umc_mod_whatsit',
        'security' => array(
           //'level' => 'Owner'
        ),
    ),
    'blockcheck' => array (
        'help' => array (
            'short' => 'Check the block info',
            'long' => "Check the block info",
            'args' => '<message>',
        ),
        'function' => 'umc_mod_blockcheck',
        'security' => array(
           'level' => 'Owner'
        ),
    ),
    'warp' => array (
        'help' => array (
            'short' => 'Warps you to a lot',
            'long' => "Warps you to a lot",
            'args' => '<lot>',
        ),
        'function' => 'umc_mod_warp_lot',
        'security' => array(
            'level'=>'Owner',
         ),
    ),
    'broadcast' => array(
        'help' => array (
            'short' => '',
            'long' => "",
            'args' => '',
        ),
        'function' => 'umc_mod_broadcast',
        'security' => array(
            'level'=>'Owner',
        ),
    ),
);

function umc_mod_error_message() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    // umc_exec_command($cmd, 'asConsole');
    $username = $UMC_USER['username'];

    $data = array(
        array('text'=>'Thanks for the test', 'format' => array('red', 'bold')),
        array('text'=>'Uncovery', 'format' => array('green', 'underlined', 'normal', 'open_url'=>'http://uncovery.me')),
        array('text'=>'received the message!', 'format' => array('red')),
    );
    umc_text_format($data, false, true);

    XMPP_ERROR_trigger('test');

    /*
    if (isset($UMC_USER['inv'][0]['nbt']) && $UMC_USER['inv'][0]['nbt']) {
        $inv = addslashes($UMC_USER['inv'][0]['nbt']);
        $item_name = $UMC_USER['inv'][0]['item_name'];
        $hover = '{id:minecraft:'.$item_name.',Damage:0,Count:1,tag:'.$inv.'}';
        $msg4 = umc_txt_hover($item_name, 'show_item', $hover);
        // $long_text = umc_nbt_display($inv, 'in_game');
        umc_tellraw($username, array($msg4), true);
    } else {
        umc_tellraw($username, array("No NBT Data found!"), true);
    }
    */
}

/**
 * Sends a message to all users in-game
 * This is a bridge so we do not need to change hundreds of lines of code
 * in case the chat plugin changes again.
 *
 * @param type $msg
 */
function umc_mod_broadcast($msg) {
    $chat_command = 'broadcast';
    // we can send several messages as an array.
    if (!is_array($msg)) {
        $msg = array($msg);
    }
    foreach ($msg as $line) {
        $str = preg_replace_callback(color_regex(), create_function('$matches', 'return color_map($matches[1]);'), $line);
        // $str = preg_replace(color_regex() . "e", 'color_map(\'$1\')', $line);
        $full_command = "$chat_command $str;";
        umc_exec_command($full_command, 'asConsole');
    }
}

/**
 * Command to send a message to a specific user.
 * See http://minecraft.gamepedia.com/Commands#Raw_JSON_text for more options
 *
 * @param type $user
 * @param type $message
 */
function umc_mod_message($user, $message) {
    $cmd = "tellraw $user {\"text\":\"$message\",\"bold\":false}";
    umc_exec_command($cmd, 'asConsole');
}

function umc_mod_banrequest() {
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $args = $UMC_USER['args'];

    if (!isset($args[2])) {
        umc_show_help($args);
        return;
    } else {
        $user = umc_check_user($args[2]);
        if (!$user) {
            umc_error("{red}The user {$args[2]} does not exist! See {yellow}/helpme mod");
        }
    }
    // concatenate multi-word reasons
    $reason = "";
    if (!isset($args[3])) {
        umc_error("You need to give a reason for the ban request!");
    }
    for ($i=3; $i<count($args); $i++) {
        $reason .= " " . $args[$i];
    }
    $subject = "[Uncovery Minecraft] Ban Request for $user";
    $content = "User $player has requested $user to be banned!\r\n"
        . "Reason: $reason\r\nLogfile:\r\n";
    $content .= umc_attach_logfile($user);

    $headers = 'From:minecraft@uncovery.me' . "\r\nReply-To:minecraft@uncovery.me\r\n" . 'X-Mailer: PHP/' . phpversion();
    mail('minecraft@uncovery.me', $subject, $content, $headers, "-fminecraft@uncovery.me");
    umc_header("Ban request for $user", true);
    umc_echo("An email with a logfile has been sent to the admin. Action will be taken if appropriate!", true);
    umc_footer(true);
    umc_log('mod', 'banrequest', "$player sent request for $user because of $reason");
}

function umc_mod_ban() {
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $args = $UMC_USER['args'];

    if ($player == 'riedi73') {
        echo "Sorry Riedi, rerouting to ban request :)";
        return umc_mod_banrequest();
    }

    if (!isset($args[2])) {
        umc_show_help($args);
        return;
    } else {
        $user = strtolower(umc_check_user($args[2]));
        if (!$user) {
            umc_error("{red}The user {$args[2]} does not exist! See {yellow}/helpme mod");
        }
    }
    $admin_arr = array('uncovery', '@Console', '@console');
    if (!in_array($user, $UMC_USER['online_players']) && !in_array($player, $admin_arr)) {
        umc_echo("Dear $player, the user {$args[2]} is not currently online, a banrequest will be sent instead!", true);
        umc_mod_banrequest();
        return;
    }

    // concatenate multi-word reasons
    if (!isset($args[3])) {
        umc_error("You need to give a reason for the ban request!");
    }
    $reason = "";
    for ($i=3; $i<count($args); $i++) {
        $reason .= " " . $args[$i];
    }
    $uuid = umc_user2uuid($user);
    $reason_text = trim($reason);
    $subject = "[Uncovery Minecraft] Ban of $user by $player";
    $content = "User $player has banned $user for reason: $reason_text!\r\n"
        . "Logfile:\r\n";
    $content .= umc_attach_logfile($user);

    $headers = 'From:minecraft@uncovery.me' . "\r\nReply-To:minecraft@uncovery.me\r\n" . 'X-Mailer: PHP/' . phpversion();
    mail('minecraft@uncovery.me', $subject, $content, $headers, "-fminecraft@uncovery.me");
    umc_header("Ban of $user by $player", true);
    umc_echo("Thanks for the ban! An email with a logfile has been sent to the admin.", true);
    umc_footer(true);
    umc_user_ban($uuid, $reason);
    // trigger plugin-even userban
}

/*
 * this function makes sure that all banned users from the text file
 * are actually in the database
 */
function umc_mod_ban_to_database() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    return;
    global $UMC_SETTING;
    $ban_file = json_decode(file($UMC_SETTING['banned_players_file']));
    $banned_db = umc_banned_users();
    foreach ($ban_file as $D) {
        $uuid = $D['uuid'];
        $name = strtolower($D['name']);
        $date = $D['created'];
        $source = $D['source'];
        $reason = $D['reason'];
        $admin = $D['admin'];
        if (!in_array($uuid, $banned_db)) {
            $sql = "INSERT INTO minecraft_srvr.`banned_users`(`username`, `reason`, `admin`, `date`, `uuid`, `source`)
                VALUES ('$name','$reason', '$admin', '$date', '$uuid', '$source');";
            umc_mysql_query($sql, true);
        }
    }
    /* format:
     *   {
    "uuid": "18d29691-51f1-4166-b2cb-46cab2b9fba0",
    "name": "iLoveMCPigs",
    "created": "2013-12-20 11:00:54 +0800",
    "source": "(Unknown)",
    "expires": "forever",
    "reason": "Banned by an operator."
  },

     */
}


function umc_attach_logfile($username) {
    global $UMC_PATH_MC;
    // read the logfile
    $content = '';
    umc_echo("starting searching for log entries of user $username");

    exec("zgrep $username $UMC_PATH_MC/server/bukkit/logs/*", $oldlog_array);
    $file_lines = file("$UMC_PATH_MC/server/bukkit/logs/latest.log");
    array_push($oldlog_array, $file_lines);

    umc_echo(count($file_lines) . " lines read");
    $i = 0;
    foreach ($file_lines as $line) {
        if (stristr($line, $username)) {
            $content .= $line;
            $i ++;
        }
    }

    array_push($oldlog_array, $file_lines);
    umc_echo ("logfile found and attached to email");
    return $content;
}

/*
 * this command is executed by the clearlag plugin to record user locations during lag
 */
function umc_mod_record_lag() {
    global $UMC_PATH_MC;
    $file = "$UMC_PATH_MC/server/bin/data/markers.json"; // $UMC_SETTING['markers_file'];
    $text = file_get_contents($file);
    $m = json_decode($text);

    foreach ($m as $data) {
        $sql = "INSERT INTO `minecraft_srvr`.`lag_location` (`location_id`, `x_coord`, `y_coord`, `z_coord`, `date`, `world`)
            VALUES (NULL, '{$data->x}', '{$data->y}', '{$data->z}', CURRENT_TIMESTAMP, '{$data->world}');";
        umc_mysql_query($sql);
    }
}


function umc_mod_mute() {
    global $UMC_USER;

    $player = $UMC_USER['username'];
    $args = $UMC_USER['args'];

    $timelist = array('1h' => 3600, '30m' => 1800, '15m' => 900, '10m' => 600, '5m' => 300, '1m' => 60);

    if (!isset($args[2])) {
        umc_show_help($args);
        return;
    } else {
        $user = umc_check_user($args[2]);
        if (!$user) {
            umc_error("{red}The user {$args[2]} does not exist! See {yellow}/helpme mod");
        }
    }


    if (!isset($args[3])) {
        $time = '1h';
    } else {
        $time = $args[3];
    }

    if (!isset($timelist[$time])) {
        umc_error("You have to chose the time from this list: 1h 30m 15m 10m 5m 1m");
    }

    $seconds = $timelist[$time];

    $uuid = umc_user2uuid($user);
    umc_ws_cmd("essentials:mute $user $time", 'asConsole');
    umc_ws_cmd("pex user $uuid timed add -venturechat.* $seconds;", 'asConsole');
    umc_ws_cmd("pex user $uuid timed add -irc.* $seconds;", 'asConsole');
    umc_ws_cmd("pex user $uuid timed add -essentials.msg $seconds;", 'asConsole');
    umc_ws_cmd("pex user $uuid timed add -essentials.me $seconds;", 'asConsole');
    umc_echo("The user $user has been muted for $time!");
    umc_log('mod', 'mute', "$player muted $user for $time");
}

/*

This requires libyaml.
Install by doing:

 - yum install libyaml libyaml-devel
 - pecl install yaml
 - add extension=yaml.so to php.ini or create /etc/php.d/yaml.ini with that line in it
 - restart webserver

*/
function umc_mod_unmute() {
    global $UMC_USER, $UMC_PATH_MC;
    // umc_echo('Unmuting...');
    $player = $UMC_USER['username'];
    $args = $UMC_USER['args'];

    if (!isset($args[2])) {
        umc_show_help($args);
        return;
    } else {
        $user = umc_check_user($args[2]);
        if (!$user) {
            XMPP_ERROR_trigger("$player tried to un-mute $user, but $user does not exist!");
            umc_error("{red}The user {$args[2]} does not exist! See {yellow}/helpme mod");
        }
    }
    // umc_echo('checks done... ');
    $user_uuid = umc_uuid_getone($user);

    $file = "$UMC_PATH_MC/server/bukkit/plugins/Essentials/userdata/" . $user_uuid . ".yml";
    $txt = file_get_contents($file);
    $search = "muted: true";
    if (strstr($txt, $search)) {
    // YAML library is not installed,
    //$yml = yaml_parse_file($file);
        //if ($yml['muted'] == 'true') {
        $uuid = umc_user2uuid($user);
        umc_ws_cmd("essentials:mute $user", 'asConsole');
        umc_ws_cmd("pex user $uuid timed remove -venturechat.*;", 'asConsole');
        umc_ws_cmd("pex user $uuid timed remove -irc.*;", 'asConsole');
        umc_ws_cmd("pex user $uuid timed remove -essentials.msg;", 'asConsole');
        umc_ws_cmd("pex user $uuid timed remove -essentials.me;", 'asConsole');
        umc_echo("The user $user has been un-muted!");
        umc_log('mod', 'un-mute', "$player un-muted $user");
    } else {
        umc_log('mod', 'un-mute', "$player tried to un-mute $user, but $user was not muted!");
        umc_error("User $user was not muted!");
    }
}

/**
 * Give the user every possible block and make sure that he actually got it!
 */
function umc_mod_blockcheck() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $username = $UMC_USER['username'];

    umc_ws_cmd("ci $username;", 'asConsole');

    $result['item_name'] = 'air';
    if (!isset($UMC_USER['args'][1])){
        $start = 0;
    } else {
        $start = $UMC_USER['args'][1];
    }

    umc_echo("Starting to fill inventory!");

    $result = umc_mod_blockcheck_fill_inv($start);
    $count = $result['count'];
    umc_echo("Filled inventory until " . $result['item_name'], ", at number $count");

    umc_mod_blockcheck_check_inv($count);
    XMPP_ERROR_trigger("Blockcheck Done!");
}

function umc_mod_blockcheck_check_inv($count) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER, $UMC_DATA;
    $inv = $UMC_USER['inv'];

    $error = false;
    $inv_count = count($inv);
    if ($inv_count < 36) {
        $error = true;
        umc_echo ("Not all items were given ($inv_count), checking for errors!");
    } else {
        umc_echo("All items handed over, please check for valid items!");
    }

    foreach ($inv as $slot => $I) {
        // iterate inventory and check if every item exists and if one was skipped (i.e. do we have an empty slot?)
        $item_name = $I['item_name'];
        if (!isset($UMC_DATA[$item_name])) {
            umc_echo("Chould not find Inv slot $slot item!");
            $error = true;
        }
    }
    if (!$error) {
        umc_echo("Please continue with number $count!");
    }
}

function umc_mod_blockcheck_fill_inv($start) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_DATA, $UMC_USER;

    $username = $UMC_USER['username'];
    $inv_slots = 36;
    $c = 0;
    $started = false;
    foreach ($UMC_DATA as $item_name => $D) {
        if ($c >= $start && !$started) {
            $started = true;
        }
        $id = 0;
        if (!isset($D['subtypes'])) {
            $D['subtypes'] = array(0 => array('name' => $item_name, 'avail' => $D['avail']));
        }
        foreach ($D['subtypes'] as $id => $d) {
            // we assume that the current availability information is correct
            $avail = $d['avail'];
            if ($avail) {
                umc_ws_cmd("give $username $item_name:$id 1;", 'asConsole');
                $c++;
            }
            // we bail if we filled the inventory
            // and return the current position in the array
            if ($c == $inv_slots) {
                return array('item_name' => $item_name, 'id' => $id, 'count' => $c);
            }
        }
    }
}


function umc_mod_warp_lot() {
    global $UMC_USER;
    $args = $UMC_USER['args'];
    if (!isset($args[2])) {
        umc_show_help($args);
        return;
    }
    $lot = strtolower($args[2]);
    $world = umc_get_lot_world($lot);

    $playerworld = $UMC_USER['world'];
    if ($world != $playerworld) {
        umc_ws_cmd("mv tp $world", 'asPlayer');
    }
    $sql = "SELECT min_x, min_z FROM minecraft_worldguard.`region_cuboid` WHERE region_id='$lot';";
    $D = umc_mysql_fetch_all($sql);
    $row = $D[0];
    $x = $row['min_x'];
    $z = $row['min_z'];
    $y = 70;
    umc_ws_cmd("tppos $x $y $z 135 0 $playerworld", 'asPlayer');
}

function umc_mod_command($player) {
    $chances = array(
        'trick' => array(
            "smite $player 2" => "$player was struck by lightning!",
            "burn $player" => "$player has caught fire!",
            "kick $player" => "$player as been kicked!",
            "spawnmob creeper 3 $player" => "$player is suddenly in VERY bad company!",
            "spawnmob zombie  3 $player" => "$player is suddenly in bad company!",
            "tempban $player 5 minutes" => "$player was banned for 5 minutes!",
            "tjail $player 10 minutes" => "$player was jailed for 10 minutes!",
        ),
        'treat' => array(
            "heal player" => "$player was healed!",
            "feed player" => "$player was fed!",
            "exp $player give 50 " => "$player received 50 XP!",
            "spawnmob cat 1 $player" => "$player has a new cat!",
            "spawnmob dog 1 $player" => "$player has a new dog!",
        ),
    );
}
