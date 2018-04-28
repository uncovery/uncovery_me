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
 * This file manages wordpress-specific data and should contain all wordpress
 * specific functionality (except for WP plugins)
 */

/**
 * Get all variables of the current user from Wordpress and add it to $UMC_USER
 */
function umc_wp_get_vars() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER, $UMC_ENV, $user_email, $user_login;

    if ($UMC_ENV !== 'wordpress') {
        // XMPP_ERROR_trigger("Tried to get wordpress vars, but environment did not match: " . var_export($UMC_ENV, true));
        // die();
    }
    get_currentuserinfo();
    if (!isset($user_login) || ($user_login == '') || ($user_email == '')) {
        $UMC_USER = false;
    } else {
        if (!function_exists('umc_userlevel_get')) {
            XMPP_ERROR_send_msg("Could not get uuid_level, Env = $UMC_ENV");
            require_once('/home/minecraft/server/bin/core_include.php');
        }
        $uuid = umc_wp_get_uuid_for_currentuser();

        $UMC_USER['ip'] = filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_FLAG_IPV4);
        if (!$uuid) { // we have a guest who is trying to register
            $UMC_USER['username'] = $user_login;
            $UMC_USER['email'] = $user_email;
            $uuid = umc_user2uuid($user_login);
            $UMC_USER['uuid'] = $uuid;
            $UMC_USER['userlevel'] = 'Guest';
        } else { // there is a logged-in user
            // we do not check here since we do not know if the username is correct
            // also we do not want to check at mojang every time.
            // umc_uuid_check_usernamechange($uuid, $UMC_USER['username']);
            $UMC_USER['email'] = $user_email;
            $UMC_USER['username'] = umc_uuid_getone($uuid, 'username');
            $UMC_USER['uuid'] = $uuid;
            $UMC_USER['userlevel'] = umc_userlevel_get($uuid);
            if (strstr($UMC_USER['userlevel'], 'Donator')) {
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
        umc_plugin_eventhandler('any_wordpress');
    }
    //$UMC_USERS[$uuid] = new UMC_User($uuid);
    //$UMC_USERS[$uuid]->set_username($username);
    //$UMC_USERS[$uuid]->set_userlevel($userlevel);

}

/**
 * When banning a users, reset the users password in the WP database to something
 * random and log the user out of the system
 *
 * @param type $uuid
 */
function umc_wp_ban_user($uuid) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    // get wordpress ID
    $wp_id = umc_user_get_wordpress_id($uuid);
    XMPP_ERROR_trace("User ID", $wp_id);
    $password = wp_generate_password(20, true, true);
    XMPP_ERROR_trace("New random Password", $wp_id);
    wp_set_password($password, $wp_id);
    // get all sessions for user with ID $user_id
    $sessions = WP_Session_Tokens::get_instance($wp_id);
    XMPP_ERROR_trace("sessions incoming", $sessions);
    // we have got the sessions, destroy them all!
    $sessions->destroy_all();
    XMPP_ERROR_trace("sessions outgoing", $sessions);
    XMPP_ERROR_trigger("User $uuid banned");
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
 * Get a wp username (login name) from the UUID
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
 * Get a wp ID (nmumeric) from the UUID
 * This NEEDS to be the user_login, not display_name,
 *
 * @param string $uuid
 * @return string
 */
function umc_wp_get_id_from_uuid($uuid) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER, $UMC_ENV;
    $current_uuid = $UMC_USER['uuid'];
    if (($UMC_ENV == 'wordpress') && ($uuid == $current_uuid)) {
        $out = get_current_user_id();
    } else {
        $uuid_sql = umc_mysql_real_escape_string($uuid);
        $sql = "SELECT ID FROM minecraft.wp_users
            LEFT JOIN minecraft.wp_usermeta ON ID=user_id
            WHERE meta_value=$uuid_sql AND meta_key ='minecraft_uuid'
	    LIMIT 1;";
        $data = umc_mysql_fetch_all($sql);
        $out = $data[0]['ID'];
    }
    return $out;
}


/**
 * Checks for a specific user to exist in wordpress
 * Can take user_login, display_name or UUID
 *
 * TODO merge/replace with above function
 *
 * returns the wordpress ID
 *
 * This will replace the below umc_check_user
 *
 * @param type $display_name
 */
function umc_user_get_wordpress_id($query) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    if (strlen($query) < 2) {
        return false;
    }
    $username_quoted = umc_mysql_real_escape_string($query);
    // UUID
    if (strlen($query) > 17) {
        $uuid = true;
        $sql = "SELECT user_id as ID FROM minecraft.wp_usermeta
            WHERE meta_value LIKE $username_quoted;";
        $D = umc_mysql_fetch_all($sql);
    } else {
        // Username
        $uuid = false;
        $sql = "SELECT ID FROM minecraft.wp_users
            WHERE user_login LIKE $username_quoted;";
        $D = umc_mysql_fetch_all($sql);
        if (count($D) == 0) { // we might have the display_name, not the login
            $sql = "SELECT ID FROM minecraft.wp_users
                WHERE display_name LIKE $username_quoted;";
            $D = umc_mysql_fetch_all($sql);
        }
    }

    if (count($D) == 0) {
        return false;
    } else {
        return $D[0]['ID'];
    }
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
 * optionally for a passed user object
 *
 * @param string $uuid
 * @return string
 */
function umc_wp_get_uuid_for_currentuser($user_obj = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    if (!$user_obj) {
        $current_user = wp_get_current_user();
    } else {
        $current_user = $user_obj;
    }

    $user_id = $current_user->ID;
    $sql = "SELECT meta_value as uuid FROM minecraft.wp_usermeta
        WHERE user_id = $user_id AND meta_key ='minecraft_uuid' LIMIT 1;";
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