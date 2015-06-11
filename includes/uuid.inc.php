<?php

function umc_tmp_fixtables() {
    $tables = array(
        'minecraft_srvr.proposals' => array('username' => 'uuid', 'proposer' => 'proposer_uuid'),
        'minecraft_srvr.proposals_votes' => array('voter' => 'voter_uuid'),
    );

    foreach ($tables as $table => $fields) {
        foreach ($fields as $userfield => $uuidfield) {
            $sql = "SELECT $userfield FROM $table WHERE $uuidfield = '' GROUP BY $userfield;";
            echo $sql. "<br>";
            $data = umc_mysql_fetch_all($sql);
            foreach ($data as $row) {
                $username = $row[$userfield];
                $json = file_get_contents("https://api.mojang.com/users/profiles/minecraft/$username?at=141321800");
                $object = json_decode($json);
                $uuid_raw = $object->id;
                $uuid = umc_uuid_format($uuid_raw);
                if ($uuid) {
                    $update_sql = "UPDATE $table SET $uuidfield='$uuid' WHERE $userfield='$username';";
                    echo $update_sql;
                    umc_mysql_query($update_sql, true);
                }
            }
        }
    }
}

/**
 * Update last login time, last logout time, onlinetime
 *
 * @global type $UMC_USER
 * @param type $type
 */
function umc_uuid_record_usertimes($type) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $uuid = $UMC_USER['uuid'];
    $username = $UMC_USER['username'];

    // make sure the userdata exists
    $D = umc_uuid_userdata($uuid, $username);

    umc_uuid_login_logout_update($type);
    // in case the user logged out just now, we update the onlinetime of the user
    if ($type == 'lastlogout') {
        // get the data again since it just changed
        $D = umc_uuid_userdata($uuid, $username);
        // we only calculate this in case the time is properly recorded

        if ($D['lastlogin'] <= $D['lastlogout']) {
            $login = umc_datetime($D['lastlogin']);
            $logout = umc_datetime($D['lastlogout']);
            $seconds = umc_timer_raw_diff($login, $logout);

            $online_sql = "UPDATE minecraft_srvr.UUID SET onlinetime=onlinetime+$seconds WHERE UUID='$uuid';";
            umc_mysql_query($online_sql);
        } else {
            XMPP_ERROR_trigger("User login was later than last logout! ".  var_export($D, true));
        }
    }
}

function umc_uuid_login_logout_update($type) {
    global $UMC_USER;
    $uuid = $UMC_USER['uuid'];

    $lots = umc_user_countlots($uuid);
    $userlevel = $UMC_USER['userlevel'];
    $sql = "UPDATE minecraft_srvr.UUID SET $type=NOW(), lot_count=$lots, userlevel='$userlevel' WHERE UUID='$uuid';";
    umc_mysql_query($sql);
}

function umc_uuid_userdata($uuid, $username) {
    $sql_time = "SELECT * FROM minecraft_srvr.UUID WHERE UUID='$uuid';";
    $data = umc_mysql_fetch_all($sql_time);
    if (count($data) == 0) {
        $ins_sql = "INSERT INTO minecraft_srvr.UUID (UUID, username) VALUES ('$uuid', '$username');";
        umc_mysql_query($ins_sql);
        umc_uuid_firstlogin_update($uuid);
        // we cal lthis function again to get the data output;
        umc_uuid_userdata($uuid, $username);
    } else {
        return $data[0];
    }
}

function umc_uuid_firstlogin_update($uuid) {
    $sql = "SELECT user_registered FROM minecraft.wp_users
        LEFT JOIN minecraft.wp_usermeta ON ID=user_id
        WHERE meta_value='$uuid';";
    $D = umc_mysql_fetch_all($sql);
    if (count($D) > 0) {
        $date = $D[0]['user_registered'];
        $up_sql = "UPDATE minecraft_srvr.UUID SET firstlogin='$date' WHERE UUID='$uuid';";
        umc_mysql_query($up_sql);
    }
}


/**
 * Updates the amount of lots a user has in the UUID table
 * if $user = false, update ALL lot counts
 *
 * @param type $user
 */
function umc_uuid_record_lotcount($user = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    if ($user) {
        $uuid = umc_uuid_getone($user, 'uuid');
        $lots = umc_user_countlots($uuid);
        $sql = "UPDATE minecraft_srvr.UUID SET lot_count=$lots WHERE UUID='$uuid';";
        umc_mysql_query($sql);
    } else {


    }
}


