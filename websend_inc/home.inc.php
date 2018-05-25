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

global $UMC_SETTING, $WS_INIT;

$WS_INIT['homes'] = array(  // the name of the plugin
    'disabled' => false,
    'events' => array(
        '2dmap_display' => 'umc_home_2d_map',
    ),
    'default' => array(
        'help' => array(
            'title' => 'Home Manager',  // give it a friendly title
            'short' => 'Warp to personal, pre-defined locations',  // a short description
            'long' => "This command allows you to warp to pre-defined locations (homes). "
                . "It also allows you to buy additional homes locations, depending on your userlevel. "
                . "Home prices increase with each additional home. We use the formula: cost = (no_of_homes ^ 3) x 10.", // a long add-on to the short  description
        ),
    ),
    'home' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Warp to a location',
            'long' => "Warps you to your home. If you have several homes, add the name of it.",
            'args' => '<home name>',
        ),
        'security' => array(
            'worlds' => array('city', 'empire', 'kingdom', 'skylands', 'aether', 'nether', 'flatlands', 'draftlands'),
        ),
        'function' => 'umc_home_warp',
        'top' => true,
    ),
    'buy' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Buy 1 additonal home slot for the current location',
            'long' => "This will buy you 1 additional home slot.",
            'args' => '<home name>',
        ),
        'function' => 'umc_home_buy',
        'security' => array(
            'worlds' => array('city', 'empire', 'kingdom', 'skylands', 'aether', 'nether', 'flatlands', 'draftlands'),
            // 'level'=>'Owner',
        ),
    ),
    'update' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Update the location of a home (/sethome also works)',
            'long' => "This will update the position of an existing home. Alternatively to /homes update <name> you can also use /sethome <name>."
                . " You can rename the home at the same time by optionally adding a new name after the current name.",
            'args' => '<home name> [new name]',
        ),
        'function' => 'umc_home_update',
        'security' => array(
            'worlds' => array('city', 'empire', 'kingdom', 'skylands', 'aether', 'nether', 'flatlands', 'draftlands'),
        ),
    ),
    'rename' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Change the name of a home',
            'long' => "This will change the name of an existing home.",
            'args' => '<home name> <new name>',
        ),
        'function' => 'umc_home_rename',
    ),
    'list' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Get a list of your homes',
            'long' => "This will list all of your homes. Optionally add a world name to filter",
            'args' => '[world]',
        ),
        'function' => 'umc_home_list',
    ),
    'check' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Gets the next homes cost',
            'long' => "This will return the cost of your next home you can purchase along with your current maximum number of homes.",
        ),
        'function' => 'umc_home_check',
    ),
    /* 'sell' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Sell a home slot',
            'long' => "This will sell one of your homes and refund you 50%.",
            'args' => '<home name>',
        ),
        'function' => 'umc_home_sell',
    ),
    'import' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Import your legacy homes',
            'long' => "This will import your old homes.",
            // 'args' => '<home name> <new name>',
        ),
        'function' => 'umc_home_import',
    ),
     */
);

$UMC_SETTING['homes']['max_homes'] = array(
    'Guest' => 1,
    'Settler' => 6, 'SettlerDonator' => 6,
    'Citizen' => 8, 'CitizenDonator' => 8,
    'Architect'  => 10, 'ArchitectDonator' => 10,
    'Designer' => 15, 'DesignerDonator' => 15,
    'Master' => 20, 'MasterDonator' => 20,
    'Elder' => 50, 'ElderDonator' => 50,
    'Owner' => 100,
);
$UMC_SETTING['homes']['icon_url'] = "https://uncovery.me/admin/img/home_icon.png";

// returns information about the players homes
function umc_home_check() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER, $UMC_SETTING;

    $count = umc_home_count();

    $cost = umc_home_calc_costs($count + 1);
    $userlevel = $UMC_USER['userlevel'];
    $max_homes = $UMC_SETTING['homes']['max_homes'][$userlevel];
    $bank = umc_money_check($UMC_USER['uuid']);

    // output the return values to the chat window
    umc_header("Checking Home Status");
    umc_echo("You currently have $count homes.");
    umc_echo("Your maximum number of homes available for purchase is $max_homes.");
    umc_echo("The cost to purchase your next home is $cost Uncs.");
    umc_echo("You currently have $bank Uncs.");
    umc_footer();
}

