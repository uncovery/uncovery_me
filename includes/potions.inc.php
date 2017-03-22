<?php
global $UMC_POTION_EFFECTS;

/**
 * returns the text of an potion based on a value in the array
 * this is used if we do not have that ALL_CAPS value but the ID for example
 * returns the value of the field
 *
 * @global array $ENCH_ITEMS
 * @param type $search_field
 * @param type $search_value
 * @param type $return_field
 * @return type
 */
function umc_potion_text_find($search_field, $search_value, $return_field) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_POTION_EFFECTS;
    //TODO: This needs to be simplified. Either we find a better way to directly
    // get the text key, or we change the array once we do not need the text
    // keys anymore when NBT is fully implemented.

    // strip minecraft: if needed
    if (strpos($search_value, 'minecraft:') === 0) {
        $search_value = substr($search_value, 10);
    }
    
    // we get the numeric key
    $ench_key = array_search($search_value, array_column($UMC_POTION_EFFECTS, $search_field));
    // get the text key from the numeric
    $keys = array_keys($UMC_POTION_EFFECTS);
    $text_key = $keys[$ench_key];

    $text = $UMC_POTION_EFFECTS[$text_key][$return_field];
    return $text;
}

$UMC_POTION_EFFECTS = array(
    1 => array('text'=>'Speed', 'code' => 'speed'), //x
    2 => array('text'=>'Slowness', 'code' => 'slowness'),
    3 => array('text'=>'Haste', 'code' => 'haste'), //x
    4 => array('text'=>'Mining Fatigue', 'code' => 'mining_fatigue'), //x
    5 => array('text'=>'Strength', 'code' => 'strength'),
    6 => array('text'=>'Instant Health', 'code' => 'instant_health', 'ws_code' => 'healing'), //x
    7 => array('text'=>'Instant Damage', 'code' => 'instant_damage', 'ws_code' => 'harming'), //x
    8 => array('text'=>'Jump Boost', 'code' => 'jump_boost', 'ws_code' => 'leaping'), //x
    9 => array('text'=>'Nausea', 'code' => 'nausea'), //x
    10 => array('text'=>'Regeneration', 'code' => 'regeneration'), 
    11 => array('text'=>'Resistance', 'code' => 'resistance'), //x
    12 => array('text'=>'Fire Resistance', 'code' => 'fire_resistance'),
    13 => array('text'=>'Water Breathing', 'code' => 'water_breathing'),
    14 => array('text'=>'Invisibility', 'code' => 'invisibility'),
    15 => array('text'=>'Blindness', 'code' => 'blindness'), //x
    16 => array('text'=>'Night Vision', 'code' => 'night_vision'),
    17 => array('text'=>'Hunger', 'code' => 'hunger'), //x
    18 => array('text'=>'Weakness', 'code' => 'weakness'),
    19 => array('text'=>'Poison', 'code' => 'poison'),
    20 => array('text'=>'Wither', 'code' => 'wither'), //x  , 'ws_code' => 'strong_poison'
    21 => array('text'=>'Health Boost', 'code' => 'health_boost'), //x
    22 => array('text'=>'Absorption', 'code' => 'absorption'),//x 
    23 => array('text'=>'Saturation', 'code' => 'saturation'),//x
    24 => array('text'=>'Glowing', 'code' => 'glowing'),//x 
    25 => array('text'=>'Levitation', 'code' => 'levitation'), //x
    26 => array('text'=>'Luck', 'code' => 'luck'),
    27 => array('text'=>'Bad Luck', 'code' => 'unluck'), //x
); 

$UMC_POTIONS = array(
  'water' => array('long_text' => 'Water', 'short_text' => 'Water'),
  'thick' => array('long_text' => 'Thick', 'short_text' => 'Thick'),
  'mundane' => array('long_text' => 'Mundane', 'short_text' => 'Mund'),
  'awkward' => array('long_text' => 'Awkward', 'short_text' => 'Awk'),
  'long_night_vision' => array('long_text' => 'Night vision (long)', 'short_text' => 'Night+'),
  'night_vision' => array('long_text' => 'Night vision', 'short_text' => 'Night'),  
  'invisibility' => array('long_text' => 'Invisibility', 'short_text' => 'Invis'),
  'long_invisibility' => array('long_text' => 'Invisibility (long)', 'short_text' => 'Invis+'),
  'luck' => array('long_text' => 'Luck', 'short_text' => 'Luck'),
  'leaping' => array('long_text' => 'Leaping', 'short_text' => 'Leap'),
  'long_leaping' => array('long_text' => 'Leaping (long)', 'short_text' => 'Leap+'),
  'strong_leaping' => array('long_text' => 'Leaping (strong)', 'short_text' => 'Leap++'),
  'fire_resistance' => array('long_text' => 'Fire Resistance', 'short_text' => 'ResFire'),
  'long_fire_resistance' => array('long_text' => 'Fire Resistance (long)', 'short_text' => 'ResFire+'),
  'swiftness' => array('long_text' => 'Swiftness', 'short_text' => 'Swift'),
  'long_swiftness' => array('long_text' => 'Swiftness (long)', 'short_text' => 'Swift+'),
  'strong_swiftness' => array('long_text' => 'Swiftness (strong)', 'short_text' => 'Swift++'),
  'slowness' => array('long_text' => 'Slowness', 'short_text' => 'Slow'),
  'long_slowness' => array('long_text' => 'Slowness (long)', 'short_text' => 'Slow+'),
  'water_breathing' => array('long_text' => 'Water Breathing', 'short_text' => 'Dive'),
  'long_water_breathing' => array('long_text' => 'Water Breathing (long)', 'short_text' => 'Dive+'),
  'healing' => array('long_text' => 'Healing', 'short_text' => 'Heal'),
  'strong_healing' => array('long_text' => 'Healing (strong)', 'short_text' => 'Heal+'),
  'harming' => array('long_text' => 'Harming', 'short_text' => 'Harm'),
  'strong_harming' => array('long_text' => 'Harming (strong)', 'short_text' => 'Harm+'),
  'long_poison' => array('long_text' => 'Poision (long)', 'short_text' => 'Poi+'),
  'poison' => array('long_text' => 'Poison', 'short_text' => 'Poi'),
  'strong_poison' => array('long_text' => 'Poision', 'short_text' => 'Poi'),
  'regeneration' => array('long_text' => 'Regeneration', 'short_text' => 'Regen'),
  'long_regeneration' => array('long_text' => 'Regeneration (long)', 'short_text' => 'Regen+'),
  'strong_regeneration' => array('long_text' => 'Regeneration (strong)', 'short_text' => 'Regen++'),
  'strength' => array('long_text' => 'Strength', 'short_text' => 'Str'),
  'long_strength' => array('long_text' => 'Strength (long)', 'short_text' => 'Str+'),
  'strong_strength' => array('long_text' => 'Strength (strong)', 'short_text' => 'Str++'),
  'weakness' => array('long_text' => 'Weakness', 'short_text' => 'Weak'),
  'long_weakness' => array('long_text' => 'Weakness (long)', 'short_text' => 'Weak'),    
);

function umc_potions_give_all() {
    global $UMC_POTION_EFFECTS;
    foreach ($UMC_POTION_EFFECTS as $D) {
        $code = $D['code'];
        umc_ws_give('uncovery', 'potion', 1, 0, "{Potion:\"minecraft:$code\"}");
    }
    
}