/**
 * This checks if the username has changed and updates the wordpress table accordingly
 *
 * @param type $username
 * @param type $uuid
 */
function umc_uuid_check_usernamechange($uuid) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    $sql = 'SELECT ID, user_login, display_name, UUID, username, wp_users.user_registered, UUID.lastlogin FROM minecraft.`wp_users`
        LEFT JOIN minecraft.wp_usermeta ON ID=wp_usermeta.user_id
        LEFT JOIN minecraft_srvr.UUID ON UUID.UUID=wp_usermeta.meta_value
        WHERE meta_key=\'minecraft_uuid\' AND meta_value=\'' . $uuid . '\'';
    $D = umc_mysql_fetch_all($sql);

    foreach ($D as $d) {
        // get proper username from Mojang
        $uuid = $d['UUID'];
        $wordpress_name = strtolower($d['display_name']);
        $uuid_name = strtolower($d['username']);
        $wp_login = $d['user_login'];
        $wp_id = $d['ID'];
        $mojang_raw = umc_uuid_get_from_mojang($uuid);
        $mojang_name = strtolower($mojang_raw['name']);
        if (!$mojang_name) {
            XMPP_ERROR_trigger("Tried to check for username change, failed to confirm ($sql)");
            // let's try the user_login
        /*    $mojang_uuid = umc_uuid_get_from_mojang($wp_login);
            // update the meta table
            $u_sql_meta = "UPDATE minecraft.wp_usermeta SET meta_value='$mojang_uuid' WHERE user_id='$wp_id' AND meta_key='minecraft_uuid'";
            umc_mysql_query($u_sql_meta, true);
         *
         */
        }
        if ($wordpress_name != $mojang_name) {
            $u_sql_wp = "UPDATE minecraft.wp_users SET display_name='$mojang_name' WHERE user_login='$wp_login'";
            $logtext = "User $uuid changed username from $wordpress_name to $mojang_name in Wordpress";
            XMPP_ERROR_send_msg($logtext);
            umc_log('UUID', 'Username Change', $logtext);
            umc_mysql_query($u_sql_wp, true);
        }
        if ($uuid_name != $mojang_name) {
            $u_sql_uuid = "UPDATE minecraft_srvr.UUID SET username='$mojang_name' WHERE UUID='$uuid'";
            $logtext = "User $uuid changed username from $uuid_name to $mojang_name in UUID table";
            XMPP_ERROR_send_msg($logtext);
            umc_log('UUID', 'Username Change', $logtext);
            umc_mysql_query($u_sql_uuid, true);
        }
    }
}

/**
 * returns an array('uuid'=> $uuid, 'user'=>$username) from any input
 * retrieve with format:
    $U = umc_uuid_getboth($user);
    $uuid = $U['uuid'];
    $username = $U['username'];
 *
 * @param string $query
 * @return array('uuid'=> $uuid, 'user'=>$username)
 */
function umc_uuid_getboth($query) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    // input is a uuid
    if (strlen($query) > 18) {
        $uuid = $query;
        $username = umc_user2uuid($query);
    } else {
        $username = $query;
        $uuid = umc_user2uuid($query);
    }

    return array('uuid'=> $uuid, 'username'=>$username);
}

/**
 * get's either the uuid or username, depending on format
 * @param string $query username or uuid
 * @param string $format of array('username', 'uuid')
 * @return string
 */
function umc_uuid_getone($query, $format = 'uuid') {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    if ($format == 'uuid' && strlen($query) > 18) {
        return $query;
    } else if ($format == 'uuid' && strlen($query) < 18) {
        return umc_user2uuid($query);
    } else if ($format == 'username' && strlen($query) > 18) {
        return umc_user2uuid($query);
    } else if ($format == 'username' && strlen($query) < 18) {
        return $query;
    } else {
        XMPP_ERROR_trigger("Error format for umc_uuid_getone ($format / $query)");
    }
}

/**
 * retrieves current uuid from username and vice-versa from various sources
 *
 * @param string $query either uuid or username
 * @param boolean $critical if critical, an error would be thrown if the user cannot be found
 * @return string either username or uuid
 */
