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
 * This allows users to add and remove members to their lot. It also allows users
 * to warp Guests to their lot before the reserve it.
 */

global $UMC_SETTING, $WS_INIT;

$WS_INIT['lot'] = array(
    'disabled' => false,
    'events' => array(
        'user_ban' => 'umc_lot_wipe_user',
        'user_delete' => 'umc_lot_wipe_user',
        // 'PlayerPreLoginEvent'  => 'umc_lot_end_wipe_inventory', // this does not work currently ue to file write permissions
        'server_reboot' => 'umc_uuid_record_lotcount',
    ),
    'default' => array(
        'help' => array(
            'title' => 'Lot Management',
            'short' => 'Add/remove members, en/disable snowfall on your lot',
            'long' => 'Allow others to build on your lot, en/disable snow accumulation, ice formation;',
            ),
    ),
    'manage' => array(
        'help' => array(
            'short' => 'Display an in-game menu for your lot;',
            'args' => '<lot>',
            'long' => 'This will display an in-game menu for your lot for easier management. If you do not give a lot argument, it will use your current lot.',
        ),
        'function' => 'umc_lot_manage',
    ),    
    'add' => array(
        'help' => array(
            'short' => 'Add features to your lot;',
            'args' => '<lot> <member|snow|ice> [user] [user2]...',
            'long' => 'Add a member so thet they can build on your lot or add snow accumulation or ice forming. You can list several users, separated with spaces.
                The added user needs to own a lot as well. See FAQ #32 for info.',
        ),
        'function' => 'umc_lot_addrem',
    ),
    'rem' => array(
        'help' => array(
            'short' => 'Remove features from  your lot;',
            'args' => '<lot> <member|snow|ice> [user] [user2]...',
            'long' => 'You can remove users or flags from your lot or remove snow accumulation or ice forming. You can list several users, separated with spaces.;',
        ),
        'function' => 'umc_lot_addrem',
    ),
    'give' => array(
        'help' => array(
            'short' => 'Give a lot to someone. Removes all other members.',
            'args' => '<lot> give [user]',
            'long' => 'Give a lot to someone. Removes all other members.',
        ),
        'function' => 'umc_lot_addrem',
        'security' => array(
            'level' => 'Owner',
         ),
    ),
    'transfer' => array(
        'help' => array(
            'short' => 'Give a lot to someone. Removes old owners only.',
            'args' => '<lot> transfer [user]',
            'long' => 'Give a lot to someone. Removes old owners only.',
        ),
        'function' => 'umc_lot_addrem',
        'security' => array(
            'level' => 'Owner',
         ),
    ),    
    'mod' => array(
        'help' => array(
            'short' => 'Add/Remove yourself from a flatlands lot for emergency fixes',
            'args' => '<lot> <add|rem>',
            'long' => '',
        ),
        'function' => 'umc_lot_mod',
        'security' => array(
            'level'=>'Elder',
            // 'level'=>'ElderDonator',
         ),
    ),
    'warp' => array(
        'help' => array(
            'short' => 'Teleport yourself to a lot - only usable by guests for the settler test',
            'args' => '<lot>',
            'long' => 'Teleport yourself to a lot - only usable by guests for the settler test',
        ),
        'function' => 'umc_lot_warp',
    ),
    'resetflags' => array(
        'help' => array(
            'short' => 'Resets the usage flags on your lot',
            'args' => '<lot>',
            'long' => 'This will reset all flags on your lot. It will also remove all snowfall and iceform flgs. Use this in case people cannot use your doors and buttons.',
        ),
        'function' => 'umc_lot_reset_flags',
    ) //
);

