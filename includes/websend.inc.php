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
 * This function is a central interface to all things Websend. Anything that comes
 * from websend or goes to the game through websend passes throug here.
 */

/**
 * Checks if there is a valid connection from websend
 *
 * @return boolean
 */
function umc_ws_auth() {

    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $checkpass = file_get_contents("/home/includes/certificates/websend_code.txt");
    $hashAlgorithm = "sha512";

    $s_post  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
    if (!isset($s_post['authKey'])) {
        return false;
    } else {
        $receivedHash = $s_post['authKey'];
    }

    // check if hash is empty
    if ($receivedHash == "") {
        XMPP_ERROR_send_msg("Websend authKey is empty!");
        return false;
    }

    // check if has is valid
    if ($receivedHash !== hash($hashAlgorithm, $checkpass)) {
        XMPP_ERROR_send_msg("Websend authKey is invalid!");
        return false;
    }

    return true;
}

// ============ Main Dispatching ====================

function umc_websend_main() {
    global $UMC_USER;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $main = $UMC_USER['args'][0];
    switch ($main) {
        case 'event':
            $event = $UMC_USER['args'][1];
            umc_ws_eventhandler($event);
            break;
        case 'inventory':
            umc_show_inventory();
            break;
        case 'help':
            umc_show_help();
            break;
        default:
            // check in the plugin folder items for existing commands
            $function = "umc_wsplg_$main";
            if (function_exists($function)) { // This plugin wants to handle its own dispatching
                $function();
            } else {
                umc_wsplg_dispatch($main); // Attempt to use global dispatching
            }
    }
}


/*
 * This handles automated WSEVENTS events for plugins.
 */
function umc_ws_eventhandler($event) {
    global $UMC_USER;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    $player = $UMC_USER['username'];

    // run plugin events
    umc_plugin_eventhandler($event);

    // non-plugin events
    switch ($event) {
        case 'PlayerQuitEvent':
            umc_log('system', 'logout', "$player logged out");
            umc_uuid_record_usertimes('lastlogout');
            break;
        case 'PlayerJoinEvent':
            umc_uuid_check_usernamechange($UMC_USER['uuid'], $UMC_USER['username']);
            umc_uuid_check_history($UMC_USER['uuid']);
            umc_log('system', 'login', "$player logged in");
            umc_uuid_record_usertimes('lastlogin');
            // check if the user has a skin stored, if not, get it
            umc_usericon_get($UMC_USER['uuid'], false);
            break;
        case 'PlayerPreLoginEvent':
            // nothing needed since the fact that websend is called makes it register the UUID already
            break;
        default:
            // all the events not covered above
            // XMPP_ERROR_send_msg("Event $event not assigned to action (umc_ws_eventhandler)");
    }
}

/**
 * This command runs on every user interaction with websend.
 * It fills all the user variables so we can use them in other functions.
 *
 * @global type $UMC_ENV
 * @global type $UMC_USER
 * @global type $UMC_USERS
 */
