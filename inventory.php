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
 * This file manages several commonly used aspects of inventory management.
 * It can take opbjects from the inventory or add them, check av. space etc.
 */

/**
 * checks how much of one item the user has
 *
 * @param string $item_name or item ID number of inv item
 * @param type $data Data number of item
 * @param type $meta serialized array of metadata (enchantments, banners)
 * @return $amount type
 */
function umc_check_inventory($item_name, $data, $meta) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $inv = $UMC_USER['inv'];
    $amount = 0;

    // this needs to be numeric since the inventory slots are still numeeric
    /*
    if (is_numeric($item_name)) {
        $item_id = $UMC_DATA[$item_name]['id'];
    } else {
        $item_id = $item_name;
    }
     *
     */
    if (strpos($meta, "{") === 0) {
        $comparator = 'nbt';
    } else if (!is_array($meta)) {
        $comparator = 'meta';
        $meta = unserialize($meta);
    }
    //umc_error_longmsg($meta);

    foreach ($inv as $inv_item) {
        // we have to make sure we do not compare enchanted w. non-enchated items
        if ($inv_item[$comparator] && (count($meta) >= 1)) {
            if (($inv_item['item_name'] == $item_name) && ($inv_item['data'] == $data) && ($inv_item[$comparator] == $meta)) {
                $amount = $amount + $inv_item['amount'];
            }
        } else if (!$inv_item[$comparator] && !$meta) {
            if ($inv_item['item_name'] == $item_name && $inv_item['data'] == $data) {
                $amount = $amount + $inv_item['amount'];
            }
        }
    }
    return $amount;
}

/**
 * Remove $amount of an item from the logged-in player's inventory
 *
 * @param type $item_name
 * @param type $data
 * @param type $amount
 * @param type $meta
 * @return boolean
 */
function umc_clear_inv($item_name, $data, $amount, $meta = '') {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    // umc_echo("trying to remove id $id, data $data, amount $amount, Enchantment $meta");
    global $UMC_USER;
    $inv = $UMC_USER['inv'];
    $player = $UMC_USER["username"];

    if ($meta == '') { // websend sets default meta to false, let's do the same
        $meta = serialize(false);
    }

    if (is_array($meta)) {
        $meta = serialise($meta);
    }

    $removed = 0;
    $clearslots = array();
    foreach ($inv as $slot => $item) {
        $comparator = 'meta';
        if (!is_array($meta) && strpos($meta, "{") === 0) {
            // we have nbt
            $comparator = 'nbt';
        } else if (is_array($item['meta'])) { //we have a meta tag (legacy)
            $item['meta'] = serialize($item['meta']);
        } else { // we do not have any valid meta
            $item['meta'] = serialize(false);
        }
        // echo "$slot:{$item['id']}:{$item['data']}:{$item['meta']} vs $meta";
        if (($item['item_name'] == $item_name) && ($item['data'] == $data) && ($item[$comparator] == $meta)) {
            if ($amount >= $item['amount']) {
                // we only prepare the list of to be cleared slots to remove them later with "removeitems"
                $clearslots[] = $slot;
                $amount = $amount - $item['amount'];
                $removed = $removed + $item['amount'];
            } else {
                // single items are cleared right now with "removeitem"
                umc_ws_cmd("removeitem $player $slot $amount", 'asConsole');
                $amount = $amount - $amount;
                $removed = $amount;
            }
            if ($amount == 0) {
                break;
            }
        }
    }
    if (count($clearslots) > 0) {
        umc_ws_cmd("removeitems $player " . implode(" ", $clearslots), 'asConsole');
    }
    
    if ($amount != $removed && $amount > 0) {
        XMPP_ERROR_trigger("Could not remove item $id:$data in amount $amount (" . var_export($meta, true) . ") from user $player!");
    }
    if ($amount == 0) {
        return true;
    } else {
        return false;
    }
}


