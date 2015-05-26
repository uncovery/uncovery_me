<?php
/**
 * Convert an array to a printable text
 *
 * @param type $data
 * @param type $array_name
 * @param type $file
 * @return string
 */
function umc_array2file($data, $array_name, $file) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $array_name_upper = strtoupper($array_name);
    $out = '<?php' . "\n"
        . '$' . $array_name_upper. " = array(\n"
        . umc_array2file_line($data, 0)
        . ");";
    file_put_contents($file, $out);
    // echo $out;
}

function umc_array2file_line($array, $layer, $val_change_func = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $in_text = umc_array2file_indent($layer);
    $out = "";
    foreach ($array as $key => $value) {
        if ($val_change_func) {
            $value = $val_change_func($key, $value);
        }        
        $out .=  "$in_text'$key' => ";
        if (is_array($value)) {
            $layer++;
            $out .= "array(\n"
                . umc_array2file_line($value, $layer,  $val_change_func)
                . "$in_text),\n";
            $layer--;
        } else if(is_numeric($value)) {
            $out .= "$value,\n";
        } else {
            $out .= "'$value',\n";
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