function umc_ws_get_vars() {
    // make sure we are on websend
    global $UMC_ENV, $UMC_USER; //, $UMC_USERS;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    if ($UMC_ENV !== 'websend') {
        XMPP_ERROR_trigger("Tried to get websend vars, but environment did not match: " . var_export($UMC_ENV, true));
        die('umc_ws_get_vars');
    }

    // TODO: find an appropriate filter to sanitize this data
    // Since the input is authenticated with the code, it should be fine, but better safe than sorry
    $json = json_decode(stripslashes($_POST["jsonData"]), true);
    if (!isset($json['Invoker']['Name'])) {
        XMPP_ERROR_trigger("No invoker name in " . var_export($json,true));
    }
    if ($json['Invoker']['Name'] == '@Console') {
        $UMC_USER['username'] = '@console';
        $UMC_USER['userlevel'] = 'Owner';
        $UMC_USER['donator'] = 'Donator';
        $UMC_USER['uuid'] = 'Console0-0000-0000-0000-000000000000';
    } else {
        $UMC_USER['username'] = $json['Invoker']['Name'];
        if (isset($json['Invoker']['UUID'])) {
            $uuid = $json['Invoker']['UUID'];
        } else {
            // this is mostly used for pre-logins. it will check if the user exists and add them to the table if not.
            XMPP_ERROR_trace("Getting UUID for UMC_USER array", $json);
            $uuid = umc_user2uuid($json['Invoker']['Name']);
        }


        $UMC_USER['uuid'] = $uuid;
        $UMC_USER['userlevel'] = umc_get_uuid_level($uuid);
        if (strstr($UMC_USER['userlevel'], 'Donator')) {
            $UMC_USER['donator'] = 'Donator';
        } else {
            $UMC_USER['donator'] = false;
        }
        // following only applies for in-game users
        if (isset($json['Invoker']['Location'])) {
            $UMC_USER['current_item'] = $json['Invoker']['CurrentItemIndex'];
            $UMC_USER['mode'] = $json['Invoker']['GameMode'];
            $UMC_USER['world'] = $json['Invoker']['Location']['World'];
            $UMC_USER['coords'] = array(
                    'x' => $json['Invoker']['Location']['X'],
                    'y' => $json['Invoker']['Location']['Y'],
                    'z' => $json['Invoker']['Location']['Z'],
                    'yaw' => umc_ws_yaw_fix($json['Invoker']['Location']['Yaw']),
            );

            // xp converted to points value obtained total. JSON returns fractional value.
            $UMC_USER['xplevel'] = $json['Invoker']['XPLevel'];
            $UMC_USER['xpfraction'] = $json['Invoker']['XP'];
            $user_xp = umc_plugin_eventhandler('ws_user_init_xp', array('xp' => $json['Invoker']['XP'], 'xplevel' => $json['Invoker']['XPLevel']));
            $UMC_USER['xp'] = $user_xp[0];

            //IP Address
            $ip_raw = $json['Invoker']['IP']; // ip ⇒ "/210.176.194.100:11567"
            $ip_matches = false;
            $pattern = "/^\/((?:[0-9]{1,3}\.){3}[0-9]{1,3})/";
            preg_match($pattern, $ip_raw, $ip_matches);
            $UMC_USER['ip'] = $ip_matches[1];

        }
        $UMC_USER['inv'] = array();
        if (isset($json['Invoker']['Inventory'])) {
            $UMC_USER['inv'] = umc_ws_get_inv($json['Invoker']['Inventory']);
            $UMC_USER['current_item'] = $json['Invoker']['CurrentItemIndex'];
        }
    }
    // import command arguments
    foreach ($_POST['args'] as $arg) {
        $UMC_USER["args"][] = filter_var(trim($arg), FILTER_SANITIZE_STRING);
    }

    // online players; we do not retrieve userlevels etc here yet
    $players = array();
    if (isset($json['ServerStatus']['OnlinePlayers'])) {
        $playerlist = $json['ServerStatus']['OnlinePlayers'];
        $player_all_data = array();
        /*
        Name:Bugsy_danny,
        XP:0.22314094007015228,
        IP:/66.27.153.127:50676,
        GameMode:SURVIVAL,
        UUID:b5e51419-30d0-4920-b7da-c90dabac6b07,
        FoodLevel:20,
        IsOP:false,
        Health:20,
        XPLevel:28,
        Exhaustion:1.4806113243103027,
        UUIDVersion:4
         */

        foreach ($playerlist as $player_data) {
            $players[$player_data['UUID']] = strtolower($player_data['Name']);
            foreach ($player_data as $type => $value) {
                $uuid = $player_data['UUID'];
                $player_all_data[$uuid][$type] = $value;
            }
        }
        $UMC_USER['online_players'] = $players;
        $UMC_USER['player_data'] = $player_all_data;
    }
    /*
    $current_user = new User();                         // create a user object
    $current_user->set_uuid($UMC_USER['uuid']);         // give it a uuid
    $UMC_USERS['current_user'] = $UMC_USER['uuid'];     // remember that this is the current user
    $UMC_USERS[$UMC_USER['uuid']] = $current_user;      // add the object to the list of all users
     *
     */
}

/**
 * Convert Websend-based YAW information into Bukkit/mc based yaw info.
 *
 * @param type $raw_yaw
 * @return int
 */
