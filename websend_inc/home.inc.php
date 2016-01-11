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

$WS_INIT['home2'] = array(  // the name of the plugin
    'disabled' => false,
    'events' => array(
        'PlayerJoinEvent' => 'umc_home_import',
    ),
    'default' => array(
        'help' => array(
            'title' => 'Home',  // give it a friendly title
            'short' => 'Warp to personal, pre-defined locations',  // a short description
            'long' => "This command allows you to warp to pre-defined locations (homes). "
                . "It also allows you to buy additional homes locations, depending on your userlevel"
                . "Home prices rise exponentially with each additional home.", // a long add-on to the short  description
            ),
    ),
    'home' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Warp to a location',
            'long' => "Warps you to your home. If you have several homes, add the name of it.",
            'args' => '<home name>',
        ),
        'security' => array(
            'worlds' => array( 'empire', 'kingdom', 'skylands', 'aether', 'the_end'),
        ),
        'function' => 'umc_home_warp',
    ),
    'buy' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Buy 1 additonal home slot for the current location',
            'long' => "This will buy you 1 additional home slot.",
            'args' => '<home name>',
        ),
        'function' => 'umc_home_buy',
    ),
    'update' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Update the location of a home',
            'long' => "This will update the position of an existing home.",
            'args' => '<home name>',
        ),
        'function' => 'umc_home_update',
    ),
    'rename' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Change the name of a home',
            'long' => "This will update the name of an existing home.",
            'args' => '<home name> <new name>',
        ),
        'function' => 'umc_home_rename',
    ),
    'import' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Import your legacy homes',
            'long' => "This will import your old homes.",
            // 'args' => '<home name> <new name>',
        ),
        'function' => 'umc_home_import',
    ),
    'sell' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Sell a home slot',
            'long' => "This will sell one of your homes and refund you 50%.",
            'args' => '<home name>',
        ),
        'function' => 'umc_home_sell',
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
);

$UMC_SETTING['max_homes'] = array(
    'Guest' => 1,
    'Settler' => 6, 'SettlerDonator' => 6, 'SettlerDonatorPlus' => 6,
    'Citizen' => 8, 'CitizenDonator' => 8, 'CitizenDonatorPlus' => 8,
    'Architect'  => 10, 'ArchitectDonator' => 10, 'ArchitectDonatorPlus' => 10,
    'Designer' => 15, 'DesignerDonator' => 15, 'DesignerDonatorPlus' => 15,
    'Master' => 20, 'MasterDonator' => 20, 'MasterDonatorPlus' => 20,
    'Elder' => 50, 'ElderDonator' => 50, 'ElderDonatorPlus' => 50,
    'Owner' => 15,
);

