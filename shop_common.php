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
 * Does not do anything with the item
 *
 * @param type $table
 * @param type $id
 * @param type $amount
 * @param type $player
 * @return int
 */
function umc_db_take_item($table, $id, $amount, $player) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    // $uuid = umc_uuid_getone($player, 'uuid');
    if ($table == 'stock') {
        $D = umc_mysql_fetch_all("SELECT amount FROM minecraft_iconomy.$table WHERE id='$id';");
        $amount_row = $D[0];
        $newstock = $amount_row['amount'] - $amount;
        if ($newstock == 0) {
            $sql = "DELETE FROM minecraft_iconomy.stock WHERE id='$id';";
            umc_log('shop', 'stock_adjust', "Cleared all content from stock for ID $id by withdrawing {$amount_row['amount']}");
        } else {
            $sql = "UPDATE minecraft_iconomy.stock SET amount=$newstock WHERE id='$id';";
            umc_log('shop', 'stock_adjust', "Changed stock level for ID $id from {$amount_row['amount']} to $newstock");
        }
    } else { // take from deposit
        $D = umc_mysql_fetch_all("SELECT amount, sender_uuid FROM minecraft_iconomy.$table WHERE id='$id';");
        $amount_row = $D[0];
        $newstock = $amount_row['amount'] - $amount;
        if ($newstock == 0) {
            $sid = $amount_row['sender_uuid'];
            // $sql = "DELETE FROM minecraft_iconomy.deposit WHERE id='$id';";
            // if not a player to player transaction
            if ($sid !== 'reusable-0000-0000-0000-000000000000' && strpos($sid, '-0000-0000-000000000000')) {
                $sql = "DELETE FROM minecraft_iconomy.deposit WHERE id='$id';";
            } else {
                $sql = "UPDATE minecraft_iconomy.deposit
                    SET sender_uuid='reusable-0000-0000-0000-000000000000', damage=0, amount=0, meta='', item_name='', date=NOW()
                    WHERE id='$id' LIMIT 1";
                //$sql = "UPDATE minecraft_iconomy.`deposit` SET `amount`=amount+'$amount' WHERE `id`={$row['id']} LIMIT 1;";
                umc_log('shop', 'deposit_adjust', "Cleared all content from deposit for ID $id by withdrawing {$amount_row['amount']}");
            }
        } else {
            $sql = "UPDATE minecraft_iconomy.deposit SET amount=$newstock WHERE id='$id';";
            umc_log('shop', 'deposit_adjust', "Changed deposit level for ID $id from {$amount_row['amount']} to $newstock");
        }
    }

    umc_mysql_execute_query($sql);

    // check stock levels

    $sql = "SELECT * FROM minecraft_iconomy.$table WHERE id=$id;";
    $D2 = umc_mysql_fetch_all($sql);
    if (count($D2)) {
        return $D2[0]['amount'];
    } else {
        return 0;
    }
}

/**
 * convert Meta data into nice text
 * TODO: This is deprecated and nbt data should override this.
 *
 * @global type $ENCH_ITEMS
 * @param type $meta_arr
 * @param type $size
 * @return string
 */