function umc_home_calc_costs($count) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $base = 10;
    $cost = pow($count, 3) * $base;
    return $cost;
}

function umc_home_warp() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;

    $args = $UMC_USER['args'];
    $player = $UMC_USER['username'];

    // no home name given
    if (!isset($args[2])) {
        // check if the user has only one home
        $home_count = umc_home_count();
        if ($home_count > 1) {
            umc_error("{red}You need to specify the name of your home! Use /homes list to see all homes");
        } else if ($home_count == 0) {
            umc_error("{red}You do not have any homeslots yet! Use /homes buy to get a new home");
        } else {
            $sql = "SELECT * FROM minecraft_srvr.homes WHERE uuid='{$UMC_USER['uuid']}' LIMIT 1;";
        }
    } else {
        $home_count = umc_home_count(trim($args[2]));
        if ($home_count < 1) {
            umc_error("{red}You do not have a home with that name!");
        }
        $name = umc_mysql_real_escape_string(trim($args[2]));
        $sql = "SELECT * FROM minecraft_srvr.homes WHERE uuid='{$UMC_USER['uuid']}' AND name=$name;";
    }

    $D = umc_mysql_fetch_all($sql);
    $row = $D[0];
    $name = $D[0]['name'];
    $world = $row['world'];
    $x = $row['x'];
    $z = $row['z'];
    $y = $row['y'];
    $yaw = $row['yaw'];
    // todo translate ESSENTIALS yaw into minecraft yaw
    $cmd = "consoletp $player $world $x $y $z $yaw 0";
    umc_log('home', 'warp', "$player warped to home $name at $world $x $y $z $yaw");
    umc_ws_cmd($cmd, 'asConsole');
}

// used primarily by lottery to force a home called 'lottery'
function umc_home_add($uuid, $name, $force = false){
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_SETTING;

    $count = umc_home_count();

    // add a prefix string to lottery home name to prevent conflict
    if (!$force) {
        $userlevel = umc_userlevel_get($uuid);
        $max_homes = $UMC_SETTING['homes']['max_homes'][$userlevel];

        if ($count >= $max_homes) {
            umc_error("You already reached your maximum home count ($max_homes)!");
        }
    }
    $uuid_sql = umc_mysql_real_escape_string($uuid);
    $name_sql = umc_mysql_real_escape_string($name);

    // add the new entry to the database
    $sql = "INSERT INTO minecraft_srvr.`homes`(`name`, `uuid`, `world`, `x`, `y`, `z`, `yaw`) VALUES "
        . "($name_sql,$uuid_sql,'empire','66.565','64','-57.219','0');"; // home is empire spawn
    umc_mysql_query($sql, true);

}

