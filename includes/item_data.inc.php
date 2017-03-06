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
 * 1) take all item_names from pasted wiki-code
 * 2) take all item_names from the data table UMC_DATA
 * 3) take all item names from the data table $UMC_DATA_ID2NAME
 * 4) check if they match
 * 5) check if any of the tables have unknown data
 *
 */
function umc_item_name_integrity_check() {
    global $UMC_DATA, $UMC_DATA_ID2NAME, $UMC_DATA_SPIGOT2ITEM;
    $out = '';
    $text = '';
    $tables = array('request', 'deposit', 'stock');

    if (isset($_POST['wiki_text'])) {
        $results = false;
        $text = strip_tags($_POST['wiki_text']);
        $pattern = '/minecraft:(.*)\r/';
        preg_match_all($pattern, $text, $results);
        // process results
        $sub_results = $results[1];
        foreach ($sub_results as $wiki_string) {
            if (!isset($UMC_DATA[$wiki_string])) {
                $out .= "$wiki_string ! UMC_DATA ERROR<br>";
            } else {
                // now check for umc_items
                $id = $UMC_DATA[$wiki_string]['id'];
                if ($UMC_DATA_ID2NAME[$id] != $wiki_string) {
                    $wrong_name = $UMC_DATA_ID2NAME[$id];
                    $out .= "$wiki_string ! UMC_ITEMS ERROR (ID: $id, $wrong_name)<br>";
                }
            }
        }
        foreach ($UMC_DATA_SPIGOT2ITEM as $wrong_data => $right_data) {
            if (!isset($UMC_DATA[$right_data])) {
                $out .= "$right_data should not be in table ($wrong_data)!";
            }
        }


        $out .= "<pre>";
        foreach ($tables as $table) {
            $sql = "SELECT item_name FROM minecraft_iconomy.$table group by item_name;";
            $rst = umc_mysql_query($sql);
            while ($D = umc_mysql_fetch_array($rst)) {
                $table_item = $D['item_name'];
                if (!in_array($table_item, $sub_results)) {
                    $out .= "UPDATE minecraft_iconomy.$table SET item_name='$table_item' WHERE item_name='$table_item';<br>";
                }
            }
        }
        $out .= "</pre>";
    }
    $out .= '<form style="text-align:center" method="post"><input type="submit">'
        . '<p><textarea name="wiki_text" rows="20" style="width=100%;">'. $text .'</textarea></p>'
        . '<input type="submit"></form>';
    return $out;
}


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
 * @global array $UMC_DATA_ID2NAME
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

$ENCH_ITEMS = array(
    'PROTECTION_ENVIRONMENTAL'=> array( // 0
        'id' => 0,
        'short'=> 'Prot',
        'name'=>'Protection',
        'items'=> array(
            'diamond_helmet', 'diamond_chestplate', 'diamond_leggings', 'diamond_boots',
            'golden_helmet', 'golden_chestplate', 'golden_leggings', 'golden_boots',
            'iron_helmet', 'iron_chestplate', 'iron_leggings', 'iron_boots',
            'chainmail_helmet', 'chainmail_chestplate', 'chainmail_leggings', 'chainmail_boots',
            'leather_helmet', 'leather_chestplate', 'leather_leggings', 'leather_boots',
        ),
        'max'=> 4
    ),
    'PROTECTION_FIRE' =>array( // 1
        'id' => 1,
        'short'=> 'FP',
        'name'=>'FireProtection',
        'items'=> array(
            'diamond_helmet', 'diamond_chestplate', 'diamond_leggings', 'diamond_boots',
            'golden_helmet', 'golden_chestplate', 'golden_leggings', 'golden_boots',
            'iron_helmet', 'iron_chestplate', 'iron_leggings', 'iron_boots',
            'chainmail_helmet', 'chainmail_chestplate', 'chainmail_leggings', 'chainmail_boots',
            'leather_helmet', 'leather_chestplate', 'leather_leggings', 'leather_boots',
        ),
        'max'=>4
    ),
    'PROTECTION_FALL' => array( // 2
        'id' => 2,
        'short'=> 'Fall',
        'name'=>'FeatherFalling',
        'items'=> array(
            'diamond_boots', 'golden_boots', 'iron_boots', 'chainmail_boots', 'leather_boots',
        ),
        'max'=>4
    ),
    'PROTECTION_EXPLOSIONS' => array( // 3
        'id' => 3,
        'short'=> 'BP',
        'name'=>'BlastProtection',
        'items'=> array(
            'diamond_helmet', 'diamond_chestplate', 'diamond_leggings', 'diamond_boots',
            'golden_helmet', 'golden_chestplate', 'golden_leggings', 'golden_boots',
            'iron_helmet', 'iron_chestplate', 'iron_leggings', 'iron_boots',
            'chainmail_helmet', 'chainmail_chestplate', 'chainmail_leggings', 'chainmail_boots',
            'leather_helmet', 'leather_chestplate', 'leather_leggings', 'leather_boots',
        ),
        'max'=>4
    ),
    'PROTECTION_PROJECTILE' => array( // 4
        'id' => 4,
        'short'=> 'PP',
        'name'=>'ProjectileProtection',
        'items'=> array(
            'diamond_helmet', 'diamond_chestplate', 'diamond_leggings', 'diamond_boots',
            'golden_helmet', 'golden_chestplate', 'golden_leggings', 'golden_boots',
            'iron_helmet', 'iron_chestplate', 'iron_leggings', 'iron_boots',
            'chainmail_helmet', 'chainmail_chestplate', 'chainmail_leggings', 'chainmail_boots',
            'leather_helmet', 'leather_chestplate', 'leather_leggings', 'leather_boots',
        ),
        'max'=>4
    ),
    'OXYGEN' => array( // 5
        'id' => 5,
        'short' => 'Res',
        'name' =>'Respiration',
        'items' => array(
            'diamond_helmet', 'golden_helmet', 'iron_helmet', 'chainmail_helmet', 'leather_helmet',
        ),
        'max' => 3
    ),
    'WATER_WORKER' => array( // 6
        'id' => 6,
        'short' => 'Aqua',
        'name' =>'AquaAffinity',
        'items' => array(
            'diamond_helmet', 'golden_helmet', 'iron_helmet', 'chainmail_helmet', 'leather_helmet',
        ),
        'max' => 1
    ),
    'THORNS' => array( // 7
        'id' => 7,
        'short' => 'Thorn',
        'name' =>'Thorn',
        'items' => array(
            'diamond_helmet', 'diamond_chestplate', 'diamond_leggings', 'diamond_boots',
            'golden_helmet', 'golden_chestplate', 'golden_leggings', 'golden_boots',
            'iron_helmet', 'iron_chestplate', 'iron_leggings', 'iron_boots',
            'chainmail_helmet', 'chainmail_chestplate', 'chainmail_leggings', 'chainmail_boots',
            'leather_helmet', 'leather_chestplate', 'leather_leggings', 'leather_boots',
        ),
        'max' => 3
    ),
    'DEPTH_STRIDER' => array( // 8
        'id' => 8,
        'short' => 'Depth',
        'name' => 'DepthStrider',
        'items' => array(
            'diamond_boots', 'golden_boots', 'iron_boots', 'chainmail_boots', 'leather_boots',
        ),
        'max' => 3
    ),
    'FROST_WALKER' => array( // 9
        'id' => 9,
        'short' => 'Depth',
        'name' => 'DepthStrider',
        'items' => array(
            'diamond_boots', 'golden_boots', 'iron_boots', 'chainmail_boots', 'leather_boots',
        ),
        'max' => 2
    ),
    'BINDING_CURSE' => array( // 10
        'id' => 10,
        'short' => 'Binding',
        'name' =>'BindingCurse',
        'items' => array(
            'diamond_helmet', 'diamond_chestplate', 'diamond_leggings', 'diamond_boots',
            'golden_helmet', 'golden_chestplate', 'golden_leggings', 'golden_boots',
            'iron_helmet', 'iron_chestplate', 'iron_leggings', 'iron_boots',
            'chainmail_helmet', 'chainmail_chestplate', 'chainmail_leggings', 'chainmail_boots',
            'leather_helmet', 'leather_chestplate', 'leather_leggings', 'leather_boots',
        ),
        'max' => 1
    ),
    'DAMAGE_ALL' => array( // 16
        'id' => 16,
        'short'=> 'Sharp',
        'name'=>'Sharpness',
        'items'=> array(
            'diamond_sword', 'golden_sword', 'iron_sword', 'stone_sword', 'wooden_sword',
            'diamond_axe', 'golden_axe', 'iron_axe', 'stone_axe', 'wooden_axe',
        ),
        'max' => 5
    ),
    'DAMAGE_UNDEAD' => array( // 17
        'id' => 17,
        'short' => 'Smite',
        'name' => 'Smite',
        'items' => array(
            'diamond_sword', 'golden_sword', 'iron_sword', 'stone_sword', 'wooden_sword',
            'diamond_axe', 'golden_axe', 'iron_axe', 'stone_axe', 'wooden_axe',
        ),
        'max'=>5
    ),
    'DAMAGE_ARTHROPODS' => array( // 18
        'id' => 18,
        'short' => 'Bane',
        'name' => 'BaneOfArthropods',
        'items' => array(
            'diamond_sword', 'golden_sword', 'iron_sword', 'stone_sword', 'wooden_sword',
            'diamond_axe', 'golden_axe', 'iron_axe', 'stone_axe', 'wooden_axe',
        ),
        'max'=>5
    ),
    'KNOCKBACK' => array( // 19
        'id' => 19,
        'short' => 'Knock',
        'name' => 'Knockback',
        'items' => array(
            'diamond_sword', 'golden_sword', 'iron_sword', 'stone_sword', 'wooden_sword',
        ),
        'max'=>2
    ),
    'FIRE_ASPECT' => array( // 20
        'id' => 20,
        'short' => 'Fire',
        'name' => 'FireAspect',
        'items' => array(
            'diamond_sword', 'golden_sword', 'iron_sword', 'stone_sword', 'wooden_sword',
        ),
        'max'=>2
    ),
    'LOOT_BONUS_MOBS' =>array( // 21
        'id' => 21,
        'short' => 'Loot',
        'name' => 'Looting',
        'items' => array(
            'diamond_sword', 'golden_sword', 'iron_sword', 'stone_sword', 'wooden_sword',
        ),
        'max'=>3
    ),
    'DIG_SPEED' =>array( // 32
        'id' => 32,
        'short' => 'Eff',
        'name' => 'Efficiency',
        'items' => array(
            'diamond_pick', 'golden_pick', 'iron_pick', 'stone_pick', 'wooden_pick',
            'diamond_shovel', 'golden_shovel', 'iron_shovel', 'stone_shovel', 'wooden_shovel',
            'diamond_axe', 'golden_axe', 'iron_axe', 'stone_axe', 'wooden_axe',
            'shears'
        ),
        'max' => 5
    ),
    'SILK_TOUCH' => array( // 33
        'id' => 33,
        'short' => 'Silk',
        'name' => 'SilkTouch',
        'items' => array(
            'diamond_pick', 'golden_pick', 'iron_pick', 'stone_pick', 'wooden_pick',
            'diamond_shovel', 'golden_shovel', 'iron_shovel', 'stone_shovel', 'wooden_shovel',
            'diamond_axe', 'golden_axe', 'iron_axe', 'stone_axe', 'wooden_axe',
        ),
        'max' => 1
    ),
    'DURABILITY' => array( // 34
        'id' => 34,
        'short'=> 'Unb',
        'name'=>'Unbreaking',
        'items' => array(
            'diamond_helmet', 'diamond_chestplate', 'diamond_leggings', 'diamond_boots',
            'golden_helmet', 'golden_chestplate', 'golden_leggings', 'golden_boots',
            'iron_helmet', 'iron_chestplate', 'iron_leggings', 'iron_boots',
            'chainmail_helmet', 'chainmail_chestplate', 'chainmail_leggings', 'chainmail_boots',
            'leather_helmet', 'leather_chestplate', 'leather_leggings', 'leather_boots',
            'diamond_pick', 'golden_pick', 'iron_pick', 'stone_pick', 'wooden_pick',
            'diamond_shovel', 'golden_shovel', 'iron_shovel', 'stone_shovel', 'wooden_shovel',
            'diamond_axe', 'golden_axe', 'iron_axe', 'stone_axe', 'wooden_axe',
            'diamond_sword', 'golden_sword', 'iron_sword', 'stone_sword', 'wooden_sword',
            'diamond_hoe', 'golden_hoe', 'iron_hoe', 'stone_hoe', 'wooden_hoe',
            'bow', 'fishing_rod', 'shears', 'flint_and_steel', 'carrot_on_a_stick', 'shield', 'elytra'
        ),
        'max' => 3
    ),
    'LOOT_BONUS_BLOCKS' => array( // 35
        'id' => 35,
        'short' => 'Fort',
        'name' => 'Fortune',
        'items' => array(
            'diamond_pick', 'golden_pick', 'iron_pick', 'stone_pick', 'wooden_pick',
            'diamond_shovel', 'golden_shovel', 'iron_shovel', 'stone_shovel', 'wooden_shovel',
            'diamond_axe', 'golden_axe', 'iron_axe', 'stone_axe', 'wooden_axe',
        ),
        'max' => 3
    ),
    'ARROW_DAMAGE' => array( //48
        'id' => 48,
        'short' => 'Power',
        'name' => 'Power',
        'items' => array('bow'),
        'max'=>5
    ),
    'ARROW_KNOCKBACK' => array( //49
        'id' => 49,
        'short'=> 'Punch',
        'name'=>'Punch',
        'items' => array('bow'),
        'max' => 2
    ),
    'ARROW_FIRE' => array( // 50
        'id' => 50,
        'short' => 'Flame',
        'name' => 'Flame',
        'items' => array('bow'),
        'max' => 1
    ),
    'ARROW_INFINITE' => array( // 51
        'id' => 51,
        'short' => 'Inf',
        'name' => 'Infinity',
        'items' => array('bow'),
        'max'=>1
    ),
    'LUCK' =>array( // 61
        'id' => 61,
        'short' => 'Luck',
        'name' => 'Luck',
        'items' => array('fishing_rod'),
        'max'=>1
    ),
    'LURE' => array( // 62
        'id' => 62,
        'short' => 'Lure',
        'name' => 'Lure',
        'items' => array('fishing_rod'),
        'max'=>1
    ),
    'MENDING' => array( // 70
        'id' => 70,
        'short' => 'Mending',
        'name' => 'Mending',
        'items' => array(
            'diamond_helmet', 'diamond_chestplate', 'diamond_leggings', 'diamond_boots',
            'golden_helmet', 'golden_chestplate', 'golden_leggings', 'golden_boots',
            'iron_helmet', 'iron_chestplate', 'iron_leggings', 'iron_boots',
            'chainmail_helmet', 'chainmail_chestplate', 'chainmail_leggings', 'chainmail_boots',
            'leather_helmet', 'leather_chestplate', 'leather_leggings', 'leather_boots',
            'diamond_pick', 'golden_pick', 'iron_pick', 'stone_pick', 'wooden_pick',
            'diamond_shovel', 'golden_shovel', 'iron_shovel', 'stone_shovel', 'wooden_shovel',
            'diamond_axe', 'golden_axe', 'iron_axe', 'stone_axe', 'wooden_axe',
            'diamond_sword', 'golden_sword', 'iron_sword', 'stone_sword', 'wooden_sword',
            'diamond_hoe', 'golden_hoe', 'iron_hoe', 'stone_hoe', 'wooden_hoe',
            'bow', 'fishing_rod', 'shears', 'flint_and_steel', 'carrot_on_a_stick', 'shield', 'elytra',
        ),
        'max'=>1
    ),
    'UNKNOWN_ENCHANT_71' =>array( // 71
        'short' => 'Vanish',
        'name' => 'Curse of Vanishing',
        'items' => array(
            'diamond_helmet', 'diamond_chestplate', 'diamond_leggings', 'diamond_boots',
            'golden_helmet', 'golden_chestplate', 'golden_leggings', 'golden_boots',
            'iron_helmet', 'iron_chestplate', 'iron_leggings', 'iron_boots',
            'chainmail_helmet', 'chainmail_chestplate', 'chainmail_leggings', 'chainmail_boots',
            'leather_helmet', 'leather_chestplate', 'leather_leggings', 'leather_boots',
            'diamond_pick', 'golden_pick', 'iron_pick', 'stone_pick', 'wooden_pick',
            'diamond_shovel', 'golden_shovel', 'iron_shovel', 'stone_shovel', 'wooden_shovel',
            'diamond_axe', 'golden_axe', 'iron_axe', 'stone_axe', 'wooden_axe',
            'diamond_sword', 'golden_sword', 'iron_sword', 'stone_sword', 'wooden_sword',
            'diamond_hoe', 'golden_hoe', 'iron_hoe', 'stone_hoe', 'wooden_hoe',
            'bow', 'fishing_rod', 'shears', 'flint_and_steel', 'carrot_on_a_stick', 'shield', 'elytra',
        ),
        'max'=>1
    ),
    'VANISHING_CURSE' =>array(  // 71
        'id' => 71,
        'short' => 'Vanish',
        'name' => 'Curse of Vanishing',
        'items' => array(
            'diamond_helmet', 'diamond_chestplate', 'diamond_leggings', 'diamond_boots',
            'golden_helmet', 'golden_chestplate', 'golden_leggings', 'golden_boots',
            'iron_helmet', 'iron_chestplate', 'iron_leggings', 'iron_boots',
            'chainmail_helmet', 'chainmail_chestplate', 'chainmail_leggings', 'chainmail_boots',
            'leather_helmet', 'leather_chestplate', 'leather_leggings', 'leather_boots',
            'diamond_pick', 'golden_pick', 'iron_pick', 'stone_pick', 'wooden_pick',
            'diamond_shovel', 'golden_shovel', 'iron_shovel', 'stone_shovel', 'wooden_shovel',
            'diamond_axe', 'golden_axe', 'iron_axe', 'stone_axe', 'wooden_axe',
            'diamond_sword', 'golden_sword', 'iron_sword', 'stone_sword', 'wooden_sword',
            'diamond_hoe', 'golden_hoe', 'iron_hoe', 'stone_hoe', 'wooden_hoe',
            'bow', 'fishing_rod', 'shears', 'flint_and_steel', 'carrot_on_a_stick', 'shield', 'elytra',
        ),
        'max' => 1
    ),
);

$UMC_BANNERS = array(
    'colors' => array(
        'WHITE', 'ORANGE', 'MAGENTA', 'LIGHT_BLUE', 'YELLOW', 'LIME', 'PINK', 'GRAY', 'LIGHT_GRAY', 'CYAN', 'PURPLE', 'BLUE', 'BROWN', 'GREEN', 'RED', 'BLACK',
    ),
    'patterns' => array(
        "SQUARE_BOTTOM_LEFT", "SQUARE_BOTTOM_RIGHT","SQUARE_TOP_LEFT","SQUARE_TOP_RIGHT","STRIPE_BOTTOM","STRIPE_TOP","STRIPE_LEFT",
        "BASE","STRIPE_RIGHT","STRIPE_CENTER","STRIPE_MIDDLE","STRIPE_DOWNRIGHT","STRIPE_DOWNLEFT","STRIPE_SMALL","CROSS","TRIANGLE_BOTTOM","TRIANGLE_TOP",
        "TRIANGLES_TOP","DIAGONAL_LEFT","DIAGONAL_RIGHT","CIRCLE_MIDDLE","RHOMBUS_MIDDLE","HALF_VERTICAL","HALF_HORIZONTAL","CREEPER",
        "GRADIENT","BRICKS","SKULL","FLOWER",
    ),
);

