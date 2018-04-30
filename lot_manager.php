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
 * This is the central lot management system, mainly used during lot registration
 * when becoming a Settler or on the website when regisering a lot or managing
 * the lot features. This system is quite complex and - if broken - can create
 * a huge amount of issues on the server, hadle with care.
 */

global $UMC_USER;
/**
 * Main Lot manager function, single direct entry into the file
 */
function umc_lot_manager_main() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER, $UMC_DOMAIN;
    // levels which should be able to get draftlands lots
    $elder_ranks = array('Elder', 'ElderDonator', 'Owner');

    // ***** ACCESS ***** //
    $out = '';
    if (!$UMC_USER) {
        return "<strong>You need to be <a href=\"$UMC_DOMAIN/wp-login.php\">logged in</a></strong> to see this!\n";
    } else {
        $username = $UMC_USER['username'];
        $userlevel = $UMC_USER['userlevel'];
        $uuid = $UMC_USER['uuid'];
    }

    if ($userlevel == 'Guest') {
        return "Since you are not Settler yet, you need to go to <a href=\"$UMC_DOMAIN/private-area-allocation-map/\">this page</a> to become one!";
    } else if (in_array($userlevel, $elder_ranks)) {
        $worlds = array('empire', 'aether', 'kingdom', 'skyblock', 'draftlands');
    } else {
        $worlds = array('empire', 'aether', 'kingdom', 'skyblock');
    }

    $out .= "Welcome $username ($userlevel)!<br>";

    // ***** INITIALIZE ***** //
    if (!isset($UMC_USER['lots'])) {
        $UMC_USER['lots'] = array();
        foreach ($worlds as $world) {
            $UMC_USER['lots'][$world] = umc_get_lot_number($uuid, $world);
        }

    }
    $sani_post  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
    $sani_get  = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
    // is this a form for a single lot?
    $edit_lot = false;
    if (isset($sani_post['edit_lot'])) {
        $world = umc_get_lot_world($sani_post['lot']);
        $edit_lot = $sani_post['lot'];
    } else if (isset($sani_get['world'])) {
        $world = $sani_get['world'];
        if (!in_array($world, $worlds)) {
            $world = 'empire';
        }
    } else {
        $world = 'empire';
    }
    if ($world == 'flatlands') {
        $world = 'empire';
    }

    if (isset($sani_post['delete_dib'])) {
        $dib_lot = $sani_post['lot'];

        $out .= umc_lot_manager_dib_delete($uuid, $dib_lot);
    }

    if (isset($sani_post['save_lot'])) {
        $out .= umc_lot_manager_process();
    }

    if (isset($sani_post['new_lot']) && ($sani_post['new_lot'] != 'false')) {
        // add the new lot
        $check = umc_lot_manager_check_before_assign($uuid, $sani_post['new_lot']);
        if ($check['result']) {
            umc_lot_add_player($uuid, $sani_post['new_lot'], 1, $check['cost']);
        }
        $out .= $check['text'];
    }

    if (isset($sani_post['new_dib']) && ($sani_post['new_dib'] != 'false')) {
        // add the new lot
       $result = umc_lot_manager_dib_add($uuid, $sani_post['new_dib'], $sani_post['dib_action']);
       $out .= $result;
    }


    $out .= "\n<strong>Notes:</strong>
        <ol>
            <li>If you chose to<strong> abandon, refund or give</strong> a lot to someone else, you will <span style=\"color: #ff0000;\">lose access immediately</span>! If that gives you free available lots in the same world, you can pick a new lot immediately!</li>
            <li>Resets will happen at the next restart of the server. Until then, you can still change your mind. Restarts are at 16:00 HKG (see time at the server status on the front page)</li>
            <li>You can abandon a flatlands lot anytime and get an empire lot for it, but you cannot abandon an empire lot if it is generated with the current minecraft version!</li>
            <li>You can get dibs on an occupied lot. If nobody before you called dibs, you will get the lot once it's abandoned - provided you have the free lots/cash. This will be checcked at the moment of transfer. If you do not, the next in line gets it</li>
        </ol>
        The tabs show \"World (Owned Lots/Max lots/Dibs)\"
        \n";


    $out .= umc_lot_manager_menu($worlds, $world);

    $current_lots = $UMC_USER['lots'][$world];

    // get user lots per world

    $out .= "<div class=\"formbox\">";
    $out .= umc_lot_manager_get_lots($world, $edit_lot);

    $uworld = ucwords($world);
    if ($world == 'empire') {
        $uworld = 'Empire & Flatlands';
    }


    // in the dibs tab, we do not show a "new lot form".
    $out .= umc_lot_costs_simulation($world);
    $form = umc_lot_manager_new_lot_form($world);
    if ($current_lots['avail_lots'] == 0) {
        $out .= "<div class=\"newlotform\">You do not have enough available lots to get one in $uworld!</div>";
    } else if (!$form) {
        $out .= "<div class=\"newlotform\">There are no available lots for you in $uworld! "
                . "Further, we are changing the way kingdom lots are being bought, until then, no new lots are up for sale."
                . "You need to be Citizen for Ather lots. Also, you need to have 10'000 Uncs in your account for Kingdom lots, 50k for Draftland lots.</div>";
        return $out;
    } else {
        $out .= "<form class=\"newlotform\" method=\"POST\">\n"
            . "<p><strong>Get a new lot:</strong></p>\n"
            . "<p>You have {$current_lots['avail_lots']} lots available in $uworld.<br>If you want an additional lot, please chose from the list: "
            . $form . " <input type=\"submit\" name=\"submit\" value=\"Get this lot\"></p>\n</form>\n";
    }
    // dibs form
    if ($world != 'draftlands') {
        $dibs_form = umc_lot_manager_new_lot_form($world, true);
        $out .= "<form class=\"newlotform\" method=\"POST\">\n"
            . "<p><strong>Call dibs for an occupied lot:</strong></p>\n"
            . "<p>If you call dibs, you will be given that lot once it becomes available and if you have the resources (money/free lots) "
            . "for it at that moment. If there are other people who called dibs first, they will get it instead "
            . $dibs_form . " <input type=\"submit\" name=\"submit\" value=\"Call dibs!\"></p>\n</form>\n";
    }

    $out .= "</div>";
    return $out;
}

/**
 * Display the menu on top of the lot manager page
 *
 */
function umc_lot_manager_menu($worlds, $world) {
    global $UMC_USER;
    // top menu
    $out = '<ul class="lot_tabs">' . "\n";
    // get available worlds
    foreach ($worlds as $menu_world) {
        $user_lots = $UMC_USER['lots'][$menu_world];
        // create tabs
        $uworld = ucwords($menu_world);
        if ($menu_world == 'empire') {
            $uworld = 'Empire & Flatlands';
        }

        $taken_lots = $user_lots['used_lots'];
        $max_lots = $user_lots['max_lots'];
        $dibs = count($UMC_USER['lots'][$menu_world]['dib_list']);
        $count_str = "($taken_lots/$max_lots/$dibs)";

        if ($world == $menu_world) {
            $out .= "<li class=\"active_world\">$uworld $count_str</li>";
        } else {
            $out .= "<li><a href=\"?world=$menu_world\">$uworld $count_str</a></li>";
        }
    }
    $out .= "</ul><div style=\"clear:both\"></div>";
    return $out;
}

/**
 * Get all lots that the user has for a specific world
 *
 * @global type $UMC_USER
 * @global type $UMC_SETTING
 * @param type $world
 * @param type $edit_lot
 * @return string
 */
function umc_lot_manager_get_lots($world, $edit_lot) {
    global $UMC_USER;

    $world_lots = $UMC_USER['lots'][$world]['lot_list'];
    // list existing lots
    $out = "";

    foreach ($world_lots as $lot => $lot_data) {
        $form = false;
        $button = "<input class=\"submitbutton\" type=\"submit\" name=\"edit_lot\" value=\"Edit $lot\">";
        $class = '';
        if ($lot == $edit_lot) {
            $class = ' editform';
            $form = true;
            $button = "<input  class=\"submitbutton\" type=\"submit\" name=\"save_lot\" value=\"Save\">";
        }
        $members_form = umc_get_member_form($lot, $form);
        $flags_form = umc_get_flag_form($lot, $form);
        $lot_change_form = umc_get_lot_change_form($lot, $form);
        $image = umc_lot_get_tile($lot);

        $out .= "<a name=\"$lot\"></a><form style=\"overflow:auto;\" action=\"#$lot\" class=\"lotform$class\" method=\"POST\">
            <input type=\"hidden\" name=\"lot\" value=\"$lot\">
            <div>
                $image
                <div class=\"lot_features\">
                    <p><strong>Lot:</strong> $lot</p>
                    <p><strong>Members:</strong> $members_form</p>
                    <p><strong>Flags:</strong> $flags_form</p>
                    <p>$lot_change_form</p>
                </div>
                $button
            </div>
            </form>\n";
    }

    // get dibs
    // $out .= "<div>Dibs</div>\n";

    $dibs = $UMC_USER['lots'][$world]['dib_list'];
    foreach ($dibs as $lot => $lot_data) {
        $form = false;
        $button = "<input class=\"submitbutton\" type=\"submit\" name=\"delete_dib\" value=\"Cancel dibs on $lot\">";
        $class = '';
        $image = umc_lot_get_tile($lot);
        $action = $lot_data['action'];

        $out .= "<a name=\"$lot\"></a><form action=\"#$lot\" style=\"overflow:auto;\" class=\"lotform\" method=\"POST\">
            <input type=\"hidden\" name=\"lot\" value=\"$lot\">
            <div>
                $image
                <div class=\"lot_features\">
                    <p><strong>Dibs on Lot:</strong>$lot</p>
                    <p><strong>Action:</strong> $action</p>
                </div>
                $button
            </div>
            </form>\n";
    }
    return $out;
}