//
function umc_home_buy() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER, $UMC_SETTING;
    $args = $UMC_USER['args'];
    $count = umc_home_count();
    $cost = $cost = umc_home_calc_costs($count + 1);
    $userlevel = $UMC_USER['userlevel'];
    $max_homes = $UMC_SETTING['homes']['max_homes'][$userlevel];

    // sanitise input and check if home name valid
    if (isset($args[2])) {

        $sanitised_name = preg_replace('/[^a-zA-Z0-9_-]+/', '', trim($args[2]));

        if ($sanitised_name != trim($args[2])){
            umc_error("{red}Home names must only contain numbers, letters, dashes(-) and underscores(_)");
        }

        // check if the name already exists
        $name_check = umc_home_count($sanitised_name);
        if ($name_check > 0) {
            umc_error("{red}You already have a home with that name!");
        }
        $name = umc_mysql_real_escape_string($sanitised_name);
    } else {
        umc_error("{red}You need to specify the name of your new home!");
    }

    // check player is not home capped
    if ($count >= $max_homes) {
        umc_error("You already reached your maximum home count ($max_homes)!");
    }

    // check if the user has the cash to afford their new home
    $bank = umc_money_check($UMC_USER['uuid']);
    if ($bank < $cost) {
        umc_error("You do not have enough cash to buy another home! You have only $bank Uncs. You need $cost Uncs.");
    }
    $leftover = $bank - $cost;

    if ($UMC_USER['world'] == 'nether' && $UMC_USER['coords']['y'] > 110) {
        umc_error("Sorry, you cannot set a home this high in the nether!");
    }

    // transfer the money
    umc_money($UMC_USER['uuid'], false, $cost);

    // add the new entry to the database
    $sql = "INSERT INTO minecraft_srvr.`homes`(`name`, `uuid`, `world`, `x`, `y`, `z`, `yaw`) VALUES "
        . "($name,'{$UMC_USER['uuid']}','{$UMC_USER['world']}','{$UMC_USER['coords']['x']}','{$UMC_USER['coords']['y']}','{$UMC_USER['coords']['z']}','{$UMC_USER['coords']['yaw']}');";
    umc_mysql_query($sql, true);

    // output user feedback regarding their purchase
    umc_header("Buying a home");
    umc_echo("You currently have $count homes (max $max_homes).");
    umc_echo("This home costs you $cost Uncs! You have $leftover Uncs in your account left.");
    umc_echo("Your home slot has been purchased and set to your current location.");
    umc_echo("You can edit it with the {blue}rename{white} and {blue}update{white} commands!");
    umc_footer();
    umc_log('home', 'buy', "{$UMC_USER['uuid']}/{$UMC_USER['username']} bought a home called $sanitised_name for $cost!");
}

function umc_home_sell() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $args = $UMC_USER['args'];
    $count = umc_home_count();
    $cost = umc_home_calc_costs($count) / 2;
    if (!isset($args[2])) {
        umc_error("{red}You need to specify which home you wish to sell");
    } else {
        $name_check = umc_home_count(trim($args[2]));
        if ($name_check == 0) {
            umc_error("{red}You do not have a home with that name!");
        }
        $name = umc_mysql_real_escape_string(trim($args[2]));
    }

    umc_money(false, $UMC_USER['uuid'], $cost);
    $bank = umc_money_check($UMC_USER['uuid']);
    $newcount = $count - 1;
    $sql = "DELETE FROM minecraft_srvr.`homes` WHERE uuid='{$UMC_USER['uuid']}' AND name=$name;";
    umc_mysql_query($sql, true);
    umc_header("Selling a home");
    umc_echo("You currently have $count homes, selling one.");
    umc_echo("This home sell earns you $cost Uncs! You now have $bank Uncs in your account.");
    umc_echo("Your home slot has been sold successfully sold, you now have $newcount homes.");
    umc_footer();
    umc_log('home', 'sell', "{$UMC_USER['uuid']}/{$UMC_USER['username']} sold the home called {$args[2]}!");
}

function umc_home_update() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $args = $UMC_USER['args'];

    // check if the home exists
    if (isset($args[2])) {

        // leave existing badly formatted names as valid things to replace
        $unsanitised_name = $args[2];

        // check if the name actually exists to replace
        $name_check = umc_home_count($unsanitised_name);
        if ($name_check <> 1) {
            umc_error("{red}You do not have a home called " . $unsanitised_name . " to replace!");
        }
        $replacing = umc_mysql_real_escape_string($unsanitised_name);
    } else {
        umc_error("{red}You need to specify the name of the home you want to update home!");
    }

    // change the home name as well?
    $name_update = '';
    $log_addon = '';
    if (isset($args[3])) {
        $sanitised_name = preg_replace('/[^a-zA-Z0-9_-]+/', '', trim($args[3]));

        if ($sanitised_name != trim($args[3])){
            umc_error("{red}Home names must only contain numbers, letters, dashes(-) and underscores(_)");
        }

        // check if the name already exists
        $name_check = umc_home_count($sanitised_name);
        if ($name_check == 1) {
            umc_error("{red}You do already have a home with that name!");
        }
        $new_name = umc_mysql_real_escape_string($sanitised_name);
        $name_update = " `name`=$new_name,";
        $log_addon = " and the name of the home was changed to " . $sanitised_name;
    }
    if ($UMC_USER['world'] == 'nether' && $UMC_USER['coords']['y'] > 110) {
        umc_error("Sorry, you cannot set a home this high in the nether!");
    }

    $sql = "UPDATE minecraft_srvr.`homes` SET $name_update `world`='{$UMC_USER['world']}',`x`='{$UMC_USER['coords']['x']}',`y`='{$UMC_USER['coords']['y']}',`z`='{$UMC_USER['coords']['z']}',`yaw`='{$UMC_USER['coords']['yaw']}' "
        . "WHERE uuid='{$UMC_USER['uuid']}' AND name=$replacing LIMIT 1;";

    umc_mysql_query($sql, true);
    umc_log('home', 'update', "{$UMC_USER['uuid']}/{$UMC_USER['username']} updated home {$args[2]} $log_addon!");
    umc_echo("The coordinates of home {$args[2]} were updated to the current location $log_addon!");
}

