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
 * This is a small plugin that send various information to the client. It also
 * allows password resets w/o using wordpress
 */
global $UMC_SETTING, $WS_INIT;

$WS_INIT['info'] = array(  // the name of the plugin
    'disabled' => false,
    'events' => false,
    'default' => array(
        'help' => array(
            'title' => 'Information Commands',  // give it a friendly title
            'short' => 'Diverse Information commands.',  // a short description
            'long' => "Gives you information about the game and other users.", // a long add-on to the short  description
            ),
    ),
    'website' => array (
        'help' => array (
            'short' => 'Encourages users to read the website',
            'long' => "If a user is asking questions that are answered on the website, this command tells them to go there and look up the answer themselves. "
                . "Can be used with /mod website <player> or just /website <player>",
            'args' => '<player>',
        ),
        'function' => 'umc_info_website',
        'security' => array(
            'level'=>'Settler',
         ),
        'top' => true,
    ),
    'settler' => array (
        'help' => array (
            'short' => 'Tells people how to become settler',
            'long' => "If a user is is havong issues to become settler, you can use this command to send him instructions. ",
            'args' => '<player>',
        ),
        'function' => 'umc_info_settler',
        'security' => array(
            'level'=>'Settler',
         ),
        'top' => true,
    ),
    'who' => array (
        'help' => array (
            'short' => 'Improved "who" command',
            'long' => "Shows you who is online. Use as /mod who or /who. Add 'where' to show world users are in",
            'args' => '[where/player]',
        ),
        'function' => 'umc_info_who',
        'top' => true,
    ),
    'whereami' => array(
        'help' => array (
            'short' => 'Improved "compass" command',
            'long' => "Shows you where you are",
        ),
        'function' => 'umc_info_whereami',
        'top' => true,
    ),
    'setpass' => array(
        'help' => array (
            'short' => 'Set a new website password',
            'long' => "In case you have trouble getting your website password, you can set a new password with this command. It will return a URL that allows you to enter a new password.",
        ),
        'function' => 'umc_info_setpass',
        'top' => false,
    ),
);

function umc_info_setpass() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    global $UMC_USER;
    $uuid = $UMC_USER['uuid'];
    $user_login = umc_wp_get_login_from_uuid($uuid);

    // get userdata
    // this code is copied from wp-login.php, round line 325, grep for 'get_password_reset_key'
    $user_data = get_user_by('login', $user_login);
    $reset_key = get_password_reset_key($user_data);
    $url = network_site_url("wp-login.php?action=rp&key=$reset_key&login=" . rawurlencode($user_login), 'login');
    // shorten the URL
    $shortenedurl = file_get_contents('https://uncovery.me/s/shorten.php?longurl=' . urlencode($url));

    umc_header("Password Reset Link");
    umc_echo("Please click on the following link to set a new password:");
    umc_echo($shortenedurl);
    umc_footer();
}

function umc_info_checkpass_strength($password) {
    if (strlen($password) == 0) {
        return 1;
    }

    $strength = 0;

    $length = strlen($password); /*** get the length of the password ***/
    $strength = $length;

    if (strtolower($password) != $password) { /*** check if password is not all lower case ***/
        $strength += 1;
    }

    if (strtoupper($password) == $password) { /*** check if password is not all upper case ***/
        $strength += 1;
    }

    if ($length >= 8 && $length <= 15) { /*** check string length is 8 -15 chars ***/
        $strength += 1;
    } else if ($length >= 16 && $length <=35) { /*** check if lenth is 16 - 35 chars ***/
        $strength += 2;
    } else if ($length > 35) { /*** check if length greater than 35 chars ***/
        $strength += 3;
    }

    $numbers = false; /*** get the numbers in the password ***/
    preg_match_all('/[0-9]/', $password, $numbers);
    $strength += count($numbers[0]);

    $specialchars = false; /*** check for special chars ***/
    preg_match_all('/[|!@#$%&*\/=?,;.:\-_+~^\\\]/', $password, $specialchars);
    $strength += sizeof($specialchars[0]);

    /*** get the number of unique chars ***/
    $chars = str_split($password);
    $num_unique_chars = sizeof( array_unique($chars) );
    $strength += $num_unique_chars * 2;

    /*** strength is a number 1-10; ***/
    $top_strength = $strength > 99 ? 99 : $strength;
    $round_strength = floor($top_strength / 10 + 1);

    return $round_strength;
}