/**
 * returns the minimum userlevel and money that is needed to get a lot in a certain world.
 *
 * @param type $userlevel
 * @param type $world
 */
function umc_lot_manager_minimum_requirements($userlevel, $world) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    // Check for userlevel
    global $UMC_SETTING;
    $max_lots = $UMC_SETTING['lot_limits'][$userlevel][$world];
    $out = "As $userlevel level user, you can get a max of $max_lots in $world.";

    if ($world == 'kingdom') {
        $min_price = $UMC_SETTING['lot_costs']['^draft_[a-zA-Z]+\d*$'];
    } else if ($world == 'skyblock') {
        $min_price = $UMC_SETTING['lot_costs']['^block_[a-zA-Z]+\d*$'];
    } else  {
        return $out;
    }
    $out . " Further, in $world, you need $min_price Uncs to get your first lot.";
    return $out;
}


/*
 * retrieves a region from it's coordinates and the world
 */
function umc_lot_get_from_coords($x, $z, $world) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $floor_x = floor($x);
    $floot_z = floor($z);
    $sql = "SELECT region_id from minecraft_worldguard.region_cuboid
            LEFT JOIN minecraft_worldguard.world ON world_id=id
            WHERE min_x<=$floor_x
		AND min_z<=$floot_z
		AND max_x>=$floor_x
		AND max_z>=$floot_z
		AND name='$world'
	    LIMIT 1;";
    $D = umc_mysql_fetch_all($sql);
    if (count($D) == 0) {
        return false;
    } else {
        $region = $D[0]['region_id'];
    }
    return $region;
}

/*
 * this processes forms from the lot manager before displaying it.
 */
function umc_lot_manager_process() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $out = '';
    $sani_post  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
    // var_dump($sani_post);
    $lot = $sani_post['lot'];
    $check_owner = umc_check_lot_owner($lot, $UMC_USER['username']);
    if (!$check_owner) {
        XMPP_ERROR_trigger($UMC_USER['username'] . " tried to edit lot $lot, but is not owner! (umc_lot_manager_process)");
        die('umc_lot_manager_process');
    }

    // process flags
    if (isset($sani_post['flag'])) {
        $flag_arr = $sani_post['flag'];
        foreach($flag_arr as $flag => $value) {
            $check = umc_lot_set_flag($lot, $flag, $value);
        }
    }
    // process new members
    $new_member = $sani_post['new_member'];

    $check = false;
    if ($new_member != 'none') {
        $check = umc_lot_add_player($new_member, $lot, 0);
    }
    if ($check) {
        $out .= "$new_member added to $lot!";
    }
    // process removed members
    if (isset($sani_post['remove_member'])) {
        $remove_arr = $sani_post['remove_member'];
        foreach ($remove_arr as $member => $data) {
            $check = umc_lot_rem_player($member, $lot, 0);
        }
    }
    // process lot changes
    if (isset($sani_post['lot_action'])) {
        $lot_action = $sani_post['lot_action'];
        $out .= umc_lot_do_action($lot, $lot_action);
    }
    return $out;
}

/**
 * Deletes a dib for a lot of a specific user.
 * If lot=false, all dibs of that user will be removed (when a user becomes inactive)
 *
 * @global type $UMC_USER
 * @param type $uuid
 * @param type $lot
 * @return string
 */
function umc_lot_manager_dib_delete($uuid, $lot = false) {
    global $UMC_USER;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $lot_sql = '';
    if ($lot) {
        $lot_sql = "AND lot=" . umc_mysql_real_escape_string($lot);
        $world = umc_get_lot_world($lot);
        if ($UMC_USER && isset($UMC_USER['lots'][$world]['dib_list'][$lot])) {
            unset($UMC_USER['lots'][$world]['dib_list'][$lot]);
        }
        $log_msg = "for lot $lot in world $world";
    } else { // we remove all dibs, so we
        $log_msg = "for all lots";
    }

    $sql = "DELETE FROM minecraft_srvr.lot_reservation WHERE uuid='$uuid' $lot_sql;";
    $count = umc_mysql_execute_query($sql);

    XMPP_ERROR_send_msg("User $uuid removed $count dibs $log_msg");
    umc_log("Lot", "wipe_user_dibs", "$count dibs removed from $uuid");
}

/**
 * Adds a dib for a lot of a specific user.
 * If lot=false, all dibs of that user will be removed (when a user becomes inactive)
 *
 * @param type $uuid
 * @param type $lot
 * @param type $action
 * @return string
 */
function umc_lot_manager_dib_add($uuid, $lot, $action) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    // check first if dib exists already
    $world = umc_get_lot_world($lot);
    $dibs = umc_lot_manager_dib_get_number($uuid, $world);
    if (isset($dibs[$lot])) {
        return false;
    }
    $sql = "INSERT INTO minecraft_srvr.lot_reservation(`uuid`, `lot`, `world`, `action`) VALUES ('$uuid','$lot','$world','$action')";
    umc_mysql_query($sql);
    // refresh the variable
    $UMC_USER['lots'][$world]['dib_list'] = umc_lot_manager_dib_get_number($uuid, $world);
    $out = "Successfully added dib for lot $lot;";
    XMPP_ERROR_send_msg("User $uuid got dibs for lot $lot in world $world");
    return $out;
}

/**
 * iterates all dibs and kicks out invalid ones (user is not active or owns the lot already
 */
function umc_lot_manager_dib_cleanup() {
    $sql = "SELECT * FROM minecraft_srvr.lot_reservation;";
    $R = umc_mysql_fetch_all($sql);
    foreach ($R as $r) {
        $uuid = $r['uuid'];
        $lot = $r['lot'];
        $countlots = umc_user_countlots($uuid);
        if ($countlots < 1) {
            XMPP_ERROR_send_msg("User $uuid is not active anymore, remove dibs for $lot!");
            umc_lot_manager_dib_delete($uuid, $lot);
        } else {

            $user_lots = umc_user_getlots($uuid);
            if (isset($user_lots[$lot])) {
                XMPP_ERROR_send_msg("User $uuid is owner of $lot already, remove dibs!");
                umc_lot_manager_dib_delete($uuid, $lot);
            }
        }
    }
}


/*
 * displays a form where you can change the lot
 */
function umc_get_lot_change_form($lot, $form = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    // get possible options
    $options = umc_get_lot_options($lot, $form);
    // get current option
    $sql = "SELECT choice FROM minecraft_srvr.lot_version WHERE lot='$lot'";
    $D = umc_mysql_fetch_all($sql);
    $choice = $D[0]['choice'];

    If ($choice != null) {
        $out = "<strong>Lot action on next server restart:</strong> ";
    } else {
        $out = "<strong>Available Lot actions:</strong> ";
    }

    $selected = array();
    $selected[$choice] = " selected=\"selected\"";

    if (count($options) < 1) {
        return $out . "No actions available";
    } else {
        if ($form) {
            $out .= "<select name=\"lot_action\">";
            foreach ($options as $option => $text) {
                $sel_str = '';
                if (isset($selected[$option])) {
                    $sel_str = $selected[$option];
                }
                $out .= "<option value=\"$option\"$sel_str>$text</option>\n";
            }
            $out .= "</select> (Resets happen @ next reboot in " . umc_time_until_restart() .")";
        } else {
            if ($choice == null) {
                $out .= "<ul>";
                foreach ($options as $option => $text) {
                    $out .= "<li>$text</li>";
                }
                $out .= "</ul>";
            } else {
                $out .= "$choice (@ next reboot in " . umc_time_until_restart() .")";
            }
        }
        return $out;
    }
}

function umc_lot_do_action($lot, $choice) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $username = $UMC_USER['username'];
    $world = umc_get_lot_world($lot);
    $options = umc_get_lot_options($lot, true); // get ALL choices
    $members = umc_get_active_members();
    if (!isset($options[$choice]) && !in_array($choice, $members)) {
        XMPP_ERROR_trigger("attemped to execute $choice on $lot, but action is not available!");
        die('umc_lot_do_action');
    }
    $message = '';
    switch($choice) {
        case 'abandon':
            umc_lot_remove_all($lot);
            umc_lot_add_player('_abandoned_', $lot, 1);
            $message = "Your access to lot $lot has been removed. It will be reset on the next server restart";
            umc_log('lot_manager', 'abandon', "$username abandoned $lot in $world. Reset on restart pending.");
            break;
        case 'reset':
            $sql = "UPDATE minecraft_srvr.lot_version SET `choice`='reset' WHERE lot='$lot';";
            umc_mysql_query($sql, true);
            $message = 'Your lot $lot will be reset on the next server restart. Please get all your goods from it ASAP.';
            umc_log('lot_manager', 'RESET', "$username asked for reset of $lot in $world. Reset on restart pending.");
            break;
        case 'refund':
            $costs = umc_lot_get_costs($lot);
            $refund = $costs / 2;
            umc_money(false, $username, $refund);
            $message = "Your access to lot $lot has been removed. "
                . "It will be reset on the next server restart. "
                . "$refund Uncs have been transferred to your account.";
            umc_lot_remove_all($lot);
            umc_lot_add_player('_abandoned_', $lot, 1);
            umc_log('lot_manager', 'REFUND', "$username asked for refund of $lot in $world. $refund uncs paid. Reset on restart pending.");
            break;
        case 'none':
            $sql = "UPDATE minecraft_srvr.lot_version SET `choice`=NULL WHERE lot='$lot';";
            umc_mysql_query($sql, true);
            break;
        default:
            if ($world == 'kingdom') { // gifting choices for kingdom
                if (isset($choice, $members)) {
                    XMPP_ERROR_send_msg("$username tries to give $lot to $choice");
                    // This is pre-checked already but let's make sure this does not conflict and fail with other upcomingoptions
                    umc_lot_remove_all($lot);
                    umc_lot_add_player($choice, $lot, 1);
                    umc_log('lot_manager', 'TRANSFER', "$username gave lot $lot in $world to $choice");
                }
            } else if (($world == 'flatlands') || ($world == 'skyblock') || ($world == 'draftlands')) {
                $sql = "UPDATE minecraft_srvr.lot_version SET `choice`='$choice' WHERE lot='$lot';";
                umc_mysql_query($sql, true);
                umc_log('lot_manager', 'RESET', "$username had lot $lot reset to choice $choice");
            } else {
                XMPP_ERROR_trigger("$username had invalid choice $choice for $lot in $world!");
            }
    }
    return $message;
}

