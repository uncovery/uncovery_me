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
    $nbt_clean = preg_replace($regex, ":", $nbt_raw);
    
    $nbt_sorted = umc_nbt_sort_enchantments($nbt_clean);
    return $nbt_sorted;
}

/**
 * Convert minecraft NBT to a valid JSON then to an array
 *
 * @param type $nbt
 * @return type
 */
function umc_nbt_to_array($nbt) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    // check if we have encapsulated JSON
    // we try to find quotes between :[ and { as well as on the backside between } and ],
    // we split in three parts, the inside is the book pages
    $fix_regex = '/(?<front>.*:\[)"(?<inside>{.+})"(?<back>\],.*)/';
    $matches = false;
    // TODO: do this regex only if we actually have a book (or whatever else this applies to)
    preg_match_all($fix_regex, $nbt, $matches);

    // this regex marks the array keys so that they can be put in quotes.
    // this regex basically puts all the array keys from the NBT data into match $2 and puts quotes around them.
    $fix_nbt_regex = '/([,{]{1,2})([^,}:]*):/';

    // do we have a multi-level JSON?
    if ($matches && isset($matches['inside'][0])) {
        // put quotes around the keys in "normal" part of the JSON
        $front = preg_replace($fix_nbt_regex, '$1"$2":', $matches['front'][0]);
        $back = preg_replace($fix_nbt_regex, '$1"$2":', $matches['back'][0]);
        // eliminate quotes around the comma between pages
        $inside_fix = str_replace('"}","{"', '"},{"', $matches['inside'][0]);
        // put everything back together
        $json = $front . $inside_fix . $back;
    } else {
        // put quotes around the keys
        $json = preg_replace($fix_nbt_regex, '$1"$2":', $nbt);
    }
    // XMPP_ERROR_trace("nbt_fixed", $json);

    // now we have valid json, decode it please
    $nbt_array = json_decode($json, true);
    if (!$nbt_array) {
        XMPP_ERROR_trigger("NBT Array invalid: $json");
    }

    return $nbt_array;
}

/**
 * takes an NBT string, converts it to an array, sorts the enchantments,
 * then converts it back to an NBT String.
 * 
 * @param type $nbt
 */
function umc_nbt_sort_enchantments($nbt) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    
    // only do this if we have enchantments in the array
    if (strpos($nbt, 'ench:') === false) {
        return $nbt;
    }
    // convert the NBT to an array so we can search it
    $array = umc_nbt_to_array($nbt);

    // let's iterate the array
    foreach ($array as $key => $value) {
        if (strtolower($key) == 'ench') { // find the enchantment
            $types = array();
            $levels = array();
            foreach ($value as $valkey => $row) { // get a sortable array for array_multisort
                $types[$valkey]  = $row['id'];
                $levels[$valkey] = $row['lvl'];
            }
            array_multisort($types, SORT_DESC, $levels, SORT_DESC, $value); // sort it
            $array[$key] = $value; //re-insert
        }
    }

    $json = json_encode($array); // convert the array to JSON
    $out_nbt = umc_nbt_from_json($json); // fix the JSON to valid NBT

    return $out_nbt;
}

/**
 * take the quotes away from keys and leave them around string values
 * IN: {"ench":[{"lvl":3,"id":34},{"lvl":5,"id":48},{"lvl":2,"id":49},{"lvl":1,"id":50},{"lvl":1,"id":51}]} 
 * OUT: {ench:[{lvl:5,id:48},{lvl:2,id:49},{lvl:1,id:50},{lvl:3,id:34},{lvl:1,id:51}]}
 * @param type $json
 * @return type
 * 
 */
function umc_nbt_from_json($json) {
    $regex = '/([,{]{1,2})"([^,}:]*)":/';
    $nbt = preg_replace($regex, '$1$2:', $json);
    return $nbt;
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
        'long_text', 'short_text','in_game'
    );
    $text = '';
    if (in_array($format, $formats) && function_exists('umc_nbt_display_' . $format)) {
        $function = 'umc_nbt_display_' . $format;
        $text = $function($nbt_array);
    } else {
        XMPP_ERROR_trigger("ERROR: NBT display format '$format' does not exist!");
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
            case 'entitytag':
                // Spawn eggs: {EntityTag:{id:"minecraft:blaze"}}
                $item_text_arr = explode(":", $data['id']);
                $item_text = $item_text_arr[1];
                $text .= umc_pretty_name($item_text);
                break;
            case 'display': // armor dyes
                if (isset($data['color'])) {
                    $text .= "dyed";
                }
                break;
            case 'ench':
            case 'storedenchantments':
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
                $text .= "Flight Duration: " . $data['Flight'] . '\n';
                $explosions = array(0 => 'Small Ball', 1 => 'Large Ball', 2 => 'Star-Shaped', 3 => 'Creeper-Shaped',  3 => 'Sparkle',);
                $e_data = $data['Explosions'][0];
                $explosion_type = $e_data['Type'];
                $text .= "Explosion " . $explosions[$explosion_type];
                if (isset($e_data['Flicker']) && $e_data['Flicker'] == 1) {
                    $text .= ', Flicker';
                }
                if (isset($e_data['Trail']) && $e_data['Trail'] == 1) {
                    $text .= ', Trail';
                }
                $text .= '\n';
                $text .= "Colors: " . count($e_data['Colors']) . '\n';
                $text .= "Fade Colors: " . count($e_data['FadeColors']) . '\n';
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
            case 'resolved': // "closed books"
                break;
            case 'potion':
                $text .= umc_potion_text_find($data, 'long_text');
                break;
            default:
                XMPP_ERROR_trigger("Unknown NBT Type '$feature'");
        }
    }
    return $text;
}