function umc_get_meta_txt($meta_arr, $size = 'long') {
    global $ENCH_ITEMS;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $out = '';
    $e = 0;
    if (strpos($meta_arr, "{") === 0) { // we have NBT, not legacy meta data
        $out = umc_nbt_display($meta_arr, $size . '_text');
        return $out;
    } else if (!is_array($meta_arr)) {
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
 * @param string $item_name_raw
 * @param int $item_data
 * @param string $meta
 */
function umc_goods_get_text($item_name_raw, $item_data = 0, $meta = '') {
    global $UMC_DATA, $UMC_ENV, $UMC_DATA_ID2NAME;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    // check if we have "minecraft:" in the beginning.
    $check = strpos($item_name_raw, ':');
    if ($check > 0) {
        $item_name_raw = substr($item_name_raw, 10);
    }

    // cast part capitalized text to lowercase.
    $item_name = strtolower($item_name_raw);

    // just to deal with legacy item id
    if (is_numeric($item_name)) {
        if (isset($UMC_DATA_ID2NAME[$item_name])) {
            $item_name = $UMC_DATA_ID2NAME[$item_name];
        } else {
            return false;
        }
    }

    // if item name is not set at all
    if (!isset($UMC_DATA[$item_name])) {
        XMPP_ERROR_trigger("Could not identify $item_name as STRING umc_goods_get_text");
        $UMC_DATA[$item_name] = array('stack' => 64, 'avail' => true);
    } 
    $item_arr = $UMC_DATA[$item_name];


    // calculate the damage
    $damage_text = '';
    $damage_spacer = '';
    $mc_name = $item_name;

    $damage = false;
    if (isset($item_arr['damage'])) {
        $damage = umc_goods_damage_calc($item_data, $item_arr['damage']);
        if ($damage) {
            $damage_spacer = ' ';
            $damage_text = "$damage% dmgd";
        }
    } else if (isset($item_arr['subtypes'][$item_data])) { // we might have a subtype
        $mc_name = $item_arr['subtypes'][$item_data]['name'];
    }

    $nice_name = umc_pretty_name($mc_name);

    $meta_text = '';
    $nbt_string = '';
    $nbt_raw = '';
    $meta_spacer = '';
    if ($meta != '') {
        $tmp_var = umc_get_meta_txt($meta, 'short');
        $meta_spacer = ' ';
        // differentiate between meta and nbt
        if (strpos($meta, "{") === 0) { // we have nbt
            $nbt_string = " " . $tmp_var;
            $nbt_raw = $meta;
        } else {
            $meta_text = $tmp_var;
        }
    }

    $full_clean = trim("$nice_name$meta_text$nbt_string$damage_spacer$damage_text");
    if ($UMC_ENV == 'wordpress') {
        global $ITEM_SPRITES;
        if (isset($ITEM_SPRITES[$item_name])) { // get background image of single image
            if ($damage) {
                $item_data = 'damaged';
            }
            $img = umc_item_data_icon_html($item_name, $item_data) ;
        } else {
            $img = umc_item_data_icon_html('invalid', 'unknown') . "?";
        }
        $full = "$img $full_clean";
    } else if ($UMC_ENV == 'websend') {
        $full = "{green}$nice_name{magenta}$meta_text$nbt_string$meta_spacer{red}$damage_spacer$damage_text{white}";
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

    if (isset($UMC_DATA[$item_name]['notrade'])) {
        $notrade = $UMC_DATA[$item_name]['notrade'];
    } else {
        $notrade = false;
    }

    $out = array(
        'full' => $full,
        'full_nocolor' => "$nice_name$meta_spacer$meta_text$damage_spacer$damage_text",
        'type' => $item_data,
        'full_clean' => $full_clean,
        'meta' => $meta_text,
        'icon' => $img,
        'name' => $nice_name,
        'item_name' => $item_name,
        'dmg' => $damage_text,
        'group' => $group,
        'notrade' => $notrade,
        'nbt_raw' => $nbt_raw,
        'nbt_text' => $nbt_string,
    );
    XMPP_ERROR_trace('get_text', $out);

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
        XMPP_ERROR_trigger('UMC_DATA_ID2NAME USAGE');
        $item_name = $UMC_DATA_ID2NAME[$item];
    } else {
        $item_name = $item;
    }

    $meta_sql = umc_mysql_real_escape_string($meta);
    $ins_sql = "INSERT INTO minecraft_iconomy.`transactions` (`damage`, `buyer_uuid`, `seller_uuid`, `item_name`, `cost`, `amount`, `meta`)
        VALUES ('$type', '$to_uuid', '$from_uuid', '$item_name', '$value', '$amount', $meta_sql);";
    umc_mysql_query($ins_sql, true);
}
