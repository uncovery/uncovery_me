<?php

/**
 * returns the text of an enchantment based on a value in the array
 * this is used if we do not have that ALL_CAPS value but the ID for example
 * returns the value of the field
 *  
 * @global array $ENCH_ITEMS
 * @param type $search_field
 * @param type $search_value
 * @param type $return_field
 * @return type
 */
function umc_enchant_text_find($search_field, $search_value, $return_field) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $ENCH_ITEMS;
    //TODO: This needs to be simplified. Either we find a better way to directly
    // get the text key, or we change the array once we do not need the text 
    // keys anymore when NBT is fully implemented.
    
    // we get the numeric key
    $ench_key = array_search($search_value, array_column($ENCH_ITEMS, $search_field));
    // get the text key from the numeric
    $keys = array_keys($ENCH_ITEMS);
    $text_key = $keys[$ench_key];

    $text = $ENCH_ITEMS[$text_key][$return_field];
    return $text;
}

$ENCH_ITEMS = array(
    'PROTECTION_ENVIRONMENTAL' => array( // 0
        'id' => 0,
        'key' => 'PROTECTION_ENVIRONMENTAL',
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
    'PROTECTION_FIRE' => array( // 1
        'id' => 1,
        'key' => 'PROTECTION_FIRE',
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
        'key' => 'PROTECTION_FALL',
        'short'=> 'Fall',
        'name'=>'FeatherFalling',
        'items'=> array(
            'diamond_boots', 'golden_boots', 'iron_boots', 'chainmail_boots', 'leather_boots',
        ),
        'max'=>4
    ),
    'PROTECTION_EXPLOSIONS' => array( // 3
        'id' => 3,
        'key' => 'PROTECTION_EXPLOSIONS',
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
        'key' => 'PROTECTION_PROJECTILE',
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
        'key' => 'OXYGEN',
        'short' => 'Res',
        'name' =>'Respiration',
        'items' => array(
            'diamond_helmet', 'golden_helmet', 'iron_helmet', 'chainmail_helmet', 'leather_helmet',
        ),
        'max' => 3
    ),
    'WATER_WORKER' => array( // 6
        'id' => 6,
        'key' => 'WATER_WORKER',
        'short' => 'Aqua',
        'name' =>'AquaAffinity',
        'items' => array(
            'diamond_helmet', 'golden_helmet', 'iron_helmet', 'chainmail_helmet', 'leather_helmet',
        ),
        'max' => 1
    ),
    'THORNS' => array( // 7
        'id' => 7,
        'key' => 'THORNS',
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
        'key' => 'DEPTH_STRIDER',
        'short' => 'Depth',
        'name' => 'DepthStrider',
        'items' => array(
            'diamond_boots', 'golden_boots', 'iron_boots', 'chainmail_boots', 'leather_boots',
        ),
        'max' => 3
    ),
    'FROST_WALKER' => array( // 9
        'id' => 9,
        'key' => 'FROST_WALKER',
        'short' => 'Depth',
        'name' => 'DepthStrider',
        'items' => array(
            'diamond_boots', 'golden_boots', 'iron_boots', 'chainmail_boots', 'leather_boots',
        ),
        'max' => 2
    ),
    'BINDING_CURSE' => array( // 10
        'id' => 10,
        'key' => 'BINDING_CURSE',
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
        'key' => 'DAMAGE_ALL',
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
        'key' => 'DAMAGE_UNDEAD',
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
        'key' => 'DAMAGE_ARTHROPODS',
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
        'key' => 'KNOCKBACK',
        'short' => 'Knock',
        'name' => 'Knockback',
        'items' => array(
            'diamond_sword', 'golden_sword', 'iron_sword', 'stone_sword', 'wooden_sword',
        ),
        'max'=>2
    ),
    'FIRE_ASPECT' => array( // 20
        'id' => 20,
        'key' => 'FIRE_ASPECT',
        'short' => 'Fire',
        'name' => 'FireAspect',
        'items' => array(
            'diamond_sword', 'golden_sword', 'iron_sword', 'stone_sword', 'wooden_sword',
        ),
        'max'=>2
    ),
    'LOOT_BONUS_MOBS' =>array( // 21
        'id' => 21,
        'key' => 'LOOT_BONUS_MOBS',
        'short' => 'Loot',
        'name' => 'Looting',
        'items' => array(
            'diamond_sword', 'golden_sword', 'iron_sword', 'stone_sword', 'wooden_sword',
        ),
        'max'=>3
    ),
    'DIG_SPEED' =>array( // 32
        'id' => 32,
        'key' => 'DIG_SPEED',
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
        'key' => 'SILK_TOUCH',
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
        'key' => 'DURABILITY',
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
        'key' => 'LOOT_BONUS_BLOCKS',
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
        'key' => 'ARROW_DAMAGE',
        'short' => 'Power',
        'name' => 'Power',
        'items' => array('bow'),
        'max'=>5
    ),
    'ARROW_KNOCKBACK' => array( //49
        'id' => 49,
        'key' => 'ARROW_KNOCKBACK',
        'short'=> 'Punch',
        'name'=>'Punch',
        'items' => array('bow'),
        'max' => 2
    ),
    'ARROW_FIRE' => array( // 50
        'id' => 50,
        'key' => 'ARROW_FIRE',
        'short' => 'Flame',
        'name' => 'Flame',
        'items' => array('bow'),
        'max' => 1
    ),
    'ARROW_INFINITE' => array( // 51
        'id' => 51,
        'key' => 'ARROW_INFINITE',
        'short' => 'Inf',
        'name' => 'Infinity',
        'items' => array('bow'),
        'max'=>1
    ),
    'LUCK' =>array( // 61
        'id' => 61,
        'key' => 'LUCK',
        'short' => 'Luck',
        'name' => 'Luck',
        'items' => array('fishing_rod'),
        'max'=>1
    ),
    'LURE' => array( // 62
        'id' => 62,
        'key' => 'LURE',
        'short' => 'Lure',
        'name' => 'Lure',
        'items' => array('fishing_rod'),
        'max'=>1
    ),   
    'MENDING' => array( // 70
        'id' => 70,
        'key' => 'MENDING',
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
    'UNKNOWN_ENCHANT_71' =>array( // 71 // this here is faulty but still exists sometimes
        'short' => 'Vanish',
        'key' => 'VANISHING_CURSE',
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
        'key' => 'VANISHING_CURSE',
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