/**
 * pure text output with all details.
 *
 * @param type $nbt_array
 * @return type
 */
function umc_nbt_display_short_text($nbt_array) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $text = '';
    foreach ($nbt_array as $feature => $data) {
        $feat = strtolower($feature);
        switch ($feat) {
            case 'entitytag':
                // Spawn eggs: {EntityTag:{id:"minecraft:blaze"}}
                $item_text_arr = explode(":", $data['id']);
                $item_text = $item_text_arr[1];
                $text .= umc_pretty_name($item_text);
                break;
            case 'display': // armor dyes
                if (isset($data['color'])) {
                    $text .= "dyed";
                }
                break;
            case 'ench':
            case 'storedenchantments':
                $text .= "Ench: ";
                // example enchantment {ench:[{lvl:5,id:16},{lvl:5,id:17},{lvl:5,id:18},{lvl:2,id:19},{lvl:2,id:20},{lvl:3,id:21}]}
                $enchs = array();
                foreach ($data as $ench) {
                    // find the id in the enchantments data
                    $ench_name = umc_enchant_text_find('id', $ench['id'], 'short');
                    $enchs[] = $ench_name . " {$ench['lvl']}";
                }
                $text .= implode(", ", $enchs);
                break;
            case 'display':
                if (isset($data['Name'])) {
                    $text .= ' "' . $data['Name'] . '"' ;
                }
                break;
            case 'generation': // for books
            case 'resolved': // "closed books"
            case 'repaircost':
            case 'attributemodifiers':
            case 'candestroy':
            case 'canplaceon':
                break;
            case 'blockentitytag': //shields, shulker boxes, banners, fireworks?
                if (isset($data['Patterns'])) {
                    $text .= umc_patterns_get_text($data['Patterns'], 'long')  . '\n';
                }
                if (isset($data['Items'])) {
                    $text .= 'with ' . count($data['Items']) . ' items';
                }
                break;
            case 'fireworks':
                // {Fireworks:{Flight:2,Explosions:[{Type:1,Flicker:0,Trail:1,Colors:[11743532,5320730,8073150],FadeColors:[3887386,4312372,6719955]}]}}
                $text .= "Flight Duration: " . $data['Flight'] . '\n';
                $explosions = array(0 => 'Small Ball', 1 => 'Large Ball', 2 => 'Star-Shaped', 3 => 'Creeper-Shaped',  3 => 'Sparkle',);
                if (isset($data['Explosions'])) {
                    $e_data = $data['Explosions'][0];
                    $explosion_type = $e_data['Type'];
                    $text .= "Explosion " . $explosions[$explosion_type];
                }
                if (isset($e_data['Flicker']) && $e_data['Flicker'] == 1) {
                    $text .= ', Flicker';
                }
                if (isset($e_data['Trail']) && $e_data['Trail'] == 1) {
                    $text .= ', Trail';
                }
                $text .= '\n';
                $text .= "Colors: " . count($e_data['Colors']) . '\n';
                $text .= "Fade Colors: " . count($e_data['FadeColors']) . '\n';
                break;
            case 'pages': // for books
                $text .= count($data) . ' Pages';
                break;
            case 'title': // for books
            case 'author':
                $text .= ucwords("$feature: $data") . '\n';
                break;
            case 'potion': 
                $text .= umc_potion_text_find($data, 'short_text');
                break;
            default:
                XMPP_ERROR_trigger("Unknown NBT Type '$feature'");
        }
    }
    return $text;
}

/** Legacy **/


/**
 * temp maintenance to convert legacy enchantments to NBT
 * @global type $ENCH_ITEMS
 */
function umc_nbt_fix() {
    global $ENCH_ITEMS;
    $sql = 'SELECT * FROM minecraft_iconomy.stock WHERE meta LIKE "a:%" ORDER BY id DESC' ;
    $D = umc_mysql_fetch_all($sql);
    foreach ($D as $row) {
        $meta = trim($row['meta']);
        $meta_arr = unserialize($meta);
        if (!is_array($meta_arr)) {
            continue;
        }
        // {ench:[{lvl:5,id:16},{lvl:5,id:17},{lvl:5,id:18},{lvl:2,id:19},{lvl:2,id:20},{lvl:3,id:21}]}
        $nbt = '{ench:[';
        $nbt_arr = array();
        foreach ($meta_arr as $ench => $lvl) {
            $id = $ENCH_ITEMS[$ench]['id'];
            $nbt_arr[] = "{lvl:$lvl,id:$id}";
        }
        $nbt .= implode(",", $nbt_arr);
        $nbt .= "]}";
        $line_id = $row['id'];
        $update_sql = "UPDATE minecraft_iconomy.stock SET `meta` = '$nbt' WHERE `id` = $line_id;";
        umc_mysql_execute_query($update_sql);
        XMPP_ERROR_send_msg("$meta => $nbt");

    }
}