function umc_home_rename() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $args = $UMC_USER['args'];
    // home name
    if (isset($args[2]) && isset($args[3])) {
        // check if the name already exists
        $name_check = umc_home_count(trim($args[2]));
        if ($name_check <> 1) {
            umc_error("{red}You do not have a home with that name!");
        }
        $old_name = umc_mysql_real_escape_string(trim($args[2]));
        $sanitised_name = preg_replace('/[^a-zA-Z0-9_-]+/', '', trim($args[3]));
        if ($sanitised_name != trim($args[3])) {
            umc_error("{red}Home names must only contain numbers, letters, dashes(-) and underscores(_)");
        }
        $new_name_check = umc_home_count($sanitised_name);
        if ($new_name_check == 1) {
            umc_error("{red}You already have a home with that name!");
        }
        $new_name = umc_mysql_real_escape_string($sanitised_name);
    } else {
        umc_error("{red}You need to specify the name of your new home!");
    }
    $sql = "UPDATE minecraft_srvr.`homes` SET `name`=$new_name "
        . "WHERE uuid='{$UMC_USER['uuid']}' AND name=$old_name LIMIT 1;";
    umc_mysql_query($sql, true);
    umc_log('home', 'rename', "{$UMC_USER['uuid']}/{$UMC_USER['username']} renamed home {$args[2]} to $sanitised_name!");
    umc_echo("The name of home {$args[2]} was updated to $sanitised_name!");
}

// find out how many homes a user has already
function umc_home_count($name = false, $uuid_req = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    if (!$uuid_req) {
        $uuid = $UMC_USER['uuid'];
    } else {
        $uuid = $uuid_req;
    }
    global $UMC_USER;
    $name_sql = '';

    // this cannot be "if ($name)" since $home can be "0" in which case == false but not === false
    if ($name !== false) {
        $name_sql = "AND name=" . umc_mysql_real_escape_string($name);
    }
    $sql = "SELECT count(home_id) as count FROM minecraft_srvr.homes WHERE uuid='$uuid' $name_sql;";
    $D = umc_mysql_fetch_all($sql);
    $homes = $D[0]['count'];
    return $homes;
}

/**
 * List the homes for the current user in-game
 *
 * @global type $UMC_USER
 */
function umc_home_list() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;

    $homes = umc_homes_array($UMC_USER['uuid'], false);

    umc_header("Your home list");
    $count = 0;
    foreach ($homes as $world => $worldhomes) {
        $count += count($worldhomes);
        $out = "{red}$world: {white}" . implode("{red},{white} ", array_keys($worldhomes));
        umc_echo($out);
    }
    umc_pretty_bar("darkblue", "-", " {white}Your Homecount: $count{darkblue} ", 49, true);
}

/**
 * Create an array with home data for a user, optionally only for one world
 *
 * @param type $uuid
 * @param type $world
 * @return type
 */
function umc_homes_array($uuid, $world = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $world_filter = '';
    if ($world) {
        $world_filter = " AND world=" . umc_mysql_real_escape_string($world);
    }
    $uuid_sql = umc_mysql_real_escape_string($uuid);

    $sql = "SELECT * FROM minecraft_srvr.homes WHERE uuid=$uuid_sql $world_filter ORDER BY world, name;";
    $D = umc_mysql_fetch_all($sql);

    $homes = array();
    foreach ($D as $d) {
        $world = $d['world'];
        $name = $d['name'];
        $homes[$world][$name] = array('x' => $d['x'], 'y' => $d['y'], 'z' => $d['z']);
    }
    return $homes;
}

