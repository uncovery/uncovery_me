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
 * Convert minecraft NBT to a valid JSON then to an array
 * 
 * @param type $nbt
 * @return type
 */
function umc_nbt_to_array($nbt) {
    // this regex basically takes all the array keys from the NBT data into $2 and puts quotes around them.
    $regex = '/([,{]{1,2})([^,}:]*):/';
    $json = preg_replace($regex, '$1"$2":', $nbt);  
    // now we have valid json, decode it please
    $nbt_array = json_decode($json, true);
    // we sort it so that same items with different order are displayed the same
    // I am not sure this is necessary though.
    // array_multisort($nbt_array);
    return $nbt_array;
}

/**
 * takes an NBT string and converts it into something readable
 * 
 * @param type $nbt
 */
function umc_nbt_display($nbt, $format) {
    $json = umc_nbt_to_array($nbt);
    $formats = array(
        'long_text',
    );
    $text = '';
    if (in_array($format, $formats) && function_exists('umc_nbt_display_' . $format)) {
        $function = 'umc_nbt_display_' . $format;
        $text = $function($json);
    }
    return $text;
}

function umc_nbt_display_long_text($json) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $text = '';
    foreach ($json as $feature => $data) {
        $feat = strtolower($feature);
        switch ($feat) {
            case 'ench': 
                $text .= "Enchantments: ";
                // example enchantment {ench:[{lvl:5,id:16},{lvl:5,id:17},{lvl:5,id:18},{lvl:2,id:19},{lvl:2,id:20},{lvl:3,id:21}]}
                $enchs = array();
                foreach ($data as $ench) {
                    // find the id in the enchantments data
                    $ench_name = umc_enchant_text_find('id', $ench['id'], 'name');
                    $enchs[] = $ench_name . " Lvl {$ench['lvl']}"; 
                }
                $text .= implode(", ", $enchs) . '\n';
                break;
            case 'display':               
                if (isset($data['Name'])) {
                    $text .= "Called " . $data['Name'] . '\n';
                }
                break;
            case 'repaircost':
                $text .= "Repair Costs: $data". '\n';
                break;
            case 'attributemodifiers':
                // this is so far ignored. We have to find out if we really need this
                // $text .= $feature;
                break;
            case 'candestroy':
                $text .= "Can be destroy: ";
                $items = array();
                foreach ($data as $item_name) {
                    $item = umc_goods_get_text($item_name);
                    $items[] = $item['name'];
                }
                $text .= implode(", ", $items) . '\n';
                break;
            case 'canplaceon':
                $text .= "Can be placed on: ";
                $items = array();
                foreach ($data as $item_name) {
                    $item = umc_goods_get_text($item_name);
                    $items[] = $item['name'];
                }
                $text .= implode(", ", $items) . '\n';
                break;
            case 'blockentitytag': //shields, shulker boxes, banners
                //banner/shield example  {BlockEntityTag:{Patterns:[{Color:2,Pattern:"dls"},{Color:5,Pattern:"rud"}]}}
                // patterns 
                if (isset($data['Patterns'])) {
                    $text .= umc_patterns_get_text($data['Patterns'], 'long')  . '\n';
                }
                // items
                
                $text .= $feature;
                break;
            case 'pages': // for books
                $text .= $feature;
                break;
            case 'title': // for books
                $text .= $feature;
                break;
            case 'author': // for books
                $text .= $feature;
                break;
            case 'generation': // for books
                $text .= $feature;
                break;
            default:
                XMPP_ERROR_trigger("Unknown NBT Type $feature");
        }
    }
    return $text;
}