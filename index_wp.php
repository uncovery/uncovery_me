<?php
global $UMC_USER, $UMC_ENV;

// do not process any functions if we have a 404 error;
if (function_exists('is_404') && is_404()) {
    return;
}

require_once(__DIR__ . '/core_include.php');

$s_get = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
$s_post  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);

if (isset($s_get['function']) && function_exists('umc_'.$s_get['function'])) {
    umc_function_call($s_get['function']);
} else if (isset($s_post['function']) && function_exists('umc_'.$s_post['function'])) {
    umc_function_call($s_post['function']);
}

if (!$UMC_ENV) {
    umc_set_environment();
}

/**
 * Verify that an externally called function is actually a vaild, no-risk function call.
 *
 * @global type $UMC_FUNCTIONS
 * @param type $function
 */
function umc_function_call($function) {
    global $UMC_FUNCTIONS;

    // we check if the function is in the list of known functions
    if (!isset($UMC_FUNCTIONS[$function])) {
        // if not, send a warning so that it can be included for the future
        XMPP_ERROR_send_msg("Unverified function call '$function'");
        $function_name = 'umc_' . $function;
    } else {
        // if yes, all fine, get proper function name
        $function_name = $UMC_FUNCTIONS[$function];
    }
    // execute code
    echo $function_name();
}