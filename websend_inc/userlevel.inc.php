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

/**
 * This file handles all user-level related events and functions.
 *
 * TODO: have one donator level only
 * Process:
 * We downgrade all simple donators
 * Then we re-balance all the
 */

global $UMC_SETTING, $WS_INIT;

$WS_INIT['userlevel'] = array(  // the name of the plugin
    'disabled' => false,
    'events' => array(
        'PlayerJoinEvent' => 'umc_userlevel_player_check',
    ),
    'default' => array(
        'help' => array(
            'title' => 'Userlevl commands',  // give it a friendly title
            'short' => 'Various Userlevel functions.',  // a short description
            'long' => "Please see the commands for further info.", // a long add-on to the short  description
        ),
    ),
);


$UMC_SETTING['userlevels'] = array(
    'base_levels' => array(
        0 => 'Guest' ,
        1 => 'Settler',
        2 => 'Citizen',
        3 => 'Architect',
        4 => 'Designer',
        5 => 'Master',
        6 => 'Elder'
    ),
);

/**
 * this function is executed on player join event
 * it makes sure that people have the right citizen and donator status
 *
 * @global type $UMC_USER
 */
function umc_userlevel_player_check() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $uuid = $UMC_USER['uuid'];

    umc_userlevel_citizen_update($uuid);
}

/**
 * Get the userlevel of a user, NOT multi-level-safe
 *
 * @global type $UMC_USER
 * @param type $uuid
 * @return boolean|string
 */
function umc_userlevel_get($uuid) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    // did we query a username instead of a uuid?
    if (strlen($uuid) < 35) {
        XMPP_ERROR_trigger("umc_get_uuid_level: Tried to get uuid-level of invalid UUID: $uuid");
        return false;
    }

    // check if the userlevel is already set
    if ($uuid == $UMC_USER['uuid'] && isset($UMC_USER['userlevel'])) {
        return $UMC_USER['userlevel'];
    }

    $uuid_sql = umc_mysql_real_escape_string($uuid);
    // TODO: we do not need the username here so we can probably simplify this
    $sql = "SELECT parent AS userlevel, value AS username, name AS uuid FROM minecraft_srvr.permissions
        LEFT JOIN minecraft_srvr.`permissions_inheritance` ON name=child
        WHERE `name`=$uuid_sql AND permissions.permission='name'";
    $D = umc_mysql_fetch_all($sql);

    // user not found, so he's guest
    if (count($D) == 0)  {
        // we give the console Owner priviledges
        if (strtolower($uuid) == '@console') {
            $uuid_level = "Owner";
        } else { // everyone else we cannot find is a guest
            $uuid_level = "Guest";
        }
    } else {
        $level = $D[0]['userlevel'];
        if ($level == 'NULL') {
            $level = 'Guest';
        }
        $uuid_level = $level;
    }
    return $uuid_level;
}

/**
 * returns the userlevel for a username
 *
 * @param type $username
 * @return string
 */
function umc_get_userlevel($username) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    if (!is_array($username) && (strtolower($username) == '@console')) {
        return "Owner";
    }
    if (is_array($username)) {
        $user_arr = $username;
    } else {
        $username_str = $username;
        $user_arr = array($username);
    }
    // fix username capitalization
    $user_arr_ok = array();
    foreach ($user_arr as $name) {
        $sql_search = "SELECT value FROM minecraft_srvr.permissions WHERE value LIKE '$name' LIMIT 1;";
        $D = umc_mysql_fetch_all($sql_search);
        if (count($D) == 0){
            return 'Guest';
        }
        $row = $D[0];
        $user_arr_ok[] = $row['value'];
    }
    $username_str = implode("','", $user_arr_ok);

    $sql = "SELECT parent as userlevel, value as username, name as uuid FROM minecraft_srvr.permissions
        LEFT JOIN minecraft_srvr.`permissions_inheritance` ON name=child
        WHERE `value` IN ('$username_str') AND permissions.permission='name';";
    $D = umc_mysql_fetch_all($sql);

    $user_levels = array();
    // user not found, so he's guest
    if (count($D) == 0)  {
        return "Guest";
    }
    //parent 	value 	name
    // Owner 	uncovery	ab3bc877-4434-45a9-93bd-bab6df41eabf

    // otherwise get results
    // umc_error_notify($username_str);
    $row = $D[0];
    $user = $row['username'];
    $level = $row['userlevel'];
    if ($level == NULL) {
        $level = 'Guest';
    }
    $user_levels[strtolower($user)] = $level;
    // check if all users were found, if not, set them as guest
    if (count($user_arr_ok) > 1) {
        foreach ($user_arr_ok as $user) {
            $lower_user = strtolower($user);
            if (!isset($user_levels[$lower_user])) {
                $user_levels[$lower_user] = 'Guest';
            }
        }
        return $user_levels;
    } else {
        $lower_user = strtolower($username_str);
        if (!isset($user_levels[$lower_user])) {
            XMPP_ERROR_trigger("Could not find userlevel for user $username with $sql");
            return "Guest";
        }
        return $user_levels[$lower_user];
    }
}


