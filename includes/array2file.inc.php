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

/**
 * Convert an array to a printable text
 *
 * @param type $data
 * @param type $array_name
 * @param type $file
 * @param string $comments this should not have a newline at the end.
 * @return string
 */
function umc_array2file($data, $array_name, $file, $comments = false) {
    $array_name_upper = strtoupper($array_name);
    
    $fixed_comments = '';
    if ($comments) {
        $fixed_comments = "/**\n" . preg_replace('/^/m', ' * ', $comments) . "\n */\n\n";
    }
    $out = '<?php' . "\n"
        . $fixed_comments
        . '$' . $array_name_upper. " = array(\n"
        . umc_array2file_line($data, 0)
        . ");";
    file_put_contents($file, $out);
    // echo $out;
}

function umc_array2file_line($array, $layer, $val_change_func = false) {
    $in_text = umc_array2file_indent($layer);
    $out = "";
    foreach ($array as $key => $value) {
        if ($val_change_func) {
            $value = $val_change_func($key, $value);
        }
        $slash_key = addslashes($key);
        $out .=  "$in_text'$slash_key' => ";
        if (is_array($value)) {
            $layer++;
            $out .= "array(\n"
                . umc_array2file_line($value, $layer,  $val_change_func)
                . "$in_text),\n";
            $layer--;
        } else if(is_numeric($value)) {
            $out .= "$value,\n";
        } else {
            $out .= "'" . addslashes($value) . "',\n";
        }
    }
    return $out;
}



function umc_array2file_indent($layer) {
    $text = '    ';
    $out = '';
    for ($i=0; $i<=$layer; $i++) {
        $out .= $text;
    }
    return $out;
}