function umc_user2uuid($query) {
    // get a username
    global $UMC_USER;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    if (strlen($query) < 2) {
        XMPP_ERROR_trigger("UUID/Username failed since query too short!" . var_export($query, true));
        return false;
    }

    // allways try to get user uuid etc from the current user
    if (isset($UMC_USER['username']) && $query == $UMC_USER['username'] && isset($UMC_USER['uuid'])) {
        return $UMC_USER['uuid'];
    } else if (isset($UMC_USER['uuid']) && $query == $UMC_USER['uuid'] && isset($UMC_USER['username'])) {
        return $UMC_USER['username'];
    }

    $checks = array(
        'umc_uuid_get_system_users',
        'umc_uuid_get_from_wordpress',
        'umc_uuid_get_from_uuid_table',
        'umc_uuid_get_from_logfile',
        'umc_uuid_get_from_mojang'
    );

    foreach ($checks as $function) {
        $result = $function($query);
        if ($result) {
            break;
        }
    }
    if ($result) {
        return $result;
    } else {
        // umc_error_longmsg("Could not find UUID/Username for $query");
        return false;
    }
}

/**
 * check against default system users
 *
 * @param type $query
 * @return boolean
 */
function umc_uuid_get_system_users($query) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    if ($query == 'WEBSEND_EVENTS_ENTITY') {
        $query = 'Server';
    }

    $default_uuids = array(
        'cancel_deposit' => 'cancel00-depo-0000-0000-000000000000',
        'cancel_item' =>    'cancel00-item-0000-0000-000000000000',
        'cancel_sell' =>    'cancel00-sell-0000-0000-000000000000',
        'lot_reset' =>      'reset000-lot0-0000-0000-000000000000',
        'lottery' =>        'lottery0-lot0-0000-0000-000000000000',
        '_abandoned_' =>    'abandone-0000-0000-0000-000000000000',
        'contest_refund' => 'contest0-refu-0000-0000-000000000000',
        'Shop' =>           'shop0000-0000-0000-0000-000000000000',
        'Console' =>        'Console0-0000-0000-0000-000000000000',
        'Server' =>         'Server00-0000-0000-0000-000000000000',
    );

    if (strlen($query) < 17) {
        $array = $default_uuids;
    } else {
        $array = array_flip($default_uuids);
    }


    if (isset($array[$query])) {
        return $array[$query];
    } else if (strstr($query, 'Contest')) {
        // contest has several differnt variations, so we do wildcards here
        return 'contest0-refu-0000-0000-000000000000';
    } else {
        return false;
    }
}


/**
 * get a userid or username from wordpress database
 *
 * @param type $query
 */
function umc_uuid_get_from_wordpress($query) {

    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    $str_query = strtolower($query);
    $query_sql = umc_mysql_real_escape_string(strtolower($str_query));
    if (strlen($query) < 17) {
        $sel_field = 'meta_value';
        $where_field = 'display_name';
    // get a UUID
    } else {
        $sel_field = 'display_name';
        $where_field = 'meta_value';
    }
    // look in the wordpress database
    $sql = "SELECT $sel_field AS output FROM minecraft.wp_users
        LEFT JOIN minecraft.wp_usermeta ON ID=user_id
        WHERE $where_field=$query_sql AND meta_key ='minecraft_uuid'
	LIMIT 1;";
    $data = umc_mysql_fetch_all($sql);
    if (count($data) == 1) {
        return $data[0]['output'];
    } else {
        return false;
    }
}

/**
 * get a userid or username from wordpress database
 *
 * @param type $query
 */
function umc_uuid_get_from_uuid_table($query) {

    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    $str_query = strtolower($query);
    $query_sql = umc_mysql_real_escape_string(strtolower($str_query));
    if (strlen($query) < 17) {
        $sel_field = 'UUID';
        $where_field = 'username';
    // get a UUID
    } else {
        $sel_field = 'username';
        $where_field = 'UUID';
    }
    // look in the wordpress database
    $sql = "SELECT $sel_field as output FROM minecraft_srvr.UUID
        WHERE $where_field=$query_sql
	LIMIT 1;";
    $data = umc_mysql_fetch_all($sql);
    if (count($data) == 1) {
        return $data[0]['output'];
    } else {
        return false;
    }
}

/**
 * Reads username/uuid pairs from the latest logfile and returns the username or
 * uuid, depending on what was asked
 *
 * @return boolean/string
 */
