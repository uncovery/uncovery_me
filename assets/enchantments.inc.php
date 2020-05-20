<?php

/**
 * returns the text of an enchantment based on a value in the array
 * this is used if we do not have that ALL_CAPS value but the ID for example
 * returns the value of the field
 *
 * @global array $UMC_DATA_ENCHANTMENTS
 * @param type $search_field
 * @param type $search_value
 * @param type $return_field
 * @return type
 */
function umc_enchant_text_find($search_field, $search_value, $return_field) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_DATA_ENCHANTMENTS;
    //TODO: This needs to be simplified. Either we find a better way to directly
    // get the text key, or we change the array once we do not need the text
    // keys anymore when NBT is fully implemented.

    // we get the numeric key
    $ench_key = array_search(strtolower($search_value), array_column($UMC_DATA_ENCHANTMENTS, $search_field));
    // get the text key from the numeric
    $keys = array_keys($UMC_DATA_ENCHANTMENTS);
    $text_key = $keys[$ench_key];

    $text = $UMC_DATA_ENCHANTMENTS[$text_key][$return_field];
    return $text;
}