function umc_ws_yaw_fix($raw_yaw) {
    $yaw = round($raw_yaw, 1);
    // first we fix the 2x turn from websend with negative -350 degrees
    if ($yaw < 0) {
        $yaw += 360;
    }
    // then we change the system from 0-360 to +180/-180
    // as minecraft shows on F3
    if ($yaw > 180) {
        $yaw -= 360;
    }
    return $yaw;
}

/**
 * Executes a command without the initiation of Websend
 * always does this as the console since no user initiating it.
 *.
 * @param type $cmd
 * @param type $how (asConsole (default), asPlayer, toConsole, toPlayer, broadcast, doScript
 * @param type $player
 */
function umc_exec_command($cmd, $how = 'asConsole', $player = false) {
    $ws = umc_ws_connect();
    if (!$ws) {
        return;
    }
    // $ws->writeOutputToConsole("starting ws communication;");
    // $ws->writeOutputToConsole("Executing Command '$cmd' Method '$how' Player '$player';");
    switch ($how) {
        case 'asConsole':
            $check = $ws->doCommandAsConsole($cmd);
            break;
        case 'asPlayer':
            $check = $ws->doCommandAsPlayer($cmd, $player);
            break;
        case 'toConsole':
            $check = $ws->writeOutputToConsole($cmd);
            break;
        case 'toPlayer':
            $check = $ws->writeOutputToPlayer($cmd, $player);
            break;
        case 'broadcast':
            $check = $ws->broadcast($cmd);
            break;
        case 'doScript':
            $check = $ws->doScript($cmd);
            break;
    }
    if (!$check) {
        XMPP_ERROR_trigger("Could not verify correct connection to websend (umc_exec_command / $cmd / $how / $player)");
    } else {
        //echo "nah.";
        //$check = $ws->writeOutputToConsole("error");
    }
    $ws->disconnect();
    return $check;
}

/*
 *  This is experimental and does not seem to work.
 * It requires the feature WRAP_COMMAND_EXECUTOR=true in the websend config
 * which crashes on a stackoverflow.
 * */
function umc_ws_plugin_comms($plugin, $cmd) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $ws = umc_ws_connect();
    if (!$ws) {
        return;
    }
    $check1 = $ws->startPluginOutputListening($plugin);
    XMPP_ERROR_trace("connect $plugin", $check1);
    $check2 = $ws->doCommandAsConsole($cmd);
    XMPP_ERROR_trace("do command $cmd", $check2);
    $check3 = $ws->stopPluginOutputListening($plugin);
    XMPP_ERROR_trace("disconnect $plugin", $check3);
    XMPP_ERROR_trigger("Done!");
}

function umc_ws_connect() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_PATH_MC;
    require_once "$UMC_PATH_MC/server/bin/includes/websend_class.php";
    $ws = new Websend("74.208.45.80"); //, 4445
    $password = file_get_contents("/home/includes/certificates/websend_code.txt");
    $ws->password = $password;
    if (!$ws->connect()) {
        // try again
        XMPP_ERROR_trace("websend Auth failed (attempt 1, trying again)", "none");
        $ws = new Websend("74.208.45.80"); //, 4445
        $ws->password = $password;
        if (!$ws->connect()) { // fail agin? bail.
            XMPP_ERROR_trigger("Could not connect to websend server (umc_exec_command)");
            return false;
        }
    }
    return $ws;
}


/**
 * Send a command back to websend.
 * This function serves as an abstraction layer for websend
 * Terminates the command with a ;
 *
 * @param $cmd the command to send
 * @param $how send the command by either console or user
 * @return boolean true or false. false if the $how param was wrong
 */
