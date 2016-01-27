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
 * This file includes several functions that are used for the shop and general
 * goods/blocks handling across the server.
 */

/**
 * Removes an item from stock or deposit; does not record the transaction
 *
 * @param type $table
 * @param type $id
 * @param type $amount
 * @param type $player
 * @return int
 */
function umc_db_take_item($table, $id, $amount, $player) {
    // $uuid = umc_uuid_getone($player, 'uuid');

    $D = umc_mysql_fetch_all("SELECT amount FROM minecraft_iconomy.$table WHERE id='$id';");
    $amount_row = $D[0];
    $newstock = $amount_row['amount'] - $amount;
    if ($table == 'stock') {
        if ($newstock == 0) {
            $sql = "DELETE FROM minecraft_iconomy.stock WHERE id='$id';";
            umc_log('shop', 'stock_adjust', "Cleared all content from stock for ID $id by withdrawing {$amount_row['amount']}");
        } else {
            $sql = "UPDATE minecraft_iconomy.stock SET amount=$newstock WHERE id='$id';";
            umc_log('shop', 'stock_adjust', "Changed stock level for ID $id from {$amount_row['amount']} to $newstock");
        }
    } else { // take from deposit
        if ($newstock == 0) {
            //$sql = "DELETE FROM minecraft_iconomy.deposit WHERE id='$id';";
            
            // TABLE SCHEMA:
            
            /*
                CREATE TABLE IF NOT EXISTS `deposit` (
                  `id` int(11) NOT NULL,
                  `sender_uuid` varchar(37) NOT NULL,
                  `recipient_uuid` varchar(39) NOT NULL,
                  `damage` int(11) DEFAULT NULL,
                  `amount` int(11) NOT NULL,
                  `meta` text NOT NULL,
                  `item_name` varchar(125) NOT NULL,
                  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=MyISAM DEFAULT CHARSET=latin1;
            */
            
            $new_sender_uuid = 'reusable-0000-0000-0000-000000000000';
            $date = date();
            
            $action = "UPDATE minecraft_iconomy.deposit ";
            $details = "SET "
                . "sender_uuid='$new_sender_uuid',"
                . "damage=0,"
                . "amount=0,"
                . "meta='',"
                . "item_name='',"
                . "date=NOW "
            $condition = "WHERE id=$id ";
            $limit = 'LIMIT 1';
            
            $sql = $action . $details . $condition . $limit;
            
            //$sql = "UPDATE minecraft_iconomy.`deposit` SET `amount`=amount+'$amount' WHERE `id`={$row['id']} LIMIT 1;";
            umc_log('shop', 'deposit_adjust', "Cleared all content from deposit for ID $id by withdrawing {$amount_row['amount']}");
        } else {
            $sql = "UPDATE minecraft_iconomy.deposit SET amount=$newstock WHERE id='$id';";
            umc_log('shop', 'deposit_adjust', "Changed deposit level for ID $id from {$amount_row['amount']} to $newstock");
        }
    }
    umc_mysql_query($sql,true);

    // check stock levels

    $sql = "SELECT * FROM minecraft_iconomy.$table WHERE id=$id;";
    $D2 = umc_mysql_fetch_all($sql);
    if (count($D2)) {
        return $D2[0]['amount'];
    } else {
        return 0;
    }
}

function umc_get_meta_txt($meta_arr, $size = 'long') {
    global $ENCH_ITEMS, $UMC_BANNERS;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $out = '';
    $e = 0;
    if (!is_array($meta_arr)) {
        $meta_arr = unserialize($meta_arr);
    }
    // faulty arrays
    if ((!is_array($meta_arr)) && (strlen($meta_arr) > 1)) {
        XMPP_ERROR_trigger("error unserializing metadata array: " . var_export($meta_arr, true));
        return "error unserializing metadata array";
    } else if (!is_array($meta_arr)) {
        return;
    }
    foreach ($meta_arr as $meta_name => $lvl) {
        if (isset($ENCH_ITEMS[$meta_name])) {
            if ($size == 'long') {
                $meta_name = $ENCH_ITEMS[$meta_name]['name'];
            } else {
                $meta_name = $ENCH_ITEMS[$meta_name]['short'];
            }
            $out .= "$meta_name $lvl";
        } else if (isset($UMC_BANNERS['colors'][$meta_name])) {
            $out .= count($lvl) . "-Layered";
        } else { // some enchantments are stored wrong, with lowercase names instead of codes
            // this should not be needed anymore once there are no lowercase enchantments in the deposit
            foreach ($ENCH_ITEMS as $code => $data) {
                if (strtolower($data['name']) == $meta_name) {
                    if ($size == 'long') {
                        $meta_name = $ENCH_ITEMS[$code]['name'];
                    } else {
                        $meta_name = $ENCH_ITEMS[$code]['short'];
                    }
                    break;
                }
            }
            $out .= "$meta_name $lvl";
        }
        if (($e + 1) < count($meta_arr)) {
            $out .= ", ";
        }
        $e++;
    }
    return $out;
}

/**
 * returns full name of an item, depending on the environment
 * @param string $item_name
 * @param int $item_data
 * @param string $meta
 */
