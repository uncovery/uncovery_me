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
 * This file creates a static array from the Essentials/items.csv file.
 * The items.csv file contains a dictionary of possible item names and their 
 * actual item ID and type ID. This is used for the /search command and the 
 * sanitization function in core_include.php. It needs to be run whenever the 
 * Essential plugin gets updated.
 */


require_once('/home/minecraft/server/bin/core_include.php');

// this here creates a new items array file
$search_arr = umc_item_data_get_namelist();
if (($handle = fopen("/home/minecraft/server/bukkit/plugins/Essentials/items.csv", "r")) !== FALSE) {
    while (($items = fgetcsv($handle, 10000, ",")) !== FALSE) {
        $firstletter = substr($items[0], 0, 1);
        if (count($items) == 3 && $firstletter !== '#' && !isset($search_arr[$items[0]])) {
            $item = umc_goods_get_text($items[1], $items[2]);
            $search_arr[$items[0]] = array('item_name' => $item['item_name'], 'type' => $item['type']);
        }
    }
    umc_array2file($search_arr, 'ITEM_SEARCH', '/home/minecraft/server/bin/includes/item_search.inc.php');
} else {
    die("Could not read items file!");
}