function umc_check_space($amount, $item_name, $type) {
    global $UMC_DATA_ID2NAME, $UMC_USER, $UMC_DATA;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    $inv = $UMC_USER['inv'];

    if (is_numeric($item_name)) {
        XMPP_ERROR_trigger('UMC_DATA_ID2NAME USAGE');
        $item_name = $UMC_DATA_ID2NAME[$item_name];
    }

    // first find how many free slots we have
    $free = 0;
    for ($i = 0; $i < 36; $i++) {
        if (!isset($inv[$i]) || ($inv[$i]['amount'] == 0)) {
            $free++;
        }
    }
    if (isset($UMC_DATA[$item_name]['subtypes'])) {
        $type = 0;
    }

    $stack_size = 1;
    if (isset($UMC_DATA[$item_name]['stack'])) {
        $stack_size = $UMC_DATA[$item_name]['stack'];
    } else {
        XMPP_ERROR_trigger("umc_check_space error with item $item_name and type $type");
    }

    $need_slots = ceil($amount / $stack_size);

    if ($free >= $need_slots) {
        return true;
    } else {
        umc_error("{red}You only have {white}$free{red} empty slots but need {white}$need_slots{red}. "
            . "{red}Try a smaller amount, or free up some inventory space.;");
    }
}

//Check how much space is needed in $inv to hold $fill of $item and if it fits into the users inv.
function umc_check_space_multiple($items) {
    global $UMC_USER, $UMC_DATA;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $inv = $UMC_USER['inv'];

    umc_echo("Trying to check out multiple goods...");
    // first find how many free slots we have
    $free = 0;
    for ($i = 0; $i < 36; $i++) {
        if (!isset($inv[$i]) || ($inv[$i]['amount'] == 0)) {
            $free++;
        }
    }
    $overall_need = 0;
    foreach ($items as $data) {
        $amount = $data['amount'];
        $nbt = $data['nbt'];
        $item_name = $data['item_name'];
        if (!isset($UMC_DATA[$item_name]['stack'])) {
            $msg = "umc_check_space_multiple error with item $item_name / $nbt, could not find item in UMC_DATA array:" . var_export($items, true);
            XMPP_ERROR_send_msg($msg);
            XMPP_ERROR_trigger($msg);
            umc_error("There was an error calculating your free space. The admin has been informed. Process stopped.");
        }
        $need_slots = ceil($amount / $UMC_DATA[$item_name]['stack']);
        $overall_need = $overall_need + $need_slots;
    }
    if ($overall_need > $free) {
        umc_error("{red}You have {white}$free{red} empty slots but need {white}$overall_need{red}. {red}Try a smaller amount, or free up some inventory space.;");
    } else {
        umc_echo("You need $overall_need empty slots, $free found, proceeing to withdraw....");
    }
}

/**
 * Add items to a user inventory. If cancel=true, we check if the current user is owner of the goods
 *
 * @global type $UMC_USER
 * @param type $id
 * @param type $amount
 * @param type $table
 * @param boolean $cancel
 * @param type $to_deposit
 * @param string $uuid
 * @return string
 */
