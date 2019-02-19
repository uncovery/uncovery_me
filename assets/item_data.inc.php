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
 * This provides a list of items for conversion between the different standards
 * (spigot, minecraft, different plugins, historical names etc) and a hierarchy of
 * item names for the shop and other functions as well as some functions to manage them.
 */
global $UMC_FUNCTIONS;
$UMC_FUNCTIONS['get_icons'] = 'umc_get_icons';

function umc_item_data_create() {
    $files = array(
        'items' => 'items.json',
        'blocks' => 'blocks.json',
    );

    $versions = array(
        0 => array('target' => '112', 'source' => '1.12'),
        1 => array('target' => '113', 'source' => '1.13'),
        2 => array('target' => '1132', 'source' => '1.13.2'),
    );

    // versions iterations
    foreach ($versions as $V) {
        $path = "/home/minecraft/server/mc_assets/minecraft-data/data/pc/{$V['source']}/";
        $array_data = array();

        // we iterates blocks & items
        foreach ($files as $type => $filename) {
            $file_contents = file_get_contents($path . $filename);
            $data = json_decode($file_contents);
            foreach ($data as $obj) {
                $name = strtolower($obj->name);
                // we do not re-add blocks for which we have items already
                // this needs to be improved since the block data stacksize is more accurate than the items
                if (!isset($array_data[$name])) {
                    $array_data[$name] = array(
                        'stack' => $obj->stackSize,
                        'id' => $obj->id,
                        'display_name' => $obj->displayName,
                    );

                    // variations are only pre 1.13
                    if (isset($obj->variations)) {
                        $array_data[$name]['variations'] = array();
                        foreach ($obj->variations as $var) {
                            $var_id = $var->metadata;
                            $array_data[$name]['variations'][$var_id] = $var->displayName;
                        }
                    }
                }
            }
        }

        ksort($array_data);
        umc_array2file($array_data, "UMC_DATA_{$V['target']}", "/home/minecraft/server/bin/assets/item_details_{$V['target']}.inc.php");
    }
}

/**
 * this checks for broken items and fixes if possible.
 *
 * @global boolean $BROKEN_ITEMS
 * @param type $item_name
 */
function umc_broken_items_add_fix($item_name) {
    global $BROKEN_ITEMS;

    // check if we know about this one already
    if (!isset($BROKEN_ITEMS[$item_name])) {
        // we don't, add it to the list of items
        ksort($BROKEN_ITEMS);
        XMPP_ERROR_trigger("Could not identify $item_name as STRING umc_goods_get_text, added to broken items list, please add correct value!");
        $BROKEN_ITEMS[$item_name] = false;
        umc_array2file($BROKEN_ITEMS, "BROKEN_ITEMS", "/home/minecraft/server/bin/assets/broken_items.inc.php");
    } else {
        // we know about this already, let's check if we can find this in the DB?
        // just to make sure
        $check = umc_itemdata_databasecheck($item_name);
        if (!$check) {
            XMPP_ERROR_trigger("Could not identify $item_name as STRING umc_goods_get_text, it's already in the broken items list but NOT in the database????");
        } else {
            // ok, now let's try to fix it
            XMPP_ERROR_trigger("Could not identify $item_name as STRING umc_goods_get_text, it's already in the broken items list so we try adn fix it...");
            umc_item_fix_old($item_name, $BROKEN_ITEMS[$item_name]);
        }
    }
}

/**
 * fix old item names in tables
 */
function umc_item_fix_old($search, $replace) {
    $tables = array(
        'minecraft_iconomy.transactions',
        'minecraft_iconomy.stock',
        'minecraft_iconomy.request',
        'minecraft_iconomy.deposit',
    );

    foreach ($tables as $table) {
        $update_sql = "UPDATE $table SET `item_name` = REPLACE(`item_name`, $search, $replace) WHERE `item_name` LIKE '$search';";
        XMPP_ERROR_send_msg($update_sql);
        // $X = umc_mysql_query($update_sql);
    }
}


/**
 * check if an item exists anywhere in the database
 *
 * @param type $name
 * @param type $id
 * @return boolean
 */
function umc_itemdata_databasecheck($name, $id = false) {
    //check invalid items if they actually exist
    $tables = array(
        'minecraft_iconomy.transactions',
        'minecraft_iconomy.stock',
        'minecraft_iconomy.request',
        'minecraft_iconomy.deposit',
    );

    $damage_check = '';
    if ($id) {
        $damage_check = " AND `damage` = $id";
    }

    foreach ($tables as $table) {
        $sql = "SELECT * FROM $table WHERE `item_name` = '$name'$damage_check";
        $D = umc_mysql_fetch_all($sql);
        if (count($D) > 0) {
            return true;
        }
    }
    return false;
}