/**
 * Retrieve the userlevel from a uuid
 *
 * @global type $UMC_USER
 * @param type $uuid
 * @return string
 */
function umc_get_uuid_level($uuid) {
    global $UMC_USER, $UMC_SETTING;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    // check if the userlevel is already set (only if the user is the current
    if ($uuid == $UMC_USER['uuid'] && isset($UMC_USER['userlevel'])) {
        return $UMC_USER['userlevel'];
    }

    if (!is_array($uuid) && (strtolower($uuid) == '@console')) {
        return "Owner";
    }
    if (strlen($uuid) < 35) {
        XMPP_ERROR_trigger("umc_get_uuid_level: Tried to get uuid-level of invalid UUID: $uuid");
    }

    if (is_array($uuid)) {
        $uuid_str = implode("','", $uuid);
        $count = count($uuid);
    } else {
        $uuid_str = $uuid;
        $count = 1;
    }
    //SELECT * FROM `permissions_inheritance` WHERE `child` LIKE 'a1b763b9-bd7d-4914-8b4b-8c20bddb5882' ORDER BY `child` DESC

    $valid_levels_str = implode("','", $UMC_SETTING['ranks']);

    $sql = "SELECT parent AS userlevel, value AS username, name AS uuid FROM minecraft_srvr.permissions
        LEFT JOIN minecraft_srvr.`permissions_inheritance` ON name=child
        WHERE permissions.permission='name' AND `name` IN ('$uuid_str') AND parent IN ('$valid_levels_str') ";
    $D = umc_mysql_fetch_all($sql);
    $uuid_levels = array();
    // user not found, so he's guest
    if (count($D) == 0)  {
        return "Guest";
    }
    //parent 	value 	name
    // Owner 	uncovery	ab3bc877-4434-45a9-93bd-bab6df41eabf

    // otherwise get results
    foreach ($D as $row) {
        $uuid = $row['uuid'];
        $level = $row['userlevel'];
        // for now, ignore the Donor level
        if (!in_array($level, $UMC_SETTING['ranks'])) {
            continue;
        }
        if ($level == 'NULL') {
            $level = 'Guest';
        }
        $uuid_levels[$uuid] = $level;
    }
    // check if all users were found, if not, set them as guest
    if (is_array($uuid)) {
        foreach ($uuid as $user) {
            if (!isset($uuid_levels[$user])) {
                $uuid_levels[$user] = 'Guest';
            }
        }
        return $uuid_levels;
    } else {
        if (!isset($uuid_levels[$uuid_str])) {
            // umc_error_msg("Could not determine userlevel for UUID $uuid");
            return "Guest";
        }
        return $uuid_levels[$uuid_str];
    }
}

/**
 * promotes a user to Citizen if applicable
 * whenever we write the onlinetime for a user, we should check if this applies
 *
 * @param string $uuid
 * @param string $userlevel
 */
function umc_userlevel_citizen_update($uuid, $userlevel = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    if (!$userlevel) {
        $userlevel = umc_userlevel_get($uuid);
    }
    if (strpos($userlevel, 'Settler')) {
        $online_hours = umc_get_online_hours($uuid);
        if ($online_hours >= 60) {
            umc_log("users", "Citizen", "User $uuid was online $online_hours, hours let's promote!");
            umc_userlevel_promote_onelevel($uuid);
        }
    }
}

/**
 * Generic promotion function
 * Promotes a user while keeping the donation status the same
 *
 * @param string $uuid
 */
function umc_userlevel_promote_onelevel($uuid) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_SETTING;

    // first, we get the current userlevel and it's base
    $userlevels = umc_userlevel_get($uuid);
    $userlevel = $userlevels[$uuid];
    $base_level_arr = umc_userlevel_get_base($userlevel);
    $user_base_level_id = $base_level_arr['level_id'];

    // lets create the new userlevel, first upgrade the base level
    $user_base_level_id++;
    // Get the new level name
    $new_level = $UMC_SETTING['userlevels']['base_levels'][$user_base_level_id];
    // add the donator status if the old level had that
    // alternatively we could skip this and just run the donator check again.
    if (strpos($userlevel, "Donator")) {
        $new_level .= "Donator";
    }
    umc_userlevel_assign_level($uuid, $new_level);
}

/**
 * assign a new userlevel to a user
 *
 * @param type $uuid
 * @param type $newlevel
 */
