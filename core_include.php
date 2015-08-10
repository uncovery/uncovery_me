<?php

/* UMC_FUNCTIONS: This is a list of functions that are called directly from the website, outside of Wordpress
 * The list needs to be maintained so that the function can actually be called. See index_wp for the mechanism.
 */

global $UMC_USERS, $WS_INIT, $UMC_USER, $UMC_SETTING, $UMC_ITEMS, $UMC_DATA, $UMC_DATA_SPIGOT2ITEM, $UMC_DATA_ID2NAME;
global $UMC_ENV, $ITEM_SEARCH, $ENCH_ITEMS, $UMC_DOMAIN, $UMC_PATH_MC, $UMC_FUNCTIONS;

$UMC_PATH_MC = "/home/minecraft";

// include error handling
global $XMPP_ERROR;
$XMPP_ERROR['config']['project_name'] = 'Uncovery.me';
$XMPP_ERROR['config']['enabled'] = true;
require_once('/home/includes/xmpp_error/xmpp_error.php');

// include database abstraction
global $UNC_DB;
$UNC_DB = array('database' => 'minecraft', 'username' => 'minecraft', 'server' => 'localhost', 'password' => '9sd6ncC9vEcTD55Z');
// legacy since we did not merge completely
mysql_connect($UNC_DB['server'], $UNC_DB['username'], $UNC_DB['password']);
require_once('/home/includes/uncovery_mysql/uncovery_mysql.inc.php');

// include serial_curl
require_once('/home/includes/unc_serial_curl/unc_serial_curl.php');

// include everything else
require_once($UMC_PATH_MC . '/server/bin/classes/class.users.php');
require_once($UMC_PATH_MC . '/server/bin/includes/config.inc.php');
require_once($UMC_PATH_MC . '/server/bin/includes/uuid.inc.php');
require_once($UMC_PATH_MC . '/server/bin/users.php');
require_once($UMC_PATH_MC . '/server/bin/map.php');
require_once($UMC_PATH_MC . '/server/bin/web.php');
require_once($UMC_PATH_MC . '/server/bin/contests.php');
require_once($UMC_PATH_MC . '/server/bin/shop_common.php');
require_once($UMC_PATH_MC . '/server/bin/donation.php');
require_once($UMC_PATH_MC . '/server/bin/lot_manager.php');
require_once($UMC_PATH_MC . '/server/bin/includes/websend.inc.php');
require_once($UMC_PATH_MC . '/server/bin/includes/wordpress.inc.php');
require_once($UMC_PATH_MC . '/server/bin/plugin.php');
require_once($UMC_PATH_MC . '/server/bin/inventory.php');
require_once($UMC_PATH_MC . '/server/bin/settler_test.php');
require_once($UMC_PATH_MC . '/server/bin/includes/colors.inc.php');
require_once($UMC_PATH_MC . '/server/bin/includes/log.inc.php');
require_once($UMC_PATH_MC . '/server/bin/includes/timer.inc.php');
require_once($UMC_PATH_MC . '/server/bin/includes/item_data.inc.php');
require_once($UMC_PATH_MC . '/server/bin/includes/item_search.inc.php');
require_once($UMC_PATH_MC . '/server/bin/shop_manager.php');
require_once($UMC_PATH_MC . '/server/bin/includes/array2file.inc.php');
require_once($UMC_PATH_MC . '/server/bin/includes/usericons.inc.php');
require_once($UMC_PATH_MC . '/server/bin/includes/github.inc.php');
require_once($UMC_PATH_MC . '/server/bin/includes/faq.inc.php');

// include all websend plugins
umc_plg_include();

function umc_set_environment() {
    global $UMC_ENV;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    if (isset($UMC_ENV)) {
        return;
    }
    // check if we are on websend
    if (function_exists('umc_ws_auth') && umc_ws_auth()) {
        // get all variables from Websend to $UMC_USER
        $UMC_ENV = 'websend';
        umc_ws_get_vars();
    } else if (function_exists('get_currentuserinfo')) {
        $UMC_ENV = 'wordpress';
        umc_wp_get_vars();
    } else {
        $UMC_ENV = 'unknown';
    }

}

