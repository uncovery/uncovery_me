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
    $version = '1.13';
    $path = "/home/minecraft/server/mc_assets/minecraft-data/data/pc/$version/";
    $files = array(
    //     'blocks.json',
        'items.json',
    );

    $array_data = array();
    foreach ($files as $filename) {
        $file_contents = file_get_contents($path . $filename);
        $data = json_decode($file_contents);
        foreach ($data as $obj) {
            $name = $obj->name;
            if (!isset($array_data[$name])) {
                $array_data[$name] = array(
                    'stack' => $obj->stackSize,
                );
            }
        }
    }
    umc_array2file($array_data, 'UMC_DATA', '/home/minecraft/server/bin/assets/item_details.inc.php');
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
 * This downloads all icons from Minecraft Wiki and stores it on the website
 *
 */
function umc_get_icons() {
    global $UMC_DATA, $UMC_PATH_MC;
    $base_url = 'http://hydra-media.cursecdn.com/minecraft.gamepedia.com';
    $base_path = "$UMC_PATH_MC/server/bin/data/icons/";

    $img_arr = array();
    foreach ($UMC_DATA as $item => $D) {
        if (isset($D['subtypes'])) {
            foreach ($D['subtypes'] as $id => $S) {
                if ($S['icon_url'] != '?') {
                    $img_arr[$S['name']] = $base_url . $S['icon_url'];
                }
            }
        }
        if ($D['icon_url'] === '?') {
            continue;
        } else {
            $img_arr[$item] = $base_url . $D['icon_url'];
        }
    }
    // pass all arrays to mass-downloader
    $complete_count = count($img_arr);
    $D = unc_serial_curl($img_arr);

    $failed_icons = array();
    foreach ($D as $img => $R) {
        if ($R['response']['http_code'] !== 200) {
            $failed_icons[] = array(
                'img' => $img,
                'url' => $R['response']['url'],
                'reason' => "failed to get file from source",
            );
        } else {
            // assemble target path
            $full_url = $R['response']['url'];
            $path_info = pathinfo($full_url);
            if (!isset($path_info['extension'])) {
                XMPP_ERROR_trace("Extension missning for $img", $full_url);
            }
            $ext = $path_info['extension'];
            $target_path = $base_path . "$img.$ext";
            // write target file
            $written = file_put_contents($target_path, $R['content']);
            if (!$written) {
                $failed_icons[] = array(
                    'img' => $img,
                    'url' => $R['response']['url'],
                    'reason' => 'failed to write file to $target_path',
                );
            }
        }
    }
    $count = count($failed_icons);
    if ($count > 0) {
        XMPP_ERROR_trace("failed users:", $failed_icons);
        XMPP_ERROR_trigger("Failed to get $count of $complete_count Block icons, see error report for details");
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

/**
 * get the full HTML of the icon of an item/block to be displayed in-line
 * in HTML
 *
 * @param type $item_name
 * @param type $sub_type
 */
function umc_item_data_icon_html($item_name, $sub_type = false) {
    $html = "<span class=\"item_sprite item_{$item_name}_{$sub_type}\"> </span>";
    return $html;
}


/**
 * Getting the data from the google spreadsheets
 *
 * @return type
 */
function umc_item_data_icon_getdata() {
    global $UMC_DATA;
    // google API settings:
    // https://console.developers.google.com/iam-admin/serviceaccounts/project?project=plucky-sight-167212&organizationId=0

    // used code:
    // https://github.com/juampynr/google-spreadsheet-reader

    require '/home/includes/google_api/vendor/autoload.php';
    $service_account_file = '/home/includes/google_api/google_auth.json';
    $spreadsheet_id = '1b3M2EPGzNFtMp-hW9Sam5ETQg2eTuBEg1S1WArhwJKY';
    $spreadsheet_range = 'Entry!B2:AG51';

    putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $service_account_file);
    $client = new Google_Client();
    $client->useApplicationDefaultCredentials();
    $client->addScope(Google_Service_Sheets::SPREADSHEETS_READONLY);
    $service = new Google_Service_Sheets($client);

    $result = $service->spreadsheets_values->get($spreadsheet_id, $spreadsheet_range);

    $data = $result->getValues();
    echo "processing " . count($data) . " datasets for itemsprites";

    $final_data = array();

    $icon_size = 32;
    $image_width = 1024;
    $scale = 0.75;

    $background_size_x = $image_width * $scale;
    $img_size = $icon_size * $scale;

    // item sprite css header
    $css = ".item_sprite {display: inline-block; background-size: {$background_size_x}px; background-image: url(/admin/img/InvSprite.png); "
        . "background-repeat: no-repeat; width:{$img_size}px; height:{$img_size}px;}\n"
        . ".item_golden_apple_1 {background-position:-24px -624px;}\n"; // the enchanted golden apple is the same as the normal one but not twice in the table
    foreach ($data as $row => $L) {
        foreach ($L as $line => $name) {
            $name_type = 0;
            $coords = array('x' => $line, 'y' => $row);

            if ($name == '' || strstr($name, " ") || strstr($name, ".")) {
                continue;
            } else if (strstr($name, ":")) {
                $name_data = explode(":", $name);
                $item_name = $name_data[0];
                $name_type = $name_data[1];
                if (!isset($UMC_DATA[$item_name])) {
                    continue;
                }
                $final_data[$item_name][$name_type] = $coords;
            } else {
                if (!isset($UMC_DATA[$name])) {
                    continue;
                }
                $item_name = $name;
                $final_data[$item_name]['coords'] = $coords;
            }
            $x = $line * $scale * $icon_size;
            $y = $row * $scale * $icon_size;
            $css .=  ".item_{$item_name}_{$name_type} {background-position:-{$x}px -{$y}px;}\n";
        }
    }

    ksort($final_data);
    //$final_data['invalid'] = $invalid_data;

    umc_array2file($final_data, 'item_sprites', '/home/minecraft/server/bin/assets/item_sprites.inc.php');

    //TODO: Download latest version of this file:
    // http://hydra-media.cursecdn.com/minecraft.gamepedia.com/4/44/InvSprite.png
    //wiki page here: http://minecraft.gamepedia.com/File:InvSprite.png

    $source_file = 'https://d1u5p3l4wpay3k.cloudfront.net/minecraft_gamepedia/4/44/InvSprite.png';
    $target_directory = '/home/minecraft/server/bin/data/images';
    // $R = unc_serial_curl($source_file);
    // file_put_contents($target_directory . "/InvSprite.png", $R[0]['content']);

    // write CSS to file
    $css_file = '/home/minecraft/server/bin/data/item_sprites.css';
    file_put_contents($css_file, $css);
}

/**
 * get the invSprote data from the Wiki
 * and adds it to the table minecraft_srvr.items
 */
function umc_item_data_wiki() {
    global $UMC_DATA_ITEM2WIKI, $UMC_CONFIG;

    // STEP 1: get the whole website data
    $url = 'https://minecraft.gamepedia.com/Module:InvSprite';
    $url_data = unc_serial_curl($url,0,50, $UMC_CONFIG['ssl_cert']);

    // STEP 2: filter out only the LUA part
    $matches = false;
    $regex = '/.*(return {[\S\s]*)<\/pre>/';
    preg_match($regex, $url_data[0]['content'], $matches);

    $searches = array(
        0 => '/&quot;/', // replace HTML entities (could be done with htmlentities?
        1 => '/url = require.*,/', // filter out some requirements for the wiki
        2 => '/&amp/', // replace HTML entities (could be done with htmlentities?
    );
    $replacement = array(
        0 => '"',
        1 => '',
        2 => '&',
    );
    $fixed = preg_replace($searches, $replacement, $matches[1]);

    // STEP 3: parse the LUA
    $file = new Lua();
    $output = $file->eval($fixed);

    // STEP 4: Get the important parts of the array
    $ids = $output['ids'];
    $raw_sections = $output['sections'];

    // STEP 5 filter out bad sections
    $sections = array(); // make array with sections so we can match out the bad ones
    foreach ($raw_sections as $S) {
        $s_id = $S['id'];
        $sections[$s_id] = $S['name'];
    }

    $invalid_sections = array(
        'April Fools',
        'Outdated',
        "Bedrock Edition & Education Edition"
    );

    // STEP 6 add the sprite locations to the MySQL table where we have a perfect match
    foreach ($ids as $item_text => $I) {
        $section_id = $I['section'];
        $section_name = $sections[$section_id];
        if (!in_array($section_name, $invalid_sections)) {
            $item_name = strtolower(str_replace(" ", "_", $item_text));
            $item_safe = umc_mysql_real_escape_string($item_name);
            $sql = "UPDATE minecraft_srvr.items SET sprite_location = {$I['pos']} WHERE item_name LIKE $item_safe;";
            umc_mysql_execute_query($sql);
        }
    }

    // STEP 7 take the ones without a perfect match & add them to the file array where we don't have a stored fix in the file
    $sql = "SELECT item_name FROM minecraft_srvr.items WHERE sprite_location = 0;";
    $D = umc_mysql_fetch_all($sql);
    $count = 0;

    // just in case this file is generated for the first time...
    if (!isset($UMC_DATA_ITEM2WIKI)) {
        $UMC_DATA_ITEM2WIKI = array();
    }

    foreach ($D as $d) {
        $name = $d['item_name'];
        // this is for items that don't exist in the array yet, that are new in the LUA file online
        if (!isset($UMC_DATA_ITEM2WIKI[$name])) {
            $UMC_DATA_ITEM2WIKI[$name] = '';
        }

        // this is for the items that might be new or old, but they don't have a translation yet
        if ($UMC_DATA_ITEM2WIKI[$name] == '') {
            $count ++;
        }
    }

    // STEP 8 Message the admin that there are unmatched item names that need to be filled in manually
    if ($count > 0) {
        XMPP_ERROR_trigger("Found $count undefined items in Inv_sprite on the minecraft wiki! Please fill the empty ones in item_wiki2item.inc.php!");
    }

    $comments = "This file is needed to translate item names where the InvSprites name in this URL
https://minecraft.gamepedia.com/Module:InvSprite
does not matach the actual item name. The file will automatically expanded when there are new item names found in minecraft with empty array values.
Please look for empty array values and match their key with above URL. Once you fill those in, they will stay in the file.
Syntax is item_name => wiki_name";
    umc_array2file($UMC_DATA_ITEM2WIKI, 'UMC_DATA_ITEM2WIKI', '/home/minecraft/server/bin/assets/item_item2wiki.inc.php', $comments);
}

/**
 * fix old item names in tables
 */
function umc_item_fix_old() {
    global $UMC_DATA_SPIGOT2ITEM, $UMC_DATA;

    $tables = array(
        'minecraft_iconomy.transactions',
        'minecraft_iconomy.stock',
        'minecraft_iconomy.request',
        'minecraft_iconomy.deposit',
    );

    foreach ($UMC_DATA_SPIGOT2ITEM as $old_name => $new_name) {
        foreach ($tables as $table) {
            $sql = "UPDATE $table SET `item_name` = '$new_name' WHERE `item_name` LIKE '$old_name';";
            umc_mysql_execute_query($sql);
        }
    }
    
    foreach ($UMC_DATA as $old_name => $new_data) {
        if (isset($new_data['subtypes'])) {
            foreach ($tables as $table) {
                $sub_types = $new_data['subtypes'];
                foreach ($sub_types as $old_id => $sub_data) {
                    $item_name = $sub_data['name'];
                    $update_sql = "UPDATE $table SET `item_name` = '$item_name' WHERE `item_name` = '$old_name' AND `damage` = $old_id;";
                    $sql = "SELECT * FROM $table WHERE `item_name` = '$old_name' AND `damage` = $old_id";
                    $D = umc_mysql_fetch_all($sql);
                    if (count($D) > 0) {
                        umc_mysql_execute_query($update_sql);
                    }
                }
            } 
        }
    }

}

$UMC_DATA_SPIGOT2ITEM = array(
    'acacia_door_item' => 'acacia_door',
    'birch_door_item' => 'birch_door',
    'birch_wood_stairs' => 'birch_stairs',
    'boat_acacia' => 'acacia_boat',
    'boat_birch' => 'birch_boat',
    'boat_dark_oak' => 'dark_oak_boat',
    'boat_jungle' => 'jungle_boat',
    'boat_spruce' => 'spruce_boat',
    'book_and_quill' => 'writable_book',
    'brewing_stand_item' => 'brewingstand',
    'brick' => 'brick_block',
    'carrot_item' => 'carrot',
    'carrot_stick' => 'carrot_on_a_stick',
    'cauldron_item' => 'cauldron',
    'clay_balls' => 'clay_ball',
    'clay_block' => 'clay',
    'clay_brick' => 'brick',
    'clayblock' => 'clay',
    'cobble_wall' => 'cobblestone_wall',
    'cobblestone_stairs' => 'stone_stairs',
    'dark_oak_door_item' => 'dark_oak_door',
    'dead_bush' => 'deadbush',
    'diamond_barding' => 'diamond_horse_armor',
    'diamond_pick' => 'diamond_pickaxe',
    'diamond_spade' => 'diamond_shovel',
    'diode' => 'repeater',
    'dragons_breath' => 'dragon_breath',
    'empty_map' => 'map',
    'enchantment_table' => 'enchanting_table',
    'ender_portal_frame' => 'end_portal_frame',
    'ender_stone' => 'end_stone',
    'exp_bottle' => 'experience_bottle',
    'eye_of_ender' => 'ender_eye',
    'fireball' => 'fire_charge',
    'firework' => 'fireworks',
    'flower_pot_item' => 'flower_pot',
    'gold_axe' => 'golden_axe',
    'gold_barding' => 'golden_horse_armor',
    'gold_boots' => 'golden_boots',
    'gold_chestplate' => 'golden_chestplate',
    'gold_helmet' => 'golden_helmet',
    'gold_hoe' => 'golden_hoe',
    'gold_leggings' => 'golden_leggings',
    'gold_pickaxe' => 'golden_pickaxe',
    'gold_plate' => 'light_weighted_pressure_plate',
    'gold_record' => 'record_13',
    'gold_spade' => 'golden_shovel',
    'gold_sword' => 'golden_sword',
    'green_record' => 'record_cat',
    'grilled_pork' => 'cooked_porkchop',
    'grilled_pork' => 'cooked_porkchop',
    'hard_clay' => 'hardened_clay',
    'huge_mushroom_1' => 'brown_mushroom_block',
    'huge_mushroom_2' => 'red_mushroom_block',
    'ink_sack' => 'dye',
    'iron_barding' => 'iron_horse_armor',
    'iron_fence' => 'iron_bars',
    'iron_plate' => 'heavy_weighted_pressure_plate',
    'iron_spade' => 'iron_shovel',
    'jack_o_lantern' => 'lit_pumpkin',
    'jungle_door_item' => 'jungle_door',
    'jungle_wood_stairs' => 'jungle_stairs',
    'leash' => 'lead',
    'leaves_2' => 'leaves2',
    'log_2' => 'log2',
    'long_grass' => 'tallgrass',
    'monster_egg' => 'spawn_egg',
    'monster_eggs' => 'monster_egg',
    'mushroom_soup' => 'mushroom_stew',
    'mycel' => 'mycelium',
    'nether_fence' => 'nether_brick_fence',
    'nether_stalk' => 'nether_wart',
    'nether_brick_item' => 'netherbrick',
    'nether_quartz' => 'quartz_ore',
    'note_block' => 'noteblock',
    'piston_base' => 'piston',
    'piston_sticky_base' => 'sticky_piston',
    'pork' => 'porkchop',
    'potato_item' => 'potato',
    'powered_rail' => 'golden_rail',
    'rails' => 'rail',
    'raw_chicken' => 'chicken',
    'raw_fish' => 'fish',
    'raw_beef' => 'beef',
    'raw_porkchop' => 'porkchop',
    'record_10' => 'record_ward',
    'record_12' => 'record_wait',
    'record_3' => 'record_blocks',
    'record_4' => 'record_chirp',
    'record_5' => 'record_far',
    'record_6' => 'record_mall',
    'record_7' => 'record_mellohi',
    'record_8' => 'record_stal',
    'record_9' => 'record_strad',
    'red_rose' => 'red_flower',
    'redstone_comparator' => 'comparator',
    'redstone_repeater' => 'repeater',
    'redstone_lamp_off' => 'redstone_lamp',
    'redstone_torch_on' => 'redstone_torch',
    'seeds' => 'wheat_seeds',
    'silver_shulker_box' => 'light_grey_shulker_box',
    'silver_glazed_terracotta' => 'light_gray_glazed_terracotta',
    'skeletonskull' => 'skull',
    'skull_item' => 'skull',
    'slime_block' => 'slime',
    'slimeball' => 'slime_ball',
    'sugarcane' => 'reeds',
    'smooth_brick' => 'stonebrick',
    'smooth_stairs' => 'stone_brick_stairs',
    'snow_ball' => 'snowball',
    'snow_block' => 'snow',
    'spruce_door_item' => 'spruce_door',
    'spruce_wood_stairs' => 'spruce_stairs',
    'stained_clay' => 'stained_hardened_clay',
    'step' => 'stone_slab',
    'stone_plate' => 'stone_pressure_plate',
    'stone_spade' => 'stone_shovel',
    'storage_minecart' => 'chest_minecart',
    'sugar_cane' => 'reeds',
    'sulphur' => 'gunpowder',
    'thin_glass' => 'glass_pane',
    'totem' => 'totem_of_undying',
    'trap_door' => 'trapdoor',
    'unpowered_repeater' => 'repeater',
    'watch' => 'clock',
    'water_lily' => 'waterlily',
    'wood' => 'planks',
    'wood_axe' => 'wooden_axe',
    'wood_button' => 'wooden_button',
    'wood_door' => 'wooden_door',
    'wood_hoe' => 'wooden_hoe',
    'wood_pickaxe' => 'wooden_pickaxe',
    'wood_plate' => 'wooden_pressure_plate',
    'wood_spade' => 'wooden_shovel',
    'wood_stairs' => 'oak_stairs',
    'wood_step' => 'wooden_slab',
    'wood_sword' => 'wooden_sword',
    'workbench' => 'crafting_table',
);