function umc_ws_cmd($cmd_raw, $how = 'asConsole', $player = false, $silent = false) {
    global $UMC_USER;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $post_player = filter_input(INPUT_POST, "player", FILTER_SANITIZE_STRING);
    if (!is_null($player)) {
        $fromplayer = $post_player;
    } else {
        $fromplayer = $UMC_USER['username'];
    }
    $return = true;
    // if a command is executed by console, return messages back to console instead
    // of trying to echo to player
    if ($UMC_USER['username'] == '@console' && $how == 'toPlayer') {
        $how = 'toConsole';
    } else if (strtolower($fromplayer) == '@console' && $how == 'asPlayer') {
        $how = 'asConsole';
    }
    // remove colons, just in case
    $cmd = str_replace(';', '', $cmd_raw);

    // this is debugging info
    /* if (!$silent) {
        $color_arr = array('§0','§1','§2','§3','§4','§5','§6','§7','§8','§9','§a','§b','§c','§d','§e','§f',"'");
        $log_cmd = str_replace($color_arr, '', $cmd);
        print("/Output/PrintToConsole:ExecCmd '$log_cmd' Method '$how' Player '$player', executed by '$fromplayer';");
    } */

    switch ($how) {
        case 'asConsole':
            print("/Command/ExecuteConsoleCommand:" . $cmd . ";");
            break;
        case 'asPlayer':
            print("/Command/ExecutePlayerCommand:" . $cmd . ";");
            break;
        case 'toConsole':
            print("/Output/PrintToConsole:" . $cmd . ";");
            break;
        case 'toPlayer':
            if ($player) {
                print("/Output/PrintToPlayer-". $player . ":" . $cmd . ";");
            } else {
                print("/Output/PrintToPlayer:" . $cmd . ";");
            }
            break;
        case 'broadcast':
            print("/Command/Broadcast:" . $cmd . ";");
            break;
        default:
            print("/Output/PrintToConsole:error in command $cmd (how = $how is invalid)!;");
            print("/Output/PrintToPlayer:error in command $cmd (how = $how is invalid);");
            $return = false;
            break;
    }
    return true;
}

/**
 * Return an array of the logged-in player's inventory
 *
 * @param json_data $inv_data
 * @return type
 */
function umc_ws_get_inv($inv_data) {
    global $UMC_DATA_SPIGOT2ITEM, $UMC_DATA, $UMC_DATA_ID2NAME;
    // XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $inv = array();
    foreach($inv_data as $item) {
        $slot = $item['Slot'];
        $inv[$slot] = array();
        $inv[$slot]['meta'] = false;
        foreach ($item as $name => $value) {
            $fix_name = strtolower($name);
            if ($fix_name == 'typename') {
                $item_typename = strtolower($item['TypeName']);
                if (isset($UMC_DATA_SPIGOT2ITEM[$item_typename])) {
                    $inv[$slot]['item_name'] = $UMC_DATA_SPIGOT2ITEM[$item_typename];
                } else if (isset($UMC_DATA[$item_typename])) {
                    $inv[$slot]['item_name'] = $item_typename;
                } else {
                    $inv[$slot]['item_name'] = $UMC_DATA_ID2NAME[$item['TypeName']];
                    $out = "ITEM ISSUE! Please add: '$item_typename' => '{$inv[$slot]['item_name']}', to the \$UMC_DATA_SPIGOT2ITEM array";
                    XMPP_ERROR_send_msg($out);
                }
            } else if ($fix_name == "type") {
                $inv[$slot]['id'] = $item['Type'];
            } else if ($fix_name == 'durability') {
                $name = 'data';
                if ($value == -1) { // case 1) saplings of dark oak harvested from minecart maniah have a -1 data
                    // umc_clear_inv($data['item_name'], $data['data'], $data['amount']);
                    umc_echo("{red}You had a bugged item in your inventory, it had to be removed!");
                    XMPP_ERROR_trigger("Invalid item with -1 damage found!");
                } else {
                    $inv[$slot][$name] = $value;
                }
            } else if ($fix_name == 'meta') {
                foreach ($value as $meta_type => $meta_value) {
                    // enchantments
                    if ($meta_type == 'Enchantments' || $meta_type == 'EnchantmentStorage') {
                        foreach ($meta_value as $ench_data) {
                            $e_name = $ench_data['Name'];
                            $inv[$slot]['meta'][$e_name] = $ench_data['Level'];
                        }
                    }
                }
            } else if ($fix_name == 'nbt') {
                // convert spigot NBT to minecraft NBT
                $nbt = umc_nbt_cleanup($value);
                $inv[$slot]['nbt'] = $nbt;                
            } else {
                $name = strtolower($name);
                $inv[$slot][$name] = $value;
            }
        }
    }
    return $inv;
}

