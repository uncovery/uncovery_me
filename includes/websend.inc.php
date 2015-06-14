<?php

/**
 * Checks if there is a valid connection from websend
 *
 * @return boolean
 */
function umc_ws_auth() {

    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $checkpass = "willkommenaufdererde";
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

    umc_bukkit_to_websend();
    return true;
}

// ============ Main Dispatching ====================

function umc_websend_main() {
    global $UMC_USER;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $main = $UMC_USER['args'][0];
    // $player = $WSEND['player'];
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
    global $WS_INIT, $UMC_USER;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    $player  = $UMC_USER['username'];

    // iterate all plugins
    foreach ($WS_INIT as $data) {
        // check if there is a setting for the current event
        if (($data['events'] != false) && (isset($data['events'][$event]))) {
            // execute function
            $function = $data['events'][$event];
            if (!is_string($function)) {
                XMPP_ERROR_trigger("plugin eventhandler failed event $event");
            }
            // execute the function
            $function();
        }
    }
    // non-plugin events
    switch ($event) {
        case 'PlayerQuitEvent':
            umc_log('system', 'logout', "$player logged out");
            umc_uuid_record_usertimes('lastlogout');
            break;
        case 'PlayerJoinEvent':
            umc_uuid_check_usernamechange($UMC_USER['uuid']);
            umc_donation_level($player);
            umc_promote_citizen($player, false);
            umc_log('system', 'login', "$player logged in");
            umc_uuid_record_usertimes('lastlogin');
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
 * This retrieves the websend environment variables and returns them
 */
function umc_ws_get_vars() {
    // make sure we are on websend
    global $UMC_ENV, $UMC_USER, $UMC_USERS;
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
        $UMC_USER['donator'] = 'DonatorPlus';
        $UMC_USER['uuid'] = 'Console0-0000-0000-0000-000000000000';
    } else {
        $UMC_USER['username'] = $json['Invoker']['Name'];
        if (isset($json['Invoker']['UUID'])) {
            $uuid = $json['Invoker']['UUID'];
        } else {
            // this is mostly used for pre-logins. it will check if the user exists and add them to the table if not.
            $uuid = umc_user2uuid($json['Invoker']['Name']);
        }


        $UMC_USER['uuid'] = $uuid;
        $UMC_USER['userlevel'] = umc_get_uuid_level($uuid);
        if (strstr($UMC_USER['userlevel'], 'DonatorPlus')) {
            $UMC_USER['donator'] = 'DonatorPlus';
        } else if (strstr($UMC_USER['userlevel'], 'Donator')) {
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
                    'yaw' => $json['Invoker']['Location']['Yaw'],
            );
            $UMC_USER['ip'] = $json['Invoker']['IP'];
        }
        $UMC_USER['inv'] = array();
        if (isset($json['Invoker']['Inventory'])) {
            $UMC_USER['inv'] = umc_ws_get_inv($json['Invoker']['Inventory']);
            $UMC_USER['current_item'] = $json['Invoker']['CurrentItemIndex'];
        }
    }
    $UMC_USER["args"] = $_POST['args'];
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
            $players[] = strtolower($player_data['Name']);
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

// collect a command from the server
/**
 * DEPRECATED
 *
 * @global type $WSEND
 * @global type $UMC_USER
 */
function umc_bukkit_to_websend() {
    global $WSEND;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $s_post = $_POST;
    $args = $s_post['args'];

    if ((!isset($args[0]) || $args[0] == "")) {
        umc_error('{white}Please use {green}/helpme;');
    }

    if ($s_post['isCompressed'] == "true" && isset($_FILES['jsonData']['tmp_name'])) {
        $json_data = json_decode(gzdecode(file_get_contents($_FILES['jsonData']['tmp_name'])));
    } else {
        $json_data = json_decode($s_post["jsonData"]);
    }

    $json_data = $s_post['jsonData'];
    $arr_data = stripslashes($json_data);
    $data = json_decode($arr_data, true);
    // umc_echo($arr_data);

    if ($data == '') {
        $test = var_export($data, true);
        XMPP_ERROR_trigger("Error: Json data invalid: $test");
        //If compressed is enabled PHP probably refused the binary data: check upload_max_filesize, post_max_size and file_uploads
    }

    $player = $data['Invoker']['Name'];

    $players = array();
    $playerlist = $data['ServerStatus']['OnlinePlayers'];
    foreach ($playerlist as $player_data) {
        $players[] = $player_data['Name'];
    }

    if ($player !== 'uncovery') {
        // umc_error('shop closed for maintenance');
    }

    if ($data['Invoker']['Name'] == '@Console') {
        $WSEND = array(
            'args' => $s_post["args"],
            'players' => $players,
            'plugins' => $data['Plugins'],
            'player' => $data['Invoker']['Name'], // we need to know that it's @Console
        );
    } else {
        $WSEND = array();
        $WSEND['args'] = $s_post["args"];
        $WSEND['player'] = $data['Invoker']['Name'];
        // only happens when the user is already online
        if (isset($data['Invoker']['Location'])) {
            $WSEND['current'] = $data['Invoker']['CurrentItemIndex'];
            $WSEND['mode'] = $data['Invoker']['GameMode'];
            $WSEND['world'] = $data['Invoker']['Location']['World'];
            $WSEND['coords'] = array(
                    'x' => $data['Invoker']['Location']['X'],
                    'y' => $data['Invoker']['Location']['Y'],
                    'z' => $data['Invoker']['Location']['Z'],
                    'yaw' => $data['Invoker']['Location']['Yaw'],
            );
        }
        $WSEND['players'] = $players;
        $WSEND['plugins'] = $data['Plugins'];
    }

    $WSEND['inv'] = array();
    if (isset($data['Invoker']['Inventory'])) {
        $WSEND['inv'] = umc_ws_get_inv($data['Invoker']['Inventory']);
    }

    $str_args = implode(" ", $args);
    if ($args[0] != 'event') { // exclude 'event' from WSEvents
        umc_log('websend', 'incoming', $str_args);
    }
    $WSEND['json_raw'] = $json_data;
    umc_ws_sanitize_inv();
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
    global $UMC_PATH_MC;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    umc_log('websend', 'outgoing', "$cmd $how to $player");
    require_once "$UMC_PATH_MC/server/bin/includes/websend_class.php";
    $ws = new Websend("74.208.45.80"); //, 4445
    $ws->password = "willkommenaufdererde";
    if (!$ws->connect()) {
        // try again
        $ws = new Websend("74.208.45.80"); //, 4445
        $ws->password = "willkommenaufdererde";
        if (!$ws->connect()) { // fail agin? bail.
            XMPP_ERROR_trigger("Could not connect to websend server (umc_exec_command / $cmd / $how / $player)");
        }
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
function umc_ws_cmd($cmd, $how = 'asConsole', $player = false, $silent = false) {
    global $WSEND;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    if (isset($_POST["player"])) {
        $fromplayer = $_POST['player'];
    } else {
        $fromplayer = $WSEND['player'];
    }
    $return = true;
    // if a command is executed by console, return messages back to console instead
    // of trying to echo to player
    if ($fromplayer == '@Console' && $how == 'toPlayer') {
        $how = 'toConsole';
    } else if ($fromplayer == '@Console' && $how == 'asPlayer') {
        $how = 'asConsole';
    }
    // remove colons, just in case
    $cmd = str_replace(';', '', $cmd);

    // this is debugging info
    if (!$silent) {
        $color_arr = array('§0','§1','§2','§3','§4','§5','§6','§7','§8','§9','§a','§b','§c','§d','§e','§f',"'");
        // $log_cmd = str_replace($color_arr, '', $cmd);
        // print("/Output/PrintToConsole:ExecCmd '$log_cmd' Method '$how' Player '$player', executed by '$fromplayer';");
    }

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

function umc_random_command($player) {
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
            if ($name == 'TypeName') {
                $item_typename = strtolower($item['TypeName']);
                if (isset($UMC_DATA_SPIGOT2ITEM[$item_typename])) {
                    $inv[$slot]['item_name'] = $UMC_DATA_SPIGOT2ITEM[$item_typename];
                } else if (isset($UMC_DATA[$item_typename])) {
                    $inv[$slot]['item_name'] = $item_typename;
                } else {
                    $inv[$slot]['item_name'] = $UMC_DATA_ID2NAME[$item['Type']];
                    XMPP_ERROR_send_msg("ITEM ISSUE: $item_typename not found in \$UMC_DATA, item {$item['Type']} : {$item['Durability']}, should be {$inv[$slot]['item_name']}");
                }
            } else if ($name == "Type") {
                $inv[$slot]['id'] = $item['Type'];
            } else if ($name == 'Durability') {
                $name = 'data';
                $inv[$slot][$name] = $value;
            } else if ($name == 'Meta') {
                foreach ($value as $meta_type => $meta_value) {
                    // enchantments
                    if ($meta_type == 'Enchantments' || $meta_type == 'EnchantmentStorage') {
                        foreach ($meta_value as $ench_data) {
                            $e_name = $ench_data['Name'];
                            $inv[$slot]['meta'][$e_name] = $ench_data['Level'];
                        }
                    }
                    if ($meta_type == 'BaseColor') {
                        $inv[$slot]['meta'] = array($meta_value => $value['Patterns']);
                    }
                }
            } else {
                $name = strtolower($name);
                $inv[$slot][$name] = $value;
            }
        }
    }
    return $inv;
}


/**
 * Sanitize the inventory, remove all items that are not allowed
 */
function umc_ws_sanitize_inv() {
    global $WSEND;
    $inv = $WSEND['inv'];
    foreach ($inv as $data) { // iterate the slots
        if ($data['data'] == -1) { // case 1) saplings of dark oak harvested from minecart maniah have a -1 data
            umc_clear_inv($data['item_name'], $data['data'], $data['amount']);
            umc_error_msg("Invalid item found!");
            umc_echo("{red}You had a bugged item in your inventory, it had to be removed!");
            XMPP_ERROR_trigger("Invalid item with -1 damage found!");
        }
    }
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


/* messages a user from the console or code
 * will return false in case the user does not exist or is not online
 */
function umc_msg_user($username, $message) {
    global $WSEND;
    $str = preg_replace(color_regex() . "e", 'color_map(\'$1\')', $message);
    if (!in_array($username, $WSEND['players'])) {
        return false;
    } else {
        umc_ws_cmd("msg $username $str", 'asConsole');
    }
    return true;
}


function umc_header($string = 'Uncovery Minecraft', $silent = false) {
    $bar = "{blue}--{darkcyan}-{cyan}-{green}-=[ {white}$string{green} ]=-{cyan}-{darkcyan}-{blue}--";
    umc_pretty_bar("darkblue", "-", $bar, 52, $silent);
}

/**
 *
 * @param type $silent
 */
function umc_footer($silent = false) {
    umc_pretty_bar("darkblue", "-", "", 49, $silent);
}

function umc_announce($string, $channel = 't') {
    $str = preg_replace(color_regex() . "e", 'color_map(\'$1\')', $string);
    umc_ws_cmd("ch qm $channel $str", 'asConsole');
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
 *
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

?>
