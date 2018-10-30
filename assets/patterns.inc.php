<?php

/**
 * return a better text for patterns
 * 
 * @param type $nbt
 */
function umc_patterns_get_text($nbt, $format) {
    global $UMC_PATTERNS;
    // sample: [{Pattern:"ld",Color:1},{Pattern:"mc",Color:2}]
    $texts = array();
    $formats = array(
        'long', 'in_game',
    );
    
    foreach ($nbt as $pat) {
        $pat_code = $pat['Pattern'];
        $col_code = $pat['Color'];
        $pattern = $UMC_PATTERNS[$pat_code]['text'];
        $color = ucwords(unc_color_decimal($col_code));
        switch ($format) {
            case 'long':
                $texts[] = "$color $pattern";
                break;
            case 'in_game':
                
                break;
        }
        
    }
    $out = implode(", ", $texts);
    return $out;
}

$UMC_PATTERNS= array(
    'bs' => array('text' => 'Base fess', 'name' => 'Bottom Stripe'),
    'ts' => array('text' => 'Chief fess', 'name' => 'Top Stripe'),
    'ls' => array('text' => 'Pale dexter', 'name' => 'Left Stripe'),
    'rs' => array('text' => 'Pale sinister', 'name' => 'Right Stripe'),
    'cs' => array('text' => 'Pale', 'name' => 'Center Stripe (Vertical)'),
    'ms' => array('text' => 'Fess', 'name' => 'Middle Stripe (Horizontal)'),
    'drs' => array('text' => 'Bend', 'name' => 'Down Right Stripe'),
    'dls' => array('text' => 'Bend sinister', 'name' => 'Down Left Stripe'),
    'ss' => array('text' => 'Paly', 'name' => 'Small (Vertical) Stripes'),
    'cr' => array('text' => 'Saltire', 'name' => 'Diagonal Cross'),
    'sc' => array('text' => 'Cross', 'name' => 'Square Cross'),
    'ld' => array('text' => 'Per bend sinister', 'name' => 'Left of Diagonal'),
    'rud' => array('text' => 'Per bend', 'name' => 'Right of upside-down Diagonal'),
    'lud' => array('text' => 'Per bend inverted', 'name' => 'Left of upside-down Diagonal'),
    'rd' => array('text' => 'Per bend sinister inverted', 'name' => 'Right of Diagonal'),
    'vh' => array('text' => 'Per pale', 'name' => 'Vertical Half (left)'),
    'vhr' => array('text' => 'Per pale inverted', 'name' => 'Vertical Half (right)'),
    'hh' => array('text' => 'Per fess', 'name' => 'Horizontal Half (top)'),
    'hhb' => array('text' => 'Per fess inverted', 'name' => 'Horizontal Half (bottom)'),
    'bl' => array('text' => 'Base dexter canton', 'name' => 'Bottom Left Corner'),
    'br' => array('text' => 'Base sinister canton', 'name' => 'Bottom Right Corner'),
    'tl' => array('text' => 'Chief dexter canton', 'name' => 'Top Left Corner'),
    'tr' => array('text' => 'Chief sinister canton', 'name' => 'Top Right Corner'),
    'bt' => array('text' => 'Chevron', 'name' => 'Bottom Triangle'),
    'tt' => array('text' => 'Inverted chevron', 'name' => 'Top Triangle'),
    'bts' => array('text' => 'Base indented', 'name' => 'Bottom Triangle Sawtooth'),
    'tts' => array('text' => 'Chief indented', 'name' => 'Top Triangle Sawtooth'),
    'mc' => array('text' => 'Roundel', 'name' => 'Middle Circle'),
    'mr' => array('text' => 'Lozenge', 'name' => 'Middle Rhombus'),
    'bo' => array('text' => 'Bordure', 'name' => 'Border'),
    'cbo' => array('text' => 'Bordure indented', 'name' => 'Curly Border'),
    'bri' => array('text' => 'Field masoned', 'name' => 'Brick'),
    'gra' => array('text' => 'Gradient', 'name' => 'Gradient'),
    'gru' => array('text' => 'Base gradient', 'name' => 'Gradient upside-down'),
    'cre' => array('text' => 'Creeper charge', 'name' => 'Creeper'),
    'sku' => array('text' => 'Skull charge', 'name' => 'Skull'),
    'flo' => array('text' => 'Flower charge', 'name' => 'Flower'),
    'moj' => array('text' => 'Mojang charge', 'name' => 'Mojang'),
);