/*
 * this finds out what one can do with the lot
 */
function umc_get_lot_options($lot, $form = false){
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_SETTING;
    $sql = "SELECT version, mint_version FROM minecraft_srvr.lot_version WHERE lot='$lot';";
    $D = umc_mysql_fetch_all($sql);
    $row = $D[0];
    $world = umc_get_lot_world($lot);
    if ($form) {
        $lot_options = array('none' => 'Leave as-is');
    } else {
        $lot_options = array();
    }
    if ($world == 'kingdom') {
        $lot_options['abandon'] = 'Abandon now (no refund, lot will be reset)!';
        // $lot_options['refund'] = 'abandon & reset (refunds 50%)';
        /*
        if ($form) {
            $members = umc_get_active_members();
            foreach ($members as $uuid => $member) {
                $lot_options[$uuid] = "Gift to $member";
            }
        } else {
            $lot_options['gift'] = "Gift to someone (as-is)";
        }
        */
    } else if ($world == 'draftlands') {
        // allow gift & refund etc
        $lot_options['refund'] = 'abandon & reset (refunds 50%)';
        $lot_options['reset'] = 'reset to flatlands';
        $lot_options['mint_king'] = 'reset to mint kingdom';
        $lot_options['curr_king'] = 'reset to current kingdom';
        return $lot_options;
    } else if ($world == 'flatlands' || $world == 'skyblock') {
        $lot_choices = $UMC_SETTING['mint_lots'][$world];
        foreach($lot_choices as $lot_choice => $desc) {
            $lot_options[$lot_choice] = $desc;
        }
    }

    if ($row['mint_version'] == 0) {
        $lot_options['abandon'] = 'Abandon now!';
    } else if ($row['mint_version'] != $row['version']) {
        $lot_options['reset'] = "Reset to version " . $row['mint_version'];
        $lot_options['abandon'] = 'Abandon now!';
    }

    return $lot_options;
}

/*
 * This is converting a draftlands lot to the appropriate kingdom lot
 * and vice-versa
 */
function umc_get_draftlands_kingdom_equivalent($draftlands_lot) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $target = 'king_';
    if (substr($draftlands_lot, 0, 4) == 'king') {
        $target = 'draft_';
    }
    $lot_arr = explode("_", $draftlands_lot);
    array_shift($lot_arr);
    $kingdom_arr = $target . implode("_", $lot_arr);
    return $kingdom_arr;
}

// displays a form to chose a new lot in a specific world.
// merges empire & flatlands worlds
function umc_lot_manager_new_lot_form($world, $dibs = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;

    $out = '';
    $docs = "<ul>\n";

    if (!$dibs) {
        $avail_lots = umc_get_available_lots($world);
        // we show flatlands & empire together
        if ($world == 'empire') {
            $avail_lots = array_merge($avail_lots, umc_get_available_lots('flatlands'));
        }
        $out .= "<select name=\"new_lot\">";
        $multiplier = 1;
        $actions = "";
    } else {
        $avail_lots = umc_lot_manager_get_occupied_lots($world);
        // we show flatlands & empire together
        if ($world == 'empire') {
            $avail_lots = array_merge($avail_lots, umc_lot_manager_get_occupied_lots('flatlands'));
        }
        $out .= "<select name=\"new_dib\">";
        $actions = "<select name=\"dib_action\"><option value=\"none\">[none]</option><option value=\"reset\">Reset</option></select>\n";
        $multiplier = 2;
    }

    $out .= "<option value=\"false\">[none]</option>\n";

    $mainlot = false;
    If ($world == 'kingdom' || $world == 'draftlands') {
        // check if the user has a main lot already, if not, hide street lots.
        $mainlot = umc_check_if_world_mainlot($world);
    }

    if ($world == 'kingdom' && $dibs == false) {
        $docs .= "<li><strong>Snow:</strong> It might not snow anymore on a lot that has snow on it now! Please see the <a href=\"https://uncovery.me/about-this-server/faq/\">FAQ</a> for more info.</li>";
    }

    $uuid = $UMC_USER['uuid'];
    $account = umc_money_check($uuid);

    // get allowed draftland lots
    if ($world == 'draftlands') {
        $draft_lots = array();
        $world_lots = $UMC_USER['lots']['kingdom']['lot_list'];
        foreach ($world_lots as $lot) {
            $draft_lots[] = str_replace('king', 'draft', $lot);
        }
    }

    $count = 0;

    $fixed_lots = array();
    foreach ($avail_lots as $lot => $distance) {
        // we match the king_a1_b with "world" = king, "alpha" = a, "number" = 1, "extras" = _b
        $regex = '/(?P<world>^[a-z]*_)(?P<alpha>[a-z]*)(?P<number>[0-9]*)(?P<extras>.*)/';
        $matches = false;
        preg_match($regex, $lot, $matches);
        $new_lot_name = $matches['world'] . str_pad($matches['alpha'], 3, 0, STR_PAD_LEFT) . str_pad($matches['number'], 3, 0, STR_PAD_LEFT) . $matches['extras'];
        $fixed_lots[$new_lot_name] = $lot;

    }
    ksort($fixed_lots);

    // lots might be hidden for various reasons. Show those with this array.
    $unavailable = array(
        'dibs' => array('count' => 0, 'reason' => 'lots unavailable since you already have dibs on them.'),
        'draftlands' => array('count' => 0, 'reason' => 'draftlands lots unavailable since you don\'t own the corresponding Kingdom lots.'),
        'price_issues' => array('count' => 0, 'reason' => 'lots unavailable since you cannot afford them.'),
        'street_lots' => array('count' => 0, 'reason' => 'street lots unavailable since you don\'t own a main lot.'),
    );
    
    foreach ($fixed_lots as $lot) {
        // drop already diped losts
        if ($dibs && isset($UMC_USER['lots'][$world]['dib_list'][$lot])) {
            $unavailable['dibs']['count']++;
            continue;
        }

        // only draftland lots where the user has the equivalent kingdom lot
        if ($world == 'draftlands' && !in_array($lot, $draft_lots)) {
            $unavailable['draftlands']['count']++;
            continue;
        }
        // check costs
        $price = umc_lot_get_costs($lot);
        if ($price) {
            if ($price > $account) {
                $unavailable['price_issues']['count']++;
                continue;
            }
            $price = number_format($price * $multiplier, 0, ',', '\'');
        }

        // kingdom street lots check
        if (($world == 'kingdom' || $world == 'draftlands') && !$mainlot) {
            $lot_segments = explode("_", $lot);
            if (count($lot_segments) > 2) {
                $unavailable['street_lots']['count']++;
                continue;
            }
        }
        $count++;
        $out .= "<option value=\"$lot\">$lot @ $price</option>\n";
    }
    $out .= "</select><br>";
    
    if ($actions != '') {
        $out .= "Action on Tranfer: " . $actions;
    }
    
    if ($count == 0) {
        $out = umc_lot_manager_minimum_requirements($UMC_USER['userlevel'], $world);
        return false;
    }
    // iterate reasons of unavailability and show if lots were hidden
    foreach ($unavailable as $title => $U) {
        if ($U['count'] > 0) {
            $title_nice = umc_pretty_name($title);
            $docs .= "<li><strong>$title_nice:</strong> {$U['count']} {$U['reason']}</li>";
        }
    }
    $docs .= "</ul>";
    return $out . $docs;
}


/*
 * Kingdom / draftlands street lots are only available if the user has a main lot. This
 * process checks if that is true.
 */
function umc_check_if_world_mainlot($world) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER, $UMC_SETTING;
    // iterate kingdom / draftlands lots to find main lots
    $world_lots = $UMC_USER['lots'][$world]['lot_list'];
    $prefix = $UMC_SETTING['world_data'][$world]['prefix'];
    foreach ($world_lots as $world_lot => $world_lot_data) {
        $check = preg_match("/^{$prefix}_[a-zA-Z]+\d*$/", $world_lot);
        if ($check) {
            return true;
        }
    }
    return false;
}

/*
 * This shows a form to add new members to the lot
 * Lists only active members
 */
function umc_get_member_form($lot, $form = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $username = $UMC_USER['username'];
    $members = umc_get_active_members();
    $lot_members = umc_get_lot_members($lot);
    $out = '';
    if ($lot_members) {
        if ($form) {
            $out .= '<br>Check members you want to remove: ';
        }
        // make a form where existing members can be removed
        foreach ($lot_members as $UUID => $member) {
            // remoe all inactive members from the lot
            if (!isset($members[$UUID])) {
                XMPP_ERROR_trace("Removing inactive player $UUID from lot, list of active members:", $members);
                umc_lot_rem_player($UUID, $lot, 0);
            }
            if ($form) {
                $out .= "$member <input type=\"checkbox\" name=\"remove_member[$UUID]\">;";
            } else {
                $out .= "$member; ";
            }
        }
    } else {
        $out .= "No members";
    }
    if (!$form) {
        return $out;
    }
    $out .= "<br>Add member: <select name=\"new_member\">"
        . "<option value=\"none\">- none -</option>\n";
    foreach ($members as $member) {
        // do not add the lot owner
        if ($member == $username) {
            continue;
        }
        // do not add existing members again
        if (!$lot_members || !in_array($member, $lot_members)) {
            $out .= "<option value=\"$member\">$member</option>\n";
        }
    }
    $out .= "</select>";
    return $out;
}

