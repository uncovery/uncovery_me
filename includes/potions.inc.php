<?php
global $UMC_POTIONS;

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
function umc_potion_text_find($search_value, $return_field) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_POTIONS;
    //TODO: This needs to be simplified. Either we find a better way to directly
    // get the text key, or we change the array once we do not need the text
    // keys anymore when NBT is fully implemented.

    // strip minecraft: if needed
    if (strpos($search_value, 'minecraft:') === 0) {
        $search_value = substr($search_value, 10);
    }
    
    $text = $UMC_POTIONS[$search_value][$return_field];
   
    return $text;
}

// note: Those are not the potion effects but just the potions that are available in cretive mode.
// Potion effects are less detailed, have different names and are split between duration and strength modifier
// Websend seems to have issues with custom potions however. It's better to stick with those default potions.
// see custom potion examples here: https://minecraftcommand.science/potion-generator
// effect list is here: http://minecraft.gamepedia.com/Status_effect

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
  'poison' => array('long_text' => 'Poison', 'short_text' => 'Poi'),
    'long_poison' => array('long_text' => 'Poision (long)', 'short_text' => 'Poi+'),
    'strong_poison' => array('long_text' => 'Poision (strong)', 'short_text' => 'Poi++'),
  'regeneration' => array('long_text' => 'Regeneration', 'short_text' => 'Regen'),
    'long_regeneration' => array('long_text' => 'Regeneration (long)', 'short_text' => 'Regen+'),
    'strong_regeneration' => array('long_text' => 'Regeneration (strong)', 'short_text' => 'Regen++'),
  'strength' => array('long_text' => 'Strength', 'short_text' => 'Str'),
    'long_strength' => array('long_text' => 'Strength (long)', 'short_text' => 'Str+'),
    'strong_strength' => array('long_text' => 'Strength (strong)', 'short_text' => 'Str++'),
  'weakness' => array('long_text' => 'Weakness', 'short_text' => 'Weak'),
    'long_weakness' => array('long_text' => 'Weakness (long)', 'short_text' => 'Weak'),    
);

// this was just for testing
function umc_potions_give_all() {
    global $UMC_POTION_EFFECTS;
    foreach ($UMC_POTION_EFFECTS as $D) {
        $code = $D['code'];
        umc_ws_give('uncovery', 'potion', 1, 0, "{Potion:\"minecraft:$code\"}");
    }
}