function umc_lot_mod() {
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $uuid = $UMC_USER['uuid'];
    $args = $UMC_USER['args'];
    $userlevel = $UMC_USER['userlevel'];

    /// /lotmember lot world add target

    $addrem = $args[3];
    $lot = strtolower($args[2]);

    if (count($args) <= 2) {
        umc_echo("Too few arguments!");
        umc_show_help($args);
        return;
    }
    $world = 'flatlands';

    $user_id = umc_get_worldguard_id('user', strtolower($player));
    if (!$user_id) {
        umc_error("Your user id cannot be found!");
    }

    $world_id = umc_get_worldguard_id('world', $world);
    if (!$world_id) {
        umc_error("The lot '$lot' cannot be found in any world!");
    }

    if (!umc_check_lot_exists($world_id, $lot)) {
        umc_error("There is no lot $lot in world $world;");
    }
    if ($userlevel !== 'Owner' && $userlevel !== 'Elder' && $userlevel !== 'ElderDonator') {
        umc_error("You are not Elder or Owner, you are $userlevel!");
    }

    if ($addrem == 'add') {
        $sql_ins = "INSERT INTO minecraft_worldguard.region_players (`region_id`, `world_id`, `user_id`, `Owner`)
            VALUES ('$lot', '$world_id', $user_id, 0);";
        umc_mysql_query($sql_ins, true);
        umc_echo("Added you to $lot in the $world!");
    } else if ($addrem == 'rem') {
        // check if target is there at all
        $sql = "SELECT * FROM minecraft_worldguard.region_players WHERE region_id='$lot' AND world_id=$world_id AND user_id=$user_id AND Owner=0 LIMIT 1;";
        $D = umc_mysql_fetch_all($sql);
        if (count($D) !== 1) {
            umc_error("It appears you are not a member of lot $lot in world $world!");
        }
        $sql_del = "DELETE FROM minecraft_worldguard.region_players WHERE region_id = '$lot' AND world_id = $world_id AND user_id = $user_id AND Owner=0;";
        umc_mysql_query($sql_del, true);
        umc_echo("Removed you from $lot in the $world!");
    } else {
        umc_error("You can only use [add] or [rem], not {$args[1]}!");
    }
    umc_ws_cmd('region load -w flatlands', 'asConsole');
    umc_log('lot', 'mod', "$player added himself to lot $lot to fix something");
    XMPP_ERROR_send_msg("$player added himself to lot $lot to fix something");
}