function umc_goods_get_text($item_name, $item_data = 0, $meta = '') {
    
    global $UMC_DATA, $UMC_ENV, $UMC_PATH_MC, $UMC_DOMAIN, $UMC_DATA_ID2NAME;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    // cast part capitalized text to lowercase.
    $item_name = strtolower($item_name);

    // just to deal with legacy item id
    if (is_numeric($item_name)) {
        $item_name = $UMC_DATA_ID2NAME[$item_name];
        // conversion failed, item does not exist
        if (!$item_name) {
            XMPP_ERROR_trigger("Could not identify $item_name from $item_data: DATA umc_goods_get_text");
            return false;
        }
    }
    
    // if item name is not set at all
    if (!isset($UMC_DATA[$item_name])) {
        XMPP_ERROR_trigger("Could not identify $item_name as STRING umc_goods_get_text");
        return false;
    } else {
        $item_arr = $UMC_DATA[$item_name];
    }

    // calculate the damage
    $damage_text = '';
    $damage_spacer = '';
    $mc_name = $item_name;
    $icon_ext = strtolower(pathinfo($item_arr['icon_url'], PATHINFO_EXTENSION));
    
    if (isset($item_arr['damage'])) {
        $damage = umc_goods_damage_calc($item_data, $item_arr['damage']);
        if ($damage) {
            $damage_spacer = ' ';
            $damage_text = "$damage% dmgd";
        }
    } else if (isset($item_arr['subtypes'][$item_data])) { // we might have a subtype
        $mc_name = $item_arr['subtypes'][$item_data]['name'];
        $icon_ext = strtolower(pathinfo($item_arr['subtypes'][$item_data]['icon_url'], PATHINFO_EXTENSION));
    }
    
    $nice_name = umc_pretty_name($mc_name);

    $icon_file = "icons/$mc_name.$icon_ext";
    $icon_path = "$UMC_PATH_MC/server/bin/data/";

    $meta_text = '';
    $meta_spacer = '';
    if ($meta != '') {
        $meta_text = umc_get_meta_txt($meta, 'short');
        $meta_spacer = ' ';
    }

    $full_clean = trim("$nice_name$meta_text$damage_text");
    if ($UMC_ENV == 'wordpress' && file_exists($icon_path . $icon_file)) {
        $img = "<img width=\"24\" src=\"$UMC_DOMAIN/websend/$icon_file\" alt=\"$nice_name\">";
        $full = "$img $full_clean";
    } else if ($UMC_ENV == 'websend') {
        $full = "{magenta}$meta_text$meta_spacer{green}$nice_name{red}$damage_spacer$damage_text{white}";
        $img = '';
    } else {
        $full = "$nice_name$meta_spacer$meta_text$damage_spacer$damage_text";
        $img = '';
    }
    if (isset($UMC_DATA[$item_name]['group'])) {
        $group = umc_pretty_name($UMC_DATA[$item_name]['group']);
    } else {
        $group = false;
    }

    $out = array(
        'full' => $full,
        'item_id' => $UMC_DATA[$item_name]['id'],
        'type' => $item_data,
        'full_clean' => $full_clean,
        'meta' => $meta_text,
        'icon' => $img,
        'name' => $nice_name,
        'item_name' => $item_name,
        'dmg' => $damage_text,
        'group' => $group,
    );
    return $out;
}

/**
 * Get the damage value from the item data and max damage
 *
 * @param type $item_data
 * @param type $max_damage
 * @return type
 */
function umc_goods_damage_calc($item_data, $max_damage) {
    $damage = 0;
    if (($max_damage > 0) && ($item_data > 0)) {
        $percent = ($item_data/$max_damage) * 100;
        $damage = round($percent, 2);
    } else if (($max_damage > 0) && ($item_data < 0)) {
        $percent = (abs($max_damage + $item_data) / $max_damage) * 100;
        $damage = round($percent, 2);
    }
    if ($damage == 0) {
        return false;
    } else {
        return $damage;
    }
}


/**
 * Records a transaction in the database.
 *
 * @param type $from
 * @param type $to
 * @param type $amount
 * @param type $value
 * @param type $item
 * @param type $type
 * @param type $meta
 */
function umc_shop_transaction_record($from, $to, $amount, $value, $item, $type = 0, $meta = '') {
    global $UMC_DATA_ID2NAME;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    // make sure we have UUIDs
    $from_uuid = umc_uuid_getone($from, 'uuid');
    $to_uuid = umc_uuid_getone($to, 'uuid');

    // make sure we have item names
    if (is_numeric($item)) {
        $item_name = $UMC_DATA_ID2NAME[$item];
    } else {
        $item_name = $item;
    }

    $ins_sql = "INSERT INTO minecraft_iconomy.`transactions` (`damage`, `buyer_uuid`, `seller_uuid`, `item_name`, `cost`, `amount`, `meta`)
        VALUES ('$type', '$to_uuid', '$from_uuid', '$item_name', '$value', '$amount', '$meta');";
    umc_mysql_query($ins_sql, true);
}
