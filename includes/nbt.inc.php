<?php

/**
 * Fix the = into : for proper minecraft-valid NBT
 * The result can be used in /give etc
 * 
 * @param type $nbt_raw
 * @return type
 */
function umc_nbt_cleanup($nbt_raw) {
    $regex = "/=(?=([^\"']*[\"'][^\"']*[\"'])*[^\"']*$)/";
    $meta_cmd = preg_replace($regex, ":", $nbt_raw);                
    return $meta_cmd;
}

/**
 * Convert minecraft NBT to a valid JSON array
 * 
 * @param type $nbt
 * @return type
 */
function umc_nbt_to_json($nbt) {
    $split_regex = '/([,{]{1,2})([^,}:]*):/';
    $json_text = preg_replace($split_regex, '$1"$2":', $nbt);  
    $json = json_decode($json_text);
    return $json;
}

/**
 * takes an NBT string and converts it into something readable
 * 
 * @param type $nbt
 */
function unc_nbt_display($nbt, $format) {
    $json = umc_nbt_to_json($nbt);
    $formats = array(
        'long_text',
    );
    if (in_array($format, $formats) && function_exists('unc_nbt_display_' . $format)) {
        $function = 'unc_nbt_display_' . $format;
        $text = $function($json);
    }
    return $text;
}

function unc_nbt_display_long_text($json) {
    global $ENCH_ITEMS;
    $text = '';
    foreach ($json as $feature => $data) {
        $feat = strtolower($feature);
        switch ($feat) {
            case 'ench': 
                $text .= "Enchantments: ";
                // example enchantment {ench:[{lvl:5,id:16},{lvl:5,id:17},{lvl:5,id:18},{lvl:2,id:19},{lvl:2,id:20},{lvl:3,id:21}]}
                foreach ($data as $ench) {
                    // find the id in the enchantments data
                    $ench_type = array_search($ench['id'], array_column($ENCH_ITEMS, 'id'));
                    $text .= $ENCH_ITEMS[$ench_type]['name'] . " Lvl {$ench['lvl']}, ";
                }
                break;
            case 'display':
                
                break;
            case 'repaircost':
                
                break;
            case 'attributemodifiers':
                
                break;
            case 'candestroy':
                
                break;
            case 'canplaceon':
                
                break;
            case 'blockentitytag': //shields, shulker boxes, banners
                //banner/shield example  {BlockEntityTag:{Patterns:[{Color:2,Pattern:"dls"},{Color:5,Pattern:"rud"}]}}
                // patterns 
                // items
                
                
                break;
            case 'pages': // for books
                break;
            case 'title': // for books
                break;
            case 'author': // for books
                break;
            case 'generation': // for books
                break;
        
        }
    }
    return $text;
}