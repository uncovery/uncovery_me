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
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    // this regex basically takes all the array keys from the NBT data into $2 and puts quotes around them.

    // check if we have encapsulated JSON
    $fix_regex = '/(?<front>.*:\[)"(?<inside>{.+})"(?<back>\],.*)/';
    $matches = false;
    preg_match_all($fix_regex, $nbt, $matches);

    XMPP_ERROR_trace("nbt_matches", $matches);

    $fix_nbt_regex = '/([,{]{1,2})([^,}:]*):/';

    // do we have a multi-level JSON?
    if ($matches && isset($matches['inside'][0])) {
        $front = preg_replace($fix_nbt_regex, '$1"$2":', $matches['front'][0]);
        $back = preg_replace($fix_nbt_regex, '$1"$2":', $matches['back'][0]);
        $inside_fix = str_replace('"}","{"', '"},{"', $matches['inside'][0]);
        $json = $front . $inside_fix . $back;
    } else {
        $json = preg_replace($fix_nbt_regex, '$1"$2":', $nbt);
    }
    XMPP_ERROR_trace("nbt_fixed", $json);

    // now we have valid json, decode it please
    $nbt_array = json_decode($json, true);
    if (!$nbt_array) {
        XMPP_ERROR_trigger("NBT Array invalid: $json");
    }
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
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $nbt_array = umc_nbt_to_array($nbt);
    $formats = array(
        'long_text',
    );
    $text = '';
    if (in_array($format, $formats) && function_exists('umc_nbt_display_' . $format)) {
        $function = 'umc_nbt_display_' . $format;
        $text = $function($nbt_array);
    }
    return $text;
}

/**
 * pure text output with all details.
 *
 * @param type $nbt_array
 * @return type
 */
function umc_nbt_display_long_text($nbt_array) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $text = '';
    foreach ($nbt_array as $feature => $data) {
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
                if (isset($data['Lore'])) {
                    $text .= "Lore: " . implode('\n', $data['Lore']) . '\n';
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
            case 'blockentitytag': //shields, shulker boxes, banners, fireworks?
                if (isset($data['Patterns'])) {
                    $text .= umc_patterns_get_text($data['Patterns'], 'long')  . '\n';
                }
                if (isset($data['Items'])) {
                    $items = array();
                    foreach ($data['Items'] as $slot) {
                        $nbt_text = '';
                        if (isset($slot['tag'])) { // we have additional per-item NBT data
                            $nbt_text = umc_nbt_display_long_text($slot['tag']);
                        }
                        $item = umc_goods_get_text($slot['id'], $slot['Damage']);
                        $items[] = $slot['Count'] . " " . $item['name'] . $nbt_text;
                    }
                    $text .= implode(", ", $items) . '\n';
                }
                break;
            case 'fireworks':
                // {Fireworks:{Flight:2,Explosions:[{Type:1,Flicker:0,Trail:1,Colors:[11743532,5320730,8073150],FadeColors:[3887386,4312372,6719955]}]}}
                $text .= $feature;
                break;
            case 'pages': // for books
                $text .= ucwords($feature) . ": " . count($data) . '\n';
                break;
            case 'title': // for books
            case 'author':
                $text .= ucwords("$feature: $data") . '\n';
                break;
            case 'generation': // for books
                $generations = array('0' => 'original', '1' => 'copy of original', '2' => 'copy of copy', '3' => 'tattered');
                $text .= ucwords("$feature: " . $generations[$data]) . '\n';
                break;
            default:
                XMPP_ERROR_trigger("Unknown NBT Type $feature");
        }
    }
    return $text;
}