function umc_uuid_get_from_logfile($query) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    $str_query = strtolower($query);
    $text = file("/home/minecraft/server/bukkit/logs/latest.log");
    // reverse
    $back_text = array_reverse($text);
    $pattern = '/\[id=(.*),name=(.*),/U';
    $pattern2 = '/UUID of player (.*) is (.*)/';
    $result = array();
    $result2 = array();
    $userdata = array();
    foreach ($back_text as $line_text) {
        preg_match($pattern, $line_text, $result);
        if (count($result) == 0) {
            preg_match($pattern2, $line_text, $result2);
            if (count($result2) == 0) {
                continue;
            } else {
                $username = strtolower($result2[1]);
                $userdata[$username] =  $result2[2];
            }
        } else if ($result[1] == '<null>') {
            continue;
        } else {
            $username = strtolower($result[2]);
            $userdata[$username] = $result[1];
        }
    }
    // update all of those into the UUID table
    foreach ($userdata as $username => $uuid) {
        $check_sql = "SELECT username FROM minecraft_srvr.UUID WHERE UUID='$uuid';";
        $check_data = umc_mysql_fetch_all($check_sql);
        if (count($check_data) == 0) {
            $ins_sql = "INSERT INTO minecraft_srvr.UUID (UUID, username) VALUES ('$uuid', '$username');";
            umc_mysql_query($ins_sql);
        } else {
            if ($check_data[0]['username'] != $username) {
                $sql = "UPDATE minecraft_srvr.UUID SET username='$username' WHERE UUID='$uuid';";
                umc_mysql_query($sql);
            }
        }
    }

    $uuid_data = array_flip($userdata);
    if (strlen($query) < 17 && isset($userdata[$str_query])) {
        // return username
        return $userdata[$str_query];
    } else if (strlen($query) > 17 && isset($uuid_data[$str_query])) {
        return $uuid_data[$str_query];
    } else {
        return false;
    }
}

/**
 * get a uuid or username from Mojang
 *
 * @param type $username or userid
 * @return boolean
 */
function umc_uuid_get_from_mojang($username, $timer = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    if(strlen($username) < 17) {
        if (!preg_match('/^[A-Za-z_\d ]{1,16}$/', $username)) {
            return false;
        }

        if ($timer) {
            $data_arr = array($username, 141321800);
        } else {
            $data_arr = array($username);
        }

        $opts = array('http' =>
            array(
                'method' => 'POST',
                'header' => 'Content-type: application/json',
                'content' => json_encode($data_arr),
            ),
        );

        $context = stream_context_create($opts);
        $json_result = file_get_contents('https://api.mojang.com/profiles/minecraft', false, $context);
        $json_data = json_decode($json_result, true);
        if (isset($json_result['error']) && $json_result['error'] == 'TooManyRequestsException') {
            XMPP_ERROR_trigger("could not get UUID for $username from Mojang: " . var_export($json_result, true));
            return false;
        }

        if (count($json_data) == 0) {
            $text = var_export($json_data, true);
            XMPP_ERROR_trigger("Could not find username for $username at Mojang ($text)");
            return false;
        }
        return umc_uuid_format($json_data[0]["id"]); // add "-" dashes if needed
    } else {
        $uuid = $username;
        $uuid_raw = str_replace("-", "", $uuid);
        // https://api.mojang.com/user/profiles/a0130adc42ad4e619da2f90a5bc310d3/names
        $url = "https://api.mojang.com/user/profiles/$uuid_raw/names";
        $json_result = file_get_contents($url, false);
        $json_data = json_decode($json_result, true);
        if (count($json_data) == 0) {
            $text = var_export($json_data, true);
            XMPP_ERROR_trigger("Could not find username for $uuid at Mojang $url ($text)");
            return false; // invalid uuid or too long username
        } else {
            $id = count($json_data) - 1;
            return $json_data[$id];
        }
    }
}

/**
 * format a UUID with dashes in case there are none
 * Mojang normally delivers them without dashes
 *
 * @param type $uuid
 * @return type
 */
function umc_uuid_format($uuid) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    if (strstr($uuid, "-")) {
        return $uuid;
    }
    $pattern = "/(.{8})(.{4})(.{4})(.{4})(.{12})/";
    $res = NULL;
    preg_match($pattern, $uuid, $res);
    $new_uuid = "{$res[1]}-{$res[2]}-{$res[3]}-{$res[4]}-{$res[5]}";
    return $new_uuid;
}