function umc_lot_addrem() {
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $uuid = $UMC_USER['uuid'];
    $args = $UMC_USER['args'];
    $userlevel = $UMC_USER['userlevel'];

    /// /lotmember lot world add target
    if ((count($args) <= 3)) {
        umc_echo("{red}Not enough arguments given");
        umc_show_help($args);
        return;
    }

    $addrem = $args[1];
    $lot = strtolower($args[2]);
    $action = $args[3];

    $worlds = array(
        'emp' => 'empire',
        'fla' => 'flatlands',
        'dar' => 'darklands',
        'aet' => 'aether',
        'kin' => 'kingdom',
        'dra' => 'draftlands',
        'blo' => 'skyblock',
        'con' => 'aether');

    $world_abr = substr($lot, 0, 3);
    if (!isset($worlds[$world_abr])) {
        umc_error("Your used an invalid lot name!");
    }
    $world = $worlds[$world_abr];

    if ($player == '@console') {
        $player = 'uncovery';
    }

    $user_id = umc_get_worldguard_id('user', strtolower($player));
    if (!$user_id) {
        umc_error("Your user id ($player) cannot be found!");
    }

    $world_id = umc_get_worldguard_id('world', $world);
    if (!$world_id) {
        umc_show_help($args);
        umc_error("The lot '$lot' cannot be found in any world!");
    }

    if (!umc_check_lot_exists($world_id, $lot)) {
        umc_show_help($args);
        umc_error("There is no lot $lot in world $world!");
    }

    if ($action == 'snow' || $action == 'ice') {
        // check if the user has Donator status.

        if ($userlevel !== 'Owner') {
            if (!stristr($userlevel, 'Donator')) {
                umc_error("You need to be Donator level to use the snow/ice features!;");
            }
            $owner_switch = 0;
            // check if player is Owner of lot
            $sql = "SELECT * FROM minecraft_worldguard.region_players
                WHERE region_id='$lot' AND world_id=$world_id AND user_id=$user_id and Owner=1;";
            $D = umc_mysql_fetch_all($sql);
            $num = count($D);
            if ($num != 1) {
                umc_error("It appears you $player ($user_id) are not Owner of lot $lot in world $world!");
            }
        }
        // get the current status of the flags
        if ($addrem == 'add') {
            $flag = 'allow';
            umc_echo("Allowing $action forming on lot $lot... ");
        } else if ($addrem == 'rem')  {
            $flag = 'deny';
            umc_echo("Preventing $action forming on lot $lot... ");
        } else {
            umc_show_help($args);
        }
        if ($action == 'snow') {
            $flagname = 'snow-fall';
        } else if ($action == 'ice') {
            $flagname = 'ice-form';
        }
        // does flag exist?
        $check_sql = "SELECT * FROM minecraft_worldguard.region_flag WHERE region_id='$lot' AND world_id=$world_id AND flag='$flagname';";
        $D2 = umc_mysql_fetch_all($check_sql);
        $count = count($D2);
        if ($count == 0) {
            // insert
            $ins_sql = "INSERT INTO minecraft_worldguard.region_flag (region_id, world_id, flag, value) VALUES ('$lot', $world_id, '$flagname', '$flag');";
            umc_mysql_query($ins_sql, true);
        } else {
            // update
            $upd_sql = "UPDATE minecraft_worldguard.region_flag SET value='$flag' WHERE region_id='$lot' AND world_id=$world_id AND flag='$flagname';";
            umc_mysql_query($upd_sql, true);
        }
        umc_echo("done!");
        umc_log('lot', 'addrem', "$player changed $action property of $lot");
    } else {
        if ($action == 'owner' || $action == 'give' || $action == 'transfer') {
            if ($player != 'uncovery' && $player != '@Console') {
                umc_error("Nice try, $player. Think I am stupid? Want to get banned?");
            }
            $owner_switch = 1;
        } else if ($action == 'member') {
            $user_id = umc_get_worldguard_id('user', strtolower($player));
            if (!$user_id && $player !== 'uncovery') {
                umc_error("Your user id cannot be found!");
            }
            $owner_switch = 0;
            // check if player is Owner of lot
            if ($userlevel !== 'Owner') {
                $sql = "SELECT * FROM minecraft_worldguard.region_players WHERE region_id='$lot' AND world_id=$world_id AND user_id=$user_id and Owner=1;";
                $D3 = umc_mysql_fetch_all($sql);
                $count = count($D3);
                if ($count != 1) {
                    umc_error("It appears you ($player $user_id) are not Owner of lot $lot in world $world!");
                }
            }
        } else {
            umc_echo("Action $action not recognized!");
            umc_show_help($args);
            return;
        }
        // get list of active users
        $active_users = umc_get_active_members();

        for ($i=4; $i<count($args); $i++) {
            $target = strtolower($args[$i]);
            $target_uuid = umc_uuid_getone($target, 'uuid');

            // check if target player exists
            $target_id = umc_get_worldguard_id('user', strtolower($target));
            if (!$target_id) {
                umc_error("The user $target does not exist in the database. Please check spelling of username");
            }
            if ($player != 'uncovery') {
                $targ_group = umc_userlevel_get($target_uuid);
                if ($targ_group == 'Guest') {
                    umc_error("You cannnot add Guests to your lot!;");
                } else if (!in_array($target, $active_users)) {
                    XMPP_ERROR_trigger("$player tried to add $target to his lot $lot, but $target is not an active member!");
                    umc_error("$target is not an active user! You can only add people who have their own lot! See FAQ entry #32 please.");
                }
            }

            // add / remove target player from lot
            if ($addrem == 'add') {
                // make sure target is not already there
                $sql = "SELECT * FROM minecraft_worldguard.region_players WHERE region_id='$lot' AND world_id=$world_id AND user_id=$target_id;";
                $D3 = umc_mysql_fetch_all($sql);
                $num = count($D3);
                if ($num == 1) {
                    umc_error("It appears $target is already member of lot $lot in world $world!");
                }
                // add to the lot
                umc_lot_add_player($target, $lot, 0);
                umc_echo("Added $target to $lot in the $world!");
            } else if ($addrem == 'rem') {
                // check if target is there at all
                $sql = "SELECT * FROM minecraft_worldguard.region_players WHERE region_id='$lot' AND world_id=$world_id AND user_id=$target_id AND Owner=$owner_switch LIMIT 1;";
                $D3 = umc_mysql_fetch_all($sql);
                $num = count($D3);
                if ($num !== 1) {
                    umc_error("It appears user $target is not a member of lot $lot in world $world!");
                }
                umc_lot_rem_player($target, $lot, 0);
                umc_echo("Removed $target from $lot in the $world!");
            } else if ($addrem == 'give') {
                // remove all members and owners
                umc_lot_remove_all($lot);
                umc_lot_add_player($target, $lot, 1);
                umc_echo("Gave $lot to $target in the $world! All other user removed!");
                // logfile entry
                umc_log('lot', 'addrem', "$player gave lot to $target");
            } else if ($addrem == 'transfer') {
                // remove the target in case he's a member
                umc_lot_rem_player($target, $lot, 0);
                // get all current owners
                $owners = umc_get_lot_members($lot, true);
                // remove all current owners
                foreach ($owners as $uuid => $username) {
                    umc_lot_rem_player($uuid, $lot, 1);     
                }
                // add the new owner
                umc_lot_add_player($target, $lot, 1);
                umc_echo("Gave $lot to $target in the $world! Old Owners removed!");
                // logfile entry
                umc_log('lot', 'addrem', "$player gave lot to $target");             
            } else {
                umc_show_help($args);
            }
        }
    }
    umc_ws_cmd("region load -w $world", 'asConsole');
}