// returns information about the players homes
function umc_home_check() {
    
    $count = umc_home_count();
    $base = 10;
    $cost = pow($count + 1, 3) * $base;
    $userlevel = $UMC_USER['userlevel'];
    $max_homes = $UMC_SETTING['max_homes'][$userlevel];
    $bank = umc_money_check($UMC_USER['uuid']);
    
    // output the return values to the chat window
    umc_header("Checking Home Status");
    umc_echo("You currently have $count homes.");
    umc_echo("Your maximum number of homes available for purchase is $max_homes.");
    umc_echo("The cost to purchase your next home is $cost Uncs.");
    umc_echo("You currently have $bank Uncs."
    
}

function umc_home_warp() {
    global $UMC_USER;

    $playerworld = $UMC_USER['world'];
    $args = $UMC_USER['args'];

    // no home name given
    if (!isset($args[2])) {
        // check if the user has only one home
        $home_count = umc_home_count();
        if ($home_count > 1) {
            umc_error("{red}You need to specify the name of your home!");
        } else if ($home_count == 0) {
            umc_error("{red}You do not have any homeslots yet!");
        } else {
            $sql = "SELECT * FROM minecraft_srvr.homes WHERE uuid='{$UMC_USER['uuid']}' LIMIT 1;";
        }
    } else {
        $name = umc_mysql_real_escape_string(trim($args[2]));
        $home_count = umc_home_count($name);
        if ($home_count < 1) {
            umc_error("{red}You do not have a home with that name!");
        }
        $sql = "SELECT * FROM minecraft_srvr.homes WHERE uuid='{$UMC_USER['uuid']}' AND name=$name;";
    }

    $D = umc_mysql_fetch_all($sql);
    $row = $D[0];
    $world = $row['world'];
    if ($world != $playerworld) {
        umc_ws_cmd("mv tp $world", 'asPlayer');
    }
    $x = $row['x'];
    $z = $row['z'];
    $y = $row['y'];
    $yaw = $row['yaw'];
    // todo translate ESSENTIALS yaw into minecraft yaw
    XMPP_ERROR_send_msg("tppos $x $y $z $yaw");
    umc_ws_cmd("tppos $x $y $z $yaw", 'asPlayer');
}

// 
function umc_home_buy() {
    global $UMC_USER, $UMC_SETTING;
    $args = $UMC_USER['args'];
    $count = umc_home_count();
    $base = 10;
    $cost = pow($count + 1, 3) * $base;
    $userlevel = $UMC_USER['userlevel'];
    $max_homes = $UMC_SETTING['max_homes'][$userlevel];

    // sanitise input and check if home name valid
    if (isset($args[2])) {
        $name = umc_mysql_real_escape_string(trim($args[2]));
        // check if the name already exists
        $name_check = umc_home_count($name);
        if ($name_check > 0) {
            umc_error("{red}You already have a home with that name!");
        }
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
    
    // transfer the money
    umc_money($UMC_USER['uuid'], false, $cost);
    
    // add the new entry to the database
    $sql = "INSERT INTO minecraft_srvr.`homes`(`name`, `uuid`, `world`, `x`, `y`, `z`, `yaw`) VALUES "
        . "($name,'{$UMC_USER['uuid']}','{$UMC_USER['world']}','{$UMC_USER['coords']['x']}','{$UMC_USER['coords']['y']}','{$UMC_USER['coords']['z']}','{$UMC_USER['coords']['yaw']}');";
    umc_mysql_query($sql, true);
    
    // output user feedback regarding their purchase
    umc_header("Buying a home");
    umc_echo("You currently have $count homes.");
    umc_echo("This home costs you $cost Uncs! You have $leftover Uncs in your account left.");
    umc_echo("Your home slot has been purchased and set to your current location.");
    umc_echo("You can edit it with the {blue}rename{white} and {blue}update{white} commands!");
    umc_footer();
    umc_log('homes', 'buy', "{$UMC_USER['uuid']}/{$UMC_USER['username']} bought a home called {$args[2]}!");
}

function umc_home_sell() {
    global $UMC_USER;
    $args = $UMC_USER['args'];
    $count = umc_home_count();
    $base = 10;
    $cost = (pow($count, 3) * $base) / 2;
    if (!isset($args[2])) {
        umc_error("{red}You need to specify the name of your new home!");
    } else {
        $name = umc_mysql_real_escape_string(trim($args[2]));
        $name_check = umc_home_count($name);
        if ($name_check == 0) {
            umc_error("{red}You do not have a home with that name!");
        }
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
    umc_log('homes', 'sell', "{$UMC_USER['uuid']}/{$UMC_USER['username']} sold the home called {$args[2]}!");
}

function umc_home_update() {
    global $UMC_USER;
    $args = $UMC_USER['args'];
    // home name
    if (isset($args[2])) {
        $name = umc_mysql_real_escape_string(trim($args[2]));
        // check if the name already exists
        $name_check = umc_home_count($name);
        if ($name_check <> 1) {
            umc_error("{red}You do not have a home with that name!");
        }
    } else {
        umc_error("{red}You need to specify the name of your new home!");
    }
    $sql = "UPDATE minecraft_srvr.`homes` SET `world`='{$UMC_USER['world']}',`x`='{$UMC_USER['coords']['x']}',`y`='{$UMC_USER['coords']['y']}',`z`='{$UMC_USER['coords']['z']}',`yaw`='{$UMC_USER['coords']['yaw']}' "
        . "WHERE uuid='{$UMC_USER['uuid']}' AND name=$name LIMIT 1;";
    umc_mysql_query($sql, true);
    umc_echo("The coordinates of home {$args[2]} were updated to the current location!");
}

function umc_home_rename() {
    global $UMC_USER;
    $args = $UMC_USER['args'];
    // home name
    if (isset($args[2]) && isset($args[3])) {
        $old_name = umc_mysql_real_escape_string(trim($args[2]));
        // check if the name already exists
        $name_check = umc_home_count($old_name);
        if ($name_check <> 1) {
            umc_error("{red}You do not have a home with that name!");
        }
        $new_name = umc_mysql_real_escape_string(trim($args[3]));
        $new_name_check = umc_home_count($new_name);
        if ($new_name_check == 1) {
            umc_error("{red}You already have a home with that name!");
        }
    } else {
        umc_error("{red}You need to specify the name of your new home!");
    }
    $sql = "UPDATE minecraft_srvr.`homes` SET `name`=$new_name "
        . "WHERE uuid='{$UMC_USER['uuid']}' AND name=$old_name LIMIT 1;";
    umc_mysql_query($sql, true);
    umc_echo("The name of home {$args[2]} was updated to {$args[3]}!");
}

// find out how many homes a user has already
function umc_home_count($name = false) {
    global $UMC_USER;
    $name_sql = '';
    if ($name) {
        $name_sql = "AND name=$name";
    }
    $sql = "SELECT count(home_id) as count FROM minecraft_srvr.homes WHERE uuid='{$UMC_USER['uuid']}' $name_sql;";
    $D = umc_mysql_fetch_all($sql);
    $homes = $D[0]['count'];
    return $homes;
}

// import current homes from the essential plugin
function umc_home_import() {
    global $UMC_USER;
    // we automatically import old homes for all players on login, but only once
    $existing_count = umc_home_count();
    if ($existing_count > 0) {
        return;
    }

    // include spyc to parse YAML https://github.com/mustangostang/spyc
    require_once('/home/includes/spyc/Spyc.php');
    $path = '/home/minecraft/server/bukkit/plugins/Essentials/userdata/' . $UMC_USER['uuid'] . ".yml";
    $A = Spyc::YAMLLoad($path);
    if (!isset($A['homes']) || count($A['homes']) == 0) {
        return;
    }
    $H = $A['homes'];

    // iterate homes and import them
    foreach ($H as $home_name => $h) {
        $name = umc_mysql_real_escape_string($home_name);
        // XMPP_ERROR_trigger($h);
        $sql = "INSERT INTO minecraft_srvr.`homes`(`name`, `uuid`, `world`, `x`, `y`, `z`, `yaw`) VALUES "
            . "($name,'{$UMC_USER['uuid']}','{$h['world']}','{$h['x']}','{$h['y']}','{$h['z']}','{$h['yaw']}');";
        umc_mysql_query($sql, true);
    }
    umc_log('homes', 'import', "{$UMC_USER['uuid']}/{$UMC_USER['username']}homes have been imported!");
}

function umc_home_list() {
    global $UMC_USER;
    $sql = "SELECT * FROM minecraft_srvr.homes WHERE uuid='{$UMC_USER['uuid']}' ORDER BY world, name;";
    $D = umc_mysql_fetch_all($sql);
    $count = count($D);
    umc_header("Your home list ($count homes)");

    $cur_world = false;
    $out = '';
    $worldhomes = array();
    foreach ($D as $d) {
        if ($d['world'] <> $cur_world) {
            if ($cur_world) {
                $out .= implode(", ", $worldhomes);
                umc_echo($out);
            }
            $out = "{red}{$d['world']}:{white} ";
            $worldhomes = array();
        }
        $worldhomes[] = $d['name'];
        $cur_world = $d['world'];
    }
    umc_footer();
}