/*
 * adds a form that lets you chose flags for a lot
 */
function umc_get_flag_form($lot, $form = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER, $UMC_SETTING;
    $out = '';
    if ($UMC_USER['donator'] == 'Donator' || $UMC_USER['username'] == 'uncovery') {
        $flags = umc_get_lot_flags($lot);
        $avail_flags = $UMC_SETTING['lot_flags'];
        foreach ($avail_flags as $flag) {
            if ($form) {
                $selected = array();
                if (isset($flags[$flag])) {
                    $value = $flags[$flag];
                    $selected[$value] = " selected=\"selected\"";
                }
                $sel_allow = '';
                $sel_deny = '';
                if (isset($selected['allow'])) {
                    $sel_allow = $selected['allow'];
                }
                if (isset($selected['deny'])) {
                    $sel_deny = $selected['deny'];
                }
                $out .= " $flag: <select name=\"flag[$flag]\">"
                    . "<option value=\"false\">n/a</option>\n"
                    . "<option value=\"allow\"$sel_allow>allow</option>\n"
                    . "<option value=\"deny\"$sel_deny>deny</option>\n"
                    . "</select>";
            } else if (isset($flags[$flag])) {
                $value = $flags[$flag];
                $out .= "$flag: $value; ";
            }
        }
        if (!$form && !$flags) {
            $out .= "No flags set";
        }
    } else if ($form) {
        $out .= "Flag changes are only available to Donators;";
    } else {
        $out .= " No Flags;";
    }
    return $out;
}


function umc_lot_set_flag($lot, $flag, $value) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_SETTING;
    $flag_values = array('allow', 'deny', 'false');
    $world = umc_get_lot_world($lot);
    $world_id = umc_get_worldguard_id('world', $world);
    if (!$world) {
        XMPP_ERROR_trigger("World could not be found for lot_name $lot, $flag, $value (umc_lot_set_flag)");
        die('umc_lot_set_flag');
    }
    if (!in_array($flag, $UMC_SETTING['lot_flags'])) {
        XMPP_ERROR_trigger("attempt to set invalid flag $flag (umc_lot_set_flag)");
        die('umc_lot_set_flag');
    }
    if (!in_array($value, $flag_values)) {
        XMPP_ERROR_trigger("attempt to set invalid flag value $value (umc_lot_set_flag)");
        die('umc_lot_set_flag');
    }
    // first, find out if the flag is set
    $sql = "SELECT value FROM minecraft_worldguard.region_flag WHERE world_id=$world_id AND region_id='$lot' AND flag='$flag';";
    $D = umc_mysql_fetch_all($sql);
    if (count($D) > 0) {
        $old_value = $D[0]['value'];
    } else {
        $old_value = false;
    }
    if ($old_value && $value == 'false') { //remove flag
        $sql = "DELETE FROM minecraft_worldguard.region_flag WHERE world_id=$world_id AND region_id='$lot' AND flag='$flag';";
        umc_log('lot_manager', 'remove flag', "$flag was removed from lot $lot");
    } else if ($old_value && ($old_value != $value)){ // update with new value
        $sql = "UPDATE minecraft_worldguard.region_flag SET value='$value' WHERE world_id=$world_id AND region_id='$lot' AND flag='$flag';";
        umc_log('lot_manager', 'remove player', "$flag was changed for lot $lot to $value");
    } else if (!$old_value && $value != 'false') { // insert new flag, but only if needed
        $sql = "INSERT INTO minecraft_worldguard.region_flag (`region_id`, `world_id`, `flag`, `value`) VALUES ('$lot',$world_id,'$flag','$value')";
        umc_log('lot_manager', 'remove player', "New $flag was set for lot $lot to $value");
    } else { // nothing to do
        return;
    }
    umc_mysql_query($sql, true);
}

/**
 * Check if a user can have a lot or not, also check for money requirements
 *
 * @param type $user
 * @param type $new_lot
 * @return boolean|string\
 */
function umc_lot_manager_check_before_assign($user, $new_lot) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    // get username
    $U = umc_uuid_getboth($user);
    $uuid = $U['uuid'];
    $username = $U['username'];

    // validate input and check if lot is free
    // find world & sanitize
    $world = umc_get_lot_world($new_lot);
    $userlevel = umc_userlevel_get($uuid);
    if (!$world) {
        XMPP_ERROR_send_msg("Guest $username tried to get lot $new_lot, but world could not be found! (umc_assign_new_lot)");
        $result = array('result' => false, 'text' => "The lot you chose is invalid!", 'cost' => false);
        return $result;
    }
    $cost = umc_lot_get_costs($new_lot);


    // guests need to get either an empire or flatlands lot
    $guest_worlds = array('empire', 'flatlands');
    if (($userlevel == 'Guest') && !in_array($world, $guest_worlds)) {
        XMPP_ERROR_send_msg("Guest $username tried to get lot $new_lot in $world (umc_assign_new_lot)");
        $result = array('result' => false, 'text' => "You can only get lots in Empire & Flatlands as Guest!", 'cost' => $cost);
        return $result;
    }

    $userlots = umc_get_lot_number($uuid, $world);

    // check costs

    if ($cost) { // see if the user has enough money
        $balance = umc_money_check($uuid);
        if ($balance < $cost) {
            XMPP_ERROR_send_msg("User $username did not have enough money to get $new_lot (umc_assign_new_lot)");
            $result = array('result' => false, 'text' => "You do not have enough money to get this lot!", 'cost' => $cost);
            return $result;
        }
    }

    //check if user has approriate kingdom lot
   if ($world == 'draftlands') {
        $draft_lots = array();
        $king_lots = umc_get_lot_number($uuid, 'kingdom');
        $king_lots_list = $king_lots['lot_list'];
        foreach ($king_lots_list as $king_lot) {
            $draft_lots[] = str_replace('king', 'draft', $king_lot);
        }
        if (!in_array($new_lot, $draft_lots)) {
            $result = array('result' => false, 'text' => "You need to own the same kingdom lot!", 'cost' => $cost);
            return $result;
        }
    }

    // check if the lot is owned already by someone
    $occupied_check = umc_check_lot_owner($new_lot, false);
    if ($occupied_check) {
        XMPP_ERROR_trigger("User $username tried to get $new_lot but someone else owns it! (umc_assign_new_lot)");
        $result = array('result' => false, 'text' => "This lot is owned by someone else already!", 'cost' => $cost);
        return $result;
    }

    // check if user has lots free
    if (isset($userlots['lot_list'][$new_lot])) {
        XMPP_ERROR_send_msg("User $username tried to get $new_lot but is an owner already! (umc_assign_new_lot)");
        $result = array('result' => false, 'text' => "You own this lot already!", 'cost' => $cost);
        return $result;
    } else if ($userlots['avail_lots'] > 0) {
        // user can get the lot!
        umc_log("lot_manager", "assign_new_lot", "$username was added as owner to lot $new_lot");
        $result = array('result' => true, 'text' => "You are now proud owner of lot $new_lot in $world!", 'cost' => $cost);
        XMPP_ERROR_send_msg("User $username tried to get $new_lot and is approved! (umc_assign_new_lot)");
        return $result;
    } else {
        XMPP_ERROR_send_msg("User $username did not have avialable lots free to get $new_lot (umc_assign_new_lot) " . var_export($userlots, true));
        $result = array('result' => false, 'text' => "You do not have enough available lots in this world!", 'cost' => $cost);
        return $result;
    }
}

/**
 * checks for any lot if it costs something or returns false if lot is free.
 *
 * @global type $UMC_SETTING
 * @param type $lot
 * @return \type
 */
function umc_lot_get_costs($lot) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_SETTING, $UMC_USER;
    // check costs
    $costs = $UMC_SETTING['lot_costs'];

    $cost = false;
    $count_main = false;
    foreach ($costs as $pattern => $price) {
        $check = preg_match("/$pattern/", $lot);
        if ($check) {
            if (is_array($price)) { // calculated pricing instead of fixed
                $world = umc_get_lot_world($lot);
                // get the number of main lots in this world, but only once
                if (!$count_main) {
                    $count = umc_get_lot_number($UMC_USER['uuid'], $world, true);
                    $count_main = $count['used_lots'];
                }
                $cost = umc_lot_costs_exponential($count_main, $price['base'], $price['power']);
            } else {
                $cost = $price;
            }
        }
    }
    return $cost;
}

/**
 * Centralized function to implement the exponential formula
 * We need to add +1 to the lot count to make sure that the first lot is not free.
 * 
 * @param type $count
 * @param type $base
 * @param type $power
 * @return type
 */
function umc_lot_costs_exponential($count, $base, $power) {
    $cost = pow($count + 1, $power) * $base;
    return $cost;
}

function umc_lot_costs_simulation($world) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_SETTING, $UMC_USER;
    $costs = $UMC_SETTING['lot_costs'];

    // we take the spawn lot as a sample lot of that world to get sample costs
    $lot = $UMC_SETTING['world_data'][$world]['prefix'] . "_a1";

    $simulation_row = array(1,2,3,4,5,6,7,8,9,10,15,20,50,100);

    $out = '';

    $progressive = false;
    foreach ($costs as $pattern => $price) {
        $check = preg_match("/$pattern/", $lot);
        if ($check) {
            if (is_array($price)) { // calculated pricing instead of fixed
                $progressive = true;
                $out = "<h2>Here is a simulation of the cost progression for the $world world</h2>";
                $out .= "<ul>";
                foreach ($simulation_row as $count) {
                    $cost = umc_lot_costs_exponential($count, $price['base'], $price['power']);
                    $cost_nice = number_format($cost, 0, ',', '\'');
                    $out .= "<li>$count lot(s): $cost_nice UNCs</li>";
                }
                $out .= "</ul>";
                $count = umc_get_lot_number($UMC_USER['uuid'], $world, true);
                $cost = umc_lot_costs_exponential($count['used_lots'], $price['base'], $price['power']);
                $cost_nice = number_format($cost, 0, ',', '\'');
                $out .= "You currently have {$count['used_lots']} lots in $world. Your next lot will cost $cost_nice";
            } else {
                $out = "This type of lot has a fixed price: $price";
            }
        }
    }

    return $out;
}