function umc_lot_warp() {
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $userlevel = $UMC_USER['userlevel'];
    $world = $UMC_USER['world'];
    $args = $UMC_USER['args'];

    $allowed_ranks = array('Owner', 'Guest');
    if (!in_array($userlevel, $allowed_ranks)) {
        umc_error("Sorry, this command is only for Guests!");
    }

    $allowed_worlds = array('empire', 'flatlands');

    if ($player != 'uncovery' && !in_array($world, $allowed_worlds)) {
        umc_error('Sorry, you need to be in the Empire or Flatlands to warp!');
    }

    if (isset($args[2])) {
        $lot = strtolower(umc_sanitize_input($args[2], 'lot'));
        // the above one fails already if the lot is not a proper lot
        $target_world = umc_get_lot_world($lot);
        if ($player != 'uncovery' && !in_array($target_world, $allowed_worlds)) {
            umc_error("Sorry, $player, you need to enter a lot name from the empire or flatlands. Lot names are for example 'emp_a1'");
        }
        if ($target_world != $world) {
            umc_error("Sorry, you need to be in $target_world to warp to $lot!");
        }
        // check if lot exists
        $lot_check = umc_check_lot_exists($target_world, $lot);
        if (!$lot_check) {
            umc_error('Sorry, this lot does not exist! Lot names are for example "emp_a1"');
        }
    } else {
        umc_error("Sorry, you need to enter the lot name after /lot warp!");
    }

    $sql = "SELECT * FROM minecraft_worldguard.world LEFT JOIN minecraft_worldguard.region ON world.id=region.world_id
        LEFT JOIN minecraft_worldguard.region_cuboid ON region.id=region_cuboid.region_id
        WHERE world.name='$target_world' AND region.id = '$lot' ";

    $D = umc_mysql_fetch_all($sql);
    if (count($D) != 1) {
        XMPP_ERROR_trigger("Could not get coordinates for lot warp command: $sql");
        umc_error("There was an error teleporting you to your lot, the admin was notified, please try again. If the error persists, please wait for it to be fixed!");
    }
    $lots = $D[0];

    $c_x = $lots['min_x'] + 64;
    $c_z = $lots['min_z'] + 64;
    $c_y = 256;

    $cmd = "tppos $player $c_x $c_y $c_z 0 0 $world";
    umc_ws_cmd($cmd, 'asConsole');
    umc_pretty_bar("darkblue", "-", "{darkcyan} Warping to lot $lot");
    umc_echo("You are now in the center of lot $lot!");
    umc_footer();
}

/**
 * wipe a user from all lots
 * @param type $uuid
 */
function umc_lot_wipe_user($uuid) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    // delete all dibs the user has
    umc_lot_manager_dib_delete($uuid);

    // get all lots of that user
    $lots = umc_lot_by_owner($uuid);
    foreach ($lots as $world => $L) {
    // remove that user from the lots
        $lot = $L['lot'];
        umc_lot_rem_player($player, $lot, 1);

    // check if someone else has dibs for that lot

    }
    // same for members:
    $lot_members = umc_lot_by_owner($uuid, false, false);
    foreach ($lot_members as $world => $L) {
    // remove that user from the lots
        $lot = $L['lot'];
        umc_lot_rem_player($player, $lot, 0);

    // check if someone else has dibs for that lot

    }
}

/**
 * returns all lots of a specific user
 *
 * @param type $uuid
 * @param type $world
 * @return type
 */