/**
 *
 * @param type $string string to type. Automatically includes linebreak. Execute for each line individually please!
 * @param type $silent if true, do not send to console
 */
function umc_echo($string, $silent = false) {
    $color_regex = color_regex();
    $str = preg_replace_callback($color_regex, create_function('$matches', 'return color_map($matches[1]);'), $string);

    // echo $str;
    umc_ws_cmd($str, 'toPlayer', false, $silent);
}


function umc_header($string = 'Uncovery Minecraft', $silent = false) {
    $bar = "{blue}--{darkcyan}-{cyan}-{green}-=[ {white}$string{green} ]=-{cyan}-{darkcyan}-{blue}--";
    umc_pretty_bar("darkblue", "-", $bar, 52, $silent);
}

/**
 *
 * @param type $silent
 */
function umc_footer($silent = false, $footer_text = false) {
    $footer = '';
    if ($footer_text) {
        $footer = " {blue}$footer_text{darkblue} ";
    }
    umc_pretty_bar("darkblue", "-", $footer, 49, $silent);
}

/**
 *
 * @param type $color
 * @param type $char
 * @param type $string
 * @param type $width
 * @param type $silent
 */
function umc_pretty_bar($color, $char, $string, $width = 52, $silent = false) {
    $bar_width = round(($width - (strlen(preg_replace(color_regex(), "", $string)))) / 2);
    if ($bar_width > 0) {
        $half_bar = "{" . $color . "}" . str_repeat($char, $bar_width);
    } else {
        $half_bar = "{" . $color . "}$char";
    }
    umc_echo("$half_bar$string$half_bar", $silent);
}

function umc_exit_msg($msg) {
    umc_echo($msg . "-----------------------------------------------------");
    die('umc_exit_msg');
}



/**
 * Sends an error message to the user
 * Terminates  with a ;

 * @param $message to send
 */
function umc_error($message, $silent = false) {
    umc_pretty_bar('red', '-', ' ERROR ', 52);
    umc_echo($message, $silent);
    umc_pretty_bar('red', '-', '', 52);
    die('umc_error');
}

function umc_ws_vardump($var) {
    $exoport_var = var_export($var, true);
    // replace linebreaks
    $search = array("\n", "\r", "  ");
    $line = str_replace($search, " ", $exoport_var);
    return $line;
}

/**
 * TELLRAW SECTION *************************************************************
 */

/**
 * Base Tellraw execution
 *
 * @param type $selector
 * @param type $msg_arr
 * @param type $spacer
 */
function umc_tellraw($selector, $msg_arr, $spacer) {
    $valid_selectors = array(
        '@p', // nearest
        '@r', // random
        '@a', // all users
        '@e', // all entities, including users
    );

    if (in_array($selector, $valid_selectors)) {
        $sel = $selector;
    } else {
        $sel = "@a[name=$selector]";
    }

    $texts = array();
    foreach ($msg_arr as $msg) {
        if (is_array($msg)) {
            $out = "{\"text\":\"{$msg['txt']}\"";
            if (isset($msg['att'])) {
                $out .= $msg['att'];
            }
            $out .= "}";
        } else {
            $out = "{\"text\":\"$msg\"}";
        }
        $texts[] = $out;
    }

    // glue the pieces with commas
    $spacer_str = ",";
    if ($spacer == true) {
        $spacer_str = ",{\"text\":\" \"},";
    }
    $text_line = implode($spacer_str, $texts);

    $cmd = "tellraw $sel [$text_line]";
    umc_ws_cmd($cmd, 'asConsole');

    // we likely need to check if the environment is websend or not and if not
    // use umc_exec_command($cmd, 'asConsole'); instead
}

/**
 * Apply a color to a text for tellraw
 * Can receive the output of other umc_txt_* functions as $msg input to apply
 * several effects on the same text.
 *
 * @param type $msg
 * @param type $color
 * @return boolean
 */
