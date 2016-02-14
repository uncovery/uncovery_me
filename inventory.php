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
    if (!is_array($meta)) {
        $meta = unserialize($meta);
    }
    //umc_error_longmsg($meta);

    foreach ($inv as $inv_item) {
        // we have to make sure we do not compare enchanted w. non-enchated items
        if ($inv_item['meta'] && (count($meta) >= 1)) {
            if (($inv_item['item_name'] == $item_name) && ($inv_item['data'] == $data) && ($inv_item['meta'] == $meta)) {
                $amount = $amount + $inv_item['amount'];
            }
        } else if (!$inv_item['meta'] && !$meta) {
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
 * @param type $id
 * @param type $data
 * @param type $amount
 * @param type $meta
 * @return boolean
 */
function umc_clear_inv($id, $data, $amount, $meta = '') {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    // umc_echo("trying to remove id $id, data $data, amount $amount, Enchantment $meta");
    global $UMC_USER;
    $inv = $UMC_USER['inv'];
    $player = $UMC_USER["username"];
    if ($meta == '') {
        $meta = serialize(false);
    }
    if (is_array($meta)) {
        $meta = serialise($meta);
    }
    $removed = 0;
    foreach ($inv as $slot => $item) {
        $item['meta'] = serialize($item['meta']);
        // echo "$slot:{$item['id']}:{$item['data']}:{$item['meta']} vs $meta";
        if (($item['item_name'] == $id) && ($item['data'] == $data) && ($item['meta'] == $meta)) {
            if ($amount >= $item['amount']) {
                umc_ws_cmd("removeitem $player $slot", 'asConsole');
                //umc_echo("removeitem $player $slot");
                $amount = $amount - $item['amount'];
                $removed = $removed + $item['amount'];
            } else {
                umc_ws_cmd("removeitem $player $slot $amount", 'asConsole');
                //umc_echo("removeitem $player $slot $aomunt");
                $amount = $amount - $amount;
                $removed = $amount;
            }
            if ($amount == 0) {
                break;
            }
        }
    }
    if ($amount != $removed && $amount > 0) {
        XMPP_ERROR_trigger("Could not remove item $id:$data in amount $amount (" . var_export($meta, true) . "from user $player!");
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
        $item_name = $UMC_DATA_ID2NAME[$item_name];
    }

    // first find how many free slots we have
    $free = 0;
    for ($i = 0; $i < 36; $i++) {
        if (!isset($inv[$i])) {
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
        XMPP_ERROR_trigger("umc_check_space error with item $item and type $type");
    }

    $need_slots = ceil($amount / $stack_size);

    if ($free >= $need_slots) {
        return true;
    } else {
        umc_error("{red}You have {white}$free{red} empty slots but need {white}$need_slots{red}. "
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
        if (!isset($inv[$i])) {
            $free++;
        }
    }
    $overall_need = 0;
    foreach ($items as $data) {
        $amount = $data['amount'];
        $item_name = $data['item_name'];
        if (!isset($UMC_DATA[$item_name]['stack'])) {
            XMPP_ERROR_trigger("umc_check_space_multiple error with item {$data['item_name']}, could not find item in UMC_DATA array:" . var_export($data, true));
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
            WHERE (sender_uuid='$uuid' OR recipient_uuid='$uuid') AND id='$id' AND amount > 0 LIMIT 1;";
    }
    $D = umc_mysql_fetch_all($sql);
    if (count($D) == 0) {
        umc_error("{red}Id {white}$id{red} not found! Please try again.;");
    } else {
        $row = $D[0];
        $item = umc_goods_get_text($row['item_name'], $row['damage'], $row['meta']);
        $meta_cmd = $meta = '';
        if ($row['meta'] != '') {
            $meta_arr = unserialize($row['meta']);
            if (!is_array($meta_arr)) {
                XMPP_ERROR_trigger("Could not get Meta Data array for $table id $id: " . var_export($row, true));
            }
            if ($row['item_name'] == "banner") {
                $meta_cmd = umc_banner_get_data($meta_arr);
            } else {
                foreach ($meta_arr as $type => $lvl) {
                    $meta_cmd .= " $type:$lvl";
                }
            }
        }

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
            umc_echo("{green}[+]{gray} You are withdrawing {yellow} $amount {gray} of {$item['full']}{gray}.");
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
            umc_ws_cmd("give $player {$item['item_name']}:{$item['type']} $sellamount$meta_cmd;", 'asConsole');
            umc_log('inventory', 'give', "$player received {$item['full_clean']} $sellamount");
        } else {
            umc_deposit_give_item($target, $item['item_name'], $item['type'], $meta, $sellamount, $source);
            umc_log('inventory', 'give_deposit', "$player recived in deposit {$item['full_clean']} $sellamount");
        }
        //umc_echo("./give $player {$item['id']}:{$item['type']} $sellamount$meta_cmd");

        // check status
        umc_shop_transaction_record($source, $target, $sellamount, $cost, $item['item_name'], $item['type'], $meta);

        if ($unlimited) {
            return "unlimited";
        }

        // fix the stock levels
        $amount_left = umc_db_take_item($table, $id, $sellamount, $source);
        if ($UMC_ENV == 'websend') {
            if ($amount_left == 0) {
                umc_echo("{green}[+]{gray} No more {green}{$item['full']}{gray} now in stock.");
            } else {
                umc_echo("{green}[+]{yellow} $amount_left{green} {$item['full']}{gray} remaining in stock.");
            }
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

function umc_inventory_import() {
    $path_root = '/home/minecraft/server/bukkit/plugins/Multiverse-Inventories';
    // enderchest content path: /groups/enderchest
    $paths = array(
        'groups/invshares',
        'groups/enderchest',
        'groups/creative',
        'groups/xpshares',
        'worlds/aether',
        'worlds/city',
        'worlds/darklands',
        'worlds/deathlands',
        'worlds/draftlands',
        'worlds/empire',
        'worlds/flatlands',
        'worlds/hunger',
        'worlds/kingdom',
        'worlds/nether',
        'worlds/skyblock',
        'worlds/the_end',
        'players',
    );
    foreach ($paths as $path) {
        $inv = file_get_contents("$path_root/$path/uncovery.json");
        $inv_array = json_decode($inv, true);
        XMPP_ERROR_trace("$path Inv:", $inv_array);
    }

    $active_users = umc_get_active_members('name');

    $sql = "TRUNCATE minecraft_iconomy.multiinv_multiinv;";
    umc_mysql_execute_query($sql);

    $usernames = array();

    $dirs = array("$path_root/groups/xpshares", "$path_root/groups/invshares");
    foreach ($dirs as $dir) {
        $files = array_diff(scandir($dir), array('..', '.'));
        foreach ($files as $userfile) {
            $username = strtolower(substr($userfile, 0, -5));
            $usernames[$username] = $userfile;
        }
    }

    $empty_str = 'AIR,0,0,0;';
    $new_inv = '';
    // IRON_SWORD,1,0,0;
    // CLAY,2,0,0;
    // BOW,1,0,ARROW_DAMAGE-10000#ARROW_FIRE-10000#DURABILITY-10000#ARROW_INFINITE-10000,#NM#NQ2h1Y2sgTm9ycmlzJyBHdW4=#L#R0;
    // WOOD_SPADE,1,6,0;
    // DIRT,6,0,0;
    // SEEDS,1,0,0;


    // STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0;STONE,1,0,0:DIAMOND_BOOTS,1,0,0;DIAMOND_LEGGINGS,1,0,0;DIAMOND_CHESTPLATE,1,0,0;DIAMOND_HELMET,1,0,0
    //foreach ($active_users as $uuid => $username) {
        $username = 'uncovery';
        $real_name = $usernames[$username];
        //$XP = file_get_contents("$path_root/groups/xpshares/$real_name");
        $INV = file_get_contents("$path_root/groups/invshares/$real_name");

        if (!$XP || !$INV) {
            XMPP_ERROR_trigger("$username / $uuid file could nto be found!");
        }
        $inv_array = json_decode($INV, true);
        //$xp_array = json_decode($XP, true);
        $inv_root = $inv_array['SURVIVAL']['inventoryContents'];
        for ($i=0; $i<=39; $i++) {
            if (isset($inv_root[$i])) {
                $item_name = $inv_root[$i]['type'];
                $damage = '0';
                if (isset($inv_root[$i]['damage'])) {
                    $damage = $inv_root[$i]['damage'];
                }
                $amount = '1';
                if (isset($inv_root[$i]['amount'])) {
                    $amount = $inv_root[$i]['amount'];
                }
                $ench_txt = '0';
                if (isset($inv_root[$i]['meta'])) {
                    $enchs = $inv_root[$i]['meta']['enchants'];
                    foreach ($enchs as $ench => $strength) {
                        $ench_arr[] = $ench . "-" . $strength;
                    }
                    $ench_txt = implode("#",$ench_arr);
                }
                $new_inv .= "$item_name,$amount,$damage,$ench_txt;";
            } else {
                $new_inv .= $empty_str;
            }
        }

        XMPP_ERROR_trace("$username Inv:", $inv_array);
        XMPP_ERROR_trace("$username New Inv:", $new_inv);
        return;
    //}
}