function umc_lot_get_tile($lot, $world = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_DOMAIN;
    if (!$world) { // world is not given, find world name from the string until the first _
        global $UMC_SETTING;
        $lot_split = explode("_", $lot);
        $prefix = $lot_split[0];
        foreach ($UMC_SETTING['world_data'] as $one_world => $data) {
            if ($prefix == $data['prefix']) {
                $world = $one_world;
                break; // found it, bail
            }
        }
    }
    // kingdom and draftlands shows street lots with the main lot. We need to add another class for each type of street lot
    $street_worlds = array('kingdom', 'draftlands');
    $class = $world . '_lot';
    $lot_filename = $lot;
    $matches = false;
    if (in_array($world, $street_worlds)) {
        // do we have a street lot?
        $pattern = "/(.*)_([abc]{1}$)/";
        $check = preg_match($pattern, $lot, $matches);
        $match_sublot = '_none';
        $main_lot = $lot;
        if ($check) { // yes, apply the appropriate CSS
            $match_sublot = "_" . $matches[2];
            $main_lot = $matches[1];
        }
        $class = "street_lot" . $match_sublot;
        $lot_filename = $main_lot . "_full";
    }

    $out = "<div class=\"imagebox\">\n"
        . "    <div class=\"$class\" style=\"background-image: url('$UMC_DOMAIN/map/lots/$world/$lot_filename.png');\"></div>\n"
        . "    $lot\n"
        . "</div>\n";
    return $out;
}

/**
 * Adds or removes a player to a region.  Works for Owners (default) as well as members.
 * must ensure beforehand that the user and the lot and the world esists
 *
 * @param type $player
 * @param type $lot
 * @param type $owner
 * @param type $cost
 * @return boolean
 */
function umc_lot_add_player($player, $lot, $owner = 1, $cost = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    $world = umc_get_lot_world($lot);
    $world_id = umc_get_worldguard_id('world', $world);
    if (!$world) {
        XMPP_ERROR_trigger("World could not be found for lot $lot ($player) (umc_lot_add_player)");
        die('umc_lot_add_player');
    }
    $user_id = umc_get_worldguard_id('user', $player, true);

    // check first if the same user is already member/owner
    $sql = "SELECT * FROM minecraft_worldguard.region_players WHERE region_id='$lot' AND world_id='$world_id' AND user_id=$user_id;";
    $C = umc_mysql_fetch_all($sql);
    if (count($C) > 0) {
        XMPP_ERROR_trigger("attempt to add user $player to lot $lot failed; user is already member/owner (umc_lot_add_player)");
        return false;
    }
    $sql = "INSERT INTO minecraft_worldguard.region_players (region_id, world_id, user_id, Owner) " .
            "VALUES ('$lot', $world_id, $user_id, $owner)";
    umc_mysql_query($sql, true);
    XMPP_ERROR_send_msg("$player was added to lot $lot; Owner: $owner");
    umc_log('lot_manager', 'add_player_to_lot', "$player was added to lot $lot; Owner: $owner");
    if ($owner == 1) {
        //umc_exec_command("ch qm u Congratz for user $player to get lot $lot!");
    }
    // reload regions file
    umc_exec_command("regions load -w $world", 'asConsole');
    // update user lot count
    umc_uuid_record_lotcount($player);
    if ($cost) {
        umc_money($player, false, $cost);
    }
    return true;
}

/**
 * Remove all members/Owners and all flags from a region
 * part of the standard lot reset process
 *
 * @param type $lot
 * @return boolean
 */
function umc_lot_remove_all($lot) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $world = umc_get_lot_world($lot);
    $world_id = umc_get_worldguard_id('world', $world);
    if ($world_id === null || !umc_check_lot_exists($world_id, $lot)) {
        // echo "World $world or lot $lot could not be found!;";
        return false;
    }
    //  Remove all players
    $sql1 = "DELETE FROM minecraft_worldguard.region_players WHERE region_id = '$lot' AND world_id = $world_id";
    umc_mysql_execute_query($sql1);

    // now set default flags
    umc_lot_flags_set_defaults($lot);

    umc_log('lot_manager', 'remove all', "All users and flags have been reset for  lot $lot");
    return true;
}

/**
 * reset all lot flags to the defaults
 *
 * @param type $lot
 */
function umc_lot_flags_set_defaults($lot) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $lot_sql = umc_mysql_real_escape_string($lot);
    $world = umc_get_lot_world($lot, true);
    $world_id = umc_get_worldguard_id('world', $world, false);

    $sql2 = "DELETE FROM minecraft_worldguard.region_flag WHERE region_id = '$lot' AND world_id = $world_id";
    umc_mysql_execute_query($sql2);

    $sql = "INSERT INTO minecraft_worldguard.`region_flag` (`region_id`, `world_id`, `flag`, `value`) VALUES
        ($lot_sql, $world_id, 'use', 'allow\n'),
        ($lot_sql, $world_id, 'chest-access', 'deny\n'),
        ($lot_sql, $world_id, 'chest-access-group', 'NON_MEMBERS\n');";
    umc_mysql_execute_query($sql);
    umc_ws_cmd('regions load -w ' . $world);
}

/*
 * remove a player from a lot
 *
 */
function umc_lot_rem_player($player, $lot, $owner) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    // get user_id
    $user_id = umc_get_worldguard_id('user', $player, false);
    if (!$user_id) {
        XMPP_ERROR_trigger("Tried to remove $player from $lot but user_id not found (umc_lot_rem_player)");
        die('umc_lot_rem_player');
    }
    $world = umc_get_lot_world($lot);
    $world_id = umc_get_worldguard_id('world', $world);
    if (!$world) {
        XMPP_ERROR_trigger("World $world could not be found for lot_name $lot to remove member $player (umc_lot_rem_player)");
        die('umc_lot_rem_player');
    }
    $sql = "DELETE FROM minecraft_worldguard.region_players
        WHERE region_id='$lot' AND world_id='$world_id' AND user_id=$user_id AND owner=$owner;";
    $count = umc_mysql_execute_query($sql);
    if ($count > 0) {
        umc_log('lot_manager', 'remove player', "$player was removed from lot $lot; Owner: $owner");
        return true;
    } else {
        XMPP_ERROR_trigger("Could not remove $player from $lot in $world (id $world_id), entry not found (umc_lot_rem_player)");
        return false;
    }
}

// this lists all empty lots and lots people plan to move out from and checks it with lots that are reserved
// and returns valid lots one cam move to.
function umc_get_available_lots($world = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_SETTING;
    $world_str = '';

    if ($world) {
        $world_str = " AND name='$world'";
    }
    // get all empty lots, filter out special lots too
    $empty_sql = "SELECT region_cuboid.region_id AS lot, world.name as world, sqrt(pow(max_x,2)+pow(max_z,2)) as distance
            FROM minecraft_worldguard.world
            LEFT JOIN minecraft_worldguard.region_cuboid ON world.id=region_cuboid.world_id
            LEFT JOIN minecraft_worldguard.region_players ON region_cuboid.region_id=region_players.region_id
            WHERE user_id IS NULL AND SUBSTR(region_cuboid.region_id, 1, 4) IN {$UMC_SETTING['lot_worlds_sql']}$world_str;";
    // echo $empty_sql;
    $D = umc_mysql_fetch_all($empty_sql);
    $empty_lots = array();
    if (count($D) > 0) {
        foreach ($D as $empty_row) {
            $distance = $empty_row['distance'];
            $lot = $empty_row['lot'];
            $empty_lots[$lot] = $distance;
        }
    }

    return $empty_lots;
}

function umc_lot_manager_get_occupied_lots($world=false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_SETTING;

    $world_str = '';
    if ($world) {
        $world_str = " AND name='$world'";
    }
    // get all empty lots, filter out special lots too
    $sql = "SELECT region_players.region_id AS lot, world.name AS world
        FROM minecraft_worldguard.region_players
        LEFT JOIN minecraft_worldguard.world ON world.id=region_players.world_id
        WHERE owner=1 AND SUBSTR(region_players.region_id, 1, 4) IN {$UMC_SETTING['lot_worlds_sql']}$world_str
	ORDER BY region_id;";
    $D = umc_mysql_fetch_all($sql);
    $lots = array();
    foreach ($D as $d) {
        $lots[$d['lot']] = $d['lot'];
    }
    return $lots;
}

function umc_get_lot_world($lot, $critical = true) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_SETTING;
    $lot_slices = explode("_", strtolower($lot));
    $lot_prefix = $lot_slices[0];
    foreach ($UMC_SETTING['world_data'] as $world => $data) {
        if ($data['prefix'] == $lot_prefix) {
            return $world;
        }
    }
    if ($critical) {
        XMPP_ERROR_trigger("Iterated all worlds but could not find match to validate $lot with prefix $lot_prefix (umc_get_lot_world)");
    }
    return false;
}

/**
 * Gets an array of only members of a lot
 * If $owner = true, gets only the owners
 *
 * @param type $lot
 * @param type $owner
 * @return type
 */