function umc_txt_color($msg, $color) {
    $valid_colors = array(
        'black','dark_blue','dark_green','dark_aqua','dark_red','dark_purple',
        'gold','gray','dark_gray','blue','green','aqua','red','light_purple','yellow','white'
    );
    if ($color && in_array($color, $valid_colors)) {
        $out = ",\"color\":\"$color\"";

        if (is_array($msg)) {
            return array('txt' => $msg['txt'], 'att' => $msg['att'] . $out);
        } else {
            return array('txt' => $msg, 'att' => $out);
        }
    } else {
        return false;
    }
}

/**
 * apply a format to a text for tellraw
 * Can receive the output of other umc_txt_* functions as $msg input to apply
 * several effects on the same text.
 *
 * @param type $msg
 * @param type $formats
 * @return type
 */
function umc_txt_format($msg, $formats = array()) {
    if (!is_array($formats)) {
        $formats = array($formats);
    }
    $valid_formats = array(
        'bold','italic','strikethrough','underlined','obfuscated','normal',
    );
    $out = '';
    foreach ($formats as $format) {
        if (in_array($format, $valid_formats)) {
            if ($format == 'normal') {
                $out .= ",\"bold\":\"false\"";
            } else {
                $out .= ",\"$format\":\"true\"";
            }
        }
    }
    if (is_array($msg)) {
        return array('txt' => $msg['txt'], 'att' => $msg['att'] . $out);
    } else {
        return array('txt' => $msg, 'att' => $out);
    }
}

/**
 * Apply a click event to a text for tellraw
 * Can receive the output of other umc_txt_* functions as $msg input to apply
 * several effects on the same text.
 *
 * @param type $msg
 * @param type $action
 * @param type $value
 * @return boolean
 */
function umc_txt_click($msg, $action, $value) {
    $valid_clicks = array('open_url','suggest_command','run_command','insertion');
    if (in_array($action, $valid_clicks)) {
        $out = ",\"clickEvent\":{\"action\":\"$action\",\"value\":\"$value\"}";
        if (is_array($msg)) {
            return array('txt' => $msg['txt'], 'att' => $msg['att'] . $out);
        } else {
            return array('txt' => $msg, 'att' => $out);
        }
    } else {
        return false;
    }
}

/**
 * Apply a hover tooltip to a text for tellraw
 * Can receive the output of other umc_txt_* functions as $msg input to apply
 * several effects on the same text.
 *
 * @param type $msg
 * @param type $action
 * @param type $value
 * @return boolean
 */
function umc_txt_hover($msg, $action, $value) {
    // achievements also work for stats with the value being stats.stats_id
    $valid_tool_types = array('show_text','show_item','show_entity','show_achievement');
    // we might need to validate items, entity and achievement/stats names
    if (in_array($action, $valid_tool_types)) {
        $out = ",\"hoverEvent\":{\"action\":\"$action\",\"value\":\"$value\"}";
        if (is_array($msg)) {
            return array('txt' => $msg['txt'], 'att' => $msg['att'] . $out);
        } else {
            return array('txt' => $msg, 'att' => $out);
        }
    } else {
        return false;
    }
}


/**
 * Give an item to a a user. This is an abstraction layer for /give... commands to make sure
 * that if something changes in the way we give things to users, we find it easier to change it.
 * give command explanation: http://minecraft.gamepedia.com/Commands#give
 * Example: https://ezekielelin.com/give/
 *
 * @param type $user
 * @param type $item_name
 * @param type $amount
 * @param type $damage
 * @param type $meta
 */
function umc_ws_give($user, $item_name, $amount, $damage = 0, $meta = '') {

    // is the meta an array or NBT Data?
    if (substr($meta, 0, 2) == 'a:') { // we have an array
        $meta_arr = unserialize($meta);
        if (!is_array($meta_arr)) {
            XMPP_ERROR_trigger("Could not get Meta Data array: " . var_export($meta, true));
        }
        foreach ($meta_arr as $type => $lvl) {
            $meta_cmd .= " $type:$lvl";
        }
    } else { // otherwise we use the raw NBT meta
        $meta_cmd = $meta;
    }
    
    umc_ws_cmd("give $user $item_name $amount $damage $meta_cmd;", 'asConsole');
}