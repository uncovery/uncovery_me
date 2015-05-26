<?php

global $UMC_COLORS;

$UMC_COLORS = array(
    '0' => array('html' => 'color:#000;',    'names' => array('black')),
    '1' => array('html' => 'color:#0000aa;', 'names' => array('darkblue')),
    '2' => array('html' => 'color:#00aa00;', 'names' => array('darkgreen')),
    '3' => array('html' => 'color:#00aaaa;', 'names' => array('darkcyan')),
    '4' => array('html' => 'color:#aa0000;', 'names' => array('darkred')),
    '5' => array('html' => 'color:#aa00aa;', 'names' => array('darkpurple', 'darkmagenta')),
    '6' => array('html' => 'color:#ffaa00;', 'names' => array('darkyellow', 'orange', 'gold')),
    '7' => array('html' => 'color:#aaaaaa;', 'names' => array('gray', 'grey')),
    '8' => array('html' => 'color:#555555;', 'names' => array('darkgray', 'darkgrey')),
    '9' => array('html' => 'color:#5555ff;', 'names' => array('blue')),
    'a' => array('html' => 'color:#55ff55;', 'names' => array('green')),
    'b' => array('html' => 'color:#55ffff;', 'names' => array('cyan')),
    'c' => array('html' => 'color:#ff5555;', 'names' => array('red')),
    'd' => array('html' => 'color:#ff55ff;', 'names' => array('purple', 'pink', 'magenta')),
    'e' => array('html' => 'color:#ffff55;', 'names' => array('yellow')),
    'f' => array('html' => 'color:#ffffff;', 'names' => array('white')),
    'k' => array('html' => 'color:#000;',    'names' => array('random')),
    'l' => array('html' => 'font-weight:bold;', 'names' => array('bold')),
    'm' => array('html' => 'text-decoration:line-through;', 'names' => array('strike')),
    'n' => array('html' => 'text-decoration:underline;', 'names' => array('uline')),
    'o' => array('html' => 'font-style:italic;', 'names' => array('italic')),
    'r' => array('html' => 'color:#000;',    'names' => array('reset')),
);

function umc_ws_color_remove($input) {
    global $UMC_COLORS;
    // get array for replacement

    $color_names = array();
    foreach ($UMC_COLORS as $types) {
        foreach ($types['names'] as $name) {
            $color_names[] = '{'. $name . '}';
        }
    }
    $out = str_replace($color_names, "", $input);
    return $out;
}

function umc_mc_color_callback($matches) {
    global $UMC_COLORS;
    return "<span style='{$UMC_COLORS[$matches[1]]['html']}'>$matches[2]</span>";
}

function umc_mc_color_to_html($input) {
    return preg_replace_callback("/&([0-f])([^&]*)/","umc_mc_color_callback",$input);
}

/*
 * this creates a regex-pattern that includes all colors with {} around
 * this is used to find {color}-type codes in text and then to replace
 * them with the appropriate code instead
 */
function color_regex() {
    return "/\{(" . implode("|", color_map()) . ")\}/";
}

function color_map($color = null) {
    global $UMC_COLORS;
    $keys = array();

    foreach ($UMC_COLORS as $code => $types) {
        $keys = array_merge($keys, $types['names']);
        if (isset($color) && in_array($color, $types['names'])) {
            return "§". $code;
        }
    }
    if (isset($color)) { // there is an error, we should not be here, color not found
        return;
    }
    return $keys;
}
?>