function umc_item_data_versionmatch() {
    $old_version = "/home/minecraft/server/bin/assets/item_details_112.inc.php";
    $new_version = "/home/minecraft/server/bin/assets/item_details_113.inc.php";

    global $UMC_DATA_112;
    global $UMC_DATA_113;

    include_once($old_version);
    include_once($new_version);

    // create a list of the new items based on display names
    $name_arr_113 = array();
    foreach ($UMC_DATA_113 as $name => $D) {
        $dis_name = $D['display_name'];
        $name_arr_113[$dis_name] = $name;
    }

    $invalid = array();

    // iterate the old items and see if we can find them in the new list
    $name_translate = array();
    foreach ($UMC_DATA_112 as $name => $D) {
        $dis_name = $D['display_name'];
        $dis_name = str_replace("Wood ", "", $dis_name);
        $dis_name = str_replace("Chain ", "Chainmail ", $dis_name);
        if (isset($D['variations'])) {
            foreach ($D['variations'] as $type_id => $var_display_name) {
                // remove all the "Wood" for better matching
                $var_display_name = str_replace("Wood ", "", $var_display_name);
                $var_display_name = str_replace("Chain ", "Chainmail ", $var_display_name);
                if (umc_itemdata_databasecheck($name, $type_id)) {
                    if (isset($name_arr_113[$var_display_name])) {
                        if ($name != $name_arr_113[$var_display_name]) {
                            $name_translate[$name][$type_id] = $name_arr_113[$var_display_name];
                        }
                    } else {
                        $invalid[$name][$type_id] = $var_display_name;
                    }
                }
            }
        } else {
            if (umc_itemdata_databasecheck($name, 0)) {
                if (isset($name_arr_113[$dis_name])) {
                    if ($name != $name_arr_113[$dis_name]) {
                        $name_translate[$name][0] = $name_arr_113[$dis_name];
                    }
                } else {
                    $invalid[$name][0] = $dis_name;
                }
            }
        }
    }

    $count = count($invalid);
    $name_translate['invalid'] = $invalid;
    umc_array2file($name_translate, 'UMC_DATA_tranlation', "/home/minecraft/server/bin/assets/item_details_translation.inc.php");
    echo "$count invalid items found";
}


function umc_item_search_create() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    // this here creates a new items array file
    $search_arr = umc_item_data_get_namelist();
    if (($handle = fopen("/home/minecraft/server/bukkit/plugins/Essentials/items.csv", "r")) !== FALSE) {
        while (($items = fgetcsv($handle, 10000, ",")) !== FALSE) {
            XMPP_ERROR_trace("Reading Essentials CSV");
            // get the fist letter to weed out comments
            $firstletter = substr($items[0], 0, 1);
            if (count($items) == 3 && $firstletter !== '#' && !isset($search_arr[$items[0]])) {
                // we get the numeric ID from the list
                // csv format is:
                // rock,1,0
                // item_name, num_id, type_id
                $item = umc_goods_get_text($items[1], $items[2]);
                if ($item) { // the file contains a bunch of unobtainable stuff, we skip that
                    $search_arr[$items[0]] = array('item_name' => $item['item_name'], 'type' => $item['type']);
                }
            }
        }
        umc_array2file($search_arr, 'ITEM_SEARCH', '/home/minecraft/server/bin/assets/item_search.inc.php');
    } else {
        die("Could not read items file!");
    }
}


/**
 * returns the list of available names with their main item name and the type number
 *
 * @global array $UMC_DATA
 * @return type
 */
function umc_item_data_get_namelist() {
    global $UMC_DATA;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $out = array();
    foreach ($UMC_DATA as $item_name => $data) {
        $out[$item_name] = array('item_name' => $item_name, 'type' => 0);
        if (isset($data['subtypes'])) {
            foreach ($data['subtypes'] as $sub_id => $subtype) {
                if (!isset($out[$subtype['name']])) {
                    $out[$subtype['name']] = array('item_name' => $item_name, 'type' => $sub_id);
                }
            }
        }
    }
    return $out;
}

function umc_item_data_id2namelist() {
    global $UMC_DATA;
    $D = array();
    foreach ($UMC_DATA as $item_name => $data) {
        $id = $data['id'];
        $D[$id] = $item_name;
    }
    ksort($D);
    umc_array2file($D, 'UMC_DATA_ID2NAME', __DIR__ . "/item_id2name.inc.php");
}