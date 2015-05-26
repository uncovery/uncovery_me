<?php
global $UMC_USER, $UMC_ENV, $UMC_SETTING;
require_once(__DIR__ . '/core_include.php');
require_once($UMC_SETTING['path']['wordpress'] . '/wp-load.php');

if (!$UMC_ENV) {
    umc_set_environment();
}
umc_websend_main();