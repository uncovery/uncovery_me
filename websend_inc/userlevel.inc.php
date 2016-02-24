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
        'server_pre_reboot' => 'umc_userlevel_donation_update_all',
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
    umc_userlevel_donator_update($uuid);
}

/**
 * Get the userlevel of a user
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

    // upgrade on the server
    $check = umc_exec_command("pex user $uuid group set $newlevel");
    // if the server was not online, we need to do it in the database directly.
    if (!$check) {
        $uuid_sql = umc_mysql_real_escape_string($uuid);
        $level_sql = umc_mysql_real_escape_string($newlevel);
        $sql = "UPDATE `permissions_inheritance` SET parent=$level_sql WHERE `child`=$uuid_sql";
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

/**
 * go through all donators and make sure that they are downgraded if required
 */
function umc_userlevel_donation_update_all() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $sql = "SELECT sum(`amount`), donations.`uuid`, sum(amount - (DATEDIFF(NOW(), `date`) / 30)) as leftover
        FROM minecraft_srvr.donations
        LEFT JOIN minecraft_srvr.UUID ON UUID.uuid=donations.uuid
        WHERE userlevel LIKE '%Donator%' AND amount - (DATEDIFF(NOW(), `date`) / 30) < 2 AND donations.uuid <> 'void'
        GROUP BY uuid
        ORDER BY `leftover` DESC";
    $result = umc_mysql_fetch_all($sql);
    foreach ($result as $D) {
        umc_userlevel_donator_update($D['uuid']);
    }
}

/**
 * update the donator status user depending on their past donations.
 *
 * @param type $uuid
 * @return boolean
 */
function umc_userlevel_donator_update($uuid) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $is_donator = umc_userlevel_donation_remains($uuid);

    $userlevel = umc_userlevel_get($uuid);
    if ($userlevel == 'Owner') {
        return false;
    }

    $base_level_arr = umc_userlevel_get_base($userlevel);
    $base_level = $base_level_arr['level_name'];
    if ($is_donator) {
        if (strpos($userlevel, 'DonatorPlus')) { // all good
            $new_level = $base_level . "Donator";
            umc_userlevel_assign_level($uuid, $new_level);
        } else if (strpos($userlevel, 'Donator')) { // all good
            return;
        } else {
            $new_level = $userlevel . "Donator";
            umc_userlevel_assign_level($uuid, $new_level);
        }
    } else { // not donator
        if ($userlevel != $base_level) { // downgrade
            umc_userlevel_assign_level($uuid, $base_level);
        }
    }
}

/**
 * calculate if the user has active donations or not
 *
 * @param type $uuid
 * @return boolean
 */
function umc_userlevel_donation_remains($uuid) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    // we assume that they are not a donator

    // today's date
    $date_now = new DateTime("now");
    // lets get all the donations
    $sql_uuid = umc_mysql_real_escape_string($uuid);
    $sql = "SELECT amount, date FROM minecraft_srvr.donations WHERE uuid=$sql_uuid;";
    $D = umc_mysql_fetch_all($sql);
    // no donations, not donator
    if (count($D) == 0) {
        return false;
    }

    // go through all donations and find out how much is still active
    // the problem here is that if a user donated 2 USD twice 3 months ago
    // he is still a donator. we have to be aware about overlapping donations
    // that extend further into the future due to the overlap
    $donation_level = 0;
    foreach ($D as $row) {
        $date_donation = new DateTime($row['date']);
        $interval = $date_donation->diff($date_now);
        $years = $interval->format('%y'); // years since the donation
        $months = $interval->format('%m');
        $donation_term = ($years * 12) + $months;
        $donation_leftover = $row['amount'] - $donation_term;
        if ($donation_leftover < 0) {
            $donation_leftover = 0; // do not create negative carryforward
        }
        $donation_level += $donation_leftover;
    }
    if ($donation_level > 0) {
        return $donation_level;
    } else {
        return false;
    }
}

/**
 * return an array of all current donators
 *
 * @return type
 */
function umc_userlevel_donators_list() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $sql = "SELECT child as uuid FROM minecraft_srvr.permissions_inheritance WHERE parent LIKE '%Donator';";
    $D = umc_mysql_fetch_all($sql);
    $out_arr = array();
    foreach($D as $row) {
        $out_arr[] = $row['uuid'];
    }
    return $out_arr;
}