function umc_get_lot_members($lot, $owner = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $owner_val = 0;
    if ($owner) {
        $owner_val = 1;
    }
    $sql = "SELECT user.UUID AS UUID, username FROM minecraft_worldguard.region_players
        LEFT JOIN minecraft_worldguard.user ON user_id=id
        LEFT JOIN minecraft_srvr.UUID ON UUID.UUID=user.UUID
        WHERE region_id='$lot' AND owner=$owner_val;";
    $D = umc_mysql_fetch_all($sql);
    $members = false;
    if (count($D) > 0) {
        $members = array();
        foreach ($D as $row) {
            $members[$row['UUID']] = $row['username'];
        }
    }
    return $members;
}

/**
 * Gets an array of only members of a lot
 * If $owner = true, gets only the owners
 *
 * @param type $lot
 * @param type $owner
 * @return type
 */
function umc_lot_get_group_members($lot, $owner = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $owner_val = 0;
    if ($owner) {
        $owner_val = 1;
    }
    $sql = "SELECT group.id as id, group.name FROM minecraft_worldguard.group
        LEFT JOIN minecraft_worldguard.region_groups ON group.id=region_groups.group_id
        WHERE region_id='$lot' AND owner=$owner_val;";
    $D = umc_mysql_fetch_all($sql);
    $members = false;
    if (count($D) > 0) {
        $members = array();
        foreach ($D as $row) {
            $members[$row['id']] = $row['name'];
        }
    }
    return $members;
}

function umc_get_lot_flags($lot) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $sql = "SELECT `flag`, `value` FROM minecraft_worldguard.region_flag WHERE region_id='$lot';";
    $D = umc_mysql_fetch_all($sql);
    $flags = false;
    if (count($D) > 0) {
        $flags = array();
        foreach ($D as $row) {
            $flag = $row['flag'];
            $value = trim($row['value']);
            $flags[$flag] = $value;
        }
    }
    return $flags;
}

//  Utility: Look up players/worlds by name and return the id
function umc_get_worldguard_id($type, $name, $add = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $valid_terms = array('world', 'user');
    if (!in_array($type, $valid_terms)) {
        XMPP_ERROR_trigger("Error trying to get a type ID of unknown type: $type");
        return false;
    }
    $name_str = 'name';
    if ($type == 'user') {
        $name = umc_uuid_getone($name, 'uuid');
        $name_str = 'uuid';
    }

    $sql = "SELECT id FROM minecraft_worldguard.$type WHERE $name_str LIKE '$name';";
    $D = umc_mysql_fetch_all($sql);
    if (count($D) > 0) {
        $row = $D[0];
        return $row['id'];
    } else if (($type == 'user') && $add) {
        $uuid = umc_uuid_getone($name, 'uuid');
        $sql = "INSERT INTO minecraft_worldguard.user (uuid) VALUES ('$uuid');";
        $rst = umc_mysql_query($sql);
        $user_id = umc_mysql_insert_id();
        umc_mysql_free_result($rst);
        if (!$user_id) {
            XMPP_ERROR_trigger("Error with username '$name/$uuid'. User does not exist and could not create! (umc_get_worldguard_id)");
            return false;
        }
        return $user_id; //  No such world or player.
    } else if (($type == 'user') && !$add) {
        return false;
    } else if ($type == 'world') {
        XMPP_ERROR_trigger("Error with $type '$name'. $type does not exist! (umc_get_worldguard_id)");
        return false;
    }
}

/**
 * Check if a user is owner of a lot.
 * If UUID is false, returns the current owner of the lot
 *
 * @param type $lot
 * @param type $uuid
 * @return boolean
 */
function umc_check_lot_owner($lot, $uuid = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    $lot_sql = umc_mysql_real_escape_string($lot);
    if ($uuid) {
        $uuid = umc_uuid_getone($uuid, 'uuid');
        $uuid_sql = umc_mysql_real_escape_string($uuid);

        $sql = "SELECT region_id FROM minecraft_worldguard.region_players
            LEFT JOIN minecraft_worldguard.user ON user_id=user.id
            WHERE Owner=1 AND user.uuid=$uuid_sql AND region_id=$lot_sql;";
        $D = umc_mysql_fetch_all($sql);
        // echo $sql;
        if (count($D) == 1) {
            return true;
        }
    } else {
        $sql = "SELECT uuid FROM minecraft_worldguard.region_players
            LEFT JOIN minecraft_worldguard.user ON user_id=user.id
            WHERE Owner=1 AND region_id=$lot_sql;";
        $data = umc_mysql_fetch_all($sql);
        if (count($data) == 0) {
            return false;
        } else {
            return $data[0]['uuid'];
        }
    }
    return false;
}


/**
 * resets lots
 *
 * @global type $UMC_SETTING
 * @global array $UMC_PATH_MC
 * @param type $debug\
 */
function umc_lot_reset_process() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_SETTING;

    umc_log('lot_manager', 'reset', "Lot reset process started");
    // first of all, clean up user dibs
    umc_lot_manager_dib_cleanup();

    // get banned users UUID => username
    $banned_users = umc_banned_users();
    // var_dump($banned_users);
    // donators UUID => leftover days
    $donators = umc_donation_list_donators();

    $dibs = umc_lot_manager_dibs_get_all();

    // Update all userlevels in UUID table
    $upd_sql = 'UPDATE minecraft_srvr.UUID
        LEFT JOIN minecraft_srvr.permissions_inheritance ON UUID.UUID=permissions_inheritance.child
        SET userlevel = parent
        WHERE parent != userlevel';
    umc_mysql_query($upd_sql, true);

    // get dates for -1 Month and -2 months
    $now_datetime = umc_datetime();
    $now_datetime->modify('-31 day');
    $one_months_ago = $now_datetime->format('Y-m-d H:i:s');
    $now_datetime->modify('-31 day');
    // what date was 2 months ago?
    $two_months_ago = $now_datetime->format('Y-m-d H:i:s');

    $longterm = $UMC_SETTING['longterm'];

    $source_path = $UMC_SETTING['path']['worlds_mint'];
    $dest_path = $UMC_SETTING['path']['worlds_save'];

    // get all occupied lots and their owners
    // TODO: We should get first all expired users and reset their shop inventory, then do their lots.
    $list_sql = "SELECT region_id as lot, user.UUID as uuid, UUID.username as username, world.name as world, userlevel, lastlogin
            FROM minecraft_worldguard.region_players LEFT JOIN minecraft_worldguard.user ON user_id=user.id
            LEFT JOIN minecraft_worldguard.world ON world_id=world.id
            LEFT JOIN minecraft_srvr.UUID ON user.UUID=UUID.UUID
            WHERE owner=1 AND LEFT(region_id, 4) IN {$UMC_SETTING['lot_worlds_sql']}";
    $D = umc_mysql_fetch_all($list_sql);

    /**
     * Actions to be taken, with reasons
     */
    $A = array();
    foreach ($D as $row) {
        $owner_username = strtolower($row['username']);
        $owner_uuid = $row['uuid'];
        $owner_lastlogin = $row['lastlogin'];
        $owner_level = $row['userlevel'];

        $no_reset = array('uncovery', 'riedi77', 'psiber');
        if (in_array($owner_username, $no_reset)) {
            continue;
        }

        // we do not reset active donators, except those who are banned
        if (in_array($owner_uuid, $donators) && !isset($banned_users[$owner_uuid])) {
            continue;
        }

        $lot = $row['lot'];
        $world = $row['world'];

        $lot_dibs = false;
        if (isset($dibs[$lot])) {
            $lot_dibs = $dibs[$lot];
        }

        // sanity check
        if ((!isset($row['userlevel'])) || (!in_array($owner_level, $UMC_SETTING['ranks']))) {
            $this_row = var_export($row, true);
            XMPP_ERROR_trigger("Could not reset lots, userlevel failure for Owner '$owner_username / $owner_uuid / $owner_level': $list_sql // $this_row");
            die("userlevel error");
        }

        if (isset($banned_users[$owner_uuid])) {
            $A[$lot] = array(
                'reason' => "$lot was reset because $owner_username / $owner_uuid was banned",
                'source_world' => "$source_path/$world",
                'dest_world' => "$dest_path/$world",
                'remove_users' => true,
                'reset_to' => $lot,
                'user_shop_clean' => false, // we do that via plugin-event
                'dibs' => $lot_dibs,
                'version_sql' => false,
                'del_skyblock_inv' => false,
            );
        } else if ($owner_username == '_abandoned_') {
            $A[$lot] = array(
                'reason' => "$lot was reset because Owner was _abandoned_",
                'source_world' => "$source_path/$world",
                'dest_world' => "$dest_path/$world",
                'remove_users' => true,
                'reset_to' => $lot,
                'user_shop_clean' => false,
                'dibs' => $lot_dibs,
                'version_sql' => false,
                'del_skyblock_inv' => false,
            );
        } else {
            $longterm_user = in_array($owner_level, $longterm);
            if (($owner_lastlogin < $two_months_ago) && $longterm_user) {
                $A[$lot] = array(
                    'reason' => "$lot was reset because $owner_username / $owner_uuid is 2 months protected but was absent for 2 months (last login $owner_lastlogin)",
                    'source_world' => "$source_path/$world",
                    'dest_world' => "$dest_path/$world",
                    'remove_users' => true,
                    'reset_to' => $lot,
                    'user_shop_clean' => $owner_uuid,
                    'dibs' => $lot_dibs,
                    'version_sql' => false,
                    'del_skyblock_inv' => false,
                );
            } else if (($owner_lastlogin < $one_months_ago) && !$longterm_user) {
                $A[$lot] = array(
                    'reason' => "$lot was reset because $owner_username / $owner_uuid was absent for 1 months (last login $owner_lastlogin)",
                    'source_world' => "$source_path/$world",
                    'dest_world' => "$dest_path/$world",
                    'remove_users' => true,
                    'reset_to' => $lot,
                    'user_shop_clean' => $owner_uuid,
                    'dibs' => $lot_dibs,
                    'version_sql' => false,
                    'del_skyblock_inv' => false,
                );
            }
        }
        // reset skyblock inventories
        if ($world == 'skyblock' && isset($A[$lot])) { //
           $A[$lot]['del_skyblock_inv'] =  $owner_uuid;
        }
    }

    // choice based resets
    $sql = "SELECT lot, world.name as world, choice, version, mint_version
        FROM minecraft_srvr.lot_version
        LEFT JOIN minecraft_worldguard.region ON lot=id
        LEFT JOIN minecraft_worldguard.world ON world_id=world.id
        WHERE SUBSTR(lot_version.lot, 1, 4) IN {$UMC_SETTING['lot_worlds_sql']} AND choice IS NOT NULL;";
    $R = umc_mysql_fetch_all($sql);

    // fixed choices:
    foreach ($R as $row) {
        $lot = $row['lot'];
        $version = $row['version'];
        $mint_version = $row['mint_version'];
        $choice = $row['choice'];
        $world = $row['world'];
        // reset the lot
        if ($choice == 'reset') { // any lot that can be reset
            // set the new version to the chosen lot
            $A[$lot] = array(
                'reason' => "Lot $lot version was set from $version to $mint_version after reset",
                'source_world' => "$source_path/$world",
                'dest_world' => "$dest_path/$world",
                'del_skyblock_inv' => false,
                'remove_users' => false,
                'reset_to' => $lot,
                'user_shop_clean' => false,
                'dibs' => false,
                'version_sql' => "UPDATE minecraft_srvr.lot_version SET choice=NULL, version='$mint_version' WHERE lot='$lot' LIMIT 1;",
            );
        } else if ($choice == 'mint_king' && $world == 'draftlands') {
            $choice = umc_get_draftlands_kingdom_equivalent($lot);
            $A[$lot] = array(
                'reason' => "Lot $lot version was reset to mint kingdom version $choice by user choice",
                'source_world' => "$source_path/kingdom",
                'dest_world' => "$dest_path/draftlands",
                'del_skyblock_inv' => false,
                'remove_users' => false,
                'reset_to' => $choice,
                'user_shop_clean' => false,
                'dibs' => false,
                'version_sql' => "UPDATE minecraft_srvr.lot_version SET choice=NULL, version='$choice' WHERE lot='$lot' LIMIT 1;",
            );
        } else if ($choice == 'curr_king' && $world == 'draftlands') {
            $choice = umc_get_draftlands_kingdom_equivalent($lot);
            $A[$lot] = array(
                'reason' => "Lot $lot version was reset to current kingdom version $choice by user choice",
                'source_world' => "$dest_path/kingdom",
                'dest_world' => "$dest_path/draftlands",
                'del_skyblock_inv' => false,
                'remove_users' => false,
                'reset_to' => $choice,
                'user_shop_clean' => false,
                'dibs' => false,
                'version_sql' => "UPDATE minecraft_srvr.lot_version SET choice=NULL, version='$choice' WHERE lot='$lot' LIMIT 1;",
            );
        } else { // other non-default options to reset to, usually lot names on the same world
            // assume that we always copy from the same world, but mint version
            $A[$lot] = array(
                'reason' => "Lot $lot version was reset to $choice (user choice)",
                'source_world' => "$source_path/" . umc_get_lot_world($choice),
                'dest_world' => "$dest_path/$world",
                'del_skyblock_inv' => false,
                'remove_users' => false,
                'reset_to' => $choice,
                'user_shop_clean' => false,
                'dibs' => false,
                'version_sql' => "UPDATE minecraft_srvr.lot_version SET choice=NULL, version='$choice' WHERE lot='$lot' LIMIT 1;",
            );
        }
    }

    // iterate the items
    $count = count($A);
    umc_log('lot_manager', 'reset', "$count lots marked for reset");
    foreach ($A as $lot => $a) {
        umc_lot_manager_reset_lot($lot, $a);
    }
    XMPP_ERROR_trigger("Lot reset process finished");
}