function umc_sanitize_input(&$value, $type) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $MAX_UNCS = 10000;
    $MIN_UNCS = 0.00001;

    if ($type == "price") {
        # Check that this is a number
        # Check that it is greater than zero
        # Check bounds
        if (!is_numeric($value)) {
            umc_error("{red}Invalid amount of uncs ({yellow}$value{red}), must be a number.");
        } elseif ($value < $MIN_UNCS) {
            umc_error("{red}Invalid amount of uncs ({yellow}$value{red}), must be at least {yellow}$MIN_UNCS{red}.");
        } elseif($value > $MAX_UNCS) {
            umc_error("{red}Invalid amount of uncs ({yellow}$value{red}), cannot be more than {yellow}$MAX_UNCS{red}.");
        } else {
            return $value;
        }
    }

    if ($type == "amount") {
        if ($value == NULL) { // buying all available
            return NULL;
        }
        if (!is_numeric($value)) {
            umc_error("{red}Invalid amount ({yellow}$value{red}), must be an integer.");
        } elseif (intval($value) < 1) {
            umc_error("{red}Invalid amount ({yellow}$value{red}), must be at least 1.");
        } else {
            return intval(abs($value));
        }
    }

    if ($type == "player") {
        $player = umc_check_user($value);
        if (!$player) {
            umc_error("{red}Invalid player name ({yellow}$value{red}), no such player.");
        } else {
            return $player;
        }
    }

    if ($type == "item") {
        //'get a list of all possible item names
        $all_names = umc_item_data_get_namelist();
        if (isset($all_names[$value])) {
            return $all_names[$value];
        } else {
            return false;
        }
    }

    if ($type == "table") {
        if (isset($value[2]) && ($value[2] == 'request' || $value[2] == 'req' || $value[2] == 'r')) {
            return 'request';
        } elseif (isset($value[2]) && ($value[2] == 'offer' || $value[2] == 'off' || $value[2] == 'o')) {
            return 'stock';
        } else {
            array_splice($value,2,0,'offer');
            umc_echo("{yellow}[!]{gray} Didn't specify {yellow}request{gray} or {yellow}offer{gray}, assuming {yellow}offer", true);
            return 'stock';
        }
    }
    if ($type == "lot") {
        $check = !preg_match('/[^A-Za-z0-9_.#\\-$]/', $value);
        if (!$check) {
            umc_error('You need to enter a valid lot name such as "emp_a1"');
        } else {
            return $value;
        }
    }
    if ($type == "meta") {
        $meta_name = umc_parse_meta_input($value);
        if (is_null($meta_name)) {
            umc_error("Unknown Metavalue name: {white}$value");
        } else {
            return $meta_nam;
        }
    }

}



function in_arrayi($needle, $haystack) {
    $found = false;
    foreach($haystack as $value) {
        if(strtolower($value) == strtolower($needle)) {
            $found = true;
        }
    }
    return $found;
}

// makes sure no redundant date is included in import
function umc_clean_line($line) {
    $line = trim($line);
    if ((substr($line,0,1) =='#') && strlen($line < 2)) {
	    return false;
    } else {
	    return $line;
    }
}

// A1 south-west
// min: {z: 1152.0, y: 0.0, x: -1280.0}
// max: {z: 1279.0, y: 128.0, x: -1153.0}

function conv_z($z, $map) {
    // 'map_img_empire' => array('top_offset' => 16, 'left_offset' => 33, 'max_coord' => 1280, 'row_size' => 16),
    $offset = 0;
    if (isset($map['top_offset'])) {
        $offset = $map['top_offset'];
    }
    $y = $z + $offset + $map['max_coord'];
    return $y;
}

function conv_x($x, $map) {
    $offset = 0;
    if (isset($map['left_offset'])) {
        $offset = $map['left_offset'];
    }
    $x = $x + $map['max_coord'] + $offset;
    return $x;
}


/*
 * iterates a path for all files
 */
function umc_glob_recursive($pattern, $flags = 0) {
    $files = glob($pattern, $flags);
    foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
        $files = array_merge($files, umc_glob_recursive($dir.'/'.basename($pattern), $flags));
    }
    return $files;
}

function umc_frontpage() {
    XMPP_ERROR_trigger("This should not happen umc_frontpage");
}

function umc_pretty_name($name) {
    return ucwords(str_replace("_"," ",$name));
}

function umc_unpretty_name($name) {
    return strtolower(str_replace(" ","_",$name));
}

function umc_filemtime_remote($uri) {
    $uri = parse_url($uri);
    $handle = @fsockopen($uri['host'],80);
    if (!$handle) {
        return false;
    }

    fputs($handle,"GET $uri[path] HTTP/1.1\r\nHost: $uri[host]\r\n\r\n");
    $result = 0;
    while (!feof($handle)) {
        $line = fgets($handle,1024);
        if (!trim($line)) {
            break;
        }

        $col = strpos($line,':');
        if ($col !== false) {
            $header = trim(substr($line,0,$col));
            $value = trim(substr($line,$col+1));
            if (strtolower($header) == 'last-modified') {
                $result = strtotime($value);
                break;
            }
        }
    }
    fclose($handle);
    return $result;
}


function umc_print_truefalse($query) {
    if ($query) {
        return 'true';
    } else {
        return 'false';
    }
}