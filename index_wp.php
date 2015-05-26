<?php
global $UMC_USER, $UMC_ENV;
require_once(__DIR__ . '/core_include.php');

$s_get = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
$s_post  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);

if (isset($s_get['function']) && function_exists('umc_'.$s_get['function'])) {
    $function = 'umc_' . $s_get['function'];
    echo $function();
} else if (isset($s_post['function']) && function_exists('umc_'.$s_post['function'])) {
    $function = 'umc_' . $s_post['function'];
    echo $function();
}

if (!$UMC_ENV) {
    umc_set_environment();
}