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

/**
 * Updates all tables where item_ids are used to use the new item names
 * @global array $UMC_DATA
 */
function umc_populate_name_field() {
    global $UMC_DATA;

    $tables = array('request', 'deposit', 'stock');

    foreach ($tables as $table) {
        foreach ($UMC_DATA as $item_name => $item_data) {
            if (isset($item_data['subtypes'])) {
                foreach ($item_data['subtypes'] as $type => $sub_data) {
                    $sql = "UPDATE minecraft_iconomy.$table "
                            . "SET item_name='$item_name' "
                            . "WHERE damage='$type' AND item_name = '{$sub_data['name']}';";
                    echo $sql;
                    // umc_mysql_query($sql, true);
                }
            }

        }
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

    $final_data = array();
    $invalid_data = array();

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

    umc_array2file($final_data, 'item_sprites', '/home/minecraft/server/bin/includes/item_sprites.inc.php');

    //TODO: Download latest version of this file:
    // http://hydra-media.cursecdn.com/minecraft.gamepedia.com/4/44/InvSprite.png
    //wiki page here: http://minecraft.gamepedia.com/File:InvSprite.png

    $source_file = 'http://hydra-media.cursecdn.com/minecraft.gamepedia.com/4/44/InvSprite.png';
    $target_directory = '/home/minecraft/server/bin/data/images';
    $R = unc_serial_curl($source_file);
    file_put_contents($target_directory . "/InvSprite.png", $R[0]['content']);

    // write CSS to file
    $css_file = '/home/minecraft/server/bin/data/item_sprites.css';
    file_put_contents($css_file, $css);
}


$UMC_DATA = array(
    'air' => array(
        'id' => 0,
        'stack' => 1,
        'avail' => false,
    ),
    'stone' => array(
        'id' => 1,
        'stack' => 64,
        'avail' => true,
        'group' => 'stone_types',
        'subtypes' => array(
            0 => array('name' => 'stone', 'avail' => true),
            1 => array('name' => 'granite', 'avail' => true),
            2 => array('name' => 'polished_granite', 'avail' => true),
            3 => array('name' => 'diorite', 'avail' => true),
            4 => array('name' => 'polished_diorite', 'avail' => true),
            5 => array('name' => 'andesite', 'avail' => true),
            6 => array('name' => 'polished_andesite', 'avail' => true),
        ),
    ),
    'grass' => array(
        'id' => 2,
        'stack' => 64,
        'avail' => true,
    ),
    'dirt' => array(
        'id' => 3,
        'stack' => 64,
        'avail' => true,
        'group' => 'dirt_types',
        'subtypes' => array(
            0 => array('name' => 'dirt', 'avail' => true),
            1 => array('name' => 'coarse_dirt', 'avail' => true),
            2 => array('name' => 'podzol', 'avail' => true),
        ),
    ),
    'cobblestone' => array(
        'id' => 4,
        'stack' => 64,
        'avail' => true,
    ),
    'planks' => array(
        'id' => 5,
        'stack' => 64,
        'avail' => true,
        'group' => 'plank_types',
        'subtypes' => array(
            0 => array('name' => 'oak_wood_planks', 'avail' => true),
            1 => array('name' => 'spruce_wood_planks', 'avail' => true),
            2 => array('name' => 'birch_wood_planks', 'avail' => true),
            3 => array('name' => 'jungle_wood_planks', 'avail' => true),
            4 => array('name' => 'acacia_wood_planks', 'avail' => true),
            5 => array('name' => 'dark_oak_wood_planks', 'avail' => true),
        ),
    ),
    'sapling' => array(
        'id' => 6,
        'stack' => 64,
        'avail' => true,
        'group' => 'sapling_types',
        'subtypes' => array(
            0 => array('name' => 'oak_sapling', 'avail' => true),
            1 => array('name' => 'spruce_sapling', 'avail' => true),
            2 => array('name' => 'birch_sapling', 'avail' => true),
            3 => array('name' => 'jungle_sapling', 'avail' => true),
            4 => array('name' => 'acacia_sapling', 'avail' => true),
            5 => array('name' => 'dark_oak_sapling', 'avail' => true),
        ),
    ),
    'bedrock' => array(
        'id' => 7,
        'stack' => 64,
        'avail' => false,
    ),
    'flowing_water' => array(
        'id' => 8,
        'stack' => 64,
        'avail' => false,
    ),
    'water' => array(
        'id' => 9,
        'stack' => 64,
        'avail' => false,
    ),
    'flowing_lava' => array(
        'id' => 10,
        'stack' => 64,
        'avail' => false,
    ),
    'lava' => array(
        'id' => 11,
        'stack' => 64,
        'avail' => false,
    ),
    'sand' => array(
        'id' => 12,
        'stack' => 64,
        'group' => 'sand_types',
        'avail' => true,
        'subtypes' => array(
            0 => array('name' => 'sand', 'avail' => true),
            1 => array('name' => 'red_sand', 'avail' => true),
        ),
    ),
    'gravel' => array(
        'id' => 13,
        'stack' => 64,
        'avail' => true,
    ),
    'gold_ore' => array(
        'id' => 14,
        'stack' => 64,
        'avail' => true,
    ),
    'iron_ore' => array(
        'id' => 15,
        'stack' => 64,
        'avail' => true,
    ),
    'coal_ore' => array(
        'id' => 16,
        'stack' => 64,
        'avail' => true,
    ),
    'log' => array(
        'id' => 17,
        'stack' => 64,
        'avail' => true,
        'group' => 'log_types',
        'subtypes' => array(
            0 => array('name' => 'oak_wood', 'avail' => true),
            1 => array('name' => 'spruce_wood', 'avail' => true),
            2 => array('name' => 'birch_wood', 'avail' => true),
            3 => array('name' => 'jungle_wood', 'avail' => true),
        ),
    ),
    'leaves' => array(
        'id' => 18,
        'stack' => 64,
        'avail' => true,
        'group' => 'leave_types',
        'subtypes' => array(
            0 => array('name' => 'oak_leaves', 'avail' => true),
            1 => array('name' => 'spruce_leaves', 'avail' => true),
            2 => array('name' => 'birch_leaves', 'avail' => true),
            3 => array('name' => 'jungle_leaves', 'avail' => true),
            4 => array('name' => 'oak_leaves_no_decay', 'avail' => false),
            5 => array('name' => 'spruce_leaves_no_decay', 'avail' => false),
            6 => array('name' => 'birch_leaves_no_decay', 'avail' => false),
            7 => array('name' => 'jungle_leaves_no_decay', 'avail' => false),
            8 => array('name' => 'oak_leaves_check_decay', 'avail' => false),
            9 => array('name' => 'spruce_leaves_check_decay', 'avail' => false),
            10 => array('name' => 'birch_leaves_check_decay', 'avail' => false),
            11 => array('name' => 'jungle_leaves_check_decay', 'avail' => false),
            12 => array('name' => 'oak_leaves_no_decay_and_check_decay', 'avail' => false),
            13 => array('name' => 'spruce_leaves_no_decay_and_check_decay', 'avail' => false),
            14 => array('name' => 'birch_leaves_no_decay_and_check_decay', 'avail' => false),
            15 => array('name' => 'jungle_leaves_no_decay_and_check_decay', 'avail' => false),
        ),
    ),
    'sponge' => array(
        'id' => 19,
        'stack' => 64,
        'avail' => false,
        'group' => 'sponge_types',
        'subtypes' => array(
            0 => array('name' => 'sponge', 'avail' => false),
            1 => array('name' => 'wet_sponge', 'avail' => false),
        ),
    ),
    'glass' => array(
        'id' => 20,
        'stack' => 64,
        'avail' => true,

    ),
    'lapis_ore' => array(
        'id' => 21,
        'stack' => 64,
        'avail' => true,

    ),
    'lapis_block' => array(
        'id' => 22,
        'stack' => 64,
        'avail' => true,

    ),
    'dispenser' => array(
        'id' => 23,
        'stack' => 64,
        'avail' => true,

    ),
    'sandstone' => array(
        'id' => 24,
        'stack' => 64,
        'avail' => true,
        'group' => 'sandstone_types',
        'subtypes' => array(
            0 => array('name' => 'sandstone', 'avail' => true),
            1 => array('name' => 'chiseled_sandstone', 'avail' => true),
            2 => array('name' => 'smooth_sandstone', 'avail' => true),
        ),
    ),
    'noteblock' => array(
        'id' => 25,
        'stack' => 64,
        'avail' => true,

    ),
    'bed' => array(
        'id' => 26,
        'stack' => 1,
        'avail' => true,

    ),
    'golden_rail' => array(
        'id' => 27,
        'stack' => 64,
        'avail' => true,

    ),
    'detector_rail' => array(
        'id' => 28,
        'stack' => 64,
        'avail' => true,

    ),
    'sticky_piston' => array(
        'id' => 29,
        'stack' => 64,
        'avail' => true,
    ),
    'web' => array(
        'id' => 30,
        'stack' => 64,
        'avail' => false,
    ),
    'tallgrass' => array(
        'id' => 31,
        'stack' => 64,
        'avail' => true,
        'group' => 'grass_types',

        'subtypes' => array(
            0 => array('name' => 'shrub', 'avail' => true),
            1 => array('name' => 'tallgrass', 'avail' => true),
            2 => array('name' => 'fern', 'avail' => true),
        ),
    ),
    'deadbush' => array(
        'id' => 32,
        'stack' => 64,
        'avail' => true,
    ),
    'piston' => array(
        'id' => 33,
        'stack' => 64,
        'avail' => true,
    ),
    'piston_head' => array(
        'id' => 34,
        'stack' => 1,
        'avail' => false,
    ),
    'wool' => array(
        'id' => 35,
        'stack' => 64,
        'avail' => true,
        'group' => 'wool_types',
        'subtypes' => array(
            0 => array('name' => 'white_wool', 'avail' => true),
            1 => array('name' => 'orange_wool', 'avail' => true),
            2 => array('name' => 'magenta_wool', 'avail' => true),
            3 => array('name' => 'light_blue_wool', 'avail' => true),
            4 => array('name' => 'yellow_wool', 'avail' => true),
            5 => array('name' => 'lime_wool', 'avail' => true),
            6 => array('name' => 'pink_wool', 'avail' => true),
            7 => array('name' => 'gray_wool', 'avail' => true),
            8 => array('name' => 'light_gray_wool', 'avail' => true),
            9 => array('name' => 'cyan_wool', 'avail' => true),
            10 => array('name' => 'purple_wool', 'avail' => true),
            11 => array('name' => 'blue_wool', 'avail' => true),
            12 => array('name' => 'brown_wool', 'avail' => true),
            13 => array('name' => 'green_wool', 'avail' => true),
            14 => array('name' => 'red_wool', 'avail' => true),
            15 => array('name' => 'black_wool', 'avail' => true),
        ),
    ),
    'piston_extension' => array(
        'id' => 36,
        'stack' => 1,
        'avail' => false,
    ),
    'yellow_flower' => array(
        'id' => 37,
        'stack' => 64,
        'avail' => true,
    ),
    'red_flower' => array(
        'id' => 38,
        'stack' => 64,
        'avail' => true,
        'group' => 'Flower Types',
        'subtypes' => array(
            0 => array('name' => 'red_flower', 'avail' => true),
            1 => array('name' => 'blue_orchid', 'avail' => true),
            2 => array('name' => 'allium', 'avail' => true),
            3 => array('name' => 'azure_bluet', 'avail' => true),
            4 => array('name' => 'red_tulip', 'avail' => true),
            5 => array('name' => 'orange_tulip', 'avail' => true),
            6 => array('name' => 'white_tulip', 'avail' => true),
            7 => array('name' => 'pink_tulip', 'avail' => true),
            8 => array('name' => 'oxeye_daisy', 'avail' => true),
        ),
    ),
    'brown_mushroom' => array(
        'id' => 39,
        'stack' => 64,
        'avail' => true,
    ),
    'red_mushroom' => array(
        'id' => 40,
        'stack' => 64,
        'avail' => true,
    ),
    'gold_block' => array(
        'id' => 41,
        'stack' => 64,
        'avail' => true,
    ),
    'iron_block' => array(
        'id' => 42,
        'stack' => 64,
        'avail' => true,
    ),
    'double_stone_slab' => array(
        'id' => 43,
        'stack' => 64,
        'avail' => false,
        'group' => 'double_slab_types',
        'subtypes' => array(
            0 => array('name' => 'double_stone_slab', 'avail' => false),
            1 => array('name' => 'double_sandstone_slab', 'avail' => false),
            2 => array('name' => 'double_(stone)_wooden_slab', 'avail' => false),
            3 => array('name' => 'double_cobblestone_slab', 'avail' => false),
            4 => array('name' => 'double_bricks_slab', 'avail' => false),
            5 => array('name' => 'double_stone_brick_slab', 'avail' => false),
            6 => array('name' => 'double_nether_brick_slab', 'avail' => false),
            7 => array('name' => 'double_quartz_slab', 'avail' => false),
            8 => array('name' => 'full_stone_slab', 'avail' => false),
            9 => array('name' => 'full_sandstone_slab', 'avail' => false),
        ),
    ),
    'stone_slab' => array(
        'id' => 44,
        'stack' => 64,
        'avail' => true,
        'group' => 'stone_slab_types',
        'subtypes' => array(
            0 => array('name' => 'stone_slab', 'avail' => true),
            1 => array('name' => 'sandstone_slab', 'avail' => true),
            2 => array('name' => '(stone)_wooden_slab', 'avail' => false),
            3 => array('name' => 'cobblestone_slab', 'avail' => true),
            4 => array('name' => 'bricks_slab', 'avail' => true),
            5 => array('name' => 'stone_brick_slab', 'avail' => true),
            6 => array('name' => 'nether_brick_slab', 'avail' => true),
            7 => array('name' => 'quartz_slab', 'avail' => true),
            8 => array('name' => 'upside-down_stone_slab', 'avail' => false),
            9 => array('name' => 'upside-down_sandstone_slab', 'avail' => false),
            10 => array('name' => 'upside-down_(stone)_wooden_slab', 'avail' => false),
            11 => array('name' => 'upside-down_cobblestone_slab', 'avail' => false),
            12 => array('name' => 'upside-down_bricks_slab', 'avail' => false),
            13 => array('name' => 'upside-down_stone_brick_slab', 'avail' => false),
            14 => array('name' => 'upside-down_nether_brick_slab', 'avail' => false),
            15 => array('name' => 'upside-down_quartz_slab', 'avail' => false),
        ),
    ),
    'brick_block' => array(
        'id' => 45,
        'stack' => 64,
        'avail' => true,
    ),
    'tnt' => array(
        'id' => 46,
        'stack' => 64,
        'avail' => true,
    ),
    'bookshelf' => array(
        'id' => 47,
        'stack' => 64,
        'avail' => true,
    ),
    'mossy_cobblestone' => array(
        'id' => 48,
        'stack' => 64,
        'avail' => true,
    ),
    'obsidian' => array(
        'id' => 49,
        'stack' => 64,
        'avail' => true,
    ),
    'torch' => array(
        'id' => 50,
        'stack' => 64,
        'avail' => true,
    ),
    'fire' => array(
        'id' => 51,
        'stack' => 64,
        'avail' => false,
    ),
    'mob_spawner' => array(
        'id' => 52,
        'stack' => 64,
        'avail' => false,
    ),
    'oak_stairs' => array(
        'id' => 53,
        'stack' => 64,
        'avail' => true,
    ),
    'chest' => array(
        'id' => 54,
        'stack' => 64,
        'avail' => true,
    ),
    'redstone_wire' => array(
        'id' => 55,
        'stack' => 64,
        'avail' => false,
    ),
    'diamond_ore' => array(
        'id' => 56,
        'stack' => 64,
        'avail' => true,
    ),
    'diamond_block' => array(
        'id' => 57,
        'stack' => 64,
        'avail' => true,
    ),
    'crafting_table' => array(
        'id' => 58,
        'stack' => 64,
        'avail' => true,
    ),
    'wheat_block' => array(
        'id' => 59,
        'stack' => 64,
        'avail' => false,
    ),
    'farmland' => array(
        'id' => 60,
        'stack' => 64,
        'avail' => false,
    ),
    'furnace' => array(
        'id' => 61,
        'stack' => 64,
        'avail' => true,
    ),
    'lit_furnace' => array(
        'id' => 62,
        'stack' => 1,
        'avail' => false,
    ),
    'standing_sign' => array(
        'id' => 63,
        'stack' => 1,
        'avail' => false,
    ),
    'oak_door' => array(
        'id' => 64,
        'stack' => 1,
        'avail' => false,
    ),
    'ladder' => array(
        'id' => 65,
        'stack' => 64,
        'avail' => true,
    ),
    'rail' => array(
        'id' => 66,
        'stack' => 64,
        'avail' => true,
    ),
    'stone_stairs' => array(
        'id' => 67,
        'stack' => 64,
        'avail' => true,
    ),
    'wall_sign' => array(
        'id' => 68,
        'stack' => 1,
        'avail' => false,
    ),
    'lever' => array(
        'id' => 69,
        'stack' => 64,
        'avail' => true,
    ),
    'stone_pressure_plate' => array(
        'id' => 70,
        'stack' => 64,
        'avail' => true,
    ),
    'iron_door_block' => array(
        'id' => 71,
        'stack' => 1,
        'avail' => false,
    ),
    'wooden_pressure_plate' => array(
        'id' => 72,
        'stack' => 64,
        'avail' => true,
    ),
    'redstone_ore' => array(
        'id' => 73,
        'stack' => 64,
        'avail' => true,
    ),
    'lit_redstone_ore' => array(
        'id' => 74,
        'stack' => 1,
        'avail' => false,
    ),
    'unlit_redstone_torch' => array(
        'id' => 75,
        'stack' => 1,
        'avail' => false,
    ),
    'redstone_torch' => array(
        'id' => 76,
        'stack' => 64,
        'avail' => true,
    ),
    'stone_button' => array(
        'id' => 77,
        'stack' => 64,
        'avail' => true,
    ),
    'snow_layer' => array(
        'id' => 78,
        'stack' => 64,
        'avail' => false,
    ),
    'ice' => array(
        'id' => 79,
        'stack' => 64,
        'avail' => true,
    ),
    'snow_block' => array(
        'id' => 80,
        'stack' => 64,
        'avail' => true,
    ),
    'cactus' => array(
        'id' => 81,
        'stack' => 64,
        'avail' => true,
    ),
    'clay' => array(
        'id' => 82,
        'stack' => 64,
        'avail' => true,
    ),
    'reeds' => array(
        'id' => 83,
        'stack' => 64,
        'avail' => false,
    ),
    'jukebox' => array(
        'id' => 84,
        'stack' => 64,
        'avail' => true,
    ),
    'fence' => array(
        'id' => 85,
        'stack' => 64,
        'avail' => true,
    ),
    'pumpkin' => array(
        'id' => 86,
        'stack' => 64,
        'avail' => true,
    ),
    'netherrack' => array(
        'id' => 87,
        'stack' => 64,
        'avail' => true,
    ),
    'soul_sand' => array(
        'id' => 88,
        'stack' => 64,
        'avail' => true,
    ),
    'glowstone' => array(
        'id' => 89,
        'stack' => 64,
        'avail' => true,
    ),
    'portal' => array(
        'id' => 90,
        'stack' => 1,
        'avail' => false,
    ),
    'lit_pumpkin' => array(
        'id' => 91,
        'stack' => 64,
        'avail' => true,
    ),
    'cake_block' => array(
        'id' => 92,
        'stack' => 1,
        'avail' => false,
    ),
    'unpowered_repeater' => array(
        'id' => 93,
        'stack' => 64,
        'avail' => false,
    ),
    'powered_repeater' => array(
        'id' => 94,
        'stack' => 64,
        'avail' => false,
    ),
    'stained_glass' => array(
        'id' => 95,
        'stack' => 64,
        'avail' => true,
        'group' => 'stained_glass_types',
        'subtypes' => array(
            0 => array('name' => 'white_glass', 'avail' => true),
            1 => array('name' => 'orange_glass', 'avail' => true),
            2 => array('name' => 'magenta_glass', 'avail' => true),
            3 => array('name' => 'light_blue_glass', 'avail' => true),
            4 => array('name' => 'yellow_glass', 'avail' => true),
            5 => array('name' => 'lime_glass', 'avail' => true),
            6 => array('name' => 'pink_glass', 'avail' => true),
            7 => array('name' => 'gray_glass', 'avail' => true),
            8 => array('name' => 'light_gray_glass', 'avail' => true),
            9 => array('name' => 'cyan_glass', 'avail' => true),
            10 => array('name' => 'purple_glass', 'avail' => true),
            11 => array('name' => 'blue_glass', 'avail' => true),
            12 => array('name' => 'brown_glass', 'avail' => true),
            13 => array('name' => 'green_glass', 'avail' => true),
            14 => array('name' => 'red_glass', 'avail' => true),
            15 => array('name' => 'black_glass', 'avail' => true),
        ),
    ),
    'trapdoor' => array(
        'id' => 96,
        'stack' => 64,
        'avail' => true,
        'subtypes' => array(
            0 => array('name' => 'trapdoor', 'avail' => true),
            1 => array('name' => 'trapdoor', 'avail' => true),
            2 => array('name' => 'trapdoor', 'avail' => true),
            3 => array('name' => 'trapdoor', 'avail' => true),
        ),
    ),
    'monster_egg' => array(
        'id' => 97,
        'stack' => 64,
        'avail' => false,
        'group' => 'monster_egg_types',
        'subtypes' => array(
            0 => array('name' => 'stone_monster_egg', 'avail' => true),
            1 => array('name' => 'cobblestone_monster_egg', 'avail' => true),
            2 => array('name' => 'stone_brick_monster_egg', 'avail' => true),
            3 => array('name' => 'mossy_stone_brick_monster_egg', 'avail' => true),
            4 => array('name' => 'cracked_stone_brick_monster_egg', 'avail' => true),
            5 => array('name' => 'chiseled_stone_brick_monster_egg', 'avail' => true),
        ),
    ),
    'stonebrick' => array(
        'id' => 98,
        'stack' => 64,
        'avail' => true,
        'group' => 'stone_brick_types',
        'subtypes' => array(
            0 => array('name' => 'stone_brick', 'avail' => true),
            1 => array('name' => 'mossy_stone_brick', 'avail' => true),
            2 => array('name' => 'cracked_stone_brick', 'avail' => true),
            3 => array('name' => 'chiseled_stone_brick', 'avail' => true),
        ),
    ),
    'brown_mushroom_block' => array(
        'id' => 99,
        'stack' => 64,
        'avail' => true,
        'group' => 'brown_mushroom_block_types',
        'subtypes' => array(
            0 => array('name' => 'brown_mushroom_block_(pores_on_all_sides)', 'avail' => true),
            1 => array('name' => 'brown_mushroom_block_(cap_texture_on_top,_west_and_north)', 'avail' => true),
            2 => array('name' => 'brown_mushroom_block_(cap_texture_on_top_and_north)', 'avail' => true),
            3 => array('name' => 'brown_mushroom_block_(cap_texture_on_top,_north_and_east)', 'avail' => true),
            4 => array('name' => 'brown_mushroom_block_(cap_texture_on_top_and_west)', 'avail' => true),
            5 => array('name' => 'brown_mushroom_block_(cap_texture_on_top)', 'avail' => true),
            6 => array('name' => 'brown_mushroom_block_(cap_texture_on_top_and_east)', 'avail' => true),
            7 => array('name' => 'brown_mushroom_block_(cap_texture_on_top,_south_and_west)', 'avail' => true),
            8 => array('name' => 'brown_mushroom_block_(cap_texture_on_top_and_south)', 'avail' => true),
            9 => array('name' => 'brown_mushroom_block_(cap_texture_on_top,_east_and_south)', 'avail' => true),
            10 => array('name' => 'brown_mushroom_block_(stem_texture on all_four_sides,_pores_on_top_and_bottom)', 'avail' => true),
            14 => array('name' => 'brown_mushroom_block_(cap_texture_on_all_six_sides)', 'avail' => true),
            15 => array('name' => 'brown_mushroom_block_(stem_texture_on_all_six_sides)', 'avail' => true),
        ),
    ),
    'red_mushroom_block' => array(
        'id' => 100,
        'stack' => 64,
        'avail' => true,
        'group' => 'red_mushroom_block_types',
        'subtypes' => array(
            0 => array('name' => 'red_mushroom_block_(pores_on_all_sides)', 'avail' => true),
            1 => array('name' => 'red_mushroom_block_(cap_texture_on_top,_west_and_north)', 'avail' => true),
            2 => array('name' => 'red_mushroom_block_(cap_texture_on_top_and_north)', 'avail' => true),
            3 => array('name' => 'red_mushroom_block_(cap_texture_on_top,_north_and_east)', 'avail' => true),
            4 => array('name' => 'red_mushroom_block_(cap_texture_on_top_and_west)', 'avail' => true),
            5 => array('name' => 'red_mushroom_block_(cap_texture_on_top)', 'avail' => true),
            6 => array('name' => 'red_mushroom_block_(cap_texture_on_top_and_east)', 'avail' => true),
            7 => array('name' => 'red_mushroom_block_(cap_texture_on_top,_south_and_west)', 'avail' => true),
            8 => array('name' => 'red_mushroom_block_(cap_texture_on_top and south)', 'avail' => true),
            9 => array('name' => 'red_mushroom_block_(cap_texture_on_top,_east_and_south)', 'avail' => true),
            10 => array('name' => 'red_mushroom_block_(stem_texture on all_four_sides,_pores_on_top_and_bottom)', 'avail' => true),
            14 => array('name' => 'red_mushroom_block_(cap_texture_on all six sides)', 'avail' => true),
            15 => array('name' => 'red_mushroom_block_(stem_texture_on_all_six_sides)', 'avail' => true),
        ),
    ),
    'iron_bars' => array(
        'id' => 101,
        'stack' => 64,
        'avail' => true,
    ),
    'glass_pane' => array(
        'id' => 102,
        'stack' => 64,
        'avail' => true,
    ),
    'melon_block' => array(
        'id' => 103,
        'stack' => 64,
        'avail' => true,
    ),
    'pumpkin_stem' => array(
        'id' => 104,
        'stack' => 1,
        'avail' => false,
    ),
    'melon_stem' => array(
        'id' => 105,
        'stack' => 1,
        'avail' => false,
    ),
    'vine' => array(
        'id' => 106,
        'stack' => 64,
        'avail' => true,
        /*'subtypes' => array(
            0 => array('name' => 'vine', 'avail' => true),
            1 => array('name' => 'vine', 'avail' => true),
            2 => array('name' => 'vine', 'avail' => true),
            4 => array('name' => 'vine', 'avail' => true),
            8 => array('name' => 'vine', 'avail' => true),
        ),*/
    ),
    'fence_gate' => array(
        'id' => 107,
        'stack' => 64,
        'avail' => true,
    ),
    'brick_stairs' => array(
        'id' => 108,
        'stack' => 64,
        'avail' => true,
    ),
    'stone_brick_stairs' => array(
        'id' => 109,
        'stack' => 64,
        'avail' => true,
    ),
    'mycelium' => array(
        'id' => 110,
        'stack' => 64,
        'avail' => true,
    ),
    'waterlily' => array(
        'id' => 111,
        'stack' => 64,
        'avail' => true,
    ),
    'nether_brick' => array(
        'id' => 112,
        'stack' => 64,
        'avail' => true,
    ),
    'nether_brick_fence' => array(
        'id' => 113,
        'stack' => 64,
        'avail' => true,
    ),
    'nether_brick_stairs' => array(
        'id' => 114,
        'stack' => 64,
        'avail' => true,
    ),
    'nether_wart_block' => array(
        'id' => 115,
        'stack' => 64,
        'avail' => false,
    ),
    'enchanting_table' => array(
        'id' => 116,
        'stack' => 64,
        'avail' => true,
    ),
    'brewing_stand' => array( // unobtainable item, but shows up in block logs
        'id' => 117,
        'stack' => 64,
        'avail' => false,
    ),
    'cauldron_block' => array(
        'id' => 118,
        'stack' => 64,
        'avail' => false,
    ),
    'end_portal' => array(
        'id' => 119,
        'stack' => 1,
        'avail' => false,
    ),
    'end_portal_frame' => array(
        'id' => 120,
        'stack' => 64,
        'avail' => true,
    ),
    'end_stone' => array(
        'id' => 121,
        'stack' => 64,
        'avail' => true,
    ),
    'dragon_egg' => array(
        'id' => 122,
        'stack' => 64,
        'avail' => true,
    ),
    'redstone_lamp' => array(
        'id' => 123,
        'stack' => 64,
        'avail' => true,
    ),
    'lit_redstone_lamp' => array(
        'id' => 124,
        'stack' => 1,
        'avail' => false,
    ),
    'double_wooden_slab' => array(
        'id' => 125,
        'stack' => 1,
        'avail' => false,
        'subtypes' => array(
            0 => array('name' => 'double_oak_wood_slab', 'avail' => false),
            1 => array('name' => 'double_spruce_wood_slab', 'avail' => false),
            2 => array('name' => 'double_birch_wood_slab', 'avail' => false),
            3 => array('name' => 'double_jungle_wood_slab', 'avail' => false),
            4 => array('name' => 'double_acacia_wood_slab', 'avail' => false),
            5 => array('name' => 'double_dark_oak_wood_slab', 'avail' => false),
        ),
    ),
    'wooden_slab' => array(
        'id' => 126,
        'stack' => 64,
        'avail' => true,
        'group' => 'wooden_slab_types',
        'subtypes' => array(
            0 => array('name' => 'oak_wood_slab', 'avail' => true),
            1 => array('name' => 'spruce_wood_slab', 'avail' => true),
            2 => array('name' => 'birch_wood_slab', 'avail' => true),
            3 => array('name' => 'jungle_wood_slab', 'avail' => true),
            4 => array('name' => 'acacia_wood_slab', 'avail' => true),
            5 => array('name' => 'dark_oak_wood_slab', 'avail' => true),
            8 => array('name' => 'upside-down_oak_wood_slab', 'avail' => false),
            9 => array('name' => 'upside-down_spruce_wood_slab', 'avail' => false),
            10 => array('name' => 'upside-down_birch_wood_slab', 'avail' => false),
            11 => array('name' => 'upside-down_jungle_wood_slab', 'avail' => false),
            12 => array('name' => 'upside-down_acacia_wood_slab', 'avail' => false),
            13 => array('name' => 'upside-down_dark_oak_wood_slab', 'avail' => false),
        ),
    ),
    'cocoa' => array(
        'id' => 127,
        'stack' => 64,
        'avail' => false,
    ),
    'sandstone_stairs' => array(
        'id' => 128,
        'stack' => 64,
        'avail' => true,
    ),
    'emerald_ore' => array(
        'id' => 129,
        'stack' => 64,
        'avail' => true,
    ),
    'ender_chest' => array(
        'id' => 130,
        'stack' => 64,
        'avail' => true,
    ),
    'tripwire_hook' => array(
        'id' => 131,
        'stack' => 64,
        'avail' => true,
    ),
    'tripwire' => array(
        'id' => 132,
        'stack' => 64,
        'avail' => false,
        'subtypes' => array(
            0 => array('name' => 'tripwire', 'avail' => false),
            2 => array('name' => 'tripwire', 'avail' => false),
        ),
    ),
    'emerald_block' => array(
        'id' => 133,
        'stack' => 64,
        'avail' => true,
    ),
    'spruce_stairs' => array(
        'id' => 134,
        'stack' => 64,
        'avail' => true,
    ),
    'birch_stairs' => array(
        'id' => 135,
        'stack' => 64,
        'avail' => true,
    ),
    'jungle_stairs' => array(
        'id' => 136,
        'stack' => 64,
        'avail' => true,
    ),
    'command_block' => array(
        'id' => 137,
        'stack' => 64,
        'avail' => false,
    ),
    'beacon' => array(
        'id' => 138,
        'stack' => 64,
        'avail' => true,
    ),
    'cobblestone_wall' => array(
        'id' => 139,
        'stack' => 64,
        'avail' => true,
        'group' => 'cobblestone_wall_types',
        'subtypes' => array(
            0 => array('name' => 'cobblestone_wall', 'avail' => true),
            1 => array('name' => 'mossy_cobblestone_wall', 'avail' => true),
        ),
    ),
    'flower_pot_block' => array( // actually just flower_pot but overlaps with item
        'id' => 140,
        'stack' => 64,
        'avail' => false,
    ),
    'carrots' => array(
        'id' => 141,
        'stack' => 64,
        'avail' => false,
    ),
    'potatoes' => array(
        'id' => 142,
        'stack' => 64,
        'avail' => false,
    ),
    'wooden_button' => array(
        'id' => 143,
        'stack' => 64,
        'avail' => true,
    ),
    'skull_block' => array( // there is a duplicate name, this should be called 'skull'
        // but since this one is not available anyhow, we ignore that
        'id' => 144,
        'stack' => 64,
        'avail' => false,
    ),
    'anvil' => array(
        'id' => 145,
        'stack' => 64,
        'avail' => true,
        'group' => 'anvil_types',
        'subtypes' => array(
            0 => array('name' => 'anvil', 'avail' => true),
            1 => array('name' => 'slightly_damaged_anvil', 'avail' => true),
            2 => array('name' => 'very_damaged_anvil', 'avail' => true),
        ),
    ),
    'trapped_chest' => array(
        'id' => 146,
        'stack' => 64,
        'avail' => true,
    ),
    'light_weighted_pressure_plate' => array(
        'id' => 147,
        'stack' => 64,
        'avail' => true,
    ),
    'heavy_weighted_pressure_plate' => array(
        'id' => 148,
        'stack' => 64,
        'avail' => true,
    ),
    'unpowered_comparator' => array(
        'id' => 149,
        'stack' => 64,
        'avail' => false,
    ),
    'powered_comparator' => array(
        'id' => 150,
        'stack' => 1,
        'avail' => false,
    ),
    'daylight_detector' => array(
        'id' => 151,
        'stack' => 64,
        'avail' => true,
    ),
    'redstone_block' => array(
        'id' => 152,
        'stack' => 64,
        'avail' => true,
    ),
    'quartz_ore' => array(
        'id' => 153,
        'stack' => 64,
        'avail' => true,
    ),
    'hopper' => array(
        'id' => 154,
        'stack' => 64,
        'avail' => true,
    ),
    'quartz_block' => array(
        'id' => 155,
        'stack' => 64,
        'avail' => true,
        'group' => 'quartz_block_types',
        'subtypes' => array(
            0 => array('name' => 'quartz_block', 'avail' => true),
            1 => array('name' => 'chiseled_quartz_block', 'avail' => true),
            2 => array('name' => 'pillar_quartz_block', 'avail' => true),
            3 => array('name' => 'pillar_quartz_block', 'avail' => false),
            4 => array('name' => 'pillar_quartz_block', 'avail' => false),
        ),
    ),
    'quartz_stairs' => array(
        'id' => 156,
        'stack' => 64,
        'avail' => true,
    ),
    'activator_rail' => array(
        'id' => 157,
        'stack' => 64,
        'avail' => true,
    ),
    'dropper' => array(
        'id' => 158,
        'stack' => 64,
        'avail' => true,
    ),
    'stained_hardened_clay' => array(
        'id' => 159,
        'stack' => 64,
        'avail' => true,
        'group' => 'clay_blocks',
        'subtypes' => array(
            0 => array('name' => 'white_clay', 'avail' => true),
            1 => array('name' => 'orange_clay', 'avail' => true),
            2 => array('name' => 'magenta_clay', 'avail' => true),
            3 => array('name' => 'light_blue_clay', 'avail' => true),
            4 => array('name' => 'yellow_clay', 'avail' => true),
            5 => array('name' => 'lime_clay', 'avail' => true),
            6 => array('name' => 'pink_clay', 'avail' => true),
            7 => array('name' => 'gray_clay', 'avail' => true),
            8 => array('name' => 'light_gray_clay', 'avail' => true),
            9 => array('name' => 'cyan_clay', 'avail' => true),
            10 => array('name' => 'purple_clay', 'avail' => true),
            11 => array('name' => 'blue_clay', 'avail' => true),
            12 => array('name' => 'brown_clay', 'avail' => true),
            13 => array('name' => 'green_clay', 'avail' => true),
            14 => array('name' => 'red_clay', 'avail' => true),
            15 => array('name' => 'black_clay', 'avail' => true),
        ),
    ),
    'stained_glass_pane' => array(
        'id' => 160,
        'stack' => 64,
        'avail' => true,
        'group' => 'glass_panes',
        'subtypes' => array(
            0 => array('name' => 'white_glass_pane', 'avail' => true),
            1 => array('name' => 'orange_glass_pane', 'avail' => true),
            2 => array('name' => 'magenta_glass_pane', 'avail' => true),
            3 => array('name' => 'light_blue_glass_pane', 'avail' => true),
            4 => array('name' => 'yellow_glass_pane', 'avail' => true),
            5 => array('name' => 'lime_glass_pane', 'avail' => true),
            6 => array('name' => 'pink_glass_pane', 'avail' => true),
            7 => array('name' => 'gray_glass_pane', 'avail' => true),
            8 => array('name' => 'light_gray_glass_pane', 'avail' => true),
            9 => array('name' => 'cyan_glass_pane', 'avail' => true),
            10 => array('name' => 'purple_glass_pane', 'avail' => true),
            11 => array('name' => 'blue_glass_pane', 'avail' => true),
            12 => array('name' => 'brown_glass_pane', 'avail' => true),
            13 => array('name' => 'green_glass_pane', 'avail' => true),
            14 => array('name' => 'red_glass_pane', 'avail' => true),
            15 => array('name' => 'black_glass_pane', 'avail' => true),
        ),
    ),
    'leaves2' => array(
        'id' => 161,
        'stack' => 64,
        'avail' => true,
        'group' => 'leaves_types_(2)',
        'subtypes' => array(
            0 => array('name' => 'acacia_leaves', 'avail' => true),
            1 => array('name' => 'dark_oak_leaves', 'avail' => true),
        ),
    ),
    'log2' => array(
        'id' => 162,
        'stack' => 64,
        'avail' => true,
        'group' => 'log_types_(2)',
        'subtypes' => array(
            0 => array('name' => 'acacia_wood', 'avail' => true),
            1 => array('name' => 'dark_oak_wood', 'avail' => true),
        ),
    ),
    'acacia_stairs' => array(
        'id' => 163,
        'stack' => 64,
        'avail' => true,
    ),
    'dark_oak_stairs' => array(
        'id' => 164,
        'stack' => 64,
        'avail' => true,
    ),
    'slime_block' => array(
        'id' => 165,
        'stack' => 64,
        'avail' => true,
    ),
    'barrier' => array(
        'id' => 166,
        'stack' => 64,
        'avail' => true,
    ),
    'iron_trapdoor' => array(
        'id' => 167,
        'stack' => 64,
        'avail' => true,
    ),
    'prismarine' => array(
        'id' => 168,
        'stack' => 64,
        'avail' => true,
        'group' => 'prismarine_types',
        'subtypes' => array(
            0 => array('name' => 'prismarine', 'avail' => true),
            1 => array('name' => 'prismarine_bricks', 'avail' => true),
            2 => array('name' => 'dark_prismarine', 'avail' => true),
        ),
    ),
    'sea_lantern' => array(
        'id' => 169,
        'stack' => 64,
        'avail' => true,
    ),
    'hay_block' => array(
        'id' => 170,
        'stack' => 64,
        'avail' => true,
    ),
    'carpet' => array(
        'id' => 171,
        'stack' => 64,
        'avail' => true,
        'group' => 'carpets',
        'subtypes' => array(
            0 => array('name' => 'white_carpet', 'avail' => true),
            1 => array('name' => 'orange_carpet', 'avail' => true),
            2 => array('name' => 'magenta_carpet', 'avail' => true),
            3 => array('name' => 'light_blue_carpet', 'avail' => true),
            4 => array('name' => 'yellow_carpet', 'avail' => true),
            5 => array('name' => 'lime_carpet', 'avail' => true),
            6 => array('name' => 'pink_carpet', 'avail' => true),
            7 => array('name' => 'gray_carpet', 'avail' => true),
            8 => array('name' => 'light_gray_carpet', 'avail' => true),
            9 => array('name' => 'cyan_carpet', 'avail' => true),
            10 => array('name' => 'purple_carpet', 'avail' => true),
            11 => array('name' => 'blue_carpet', 'avail' => true),
            12 => array('name' => 'brown_carpet', 'avail' => true),
            13 => array('name' => 'green_carpet', 'avail' => true),
            14 => array('name' => 'red_carpet', 'avail' => true),
            15 => array('name' => 'black_carpet', 'avail' => true),
        ),
    ),
    'hardened_clay' => array(
        'id' => 172,
        'stack' => 64,
        'avail' => true,
    ),
    'coal_block' => array(
        'id' => 173,
        'stack' => 64,
        'avail' => true,
    ),
    'packed_ice' => array(
        'id' => 174,
        'stack' => 64,
        'avail' => true,
    ),
    'double_plant' => array(
        'id' => 175,
        'stack' => 64,
        'avail' => true,
        'group' => 'tall_flower_types',
        'subtypes' => array(
            0 => array('name' => 'sunflower', 'avail' => true),
            1 => array('name' => 'lilac', 'avail' => true),
            2 => array('name' => 'double_tallgrass', 'avail' => true),
            3 => array('name' => 'large_fern', 'avail' => true),
            4 => array('name' => 'rose_bush', 'avail' => true),
            5 => array('name' => 'peony', 'avail' => true),
            8 => array('name' => 'plant_top_half', 'avail' => false),
        ),
    ),
    'standing_banner' => array(
        'id' => 176,
        'stack' => 16,
        'avail' => false,
    ),
    'wall_banner' => array(
        'id' => 177,
        'stack' => 16,
        'avail' => false,
    ),
    'daylight_detector_inverted' => array(
        'id' => 178,
        'stack' => 64,
        'avail' => true,
    ),
    'red_sandstone' => array(
        'id' => 179,
        'stack' => 64,
        'avail' => true,
        'group' => 'red_sandstone_types',
        'subtypes' => array(
            0 => array('name' => 'red_sandstone', 'avail' => true),
            1 => array('name' => 'chiseled_red_sandstone', 'avail' => true),
            2 => array('name' => 'smooth__red_sandstone', 'avail' => true),
        ),
    ),
    'red_sandstone_stairs' => array(
        'id' => 180,
        'stack' => 64,
        'avail' => true,
    ),
    'double_stone_slab2' => array(
        'id' => 181,
        'stack' => 64,
        'avail' => false,
    ),
    'stone_slab2' => array(
        'id' => 182,
        'stack' => 64,
        'avail' => true,
    ),
    'spruce_fence_gate' => array(
        'id' => 183,
        'stack' => 64,
        'avail' => true,
    ),
    'birch_fence_gate' => array(
        'id' => 184,
        'stack' => 64,
        'avail' => true,
    ),
    'jungle_fence_gate' => array(
        'id' => 185,
        'stack' => 64,
        'avail' => true,
    ),
    'dark_oak_fence_gate' => array(
        'id' => 186,
        'stack' => 64,
        'avail' => true,
    ),
    'acacia_fence_gate' => array(
        'id' => 187,
        'stack' => 64,
        'avail' => true,
    ),
    'spruce_fence' => array(
        'id' => 188,
        'stack' => 64,
        'avail' => true,
    ),
    'birch_fence' => array(
        'id' => 189,
        'stack' => 64,
        'avail' => true,
    ),
    'jungle_fence' => array(
        'id' => 190,
        'stack' => 64,
        'avail' => true,
    ),
    'dark_oak_fence' => array(
        'id' => 191,
        'stack' => 64,
        'avail' => true,
    ),
    'acacia_fence' => array(
        'id' => 192,
        'stack' => 64,
        'avail' => true,
    ),
    'spruce_door_block' => array(
        'id' => 193,
        'stack' => 1,
        'avail' => false,
    ),
    'birch_door_block' => array(
        'id' => 194,
        'stack' => 1,
        'avail' => false,
    ),
    'jungle_door_block' => array(
        'id' => 195,
        'stack' => 1,
        'avail' => false,
    ),
    'acacia_door_block' => array(
        'id' => 196,
        'stack' => 1,
        'avail' => false,
    ),
    'dark_oak_door_block' => array(
        'id' => 197,
        'stack' => 64,
        'avail' => false,
    ),
    'end_rod' => array(
        'id' => 198,
        'stack' => 64,
        'avail' => false,
    ),
    'chorus_plant' => array(
        'id' => 199,
        'stack' => 64,
        'avail' => true,
    ),
    'chorus_flower' => array(
        'id' => 200,
        'stack' => 64,
        'avail' => true,
    ),
    'purpur_block' => array(
        'id' => 201,
        'stack' => 64,
        'avail' => true,
    ),
    'purpur_pillar' => array(
        'id' => 202,
        'stack' => 64,
        'avail' => true,
    ),
    'purpur_stairs' => array(
        'id' => 203,
        'stack' => 64,
        'avail' => true,
    ),
    'purpur_double_slab' => array(
        'id' => 204,
        'stack' => 64,
        'avail' => false,
    ),
    'purpur_slab' => array(
        'id' => 205,
        'stack' => 64,
        'avail' => true,
    ),
    'end_bricks' => array(
        'id' => 206,
        'stack' => 64,
        'avail' => true,
    ),
    'grass_path' => array(
        'id' => 208,
        'stack' => 64,
        'avail' => true,
    ),
    'end_gateway' => array(
        'id' => 209,
        'stack' => 64,
        'avail' => false,
    ),
    'frosted_ice' => array(
        'id' => 212,
        'stack' => 64,
        'avail' => false,
    ),
    'magma' => array(
        'id' => 213,
        'stack' => 64,
        'avail' => true,
    ),
    'nether_wart_block' => array(
        'id' => 214,
        'stack' => 64,
        'avail' => true,
    ),
    'red_nether_brick' => array(
        'id' => 215,
        'stack' => 64,
        'avail' => true,
    ),
    'bone_block' => array(
        'id' => 216,
        'stack' => 64,
        'avail' => true,
    ),
    'structure_void' => array(
        'id' => 217,
        'stack' => 64,
        'avail' => false,
    ),
    'observer' => array(
        'id' => 218,
        'stack' => 64,
        'avail' => true,
    ),
    'white_shulker_box' => array(
        'id' => 219,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
    ),
    'orange_shulker_box' => array(
        'id' => 220,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
    ),
    'magenta_shulker_box' => array(
        'id' => 221,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
    ),
    'light_blue_shulker_box' => array(
        'id' => 222,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
    ),
    'yellow_shulker_box' => array(
        'id' => 223,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
    ),
    'lime_shulker_box' => array(
        'id' => 224,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
    ),
    'pink_shulker_box' => array(
        'id' => 225,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
    ),
    'gray_shulker_box' => array(
        'id' => 226,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
    ),
    'light_gray_shulker_box' => array(
        'id' => 227,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
    ),
    'cyan_shulker_box' => array(
        'id' => 228,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
    ),
    'purple_shulker_box' => array(
        'id' => 229,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
    ),
    'blue_shulker_box' => array(
        'id' => 230,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
    ),
    'brown_shulker_box' => array(
        'id' => 231,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
    ),
    'green_shulker_box' => array(
        'id' => 232,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
    ),
    'red_shulker_box' => array(
        'id' => 233,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
    ),
    'black_shulker_box' => array(
        'id' => 234,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
    ),
    'white_glazed_terracotta' => array(
        'id' => 235,
        'stack' => 64,
        'avail' => true,
    ),
    'orange_glazed_terracotta' => array(
        'id' => 236,
        'stack' => 64,
        'avail' => true,
    ),   
    'magenta_glazed_terracotta' => array(
        'id' => 237,
        'stack' => 64,
        'avail' => true,
    ),
    'light_blue_glazed_terracotta' => array(
        'id' => 238,
        'stack' => 64,
        'avail' => true,
    ),       
    'yellow_glazed_terracotta' => array(
        'id' => 239,
        'stack' => 64,
        'avail' => true,
    ),
    'lime_glazed_terracotta' => array(
        'id' => 240,
        'stack' => 64,
        'avail' => true,
    ),   
    'pink_glazed_terracotta' => array(
        'id' => 241,
        'stack' => 64,
        'avail' => true,
    ),
    'gray_glazed_terracotta' => array(
        'id' => 242,
        'stack' => 64,
        'avail' => true,
    ),       
    'light_gray_glazed_terracotta' => array(
        'id' => 243,
        'stack' => 64,
        'avail' => true,
    ),
    'cyan_glazed_terracotta' => array(
        'id' => 244,
        'stack' => 64,
        'avail' => true,
    ),   
    'purple_glazed_terracotta' => array(
        'id' => 245,
        'stack' => 64,
        'avail' => true,
    ),
    'blue_glazed_terracotta' => array(
        'id' => 246,
        'stack' => 64,
        'avail' => true,
    ),       
    'brown_glazed_terracotta' => array(
        'id' => 247,
        'stack' => 64,
        'avail' => true,
    ),
    'green_glazed_terracotta' => array(
        'id' => 248,
        'stack' => 64,
        'avail' => true,
    ),   
    'red_glazed_terracotta' => array(
        'id' => 249,
        'stack' => 64,
        'avail' => true,
    ),
    'black_glazed_terracotta' => array(
        'id' => 250,
        'stack' => 64,
        'avail' => true,
    ),   
    'concrete' => array(
        'id' => 251,
        'stack' => 64,
        'avail' => true,
    ),
    'concrete_powder' => array(
        'id' => 252,
        'stack' => 64,
        'avail' => true,
    ),
    'structure_block' => array(
        'id' => 255,
        'stack' => 64,
        'avail' => false,
    ),


    /*************************************************/
    /*                ITEMS                          */
    /*************************************************/

    'iron_shovel' => array(
        'id' => 256,
        'damage' => 251,
        'stack' => 1,
        'avail' => true,
    ),
    'iron_pickaxe' => array(
        'id' => 257,
        'damage' => 251,
        'stack' => 1,
        'avail' => true,
    ),
    'iron_axe' => array(
        'id' => 258,
        'damage' => 251,
        'stack' => 1,
        'avail' => true,
    ),
    'flint_and_steel' => array(
        'id' => 259,
        'damage' => 65,
        'stack' => 1,
        'avail' => true,
    ),
    'apple' => array(
        'id' => 260,
        'stack' => 64,
        'avail' => true,
    ),
    'bow' => array(
        'id' => 261,
        'damage' => 385,
        'stack' => 1,
        'avail' => true,
    ),
    'arrow' => array(
        'id' => 262,
        'stack' => 64,
        'avail' => true,
    ),
    'coal' => array(
        'id' => 263,
        'stack' => 64,
        'avail' => true,
        'group' => 'coal_types',
        'subtypes' => array(
            0 => array('name' => 'coal', 'avail' => true),
            1 => array('name' => 'charcoal', 'avail' => true),
        ),
    ),
    'diamond' => array(
        'id' => 264,
        'stack' => 64,
        'avail' => true,
    ),
    'iron_ingot' => array(
        'id' => 265,
        'stack' => 64,
        'avail' => true,
    ),
    'gold_ingot' => array(
        'id' => 266,
        'stack' => 64,
        'avail' => true,
    ),
    'iron_sword' => array(
        'id' => 267,
        'damage' => 251,
        'stack' => 1,
        'avail' => true,
    ),
    'wooden_sword' => array(
        'id' => 268,
        'damage' => 60,
        'stack' => 1,
        'avail' => true,
    ),
    'wooden_shovel' => array(
        'id' => 269,
        'damage' => 60,
        'stack' => 1,
        'avail' => true,
    ),
    'wooden_pickaxe' => array(
        'id' => 270,
        'damage' => 60,
        'stack' => 1,
        'avail' => true,
    ),
    'wooden_axe' => array(
        'id' => 271,
        'damage' => 60,
        'stack' => 1,
        'avail' => true,
    ),
    'stone_sword' => array(
        'id' => 272,
        'damage' => 132,
        'stack' => 1,
        'avail' => true,
    ),
    'stone_shovel' => array(
        'id' => 273,
        'damage' => 132,
        'stack' => 1,
        'avail' => true,
    ),
    'stone_pickaxe' => array(
        'id' => 274,
        'damage' => 132,
        'stack' => 1,
        'avail' => true,
    ),
    'stone_axe' => array(
        'id' => 275,
        'damage' => 132,
        'stack' => 1,
        'avail' => true,
    ),
    'diamond_sword' => array(
        'id' => 276,
        'damage' => 1562,
        'stack' => 1,
        'avail' => true,
    ),
    'diamond_shovel' => array(
        'id' => 277,
        'damage' => 1562,
        'stack' => 1,
        'avail' => true,
    ),
    'diamond_pickaxe' => array(
        'id' => 278,
        'damage' => 1562,
        'stack' => 1,
        'avail' => true,
    ),
    'diamond_axe' => array(
        'id' => 279,
        'damage' => 1562,
        'stack' => 1,
        'avail' => true,
    ),
    'stick' => array(
        'id' => 280,
        'stack' => 64,
        'avail' => true,
    ),
    'bowl' => array(
        'id' => 281,
        'stack' => 64,
        'avail' => true,
    ),
    'mushroom_stew' => array(
        'id' => 282,
        'stack' => 1,
        'avail' => true,
    ),
    'golden_sword' => array(
        'id' => 283,
        'damage' => 33,
        'stack' => 1,
        'avail' => true,
    ),
    'golden_shovel' => array(
        'id' => 284,
        'damage' => 33,
        'stack' => 1,
        'avail' => true,
    ),
    'golden_pickaxe' => array(
        'id' => 285,
        'damage' => 33,
        'stack' => 1,
        'avail' => true,
    ),
    'golden_axe' => array(
        'id' => 286,
        'damage' => 33,
        'stack' => 1,
        'avail' => true,
    ),
    'string' => array(
        'id' => 287,
        'stack' => 64,
        'avail' => true,
    ),
    'feather' => array(
        'id' => 288,
        'stack' => 64,
        'avail' => true,
    ),
    'gunpowder' => array(
        'id' => 289,
        'stack' => 64,
        'avail' => true,
    ),
    'wooden_hoe' => array(
        'id' => 290,
        'damage' => 60,
        'stack' => 1,
        'avail' => true,
    ),
    'stone_hoe' => array(
        'id' => 291,
        'damage' => 132,
        'stack' => 1,
        'avail' => true,
    ),
    'iron_hoe' => array(
        'id' => 292,
        'damage' => 251,
        'stack' => 1,
        'avail' => true,
    ),
    'diamond_hoe' => array(
        'id' => 293,
        'damage' => 1562,
        'stack' => 1,
        'avail' => true,
    ),
    'golden_hoe' => array(
        'id' => 294,
        'damage' => 33,
        'stack' => 1,
        'avail' => true,
    ),
    'wheat_seeds' => array(
        'id' => 295,
        'stack' => 64,
        'avail' => true,
    ),
    'wheat' => array(
        'id' => 296,
        'stack' => 64,
        'avail' => true,
    ),
    'bread' => array(
        'id' => 297,
        'stack' => 64,
        'avail' => true,
    ),
    'leather_helmet' => array(
        'id' => 298,
        'damage' => 56,
        'stack' => 1,
        'avail' => true,
    ),
    'leather_chestplate' => array(
        'id' => 299,
        'damage' => 81,
        'stack' => 1,
        'avail' => true,
    ),
    'leather_leggings' => array(
        'id' => 300,
        'damage' => 76,
        'stack' => 1,
        'avail' => true,
    ),
    'leather_boots' => array(
        'id' => 301,
        'damage' => 66,
        'stack' => 1,
        'avail' => true,
    ),
    'chainmail_helmet' => array(
        'id' => 302,
        'damage' => 166,
        'stack' => 1,
        'avail' => true,
    ),
    'chainmail_chestplate' => array(
        'id' => 303,
        'damage' => 241,
        'stack' => 1,
        'avail' => true,
    ),
    'chainmail_leggings' => array(
        'id' => 304,
        'damage' => 226,
        'stack' => 1,
        'avail' => true,
    ),
    'chainmail_boots' => array(
        'id' => 305,
        'damage' => 196,
        'stack' => 1,
        'avail' => true,
    ),
    'iron_helmet' => array(
        'id' => 306,
        'damage' => 166,
        'stack' => 1,
        'avail' => true,
    ),
    'iron_chestplate' => array(
        'id' => 307,
        'damage' => 241,
        'stack' => 1,
        'avail' => true,
    ),
    'iron_leggings' => array(
        'id' => 308,
        'damage' => 226,
        'stack' => 1,
        'avail' => true,
    ),
    'iron_boots' => array(
        'id' => 309,
        'damage' => 196,
        'stack' => 1,
        'avail' => true,
    ),
    'diamond_helmet' => array(
        'id' => 310,
        'damage' => 364,
        'stack' => 1,
        'avail' => true,
    ),
    'diamond_chestplate' => array(
        'id' => 311,
        'damage' => 529,
        'stack' => 1,
        'avail' => true,
    ),
    'diamond_leggings' => array(
        'id' => 312,
        'damage' => 496,
        'stack' => 1,
        'avail' => true,
    ),
    'diamond_boots' => array(
        'id' => 313,
        'damage' => 430,
        'stack' => 1,
        'avail' => true,
    ),
    'golden_helmet' => array(
        'id' => 314,
        'damage' => 78,
        'stack' => 1,
        'avail' => true,
    ),
    'golden_chestplate' => array(
        'id' => 315,
        'damage' => 113,
        'stack' => 1,
        'avail' => true,
    ),
    'golden_leggings' => array(
        'id' => 316,
        'damage' => 106,
        'stack' => 1,
        'avail' => true,
    ),
    'golden_boots' => array(
        'id' => 317,
        'damage' => 92,
        'stack' => 1,
        'avail' => true,
    ),
    'flint' => array(
        'id' => 318,
        'stack' => 64,
        'avail' => true,
    ),
    'porkchop' => array(
        'id' => 319,
        'stack' => 64,
        'avail' => true,
    ),
    'cooked_porkchop' => array(
        'id' => 320,
        'stack' => 64,
        'avail' => true,
    ),
    'painting' => array(
        'id' => 321,
        'stack' => 64,
        'avail' => true,
    ),
    'golden_apple' => array(
        'id' => 322,
        'stack' => 64,
        'avail' => true,
        'group' => 'golden_apple_types',
        'subtypes' => array(
            0 => array('name' => 'golden_apple', 'avail' => true),
            1 => array('name' => 'enchanted_golden_apple', 'avail' => true),
        ),
    ),
    'sign' => array(
        'id' => 323,
        'stack' => 16,
        'avail' => true,
    ),
    'wooden_door' => array(
        'id' => 324,
        'stack' => 1,
        'avail' => true,
    ),
    'bucket' => array(
        'id' => 325,
        'stack' => 16,
        'avail' => true,
    ),
    'water_bucket' => array(
        'id' => 326,
        'stack' => 1,
        'avail' => true,
    ),
    'lava_bucket' => array(
        'id' => 327,
        'stack' => 1,
        'avail' => true,
    ),
    'minecart' => array(
        'id' => 328,
        'stack' => 1,
        'avail' => true,
    ),
    'saddle' => array(
        'id' => 329,
        'stack' => 1,
        'avail' => true,
    ),
    'iron_door' => array(
        'id' => 330,
        'stack' => 1,
        'avail' => true,
    ),
    'redstone' => array(
        'id' => 331,
        'stack' => 64,
        'avail' => true,
    ),
    'snow_ball' => array(
        'id' => 332,
        'stack' => 16,
        'avail' => true,
    ),
    'boat' => array(
        'id' => 333,
        'stack' => 1,
        'avail' => true,
    ),
    'leather' => array(
        'id' => 334,
        'stack' => 64,
        'avail' => true,
    ),
    'milk_bucket' => array(
        'id' => 335,
        'stack' => 1,
        'avail' => true,
    ),
    'brick' => array(
        'id' => 336,
        'stack' => 64,
        'avail' => true,
    ),
    'clay_ball' => array(
        'id' => 337,
        'stack' => 64,
        'avail' => true,
    ),
    'reeds' => array(
        'id' => 338,
        'stack' => 64,
        'avail' => true,
    ),
    'paper' => array(
        'id' => 339,
        'stack' => 64,
        'avail' => true,
    ),
    'book' => array(
        'id' => 340,
        'stack' => 64,
        'avail' => true,
    ),
    'slime_ball' => array(
        'id' => 341,
        'stack' => 64,
        'avail' => true,
    ),
    'chest_minecart' => array(
        'id' => 342,
        'stack' => 1,
        'avail' => true,
    ),
    'furnace_minecart' => array(
        'id' => 343,
        'stack' => 1,
        'avail' => true,
    ),
    'egg' => array(
        'id' => 344,
        'stack' => 16,
        'avail' => true,
    ),
    'compass' => array(
        'id' => 345,
        'stack' => 1,
        'avail' => true,
    ),
    'fishing_rod' => array(
        'id' => 346,
        'damage' => 64,
        'stack' => 1,
        'avail' => true,
    ),
    'clock' => array(
        'id' => 347,
        'stack' => 1,
        'avail' => true,
    ),
    'glowstone_dust' => array(
        'id' => 348,
        'stack' => 64,
        'avail' => true,
    ),
    'fish' => array(
        'id' => 349,
        'stack' => 64,
        'avail' => true,
        'group' => 'raw_fish_types',
        'subtypes' => array(
            0 => array('name' => 'raw_fish', 'avail' => true),
            1 => array('name' => 'raw_salmon', 'avail' => true),
            2 => array('name' => 'clownfish', 'avail' => true),
            3 => array('name' => 'pufferfish', 'avail' => true),
        ),
    ),
    'cooked_fish' => array(
        'id' => 350,
        'stack' => 64,
        'avail' => true,
        'group' => 'cooked_fish_types',
        'subtypes' => array(
            0 => array('name' => 'cooked_fish', 'avail' => true),
            1 => array('name' => 'cooked_salmon', 'avail' => true),
        ),
    ),
    'dye' => array(
        'id' => 351,
        'stack' => 64,
        'avail' => true,
        'group' => 'dye_types',
        'subtypes' => array(
            0 => array('name' => 'ink_sac', 'avail' => true),
            1 => array('name' => 'rose_red', 'avail' => true),
            2 => array('name' => 'cactus_green', 'avail' => true),
            3 => array('name' => 'cocoa_beans', 'avail' => true),
            4 => array('name' => 'lapis_lazuli', 'avail' => true),
            5 => array('name' => 'purple_dye', 'avail' => true),
            6 => array('name' => 'cyan_dye', 'avail' => true),
            7 => array('name' => 'light_gray_dye', 'avail' => true),
            8 => array('name' => 'gray_dye', 'avail' => true),
            9 => array('name' => 'pink_dye', 'avail' => true),
            10 => array('name' => 'lime_dye', 'avail' => true),
            11 => array('name' => 'dandelion_yellow', 'avail' => true),
            12 => array('name' => 'light_blue_dye', 'avail' => true),
            13 => array('name' => 'magenta_dye', 'avail' => true),
            14 => array('name' => 'orange_dye', 'avail' => true),
            15 => array('name' => 'bone_meal', 'avail' => true),
        ),
    ),
    'bone' => array(
        'id' => 352,
        'stack' => 64,
        'avail' => true,
    ),
    'sugar' => array(
        'id' => 353,
        'stack' => 64,
        'avail' => true,
    ),
    'cake' => array(
        'id' => 354,
        'stack' => 1,
        'avail' => true,
    ),
    'bed' => array(
        'id' => 355,
        'stack' => 1,
        'avail' => true,
    ),
    'repeater' => array(
        'id' => 356,
        'stack' => 64,
        'avail' => true,
    ),
    'cookie' => array(
        'id' => 357,
        'stack' => 64,
        'avail' => true,
    ),
    'filled_map' => array(
        'id' => 358,
        'stack' => 1,
        'avail' => true,
    ),
    'shears' => array(
        'id' => 359,
        'damage' => 238,
        'stack' => 1,
        'avail' => true,
    ),
    'melon' => array(
        'id' => 360,
        'stack' => 64,
        'avail' => true,
    ),
    'pumpkin_seeds' => array(
        'id' => 361,
        'stack' => 64,
        'avail' => true,
    ),
    'melon_seeds' => array(
        'id' => 362,
        'stack' => 64,
        'avail' => true,
    ),
    'raw_beef' => array(
        'id' => 363,
        'stack' => 64,
        'avail' => true,
    ),
    'cooked_beef' => array(
        'id' => 364,
        'stack' => 64,
        'avail' => true,
    ),
    'chicken' => array(
        'id' => 365,
        'stack' => 64,
        'avail' => true,
    ),
    'cooked_chicken' => array(
        'id' => 366,
        'stack' => 64,
        'avail' => true,
    ),
    'rotten_flesh' => array(
        'id' => 367,
        'stack' => 64,
        'avail' => true,
    ),
    'ender_pearl' => array(
        'id' => 368,
        'stack' => 16,
        'avail' => true,
    ),
    'blaze_rod' => array(
        'id' => 369,
        'stack' => 64,
        'avail' => true,
    ),
    'ghast_tear' => array(
        'id' => 370,
        'stack' => 64,
        'avail' => true,
    ),
    'gold_nugget' => array(
        'id' => 371,
        'stack' => 64,
        'avail' => true,
    ),
    'nether_wart' => array(
        'id' => 372,
        'stack' => 64,
        'avail' => true,
    ),
    'potion' => array(
        'id' => 373,
        'stack' => 1,
        'avail' => true,
    ),
    'glass_bottle' => array(
        'id' => 374,
        'stack' => 64,
        'avail' => true,
    ),
    'spider_eye' => array(
        'id' => 375,
        'stack' => 64,
        'avail' => true,
    ),
    'fermented_spider_eye' => array(
        'id' => 376,
        'stack' => 64,
        'avail' => true,
    ),
    'blaze_powder' => array(
        'id' => 377,
        'stack' => 64,
        'avail' => true,
    ),
    'magma_cream' => array(
        'id' => 378,
        'stack' => 64,
        'avail' => true,
    ),
    'brewingstand' => array(
        'id' => 379,
        'stack' => 64,
        'avail' => true,
    ),
    'cauldron' => array(
        'id' => 380,
        'stack' => 64,
        'avail' => true,
    ),
    'ender_eye' => array(
        'id' => 381,
        'stack' => 64,
        'avail' => true,
    ),
    'speckled_melon' => array(
        'id' => 382,
        'stack' => 64,
        'avail' => true,
    ),
    'spawn_egg' => array(
        'id' => 383,
        'stack' => 64,
        'avail' => true,
        'nbt_types' => array(
            0 => array('nbt' => '{EntityTag:{id:"minecraft:zombie_horse"}}', 'avail' => true), //not sure if egg exists
            1 => array('nbt' => '{EntityTag:{id:"minecraft:donkey"}}', 'avail' => true), //lottery
            2 => array('nbt' => '{EntityTag:{id:"minecraft:mule"}}', 'avail' => true), //lottery
            3 => array('nbt' => '{EntityTag:{id:"minecraft:creeper"}}', 'avail' => true),
            4 => array('nbt' => '{EntityTag:{id:"minecraft:skeleton"}}', 'avail' => true),
            5 => array('nbt' => '{EntityTag:{id:"minecraft:spider"}}', 'avail' => true),
            6 => array('nbt' => '{EntityTag:{id:"minecraft:zombie"}}', 'avail' => true),
            7 => array('nbt' => '{EntityTag:{id:"minecraft:slime"}}', 'avail' => true),
            8 => array('nbt' => '{EntityTag:{id:"minecraft:ghast"}}', 'avail' => true),
            9 => array('nbt' => '{EntityTag:{id:"minecraft:pigman"}}', 'avail' => true),
            10 => array('nbt' => '{EntityTag:{id:"minecraft:enderman"}}', 'avail' => true),
            11 => array('nbt' => '{EntityTag:{id:"minecraft:cave_spider"}}', 'avail' => true),
            12 => array('nbt' => '{EntityTag:{id:"minecraft:silverfish"}}', 'avail' => true),
            13 => array('nbt' => '{EntityTag:{id:"minecraft:blaze"}}', 'avail' => true),
            14 => array('nbt' => '{EntityTag:{id:"minecraft:magma_cube"}}', 'avail' => true),
            15 => array('nbt' => '{EntityTag:{id:"minecraft:bat"}}', 'avail' => true),  //lottery
            16 => array('nbt' => '{EntityTag:{id:"minecraft:witch"}}', 'avail' => true),
            17 => array('nbt' => '{EntityTag:{id:"minecraft:endermite"}}', 'avail' => true),
            18 => array('nbt' => '{EntityTag:{id:"minecraft:guardian"}}', 'avail' => true),
            19 => array('nbt' => '{EntityTag:{id:"minecraft:shulker"}}', 'avail' => true),
            20 => array('nbt' => '{EntityTag:{id:"minecraft:pig"}}', 'avail' => true), //lottery
            21 => array('nbt' => '{EntityTag:{id:"minecraft:sheep"}}', 'avail' => true), //lottery
            22 => array('nbt' => '{EntityTag:{id:"minecraft:cow"}}', 'avail' => true), //lottery
            23 => array('nbt' => '{EntityTag:{id:"minecraft:chicken"}}', 'avail' => true), //lottery
            24 => array('nbt' => '{EntityTag:{id:"minecraft:squid"}}', 'avail' => true), //lottery
            25 => array('nbt' => '{EntityTag:{id:"minecraft:wolf"}}', 'avail' => true), //lottery
            26 => array('nbt' => '{EntityTag:{id:"minecraft:mooshroom"}}', 'avail' => true), //lottery
            27 => array('nbt' => '{EntityTag:{id:"minecraft:ocelot"}}', 'avail' => true), //lottery
            28 => array('nbt' => '{EntityTag:{id:"minecraft:horse"}}', 'avail' => true), //lottery
            29 => array('nbt' => '{EntityTag:{id:"minecraft:rabbit"}}', 'avail' => true), //lottery
            30 => array('nbt' => '{EntityTag:{id:"minecraft:polar_bear"}}', 'avail' => true), //lottery
            31 => array('nbt' => '{EntityTag:{id:"minecraft:llama"}}', 'avail' => true), //lottery //TODO: find the right image
            32 => array('nbt' => '{EntityTag:{id:"minecraft:villager"}}', 'avail' => true), //lottery
            33 => array('nbt' => '{EntityTag:{id:"minecraft:zombie_villager"}}', 'avail' => true),
        ),
    ),
    'experience_bottle' => array(
        'id' => 384,
        'stack' => 64,
        'avail' => true,
    ),
    'fire_charge' => array(
        'id' => 385,
        'stack' => 64,
        'avail' => true,
    ),
    'writable_book' => array(
        'id' => 386,
        'stack' => 1,
        'avail' => true,
    ),
    'written_book' => array(
        'id' => 387,
        'stack' => 1,
        'avail' => true,
    ),
    'emerald' => array(
        'id' => 388,
        'stack' => 64,
        'avail' => true,
    ),
    'item_frame' => array(
        'id' => 389,
        'stack' => 64,
        'avail' => true,
    ),
    'flower_pot' => array(
        'id' => 390,
        'stack' => 64,
        'avail' => true,
    ),
    'carrot' => array(
        'id' => 391,
        'stack' => 64,
        'avail' => true,
    ),
    'potato' => array(
        'id' => 392,
        'stack' => 64,
        'avail' => true,
    ),
    'baked_potato' => array(
        'id' => 393,
        'stack' => 64,
        'avail' => true,
    ),
    'poisonous_potato' => array(
        'id' => 394,
        'stack' => 64,
        'avail' => true,
    ),
    'map' => array(
        'id' => 395,
        'stack' => 64,
        'avail' => true,
    ),
    'golden_carrot' => array(
        'id' => 396,
        'stack' => 64,
        'avail' => true,
    ),
    'skull' => array(
        'id' => 397,
        'stack' => 64,
        'avail' => true,
        'group' => 'skull_types',
        'subtypes' => array(
            0 => array('name' => 'skeleton_skull', 'avail' => true),
            1 => array('name' => 'wither_skeleton_skull', 'avail' => true),
            2 => array('name' => 'zombie_head', 'avail' => true),
            3 => array('name' => 'head', 'avail' => true),
            4 => array('name' => 'creeper_head', 'avail' => true),
            5 => array('name' => 'dragon_head', 'avail' => true),
        ),
    ),
    'carrot_on_a_stick' => array(
        'id' => 398,
        'damage' => 25,
        'stack' => 1,
        'avail' => true,
    ),
    'nether_star' => array(
        'id' => 399,
        'stack' => 64,
        'avail' => true,
    ),
    'pumpkin_pie' => array(
        'id' => 400,
        'stack' => 64,
        'avail' => true,
    ),
    'fireworks' => array(
        'id' => 401,
        'stack' => 64,
        'avail' => true,
    ),
    'firework_charge' => array(
        'id' => 402,
        'stack' => 64,
        'avail' => true,
    ),
    'enchanted_book' => array(
        'id' => 403,
        'stack' => 1,
        'avail' => true,
    ),
    'comparator' => array(
        'id' => 404,
        'stack' => 64,
        'avail' => true,
    ),
    'netherbrick' => array(
        'id' => 405,
        'stack' => 64,
        'avail' => true,
    ),
    'quartz' => array(
        'id' => 406,
        'stack' => 64,
        'avail' => true,
    ),
    'tnt_minecart' => array(
        'id' => 407,
        'stack' => 1,
        'avail' => true,
    ),
    'hopper_minecart' => array(
        'id' => 408,
        'stack' => 1,
        'avail' => true,
    ),
    'prismarine_shard' => array(
        'id' => 409,
        'stack' => 64,
        'avail' => false,
    ),
    'prismarine_crystals' => array(
        'id' => 410,
        'stack' => 64,
        'avail' => false,
    ),
    'rabbit' => array(
        'id' => 411,
        'stack' => 64,
        'avail' => false,
    ),
    'cooked_rabbit' => array(
        'id' => 412,
        'stack' => 64,
        'avail' => false,
    ),
    'rabbit_stew' => array(
        'id' => 413,
        'stack' => 1,
        'avail' => false,
    ),
    'rabbit_foot' => array(
        'id' => 414,
        'stack' => 64,
        'avail' => false,
    ),
    'rabbit_hide' => array(
        'id' => 415,
        'stack' => 64,
        'avail' => false,
    ),
    'armor_stand' => array(
        'id' => 416,
        'stack' => 16,
        'avail' => false,
    ),
    'iron_horse_armor' => array(
        'id' => 417,
        'stack' => 1,
        'avail' => true,
    ),
    'golden_horse_armor' => array(
        'id' => 418,
        'stack' => 1,
        'avail' => true,
    ),
    'diamond_horse_armor' => array(
        'id' => 419,
        'stack' => 1,
        'avail' => true,
    ),
    'lead' => array(
        'id' => 420,
        'stack' => 64,
        'avail' => true,
    ),
    'name_tag' => array(
        'id' => 421,
        'stack' => 64,
        'avail' => true,
    ),
    'command_block_minecart' => array(
        'id' => 422,
        'stack' => 1,
        'avail' => false,
    ),
    'mutton' => array(
        'id' => 423,
        'stack' => 64,
        'avail' => true,
    ),
    'cooked_mutton' => array(
        'id' => 424,
        'stack' => 64,
        'avail' => true,
    ),
    'banner' => array(
        'id' => 425,
        'stack' => 16,
        'avail' => true,
    ),
    'end_crystal' => array(
        'id' => 426,
        'stack' => 64,
        'avail' => true,
    ),
    'spruce_door' => array(
        'id' => 427,
        'stack' => 64,
        'avail' => true,
    ),
    'birch_door' => array(
        'id' => 428,
        'stack' => 64,
        'avail' => true,
    ),
    'jungle_door' => array(
        'id' => 429,
        'stack' => 64,
        'avail' => true,
    ),
    'acacia_door' => array(
        'id' => 430,
        'stack' => 64,
        'avail' => true,
    ),
    'dark_oak_door' => array(
        'id' => 431,
        'stack' => 64,
        'avail' => true,
    ),
    'chorus_fruit' => array(
        'id' => 432,
        'stack' => 64,
        'avail' => true,
    ),
    'chorus_fruit_popped' => array(
        'id' => 433,
        'stack' => 64,
        'avail' => true,
    ),
    'beetroot' => array(
        'id' => 434,
        'stack' => 64,
        'avail' => true,
    ),
    'beetroot_seeds' => array(
        'id' => 435,
        'stack' => 64,
        'avail' => true,
    ),
    'beetroot_soup' => array(
        'id' => 436,
        'stack' => 1,
        'avail' => true,
    ),
    'dragon_breath' => array(
        'id' => 437,
        'stack' => 64,
        'avail' => true,
    ),
    'splash_potion' => array(
        'id' => 438,
        'stack' => 1,
        'avail' => true,
    ),
    'spectral_arrow' => array(
        'id' => 439,
        'stack' => 64,
        'avail' => true,
    ),
    'tipped_arrow' => array(
        'id' => 440,
        'stack' => 64,
        'avail' => true,
    ),
    'lingering_potion' => array(
        'id' => 441,
        'stack' => 1,
        'avail' => true,
    ),
    'shield' => array(
        'id' => 442,
        'stack' => 1,
        'avail' => true,
    ),
    'elytra' => array(
        'id' => 443,
        'stack' => 1,
        'avail' => true,
    ),
    'spruce_boat' => array(
        'id' => 444,
        'stack' => 1,
        'avail' => true,
    ),
    'birch_boat' => array(
        'id' => 445,
        'stack' => 1,
        'avail' => true,
    ),
    'jungle_boat' => array(
        'id' => 446,
        'stack' => 1,
        'avail' => true,
    ),
    'acacia_boat' => array(
        'id' => 447,
        'stack' => 1,
        'avail' => true,
    ),
    'dark_oak_boat' => array(
        'id' => 448,
        'stack' => 1,
        'avail' => true,
    ),
    'totem' => array(
        'id' => 449,
        'stack' => 1,
        'avail' => true,
    ),
    'shulker_shell' => array(
        'id' => 450,
        'stack' => 64,
        'avail' => true,
    ),
    'iron_nugget' => array(
        'id' => 452,
        'stack' => 64,
        'avail' => true,
    ),

    /*************************************************/
    /*                RECORDS                        */
    /*************************************************/

    'record_13' => array(
        'id' => 2256,
        'stack' => 1,
        'avail' => true,
    ),
    'record_cat' => array(
        'id' => 2257,
        'stack' => 1,
        'avail' => true,
    ),
    'record_blocks' => array(
        'id' => 2258,
        'stack' => 1,
        'avail' => true,
    ),
    'record_chirp' => array(
        'id' => 2259,
        'stack' => 1,
        'avail' => true,
    ),
    'record_far' => array(
        'id' => 2260,
        'stack' => 1,
        'avail' => true,
    ),
    'record_mall' => array(
        'id' => 2261,
        'stack' => 1,
        'avail' => true,
    ),
    'record_mellohi' => array(
        'id' => 2262,
        'stack' => 1,
        'avail' => true,
    ),
    'record_stal' => array(
        'id' => 2263,
        'stack' => 1,
        'avail' => true,
    ),
    'record_strad' => array(
        'id' => 2264,
        'stack' => 1,
        'avail' => true,
    ),
    'record_ward' => array(
        'id' => 2265,
        'stack' => 1,
        'avail' => true,
    ),
    'record_11' => array(
        'id' => 2266,
        'stack' => 1,
        'avail' => true,
    ),
    'record_wait' => array(
        'id' => 2267,
        'stack' => 1,
        'avail' => true,
    ),
);

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
    'clay_brick' => 'brick',
    'cobble_wall' => 'cobblestone_wall',
    'cobblestone_stairs' => 'stone_stairs',
    'dark_oak_door_item' => 'dark_oak_door',
    'dead_bush' => 'deadbush',
    'diamond_barding' => 'diamond_horse_armor',
    'diamond_pick' => 'diamond_pickaxe',
    'diamond_spade' => 'diamond_shovel',
    'diode' => 'unpowered_repeater',
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
    'note_block' => 'noteblock',
    'piston_base' => 'piston',
    'piston_sticky_base' => 'sticky_piston',
    'pork' => 'porkchop',
    'potato_item' => 'potato',
    'powered_rail' => 'golden_rail',
    'rails' => 'rail',
    'raw_chicken' => 'chicken',
    'raw_fish' => 'fish',
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
    'redstone_lamp_off' => 'redstone_lamp',
    'redstone_torch_on' => 'redstone_torch',
    'seeds' => 'wheat_seeds',
    'silver_shulker_box' => 'light_grey_shulker_box',
    'skull_item' => 'skull',
    'slime_block' => 'slime_block',
    'smooth_brick' => 'stonebrick',
    'smooth_stairs' => 'stone_brick_stairs',
    'snow' => 'snow_ball',
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
    'trap_door' => 'trapdoor',
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