function umc_checkout_goods($id, $amount, $table = 'stock', $cancel = false, $to_deposit = false, $uuid = false) {
    global $UMC_USER, $UMC_ENV;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    if (!$uuid) {
        $player = $UMC_USER['username'];
        $uuid = $UMC_USER['uuid'];
    } else {
        $player = umc_user2uuid($uuid);
    }

    if (!is_numeric($id)) {
        umc_error('{red}Invalid ID. Please use {yellow}/shophelp;');
    }
    // the fact that the source is also a condition prevents people to cancel other users' items.
    if ($table == 'stock') {
        if ($cancel) {
            $sql = "SELECT * FROM minecraft_iconomy.stock WHERE uuid='$uuid' AND id='$id' LIMIT 1;";
        } else {
            $sql = "SELECT * FROM minecraft_iconomy.stock WHERE id='$id' LIMIT 1;";
        }
    } else if ($table == 'deposit') {
        $sql = "SELECT * FROM minecraft_iconomy.deposit
            WHERE (sender_uuid='$uuid' OR recipient_uuid='$uuid') AND id='$id'LIMIT 1;";
    }
    $D = umc_mysql_fetch_all($sql);
    if (count($D) == 0) {
        umc_error("{red}Id {white}$id{red} not found! Please try again.;");
    } else if ($D[0]['amount'] == 0) {
        umc_error("That depositslot is empty!");
    } else {
        $row = $D[0];
        $item = umc_goods_get_text($row['item_name'], $row['damage'], $row['meta']);

        // handle unlimited items
        $unlimited = false;
        if ($row['amount'] == -1) {
            $row['amount'] = $amount;
            $unlimited = true;
        }
        //umc_echo('There were ' . $row['amount'] . " pieces of " . $item['item_name'] . "$meta_txt stored.");
        // determine withdrawal amount
        if (is_numeric($amount) && ($amount <= $row['amount'])) {
            $sellamount = $amount;
        } else if ($amount == 'max') {
            // withdraw all
            $sellamount = $row['amount'];
            //umc_echo("You are withdrawing all ($sellamount) {$item['name']}$meta_txt");
        } else if (is_numeric($amount) && ($amount > $row['amount'])) {
            umc_echo("{yellow}[!]{gray} Available amount ({yellow}{$row['amount']}{gray}) less than amount specified ({yellow}$amount{gray})");
            $sellamount = $row['amount'];
        } else {
            umc_error("{red}Amount {white}'$amount'{red} is not numeric;");
        }
        if ($table != 'stock') {
            $format_color = 'green';
            if ($item['nbt_raw']) { // magix items are aqua
                $format_color = 'aqua';
            }
            $data = array(
                array('text' => '[+]', 'format' => 'green'),
                array('text' => 'You are withdrawing', 'format' => 'gray'),
                array('text' => $amount, 'format' => 'yellow'),
                array('text' => $item['name'], 'format' => array($format_color, 'show_item' => array('item_name' => $item['item_name'], 'damage' => $item['type'], 'nbt' => $item['nbt_raw']))),
            );
            umc_text_format($data, false, true);
        }

        if ($table == 'stock') {
            $cost = $sellamount * $row['price'];
            if ($cancel) {
                $target = $uuid;
                $source = 'cancel00-sell-0000-0000-000000000000';
            } else {
                $target = $uuid;
                $source = $row['uuid'];
            }
        } else if ($table == 'deposit') {
            if ($row['recipient_uuid'] == $uuid) {
                $cancel = true;
            }
            $cost = 0;
            if ($cancel) {
                $target = $uuid;
                $source = 'cancel00-depo-0000-0000-000000000000';
            } else {
                $target = $row['recipient_uuid'];
                $source = $row['sender_uuid'];
            }
        }

        if(!$to_deposit) {
            umc_check_space($sellamount, $item['item_name'], $item['type']);
            // the in-game command does not understand item_names yet
            umc_ws_give($player, $item['item_name'], $sellamount, $item['type'], $row['meta']);
            umc_log('inventory', 'give', "$player received $sellamount {$item['full_clean']}");
        } else {
            umc_deposit_give_item($target, $item['item_name'], $item['type'], $row['meta'], $sellamount, $source);
            umc_log('inventory', 'give_deposit', "$player received $sellamount {$item['full_clean']} in deposit");
        }

        // check status
        umc_shop_transaction_record($source, $target, $sellamount, $cost, $item['item_name'], $item['type'], $row['meta']);

        if ($unlimited) {
            return "unlimited";
        }

        // fix the stock levels
        $amount_left = umc_db_take_item($table, $id, $sellamount, $source);
        if ($UMC_ENV == 'websend') {
            $format_color = 'green';
            if ($item['nbt_raw']) { // magix items are aqua
                $format_color = 'aqua';
            }
            if ($amount_left == 0) {
                $amount_left = 'No more';
            }
            $data = array(
                array('text' => '[+]', 'format' => 'green'),
                array('text' => $amount_left, 'format' => 'gray'),
                array('text' => $item['name'], 'format' => array($format_color, 'show_item' => array('item_name' => $item['item_name'], 'damage' => $item['type'], 'nbt' => $item['nbt_raw']))),
                array('text' => "remaining in stock", 'format' => 'gray'),
            );
            umc_text_format($data, false, true);
        }
        return $amount_left;
    }
}

/**
 * Reset a user's world inventory, used in various applications
 *
 * @global type $UMC_PATH_MC
 * @param type $uuid
 * @param type $world
 */
function umc_inventory_delete_world($uuid, $world) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_PATH_MC;
    $username = umc_uuid_getone($uuid, 'username');

    $status = false;
    $inv_yml = "$UMC_PATH_MC/server/bukkit/plugins/Multiverse-Inventories/worlds/$world/" . $username . '.yml';
    if (file_exists($inv_yml)) {
        unlink($inv_yml);
        umc_log('mod_event', 'inventory-reset', "$inv_yml was deleted");
        $status = true;
    }
    $inv_json = "$UMC_PATH_MC/server/bukkit/plugins/Multiverse-Inventories/worlds/$world/" . $username . '.json';
    if (file_exists($inv_json)) {
        unlink($inv_json);
        umc_log('mod_event', 'inventory-reset', "$inv_json was deleted");
        $status = true;
    }
    return $status;
}