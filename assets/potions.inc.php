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
    
    $prefixes = array('long_', 'strong_');
    $addons = array(
        'long_' => array('long_text' =>  ' (long)', 'short_text' => '+'),
        'strong_' =>  array('long_text' =>  ' (strong)', 'short_text' => ' II'),
    );
    
    $suffix = '';
    foreach ($prefixes as $prefix) {
        if (strpos($search_value, $prefix) === 0) {
            $search_value = substr($search_value, strlen($prefix));
            $suffix = $addons[$prefix][$return_field];
        }
    }
    
    $text = $UMC_POTIONS[$search_value][$return_field] . $suffix;
   
    return $text;
}

// note: Those are not the potion effects but just the potions that are available in cretive mode.
// Potion effects are less detailed, have different names and are split between duration and strength modifier
// Websend seems to have issues with custom potions however. It's better to stick with those default potions.
// see custom potion examples here: https://minecraftcommand.science/potion-generator
// effect list is here: http://minecraft.gamepedia.com/Status_effect

$UMC_POTIONS = array(
  'water' => array(
      'long_text' => 'Water', 
      'short_text' => 'Water', 
      'potion_icon' => '/c/c3/Grid_Awkward_Potion.png',),
  'thick' => array(
      'long_text' => 'Thick', 
      'short_text' => 'Thick', 
      'potion_icon' => '/e/e6/Grid_Thick_Potion.png'),
  'mundane' => array(
      'long_text' => 'Mundane', 
      'short_text' => 'Mund', 
      'potion_icon' => '/6/6c/Grid_Mundane_Potion.png',
      'splash_icon' => '/0/0b/Grid_Splash_Mundane_Potion.png'),
  'awkward' => array(
      'long_text' => 'Awkward', 
      'short_text' => 'Awk', 
      'potion_icon' => '/c/c3/Grid_Awkward_Potion.png'),
  'night_vision' => array(
      'long_text' => 'Night vision', 
      'short_text' => 'Night', 
      'potion_icon' => '/b/ba/Grid_Potion_of_Night_Vision.png',
      'splash_icon' => '/c/c3/Grid_Splash_Potion_of_Night_Vision.png'), 
  'invisibility' => array(
      'long_text' => 'Invisibility',
      'short_text' => 'Invis', 
      'potion_icon' => '/d/d8/Grid_Potion_of_Invisibility.png',
      'splash_icon' => '/8/89/Grid_Splash_Potion_of_Invisibility.png'),
  'luck' => array(
      'long_text' => 'Luck',
      'short_text' => 'Luck'),
  'leaping' => array(
      'long_text' => 'Leaping',
      'short_text' => 'Leap', 
      'potion_icon' => '/8/8c/Grid_Potion_of_Leaping.png'),
  'fire_resistance' => array(
      'long_text' => 'Fire Resistance', 
      'short_text' => 'ResFire', 
      'potion_icon' => '/4/43/Grid_Potion_of_Fire_Resistance.png',
      'splash_icon' => '/c/cb/Grid_Splash_Potion_of_Fire_Resistance.png'),
  'swiftness' => array(
      'long_text' => 'Swiftness', 
      'short_text' => 'Swift', 
      'potion_icon' => '/1/1c/Grid_Potion_of_Swiftness.png',
      'splash_icon' => '/7/7a/Grid_Splash_Potion_of_Swiftness.png'),
  'slowness' => array(
      'long_text' => 'Slowness', 
      'short_text' => 'Slow', 
      'potion_icon' => '/c/ca/Grid_Potion_of_Slowness.png',
      'splash_icon' => '/2/22/Grid_Splash_Potion_of_Slowness.png'),
  'water_breathing' => array(
      'long_text' => 'Water Breathing', 
      'short_text' => 'Dive', 
      'potion_icon' => '/c/c2/Grid_Potion_of_Water_Breathing.png'),
  'healing' => array(
      'long_text' => 'Healing', 
      'short_text' => 'Heal', 
      'potion_icon' => '/a/a3/Grid_Potion_of_Healing.png',
      'splash_icon' => '/3/33/Grid_Splash_Potion_of_Healing.png'),
  'harming' => array(
      'long_text' => 'Harming', 
      'short_text' => 'Harm',
      'potion_icon' => '/b/b2/Grid_Potion_of_Harming.png',
      'splash_icon' => '/5/52/Grid_Splash_Potion_of_Harming.png'),
  'poison' => array(
      'long_text' => 'Poison', 
      'short_text' => 'Poi', 
      'potion_icon' => '/a/a1/Grid_Potion_of_Poison.png',
      'splash_icon' => '/1/11/Grid_Splash_Potion_of_Poison.png'),
  'regeneration' => array(
      'long_text' => 'Regeneration', 
      'short_text' => 'Regen', 
      'potion_icon' => '/0/00/Grid_Potion_of_Regeneration.png',
      'splash_icon' => '/0/0f/Grid_Splash_Potion_of_Regeneration.png'),
  'strength' => array(
      'long_text' => 'Strength', 
      'short_text' => 'Str', 
      'potion_icon' => '/8/8c/Grid_Potion_of_Strength.png',
      'splash_icon' => '/3/38/Grid_Splash_Potion_of_Strength.png'),
  'weakness' => array(
      'long_text' => 'Weakness', 
      'short_text' => 'Weak', 
      'potion_icon' => '/2/2c/Grid_Potion_of_Weakness.png',
      'splash_icon' => '/9/94/Grid_Splash_Potion_of_Weakness.png'),    
);

// this was just for testing
function umc_potions_give_all() {
    global $UMC_POTION_EFFECTS;
    foreach ($UMC_POTION_EFFECTS as $D) {
        $code = $D['code'];
        umc_ws_give('uncovery', 'potion', 1, 0, "{Potion:\"minecraft:$code\"}");
    }
}