function umc_info_whereami() {
    global $UMC_USER, $UMC_SETTING;
    $world = $UMC_USER['world'];
    $player = $UMC_USER['username'];

    $x = round($UMC_USER['coords']['x'],1);
    $y = round($UMC_USER['coords']['y'],1);
    $z = round($UMC_USER['coords']['z'],1);

    // map coords
    $map_str = '';
    if (isset($UMC_SETTING['world_img_dim'][$world])) {
        $map = $UMC_SETTING['world_img_dim'][$world];
        $map_x = floor(conv_x($x, $map));
        $map_z = floor(conv_z($z, $map));
        $map_str = "{green}2D Map:{white} x: $map_x, z: $map_z";
    }

    $lot = umc_lot_get_from_coords($x, $z, $world);
    $lot_members = umc_get_lot_members($lot, false);
    $lot_owners = umc_get_lot_members($lot, true);
    // $lot_group_members = umc_lot_get_group_members($lot, false);
    // $lot_group_owners = umc_lot_get_group_members($lot, true);
    if (!$lot) {
        $lot = 'No lot here';
    }
    // chunk
    $chunk_x = floor($x / 16);
    $chunk_z = floor($z / 16);

    // region
    $region_x = floor($x / 512);
    $region_z = floor($z / 512);

    // Yaw
    $yaw = $UMC_USER['coords']['yaw'];
    // we need to convert this to work with 0-360 instead of +-180
    $yaw_360 = $yaw + 180;

    // -22.49969482421875 ?

    $yaw_arr = array(
        0 => 'South',
        45 => 'SouthWest',
        90 => 'West',
        135 => 'NorthWest',
        180 => 'North',
        225 => 'NorthEast',
        270 => 'East',
        315 => 'SouthEast',
        360 => 'South',
    );
    // angle difference for 45 degrees:
    $var = 22.5;
    $compass = false;
    foreach ($yaw_arr as $angle => $direction) {
        if (($yaw_360 > ($angle - $var)) && ($yaw_360 < ($angle + $var))) {
            $compass = $direction;
            break;
        }
    }
    // could not identify direction
    if (!$compass) {
        XMPP_ERROR_trigger("Could not identfiy compass direction in /whereami");
        $compass = "?";
    }

    // time
    $date_today = umc_datetime();
    $today = $date_today->format('Y-m-d H:i:s');

    $world_str = ucwords($world);
    umc_header("Location for $player");
    umc_echo("{green}World:{white} $world_str {green}Lot:{white} $lot {green}Date:{white} $today");
    if ($lot_owners) {
        umc_echo("{green}Lot Owner:{white} " . implode(", ", $lot_owners));
        if ($lot_members) {
            umc_echo("{green}Lot Members:{white} " . implode(", ", $lot_members));
        }
    } else {
        umc_echo("{green}Lot Owner:{white} Unoccupied lot");
    }

    umc_echo("{green}Compass:{white} $compass {green}Yaw:{white} $yaw {green}");
    umc_echo("{green}Coordinates:{white} x: $x,  y: $y,  z: $z");
    umc_echo("{green}Chunk:{white} x: $chunk_x, z: $chunk_z {green}Region:{white} x: $region_x, z: $region_z $map_str");
    umc_footer();
}

/*
 * displays a list of online players, optionally with their world
 */
function umc_info_who() {
    global $UMC_USER;

    $args = $UMC_USER['args'];

    // we predefine the array to make sure proper sorting
    $out_arr = array(
        'Guest' => array(),
        'Settler' => array(),
        'Citizen' => array(),
        'Architect' => array(),
        'Designer' => array(),
        'Master' => array(),
        'Elder' => array(),
        'Owner' => array()
    );

    $user_worlds = false;
    if (isset($args[2]) && $args[2] == 'where') {
        $user_worlds = umc_read_markers_file('array');
    } else if (isset($args[2])) { // single player info
        $user = umc_check_user($args[2]);
        if ($user) {
            $user_info = umc_get_userinfo($user);
            umc_header("User info for $user");
            $data_text = '';
            foreach ($user_info as $desc => $data) {
                if ($desc == 'Last Seen'){
                    if (isset($UMC_USER['player_data'][$user_info['uuid']])) {
                        $data_text = "$user is currently online";
                    } else {
                        $datetime = umc_datetime($data);
                        $diff = umc_timer_format_diff($datetime);
                        $data_text = $diff . " ago";
                    }
                } else {
                    $data_text = $data;
                }
                umc_echo("{green}$desc: {white}$data_text");
            }
            umc_footer();
            return;
        } else {
            umc_echo("{red}Error: Command/user not recognized");
        }
    }

    $players_data = $UMC_USER['player_data'];
    $count = count($players_data);

    foreach ($players_data as  $uuid => $players_details) {
        $level = umc_userlevel_get($uuid);
        $player = $players_details['Name'];

        $new_lvl = $level;
        if (strstr($level, "Donator")){
            $new_player = "$player{yellow}++{white}";
        } else {
            $new_player = $player;
        }
        $lower_username = strtolower($player);
        if ($user_worlds && isset($user_worlds[$lower_username])) {
            $new_player .= " {grey}({$user_worlds[$lower_username]['world']}){white}";
        }
        $out_arr[$new_lvl][] = $new_player;
    }
    umc_header("$count users online:");
    foreach ($out_arr as $level => $players) {
        if (count($players) > 0) {
            umc_echo("{green}$level: {white}" . implode(", ", $players));
        }
    }
    umc_footer();
    if ($user_worlds) {
        umc_echo("{blue}Try {grey}/who <player>{blue} for user details");
    } else {
        umc_echo("{blue}Try {grey}/where{blue} or {grey}/who <player>{blue} for more info");
    }
}

function umc_info_website() {
    global $UMC_USER, $UMC_DOMAIN;
    $args = $UMC_USER['args'];
    $players = $UMC_USER['online_players'];

    if (isset($args[2]) && in_array($args[2], $players)) {
        $msg = "Dear {$args[2]}, you have just asked a question that is answered on the website. "
        . "Instead of bothering everyone in game, please go there and look for the answer yourself: $UMC_DOMAIN - Thats why we have it;";
        umc_mod_message($args[2], $msg);
    } else {
        umc_echo("You have to name an online user to receive the message");
    }
}

function umc_info_settler() {
    global $UMC_USER;
    $args = $UMC_USER['args'];
    $players = $UMC_USER['online_players'];

    if (isset($args[2]) && in_array($args[2], $players)) {
        $msg = "Dear {$args[2]}, welcome to Uncovery Minecraft! "
        . "You have so far only gotten Guest-status to look around. If you want to start building,; please click here http://bit.ly/1GUDhgg and follow the instructions!";
        umc_mod_message($msg, 'asPlayer');
    } else {
        umc_echo("You have to name an online user to receive the message");
    }
}