/**
 * Do an actual lot reset
 *
 * @param type $lot
 * @param type $a
 */
function umc_lot_manager_reset_lot($lot, $a) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    umc_log('lot_manager', 'reset', "restting lot $lot");
    $debug = $a['reason'];
    // we assume reseting of chunks, unless the dibs owner does not want to
    $a['reset_chunks'] = true;
    $reason = $a['reason'];
    $a['new_owner'] = false;

    // check dibs

    if ($a['dibs']) {
        umc_log('lot_manager', 'reset', "processing dibs for $lot");
        if (count($a['dibs']) > 1) {
            $debug .= "Lot $lot has more than 1 dibs applicant, aborting! ";
            return;
        }
        $debug .= "Lot $lot has dibs! ";
        // we iterate the people who asked for the lot, and
        // once we found a valid one, execute the actions
        //
        // this can only be done AFTER current owners have been removed
        // since the check_before_assign fails if the lot is owned by someone
        umc_lot_remove_all($lot);
        $a['remove_users'] = false;

        foreach ($a['dibs'] as $dibs_info) {
            $dibs_uuid = $dibs_info['uuid'];
            $debug .= " user $dibs_uuid: ";

            $dibs_check = umc_lot_manager_check_before_assign($dibs_uuid, $lot);
            XMPP_ERROR_trace('umc_lot_manager_check_before_assign result', $dibs_check);
            if ($dibs_check['result']) {
                $debug .= " OK!";
                $reason .= "user $dibs_uuid had dibs and got the lot";
                $a['new_owner'] = $dibs_uuid;
                $a['new_owner_costs'] = $dibs_check['cost'];
                if ($dibs_info['action'] == 'none') {
                    $a['reset_chunks'] = false;
                    $reason .= " but dibs owner did not want to reset!";
                }
                break;
            } else {
                $debug .= " NOT OK, going for next!";
            }
            umc_lot_manager_dib_delete($dibs_uuid, $lot);
            XMPP_ERROR_send_msg("$debug");
        }
        //echo $debug . "<br>";
    }

    // reset all lots
    // $debug .= "Lot ready for reset!";
    $source_lot = $lot;
    if ($a['user_shop_clean']) { // this is the UUID or false
        // plugin event on user inactiviy
        umc_log('lot_manager', 'reset', "cleaning shop for user of $lot");
        umc_plugin_eventhandler('user_inactive', $a['user_shop_clean']);
    }
    if ($a['remove_users']) {
        $debug .= " Removing all users ";
        umc_log('lot_manager', 'reset', "removing all users of $lot");
        umc_lot_remove_all($lot);
    }
    if ($a['reset_to']) {
        $source_lot = $a['reset_to'];
    }
    if ($a['del_skyblock_inv']) { // value is false or the uuid
        umc_log('lot_manager', 'reset', "deleting skyblock inv of $lot");
        umc_lot_skyblock_inv_reset($a['del_skyblock_inv']);
    }
    if ($a['reset_chunks']) {
        umc_log('lot_manager', 'reset', "resetting chunks of $lot");
        umc_move_chunks($source_lot, $a['source_world'], $lot, $a['dest_world'], false);
    }
    umc_log('lot_manager', 'reset', $reason);
    if ($a['version_sql']) {
        umc_mysql_query($a['version_sql'], true);
    }
    if ($a['new_owner']) { // give lot to dibs owner and charge money
        umc_lot_add_player($a['new_owner'], $lot, 1, $a['new_owner_costs']);
        // remove dibs from database
        // umc_lot_manager_dib_delete($a['new_owner'], $lot);
    }
    $debug .= "$source_lot, {$a['source_world']}, $lot, {$a['dest_world']}";

    umc_log('lot_manager', 'reset', "processing $lot done!");
    XMPP_ERROR_trace(__FUNCTION__, $debug);
}

/**
 * Reset a user's skyblock inventory, used when a skyblock lot is reset
 *
 * @global array $UMC_PATH_MC
 * @param type $username
 */
function umc_lot_skyblock_inv_reset($uuid) {
    $username = umc_uuid_getone($uuid, 'username');

    global $UMC_PATH_MC;
    $inv_yml = "$UMC_PATH_MC/server/bukkit/plugins/Multiverse-Inventories/worlds/skyblock/" . $username . '.yml';
    if (file_exists($inv_yml)) {
        unlink($inv_yml);
        umc_log('lot_manager', 'inventory-reset', "$inv_yml was deleted because $username's lot was reset");
    }
    $inv_json = "$UMC_PATH_MC/server/bukkit/plugins/Multiverse-Inventories/worlds/skyblock/" . $username . '.json';
    if (file_exists($inv_json)) {
        unlink($inv_json);
        umc_log('lot_manager', 'inventory-reset', "$inv_json was deleted because $username's lot was reset");
    }
}

/**
 * maintenance function to give all empty lots of a world to the _abandoned_ user for reset
 */
function umc_lot_reset_all_empty() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $sql = "SELECT region_cuboid.region_id as lot
        FROM minecraft_worldguard.region_cuboid LEFT JOIN minecraft_worldguard.region_players ON region_cuboid.region_id=region_players.region_id
        WHERE SUBSTR(region_cuboid.region_id, 1, 4) IN ('bloc') AND user_id IS NULL";
    $rst = umc_mysql_query($sql);
    while ($row = umc_mysql_fetch_array($rst)) {
        $lot = $row['lot'];
        umc_lot_add_player('_abandoned_', $lot, 1);
        echo "Processed lot $lot!<br>";
    }
}


/**
 * Moch chunks for lot resets
 *
 * @global array $UMC_PATH_MC
 * @param string $source_lot
 * @param string $source_world
 * @param string $dest_lot
 * @param string $dest_world
 * @param boolean $echo
 * @return boolean
 */