function umc_userlevel_assign_level($uuid, $newlevel) {
    global $UMC_SETTING;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    // check if level is valid
    if (!in_array($newlevel, $UMC_SETTING['ranks'])) {
        XMPP_ERROR_trigger("Tried to set invalid userlevel $newlevel for user $uuid!");
        return;
    }

    //always make sure first letter of groupname is capitalised
    $newlevel = ucfirst($newlevel);

    // upgrade on the server
    $check = umc_exec_command("pex user $uuid group set $newlevel");
    // if the server was not online, we need to do it in the database directly.
    if (!$check) {
        $uuid_sql = umc_mysql_real_escape_string($uuid);
        $level_sql = umc_mysql_real_escape_string($newlevel);
        $sql = "UPDATE minecraft_srvr.permissions_inheritance SET parent=$level_sql WHERE `child`=$uuid_sql";
        umc_mysql_execute_query($sql);
        // try at lease to reloaduserlvels
        umc_exec_command("pex reload");
    }
    umc_log("users", "promotion", "User $uuid was promoted to $newlevel");
}

/**
 * returns the base level of a level (without donator status)
 * while we could do this with an array, this system does not break if we add new levels
 *
 * @global array $UMC_SETTING
 * @param type $userlevel
 * @return type
 */
function umc_userlevel_get_base($userlevel) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_SETTING;
    // if we are not told what the userlevel us
    $base_levels = $UMC_SETTING['userlevels']['base_levels'];
    foreach ($base_levels as $base_level_id => $base_level_string) {
        if (strstr($userlevel, $base_level_string)) {
            return array('level_id' => $base_level_id, 'level_name' => $base_level_string);
        }
    }
    return false;
}

/*
This function checks the rank permissions for the plugin rights
*/
function umc_rank_check($player_rank, $required_rank) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_SETTING;
    if ($player_rank == 'Owner') {
        return true;
    }
    foreach ($UMC_SETTING['ranks'] as $rank) {
        if ($rank == $required_rank) { // We got to the required rank without finding the player's rank first, success!
            return true;
        }
        if ($rank == $player_rank) { // We got to the players rank without finding the require rank first, failure.
            return false;
        }
    } // We didn't find either rank yet, move on to the next one.
    // we could not find the rank at all, fail and alert
    XMPP_ERROR_trigger("Could not identify rank $player_rank / $required_rank (umc_rank_check)");
    return false;
}

/**
 * This is setting a permission for a user through websend /pex user command
 * @param type $user
 * @param type $permission
 * @param type $world
 * @param string $timed
 */
function umc_user_permission_set($user, $permission, $world = false, $timed = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    if (!$world) {
        $world = '';
    } else {
        $world = " $world";
    }
    $timed_activator = ' timed';
    if (!$timed) {
        $timed = '';
        $timed_activator = '';
    }
    $uuid = umc_uuid_getone($user, 'uuid');

    $cmd = "pex user $uuid$timed_activator add $permission$world";
    umc_exec_command($cmd, 'asConsole');
}


/**
 * retrieve the last login date of active users and their userlevel
 * TODO: This is only used for the map. can e split this in two functions?
 * @return array
 */
function umc_users_active_lastlogin_and_level() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $sql_1 = "SELECT user.uuid as uuid, lower(username) as username, lastlogin, userlevel
        FROM minecraft_worldguard.region_players
        LEFT JOIN minecraft_worldguard.user ON user_id=id
        LEFT JOIN minecraft_srvr.UUID ON user.uuid=UUID.UUID
        WHERE owner=1 AND user.uuid IS NOT NULL AND username is NOT NULL
        GROUP BY username
        ORDER BY username;";
    // XMPP_ERROR_trace($sql);
    $D = umc_mysql_fetch_all($sql_1);
    $lastlogins = array('abandone-0000-0000-0000-000000000000' => array('username' => '_abandoned_', 'userlevel' => 'Guest', 'lastlogin' => '2010-01-01 00:00:00'));
    foreach ($D as $row) {
        $lastlogins[$row['uuid']]['lastlogin'] = $row['lastlogin'];
        $lastlogins[$row['uuid']]['username'] = $row['username'];
        $lastlogins[$row['uuid']]['userlevel'] = $row['userlevel'];
        // default Guest; if there is a level set, it will be updated in the next step
        // $lastlogins[$row['name']]['userlevel'] = 'Guest';
    }

    // chek for userlevel issues
    foreach ($lastlogins as $uuid => $D) {
        if (!isset($D['userlevel'])) {
            XMPP_ERROR_trigger("User $uuid / {$D['username']} has a lot but no userlevel!!");
        }
    }
    return $lastlogins;
}
