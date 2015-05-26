<?php

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
);

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
    $yaw = round($UMC_USER['coords']['yaw'], 1);

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
    $var = 22.5;
    $game_yaw = $yaw;
    if ($yaw < 0) {
        $yaw += 360;
    }
    $compass = false;
    foreach ($yaw_arr as $angle => $direction) {
        if (($yaw > ($angle - $var)) && ($yaw < ($angle + $var))) {
            $compass = $direction;
            break;
        }
    }
    // could not identify direction
    if (!$compass) {
        XMPP_ERROR_trigger("Could not idenfiy compass direction in /whereami");
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

    umc_echo("{green}Compass:{white} $compass {green}Yaw:{white} $yaw {green}Game-Yaw:{white} $game_yaw");
    umc_echo("{green}Coordinates:{white} x: $x,  y: $y,  z: $z");
    umc_echo("{green}Chunk:{white} x: $chunk_x, z: $chunk_z {green}Region:{white} x: $region_x, z: $region_z $map_str");
    umc_footer();
}

/*
 * displays a list of online players, optionally with their world
 */
function umc_info_who() {
    global $UMC_USER;
    $players = $UMC_USER['online_players'];
    $args = $UMC_USER['args'];

    $count = count($players);
    $data = umc_get_userlevel($players);
    if (!is_array($data)) { // we have only one user
        $data = array($players[0] => $data);
    }
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
            foreach ($user_info as $desc => $data) {
                if ($desc == 'Last Seen'){
                    if (in_array($user, $players)) {
                        $data = "$user is currently online";
                    } else {
                        $datetime = umc_datetime($data);
                        $diff = umc_timer_format_diff($datetime);
                        $data = $diff . " ago";
                    }
                }
                umc_echo("{green}$desc: {white}$data");
            }
            umc_footer();
            return;
        } else {
            umc_echo("{red}Error: Command/user not recognized");
        }
    }

    foreach ($data as $player => $level) {
        if (strstr($level, "DonatorPlus")) {
            $new_lvl = substr($level, 0, -11);
            $new_player = "$player{yellow}++{white}";
        } else if (strstr($level, "Donator")){
            $new_lvl = substr($level, 0, -7);
            $new_player = "$player{yellow}+{white}";
        } else {
            $new_lvl = $level;
            $new_player = $player;
        }
        if ($user_worlds) {
            $lower_username = strtolower($player);
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
        $cmd = "msg {$args[2]} Dear {$args[2]}, you have just asked a question that is answered on the website. "
        . "Instead of bothering everyone in game, please go there and look for the answer yourself: $UMC_DOMAIN - Thats why we have it;";
        umc_ws_cmd($cmd, 'asPlayer');
    } else {
        umc_echo("You have to name an online user to receive the message");
    }
}

function umc_info_settler() {
    global $UMC_USER;
    $args = $UMC_USER['args'];
    $players = $UMC_USER['online_players'];

    if (isset($args[2]) && in_array($args[2], $players)) {
        $cmd = "msg {$args[2]} Dear {$args[2]}, welcome to Uncovery Minecraft! "
        . "You have so far only gotten Guest-status to look around. If you want to start building,; please click here http://bit.ly/1GUDhgg and follow the instructions!";
        umc_ws_cmd($cmd, 'asPlayer');
    } else {
        umc_echo("You have to name an online user to receive the message");
    }
}