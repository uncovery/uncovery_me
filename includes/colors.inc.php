<?php
/*
 * This file is part of Uncovery Minecraft.
 * Copyright (C) 2015 uncovery.me
 *
 * Uncovery Minecraft is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of  MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/*
 * This file manages the conversion of in-game color codes to web-readable color codes
 * or removes color information from text to be B&W.
 */
global $UMC_COLORS, $UMC_COLORS_DEC;

$UMC_COLORS = array(
    '0' => array('html' => 'color:#000;',       'names' => array('black')),
    '1' => array('html' => 'color:#0000aa;',    'names' => array('darkblue')),
    '2' => array('html' => 'color:#00aa00;',    'names' => array('darkgreen')),
    '3' => array('html' => 'color:#00aaaa;',    'names' => array('darkcyan')),
    '4' => array('html' => 'color:#aa0000;',    'names' => array('darkred')),
    '5' => array('html' => 'color:#aa00aa;',    'names' => array('darkpurple', 'darkmagenta')),
    '6' => array('html' => 'color:#ffaa00;',    'names' => array('darkyellow', 'orange', 'gold')),
    '7' => array('html' => 'color:#aaaaaa;',    'names' => array('gray', 'grey')),
    '8' => array('html' => 'color:#555555;',    'names' => array('darkgray', 'darkgrey')),
    '9' => array('html' => 'color:#5555ff;',    'names' => array('blue')),
    'a' => array('html' => 'color:#55ff55;',    'names' => array('green')),
    'b' => array('html' => 'color:#55ffff;',    'names' => array('cyan')),
    'c' => array('html' => 'color:#ff5555;',    'names' => array('red')),
    'd' => array('html' => 'color:#ff55ff;',    'names' => array('purple', 'pink', 'magenta')),
    'e' => array('html' => 'color:#ffff55;',    'names' => array('yellow')),
    'f' => array('html' => 'color:#ffffff;',    'names' => array('white')),
    'k' => array('html' => 'color:#000;',       'names' => array('random')),
    'l' => array('html' => 'font-weight:bold;', 'names' => array('bold')),
    'm' => array('html' => 'text-decoration:line-through;', 'names' => array('strike')),
    'n' => array('html' => 'text-decoration:underline;', 'names' => array('uline')),
    'o' => array('html' => 'font-style:italic;', 'names' => array('italic')),
    'r' => array('html' => 'color:#000;',    'names' => array('reset')),
);

/**
 * NBT Codes use decimal colors, not HEX. So to get a color name we need to
 * convert the dec into hex and then get it out of the above array.
 *
 * @global array $UMC_COLORS
 * @param type $decimal
 * @return array
 */
function unc_color_decimal($decimal) {
    global $UMC_COLORS;
    $hex = dechex($decimal);
    $col_data = $UMC_COLORS[$hex]['names'][0];
    return $col_data;
}

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