/**
 * Create a list of homes that will be displayed on the 2D map.
 *
 * @global array $UMC_SETTING
 * @param type $uuid
 * @param type $world
 * @return type
 */
function umc_home_2d_map($data) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_SETTING, $UMC_USER, $UMC_DOMAIN;
    if (!$UMC_USER) {
        return array();
    }
    $uuid = $UMC_USER['uuid'];
    $world = $data['world'];
    $homes = umc_homes_array($uuid, $world);
    
    if (count($homes) == 0) {
        return false;
    }

    $icon = $UMC_SETTING['homes']['icon_url'];
    $out = array('html'=> '', 'menu'=>  '');
    $out['html'] = "\n<!-- Homes Plugin HTML start-->\n";
    $out['menu'] = " Show home:\n <form action=\"$UMC_DOMAIN/admin/\" method=\"get\" style=\"display:inline;\">\n    <div style=\"display:inline;\">"
        . "<select id=\"home_finder\" style=\"display:inline;\" onchange='find_home(this)'>\n"
        . "<option disabled selected value> -- select a home -- </option>\n";
    foreach ($homes as $world => $world_homes) {
        foreach ($world_homes as $home => $coords) {
            $map_coords = umc_map_convert_coorindates($coords['x'], $coords['z'], $world);
            $top = $map_coords['z'];
            $left = $map_coords['x'];
            $out['html'] .= "
            <div id='home_$home' class='marker' style='font-size: 12px; font-family: sans-serif; z-index:99; top:{$top}px; left:{$left}px;'><img style='vertical-align:middle; height:20px; width:20px;' src='$icon' alt='Home $home' title='$home'>
                <span style='vertical-align:middle;'>$home</span>
            </div>\n";
            $out['menu'] .= "<option value=\"$home|$top|$left\">$home</option>\n";
        }
    }
    $out['menu'] .= "        </select>\n    </div></form>\n ";
    $out['html'] .= "\n<!-- Homes Plugin HTML end-->\n";
    $out['javascript'] = '
        function find_home(element) {
            var val_arr = element.value.split("|");
            home_name = val_arr[0];
            home_top = val_arr[1];
            home_left = val_arr[2];
            window.scrollTo(home_left - ($(window).width() / 2), home_top - ($(window).height() / 2))
            $("#home_" + home_name).effect("shake");
        }; 
        ';
    return $out;
}

// import current homes from the essential plugin
// this is not needed anymore, import done.
function umc_home_import() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    // global $UMC_USER;
    // we automatically import old homes for all players on login, but only once

    // include spyc to parse YAML https://github.com/mustangostang/spyc
    require_once('/home/includes/spyc/Spyc.php');
    $users = umc_get_active_members();

    foreach ($users as $uuid => $username) {
        $path = '/home/minecraft/server/bukkit/plugins/Essentials/userdata/' . $uuid . ".yml";
        $A = Spyc::YAMLLoad($path);

        $existing_count = umc_home_count(false, $uuid);
        if ($existing_count > 0) {
            continue;
        }

        if (!isset($A['homes'])) {
            continue;
        }
        $count = count($A['homes']);
        if ($count == 0) {
            continue;
        }
        $H = $A['homes'];

        // iterate homes and import them
        foreach ($H as $home_name => $h) {
            $name = umc_mysql_real_escape_string($home_name);
            // XMPP_ERROR_trigger($h);
            $sql = "INSERT INTO minecraft_srvr.`homes`(`name`, `uuid`, `world`, `x`, `y`, `z`, `yaw`) VALUES "
                . "($name,'$uuid','{$h['world']}','{$h['x']}','{$h['y']}','{$h['z']}','{$h['yaw']}');";
            umc_mysql_query($sql, true);
        }
        umc_log('home', 'import', "$uuid/$username $count homes have been imported!");
    }
}
