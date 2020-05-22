<?php

/**
 * returns the text of an enchantment based on a value in the array
 * this is used if we do not have that ALL_CAPS value but the ID for example
 * returns the value of the field
 *
 * @global array $UMC_DATA_ENCHANTMENTS
 * @param type $search_field
 * @param type $search_value
 * @param type $return_field
 * @return type
 */
function umc_enchant_text_find($search_field, $search_value, $return_field) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_DATA_ENCHANTMENTS;
    //TODO: This needs to be simplified. Either we find a better way to directly
    // get the text key, or we change the array once we do not need the text
    // keys anymore when NBT is fully implemented.

    // we get the numeric key
    $ench_key = array_search(strtolower($search_value), array_column($UMC_DATA_ENCHANTMENTS, $search_field));
    // get the text key from the numeric
    $keys = array_keys($UMC_DATA_ENCHANTMENTS);
    $text_key = $keys[$ench_key];

    $text = $UMC_DATA_ENCHANTMENTS[$text_key][$return_field];
    return $text;
}


// this uses /mc_assets/minecraft-data to get al enchantment data from the wiki
function umc_enchantment_data_create() {
    global $UMC_SETTING, $UMC_PATH_MC;

    $version = $UMC_SETTING['mc_version_minor'];
    $path = "$UMC_PATH_MC/server/mc_assets/minecraft-data/data/pc/$version/";
    $file_contents = file_get_contents($path . 'enchantments.json');
    $data = json_decode($file_contents);

    $short_names = array(
        'aqua_affinity' => 'Aqua',
        'bane_of_arthropods' => 'Bane',
        'blast_protection' => 'Blast',
        'unbreaking' => 'UnBr',
        'channeling' => 'Chan',
        'binding_curse' => 'Bind',
        'vanishing_curse' => 'Vanish',
        'depth_strider' => 'Depth',
        'efficiency' => 'Eff',
        'feather_falling' => 'Feat',
        'fire_aspect' => 'FireA',
        'fire_protection' => 'FProt',
        'flame' => 'Flame',
        'fortune' => 'Fort',
        'frost_walker' => 'Frost',
        'impaling' => 'Imp',
        'infinity' => 'Inf',
        'knockback' => 'Knock',
        'looting' => 'Loot',
        'loyalty' => 'Loyal',
        'luck_of_the_sea' => 'Luck',
        'lure' => 'Lure',
        'mending' => 'Mend',
        'multishot' => 'Mult',
        'piercing' => 'Pierc',
        'power' => 'Pow',
        'projectile_protection' => 'PProt',
        'protection' => 'Prot',
        'punch' => 'Punch',
        'quick_charge' => 'QChar',
        'respiration' => 'Resp',
        'riptide' => 'Rip',
        'sharpness' => 'Sharp',
        'silk_touch' => 'Silk',
        'smite' => 'Smite',
        'sweeping' => 'Sweep',
        'thorns' => 'Thorn',
        'unbreaking' => 'Unbr',
    );

    $array_data = array();
    foreach ($data as $obj) {
        $fullname = $obj->itemName;
        $name_split = explode(".", $fullname);
        $item_name = $name_split[1];

        $item_array = array();
        foreach ($obj->primaries as $item) {
            $name_split = explode(".", $item);
            $item_array[] = $name_split[1];
        }
        foreach ($obj->secondaries as $item) {
            $name_split = explode(".", $item);
            $item_array[] = $name_split[1];
        }

        $conflict_array = array();
        foreach ($obj->excludes as $ench) {
            $name_split = explode(".", $ench);
            $conflict_array[] = $name_split[1];
        }

        $array_data[$item_name] = array(
            'key' => $fullname,
            'name' => $obj->displayName,
            'short' => $short_names[$item_name],
            'max' => $obj->maxLevel,
            'items' => $item_array,
            'conflicts' => $conflict_array,
            'probability' => $obj->probability,
        );
    }

    ksort($array_data);
    umc_array2file($array_data, "UMC_DATA_ENCHANTMENTS", "/home/minecraft/server/bin/assets/item_enchantments.inc.php");
}