function umc_move_chunks($source_lot, $source_world, $dest_lot, $dest_world, $echo = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_PATH_MC;
    $exec_path = "$UMC_PATH_MC/server/chunk/copychunk";

    // get coordinates
    $sql = "SELECT * FROM minecraft_worldguard.region_cuboid WHERE region_id = '$source_lot' LIMIT 1;";
    $D = umc_mysql_fetch_all($sql);
    if (count($D) != 1) {
        XMPP_ERROR_trigger("Tried to reset $source_lot from $source_world to $dest_lot on $dest_world but $source_lot could not be found");
        return false;
    }
    $row = $D[0];

    $min_x = floor($row['min_x'] / 16);
    $max_x = floor($row['max_x'] / 16);
    $min_z = floor($row['min_z'] / 16);
    $max_z = floor($row['max_z'] / 16);

    if ($source_lot != $dest_lot) {
        $sql_dest = "SELECT * FROM minecraft_worldguard.region_cuboid WHERE region_id = '$dest_lot' LIMIT 1;";
        $D_dest = umc_mysql_fetch_all($sql_dest);
        if (count($D_dest) != 1) {
            XMPP_ERROR_trigger("Tried to reset $source_lot from $source_world to $dest_lot on $dest_world but $dest_lot could not be found");
            return false;
        }
        $row_dest = $D_dest[0];
        $min_x_dest = floor($row_dest['min_x'] / 16);
        $max_x_dest = floor($row_dest['max_x'] / 16);
        $min_z_dest = floor($row_dest['min_z'] / 16);
        $max_z_dest = floor($row_dest['max_z'] / 16);
    }

    // check if craftbukkit is running
    $output = array();
    $exec_cmd = 'ps ax | grep -v grep | grep -v -i SCREEN | grep spigot.jar';
    if (!$echo) {
        exec($exec_cmd, $output);
        if (count($output) > 0) {
            XMPP_ERROR_trigger("Tried to move chunks while Minecraft server was running: $source_lot, $source_world, $dest_lot, $dest_world");
            return false;
        }
    }
    if ($source_lot == $dest_lot) {
        $exec_cmd = "$exec_path $source_world $dest_world $min_x $min_z $max_x $max_z";
    } else {
        $exec_cmd = "$exec_path $source_world $dest_world $min_x $min_z $max_x $max_z $min_x_dest $min_z_dest $max_x_dest $max_z_dest";
    }

    // $exec_cmd . "<br>";
    if ($echo) {
        echo $exec_cmd . "\n";
    } else {
        exec($exec_cmd, $output);
    }
    if ($echo) {
        echo "\n\nATTENTION: No changes done, please execute commands above!";
    }
    umc_log('lot_manager', 'move chunks', "Moved lot from $source_lot to $dest_lot with command $exec_cmd");
    return true;
}

function umc_temp() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_PATH_MC;
    $exec_path = "$UMC_PATH_MC/server/chunk/copychunk";
    $source_world = "$UMC_PATH_MC/server/bukkit/city";
    $dest_world = "$UMC_PATH_MC/server/bukkit/kingdom";

    // city all cordinate, random
    $city_x1 = 337;
    $city_z1 = 227;

    $city_x2 = 750;
    $city_z2 = -86;

    // kingdom top-left coordinate
    $king_x1 = -2605;
    $king_z1 = -2489;

    // top-left coordinate
    $city_min_x = floor(min($city_x1, $city_x2) / 16);
    $city_max_x = floor(max($city_x1, $city_x2) / 16);
    $city_min_z = floor(min($city_z1, $city_z2) / 16);
    $city_max_z = floor(max($city_z1, $city_z2) / 16);
    // dimensions
    $city_x_dist = abs($city_max_x - $city_min_x);
    $city_z_dist = abs($city_max_z - $city_min_z);

    // kingdom destinatoon
    $king_min_x = floor($king_x1 / 16);
    $king_min_z = floor($king_z1 / 16);
    $king_max_x = $king_min_x + $city_x_dist;
    $king_max_z = $king_min_z + $city_z_dist;

    $exec_cmd = "$exec_path $source_world $dest_world $city_min_x $city_min_z $city_max_x $city_max_z $king_min_x $king_min_z $king_max_x $king_max_z";
    echo $exec_cmd;

}

function umc_restore_from_backup() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_SETTING;

    $lots = array(
        'king_h19'=>'camerissan',
        'king_h19_a'=>'camerissan',
        'king_h19_c'=>'camerissan',
        'king_h20'=>'camerissan',
        'king_h20c'=>'camerissan',
        'king_g20'=>'camerissan'
    );

    $source_folder = "/home/minecraft/server/worlds_restore/"; // trailing /
    $dest_folder = $UMC_SETTING['path']['worlds_save'];

    foreach ($lots as $lot => $owner) {
        echo "processing lot $lot\n";
        umc_lot_add_player($owner, $lot, 1);
        $world = umc_get_lot_world($lot);
        if (!$world) {
            die("World of lot $lot could not be found!");
        }
        // echo "Restoring with $lot, $source_folder$world, $lot, $dest_folder$world";
        umc_move_chunks($lot, $source_folder . $world, $lot, $dest_folder . "/" . $world, true);

    }
}

function umc_manual_reset_lot() {
    $lots = array('flat_b14');
    foreach ($lots as $lot) {
        umc_lot_add_player('_abandoned_', $lot, 1);
    }

}

function umc_flat_lot(){
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_SETTING;
    $source_lot = 'emp_g3';
    $source_world = "/tmp/minecraft/server/worlds/save/empire"; // "/home/minecraft/server/worlds/mint/empire";
    $dest_lot = 'emp_g3';
    $dest_world = $UMC_SETTING['path']['worlds_save'] . "/empire";
    umc_move_chunks($source_lot, $source_world, $dest_lot, $dest_world, true);
    echo "execute the command above now!";
}

/**
 * get existing and available lots for a user in a specific world
 *
 * @global type $UMC_SETTING
 * @global type $UMC_USER
 * @param type $user
 * @param type $world
 * @param boolean $main_lots_only
 * @return type
 */
function umc_get_lot_number($uuid, $world = 'empire', $main_lots_only = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_SETTING;

    $userlevel = umc_userlevel_get($uuid);
    $lot_limit = $UMC_SETTING['lot_limits'][$userlevel];
    if ($world == 'empire' || $world == 'flatlands') {
        $worlds = array('flatlands', 'empire');
        $max_lots = $lot_limit['empire'];
    } else {
        $worlds = array($world);
        $max_lots = $lot_limit[$world];
    }

    $lotcount = 0;
    $lot_list = array();

    // check how many lots the user has in that world
    foreach ($worlds as $world) {
        $sql = "SELECT region_id FROM minecraft_worldguard.region_players
            LEFT JOIN minecraft_worldguard.user ON region_players.user_id=user.id
            LEFT JOIN minecraft_worldguard.world ON region_players.world_id = world.id
            WHERE user.uuid = '$uuid' AND owner=1 AND world.name='$world'";

        if ($main_lots_only) {
            $sql .= " AND region_id REGEXP '^[a-z]+_[a-z0-9]*$'";
        }

        $D = umc_mysql_fetch_all($sql);
        //echo $sql;
        foreach ($D as $row) {
            if (substr($row['region_id'], 0, 3) !== 'con') {
                $lot = $row['region_id'];
                $lot_list[$lot] = array('tile' => umc_user_get_lot_tile($lot));
                $lotcount ++;
                //echo "You own the lot {$row['region_id']}<br>";
            }
        }
    }
    // echo "You own $lotcount lots and can have a max of $max_lots!<br>";

    $leftover = $max_lots - $lotcount;
    $user_lots = array(
        'max_lots' => $max_lots,
        'avail_lots' => $leftover,
        'used_lots' => $lotcount,
        'lot_list' => $lot_list,
        'dib_list' => umc_lot_manager_dib_get_number($uuid, $world),
    );
    // echo "You have $leftover lots left in the world $world.";
    return $user_lots;
}

/**
 * return dibs information per world
 */
function umc_lot_manager_dib_get_number($user, $world) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $uuid = umc_uuid_getone($user, 'uuid');
    $sql = "SELECT lot, reservation_id, action
        FROM minecraft_srvr.lot_reservation
        WHERE uuid='$uuid' and world='$world';";
    $D = umc_mysql_fetch_all($sql);
    $dibs_arr = array();
    foreach ($D as $row) {
        $dibs_arr[$row['lot']] = array('tile' => umc_user_get_lot_tile($row['lot']), 'action' => $row['action']);
    }
    return $dibs_arr;
}

/**
 * Create an array of all dibs for processing during the lot reset process
 */
function umc_lot_manager_dibs_get_all() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $dibs_sql = 'SELECT * FROM minecraft_srvr.lot_reservation ORDER BY lot, date;';
    $R = umc_mysql_fetch_all($dibs_sql);
    $dibs = array();
    $a_user = umc_get_active_members();
    foreach ($R as $r) {
        // check if the user is active or not
        if (isset($a_user[$r['uuid']])) {
            $dibs[$r['lot']][$r['date']] = array('uuid' => $r['uuid'], 'action' => $r['action']);
        } else {
            umc_lot_manager_dib_delete($r['uuid']);
        }
    }
    return $dibs;
}

//  Utility: Check that a region exists
function umc_check_lot_exists($world_id, $lot) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    if (!is_numeric($world_id)) {
        $world_id = umc_get_worldguard_id('world', $world_id);
    }

    //  Make sure the region exists
    $lot_sql = umc_mysql_real_escape_string($lot);
    $sql = "SELECT id FROM minecraft_worldguard.region WHERE world_id = $world_id AND id = $lot_sql";
    //echo $sql;
    $C = umc_mysql_fetch_all($sql);
    if (count($C) < 1) {
         // echo("No such region '$lot' found in world '$world_id'");
        return false;
    }
    return true;
}