function umc_lot_by_owner($uuid, $world = false, $owner = true) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    // worldguard stores everything in lower case.
    $filter = '';
    if ($world) {
        if (is_array($world)){
            $worlds = implode("','", $world);
            $filter = "AND world.name IN('$worlds')";
        } else {
            $filter = "AND world.name = '$world'";
        }
    }

    if (!$owner) {
        $owner_str = 0;
    } else {
        $owner_str = 1;
    }

    $sql = "SELECT region_id, world.name FROM minecraft_worldguard.`region_players`
        LEFT JOIN minecraft_worldguard.user ON user_id = user.id
        LEFT JOIN minecraft_worldguard.world ON world_id = world.id
        WHERE Owner=$owner_str AND uuid='$uuid' $filter ORDER BY region_id;";
    $R = umc_mysql_fetch_all($sql);
    $out = array();
    //echo $sql;
    foreach ($R as $row) {
        $link = umc_lot_get_tile($row['region_id'], $row['name']);
        if (!$link) {
            $link = '';
        }
        $lot = $row['region_id'];
        $out[$lot] = array('world' => $row['name'], 'lot' => $lot, 'image' => $link);
    }
    return $out;
}

/**
 * On user login, we need to wipe the inventory to make sure end reset is not abused.
 *
 * @param type $uuid
 */
function umc_lot_end_wipe_inventory() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $uuid = $UMC_USER['uuid'];
    umc_inventory_delete_world($uuid, 'the_end');
}


function umc_lot_reset_flags() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $uuid = $UMC_USER['uuid'];
    $args = $UMC_USER['args'];
    $lot = $args[2];

    $username = $UMC_USER['username'];
    if ($username != '@console') {
        $check = umc_check_lot_owner($lot, $uuid);
        if (!$check) {
            umc_error("You $username are not the owner of that lot!");
        }
    }

    umc_check_lot_owner($lot, $uuid);
    umc_lot_flags_set_defaults($lot);
    umc_echo("The flags for lot $lot have been reset!");
}

function umc_lot_manage() {
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $args = $UMC_USER['args'];

    if (isset($args[2])) {
        $lot = strtolower($args[2]);
    } else { // no lot given, get the current user's lot
        $world = $UMC_USER['world'];
        $x = round($UMC_USER['coords']['x'],1);
        $z = round($UMC_USER['coords']['z'],1);    
        $lot = umc_lot_get_from_coords($x, $z, $world);
        if (!$lot) {
            umc_error('There is no lot here!');
        }
        $lot_owners = umc_get_lot_members($lot, true);
        if (!in_array(strtolower($player), $lot_owners)) {
            $text = 'You are not owner of this lot! Owners are ' . implode(",", $lot_owners);
            XMPP_ERROR_trigger($text);
            umc_error($text);
        }
    }
    $lot_members = umc_get_lot_members($lot, false);
    $online_users = $UMC_USER['online_players'];
    
    umc_header("Lot $lot");
    // show current users for removal
    $members_str = array();
    $members_str[] = array('text' => 'Members: ', 'format' => 'blue');
    if (!$lot_members || count($lot_members) == 0) {
        $members_str[] = array('text' => "(No members)", 'format' => 'white');
    } else {
        foreach ($lot_members as $member) {
           $members_str[] = array('text' => "$member ", 'format' => 'white');
           $members_str[] = array('text' => "[x] ", 'format' => array('red', 'run_command' => "/lot rem $lot member $member", 'show_text' => "Remove $member from lot"));
        }
    }
    umc_text_format($members_str, false, false);
    
    // show active users for adding
    $new_members_str = array();
    $new_members_str[] = array('text' => 'Current Users: ', 'format' => 'blue');
    if (count($online_users) == 0) {
        $members_str[] = array('text' => "(No online users)", 'format' => 'white');
    }    
    foreach ($online_users as $user) {
       if (($lot_members && in_array($user, $lot_members)) || $user == strtolower($player)) {
           continue;
       }
       $new_members_str[] = array('text' => "$user ", 'format' => 'white');
       $new_members_str[] = array('text' => "[+] ", 'format' => array('green', 'run_command' => "/lot add $lot member $user", 'show_text' => "Add $user to lot"));
    } 
    umc_text_format($new_members_str, false, false);
    
    umc_footer();
}