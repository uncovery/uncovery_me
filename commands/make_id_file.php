<?php
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