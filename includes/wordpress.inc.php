<?php

/**
 * Get all variables of the current user from Wordpress and add it to $UMC_USER
 */
function umc_wp_get_vars() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER, $UMC_ENV, $user_email, $user_login;

    if ($UMC_ENV !== 'wordpress') {
        XMPP_ERROR_trigger("Tried to get wordpress vars, but environment did not match: " . var_export($UMC_ENV, true));
        die('umc_wp_get_vars');
    }
    get_currentuserinfo();
    if (!isset($user_login) || ($user_login == '') || ($user_email == '')) {
        $UMC_USER = false;
    } else {
        if (!function_exists('umc_get_uuid_level')) {
            XMPP_ERROR_send_msg("Could not get uuid_level, Env = $UMC_ENV");
            require_once('/home/minecraft/server/bin/core_include.php');
        }
        $uuid = umc_wp_get_uuid_for_currentuser();

        if (!$uuid) { // we have a guest who is trying to register
            $UMC_USER['username'] = $user_login;
            $UMC_USER['email'] = $user_email;
            $uuid = umc_user2uuid($user_login);
            $UMC_USER['uuid'] = $uuid;
            $UMC_USER['userlevel'] = 'Guest';
        } else {
            umc_uuid_check_usernamechange($uuid);
            $UMC_USER['email'] = $user_email;
            $UMC_USER['username'] = umc_uuid_getone($uuid, 'username');
            $UMC_USER['uuid'] = $uuid;
            $UMC_USER['userlevel'] = umc_get_uuid_level($uuid);
            if (strstr($UMC_USER['userlevel'], 'DonatorPlus')) {
                $UMC_USER['donator'] = 'DonatorPlus';
            } else if (strstr($UMC_USER['userlevel'], 'Donator')) {
                $UMC_USER['donator'] = 'Donator';
            } else {
                $UMC_USER['donator'] = false;
            }
        }
        // if we did not get any UUID
        if (!$uuid) {
            $UMC_USER['username'] = $user_login;
            $UMC_USER['uuid'] = false;
            $UMC_USER['userlevel'] = 'Guest';
        }
    }
}




/**
 * function being called if we cannot get the UUID from wordpress meta data
 * @param type $user_login
 */
function umc_wp_fix_uuid_meta($user_login){
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    XMPP_ERROR_trigger("Could not get meta uui for $user_login with umc_user2uuid()");
}

/**
 * Get a wp user ID from the UUID
 * This NEEDS to be the user_login, not display_name,
 *
 * @param string $uuid
 * @return string
 */
function umc_wp_get_login_from_uuid($uuid) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER, $UMC_ENV;
    $current_uuid = $UMC_USER['uuid'];
    if (($UMC_ENV == 'wordpress') && ($uuid == $current_uuid)) {
        $current_user = wp_get_current_user();
        $out = $current_user->user_login;
    } else {
        $uuid_sql = umc_mysql_real_escape_string($uuid);
        $sql = "SELECT user_login FROM minecraft.wp_users
            LEFT JOIN minecraft.wp_usermeta ON ID=user_id
            WHERE meta_value=$uuid_sql AND meta_key ='minecraft_uuid'
	    LIMIT 1;";
        $data = umc_mysql_fetch_all($sql);
        $out = $data[0]['user_login'];
    }
    return $out;
}

/**
 * Get a wp user ID from the UUID
 * This NEEDS to be the user_login, not display_name,
 *
 * @param string $uuid
 * @return string
 */
function umc_wp_get_username_from_uuid($uuid) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER, $UMC_ENV;
    $current_uuid = $UMC_USER['uuid'];
    if (($UMC_ENV == 'wordpress') && ($uuid == $current_uuid)) {
        $current_user = wp_get_current_user();
        $out = $current_user->display_name;
    } else {
        $uuid_sql = umc_mysql_real_escape_string($uuid);
        $sql = "SELECT display_name FROM minecraft.wp_usermeta
            LEFT JOIN minecraft.wp_users ON ID=user_id
            WHERE meta_value=$uuid_sql AND meta_key ='minecraft_uuid' LIMIT 1;";
        $data = umc_mysql_fetch_all($sql);
        $out = $data[0]['display_name'];
    }
    return $out;
}


/**
 * Get a UUID from the wp_username
 *
 * @param string $uuid
 * @return string
 */
function umc_wp_get_uuid_for_currentuser() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $current_user = wp_get_current_user();
    $username = $current_user->display_name;

    if ($username == '') {
        // we have a guest, get UUID from system instead
        return false;
    }

    $username_sql = umc_mysql_real_escape_string($username);
    $sql = "SELECT meta_value as uuid FROM minecraft.wp_usermeta
        LEFT JOIN minecraft.wp_users ON ID=user_id
        WHERE display_name=$username_sql AND meta_key ='minecraft_uuid' LIMIT 1;";
    $data = umc_mysql_fetch_all($sql);
    if (count($data) == 0) {
        return false;
    }
    $out = strtolower($data[0]['uuid']);
    return $out;
}

/**
 * Get a meta-value for a user, identified by their UUID
 * The system stores User preferences (such as mail alert)
 *
 * @param type $user
 * @param type $meta_key
 */
function umc_wp_get_meta($uuid, $meta_key) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $current_uuid = $UMC_USER['uuid'];
    if ($uuid == $current_uuid) {
        $user = wp_get_current_user();
    } else {
        $username = umc_wp_get_login_from_uuid($uuid);
        $user = get_user_by('login', $username);
    }
    $value = get_user_meta($user->ID, $meta_key, true);
    // we do not alert on false since every unset falue returns false
    return $value;
}

/**
 * Set a meta-value for a user
 *
 * @param string $uuid
 * @param string $meta_key
 * @param string $meta_value
 */
function umc_wp_set_meta($uuid, $meta_key, $meta_value) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $current_uuid = $UMC_USER['uuid'];
    if ($uuid == $current_uuid) {
        $user = wp_get_current_user();
    } else {
        $user_login = umc_wp_get_login_from_uuid($uuid);
        $user = get_user_by('login', $user_login);
    }
    $check = update_user_meta($user->ID, $meta_key, $meta_value);
    if (!$check) {
        XMPP_ERROR_trigger("Unable to set User Meta $meta_key to $meta_value for user $uuid and ID" . $user->ID);
    }
}

/**
 * takes the wordpress userlogin and returns the UUID as stored in the meta data
 * 
 * @param type $user_login
 * @return type
 */
function umc_wp_get_uuid_from_userlogin($user_login) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $user = get_user_by('login', $user_login);
    $uuid = get_user_meta($user->ID, 'minecraft_uuid', true);
    return $uuid;
}