$UMC_DATA = array(
    'air' => array(
        'id' => 0,
        'stack' => 1,
        'avail' => false,
        'icon_url' => '?',
    ),
    'stone' => array(
        'id' => 1,
        'stack' => 64,
        'avail' => true,
        'group' => 'stone_types',
        'icon_url' => '/a/a6/Grid_Stone.png',
        'subtypes' => array(
            0 => array('name' => 'stone', 'avail' => true, 'icon_url' => '/a/a6/Grid_Stone.png'),
            1 => array('name' => 'granite', 'avail' => true, 'icon_url' => '/c/ce/Grid_Granite.png'),
            2 => array('name' => 'polished_granite', 'avail' => true, 'icon_url' => '/b/b6/Grid_Polished_Granite.png'),
            3 => array('name' => 'diorite', 'avail' => true, 'icon_url' => '/5/53/Grid_Polished_Diorite.png'),
            4 => array('name' => 'polished_diorite', 'avail' => true, 'icon_url' => '/0/08/Diorite.png'),
            5 => array('name' => 'andesite', 'avail' => true, 'icon_url' => '/3/35/Grid_Andesite.png'),
            6 => array('name' => 'polished_andesite', 'avail' => true, 'icon_url' => '/8/80/Grid_Polished_Andesite.png'),
        ),
    ),
    'grass' => array(
        'id' => 2,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/0/08/Grid_Grass_Block.png',
    ),
    'dirt' => array(
        'id' => 3,
        'stack' => 64,
        'avail' => true,
        'group' => 'dirt_types',
        'icon_url' => '/b/bd/Grid_Dirt.png',
        'subtypes' => array(
            0 => array('name' => 'dirt', 'avail' => true, 'icon_url' =>  '/b/bd/Grid_Dirt.png'),
            1 => array('name' => 'coarse_dirt', 'avail' => true, 'icon_url' => '/5/57/Grid_Coarse_Dirt.png'),
            2 => array('name' => 'podzol', 'avail' => true, 'icon_url' => '/6/6d/Grid_Podzol.png'),
        ),
    ),
    'cobblestone' => array(
        'id' => 4,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/0/03/Grid_Cobblestone.png',
    ),
    'planks' => array(
        'id' => 5,
        'stack' => 64,
        'avail' => true,
        'group' => 'plank_types',
        'icon_url' => '/d/d3/Grid_Oak_Wood_Planks.png',
        'subtypes' => array(
            0 => array('name' => 'oak_wood_planks', 'avail' => true, 'icon_url' => '/d/d3/Grid_Oak_Wood_Planks.png'),
            1 => array('name' => 'spruce_wood_planks', 'avail' => true, 'icon_url' => '/f/f9/Grid_Spruce_Wood_Planks.png'),
            2 => array('name' => 'birch_wood_planks', 'avail' => true, 'icon_url' => '/a/a3/Grid_Birch_Wood_Planks.png'),
            3 => array('name' => 'jungle_wood_planks', 'avail' => true, 'icon_url' => '/d/d3/Grid_Jungle_Wood_Planks.png'),
            4 => array('name' => 'acacia_wood_planks', 'avail' => true, 'icon_url' => '/f/f1/Grid_Acacia_Wood_Planks.png'),
            5 => array('name' => 'dark_oak_wood_planks', 'avail' => true, 'icon_url' => '/b/b5/Grid_Dark_Oak_Wood_Planks.png'),
        ),
    ),
    'sapling' => array(
        'id' => 6,
        'stack' => 64,
        'avail' => true,
        'group' => 'sapling_types',
        'icon_url' => '/4/4b/Grid_Oak_Sapling.png',
        'subtypes' => array(
            0 => array('name' => 'oak_sapling', 'avail' => true, 'icon_url' => '/4/4b/Grid_Oak_Sapling.png'),
            1 => array('name' => 'spruce_sapling', 'avail' => true, 'icon_url' => '/0/08/Grid_Spruce_Sapling.png'),
            2 => array('name' => 'birch_sapling', 'avail' => true, 'icon_url' => '/b/b3/Grid_Birch_Sapling.png'),
            3 => array('name' => 'jungle_sapling', 'avail' => true, 'icon_url' => '/b/b3/Grid_Jungle_Sapling.png'),
            4 => array('name' => 'acacia_sapling', 'avail' => true, 'icon_url' => '/5/5b/Grid_Acacia_Sapling.png'),
            5 => array('name' => 'dark_oak_sapling', 'avail' => true, 'icon_url' => '/c/c5/Grid_Dark_Oak_Sapling.png'),
        ),
    ),
    'bedrock' => array(
        'id' => 7,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/e/e0/Grid_Bedrock.png',
    ),
    'flowing_water' => array(
        'id' => 8,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/1/13/Grid_Water.png',
    ),
    'water' => array(
        'id' => 9,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/1/13/Grid_Water.png',
    ),
    'flowing_lava' => array(
        'id' => 10,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/6/61/Grid_Lava.png',
    ),
    'lava' => array(
        'id' => 11,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/6/61/Grid_Lava.png',
    ),
    'sand' => array(
        'id' => 12,
        'stack' => 64,
        'group' => 'sand_types',
        'avail' => true,
        'icon_url' => '/b/bb/Grid_Sand.png',
        'subtypes' => array(
            0 => array('name' => 'sand', 'avail' => true, 'icon_url' => '/b/bb/Grid_Sand.png'),
            1 => array('name' => 'red_sand', 'avail' => true, 'icon_url' => '/8/85/Grid_Red_Sand.png'),
        ),
    ),
    'gravel' => array(
        'id' => 13,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/0/03/Grid_Gravel.png',
    ),
    'gold_ore' => array(
        'id' => 14,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/c/ce/Grid_Gold_Ore.png',
    ),
    'iron_ore' => array(
        'id' => 15,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/9/9a/Grid_Iron_Ore.png',
    ),
    'coal_ore' => array(
        'id' => 16,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/2/2b/Grid_Coal_Ore.png',
    ),
    'log' => array(
        'id' => 17,
        'stack' => 64,
        'avail' => true,
        'group' => 'log_types',
        'icon_url' => '/5/52/Grid_Oak_Wood.png',
        'subtypes' => array(
            0 => array('name' => 'oak_wood', 'avail' => true, 'icon_url' => '/5/52/Grid_Oak_Wood.png',),
            1 => array('name' => 'spruce_wood', 'avail' => true, 'icon_url' => '/c/ca/Grid_Spruce_Wood.png'),
            2 => array('name' => 'birch_wood', 'avail' => true, 'icon_url' => '/5/56/Grid_Birch_Wood.png'),
            3 => array('name' => 'jungle_wood', 'avail' => true, 'icon_url' => '/d/d3/Grid_Jungle_Wood.png'),
            /*4 => array('name' => 'Oak wood facing East/West', 'avail' => false, 'icon_url' => '/5/52/Grid_Oak_Wood.png'),
            5 => array('name' => 'Spruce wood facing East/West', 'avail' => false, 'icon_url' => '/c/ca/Grid_Spruce_Wood.png'),
            6 => array('name' => 'Birch wood facing East/West', 'avail' => false, 'icon_url' => '/5/56/Grid_Birch_Wood.png'),
            7 => array('name' => 'Jungle wood facing East/West', 'avail' => false, 'icon_url' => '/d/d3/Grid_Jungle_Wood.png'),
            8 => array('name' => 'Oak wood facing North/South', 'avail' => false, 'icon_url' => '/5/52/Grid_Oak_Wood.png'),
            9 => array('name' => 'Spruce wood facing North/South', 'avail' => false, 'icon_url' => '/c/ca/Grid_Spruce_Wood.png'),
            10 => array('name' => 'Birch wood facing North/South', 'avail' => false, 'icon_url' => '/5/56/Grid_Birch_Wood.png'),
            11 => array('name' => 'Jungle wood facing North/South', 'avail' => false, 'icon_url' => '/d/d3/Grid_Jungle_Wood.png'),
            12 => array('name' => 'Oak wood with only bark', 'avail' => false, 'icon_url' => '/5/52/Grid_Oak_Wood.png'),
            13 => array('name' => 'Spruce wood with only bark', 'avail' => false, 'icon_url' => '/c/ca/Grid_Spruce_Wood.png'),
            14 => array('name' => 'Birch wood with only bark', 'avail' => false, 'icon_url' => '/5/56/Grid_Birch_Wood.png'),
            15 => array('name' => 'Jungle wood with only bark', 'avail' => false, 'icon_url' => '/d/d3/Grid_Jungle_Wood.png'),
             *
             */
        ),
    ),
    'leaves' => array(
        'id' => 18,
        'stack' => 64,
        'avail' => true,
        'group' => 'leave_types',
        'icon_url' => '/e/e5/Grid_Oak_Leaves.png',
        'subtypes' => array(
            0 => array('name' => 'oak_leaves', 'avail' => true, 'icon_url' => '/e/e5/Grid_Oak_Leaves.png'),
            1 => array('name' => 'spruce_leaves', 'avail' => true, 'icon_url' => '/e/ed/Grid_Spruce_Leaves.png'),
            2 => array('name' => 'birch_leaves', 'avail' => true, 'icon_url' => '/3/39/Grid_Birch_Leaves.png'),
            3 => array('name' => 'jungle_leaves', 'avail' => true, 'icon_url' => '/7/76/Grid_Jungle_Leaves.png'),
            4 => array('name' => 'oak_leaves_no_decay', 'avail' => true, 'icon_url' => '/e/e5/Grid_Oak_Leaves.png'),
            5 => array('name' => 'spruce_leaves_no_decay', 'avail' => true, 'icon_url' => '/e/ed/Grid_Spruce_Leaves.png'),
            6 => array('name' => 'birch_leaves_no_decay', 'avail' => true, 'icon_url' => '/3/39/Grid_Birch_Leaves.png'),
            7 => array('name' => 'jungle_leaves_no_decay', 'avail' => true, 'icon_url' => '/7/76/Grid_Jungle_Leaves.png'),
            8 => array('name' => 'oak_leaves_check_decay', 'avail' => true, 'icon_url' => '/e/e5/Grid_Oak_Leaves.png'),
            9 => array('name' => 'spruce_leaves_check_decay', 'avail' => true, 'icon_url' => '/e/ed/Grid_Spruce_Leaves.png'),
            10 => array('name' => 'birch_leaves_check_decay', 'avail' => true, 'icon_url' => '/3/39/Grid_Birch_Leaves.png'),
            11 => array('name' => 'jungle_leaves_check_decay', 'avail' => true, 'icon_url' => '/7/76/Grid_Jungle_Leaves.png'),
            12 => array('name' => 'oak_leaves_no_decay_and_check_decay', 'avail' => true, 'icon_url' => '/e/e5/Grid_Oak_Leaves.png'),
            13 => array('name' => 'spruce_leaves_no_decay_and_check_decay', 'avail' => true, 'icon_url' => '/e/ed/Grid_Spruce_Leaves.png'),
            14 => array('name' => 'birch_leaves_no_decay_and_check_decay', 'avail' => true, 'icon_url' => '/3/39/Grid_Birch_Leaves.png'),
            15 => array('name' => 'jungle_leaves_no_decay_and_check_decay', 'avail' => true, 'icon_url' => '/7/76/Grid_Jungle_Leaves.png'),
        ),
    ),
    'sponge' => array(
        'id' => 19,
        'stack' => 64,
        'avail' => false,
        'group' => 'sponge_types',
        'icon_url' => '/5/56/Grid_Sponge.png',
        'subtypes' => array(
            0 => array('name' => 'sponge', 'avail' => false, 'icon_url' => '/5/56/Grid_Sponge.png'),
            1 => array('name' => 'wet_sponge', 'avail' => false, 'icon_url' => '/4/4e/Grid_Wet_Sponge.png'),
        ),
    ),
    'glass' => array(
        'id' => 20,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/0/00/Grid_Glass.png',
    ),
    'lapis_ore' => array(
        'id' => 21,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/7/76/Grid_Lapis_Lazuli.png',
    ),
    'lapis_block' => array(
        'id' => 22,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/2/27/Grid_Lapis_Lazuli_Block.png',
    ),
    'dispenser' => array(
        'id' => 23,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/c/cc/Grid_Dispenser.png',
    ),
    'sandstone' => array(
        'id' => 24,
        'stack' => 64,
        'avail' => true,
        'group' => 'sandstone_types',
        'icon_url' => '/1/12/Grid_Sandstone.png',
        'subtypes' => array(
            0 => array('name' => 'sandstone', 'avail' => true, 'icon_url' => '/1/12/Grid_Sandstone.png'),
            1 => array('name' => 'chiseled_sandstone', 'avail' => true, 'icon_url' => '/5/52/Grid_Chiseled_Sandstone.png'),
            2 => array('name' => 'smooth_sandstone', 'avail' => true, 'icon_url' => '/2/22/Grid_Smooth_Sandstone.png'),
        ),
    ),
    'noteblock' => array(
        'id' => 25,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/1/1d/Grid_Note_Block.png',
    ),
    'bed' => array(
        'id' => 26,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/c/c5/Bed.png',
    ),
    'golden_rail' => array(
        'id' => 27,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/9/9d/Grid_Powered_Rail.png',
    ),
    'detector_rail' => array(
        'id' => 28,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/a/a2/Grid_Detector_Rail.png',
    ),
    'sticky_piston' => array(
        'id' => 29,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/3/32/Grid_Sticky_Piston.png',
    /*    'subtypes' => array(
            0 => array('name' => 'sticky_piston', 'avail' => true, 'icon_url' => '/3/32/Grid_Sticky_Piston.png'),
            7 => array('name' => 'sticky_piston', 'avail' => true, 'icon_url' => '/3/32/Grid_Sticky_Piston.png'),
        ),
     */
    ),
    'web' => array(
        'id' => 30,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/3/36/Grid_Cobweb.png',
    ),
    'tallgrass' => array(
        'id' => 31,
        'stack' => 64,
        'avail' => true,
        'group' => 'grass_types',
        'icon_url' => '/6/67/Grid_Shrub.png',
        'subtypes' => array(
            0 => array('name' => 'tall_grass', 'avail' => true, 'icon_url' => '/6/67/Grid_Shrub.png'),
            1 => array('name' => 'grass', 'avail' => true, 'icon_url' => '/6/6f/Grid_Grass.png'),
            2 => array('name' => 'fern', 'avail' => true, 'icon_url' => '/4/43/Grid_Fern.png'),
        ),
    ),
    'deadbush' => array(
        'id' => 32,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/f/f6/Grid_Dead_Bush.png',
    ),
    'piston' => array(
        'id' => 33,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/3/3c/Grid_Piston.png',
        /*
        'subtypes' => array(
            0 => array('name' => 'piston', 'avail' => true, 'icon_url' => '/3/3c/Grid_Piston.png'),
            7 => array('name' => 'piston', 'avail' => true, 'icon_url' => '/3/3c/Grid_Piston.png'),
        ),
         */
    ),
    'piston_head' => array(
        'id' => 34,
        'stack' => 1,
        'avail' => false,
        'icon_url' => '/3/3c/Grid_Piston.png',
    ),
    'wool' => array(
        'id' => 35,
        'stack' => 64,
        'avail' => true,
        'group' => 'wool_types',
        'icon_url' => '/f/fa/Grid_White_Wool.png',
        'subtypes' => array(
            0 => array('name' => 'white_wool', 'avail' => true, 'icon_url' => '/f/fa/Grid_White_Wool.png'),
            1 => array('name' => 'orange_wool', 'avail' => true, 'icon_url' => '/0/07/Grid_Orange_Wool.png'),
            2 => array('name' => 'magenta_wool', 'avail' => true, 'icon_url' => '/f/fd/Grid_Magenta_Wool.png'),
            3 => array('name' => 'light_blue_wool', 'avail' => true, 'icon_url' => '/f/fb/Grid_Light_Blue_Wool.png'),
            4 => array('name' => 'yellow_wool', 'avail' => true, 'icon_url' => '/e/ed/Grid_Yellow_Wool.png'),
            5 => array('name' => 'lime_wool', 'avail' => true, 'icon_url' => '/a/a2/Grid_Lime_Wool.png'),
            6 => array('name' => 'pink_wool', 'avail' => true, 'icon_url' => '/b/b3/Grid_Pink_Wool.png'),
            7 => array('name' => 'gray_wool', 'avail' => true, 'icon_url' => '/d/d3/Grid_Gray_Wool.png'),
            8 => array('name' => 'light_gray_wool', 'avail' => true, 'icon_url' => '/e/e5/Grid_Light_Gray_Wool.png'),
            9 => array('name' => 'cyan_wool', 'avail' => true, 'icon_url' => '/c/cd/Grid_Cyan_Wool.png'),
            10 => array('name' => 'purple_wool', 'avail' => true, 'icon_url' => '/5/51/Grid_Purple_Wool.png'),
            11 => array('name' => 'blue_wool', 'avail' => true, 'icon_url' => '/4/40/Grid_Blue_Wool.png'),
            12 => array('name' => 'brown_wool', 'avail' => true, 'icon_url' => '/2/2a/Grid_Brown_Wool.png'),
            13 => array('name' => 'green_wool', 'avail' => true, 'icon_url' => '/f/fa/Grid_Green_Wool.png'),
            14 => array('name' => 'red_wool', 'avail' => true, 'icon_url' => '/2/2a/Grid_Red_Wool.png'),
            15 => array('name' => 'black_wool', 'avail' => true, 'icon_url' => '/4/45/Grid_Black_Wool.png'),
        ),
    ),
    'piston_extension' => array(
        'id' => 36,
        'stack' => 1,
        'avail' => false,
        'icon_url' => '?',
    ),
    'yellow_flower' => array(
        'id' => 37,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/4/49/Grid_Dandelion.png',
    ),
    'red_flower' => array(
        'id' => 38,
        'stack' => 64,
        'avail' => true,
        'group' => 'Flower Types',
        'icon_url' => '/c/c7/Grid_Poppy.png',
        'subtypes' => array(
            0 => array('name' => 'red_flower', 'avail' => true, 'icon_url' => '/c/c7/Grid_Poppy.png'),
            1 => array('name' => 'blue_orchid', 'avail' => true, 'icon_url' => '/5/56/Grid_Blue_Orchid.png'),
            2 => array('name' => 'allium', 'avail' => true, 'icon_url' => '/3/33/Grid_Allium.png'),
            3 => array('name' => 'azure_bluet', 'avail' => true, 'icon_url' => '/7/79/Grid_Azure_Bluet.png'),
            4 => array('name' => 'red_tulip', 'avail' => true, 'icon_url' => '/6/6f/Grid_Red_Tulip.png'),
            5 => array('name' => 'orange_tulip', 'avail' => true, 'icon_url' => '/e/ec/Grid_Orange_Tulip.png'),
            6 => array('name' => 'white_tulip', 'avail' => true, 'icon_url' => '/4/4f/Grid_White_Tulip.png'),
            7 => array('name' => 'pink_tulip', 'avail' => true, 'icon_url' => '/6/61/Grid_Pink_Tulip.png'),
            8 => array('name' => 'oxeye_daisy', 'avail' => true, 'icon_url' => '/f/f6/Grid_Oxeye_Daisy.png'),
        ),
    ),
    'brown_mushroom' => array(
        'id' => 39,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/d/d8/Grid_Brown_Mushroom.png',
    ),
    'red_mushroom' => array(
        'id' => 40,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/0/01/Grid_Red_Mushroom.png',
    ),
    'gold_block' => array(
        'id' => 41,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/9/96/Grid_Block_of_Gold.png',
    ),
    'iron_block' => array(
        'id' => 42,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/3/34/Grid_Block_of_Iron.png',
    ),
    'double_stone_slab' => array(
        'id' => 43,
        'stack' => 64,
        'avail' => false,
        'group' => 'double_slab_types',
        'icon_url' => '?',
        'subtypes' => array(
            0 => array('name' => 'double_stone_slab', 'avail' => false, 'icon_url' => '?'),
            1 => array('name' => 'double_sandstone_slab', 'avail' => false, 'icon_url' => '?'),
            2 => array('name' => 'double_(stone)_wooden_slab', 'avail' => false, 'icon_url' => '?'),
            3 => array('name' => 'double_cobblestone_slab', 'avail' => false, 'icon_url' => '?'),
            4 => array('name' => 'double_bricks_slab', 'avail' => false, 'icon_url' => '?'),
            5 => array('name' => 'double_stone_brick_slab', 'avail' => false, 'icon_url' => '?'),
            6 => array('name' => 'double_nether_brick_slab', 'avail' => false, 'icon_url' => '?'),
            7 => array('name' => 'double_quartz_slab', 'avail' => false, 'icon_url' => '?'),
            8 => array('name' => 'full_stone_slab', 'avail' => false, 'icon_url' => '?'),
            9 => array('name' => 'full_sandstone_slab', 'avail' => false, 'icon_url' => '?'),
        ),
    ),
    'stone_slab' => array(
        'id' => 44,
        'stack' => 64,
        'avail' => true,
        'group' => 'stone_slab_types',
        'icon_url' => '/2/29/Grid_Stone_Slab.png',
        'subtypes' => array(
            0 => array('name' => 'stone_slab', 'avail' => true, 'icon_url' => '/2/29/Grid_Stone_Slab.png'),
            1 => array('name' => 'sandstone_slab', 'avail' => true, 'icon_url' => '/2/2a/Grid_Sandstone_Slab.png'),
            2 => array('name' => '(stone)_wooden_slab', 'avail' => true, 'icon_url' => '/5/5c/Grid_Oak_Wood_Slab.png'),
            3 => array('name' => 'cobblestone_slab', 'avail' => true, 'icon_url' => '/f/f7/Grid_Cobblestone_Slab.png'),
            4 => array('name' => 'bricks_slab', 'avail' => true, 'icon_url' => '/b/b3/Grid_Bricks_Slab.png'),
            5 => array('name' => 'stone_brick_slab', 'avail' => true, 'icon_url' => '/4/46/Grid_Stone_Bricks_Slab.png'),
            6 => array('name' => 'nether_brick_slab', 'avail' => true, 'icon_url' => '/d/dd/Grid_Nether_Brick_Slab.png'),
            7 => array('name' => 'quartz_slab', 'avail' => true, 'icon_url' => '/0/09/Grid_Quartz_Slab.png'),
            8 => array('name' => 'upside-down_stone_slab', 'avail' => true, 'icon_url' => '/2/29/Grid_Stone_Slab.png'),
            9 => array('name' => 'upside-down_sandstone_slab', 'avail' => true, 'icon_url' => '/2/2a/Grid_Sandstone_Slab.png'),
            10 => array('name' => 'upside-down_(stone)_wooden_slab', 'avail' => true, 'icon_url' => '/5/5c/Grid_Oak_Wood_Slab.png'),
            11 => array('name' => 'upside-down_cobblestone_slab', 'avail' => true, 'icon_url' => '/f/f7/Grid_Cobblestone_Slab.png'),
            12 => array('name' => 'upside-down_bricks_slab', 'avail' => true, 'icon_url' => '/b/b3/Grid_Bricks_Slab.png'),
            13 => array('name' => 'upside-down_stone_brick_slab', 'avail' => true, 'icon_url' => '/4/46/Grid_Stone_Bricks_Slab.png'),
            14 => array('name' => 'upside-down_nether_brick_slab', 'avail' => true, 'icon_url' => '/d/dd/Grid_Nether_Brick_Slab.png'),
            15 => array('name' => 'upside-down_quartz_slab', 'avail' => true, 'icon_url' => '/0/09/Grid_Quartz_Slab.png'),
        ),
    ),
    'brick_block' => array(
        'id' => 45,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/4/43/Grid_Bricks.png',
    ),
    'tnt' => array(
        'id' => 46,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/2/2b/Grid_TNT.png',
    ),
    'bookshelf' => array(
        'id' => 47,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/b/bc/Grid_Bookshelf.png',
    ),
    'mossy_cobblestone' => array(
        'id' => 48,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/7/7d/Grid_Moss_Stone.png',
    ),
    'obsidian' => array(
        'id' => 49,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/5/5d/Grid_Obsidian.png',
    ),
    'torch' => array(
        'id' => 50,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/3/32/Grid_Torch.png',
    ),
    'fire' => array(
        'id' => 51,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/2/21/Grid_Fire.gif',
    ),
    'mob_spawner' => array(
        'id' => 52,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/3/3b/Grid_Monster_Spawner.png',
    ),
    'oak_stairs' => array(
        'id' => 53,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/6/6b/Grid_Oak_Wood_Stairs.png',
    ),
    'chest' => array(
        'id' => 54,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/c/c1/Grid_Chest.png',
    ),
    'redstone_wire' => array(
        'id' => 55,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/f/fd/Grid_Redstone.png',
    ),
    'diamond_ore' => array(
        'id' => 56,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/7/71/Grid_Diamond_Ore.png',
    ),
    'diamond_block' => array(
        'id' => 57,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/f/f2/Grid_Block_of_Diamond.png',
    ),
    'crafting_table' => array(
        'id' => 58,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/7/79/Grid_Crafting_Table.png',
    ),
    'wheat_block' => array(
        'id' => 59,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/c/c4/Grid_Wheat.png',
    ),
    'farmland' => array(
        'id' => 60,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/6/6a/Grid_Farmland.png',
    ),
    'furnace' => array(
        'id' => 61,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/2/2e/Grid_Furnace.png',
    ),
    'lit_furnace' => array(
        'id' => 62,
        'stack' => 1,
        'avail' => false,
        'icon_url' => '/2/2e/Grid_Furnace.png',
    ),
    'standing_sign' => array(
        'id' => 63,
        'stack' => 1,
        'avail' => false,
        'icon_url' => '/0/06/Grid_Sign.png',
    ),
    'oak_door' => array(
        'id' => 64,
        'stack' => 1,
        'avail' => false,
        'icon_url' => '/f/fb/Grid_Oak_Door.png',
    ),
    'ladder' => array(
        'id' => 65,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/f/fb/Grid_Ladder.png',
    ),
    'rail' => array(
        'id' => 66,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/b/b6/Grid_Rail.png',
    ),
    'stone_stairs' => array(
        'id' => 67,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/7/7f/Grid_Cobblestone_Stairs.png',
    ),
    'wall_sign' => array(
        'id' => 68,
        'stack' => 1,
        'avail' => false,
        'icon_url' => '/0/06/Grid_Sign.png',
    ),
    'lever' => array(
        'id' => 69,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/3/33/Grid_Lever.png',
    ),
    'stone_pressure_plate' => array(
        'id' => 70,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/4/46/Grid_Stone_Pressure_Plate.png',
    ),
    'iron_door_block' => array(
        'id' => 71,
        'stack' => 1,
        'avail' => false,
        'icon_url' => '/2/27/Grid_Iron_Door.png',
    ),
    'wooden_pressure_plate' => array(
        'id' => 72,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/d/d4/Grid_Wooden_Pressure_Plate.png',
    ),
    'redstone_ore' => array(
        'id' => 73,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/a/a9/Grid_Redstone_Ore.png',
    ),
    'lit_redstone_ore' => array(
        'id' => 74,
        'stack' => 1,
        'avail' => false,
        'icon_url' => '/d/d9/Grid_Redstone_Torch.png',
    ),
    'unlit_redstone_torch' => array(
        'id' => 75,
        'stack' => 1,
        'avail' => false,
        'icon_url' => '/d/d9/Grid_Redstone_Torch.png',
    ),
    'redstone_torch' => array(
        'id' => 76,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/d/d9/Grid_Redstone_Torch.png',
    ),
    'stone_button' => array(
        'id' => 77,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/8/81/Grid_Stone_Button.png',
    ),
    'snow_layer' => array(
        'id' => 78,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '?',
    ),
    'ice' => array(
        'id' => 79,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/2/2e/Grid_Ice.png',
    ),
    'snow_block' => array(
        'id' => 80,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/6/6c/Grid_Snow.png',
    ),
    'cactus' => array(
        'id' => 81,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/f/fb/Grid_Cactus.png',
    ),
    'clayblock' => array(
        'id' => 82,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/0/06/Grid_Clay_%28block%29.png',
    ),
    'reeds' => array(
        'id' => 83,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/7/79/Grid_Sugar_Canes.png',
    ),
    'jukebox' => array(
        'id' => 84,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/1/10/Grid_Jukebox.png',
    ),
    'fence' => array(
        'id' => 85,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/c/c8/Grid_Fence.png',
    ),
    'pumpkin' => array(
        'id' => 86,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/b/be/Grid_Pumpkin.png',
    ),
    'netherrack' => array(
        'id' => 87,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/8/86/Grid_Netherrack.png',
    ),
    'soul_sand' => array(
        'id' => 88,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/e/e4/Grid_Soul_Sand.png',
    ),
    'glowstone' => array(
        'id' => 89,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/f/f8/Grid_Glowstone.png',
    ),
    'portal' => array(
        'id' => 90,
        'stack' => 1,
        'avail' => false,
        'icon_url' => '/8/8f/Grid_Portal.png',
    ),
    'lit_pumpkin' => array(
        'id' => 91,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/9/95/Grid_Jack_o%27Lantern.png',
    ),
    'cake_block' => array(
        'id' => 92,
        'stack' => 1,
        'avail' => false,
        'icon_url' => '/2/28/Grid_Cake.png',
    ),
    'unpowered_repeater' => array(
        'id' => 93,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/e/e6/Grid_Redstone_Repeater.png',
    ),
    'powered_repeater' => array(
        'id' => 94,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/e/e6/Grid_Redstone_Repeater.png',
    ),
    'stained_glass' => array(
        'id' => 95,
        'stack' => 64,
        'avail' => true,
        'group' => 'stained_glass_types',
        'icon_url' => '/9/92/Grid_White_Stained_Glass.png',
        'subtypes' => array(
            0 => array('name' => 'white_glass', 'avail' => true, 'icon_url' => '/9/92/Grid_White_Stained_Glass.png'),
            1 => array('name' => 'orange_glass', 'avail' => true, 'icon_url' => '/b/bd/Grid_Orange_Stained_Glass.png'),
            2 => array('name' => 'magenta_glass', 'avail' => true, 'icon_url' => '/4/4d/Grid_Magenta_Stained_Glass.png'),
            3 => array('name' => 'light_blue_glass', 'avail' => true, 'icon_url' => '/2/25/Grid_Light_Blue_Stained_Glass.png'),
            4 => array('name' => 'yellow_glass', 'avail' => true, 'icon_url' => '/5/55/Grid_Yellow_Stained_Glass.png'),
            5 => array('name' => 'lime_glass', 'avail' => true, 'icon_url' => '/a/a5/Grid_Lime_Stained_Glass.png'),
            6 => array('name' => 'pink_glass', 'avail' => true, 'icon_url' => '/7/71/Grid_Pink_Stained_Glass.png'),
            7 => array('name' => 'gray_glass', 'avail' => true, 'icon_url' => '/b/bb/Grid_Gray_Stained_Glass.png'),
            8 => array('name' => 'light_gray_glass', 'avail' => true, 'icon_url' => '/2/26/Grid_Light_Gray_Stained_Glass.png'),
            9 => array('name' => 'cyan_glass', 'avail' => true, 'icon_url' => '/f/f3/Grid_Cyan_Stained_Glass.png'),
            10 => array('name' => 'purple_glass', 'avail' => true, 'icon_url' => '/b/b8/Grid_Purple_Stained_Glass.png'),
            11 => array('name' => 'blue_glass', 'avail' => true, 'icon_url' => '/3/35/Grid_Blue_Stained_Glass.png'),
            12 => array('name' => 'brown_glass', 'avail' => true, 'icon_url' => '/2/29/Grid_Brown_Stained_Glass.png'),
            13 => array('name' => 'green_glass', 'avail' => true, 'icon_url' => '/5/5f/Grid_Green_Stained_Glass.png'),
            14 => array('name' => 'red_glass', 'avail' => true, 'icon_url' => '/d/d8/Grid_Red_Stained_Glass.png'),
            15 => array('name' => 'black_glass', 'avail' => true, 'icon_url' => '/9/9f/Grid_Black_Stained_Glass.png'),
        ),
    ),
    'trapdoor' => array(
        'id' => 96,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/b/b2/Grid_Trapdoor.png',
        'subtypes' => array(
            0 => array('name' => 'trapdoor', 'avail' => true, 'icon_url' => '/b/b2/Grid_Trapdoor.png'),
            1 => array('name' => 'trapdoor', 'avail' => true, 'icon_url' => '/b/b2/Grid_Trapdoor.png'),
            2 => array('name' => 'trapdoor', 'avail' => true, 'icon_url' => '/b/b2/Grid_Trapdoor.png'),
            3 => array('name' => 'trapdoor', 'avail' => true, 'icon_url' => '/b/b2/Grid_Trapdoor.png'),
        ),
    ),
    'monster_egg' => array(
        'id' => 97,
        'stack' => 64,
        'avail' => true,
        'group' => 'monster_egg_types',
        'icon_url' => '/a/a6/Grid_Stone.png',
        'subtypes' => array(
            0 => array('name' => 'stone_monster_egg', 'avail' => true, 'icon_url' => '/a/a6/Grid_Stone.png'),
            1 => array('name' => 'cobblestone_monster_egg', 'avail' => true, 'icon_url' => '/0/03/Grid_Cobblestone.png'),
            2 => array('name' => 'stone_brick_monster_egg', 'avail' => true, 'icon_url' => '/8/84/Grid_Stone_Bricks.png'),
            3 => array('name' => 'mossy_stone_brick_monster_egg', 'avail' => true, 'icon_url' => '/9/99/Grid_Mossy_Stone_Bricks.png'),
            4 => array('name' => 'cracked_stone_brick_monster_egg', 'avail' => true, 'icon_url' => '/d/da/Grid_Cracked_Stone_Bricks.png'),
            5 => array('name' => 'chiseled_stone_brick_monster_egg', 'avail' => true, 'icon_url' => '/4/4b/Grid_Chiseled_Stone_Bricks.png'),
        ),
    ),
    'stonebrick' => array(
        'id' => 98,
        'stack' => 64,
        'avail' => true,
        'group' => 'stone_brick_types',
        'icon_url' => '/8/84/Grid_Stone_Bricks.png',
        'subtypes' => array(
            0 => array('name' => 'stone_brick', 'avail' => true, 'icon_url' => '/8/84/Grid_Stone_Bricks.png'),
            1 => array('name' => 'mossy_stone_brick', 'avail' => true, 'icon_url' => '/9/99/Grid_Mossy_Stone_Bricks.png'),
            2 => array('name' => 'cracked_stone_brick', 'avail' => true, 'icon_url' => '/d/da/Grid_Cracked_Stone_Bricks.png'),
            3 => array('name' => 'chiseled_stone_brick', 'avail' => true, 'icon_url' => '/4/4b/Grid_Chiseled_Stone_Bricks.png'),
        ),
    ),
    'brown_mushroom_block' => array(
        'id' => 99,
        'stack' => 64,
        'avail' => true,
        'group' => 'brown_mushroom_block_types',
        'icon_url' => '/9/97/Grid_Brown_Mushroom_(block).png',
        'subtypes' => array(
            0 => array('name' => 'brown_mushroom_block_(pores_on_all_sides)', 'avail' => true, 'icon_url' =>  '/9/97/Grid_Brown_Mushroom_(block).png'),
            1 => array('name' => 'brown_mushroom_block_(cap_texture_on_top,_west_and_north)', 'avail' => true, 'icon_url' => '/9/97/Grid_Brown_Mushroom_(block).png'),
            2 => array('name' => 'brown_mushroom_block_(cap_texture_on_top_and_north)', 'avail' => true, 'icon_url' => '/9/97/Grid_Brown_Mushroom_(block).png'),
            3 => array('name' => 'brown_mushroom_block_(cap_texture_on_top,_north_and_east)', 'avail' => true, 'icon_url' => '/9/97/Grid_Brown_Mushroom_(block).png'),
            4 => array('name' => 'brown_mushroom_block_(cap_texture_on_top_and_west)', 'avail' => true, 'icon_url' => '/9/97/Grid_Brown_Mushroom_(block).png'),
            5 => array('name' => 'brown_mushroom_block_(cap_texture_on_top)', 'avail' => true, 'icon_url' => '/9/97/Grid_Brown_Mushroom_(block).png'),
            6 => array('name' => 'brown_mushroom_block_(cap_texture_on_top_and_east)', 'avail' => true, 'icon_url' => '/9/97/Grid_Brown_Mushroom_(block).png'),
            7 => array('name' => 'brown_mushroom_block_(cap_texture_on_top,_south_and_west)', 'avail' => true, 'icon_url' => '/9/97/Grid_Brown_Mushroom_(block).png'),
            8 => array('name' => 'brown_mushroom_block_(cap_texture_on_top_and_south)', 'avail' => true, 'icon_url' => '/9/97/Grid_Brown_Mushroom_(block).png'),
            9 => array('name' => 'brown_mushroom_block_(cap_texture_on_top,_east_and_south)', 'avail' => true, 'icon_url' => '/9/97/Grid_Brown_Mushroom_(block).png'),
            10 => array('name' => 'brown_mushroom_block_(stem_texture on all_four_sides,_pores_on_top_and_bottom)', 'avail' => true, 'icon_url' => '/9/97/Grid_Brown_Mushroom_(block).png'),
            14 => array('name' => 'brown_mushroom_block_(cap_texture_on_all_six_sides)', 'avail' => true, 'icon_url' => '/9/97/Grid_Brown_Mushroom_(block).png'),
            15 => array('name' => 'brown_mushroom_block_(stem_texture_on_all_six_sides)', 'avail' => true, 'icon_url' => '/9/97/Grid_Brown_Mushroom_(block).png'),
        ),
    ),
    'red_mushroom_block' => array(
        'id' => 100,
        'stack' => 64,
        'avail' => true,
        'group' => 'red_mushroom_block_types',
        'icon_url' => '/f/f9/Grid_Red_Mushroom_(block).png',
        'subtypes' => array(
            0 => array('name' => 'red_mushroom_block_(pores_on_all_sides)', 'avail' => true, 'icon_url' =>  '/f/f9/Grid_Red_Mushroom_(block).png'),
            1 => array('name' => 'red_mushroom_block_(cap_texture_on_top,_west_and_north)', 'avail' => true, 'icon_url' => '/f/f9/Grid_Red_Mushroom_(block).png'),
            2 => array('name' => 'red_mushroom_block_(cap_texture_on_top_and_north)', 'avail' => true, 'icon_url' => '/f/f9/Grid_Red_Mushroom_(block).png'),
            3 => array('name' => 'red_mushroom_block_(cap_texture_on_top,_north_and_east)', 'avail' => true, 'icon_url' => '/f/f9/Grid_Red_Mushroom_(block).png'),
            4 => array('name' => 'red_mushroom_block_(cap_texture_on_top_and_west)', 'avail' => true, 'icon_url' => '/f/f9/Grid_Red_Mushroom_(block).png'),
            5 => array('name' => 'red_mushroom_block_(cap_texture_on_top)', 'avail' => true, 'icon_url' => '/f/f9/Grid_Red_Mushroom_(block).png'),
            6 => array('name' => 'red_mushroom_block_(cap_texture_on_top_and_east)', 'avail' => true, 'icon_url' => '/f/f9/Grid_Red_Mushroom_(block).png'),
            7 => array('name' => 'red_mushroom_block_(cap_texture_on_top,_south_and_west)', 'avail' => true, 'icon_url' => '/f/f9/Grid_Red_Mushroom_(block).png'),
            8 => array('name' => 'red_mushroom_block_(cap_texture_on_top and south)', 'avail' => true, 'icon_url' => '/f/f9/Grid_Red_Mushroom_(block).png'),
            9 => array('name' => 'red_mushroom_block_(cap_texture_on_top,_east_and_south)', 'avail' => true, 'icon_url' => '/f/f9/Grid_Red_Mushroom_(block).png'),
            10 => array('name' => 'red_mushroom_block_(stem_texture on all_four_sides,_pores_on_top_and_bottom)', 'avail' => true, 'icon_url' => '/f/f9/Grid_Red_Mushroom_(block).png'),
            14 => array('name' => 'red_mushroom_block_(cap_texture_on all six sides)', 'avail' => true, 'icon_url' => '/f/f9/Grid_Red_Mushroom_(block).png'),
            15 => array('name' => 'red_mushroom_block_(stem_texture_on_all_six_sides)', 'avail' => true, 'icon_url' => '/f/f9/Grid_Red_Mushroom_(block).png'),
        ),
    ),
    'iron_bars' => array(
        'id' => 101,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/0/00/Grid_Iron_Bars.png',
    ),
    'glass_pane' => array(
        'id' => 102,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/3/30/Grid_Glass_Pane.png',
    ),
    'melon_block' => array(
        'id' => 103,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/e/ee/Grid_Melon_%28block%29.png',
    ),
    'pumpkin_stem' => array(
        'id' => 104,
        'stack' => 1,
        'avail' => false,
        'icon_url' => '?',
    ),
    'melon_stem' => array(
        'id' => 105,
        'stack' => 1,
        'avail' => false,
        'icon_url' => '?',
    ),
    'vine' => array(
        'id' => 106,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/2/29/Grid_Vines.png',
        /*'subtypes' => array(
            0 => array('name' => 'vine', 'avail' => true, 'icon_url' => '/2/29/Grid_Vines.png'),
            1 => array('name' => 'vine', 'avail' => true, 'icon_url' => '/2/29/Grid_Vines.png'),
            2 => array('name' => 'vine', 'avail' => true, 'icon_url' => '/2/29/Grid_Vines.png'),
            4 => array('name' => 'vine', 'avail' => true, 'icon_url' => '/2/29/Grid_Vines.png'),
            8 => array('name' => 'vine', 'avail' => true, 'icon_url' => '/2/29/Grid_Vines.png'),
        ),*/
    ),
    'fence_gate' => array(
        'id' => 107,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/c/c2/Grid_Fence_Gate.png',
    ),
    'brick_stairs' => array(
        'id' => 108,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/3/3c/Grid_Brick_Stairs.png',
    ),
    'stone_brick_stairs' => array(
        'id' => 109,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/a/af/Grid_Stone_Brick_Stairs.png',
    ),
    'mycelium' => array(
        'id' => 110,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/a/aa/Grid_Mycelium.png',
    ),
    'waterlily' => array(
        'id' => 111,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/4/49/Grid_Lily_Pad.png',
    ),
    'nether_brick' => array(
        'id' => 112,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/6/62/Grid_Nether_Brick.png',
    ),
    'nether_brick_fence' => array(
        'id' => 113,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/5/59/Grid_Nether_Brick_Fence.png',
    ),
    'nether_brick_stairs' => array(
        'id' => 114,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/6/68/Grid_Nether_Brick_Stairs.png',
    ),
    'nether_wart_block' => array(
        'id' => 115,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/7/70/Grid_Brewing_Stand.png',
    ),
    'enchanting_table' => array(
        'id' => 116,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/0/00/Grid_Enchantment_Table.png',
    ),
    'brewing_stand' => array( // unobtainable item, but shows up in block logs
        'id' => 117,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/7/70/Grid_Brewing_Stand.png',
    ),
    'cauldron_block' => array(
        'id' => 118,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/c/ca/Grid_Cauldron.png',
    ),
    'end_portal' => array(
        'id' => 119,
        'stack' => 1,
        'avail' => false,
        'icon_url' => '?',
    ),
    'end_portal_frame' => array(
        'id' => 120,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/f/f5/Grid_End_Portal_%28block%29.png',
    ),
    'end_stone' => array(
        'id' => 121,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/5/50/Grid_End_Stone.png',
    ),
    'dragon_egg' => array(
        'id' => 122,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/b/b1/Grid_Dragon_Egg.png',
    ),
    'redstone_lamp' => array(
        'id' => 123,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/f/fa/Grid_Redstone_Lamp.png',
    ),
    'lit_redstone_lamp' => array(
        'id' => 124,
        'stack' => 1,
        'avail' => false,
        'icon_url' => '/f/fa/Grid_Redstone_Lamp.png',
    ),
    'double_wooden_slab' => array(
        'id' => 125,
        'stack' => 1,
        'avail' => false,
        'icon_url' => '?',
        'subtypes' => array(
            0 => array('name' => 'double_oak_wood_slab', 'avail' => false, 'icon_url' => '?'),
            1 => array('name' => 'double_spruce_wood_slab', 'avail' => false, 'icon_url' => '?'),
            2 => array('name' => 'double_birch_wood_slab', 'avail' => false, 'icon_url' => '?'),
            3 => array('name' => 'double_jungle_wood_slab', 'avail' => false, 'icon_url' => '?'),
            4 => array('name' => 'double_acacia_wood_slab', 'avail' => false, 'icon_url' => '?'),
            5 => array('name' => 'double_dark_oak_wood_slab', 'avail' => false, 'icon_url' => '?'),
        ),
    ),
    'wooden_slab' => array(
        'id' => 126,
        'stack' => 64,
        'avail' => true,
        'group' => 'wooden_slab_types',
        'icon_url' => '/5/5c/Grid_Oak_Wood_Slab.png',
        'subtypes' => array(
            0 => array('name' => 'oak_wood_slab', 'avail' => true, 'icon_url' => '/5/5c/Grid_Oak_Wood_Slab.png'),
            1 => array('name' => 'spruce_wood_slab', 'avail' => true, 'icon_url' => '/6/60/Grid_Spruce_Wood_Slab.png'),
            2 => array('name' => 'birch_wood_slab', 'avail' => true, 'icon_url' => '/b/bc/Grid_Birch_Wood_Slab.png'),
            3 => array('name' => 'jungle_wood_slab', 'avail' => true, 'icon_url' => '/3/3f/Grid_Jungle_Wood_Slab.png'),
            4 => array('name' => 'acacia_wood_slab', 'avail' => true, 'icon_url' => '/b/b3/Grid_Acacia_Wood_Slab.png'),
            5 => array('name' => 'dark_oak_wood_slab', 'avail' => true, 'icon_url' => '/8/86/Grid_Dark_Oak_Wood_Slab.png'),
            8 => array('name' => 'upside-down_oak_wood_slab', 'avail' => true, 'icon_url' => '/5/5c/Grid_Oak_Wood_Slab.png'),
            9 => array('name' => 'upside-down_spruce_wood_slab', 'avail' => true, 'icon_url' => '/6/60/Grid_Spruce_Wood_Slab.png'),
            10 => array('name' => 'upside-down_birch_wood_slab', 'avail' => true, 'icon_url' => '/b/bc/Grid_Birch_Wood_Slab.png'),
            11 => array('name' => 'upside-down_jungle_wood_slab', 'avail' => true, 'icon_url' => '/3/3f/Grid_Jungle_Wood_Slab.png'),
            12 => array('name' => 'upside-down_acacia_wood_slab', 'avail' => true, 'icon_url' => '/b/b3/Grid_Acacia_Wood_Slab.png'),
            13 => array('name' => 'upside-down_dark_oak_wood_slab', 'avail' => true, 'icon_url' => '/8/86/Grid_Dark_Oak_Wood_Slab.png'),
        ),
    ),
    'cocoa' => array(
        'id' => 127,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/7/7d/Grid_Cocoa_Beans.png',
    ),
    'sandstone_stairs' => array(
        'id' => 128,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/0/0d/Grid_Sandstone_Stairs.png',
    ),
    'emerald_ore' => array(
        'id' => 129,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/e/e7/Grid_Emerald_Ore.png',
    ),
    'ender_chest' => array(
        'id' => 130,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/5/56/Grid_Ender_Chest.png',
    ),
    'tripwire_hook' => array(
        'id' => 131,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/9/90/Grid_Tripwire_Hook.png',
    ),
    'tripwire' => array(
        'id' => 132,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/f/fa/Grid_String.png',
        'subtypes' => array(
            0 => array('name' => 'tripwire', 'avail' => false, 'icon_url' => '/f/fa/Grid_String.png'),
            2 => array('name' => 'tripwire', 'avail' => false, 'icon_url' => '/f/fa/Grid_String.png'),
        ),
    ),
    'emerald_block' => array(
        'id' => 133,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/2/27/Grid_Block_of_Emerald.png',
    ),
    'spruce_stairs' => array(
        'id' => 134,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/2/24/Grid_Spruce_Wood_Stairs.png',
    ),
    'birch_stairs' => array(
        'id' => 135,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/d/de/Grid_Birch_Wood_Stairs.png',
    ),
    'jungle_stairs' => array(
        'id' => 136,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/b/b9/Grid_Jungle_Wood_Stairs.png',
    ),
    'command_block' => array(
        'id' => 137,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/0/07/Grid_Command_Block.png',
    ),
    'beacon' => array(
        'id' => 138,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/c/cb/Grid_Beacon.png',
    ),
    'cobblestone_wall' => array(
        'id' => 139,
        'stack' => 64,
        'avail' => true,
        'group' => 'cobblestone_wall_types',
        'icon_url' => '/a/a9/Grid_Cobblestone_Wall.png',
        'subtypes' => array(
            0 => array('name' => 'cobblestone_wall', 'avail' => true, 'icon_url' => '/a/a9/Grid_Cobblestone_Wall.png'),
            1 => array('name' => 'mossy_cobblestone_wall', 'avail' => true, 'icon_url' => '/6/63/Grid_Mossy_Cobblestone_Wall.png'),
        ),
    ),
    'flower_pot' => array(
        'id' => 140,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/8/89/Grid_Flower_Pot.png',
    ),
    'carrots' => array(
        'id' => 141,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/8/8b/Grid_Carrot.png',
    ),
    'potatoes' => array(
        'id' => 142,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/2/2b/Grid_Potato.png',
    ),
    'wooden_button' => array(
        'id' => 143,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/c/c6/Grid_Wooden_Button.png',
    ),
    'skull' => array(
        'id' => 144,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/c/c9/Grid_Skeleton_Skull.png',
    ),
    'anvil' => array(
        'id' => 145,
        'stack' => 64,
        'avail' => true,
        'group' => 'anvil_types',
        'icon_url' => '/1/13/Grid_Anvil.png',
        'subtypes' => array(
            0 => array('name' => 'anvil', 'avail' => true, 'icon_url' => '/1/13/Grid_Anvil.png'),
            1 => array('name' => 'slightly_damaged_anvil', 'avail' => true, 'icon_url' => '/1/13/Grid_Anvil.png'),
            2 => array('name' => 'very_damaged_anvil', 'avail' => true, 'icon_url' => '/1/13/Grid_Anvil.png'),
        ),
    ),
    'trapped_chest' => array(
        'id' => 146,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/b/bb/Grid_Trapped_Chest.png',
    ),
    'light_weighted_pressure_plate' => array(
        'id' => 147,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/b/b9/Grid_Weighted_Pressure_Plate_(Light).png',
    ),
    'heavy_weighted_pressure_plate' => array(
        'id' => 148,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/6/65/Grid_Weighted_Pressure_Plate_%28Heavy%29.png',
    ),
    'unpowered_comparator' => array(
        'id' => 149,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/e/ea/Grid_Redstone_Comparator.png',
    ),
    'powered_comparator' => array(
        'id' => 150,
        'stack' => 1,
        'avail' => false,
        'icon_url' => '/e/ea/Grid_Redstone_Comparator.png',
    ),
    'daylight_detector' => array(
        'id' => 151,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/1/18/Grid_Daylight_Sensor.png',
    ),
    'redstone_block' => array(
        'id' => 152,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/2/2e/Grid_Block_of_Redstone.png',
    ),
    'quartz_ore' => array(
        'id' => 153,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/8/82/Grid_Nether_Quartz_Ore.png',
    ),
    'hopper' => array(
        'id' => 154,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/c/cb/Grid_Hopper.png',
    ),
    'quartz_block' => array(
        'id' => 155,
        'stack' => 64,
        'avail' => true,
        'group' => 'quartz_block_types',
        'icon_url' => '/0/04/Grid_Block_of_Quartz.png',
        'subtypes' => array(
            0 => array('name' => 'quartz_block', 'avail' => true, 'icon_url' => '/0/04/Grid_Block_of_Quartz.png'),
            1 => array('name' => 'chiseled_quartz_block', 'avail' => true, 'icon_url' => '/8/8d/Grid_Chiseled_Quartz_Block.png'),
            2 => array('name' => 'pillar_quartz_block', 'avail' => true, 'icon_url' => '/5/59/Grid_Pillar_Quartz_Block.png'),
            3 => array('name' => 'pillar_quartz_block', 'avail' => true, 'icon_url' => '/5/59/Grid_Pillar_Quartz_Block.png'),
            4 => array('name' => 'pillar_quartz_block', 'avail' => true, 'icon_url' => '/5/59/Grid_Pillar_Quartz_Block.png'),
        ),
    ),
    'quartz_stairs' => array(
        'id' => 156,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/c/c5/Grid_Quartz_Stairs.png',
    ),
    'activator_rail' => array(
        'id' => 157,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/5/50/Grid_Activator_Rail.png',
    ),
    'dropper' => array(
        'id' => 158,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/7/7f/Grid_Dropper.png',
    ),
    'stained_hardened_clay' => array(
        'id' => 159,
        'stack' => 64,
        'avail' => true,
        'group' => 'clay_types',
        'icon_url' => '/6/6b/Grid_White_Stained_Clay.png',
        'subtypes' => array(
            0 => array('name' => 'white_clay', 'avail' => true, 'icon_url' => '/6/6b/Grid_White_Stained_Clay.png'),
            1 => array('name' => 'orange_clay', 'avail' => true, 'icon_url' => '/3/3c/Grid_Orange_Stained_Clay.png'),
            2 => array('name' => 'magenta_clay', 'avail' => true, 'icon_url' => '/c/ce/Grid_Magenta_Stained_Clay.png'),
            3 => array('name' => 'light_blue_clay', 'avail' => true, 'icon_url' => '/a/a0/Grid_Light_Blue_Stained_Clay.png'),
            4 => array('name' => 'yellow_clay', 'avail' => true, 'icon_url' => '/e/ef/Grid_Yellow_Stained_Clay.png'),
            5 => array('name' => 'lime_clay', 'avail' => true, 'icon_url' => '/a/aa/Grid_Lime_Stained_Clay.png'),
            6 => array('name' => 'pink_clay', 'avail' => true, 'icon_url' => '/6/68/Grid_Pink_Stained_Clay.png'),
            7 => array('name' => 'gray_clay', 'avail' => true, 'icon_url' => '/1/18/Grid_Gray_Stained_Clay.png'),
            8 => array('name' => 'light_gray_clay', 'avail' => true, 'icon_url' => '/4/47/Grid_Light_Gray_Stained_Clay.png'),
            9 => array('name' => 'cyan_clay', 'avail' => true, 'icon_url' => '/7/78/Grid_Cyan_Stained_Clay.png'),
            10 => array('name' => 'purple_clay', 'avail' => true, 'icon_url' => '/6/6a/Grid_Purple_Stained_Clay.png'),
            11 => array('name' => 'blue_clay', 'avail' => true, 'icon_url' => '/9/91/Grid_Blue_Stained_Clay.png'),
            12 => array('name' => 'brown_clay', 'avail' => true, 'icon_url' => '/2/20/Grid_Brown_Stained_Clay.png'),
            13 => array('name' => 'green_clay', 'avail' => true, 'icon_url' => '/1/19/Grid_Green_Stained_Clay.png'),
            14 => array('name' => 'red_clay', 'avail' => true, 'icon_url' => '/c/cd/Grid_Red_Stained_Clay.png'),
            15 => array('name' => 'black_clay', 'avail' => true, 'icon_url' => '/9/91/Grid_Black_Stained_Clay.png'),
        ),
    ),
    'stained_glass_pane' => array(
        'id' => 160,
        'stack' => 64,
        'avail' => true,
        'group' => 'glass_types',
        'icon_url' => '/5/55/Grid_White_Stained_Glass_Pane.png',
        'subtypes' => array(
            0 => array('name' => 'white_glass_pane', 'avail' => true, 'icon_url' => '/5/55/Grid_White_Stained_Glass_Pane.png'),
            1 => array('name' => 'orange_glass_pane', 'avail' => true, 'icon_url' => '/f/f1/Grid_Orange_Stained_Glass_Pane.png'),
            2 => array('name' => 'magenta_glass_pane', 'avail' => true, 'icon_url' => '/9/91/Grid_Magenta_Stained_Glass_Pane.png'),
            3 => array('name' => 'light_blue_glass_pane', 'avail' => true, 'icon_url' => '/2/2a/Grid_Light_Blue_Stained_Glass_Pane.png'),
            4 => array('name' => 'yellow_glass_pane', 'avail' => true, 'icon_url' => '/6/6e/Grid_Yellow_Stained_Glass_Pane.png'),
            5 => array('name' => 'lime_glass_pane', 'avail' => true, 'icon_url' => '/a/ae/Grid_Lime_Stained_Glass_Pane.png'),
            6 => array('name' => 'pink_glass_pane', 'avail' => true, 'icon_url' => '/8/86/Grid_Pink_Stained_Glass_Pane.png'),
            7 => array('name' => 'gray_glass_pane', 'avail' => true, 'icon_url' => '/f/f0/Grid_Gray_Stained_Glass_Pane.png'),
            8 => array('name' => 'light_gray_glass_pane', 'avail' => true, 'icon_url' => '/7/79/Grid_Light_Gray_Stained_Glass_Pane.png'),
            9 => array('name' => 'cyan_glass_pane', 'avail' => true, 'icon_url' => '/9/97/Grid_Cyan_Stained_Glass_Pane.png'),
            10 => array('name' => 'purple_glass_pane', 'avail' => true, 'icon_url' => '/4/41/Grid_Purple_Stained_Glass_Pane.png'),
            11 => array('name' => 'blue_glass_pane', 'avail' => true, 'icon_url' => '/f/fe/Grid_Blue_Stained_Glass_Pane.png'),
            12 => array('name' => 'brown_glass_pane', 'avail' => true, 'icon_url' => '/c/ce/Grid_Brown_Stained_Glass_Pane.png'),
            13 => array('name' => 'green_glass_pane', 'avail' => true, 'icon_url' => '/9/9c/Grid_Green_Stained_Glass_Pane.png'),
            14 => array('name' => 'red_glass_pane', 'avail' => true, 'icon_url' => '/7/71/Grid_Red_Stained_Glass_Pane.png'),
            15 => array('name' => 'black_glass_pane', 'avail' => true, 'icon_url' => '/7/7b/Grid_Black_Stained_Glass_Pane.png'),
        ),
    ),
    'leaves2' => array(
        'id' => 161,
        'stack' => 64,
        'avail' => true,
        'group' => 'leaves_types_(2)',
        'icon_url' => '/7/76/Grid_Acacia_Leaves.png',
        'subtypes' => array(
            0 => array('name' => 'acacia_leaves', 'avail' => true, 'icon_url' => '/7/76/Grid_Acacia_Leaves.png',),
            1 => array('name' => 'dark_oak_leaves', 'avail' => true, 'icon_url' => '/5/57/Grid_Dark_Oak_Leaves.png'),
            4 => array('name' => 'acacia_leaves_no_decay', 'avail' => true, 'icon_url' => '/7/76/Grid_Acacia_Leaves.png'),
            5 => array('name' => 'dark_oak_leaves_no_decay', 'avail' => true, 'icon_url' => '/7/76/Grid_Acacia_Leaves.png'),
            8 => array('name' => 'acacia_leaves_check_decay', 'avail' => true, 'icon_url' => '/7/76/Grid_Acacia_Leaves.png'),
            9 => array('name' => 'dark_oak_leaves_check_decay', 'avail' => true, 'icon_url' => '/7/76/Grid_Acacia_Leaves.png'),
            12 => array('name' => 'acacia_leaves_check_decay_and_check_decay', 'avail' => true, 'icon_url' => '/7/76/Grid_Acacia_Leaves.png'),
            13 => array('name' => 'dark_oak_leaves_check_decay_and_check_decay', 'avail' => true, 'icon_url' => '/7/76/Grid_Acacia_Leaves.png'),
        ),
    ),
    'log2' => array(
        'id' => 162,
        'stack' => 64,
        'avail' => true,
        'group' => 'log_types_(2)',
        'icon_url' => '/d/d6/Grid_Acacia_Wood.png',
        'subtypes' => array(
            0 => array('name' => 'acacia_wood', 'avail' => true, 'icon_url' => '/d/d6/Grid_Acacia_Wood.png',),
            1 => array('name' => 'dark_oak_wood', 'avail' => true, 'icon_url' => '/e/ec/Grid_Dark_Oak_Wood.png'),
            4 => array('name' => 'acacia_wood_east_west', 'avail' => true, 'icon_url' => '/d/d6/Grid_Acacia_Wood.png'),
            5 => array('name' => 'dark_oak_wood_east_west', 'avail' => true, 'icon_url' => '/e/ec/Grid_Dark_Oak_Wood.png'),
            8 => array('name' => 'acacia_wood_north_south', 'avail' => true, 'icon_url' => '/d/d6/Grid_Acacia_Wood.png'),
            9 => array('name' => 'dark_oak_wood_north_south', 'avail' => true, 'icon_url' => '/e/ec/Grid_Dark_Oak_Wood.png'),
            12 => array('name' => 'acacia_wood_only_bark', 'avail' => true, 'icon_url' => '/d/d6/Grid_Acacia_Wood.png'),
            13 => array('name' => 'dark_oak_wood_only_bark', 'avail' => true, 'icon_url' => '/e/ec/Grid_Dark_Oak_Wood.png'),
        ),
    ),
    'acacia_stairs' => array(
        'id' => 163,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/8/85/Grid_Acacia_Wood_Stairs.png',
    ),
    'dark_oak_stairs' => array(
        'id' => 164,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/5/5c/Grid_Dark_Oak_Wood_Stairs.png',
    ),
    'slime_block' => array(
        'id' => 165,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/f/ff/Grid_Slime_Block.png',
    ),
    'barrier' => array(
        'id' => 166,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/0/00/Grid_Enchantment_Table.png',
    ),
    'iron_trapdoor' => array(
        'id' => 167,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/a/ab/Grid_Iron_Trapdoor.png',
    ),
    'prismarine' => array(
        'id' => 168,
        'stack' => 64,
        'avail' => true,
        'group' => 'prismarine_types',
        'icon_url' => '/4/48/Grid_Prismarine.gif',
        'subtypes' => array(
            0 => array('name' => 'prismarine', 'avail' => true, 'icon_url' => '/4/48/Grid_Prismarine.gif'),
            1 => array('name' => 'prismarine_bricks', 'avail' => true, 'icon_url' => '/8/86/Grid_Prismarine_Bricks.png'),
            2 => array('name' => 'dark_prismarine', 'avail' => true, 'icon_url' => '/7/79/Grid_Dark_Prismarine.png'),
        ),
    ),
    'sea_lantern' => array(
        'id' => 169,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/f/f1/Grid_Sea_Lantern.gif',
    ),
    'hay_block' => array(
        'id' => 170,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/1/1f/Grid_Hay_Bale.png',
    ),
    'carpet' => array(
        'id' => 171,
        'stack' => 64,
        'avail' => true,
        'group' => 'carpet_types',
        'icon_url' => '/7/72/Grid_White_Carpet.png',
        'subtypes' => array(
            0 => array('name' => 'white_carpet', 'avail' => true, 'icon_url' => '/7/72/Grid_White_Carpet.png'),
            1 => array('name' => 'orange_carpet', 'avail' => true, 'icon_url' => '/3/3c/Grid_Orange_Carpet.png'),
            2 => array('name' => 'magenta_carpet', 'avail' => true, 'icon_url' => '/0/01/Grid_Magenta_Carpet.png'),
            3 => array('name' => 'light_blue_carpet', 'avail' => true, 'icon_url' => '/4/4b/Grid_Light_Blue_Carpet.png'),
            4 => array('name' => 'yellow_carpet', 'avail' => true, 'icon_url' => '/6/63/Grid_Yellow_Carpet.png'),
            5 => array('name' => 'lime_carpet', 'avail' => true, 'icon_url' => '/3/34/Grid_Lime_Carpet.png'),
            6 => array('name' => 'pink_carpet', 'avail' => true, 'icon_url' => '/b/be/Grid_Pink_Carpet.png'),
            7 => array('name' => 'gray_carpet', 'avail' => true, 'icon_url' => '/2/2b/Grid_Gray_Carpet.png'),
            8 => array('name' => 'light_gray_carpet', 'avail' => true, 'icon_url' => '/3/3a/Grid_Light_Gray_Carpet.png'),
            9 => array('name' => 'cyan_carpet', 'avail' => true, 'icon_url' => '/d/d4/Grid_Cyan_Carpet.png'),
            10 => array('name' => 'purple_carpet', 'avail' => true, 'icon_url' => '/5/55/Grid_Purple_Carpet.png'),
            11 => array('name' => 'blue_carpet', 'avail' => true, 'icon_url' => '/5/5a/Grid_Blue_Carpet.png'),
            12 => array('name' => 'brown_carpet', 'avail' => true, 'icon_url' => '/a/ad/Grid_Brown_Carpet.png'),
            13 => array('name' => 'green_carpet', 'avail' => true, 'icon_url' => '/4/41/Grid_Green_Carpet.png'),
            14 => array('name' => 'red_carpet', 'avail' => true, 'icon_url' => '/d/dc/Grid_Red_Carpet.png'),
            15 => array('name' => 'black_carpet', 'avail' => true, 'icon_url' => '/e/e6/Grid_Black_Carpet.png'),
        ),
    ),
    'hardened_clay' => array(
        'id' => 172,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/6/6d/Grid_Hardened_Clay.png',
    ),
    'coal_block' => array(
        'id' => 173,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/b/b2/Grid_Block_of_Coal.png',
    ),
    'packed_ice' => array(
        'id' => 174,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/b/b2/Grid_Packed_Ice.png',
    ),
    'double_plant' => array(
        'id' => 175,
        'stack' => 64,
        'avail' => true,
        'group' => 'tall_flower_types',
        'icon_url' => '/b/bc/Grid_Sunflower.png',
        'subtypes' => array(
            0 => array('name' => 'sunflower', 'avail' => true, 'icon_url' => '/b/bc/Grid_Sunflower.png'),
            1 => array('name' => 'lilac', 'avail' => true, 'icon_url' => '/0/0a/Grid_Lilac.png'),
            2 => array('name' => 'double_tallgrass', 'avail' => true, 'icon_url' => '/c/c4/Grid_Double_Tallgrass.png'),
            3 => array('name' => 'large_fern', 'avail' => true, 'icon_url' => '/d/d9/Grid_Large_Fern.png'),
            4 => array('name' => 'rose_bush', 'avail' => true, 'icon_url' => '/c/c3/Grid_Rose_Bush.png'),
            5 => array('name' => 'peony', 'avail' => true, 'icon_url' => '/e/eb/Grid_Peony.png'),
            8 => array('name' => 'plant_top_half', 'avail' => false, 'icon_url' => '?'),
        ),
    ),
    'standing_banner' => array(
        'id' => 176,
        'stack' => 16,
        'avail' => true,
        'icon_url' => '/2/24/Grid_White_Banner.png',
    ),
    'wall_banner' => array(
        'id' => 177,
        'stack' => 16,
        'avail' => true,
        'icon_url' => '/2/24/Grid_White_Banner.png',
    ),
    'daylight_detector_inverted' => array(
        'id' => 178,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/1/18/Grid_Daylight_Sensor.png',
    ),
    'red_sandstone' => array(
        'id' => 179,
        'stack' => 64,
        'avail' => true,
        'group' => 'red_sandstone_types',
        'icon_url' => '/e/ec/Grid_Red_Sandstone.png',
        'subtypes' => array(
            0 => array('name' => 'red_sandstone', 'avail' => true, 'icon_url' => '/e/ec/Grid_Red_Sandstone.png'),
            1 => array('name' => 'chiseled_red_sandstone', 'avail' => true, 'icon_url' => '/f/f8/Grid_Chiseled_Red_Sandstone.png'),
            2 => array('name' => 'smooth__red_sandstone', 'avail' => true, 'icon_url' => '/0/0e/Grid_Smooth_Red_Sandstone.png'),
        ),
    ),
    'red_sandstone_stairs' => array(
        'id' => 180,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/3/3a/Grid_Red_Sandstone_Stairs.png',
    ),
    'double_stone_slab2' => array(
        'id' => 181,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/7/72/Grid_Red_Sandstone_Slab.png',
    ),
    'stone_slab2' => array(
        'id' => 182,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/7/72/Grid_Red_Sandstone_Slab.png',
    ),
    'spruce_fence_gate' => array(
        'id' => 183,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/0/08/Grid_Spruce_Fence_Gate.png',
    ),
    'birch_fence_gate' => array(
        'id' => 184,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/e/e4/Grid_Birch_Fence_Gate.png',
    ),
    'jungle_fence_gate' => array(
        'id' => 185,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/5/50/Grid_Jungle_Fence_Gate.png',
    ),
    'dark_oak_fence_gate' => array(
        'id' => 186,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/f/f4/Grid_Dark_Oak_Fence_Gate.png',
    ),
    'acacia_fence_gate' => array(
        'id' => 187,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/2/2e/Grid_Acacia_Fence_Gate.png',
    ),
    'spruce_fence' => array(
        'id' => 188,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/9/9d/Grid_Spruce_Fence.png',
    ),
    'birch_fence' => array(
        'id' => 189,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/3/34/Grid_Birch_Fence.png',
    ),
    'jungle_fence' => array(
        'id' => 190,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/0/0f/Grid_Jungle_Fence.png',
    ),
    'dark_oak_fence' => array(
        'id' => 191,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/e/ea/Grid_Dark_Oak_Fence.png',
    ),
    'acacia_fence' => array(
        'id' => 192,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/f/f2/Grid_Acacia_Fence.png',
    ),
    'spruce_door_block' => array(
        'id' => 193,
        'stack' => 1,
        'avail' => false,
        'icon_url' => '/b/be/Grid_Spruce_Door.png',
    ),
    'birch_door_block' => array(
        'id' => 194,
        'stack' => 1,
        'avail' => false,
        'icon_url' => '/f/f3/Grid_Birch_Door.png',
    ),
    'jungle_door_block' => array(
        'id' => 195,
        'stack' => 1,
        'avail' => false,
        'icon_url' => '/0/05/Grid_Jungle_Door.png',
    ),
    'acacia_door_block' => array(
        'id' => 196,
        'stack' => 1,
        'avail' => false,
        'icon_url' => '/d/d3/Grid_Acacia_Door.png',
    ),
    'dark_oak_door_block' => array(
        'id' => 197,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/e/e2/Grid_Dark_Oak_Door.png',
    ),
    'end_rod' => array(
        'id' => 198,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/0/0b/End_Rod.png',
    ),
    'chorus_plant' => array(
        'id' => 199,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/4/4b/Chorus_Tree.png',
    ),
    'chorus_flower' => array(
        'id' => 200,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/7/73/Chorus_Flower.png',
    ),
    'purpur_block' => array(
        'id' => 201,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/f/fd/Purpur_Block.png',
    ),
    'purpur_pillar' => array(
        'id' => 202,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/e/e6/Purpur_Pillar.png',
    ),
    'purpur_stairs' => array(
        'id' => 203,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/8/8e/Purpur_Stairs.png',
    ),
    'purpur_double_slab' => array(
        'id' => 204,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/c/c3/Purpur_Slab.png',
    ),
    'purpur_slab' => array(
        'id' => 205,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/c/c3/Purpur_Slab.png',
    ),
    'end_bricks' => array(
        'id' => 206,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/e/ea/End_Stone_Bricks.png',
    ),
    'grass_path' => array(
        'id' => 208,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/a/a9/Grass_Path.png',
    ),
    'end_gateway' => array(
        'id' => 209,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/6/6e/End_Gateway_(block).png',
    ),
    'frosted_ice' => array(
        'id' => 212,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/7/77/Ice.png',
    ),
    'magma' => array(
        'id' => 213,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/e/eb/Magma_Block.gif',
    ),
    'nether_wart_block' => array(
        'id' => 214,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/c/c0/Nether_Wart_Block.png',
    ),
    'red_nether_brick' => array(
        'id' => 215,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/9/94/Red_Nether_Brick.png',
    ),
    'bone_block' => array(
        'id' => 216,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/0/02/Bone_Block.png',
    ),
    'structure_void' => array(
        'id' => 217,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/e/e8/Structure_Block_Corner.png',
    ),
    'observer' => array(
        'id' => 218,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/d/d3/Observer.png',
    ),
    'white_shulker_box' => array(
        'id' => 219,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/8/80/White_Shulker_Box.png',
    ),
    'orange_shulker_box' => array(
        'id' => 220,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
        'icon_url' => '/d/d2/Orange_Shulker_Box.png',
    ),
    'magenta_shulker_box' => array(
        'id' => 221,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
        'icon_url' => '/b/bd/Magenta_Shulker_Box.png',
    ),
    'light_blue_shulker_box' => array(
        'id' => 222,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
        'icon_url' => '/d/d8/Light_Blue_Shulker_Box.png',
    ),
    'yellow_shulker_box' => array(
        'id' => 223,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
        'icon_url' => '/f/f9/Yellow_Shulker_Box.png',
    ),
    'lime_shulker_box' => array(
        'id' => 224,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
        'icon_url' => '/7/7a/Lime_Shulker_Box.png',
    ),
    'pink_shulker_box' => array(
        'id' => 225,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
        'icon_url' => '/f/fd/Pink_Shulker_Box.png',
    ),
    'gray_shulker_box' => array(
        'id' => 226,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
        'icon_url' => '/7/73/Gray_Shulker_Box.png',
    ),
    'light_gray_shulker_box' => array(
        'id' => 227,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
        'icon_url' => '/6/6c/Light_Gray_Shulker_Box.png',
    ),
    'cyan_shulker_box' => array(
        'id' => 228,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
        'icon_url' => '/7/76/Cyan_Shulker_Box.png',
    ),
    'purple_shulker_box' => array(
        'id' => 229,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
        'icon_url' => '/f/f9/Purple_Shulker_Box.png',
    ),
    'blue_shulker_box' => array(
        'id' => 230,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
        'icon_url' => '/e/e9/Blue_Shulker_Box.png',
    ),
    'brown_shulker_box' => array(
        'id' => 231,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
        'icon_url' => '/7/73/Brown_Shulker_Box.png',
    ),
    'green_shulker_box' => array(
        'id' => 232,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
        'icon_url' => '/2/2a/Green_Shulker_Box.png',
    ),
    'red_shulker_box' => array(
        'id' => 233,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
        'icon_url' => '/f/f2/Red_Shulker_Box.png',
    ),
    'black_shulker_box' => array(
        'id' => 234,
        'stack' => 64,
        'avail' => true,
        'notrade' => true,
        'icon_url' => '/e/e2/Black_Shulker_Box.png',
    ),
    'structure_block' => array(
        'id' => 255,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/4/49/Structure_Block_Save.png',
    ),


    /*************************************************/
    /*                ITEMS                          */
    /*************************************************/

    'iron_shovel' => array(
        'id' => 256,
        'damage' => 251,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/0/01/Grid_Iron_Shovel.png',
    ),
    'iron_pickaxe' => array(
        'id' => 257,
        'damage' => 251,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/1/1d/Grid_Iron_Pickaxe.png',
    ),
    'iron_axe' => array(
        'id' => 258,
        'damage' => 251,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/1/1d/Grid_Iron_Axe.png',
    ),
    'flint_and_steel' => array(
        'id' => 259,
        'damage' => 65,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/f/fb/Grid_Flint_and_Steel.png',
    ),
    'apple' => array(
        'id' => 260,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/8/83/Grid_Apple.png',
    ),
    'bow' => array(
        'id' => 261,
        'damage' => 385,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/4/49/Grid_Bow.png',
    ),
    'arrow' => array(
        'id' => 262,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/c/cc/Grid_Arrow.png',
    ),
    'coal' => array(
        'id' => 263,
        'stack' => 64,
        'avail' => true,
        'group' => 'coal_types',
        'icon_url' => '/a/ad/Grid_Coal.png',
        'subtypes' => array(
            0 => array('name' => 'coal', 'avail' => true, 'icon_url' => '/a/ad/Grid_Coal.png',),
            1 => array('name' => 'charcoal', 'avail' => true, 'icon_url' => '/5/58/Grid_Charcoal.png'),
        ),
    ),
    'diamond' => array(
        'id' => 264,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/7/72/Grid_Diamond.png',
    ),
    'iron_ingot' => array(
        'id' => 265,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/6/6b/Grid_Iron_Ingot.png',
    ),
    'gold_ingot' => array(
        'id' => 266,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/4/40/Grid_Gold_Ingot.png',
    ),
    'iron_sword' => array(
        'id' => 267,
        'damage' => 251,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/d/d5/Grid_Iron_Sword.png',
    ),
    'wooden_sword' => array(
        'id' => 268,
        'damage' => 60,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/c/cf/Grid_Wooden_Sword.png',
    ),
    'wooden_shovel' => array(
        'id' => 269,
        'damage' => 60,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/2/21/Grid_Wooden_Shovel.png',
    ),
    'wooden_pickaxe' => array(
        'id' => 270,
        'damage' => 60,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/3/3d/Grid_Wooden_Pickaxe.png',
    ),
    'wooden_axe' => array(
        'id' => 271,
        'damage' => 60,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/2/2a/Grid_Wooden_Axe.png',
    ),
    'stone_sword' => array(
        'id' => 272,
        'damage' => 132,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/e/e1/Grid_Stone_Sword.png',
    ),
    'stone_shovel' => array(
        'id' => 273,
        'damage' => 132,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/f/ff/Grid_Stone_Shovel.png',
    ),
    'stone_pickaxe' => array(
        'id' => 274,
        'damage' => 132,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/d/d2/Grid_Stone_Pickaxe.png',
    ),
    'stone_axe' => array(
        'id' => 275,
        'damage' => 132,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/5/55/Grid_Stone_Axe.png',
    ),
    'diamond_sword' => array(
        'id' => 276,
        'damage' => 1562,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/8/81/Grid_Diamond_Sword.png',
    ),
    'diamond_shovel' => array(
        'id' => 277,
        'damage' => 1562,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/8/8e/Grid_Diamond_Shovel.png',
    ),
    'diamond_pickaxe' => array(
        'id' => 278,
        'damage' => 1562,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/c/ce/Grid_Diamond_Pickaxe.png',
    ),
    'diamond_axe' => array(
        'id' => 279,
        'damage' => 1562,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/8/8d/Grid_Diamond_Axe.png',
    ),
    'stick' => array(
        'id' => 280,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/e/e9/Grid_Stick.png',
    ),
    'bowl' => array(
        'id' => 281,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/f/f1/Grid_Bowl.png',
    ),
    'mushroom_stew' => array(
        'id' => 282,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/f/fd/Grid_Mushroom_Stew.png',
    ),
    'golden_sword' => array(
        'id' => 283,
        'damage' => 33,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/6/65/Grid_Golden_Sword.png',
    ),
    'golden_shovel' => array(
        'id' => 284,
        'damage' => 33,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/9/9d/Grid_Golden_Shovel.png',
    ),
    'golden_pickaxe' => array(
        'id' => 285,
        'damage' => 33,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/3/3f/Grid_Golden_Pickaxe.png',
    ),
    'golden_axe' => array(
        'id' => 286,
        'damage' => 33,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/9/93/Grid_Golden_Axe.png',
    ),
    'string' => array(
        'id' => 287,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/f/fa/Grid_String.png',
    ),
    'feather' => array(
        'id' => 288,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/a/a7/Grid_Feather.png',
    ),
    'gunpowder' => array(
        'id' => 289,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/7/7a/Grid_Gunpowder.png',
    ),
    'wooden_hoe' => array(
        'id' => 290,
        'damage' => 60,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/e/ea/Grid_Wooden_Hoe.png',
    ),
    'stone_hoe' => array(
        'id' => 291,
        'damage' => 132,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/b/ba/Grid_Stone_Hoe.png',
    ),
    'iron_hoe' => array(
        'id' => 292,
        'damage' => 251,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/f/f8/Grid_Iron_Hoe.png',
    ),
    'diamond_hoe' => array(
        'id' => 293,
        'damage' => 1562,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/c/c9/Grid_Diamond_Hoe.png',
    ),
    'golden_hoe' => array(
        'id' => 294,
        'damage' => 33,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/7/77/Grid_Golden_Hoe.png',
    ),
    'wheat_seeds' => array(
        'id' => 295,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/6/68/Grid_Seeds.png',
    ),
    'wheat' => array(
        'id' => 296,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/c/c4/Grid_Wheat.png',
    ),
    'bread' => array(
        'id' => 297,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/d/d4/Grid_Bread.png',
    ),
    'leather_helmet' => array(
        'id' => 298,
        'damage' => 56,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/2/24/Grid_Leather_Cap.png',
    ),
    'leather_chestplate' => array(
        'id' => 299,
        'damage' => 81,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/e/ed/Grid_Leather_Tunic.png',
    ),
    'leather_leggings' => array(
        'id' => 300,
        'damage' => 76,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/c/ce/Grid_Leather_Pants.png',
    ),
    'leather_boots' => array(
        'id' => 301,
        'damage' => 66,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/0/06/Grid_Leather_Boots.png',
    ),
    'chainmail_helmet' => array(
        'id' => 302,
        'damage' => 166,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/c/c3/Grid_Chain_Helmet.png',
    ),
    'chainmail_chestplate' => array(
        'id' => 303,
        'damage' => 241,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/7/77/Grid_Chain_Chestplate.png',
    ),
    'chainmail_leggings' => array(
        'id' => 304,
        'damage' => 226,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/2/26/Grid_Chain_Leggings.png',
    ),
    'chainmail_boots' => array(
        'id' => 305,
        'damage' => 196,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/9/93/Grid_Chain_Boots.png',
    ),
    'iron_helmet' => array(
        'id' => 306,
        'damage' => 166,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/e/ec/Grid_Iron_Helmet.png',
    ),
    'iron_chestplate' => array(
        'id' => 307,
        'damage' => 241,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/8/8d/Grid_Iron_Chestplate.png',
    ),
    'iron_leggings' => array(
        'id' => 308,
        'damage' => 226,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/9/99/Grid_Iron_Leggings.png',
    ),
    'iron_boots' => array(
        'id' => 309,
        'damage' => 196,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/f/f5/Grid_Iron_Boots.png',
    ),
    'diamond_helmet' => array(
        'id' => 310,
        'damage' => 364,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/b/bd/Grid_Diamond_Helmet.png',
    ),
    'diamond_chestplate' => array(
        'id' => 311,
        'damage' => 529,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/e/e7/Grid_Diamond_Chestplate.png',
    ),
    'diamond_leggings' => array(
        'id' => 312,
        'damage' => 496,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/e/e3/Grid_Diamond_Leggings.png',
    ),
    'diamond_boots' => array(
        'id' => 313,
        'damage' => 430,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/d/d1/Grid_Diamond_Boots.png',
    ),
    'golden_helmet' => array(
        'id' => 314,
        'damage' => 78,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/4/45/Grid_Golden_Helmet.png',
    ),
    'golden_chestplate' => array(
        'id' => 315,
        'damage' => 113,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/6/67/Grid_Golden_Chestplate.png',
    ),
    'golden_leggings' => array(
        'id' => 316,
        'damage' => 106,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/f/f8/Grid_Golden_Leggings.png',
    ),
    'golden_boots' => array(
        'id' => 317,
        'damage' => 92,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/f/fb/Grid_Golden_Boots.png',
    ),
    'flint' => array(
        'id' => 318,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/8/82/Grid_Flint.png',
    ),
    'porkchop' => array(
        'id' => 319,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/e/ec/Grid_Raw_Porkchop.png',
    ),
    'cooked_porkchop' => array(
        'id' => 320,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/b/bd/Grid_Cooked_Porkchop.png',
    ),
    'painting' => array(
        'id' => 321,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/c/cc/Grid_Painting.png',
    ),
    'golden_apple' => array(
        'id' => 322,
        'stack' => 64,
        'avail' => true,
        'group' => 'golden_apple_types',
        'icon_url' => '/4/4e/Grid_Golden_Apple.png',
        'subtypes' => array(
            0 => array('name' => 'golden_apple', 'avail' => true, 'icon_url' => '/4/4e/Grid_Golden_Apple.png',),
            1 => array('name' => 'enchanted_golden_apple', 'avail' => true, 'icon_url' => '/4/4e/Grid_Golden_Apple.png'),
        ),
    ),
    'sign' => array(
        'id' => 323,
        'stack' => 16,
        'avail' => true,
        'icon_url' => '/0/06/Grid_Sign.png',
    ),
    'wooden_door' => array(
        'id' => 324,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/f/fb/Grid_Oak_Door.png',
    ),
    'bucket' => array(
        'id' => 325,
        'stack' => 16,
        'avail' => true,
        'icon_url' => '/2/2a/Grid_Bucket.png',
    ),
    'water_bucket' => array(
        'id' => 326,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/b/bf/Grid_Water_Bucket.png',
    ),
    'lava_bucket' => array(
        'id' => 327,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/c/cf/Grid_Lava_Bucket.png',
    ),
    'minecart' => array(
        'id' => 328,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/5/58/Grid_Minecart.png',
    ),
    'saddle' => array(
        'id' => 329,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/0/09/Grid_Saddle.png',
    ),
    'iron_door' => array(
        'id' => 330,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/2/27/Grid_Iron_Door.png',
    ),
    'redstone' => array(
        'id' => 331,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/f/fd/Grid_Redstone.png',
    ),
    'snow_ball' => array(
        'id' => 332,
        'stack' => 16,
        'avail' => true,
        'icon_url' => '/6/67/Grid_Snowball.png',
    ),
    'boat' => array(
        'id' => 333,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/5/59/Grid_Boat.png',
    ),
    'leather' => array(
        'id' => 334,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/6/66/Grid_Leather.png',
    ),
    'milk_bucket' => array(
        'id' => 335,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/d/db/Grid_Milk.png',
    ),
    'brick' => array(
        'id' => 336,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/7/75/Grid_Brick.png',
    ),
    'clay_ball' => array(
        'id' => 337,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/2/2c/Grid_Clay.png',
    ),
    'sugarcane' => array(
        'id' => 338,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/7/79/Grid_Sugar_Canes.png',
    ),
    'paper' => array(
        'id' => 339,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/6/6c/Grid_Paper.png',
    ),
    'book' => array(
        'id' => 340,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/e/ec/Grid_Book.png',
    ),
    'slime_ball' => array(
        'id' => 341,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/c/c9/Grid_Slimeball.png',
    ),
    'chest_minecart' => array(
        'id' => 342,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/f/f0/Grid_Minecart_with_Chest.png',
    ),
    'furnace_minecart' => array(
        'id' => 343,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/3/36/Grid_Minecart_with_Furnace.png',
    ),
    'egg' => array(
        'id' => 344,
        'stack' => 16,
        'avail' => true,
        'icon_url' => '/2/27/Grid_Egg.png',
    ),
    'compass' => array(
        'id' => 345,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/f/f2/Grid_Compass.png',
    ),
    'fishing_rod' => array(
        'id' => 346,
        'damage' => 64,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/c/c7/Grid_Fishing_Rod.png',
    ),
    'clock' => array(
        'id' => 347,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/3/32/Grid_Clock.png',
    ),
    'glowstone_dust' => array(
        'id' => 348,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/8/85/Grid_Glowstone_Dust.png',
    ),
    'fish' => array(
        'id' => 349,
        'stack' => 64,
        'avail' => true,
        'group' => 'raw_fish_types',
        'icon_url' => '/6/6a/Grid_Raw_Fish.png',
        'subtypes' => array(
            0 => array('name' => 'raw_fish', 'avail' => true, 'icon_url' => '/6/6a/Grid_Raw_Fish.png'),
            1 => array('name' => 'raw_salmon', 'avail' => true, 'icon_url' => '/c/c0/Grid_Raw_Salmon.png'),
            2 => array('name' => 'clownfish', 'avail' => true, 'icon_url' => '/8/81/Grid_Clownfish.png'),
            3 => array('name' => 'pufferfish', 'avail' => true, 'icon_url' => '/d/df/Grid_Pufferfish.png'),
        ),
    ),
    'cooked_fish' => array(
        'id' => 350,
        'stack' => 64,
        'avail' => true,
        'group' => 'cooked_fish_types',
        'icon_url' => '/2/29/Grid_Cooked_Fish.png',
        'subtypes' => array(
            0 => array('name' => 'cooked_fish', 'avail' => true, 'icon_url' => '/2/29/Grid_Cooked_Fish.png'),
            1 => array('name' => 'cooked_salmon', 'avail' => true, 'icon_url' => '/a/a5/Grid_Cooked_Salmon.png'),
        ),
    ),
    'dye' => array(
        'id' => 351,
        'stack' => 64,
        'avail' => true,
        'group' => 'dye_types',
        'icon_url' => '/d/d6/Grid_Ink_Sac.png',
        'subtypes' => array(
            0 => array('name' => 'ink_sac', 'avail' => true, 'icon_url' => '/d/d6/Grid_Ink_Sac.png'),
            1 => array('name' => 'rose_red', 'avail' => true, 'icon_url' => '/0/0d/Grid_Rose_Red.png'),
            2 => array('name' => 'cactus_green', 'avail' => true, 'icon_url' => '/f/fd/Grid_Cactus_Green.png'),
            3 => array('name' => 'cocoa_beans', 'avail' => true, 'icon_url' => '/7/7d/Grid_Cocoa_Beans.png'),
            4 => array('name' => 'lapis_lazuli', 'avail' => true, 'icon_url' => '/7/76/Grid_Lapis_Lazuli.png'),
            5 => array('name' => 'purple_dye', 'avail' => true, 'icon_url' => '/a/a0/Grid_Purple_Dye.png'),
            6 => array('name' => 'cyan_dye', 'avail' => true, 'icon_url' => '/c/ca/Grid_Cyan_Dye.png'),
            7 => array('name' => 'light_gray_dye', 'avail' => true, 'icon_url' => '/1/16/Grid_Light_Gray_Dye.png'),
            8 => array('name' => 'gray_dye', 'avail' => true, 'icon_url' => '/5/54/Grid_Gray_Dye.png'),
            9 => array('name' => 'pink_dye', 'avail' => true, 'icon_url' => '/b/bb/Grid_Pink_Dye.png'),
            10 => array('name' => 'lime_dye', 'avail' => true, 'icon_url' => '/a/a6/Grid_Lime_Dye.png'),
            11 => array('name' => 'dandelion_yellow', 'avail' => true, 'icon_url' => '/d/df/Grid_Dandelion_Yellow.png'),
            12 => array('name' => 'light_blue_dye', 'avail' => true, 'icon_url' => '/a/ae/Grid_Light_Blue_Dye.png'),
            13 => array('name' => 'magenta_dye', 'avail' => true, 'icon_url' => '/8/8f/Grid_Magenta_Dye.png'),
            14 => array('name' => 'orange_dye', 'avail' => true, 'icon_url' => '/b/b2/Grid_Orange_Dye.png'),
            15 => array('name' => 'bone_meal', 'avail' => true, 'icon_url' => '/c/c0/Grid_Bone_Meal.png'),
        ),
    ),
    'bone' => array(
        'id' => 352,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/e/e8/Grid_Bone.png',
    ),
    'sugar' => array(
        'id' => 353,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/9/96/Grid_Sugar.png',
    ),
    'cake' => array(
        'id' => 354,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/2/28/Grid_Cake.png',
    ),
    'bed' => array(
        'id' => 355,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/a/a3/Grid_Bed.png',
    ),
    'repeater' => array(
        'id' => 356,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/e/e6/Grid_Redstone_Repeater.png',
    ),
    'cookie' => array(
        'id' => 357,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/9/95/Grid_Cookie.png',
    ),
    'filled_map' => array(
        'id' => 358,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/7/78/Exploration_maps.gif',
    ),
    'shears' => array(
        'id' => 359,
        'damage' => 238,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/1/13/Grid_Shears.png',
    ),
    'melonslice' => array(
        'id' => 360,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/e/ec/Grid_Melon.png',
    ),
    'pumpkin_seeds' => array(
        'id' => 361,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/7/72/Grid_Pumpkin_Seeds.png',
    ),
    'melon_seeds' => array(
        'id' => 362,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/7/75/Grid_Melon_Seeds.png',
    ),
    'raw_beef' => array(
        'id' => 363,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/6/61/Grid_Raw_Beef.png',
    ),
    'cooked_beef' => array(
        'id' => 364,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/d/da/Grid_Steak.png',
    ),
    'chicken' => array(
        'id' => 365,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/e/e5/Grid_Raw_Chicken.png',
    ),
    'cooked_chicken' => array(
        'id' => 366,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/2/2e/Grid_Cooked_Chicken.png',
    ),
    'rotten_flesh' => array(
        'id' => 367,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/3/33/Grid_Rotten_Flesh.png',
    ),
    'ender_pearl' => array(
        'id' => 368,
        'stack' => 16,
        'avail' => true,
        'icon_url' => '/1/13/Grid_Ender_Pearl.png',
    ),
    'blaze_rod' => array(
        'id' => 369,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/1/18/Grid_Blaze_Rod.png',
    ),
    'ghast_tear' => array(
        'id' => 370,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/6/66/Grid_Ghast_Tear.png',
    ),
    'gold_nugget' => array(
        'id' => 371,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/1/1d/Grid_Gold_Nugget.png',
    ),
    'nether_wart' => array(
        'id' => 372,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/d/d2/Grid_Nether_Wart.png',
    ),
    'potion' => array(
        'id' => 373,
        'stack' => 1,
        'avail' => true,
        'group' => 'potion_types',
        'icon_url' => '/c/c3/Grid_Awkward_Potion.png',
        'subtypes' => array(
            0 => array('name' => 'awkward_potion', 'avail' => true, 'icon_url' => '/c/c3/Grid_Awkward_Potion.png'),
            1 => array('name' => 'pink_potion', 'avail' => true, 'icon_url' => '?'),
            2 => array('name' => 'uninteresting_potion', 'avail' => true, 'icon_url' => '?'),
            3 => array('name' => 'uninteresting_potion', 'avail' => true, 'icon_url' => '?'),
            4 => array('name' => 'bland_potion', 'avail' => true, 'icon_url' => '?'),
            5 => array('name' => 'bland_potion', 'avail' => true, 'icon_url' => '?'),
            6 => array('name' => 'clear_potion', 'avail' => true, 'icon_url' => '?'),
            7 => array('name' => 'clear_potion', 'avail' => true, 'icon_url' => '?'),
            8 => array('name' => 'milky_potion', 'avail' => true, 'icon_url' => '?'),
            9 => array('name' => 'milky_potion', 'avail' => true, 'icon_url' => '?'),
            10 => array('name' => 'diffuse_potion', 'avail' => true, 'icon_url' => '?'),
            11 => array('name' => 'diffuse_potion', 'avail' => true, 'icon_url' => '?'),
            12 => array('name' => 'artless_potion', 'avail' => true, 'icon_url' => '?'),
            13 => array('name' => 'artless_potion', 'avail' => true, 'icon_url' => '?'),
            14 => array('name' => 'thin_potion', 'avail' => true, 'icon_url' => '?'),
            15 => array('name' => 'thin_potion', 'avail' => true, 'icon_url' => '?'),
            16 => array('name' => 'awkward_potion', 'avail' => true, 'icon_url' => '/c/c3/Grid_Awkward_Potion.png'),
            18 => array('name' => 'flat_potion', 'avail' => true, 'icon_url' => '?'),
            19 => array('name' => 'flat_potion', 'avail' => true, 'icon_url' => '?'),
            20 => array('name' => 'bulky_potion', 'avail' => true, 'icon_url' => '?'),
            21 => array('name' => 'bulky_potion', 'avail' => true, 'icon_url' => '?'),
            22 => array('name' => 'bungling_potion', 'avail' => true, 'icon_url' => '?'),
            23 => array('name' => 'bungling_potion', 'avail' => true, 'icon_url' => '?'),
            24 => array('name' => 'buttered_potion', 'avail' => true, 'icon_url' => '?'),
            25 => array('name' => 'buttered_potion', 'avail' => true, 'icon_url' => '?'),
            26 => array('name' => 'smooth_potion', 'avail' => true, 'icon_url' => '?'),
            27 => array('name' => 'smooth_potion', 'avail' => true, 'icon_url' => '?'),
            28 => array('name' => 'suave_potion', 'avail' => true, 'icon_url' => '?'),
            29 => array('name' => 'suave_potion', 'avail' => true, 'icon_url' => '?'),
            30 => array('name' => 'debonair_potion', 'avail' => true, 'icon_url' => '?'),
            31 => array('name' => 'debonair_potion', 'avail' => true, 'icon_url' => '?'),
            32 => array('name' => 'thick_potion', 'avail' => true, 'icon_url' => '/e/e6/Grid_Thick_Potion.png'),
            33 => array('name' => 'thick_potion', 'avail' => true, 'icon_url' => '/e/e6/Grid_Thick_Potion.png'),
            34 => array('name' => 'elegant_potion', 'avail' => true, 'icon_url' => '?'),
            35 => array('name' => 'elegant_potion', 'avail' => true, 'icon_url' => '?'),
            36 => array('name' => 'fancy_potion', 'avail' => true, 'icon_url' => '?'),
            37 => array('name' => 'fancy_potion', 'avail' => true, 'icon_url' => '?'),
            38 => array('name' => 'charming_potion', 'avail' => true, 'icon_url' => '?'),
            39 => array('name' => 'charming_potion', 'avail' => true, 'icon_url' => '?'),
            40 => array('name' => 'dashing_potion', 'avail' => true, 'icon_url' => '?'),
            41 => array('name' => 'dashing_potion', 'avail' => true, 'icon_url' => '?'),
            42 => array('name' => 'refined_potion', 'avail' => true, 'icon_url' => '?'),
            43 => array('name' => 'refined_potion', 'avail' => true, 'icon_url' => '?'),
            44 => array('name' => 'cordial_potion', 'avail' => true, 'icon_url' => '?'),
            45 => array('name' => 'cordial_potion', 'avail' => true, 'icon_url' => '?'),
            46 => array('name' => 'sparkling_potion', 'avail' => true, 'icon_url' => '?'),
            47 => array('name' => 'sparkling_potion', 'avail' => true, 'icon_url' => '?'),
            48 => array('name' => 'potent_potion', 'avail' => true, 'icon_url' => '?'),
            49 => array('name' => 'potent_potion', 'avail' => true, 'icon_url' => '?'),
            50 => array('name' => 'foul_potion', 'avail' => true, 'icon_url' => '?'),
            51 => array('name' => 'foul_potion', 'avail' => true, 'icon_url' => '?'),
            52 => array('name' => 'odorless_potion', 'avail' => true, 'icon_url' => '?'),
            53 => array('name' => 'odorless_potion', 'avail' => true, 'icon_url' => '?'),
            54 => array('name' => 'rank_potion', 'avail' => true, 'icon_url' => '?'),
            55 => array('name' => 'rank_potion', 'avail' => true, 'icon_url' => '?'),
            56 => array('name' => 'harsh_potion', 'avail' => true, 'icon_url' => '?'),
            57 => array('name' => 'harsh_potion', 'avail' => true, 'icon_url' => '?'),
            58 => array('name' => 'acrid_potion', 'avail' => true, 'icon_url' => '?'),
            59 => array('name' => 'acrid_potion', 'avail' => true, 'icon_url' => '?'),
            62 => array('name' => 'stinky_potion', 'avail' => true, 'icon_url' => '?'),
            63 => array('name' => 'stinky_potion', 'avail' => true, 'icon_url' => '?'),
            64 => array('name' => 'mundane_potion_(extended)', 'avail' => true, 'icon_url' => '/6/6c/Grid_Mundane_Potion.png'),
            65 => array('name' => 'potion_of_regeneration', 'avail' => true, 'icon_url' => '/0/00/Grid_Potion_of_Regeneration.png'),
            66 => array('name' => 'potion_of_swiftness', 'avail' => true, 'icon_url' => '/1/1c/Grid_Potion_of_Swiftness.png'),
            67 => array('name' => 'potion_of_fire_resistance', 'avail' => true, 'icon_url' => '/4/43/Grid_Potion_of_Fire_Resistance.png'),
            68 => array('name' => 'potion_of_poison', 'avail' => true, 'icon_url' => '/a/a1/Grid_Potion_of_Poison.png'),
            70 => array('name' => 'potion_of_night_vision', 'avail' => true, 'icon_url' => '/b/ba/Grid_Potion_of_Night_Vision.png'),
            72 => array('name' => 'potion_of_weakness', 'avail' => true, 'icon_url' => '/2/2c/Grid_Potion_of_Weakness.png'),
            73 => array('name' => 'potion_of_weakness', 'avail' => true, 'icon_url' => '/2/2c/Grid_Potion_of_Weakness.png'),
            78 => array('name' => 'potion_of_weakness', 'avail' => true, 'icon_url' => '/2/2c/Grid_Potion_of_Weakness.png'),
            8192 => array('name' => 'mundane_potion', 'avail' => true, 'icon_url' => '/6/6c/Grid_Mundane_Potion.png'),
            8193 => array('name' => 'potion_of_regeneration', 'avail' => true, 'icon_url' => '/0/00/Grid_Potion_of_Regeneration.png'),
            8194 => array('name' => 'potion_of_swiftness', 'avail' => true, 'icon_url' => '/1/1c/Grid_Potion_of_Swiftness.png'),
            8195 => array('name' => 'potion_of_fire_resistance', 'avail' => true, 'icon_url' => '/4/43/Grid_Potion_of_Fire_Resistance.png'),
            8196 => array('name' => 'potion_of_poison', 'avail' => true, 'icon_url' => '/a/a1/Grid_Potion_of_Poison.png'),
            8197 => array('name' => 'potion_of_healing', 'avail' => true, 'icon_url' => '/a/a3/Grid_Potion_of_Healing.png'),
            8198 => array('name' => 'potion_of_night_vision', 'avail' => true, 'icon_url' => '/b/ba/Grid_Potion_of_Night_Vision.png'),
            8200 => array('name' => 'potion_of_weakness', 'avail' => true, 'icon_url' => '/2/2c/Grid_Potion_of_Weakness.png'),
            8201 => array('name' => 'potion_of_strength', 'avail' => true, 'icon_url' => '/8/8c/Grid_Potion_of_Strength.png'),
            8202 => array('name' => 'potion_of_slowness', 'avail' => true, 'icon_url' => '/c/ca/Grid_Potion_of_Slowness.png'),
            8203 => array('name' => 'potion_of_leaping', 'avail' => true, 'icon_url' => '/8/8c/Grid_Potion_of_Leaping.png'),
            8204 => array('name' => 'potion_of_harming', 'avail' => true, 'icon_url' => '/b/b2/Grid_Potion_of_Harming.png'),
            8205 => array('name' => 'potion_of_water_breathing', 'avail' => true, 'icon_url' => '/c/c2/Grid_Potion_of_Water_Breathing.png'),
            8206 => array('name' => 'potion_of_invisibility', 'avail' => true, 'icon_url' => '/d/d8/Grid_Potion_of_Invisibility.png'),
            8225 => array('name' => 'potion_of_regeneration_ii', 'avail' => true, 'icon_url' => '/0/00/Grid_Potion_of_Regeneration.png'),
            8226 => array('name' => 'potion_of_swiftness_ii', 'avail' => true, 'icon_url' => '/1/1c/Grid_Potion_of_Swiftness.png'),
            8228 => array('name' => 'potion_of_poison_ii', 'avail' => true, 'icon_url' => '/a/a1/Grid_Potion_of_Poison.png'),
            8229 => array('name' => 'potion_of_healing_ii', 'avail' => true, 'icon_url' => '/a/a3/Grid_Potion_of_Healing.png'),
            8233 => array('name' => 'potion_of_strength_ii', 'avail' => true, 'icon_url' => '/8/8c/Grid_Potion_of_Strength.png'),
            8234 => array('name' => 'potion_of_night_vision_ii', 'avail' => true, 'icon_url' => '/b/ba/Grid_Potion_of_Night_Vision.png'),
            8235 => array('name' => 'potion_of_leaping', 'avail' => true, 'icon_url' => '/8/8c/Grid_Potion_of_Leaping.png'),
            8236 => array('name' => 'potion_of_harming_ii', 'avail' => true, 'icon_url' => '/b/b2/Grid_Potion_of_Harming.png'),
            8257 => array('name' => 'potion_of_regeneration', 'avail' => true, 'icon_url' => '/0/00/Grid_Potion_of_Regeneration.png'),
            8258 => array('name' => 'potion_of_swiftness', 'avail' => true, 'icon_url' => '/1/1c/Grid_Potion_of_Swiftness.png'),
            8259 => array('name' => 'potion_of_fire_resistance', 'avail' => true, 'icon_url' => '/4/43/Grid_Potion_of_Fire_Resistance.png'),
            8260 => array('name' => 'potion_of_poison', 'avail' => true, 'icon_url' => '/a/a1/Grid_Potion_of_Poison.png'),
            8261 => array('name' => 'potion_of_healing', 'avail' => true, 'icon_url' => '/a/a3/Grid_Potion_of_Healing.png'),
            8262 => array('name' => 'potion_of_night_vision', 'avail' => true, 'icon_url' => '/b/ba/Grid_Potion_of_Night_Vision.png'),
            8264 => array('name' => 'potion_of_weakness', 'avail' => true, 'icon_url' => '/2/2c/Grid_Potion_of_Weakness.png'),
            8265 => array('name' => 'potion_of_strength', 'avail' => true, 'icon_url' => '/8/8c/Grid_Potion_of_Strength.png'),
            8266 => array('name' => 'potion_of_slowness', 'avail' => true, 'icon_url' => '/c/ca/Grid_Potion_of_Slowness.png'),
            8269 => array('name' => 'potion_of_water_breathing', 'avail' => true, 'icon_url' => '/c/c2/Grid_Potion_of_Water_Breathing.png'),
            8270 => array('name' => 'potion_of_invisibility', 'avail' => true, 'icon_url' => '/d/d8/Grid_Potion_of_Invisibility.png'),
            8289 => array('name' => 'potion_of_regeneration_ii_(extended)', 'avail' => true, 'icon_url' => '/0/00/Grid_Potion_of_Regeneration.png'),
            8290 => array('name' => 'potion_of_swiftness_ii_(extended)', 'avail' => true, 'icon_url' => '/1/1c/Grid_Potion_of_Swiftness.png'),
            8292 => array('name' => 'potion_of_poison_ii_(extended)', 'avail' => true, 'icon_url' => '/a/a1/Grid_Potion_of_Poison.png'),
            8297 => array('name' => 'potion_of_strength_ii_(extended)', 'avail' => true, 'icon_url' => '/8/8c/Grid_Potion_of_Strength.png'),
            16341 => array('name' => 'potion_of_healing', 'avail' => true, 'icon_url' => '/a/a3/Grid_Potion_of_Healing.png'),
            16384 => array('name' => 'splash_mundane_potion', 'avail' => true, 'icon_url' => '/0/0b/Grid_Splash_Mundane_Potion.png'),
            16385 => array('name' => 'splash_potion_of_regeneration', 'avail' => true, 'icon_url' => '/0/0f/Grid_Splash_Potion_of_Regeneration.png'),
            16386 => array('name' => 'splash_potion_of_swiftness', 'avail' => true, 'icon_url' => '/1/1c/Grid_Potion_of_Swiftness.png'),
            16387 => array('name' => 'splash_potion_of_fire_resistance', 'avail' => true, 'icon_url' => '/c/cb/Grid_Splash_Potion_of_Fire_Resistance.png'),
            16388 => array('name' => 'splash_potion_of_poison', 'avail' => true, 'icon_url' => '/1/11/Grid_Splash_Potion_of_Poison.png'),
            16389 => array('name' => 'splash_potion_of_healing', 'avail' => true, 'icon_url' => '/3/33/Grid_Splash_Potion_of_Healing.png'),
            16392 => array('name' => 'splash_potion_of_weakness', 'avail' => true, 'icon_url' => '/9/94/Grid_Splash_Potion_of_Weakness.png'),
            16393 => array('name' => 'splash_potion_of_strength', 'avail' => true, 'icon_url' => '/3/38/Grid_Splash_Potion_of_Strength.png'),
            16394 => array('name' => 'splash_potion_of_slowness', 'avail' => true, 'icon_url' => '/2/22/Grid_Splash_Potion_of_Slowness.png'),
            16396 => array('name' => 'splash_potion_of_harming', 'avail' => true, 'icon_url' => '/5/52/Grid_Splash_Potion_of_Harming.png'),
            16417 => array('name' => 'splash_potion_of_regeneration_ii', 'avail' => true, 'icon_url' => '/0/0f/Grid_Splash_Potion_of_Regeneration.png'),
            16418 => array('name' => 'splash_potion_of_swiftness_ii', 'avail' => true, 'icon_url' => '/7/7a/Grid_Splash_Potion_of_Swiftness.png'),
            16419 => array('name' => 'splash_potion_of_fire_resistance_(reverted)', 'avail' => true, 'icon_url' => '/c/cb/Grid_Splash_Potion_of_Fire_Resistance.png'),
            16420 => array('name' => 'splash_potion_of_poison_ii', 'avail' => true, 'icon_url' => '/1/11/Grid_Splash_Potion_of_Poison.png'),
            16421 => array('name' => 'splash_potion_of_healing_ii', 'avail' => true, 'icon_url' => '/3/33/Grid_Splash_Potion_of_Healing.png'),
            16422 => array('name' => 'splash_potion_of_night_vision', 'avail' => true, 'icon_url' => '/c/c3/Grid_Splash_Potion_of_Night_Vision.png'),
            16424 => array('name' => 'splash_potion_of_weakness_(reverted)', 'avail' => true, 'icon_url' => '/9/94/Grid_Splash_Potion_of_Weakness.png'),
            16425 => array('name' => 'splash_potion_of_strength_ii', 'avail' => true, 'icon_url' => '/3/38/Grid_Splash_Potion_of_Strength.png'),
            16426 => array('name' => 'splash_potion_of_slowness_(reverted)', 'avail' => true, 'icon_url' => '/2/22/Grid_Splash_Potion_of_Slowness.png'),
            16428 => array('name' => 'splash_potion_of_harming_ii', 'avail' => true, 'icon_url' => '/5/52/Grid_Splash_Potion_of_Harming.png'),
            16430 => array('name' => 'splash_potion_of_invisibility', 'avail' => true, 'icon_url' => '/8/89/Grid_Splash_Potion_of_Invisibility.png'),
            16449 => array('name' => 'splash_potion_of_regeneration', 'avail' => true, 'icon_url' => '/0/0f/Grid_Splash_Potion_of_Regeneration.png'),
            16450 => array('name' => 'splash_potion_of_swiftness', 'avail' => true, 'icon_url' => '/7/7a/Grid_Splash_Potion_of_Swiftness.png'),
            16451 => array('name' => 'splash_potion_of_fire_resistance', 'avail' => true, 'icon_url' => '/c/cb/Grid_Splash_Potion_of_Fire_Resistance.png'),
            16452 => array('name' => 'splash_potion_of_poison', 'avail' => true, 'icon_url' => '/1/11/Grid_Splash_Potion_of_Poison.png'),
            16453 => array('name' => 'splash_potion_of_healing_(reverted)', 'avail' => true, 'icon_url' => '/3/33/Grid_Splash_Potion_of_Healing.png'),
            16454 => array('name' => 'splash_potion_of_night_vision', 'avail' => true, 'icon_url' => '/c/c3/Grid_Splash_Potion_of_Night_Vision.png'),
            16456 => array('name' => 'splash_potion_of_weakness', 'avail' => true, 'icon_url' => '/9/94/Grid_Splash_Potion_of_Weakness.png'),
            16457 => array('name' => 'splash_potion_of_strength', 'avail' => true, 'icon_url' => '/3/38/Grid_Splash_Potion_of_Strength.png'),
            16458 => array('name' => 'splash_potion_of_slowness', 'avail' => true, 'icon_url' => '/2/22/Grid_Splash_Potion_of_Slowness.png'),
            16460 => array('name' => 'splash_potion_of_harming_(reverted)', 'avail' => true, 'icon_url' => '/5/52/Grid_Splash_Potion_of_Harming.png'),
            16462 => array('name' => 'splash_potion_of_invisibility', 'avail' => true, 'icon_url' => '/8/89/Grid_Splash_Potion_of_Invisibility.png'),
            16481 => array('name' => 'splash_potion_of_regeneration_ii', 'avail' => true, 'icon_url' => '/0/0f/Grid_Splash_Potion_of_Regeneration.png'),
            16482 => array('name' => 'splash_potion_of_swiftness_ii', 'avail' => true, 'icon_url' => '/7/7a/Grid_Splash_Potion_of_Swiftness.png'),
            16484 => array('name' => 'splash_potion_of_strength_ii', 'avail' => true, 'icon_url' => '/3/38/Grid_Splash_Potion_of_Strength.png'),
            16489 => array('name' => 'splash_potion_of_strength_ii', 'avail' => true, 'icon_url' => '/3/38/Grid_Splash_Potion_of_Strength.png'),
        ),
    ),
    'glass_bottle' => array(
        'id' => 374,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/2/22/Grid_Glass_Bottle.png',
    ),
    'spider_eye' => array(
        'id' => 375,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/0/03/Grid_Spider_Eye.png',
    ),
    'fermented_spider_eye' => array(
        'id' => 376,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/2/29/Grid_Fermented_Spider_Eye.png',
    ),
    'blaze_powder' => array(
        'id' => 377,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/0/09/Grid_Blaze_Powder.png',
    ),
    'magma_cream' => array(
        'id' => 378,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/e/e8/Grid_Magma_Cream.png',
    ),
    'brewingstand' => array(
        'id' => 379,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/7/70/Grid_Brewing_Stand.png',
    ),
    'cauldron' => array(
        'id' => 380,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/c/ca/Grid_Cauldron.png',
    ),
    'ender_eye' => array(
        'id' => 381,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/0/0e/Grid_Eye_of_Ender.png',
    ),
    'speckled_melon' => array(
        'id' => 382,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/1/11/Grid_Glistering_Melon.png',
    ),
    'spawn_egg' => array(
        'id' => 383,
        'stack' => 64,
        'avail' => true,
        'group' => 'spawn_egg_types',
        'icon_url' => '/f/fc/Grid_Spawn_Creeper.png',
        'subtypes' => array(
            29 => array('name' => 'zombie_horse_egg', 'avail' => true, 'icon_url' => '/4/46/Undeadhorse.png'), //not sure if egg exists
            31 => array('name' => 'donkey_egg', 'avail' => true, 'icon_url' => '/9/95/Donkey.png'), //lottery
            32 => array('name' => 'mule_egg', 'avail' => true, 'icon_url' => '/e/e7/Mule.png'), //lottery
            50 => array('name' => 'creeper_egg', 'avail' => true, 'icon_url' => '/f/fc/Grid_Spawn_Creeper.png'),
            51 => array('name' => 'skeleton_egg', 'avail' => true, 'icon_url' => '/3/35/Grid_Spawn_Skeleton.png'),
            52 => array('name' => 'spider_egg', 'avail' => true, 'icon_url' => '/5/50/Grid_Spawn_Spider.png'),
            54 => array('name' => 'zombie_egg', 'avail' => true, 'icon_url' => '/3/30/Grid_Spawn_Zombie.png'),
            55 => array('name' => 'slime_egg', 'avail' => true, 'icon_url' => '/1/11/Grid_Spawn_Slime.png'),
            56 => array('name' => 'ghast_egg', 'avail' => true, 'icon_url' => '/3/3f/Grid_Spawn_Ghast.png'),
            57 => array('name' => 'pigman_egg', 'avail' => true, 'icon_url' => '/4/4d/Grid_Spawn_Zombie_Pigman.png'),
            58 => array('name' => 'enderman_egg', 'avail' => true, 'icon_url' => '/6/62/Grid_Spawn_Enderman.png'),
            59 => array('name' => 'cave_spider_egg', 'avail' => true, 'icon_url' => '/e/ee/Grid_Spawn_Cave_Spider.png'),
            60 => array('name' => 'silverfish_egg', 'avail' => true, 'icon_url' => '/1/11/Grid_Spawn_Silverfish.png'),
            61 => array('name' => 'blaze_egg', 'avail' => true, 'icon_url' => '/f/ff/Grid_Spawn_Blaze.png'),
            62 => array('name' => 'magma_cube_egg', 'avail' => true, 'icon_url' => '/2/25/Grid_Spawn_Magma_Cube.png'),
            65 => array('name' => 'bat_egg', 'avail' => true, 'icon_url' => '/f/f3/Grid_Spawn_Bat.png'),  //lottery
            66 => array('name' => 'witch_egg', 'avail' => true, 'icon_url' => '/7/7c/Grid_Spawn_Witch.png'),
            67 => array('name' => 'endermite_egg', 'avail' => true, 'icon_url' => '/a/af/Grid_Spawn_Endermite.png'),
            68 => array('name' => 'guardian_egg', 'avail' => true, 'icon_url' => '/5/5f/Grid_Spawn_Guardian.png'),
            69 => array('name' => 'shulker_egg', 'avail' => true, 'icon_url' => '/c/c7/Grid_Spawn_Shulker.png'),
            90 => array('name' => 'pig_egg', 'avail' => true, 'icon_url' => '/0/09/Grid_Spawn_Pig.png'), //lottery
            91 => array('name' => 'sheep_egg', 'avail' => true, 'icon_url' => '/f/f8/Grid_Spawn_Sheep.png'), //lottery
            92 => array('name' => 'cow_egg', 'avail' => true, 'icon_url' => '/8/80/Grid_Spawn_Cow.png'), //lottery
            93 => array('name' => 'chicken_egg', 'avail' => true, 'icon_url' => '/b/b0/Grid_Spawn_Chicken.png'), //lottery
            94 => array('name' => 'squid_egg', 'avail' => true, 'icon_url' => '/5/58/Grid_Spawn_Squid.png'), //lottery
            95 => array('name' => 'wolf_egg', 'avail' => true, 'icon_url' => '/4/4b/Grid_Spawn_Wolf.png'), //lottery
            96 => array('name' => 'mooshroom_egg', 'avail' => true, 'icon_url' => '/9/91/Grid_Spawn_Mooshroom.png'), //lottery
            98 => array('name' => 'ocelot_egg', 'avail' => true, 'icon_url' => '/e/e8/Grid_Spawn_Ocelot.png'), //lottery
            100 => array('name' => 'horse_egg', 'avail' => true, 'icon_url' => '/e/e0/Grid_Spawn_Horse.png'), //lottery
            101 => array('name' => 'rabbit_egg', 'avail' => true, 'icon_url' => '/3/33/Grid_Spawn_Rabbit.png'), //lottery
            102 => array('name' => 'polar_bear_egg', 'avail' => true, 'icon_url' => '/f/fe/PolarBear_Preview.png'), //lottery
            103 => array('name' => 'llama_egg', 'avail' => true, 'icon_url' => '/f/fe/PolarBear_Preview.png'), //lottery //TODO: find the right image
            120 => array('name' => 'villager_egg', 'avail' => true, 'icon_url' => '/0/02/Grid_Spawn_Villager.png'), //lottery
        ),
    ),
    'experience_bottle' => array(
        'id' => 384,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/b/bf/Grid_Bottle_o%27_Enchanting.png',
    ),
    'fire_charge' => array(
        'id' => 385,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/e/e1/Grid_Fire_Charge.png',
    ),
    'writable_book' => array(
        'id' => 386,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/e/eb/Grid_Book_and_Quill.png',
    ),
    'written_book' => array(
        'id' => 387,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/c/c5/Grid_Written_Book.png',
    ),
    'emerald' => array(
        'id' => 388,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/8/87/Grid_Emerald.png',
    ),
    'item_frame' => array(
        'id' => 389,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/c/c5/Grid_Item_Frame.png',
    ),
    'flower_pot_item' => array(
        'id' => 390,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/8/89/Grid_Flower_Pot.png',
    ),
    'carrot' => array(
        'id' => 391,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/8/8b/Grid_Carrot.png',
    ),
    'potato' => array(
        'id' => 392,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/2/2b/Grid_Potato.png',
    ),
    'baked_potato' => array(
        'id' => 393,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/6/6c/Grid_Baked_Potato.png',
    ),
    'poisonous_potato' => array(
        'id' => 394,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/f/fd/Grid_Poisonous_Potato.png',
    ),
    'map' => array(
        'id' => 395,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/c/c7/Grid_Empty_Map.png',
    ),
    'golden_carrot' => array(
        'id' => 396,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/a/a7/Grid_Golden_Carrot.png',
    ),
    'skeletonskull' => array(
        'id' => 397,
        'stack' => 64,
        'avail' => true,
        'group' => 'skull_types',
        'icon_url' => '/c/c9/Grid_Skeleton_Skull.png',
        'subtypes' => array(
            0 => array('name' => 'skeleton_skull', 'avail' => true, 'icon_url' => '/c/c9/Grid_Skeleton_Skull.png'),
            1 => array('name' => 'wither_skeleton_skull', 'avail' => true, 'icon_url' => '/d/d4/Grid_Wither_Skeleton_Skull.png'),
            2 => array('name' => 'zombie_head', 'avail' => true, 'icon_url' => '/6/6f/Grid_Zombie_Head.png'),
            3 => array('name' => 'head', 'avail' => true, 'icon_url' => '/5/55/Grid_Head.png'),
            4 => array('name' => 'creeper_head', 'avail' => true, 'icon_url' => '/c/c7/Grid_Creeper_Head.png'),
            5 => array('name' => 'dragon_head', 'avail' => true, 'icon_url' => '/b/b6/Dragon_Head.png'),
        ),
    ),
    'carrot_on_a_stick' => array(
        'id' => 398,
        'damage' => 25,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/e/e4/Grid_Carrot_on_a_Stick.png',
    ),
    'nether_star' => array(
        'id' => 399,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/a/ae/Grid_Nether_Star.png',
    ),
    'pumpkin_pie' => array(
        'id' => 400,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/9/9f/Grid_Pumpkin_Pie.png',
    ),
    'fireworks' => array(
        'id' => 401,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/3/31/Grid_Firework_Rocket.png',
    ),
    'firework_charge' => array(
        'id' => 402,
        'stack' => 64,
        'avail' => true,
        'group' => 'firework_types',
        'icon_url' => '/6/68/Grid_White_Firework_Star.png',
        'subtypes' => array(
            0 => array('name' => 'white_firework_star', 'avail' => true, 'icon_url' => '/6/68/Grid_White_Firework_Star.png'),
            1 => array('name' => 'orange_firework_star', 'avail' => true, 'icon_url' => '/e/e7/Grid_Orange_Firework_Star.png'),
            2 => array('name' => 'magenta_firework_star', 'avail' => true, 'icon_url' => '/9/9e/Grid_Magenta_Firework_Star.png'),
            3 => array('name' => 'light_blue_firework_star', 'avail' => true, 'icon_url' => '/9/9f/Grid_Light_Blue_Firework_Star.png'),
            4 => array('name' => 'yellow_firework_star', 'avail' => true, 'icon_url' => '/b/b9/Grid_Yellow_Firework_Star.png'),
            5 => array('name' => 'lime_firework_star', 'avail' => true, 'icon_url' => '/2/20/Grid_Lime_Firework_Star.png'),
            6 => array('name' => 'pink_firework_star', 'avail' => true, 'icon_url' => '/2/20/Grid_Pink_Firework_Star.png'),
            7 => array('name' => 'gray_firework_star', 'avail' => true, 'icon_url' => '/c/c4/Grid_Gray_Firework_Star.png'),
            8 => array('name' => 'light_Gray_Firework', 'avail' => true, 'icon_url' => '/8/80/Grid_Light_Gray_Firework_Star.png'),
            9 => array('name' => 'cyan_firework_star', 'avail' => true, 'icon_url' => '/a/a5/Grid_Cyan_Firework_Star.png'),
            10 => array('name' => 'purple_firework_star', 'avail' => true, 'icon_url' => '/7/72/Grid_Purple_Firework_Star.png'),
            11 => array('name' => 'blue_firework_star', 'avail' => true, 'icon_url' => '/d/d3/Grid_Blue_Firework_Star.png'),
            12 => array('name' => 'brown_firework_star', 'avail' => true, 'icon_url' => '/e/e6/Grid_Brown_Firework_Star.png'),
            13 => array('name' => 'green_firework_star', 'avail' => true, 'icon_url' => '/f/ff/Grid_Green_Firework_Star.png'),
            14 => array('name' => 'red_firework_star', 'avail' => true, 'icon_url' => '/b/b7/Grid_Red_Firework_Star.png'),
            15 => array('name' => 'black_firework_star', 'avail' => true, 'icon_url' => '/6/67/Grid_Black_Firework_Star.png'),
        ),
    ),
    'enchanted_book' => array(
        'id' => 403,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/f/f7/Grid_Enchanted_Book.png',
    ),
    'comparator' => array(
        'id' => 404,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/e/ea/Grid_Redstone_Comparator.png',
        'subtypes' => array(
            0 => array('name' => 'redstone_comparator_(ative)', 'avail' => true, 'icon_url' => '/e/ea/Grid_Redstone_Comparator.png'),
            1 => array('name' => 'redstone_comparator_(inactive)', 'avail' => true, 'icon_url' => '/e/ea/Grid_Redstone_Comparator.png'),
        ),
    ),
    'nether_brick_item' => array(
        'id' => 405,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/6/62/Grid_Nether_Brick.png',
    ),
    'quartz' => array(
        'id' => 406,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/2/2b/Grid_Nether_Quartz.png',
    ),
    'tnt_minecart' => array(
        'id' => 407,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/9/93/Grid_Minecart_with_TNT.png',
    ),
    'hopper_minecart' => array(
        'id' => 408,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/7/78/Grid_Minecart_with_Hopper.png',
    ),
    'prismarine_shard' => array(
        'id' => 409,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/8/8e/Grid_Prismarine_Shard.png',
    ),
    'prismarine_crystals' => array(
        'id' => 410,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/0/03/Grid_Prismarine_Crystals.png',
    ),
    'rabbit' => array(
        'id' => 411,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/9/90/Grid_Raw_Rabbit.png',
    ),
    'cooked_rabbit' => array(
        'id' => 412,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/9/99/Grid_Cooked_Rabbit.png',
    ),
    'rabbit_stew' => array(
        'id' => 413,
        'stack' => 1,
        'avail' => false,
        'icon_url' => '/1/14/Grid_Rabbit_Stew.png',
    ),
    'rabbit_foot' => array(
        'id' => 414,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/2/24/Grid_Rabbit%27s_Foot.png',
    ),
    'rabbit_hide' => array(
        'id' => 415,
        'stack' => 64,
        'avail' => false,
        'icon_url' => '/b/b6/Grid_Rabbit_Hide.png',
    ),
    'armor_stand' => array(
        'id' => 416,
        'stack' => 16,
        'avail' => false,
        'notrade' => true,
        'icon_url' => '/4/47/Grid_Armor_Stand.png',
    ),
    'iron_horse_armor' => array(
        'id' => 417,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/a/af/Grid_Iron_Horse_Armor.png',
    ),
    'golden_horse_armor' => array(
        'id' => 418,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/0/09/Grid_Golden_Horse_Armor.png',
    ),
    'diamond_horse_armor' => array(
        'id' => 419,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/a/af/Grid_Diamond_Horse_Armor.png',
    ),
    'lead' => array(
        'id' => 420,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/9/96/Grid_Lead.png',
    ),
    'name_tag' => array(
        'id' => 421,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/9/97/Grid_Name_Tag.png',
    ),
    'command_block_minecart' => array(
        'id' => 422,
        'stack' => 1,
        'avail' => false,
        'icon_url' => '/a/a3/Grid_Minecart_with_Command_Block.png',
    ),
    'mutton' => array(
        'id' => 423,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/6/65/Grid_Raw_Mutton.png',
    ),
    'cooked_mutton' => array(
        'id' => 424,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/e/e2/Grid_Cooked_Mutton.png',
    ),
    'banner' => array(
        'id' => 425,
        'stack' => 16,
        'avail' => true,
        'notrade' => true,
        'icon_url' => '/2/24/Grid_White_Banner.png',
    ),
    'end_crystal' => array(
        'id' => 426,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/d/d3/Ender_Crystal.gif',
    ),
    'spruce_door' => array(
        'id' => 427,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/0/01/Spruce_Door.png',
    ),
    'birch_door' => array(
        'id' => 428,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/4/43/Birch_Door.png',
    ),
    'jungle_door' => array(
        'id' => 429,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/c/c5/Jungle_Door.png',
    ),
    'acacia_door' => array(
        'id' => 430,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/9/9d/Acacia_Door.png',
    ),
    'dark_oak_door' => array(
        'id' => 431,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/3/36/Dark_Oak_Door.png',
    ),
    'chorus_fruit' => array(
        'id' => 432,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/e/ef/Chorus_Fruit.png',
    ),
    'chorus_fruit_popped' => array(
        'id' => 433,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/3/3b/Popped_Chorus_Fruit.png',
    ),
    'beetroot' => array(
        'id' => 434,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/5/56/Beetroot.png',
    ),
    'beetroot_seeds' => array(
        'id' => 435,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/0/04/Beetroot_Seeds.png',
    ),
    'beetroot_soup' => array(
        'id' => 436,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/c/c6/Dragon%27s_Breath.png',
    ),
    'dragon_breath' => array(
        'id' => 437,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/6/61/Beetroot_Soup.png',
    ),
    'splash_potion' => array(
        'id' => 438,
        'stack' => 1,
        'avail' => true,
        'group' => 'splash_potion_types',
        'icon_url' => '/0/02/Splash_Potions.gif',
        'subtypes' => array(
            16341 => array('name' => 'potion_of_healing', 'avail' => true, 'icon_url' => '/a/a3/Grid_Potion_of_Healing.png'),
            16384 => array('name' => 'splash_mundane_potion', 'avail' => true, 'icon_url' => '/0/0b/Grid_Splash_Mundane_Potion.png'),
            16385 => array('name' => 'splash_potion_of_regeneration', 'avail' => true, 'icon_url' => '/0/0f/Grid_Splash_Potion_of_Regeneration.png'),
            16386 => array('name' => 'splash_potion_of_swiftness', 'avail' => true, 'icon_url' => '/1/1c/Grid_Potion_of_Swiftness.png'),
            16387 => array('name' => 'splash_potion_of_fire_resistance', 'avail' => true, 'icon_url' => '/c/cb/Grid_Splash_Potion_of_Fire_Resistance.png'),
            16388 => array('name' => 'splash_potion_of_poison', 'avail' => true, 'icon_url' => '/1/11/Grid_Splash_Potion_of_Poison.png'),
            16389 => array('name' => 'splash_potion_of_healing', 'avail' => true, 'icon_url' => '/3/33/Grid_Splash_Potion_of_Healing.png'),
            16392 => array('name' => 'splash_potion_of_weakness', 'avail' => true, 'icon_url' => '/9/94/Grid_Splash_Potion_of_Weakness.png'),
            16393 => array('name' => 'splash_potion_of_strength', 'avail' => true, 'icon_url' => '/3/38/Grid_Splash_Potion_of_Strength.png'),
            16394 => array('name' => 'splash_potion_of_slowness', 'avail' => true, 'icon_url' => '/2/22/Grid_Splash_Potion_of_Slowness.png'),
            16396 => array('name' => 'splash_potion_of_harming', 'avail' => true, 'icon_url' => '/5/52/Grid_Splash_Potion_of_Harming.png'),
            16417 => array('name' => 'splash_potion_of_regeneration_ii', 'avail' => true, 'icon_url' => '/0/0f/Grid_Splash_Potion_of_Regeneration.png'),
            16418 => array('name' => 'splash_potion_of_swiftness_ii', 'avail' => true, 'icon_url' => '/7/7a/Grid_Splash_Potion_of_Swiftness.png'),
            16419 => array('name' => 'splash_potion_of_fire_resistance_(reverted)', 'avail' => true, 'icon_url' => '/c/cb/Grid_Splash_Potion_of_Fire_Resistance.png'),
            16420 => array('name' => 'splash_potion_of_poison_ii', 'avail' => true, 'icon_url' => '/1/11/Grid_Splash_Potion_of_Poison.png'),
            16421 => array('name' => 'splash_potion_of_healing_ii', 'avail' => true, 'icon_url' => '/3/33/Grid_Splash_Potion_of_Healing.png'),
            16422 => array('name' => 'splash_potion_of_night_vision', 'avail' => true, 'icon_url' => '/c/c3/Grid_Splash_Potion_of_Night_Vision.png'),
            16424 => array('name' => 'splash_potion_of_weakness_(reverted)', 'avail' => true, 'icon_url' => '/9/94/Grid_Splash_Potion_of_Weakness.png'),
            16425 => array('name' => 'splash_potion_of_strength_ii', 'avail' => true, 'icon_url' => '/3/38/Grid_Splash_Potion_of_Strength.png'),
            16426 => array('name' => 'splash_potion_of_slowness_(reverted)', 'avail' => true, 'icon_url' => '/2/22/Grid_Splash_Potion_of_Slowness.png'),
            16428 => array('name' => 'splash_potion_of_harming_ii', 'avail' => true, 'icon_url' => '/5/52/Grid_Splash_Potion_of_Harming.png'),
            16430 => array('name' => 'splash_potion_of_invisibility', 'avail' => true, 'icon_url' => '/8/89/Grid_Splash_Potion_of_Invisibility.png'),
            16449 => array('name' => 'splash_potion_of_regeneration', 'avail' => true, 'icon_url' => '/0/0f/Grid_Splash_Potion_of_Regeneration.png'),
            16450 => array('name' => 'splash_potion_of_swiftness', 'avail' => true, 'icon_url' => '/7/7a/Grid_Splash_Potion_of_Swiftness.png'),
            16451 => array('name' => 'splash_potion_of_fire_resistance', 'avail' => true, 'icon_url' => '/c/cb/Grid_Splash_Potion_of_Fire_Resistance.png'),
            16452 => array('name' => 'splash_potion_of_poison', 'avail' => true, 'icon_url' => '/1/11/Grid_Splash_Potion_of_Poison.png'),
            16453 => array('name' => 'splash_potion_of_healing_(reverted)', 'avail' => true, 'icon_url' => '/3/33/Grid_Splash_Potion_of_Healing.png'),
            16454 => array('name' => 'splash_potion_of_night_vision', 'avail' => true, 'icon_url' => '/c/c3/Grid_Splash_Potion_of_Night_Vision.png'),
            16456 => array('name' => 'splash_potion_of_weakness', 'avail' => true, 'icon_url' => '/9/94/Grid_Splash_Potion_of_Weakness.png'),
            16457 => array('name' => 'splash_potion_of_strength', 'avail' => true, 'icon_url' => '/3/38/Grid_Splash_Potion_of_Strength.png'),
            16458 => array('name' => 'splash_potion_of_slowness', 'avail' => true, 'icon_url' => '/2/22/Grid_Splash_Potion_of_Slowness.png'),
            16460 => array('name' => 'splash_potion_of_harming_(reverted)', 'avail' => true, 'icon_url' => '/5/52/Grid_Splash_Potion_of_Harming.png'),
            16462 => array('name' => 'splash_potion_of_invisibility', 'avail' => true, 'icon_url' => '/8/89/Grid_Splash_Potion_of_Invisibility.png'),
            16481 => array('name' => 'splash_potion_of_regeneration_ii', 'avail' => true, 'icon_url' => '/0/0f/Grid_Splash_Potion_of_Regeneration.png'),
            16482 => array('name' => 'splash_potion_of_swiftness_ii', 'avail' => true, 'icon_url' => '/7/7a/Grid_Splash_Potion_of_Swiftness.png'),
            16484 => array('name' => 'splash_potion_of_strength_ii', 'avail' => true, 'icon_url' => '/3/38/Grid_Splash_Potion_of_Strength.png'),
            16489 => array('name' => 'splash_potion_of_strength_ii', 'avail' => true, 'icon_url' => '/3/38/Grid_Splash_Potion_of_Strength.png'),
        ),
    ),
    'spectral_arrow' => array(
        'id' => 439,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/4/41/Arrow.png',
    ),
    'tipped_arrow' => array(
        'id' => 440,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/4/41/Arrow.png',
    ),
    'lingering_potion' => array(
        'id' => 441,
        'stack' => 1,
        'avail' => true,
        'group' => 'lingering_potion_types',
        'icon_url' => '/1/19/Lingering_Potions.gif',
        'subtypes' => array(
            16341 => array('name' => 'potion_of_healing', 'avail' => true, 'icon_url' => '/a/a3/Grid_Potion_of_Healing.png'),
            16384 => array('name' => 'splash_mundane_potion', 'avail' => true, 'icon_url' => '/0/0b/Grid_Splash_Mundane_Potion.png'),
            16385 => array('name' => 'splash_potion_of_regeneration', 'avail' => true, 'icon_url' => '/0/0f/Grid_Splash_Potion_of_Regeneration.png'),
            16386 => array('name' => 'splash_potion_of_swiftness', 'avail' => true, 'icon_url' => '/1/1c/Grid_Potion_of_Swiftness.png'),
            16387 => array('name' => 'splash_potion_of_fire_resistance', 'avail' => true, 'icon_url' => '/c/cb/Grid_Splash_Potion_of_Fire_Resistance.png'),
            16388 => array('name' => 'splash_potion_of_poison', 'avail' => true, 'icon_url' => '/1/11/Grid_Splash_Potion_of_Poison.png'),
            16389 => array('name' => 'splash_potion_of_healing', 'avail' => true, 'icon_url' => '/3/33/Grid_Splash_Potion_of_Healing.png'),
            16392 => array('name' => 'splash_potion_of_weakness', 'avail' => true, 'icon_url' => '/9/94/Grid_Splash_Potion_of_Weakness.png'),
            16393 => array('name' => 'splash_potion_of_strength', 'avail' => true, 'icon_url' => '/3/38/Grid_Splash_Potion_of_Strength.png'),
            16394 => array('name' => 'splash_potion_of_slowness', 'avail' => true, 'icon_url' => '/2/22/Grid_Splash_Potion_of_Slowness.png'),
            16396 => array('name' => 'splash_potion_of_harming', 'avail' => true, 'icon_url' => '/5/52/Grid_Splash_Potion_of_Harming.png'),
            16417 => array('name' => 'splash_potion_of_regeneration_ii', 'avail' => true, 'icon_url' => '/0/0f/Grid_Splash_Potion_of_Regeneration.png'),
            16418 => array('name' => 'splash_potion_of_swiftness_ii', 'avail' => true, 'icon_url' => '/7/7a/Grid_Splash_Potion_of_Swiftness.png'),
            16419 => array('name' => 'splash_potion_of_fire_resistance_(reverted)', 'avail' => true, 'icon_url' => '/c/cb/Grid_Splash_Potion_of_Fire_Resistance.png'),
            16420 => array('name' => 'splash_potion_of_poison_ii', 'avail' => true, 'icon_url' => '/1/11/Grid_Splash_Potion_of_Poison.png'),
            16421 => array('name' => 'splash_potion_of_healing_ii', 'avail' => true, 'icon_url' => '/3/33/Grid_Splash_Potion_of_Healing.png'),
            16422 => array('name' => 'splash_potion_of_night_vision', 'avail' => true, 'icon_url' => '/c/c3/Grid_Splash_Potion_of_Night_Vision.png'),
            16424 => array('name' => 'splash_potion_of_weakness_(reverted)', 'avail' => true, 'icon_url' => '/9/94/Grid_Splash_Potion_of_Weakness.png'),
            16425 => array('name' => 'splash_potion_of_strength_ii', 'avail' => true, 'icon_url' => '/3/38/Grid_Splash_Potion_of_Strength.png'),
            16426 => array('name' => 'splash_potion_of_slowness_(reverted)', 'avail' => true, 'icon_url' => '/2/22/Grid_Splash_Potion_of_Slowness.png'),
            16428 => array('name' => 'splash_potion_of_harming_ii', 'avail' => true, 'icon_url' => '/5/52/Grid_Splash_Potion_of_Harming.png'),
            16430 => array('name' => 'splash_potion_of_invisibility', 'avail' => true, 'icon_url' => '/8/89/Grid_Splash_Potion_of_Invisibility.png'),
            16449 => array('name' => 'splash_potion_of_regeneration', 'avail' => true, 'icon_url' => '/0/0f/Grid_Splash_Potion_of_Regeneration.png'),
            16450 => array('name' => 'splash_potion_of_swiftness', 'avail' => true, 'icon_url' => '/7/7a/Grid_Splash_Potion_of_Swiftness.png'),
            16451 => array('name' => 'splash_potion_of_fire_resistance', 'avail' => true, 'icon_url' => '/c/cb/Grid_Splash_Potion_of_Fire_Resistance.png'),
            16452 => array('name' => 'splash_potion_of_poison', 'avail' => true, 'icon_url' => '/1/11/Grid_Splash_Potion_of_Poison.png'),
            16453 => array('name' => 'splash_potion_of_healing_(reverted)', 'avail' => true, 'icon_url' => '/3/33/Grid_Splash_Potion_of_Healing.png'),
            16454 => array('name' => 'splash_potion_of_night_vision', 'avail' => true, 'icon_url' => '/c/c3/Grid_Splash_Potion_of_Night_Vision.png'),
            16456 => array('name' => 'splash_potion_of_weakness', 'avail' => true, 'icon_url' => '/9/94/Grid_Splash_Potion_of_Weakness.png'),
            16457 => array('name' => 'splash_potion_of_strength', 'avail' => true, 'icon_url' => '/3/38/Grid_Splash_Potion_of_Strength.png'),
            16458 => array('name' => 'splash_potion_of_slowness', 'avail' => true, 'icon_url' => '/2/22/Grid_Splash_Potion_of_Slowness.png'),
            16460 => array('name' => 'splash_potion_of_harming_(reverted)', 'avail' => true, 'icon_url' => '/5/52/Grid_Splash_Potion_of_Harming.png'),
            16462 => array('name' => 'splash_potion_of_invisibility', 'avail' => true, 'icon_url' => '/8/89/Grid_Splash_Potion_of_Invisibility.png'),
            16481 => array('name' => 'splash_potion_of_regeneration_ii', 'avail' => true, 'icon_url' => '/0/0f/Grid_Splash_Potion_of_Regeneration.png'),
            16482 => array('name' => 'splash_potion_of_swiftness_ii', 'avail' => true, 'icon_url' => '/7/7a/Grid_Splash_Potion_of_Swiftness.png'),
            16484 => array('name' => 'splash_potion_of_strength_ii', 'avail' => true, 'icon_url' => '/3/38/Grid_Splash_Potion_of_Strength.png'),
            16489 => array('name' => 'splash_potion_of_strength_ii', 'avail' => true, 'icon_url' => '/3/38/Grid_Splash_Potion_of_Strength.png'),
        ),
    ),
    'shield' => array(
        'id' => 442,
        'stack' => 1,
        'avail' => true,
        'notrade' => true,
        'icon_url' => '/4/41/Arrow.png', // TODO Find correct item icon
    ),
    'elytra' => array(
        'id' => 443,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/7/70/Elytra.png',
    ),
    'spruce_boat' => array(
        'id' => 444,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/5/59/Grid_Boat.png',
    ),
    'birch_boat' => array(
        'id' => 445,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/5/59/Grid_Boat.png',
    ),
    'jungle_boat' => array(
        'id' => 446,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/5/59/Grid_Boat.png',
    ),
    'acacia_boat' => array(
        'id' => 447,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/5/59/Grid_Boat.png',
    ),
    'dark_oak_boat' => array(
        'id' => 448,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/5/59/Grid_Boat.png',
    ),
    'totem' => array(
        'id' => 449,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/5/57/Totem_of_Undying.png',
    ),
    'shulker_shell' => array(
        'id' => 450,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/b/ba/Shulker_Shell.png',
    ),
    'iron_nugget' => array(
        'id' => 452,
        'stack' => 64,
        'avail' => true,
        'icon_url' => '/e/e7/Iron_Nugget.png',
    ),

    /*************************************************/
    /*                RECORDS                        */
    /*************************************************/

    'record_13' => array(
        'id' => 2256,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/e/e9/Grid_13_Disc.png',
    ),
    'record_cat' => array(
        'id' => 2257,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/1/10/Grid_cat_Disc.png',
    ),
    'record_blocks' => array(
        'id' => 2258,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/a/ad/Grid_blocks_Disc.png',
    ),
    'record_chirp' => array(
        'id' => 2259,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/8/8b/Grid_chirp_Disc.png',
    ),
    'record_far' => array(
        'id' => 2260,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/2/22/Grid_far_Disc.png',
    ),
    'record_mall' => array(
        'id' => 2261,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/0/0a/Grid_mall_Disc.png',
    ),
    'record_mellohi' => array(
        'id' => 2262,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/f/f4/Grid_mellohi_Disc.png',
    ),
    'record_stal' => array(
        'id' => 2263,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/6/68/Grid_stal_Disc.png',
    ),
    'record_strad' => array(
        'id' => 2264,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/a/a3/Grid_strad_Disc.png',
    ),
    'record_ward' => array(
        'id' => 2265,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/1/18/Grid_ward_Disc.png',
    ),
    'record_11' => array(
        'id' => 2266,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/e/e9/Grid_13_Disc.png',
    ),
    'record_wait' => array(
        'id' => 2267,
        'stack' => 1,
        'avail' => true,
        'icon_url' => '/6/67/Grid_wait_Disc.png',
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
    'clay' => 'clayblock',
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
    'gold_axe' => 'golden_axe',
    'gold_barding' => 'golden_horse_armor',
    'gold_boots' => 'golden_boots',
    'gold_chestplate' => 'golden_chestplate',
    'gold_helmet' => 'golden_helmet',
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
    'melon' => 'melonslice',
    'monster_egg' => 'spawn_egg',
    'monster_eggs' => 'monster_egg',
    'mushroom_soup' => 'mushroom_stew',
    'mycel' => 'mycelium',
    'nether_fence' => 'nether_brick_fence',
    'nether_stalk' => 'nether_wart',
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
    'skull_item' => 'skeletonskull',
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
