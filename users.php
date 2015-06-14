<?php

global $UMC_FUNCTIONS;
$UMC_FUNCTIONS['update_usericons'] = 'umc_update_usericons';

function umc_sanity_check_users() {
    $sql = "SELECT username, userlevel, user.uuid, count(region_id) as counter
        FROM minecraft_worldguard.`region_players`
        LEFT JOIN minecraft_worldguard.user ON user_id = user.id
        LEFT JOIN minecraft_worldguard.world ON world_id = world.id
        LEFT JOIN minecraft_srvr.UUID ON user.uuid=UUID.UUID
        WHERE Owner=1 AND (userlevel LIKE 'Settler%' OR userlevel LIKE 'Citizen%') AND (world.name IN ('flatlands', 'empire'))
        GROUP BY uuid
        HAVING counter > 1 ";
    $D = umc_mysql_fetch_all($sql);
}


/**
 * Find all users in a specific world
 *
 * @global type $UMC_PATH_MC
 * @param type $world
 * @return type
 */
function umc_users_by_world($world) {
    global $UMC_PATH_MC;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $file = "$UMC_PATH_MC/server/bin/data/markers.json";
    $text = file_get_contents($file);
    $m = json_decode($text);
    $out = array();

    foreach ($m as $marker) {
        if ($marker->world == $world) {
            $uuid = umc_user2uuid($marker->msg);
            $out[$uuid] = strtolower($marker->msg);
        }
    }
    return $out;
}


/**
 * Retrieve the userlevel from a uuid
 *
 * @global type $UMC_USER
 * @param type $uuid
 * @return string
 */
function umc_get_uuid_level($uuid) {
    global $UMC_USER;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    // check if the userlevel is already set
    if ($uuid == $UMC_USER['uuid'] && isset($UMC_USER['userlevel'])) {
        return $UMC_USER['userlevel'];
    }

    if (!is_array($uuid) && (strtolower($uuid) == '@console')) {
        return "Owner";
    }
    if (strlen($uuid) < 35) {
        XMPP_ERROR_trigger("umc_get_uuid_level: Tried to get uuid-level of invalid UUID: $uuid");
    }

    if (is_array($uuid)) {
        $uuid_str = implode("','", $uuid);
        $count = count($uuid);
    } else {
        $uuid_str = $uuid;
        $count = 1;
    }
    //SELECT * FROM `permissions_inheritance` WHERE `child` LIKE 'a1b763b9-bd7d-4914-8b4b-8c20bddb5882' ORDER BY `child` DESC

    $sql = "SELECT parent AS userlevel, value AS username, name AS uuid FROM minecraft_srvr.permissions
        LEFT JOIN minecraft_srvr.`permissions_inheritance` ON name=child
        WHERE `name` IN ('$uuid_str') AND permissions.permission='name'";
    $rst = mysql_query($sql);
    $uuid_levels = array();
    // user not found, so he's guest
    if (mysql_num_rows($rst) == 0)  {
        return "Guest";
    }
    //parent 	value 	name
    // Owner 	uncovery	ab3bc877-4434-45a9-93bd-bab6df41eabf

    // otherwise get results
    while ($row = mysql_fetch_array($rst, MYSQL_ASSOC)) {
        $uuid = $row['uuid'];
        $level = $row['userlevel'];
        if ($level == 'NULL') {
            $level = 'Guest';
        }
        $uuid_levels[$uuid] = $level;
    }
    // check if all users were found, if not, set them as guest
    if (is_array($uuid)) {
        foreach ($uuid as $user) {
            if (!isset($uuid_levels[$user])) {
                $uuid_levels[$user] = 'Guest';
            }
        }
        return $uuid_levels;
    } else {
        if (!isset($uuid_levels[$uuid_str])) {
            // umc_error_msg("Could not determine userlevel for UUID $uuid");
            return "Guest";
        }
        return $uuid_levels[$uuid_str];
    }
}

/*
This function checks the rank permissions for the plugin rights
*/
function umc_rank_check($player_rank, $required_rank) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_SETTING;
    if ($player_rank == 'Owner') {
        return true;
    }
    foreach ($UMC_SETTING['ranks'] as $rank) {
        if ($rank == $required_rank) { // We got to the required rank without finding the player's rank first, success!
            return true;
        }
        if ($rank == $player_rank) { // We got to the players rank without finding the require rank first, failure.
            return false;
        }
    } // We didn't find either rank yet, move on to the next one.
    // we could not find the rank at all, fail and alert
    XMPP_ERROR_trigger("Could not identify rank $player_rank / $required_rank (umc_rank_check)");
    return false;
}

/**
 * This is setting a permission for a user through websend /pex user command
 * @param type $user
 * @param type $permission
 * @param type $world
 * @param string $timed
 */
function umc_user_permission_set($user, $permission, $world = false, $timed = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    if (!$world) {
        $world = '';
    } else {
        $world = " $world";
    }
    $timed_activator = ' timed';
    if (!$timed) {
        $timed = '';
        $timed_activator = '';
    }
    $uuid = umc_uuid_getone($user, 'uuid');

    $cmd = "pex user $uuid$timed_activator add $permission$world";
    umc_exec_command($cmd, 'asConsole');
}


/**
 * This checks if a user has a permission by executing a console command
 * and checking the logfile if the returned response from the system exists
 * This is not accurate if done 2x during the same day
 *
 * @param type $user
 * @param type $permission
 * @param type $world
 * @return boolean
 */
function umc_user_permission_get($user, $permission, $world = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_PATH_MC;
    if (!$world) {
        $world = '';
    } else {
        $world = " $world";
    }
    $U = umc_uuid_getboth($user);
    $uuid = $U['uuid'];
    $username = $U['username'];
    $cmd = "pex user $uuid check $permission";
    umc_exec_command($cmd, 'asConsole');

    $check = "Player \"$username\" have \"$permission\" = true";
    exec("grep '$check' $UMC_PATH_MC/server/bukkit/logs/latest.log", $match_array);
    // result = [16:15:09] [Thread-13/INFO]: Player "uncovery" have "custom_title" = true[m
    $latest_result = count($match_array) - 1;
    $result = $match_array[$latest_result];
    if (strstr($result, 'true')) {
        return true;
    } else {
        return false;
    }
    // preg match pattern to find the time: '/\[(\d{2}):(\d{2}):(\d{2})\].*(true)/';
}

/**
 * retrieve the last login date of active users and their userlevel
 *
 * @return array
 */
function umc_users_active_lastlogin_and_level() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $sql_1 = "SELECT user.uuid as uuid, lower(username) as username, lastlogin, userlevel
        FROM minecraft_worldguard.region_players
        LEFT JOIN minecraft_worldguard.user ON user_id=id
        LEFT JOIN minecraft_srvr.UUID ON user.uuid=UUID.UUID
        WHERE owner=1 AND user.uuid IS NOT NULL AND username is NOT NULL
        GROUP BY username
        ORDER BY username;";
    // XMPP_ERROR_trace($sql);
    $rst = umc_mysql_query($sql_1);
    $lastlogins = array('abandone-0000-0000-0000-000000000000' => array('username' => '_abandoned_', 'userlevel' => 'Guest', 'lastlogin' => '2010-01-01 00:00:00'));
    while ($row = umc_mysql_fetch_array($rst)) {
        $lastlogins[$row['uuid']]['lastlogin'] = $row['lastlogin'];
        $lastlogins[$row['uuid']]['username'] = $row['username'];
        $lastlogins[$row['uuid']]['userlevel'] = $row['userlevel'];
        // default Guest; if there is a level set, it will be updated in the next step
        // $lastlogins[$row['name']]['userlevel'] = 'Guest';
    }

    // chek for userlevel issues
    foreach ($lastlogins as $uuid => $D) {
        if (!isset($D['userlevel'])) {
            XMPP_ERROR_trigger("User $uuid / {$D['username']} has a lot but no userlevel!!");
        }
    }
    return $lastlogins;
}

/**
 * returns an array of all players that own a lot right now.
 *
 * @return type
 */
function umc_get_active_members() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $active_members = array();
    $sql = "SELECT user.uuid as user_uuid, lower(username) as name FROM minecraft_worldguard.region_players
        LEFT JOIN minecraft_worldguard.user ON user_id=id
        LEFT JOIN minecraft_srvr.UUID ON user.uuid=UUID.UUID
        WHERE owner=1 AND user.uuid IS NOT NULL AND username IS NOT NULL
        GROUP BY user_uuid ORDER BY name";
    $data = umc_mysql_fetch_all($sql);
    foreach ($data as $row) {

        $active_members[$row['user_uuid']] = $row['name'];
    }
    return $active_members;
}

/**
 * returns a list of the latest 5 settlers
 *
 * @param type $limit
 * @return type
 */
function umc_get_latest_settlers($limit = 5) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    $settlers = array();
    $sql = "SELECT UUID, username FROM minecraft_srvr.UUID
        WHERE userlevel='Settler'
        ORDER BY `firstlogin` DESC LIMIT $limit;";
    $data = umc_mysql_fetch_all($sql);
    foreach ($data as $row) {
        $settlers[$row['UUID']] = $row['username'];
    }
    return $settlers;
}



/**
 * returns the userlevel for a username
 *
 * @param type $username
 * @return string
 */
function umc_get_userlevel($username) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    if (!is_array($username) && (strtolower($username) == '@console')) {
        return "Owner";
    }
    if (is_array($username)) {
        $user_arr = $username;
    } else {
        $username_str = $username;
        $user_arr = array($username);
    }
    // fix username capitalization
    $user_arr_ok = array();
    foreach ($user_arr as $name) {
        $sql_search = "SELECT value FROM minecraft_srvr.permissions WHERE value LIKE '$name' LIMIT 1;";
        $rst_search = mysql_query($sql_search);
        $row = mysql_fetch_array($rst_search, MYSQL_ASSOC);
        $user_arr_ok[] = $row['value'];
    }
    $username_str = implode("','", $user_arr_ok);

    $sql = "SELECT parent as userlevel, value as username, name as uuid FROM minecraft_srvr.permissions
        LEFT JOIN minecraft_srvr.`permissions_inheritance` ON name=child
        WHERE `value` IN ('$username_str') AND permissions.permission='name';";
    $D = umc_mysql_fetch_all($sql);

    $user_levels = array();
    // user not found, so he's guest
    if (count($D) == 0)  {
        return "Guest";
    }
    //parent 	value 	name
    // Owner 	uncovery	ab3bc877-4434-45a9-93bd-bab6df41eabf

    // otherwise get results
    // umc_error_notify($username_str);
    $row = $D[0];
    $user = $row['username'];
    $level = $row['userlevel'];
    if ($level == NULL) {
        $level = 'Guest';
    }
    $user_levels[strtolower($user)] = $level;
    // check if all users were found, if not, set them as guest
    if (count($user_arr_ok) > 1) {
        foreach ($user_arr_ok as $user) {
            $lower_user = strtolower($user);
            if (!isset($user_levels[$lower_user])) {
                $user_levels[$lower_user] = 'Guest';
            }
        }
        return $user_levels;
    } else {
        $lower_user = strtolower($username_str);
        if (!isset($user_levels[$lower_user])) {
            XMPP_ERROR_trigger("Could not find userlevel for user $username with $sql");
            return "Guest";
        }
        return $user_levels[$lower_user];
    }
}
/**
 * Checks for a specific user to exist in wordpress
 * Can take user_login, display_name or UUID
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
 * check if a user exists in the wordpress database.
 * uuid enabled
 *
 * @global type $UMC_PATH_MC
 * @param string $user
 * @return boolean
 */
function umc_check_user($username) {
    global $UMC_PATH_MC;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    if (strlen($username) < 2) {
        return false;
    }

    // do we check a uuid?
    $username_quoted = umc_mysql_real_escape_string($username);
    // UUID
    if (strlen($username) > 17) {
        $uuid = true;
        $sql = "SELECT display_name FROM minecraft.wp_usermeta
            LEFT JOIN minecraft.wp_users ON ID=user_id
            WHERE meta_value LIKE $username_quoted;";
    } else {
        // Username
        $uuid = false;
        $sql = "SELECT display_name FROM minecraft.wp_users
            WHERE user_login LIKE $username_quoted;";
    }
    $data = umc_mysql_fetch_all($sql);
    if (count($data) == 1) {
        return $data[0]['display_name'];
    } else {
        if (!$uuid) {
            $uuid = umc_user2uuid($username);
        }
        $path = "$UMC_PATH_MC/server/bukkit/city/playerdata/$uuid.dat";
        $check = file_exists($path);
        if ($check) {
            return $username;
        } else {
            // let's try wildcards
            $user_quoted_wild = umc_mysql_real_escape_string("%$username%");
            $sql_wild = "SELECT display_name FROM minecraft.wp_users
                WHERE display_name LIKE $user_quoted_wild;";
            $data_wild = umc_mysql_fetch_all($sql_wild);
            if (count($data_wild) == 1) {
                return $data_wild[0]['display_name'];
            } else {
                return false;
            }
        }
    }
}

// checks is a user exists, returns email
function umc_user_email($username) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $username_quoted = umc_mysql_real_escape_string($username);
    $sql = "SELECT user_email FROM minecraft.wp_users WHERE display_name=$username_quoted LIMIT 1;";
    $data = umc_mysql_fetch_all($sql);
    if (count($data) > 0) {
        return $data[0]['user_email'];
    } else {
        return false;
    }
}

// get all user lots and the image link
function umc_user_getlots($uuid, $world = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    // worldguard stores everything in lower case.
    $filter = '';
    if ($world) {
        $filter = "AND world.name = '$world'";
    }
    $sql = "SELECT region_id, world.name FROM minecraft_worldguard.`region_players`
        LEFT JOIN minecraft_worldguard.user ON user_id = user.id
        LEFT JOIN minecraft_worldguard.world ON world_id = world.id
        WHERE Owner=1 AND uuid='$uuid' $filter ORDER BY region_id;";
    $rst = umc_mysql_query($sql);
    $out = array();
    //echo $sql;
    while ($row = umc_mysql_fetch_array($rst)) {
        $link = umc_user_get_lot_tile($row['region_id'], $row['name']);
        if (!$link) {
            $link = '';
        }
        $lot = $row['region_id'];
        $out[$lot] = array('world' => $row['name'], 'lot' => $lot, 'image' => $link);
    }
    umc_mysql_free_result($rst);
    return $out;
}

function umc_user_countlots($user) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    // worldguard stores everything in lower case.
    $uuid = umc_uuid_getone($user, 'uuid');
    $sql = "SELECT COUNT(region_id) AS counter
        FROM minecraft_worldguard.`region_players`
        LEFT JOIN minecraft_worldguard.user ON user_id=user.id
        WHERE owner=1 AND user.uuid='$uuid';";
    $rst = umc_mysql_query($sql);
    $row = umc_mysql_fetch_array($rst);
    umc_mysql_free_result($rst);
    $out = $row['counter'];
    return $out;
}

function umc_user_get_lot_tile($lot, $world = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_DOMAIN;
    if (!$world) {
        global $UMC_SETTING;
        $lot_split = explode("_", $lot);
        $prefix = $lot_split[0];
        foreach ($UMC_SETTING['world_data'] as $one_world => $data) {
            if ($prefix == $data['prefix']) {
                $world = $one_world;
            }
        }
    }
    return "<img style=\"background:#000000;\" src=\"$UMC_DOMAIN/map/lots/$world/$lot.png\" alt=\"$lot\">";
}

function umc_get_banned_users() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_SETTING;
    $bans = file($UMC_SETTING['banned_players_file']);
    $banlist = array();
    foreach ($bans as $ban) {
        if (substr($ban, 0, 1) == '#') {
            continue;
        }
        $line = explode("|", $ban);
        $banlist[] = $line[0];
    }
    return $banlist;
}

function umc_get_recent_bans($limit = 5) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $sql = "SELECT `ban_id`, `username`, `reason`, `admin`, `date` FROM minecraft_srvr.`banned_users` ORDER BY date DESC LIMIT $limit;";
    $rst = mysql_query($sql);
    $out = array();
    while ($row = mysql_fetch_array($rst, MYSQL_ASSOC)) {
        $out[$row['username']] = $row['reason'];
    }
    return $out;
}

/**
 * Checks if a user is banned, using the banned-players.json
 * detects if UUID is used or username
 *
 * @global type $UMC_SETTING
 * @param type $user
 * @return boolean
 */
function umc_user_is_banned($username) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_SETTING;
    $user = strtolower($username);

    /*  Data format:
     *  {
        "uuid": "e53b2d51-d919-4fc0-9db8-d61f66a57a2e",
        "name": "MichaelChopsWood",
        "created": "2012-11-24 02:26:44 +0800",
        "source": "(Unknown)",
        "expires": "forever",
        "reason": "Banned by an operator."
        },
     */

    $bans_file = file_get_contents($UMC_SETTING['banned_players_file']);
    $bans = json_decode($bans_file);
    foreach ($bans as $ban) {
        if ($user == strtolower($ban->uuid) || $user == strtolower($ban->name)) {
            return true;
        }
    }
    return false;
}

/**
 * Bans a user
 * can make the difference between UUID and username
 * can make the difference between websend and wordpress
 *
 * @param type $user
 */
function umc_user_ban($user, $reason) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_ENV, $UMC_USER;

    $U = umc_uuid_getboth($user);
    $uuid = $U['uuid'];
    $username = $U['username'];

    $cmd = "ban $username $reason";
    if ($UMC_ENV == 'websend') {
        umc_ws_cmd($cmd, 'asConsole', false, false);
        $admin = $UMC_USER['username'];
    } else {
        umc_exec_command($cmd, 'asConsole', false);
        $admin = 'wordpress';
    }
    $sql = "INSERT INTO minecraft_srvr.`banned_users`(`username`, `reason`, `admin`, `uuid`) VALUES ('$username','$reason', '$admin', '$uuid');";
    umc_mysql_query($sql, true);
    // remove shop inventory
    umc_shop_cleanout_olduser($uuid);
    // remove from teamspeak
    umc_ts_clear_rights($uuid);

    umc_log('mod', 'ban', "$admin banned $username/$uuid because of $reason");
    XMPP_ERROR_send_msg("$admin banned $username because of $reason");
}


function umc_user_directory() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    // list all users
    if (isset($_GET['u'])) {
        $username_get = filter_var($_GET['u'], FILTER_SANITIZE_STRING);

        $wordpress_id = umc_user_get_wordpress_id($username_get);
        $username = strtolower(umc_check_user($username_get));
        if (!$wordpress_id) {
            return "User does not exist!";
        }

        // user icon
        echo get_avatar($wordpress_id, $size = '96');
        echo "<br><strong>Username:</strong> $username<br>";
        $uuid = umc_user2uuid($username);
        echo "<br><strong>UUID:</strong> $uuid<br>";

        // is user banned?
        if (umc_user_is_banned($uuid)) {
            echo "<strong>User is BANNED!</strong><br>";
            return;
        } else {
            umc_promote_citizen($username);
            umc_donation_level($username);
        }

        // get userlevel
        $level = umc_get_userlevel($username);
        echo "<strong>Level:</strong> $level<br>";

        $karma = umc_getkarma($uuid, true);
        echo "<strong>Karma:</strong> $karma<br>";

        $money = umc_money_check($uuid);
        echo "<strong>Money:</strong> $money Uncs<br>";

        // get lots
        $lots = umc_user_getlots($uuid);
        echo "<strong>Lots:</strong><br>    ";
        foreach ($lots as $lot => $data) {
            echo $data['lot'] . "<br>" . $data['image'] . "<br>";
        }

        $donator_level = umc_users_donators($uuid);
        if ($donator_level > 12) {
            $donator_str = 'More than 1 year';
        } else if ($donator_level) {
            $donator_level_rounded = round($donator_level, 1);
            $donator_str = "$donator_level_rounded Months";
        } else {
            $donator_str = "Not a donator";
        }
        echo "<strong>Donations remaining:</strong> $donator_str<br>";

        // get member since
        $online_time = umc_get_lot_owner_age('days', $username);
        if ($online_time) {
            $lastlogin = $online_time[$username]['lastlogin']['days'];
            $firstlogin = $online_time[$username]['firstlogin']['days'];
            echo "<strong>Member since:</strong> $firstlogin days<br>";
            echo "<strong>Offline since:</strong> $lastlogin days<br>";
        }
        // get user bio
        $sql = "SELECT meta_value FROM minecraft.wp_users
            LEFT JOIN minecraft.wp_usermeta ON wp_users.ID = wp_usermeta.user_id
            WHERE display_name='$username' AND meta_key='description';";
        $rst = mysql_query($sql);
        if (mysql_num_rows($rst) > 0) {
            $row = mysql_fetch_array($rst, MYSQL_ASSOC);
            echo "<strong>Bio:</strong> " . $row['meta_value'] . "<br>";
        }


        // comments
        $sql = "SELECT comment_date, comment_author, id, comment_id, post_title FROM minecraft.wp_comments
            LEFT JOIN minecraft.wp_posts ON comment_post_id=id
            WHERE comment_author = '$username' AND comment_approved='1' AND id <> 'NULL'
            ORDER BY comment_date DESC";
        $rst = mysql_query($sql);
        echo "<strong>Comments:</strong> (". mysql_num_rows($rst) . ")\n<ul>\n";
        while ($row = mysql_fetch_array($rst, MYSQL_ASSOC)) {
            echo "<li>" . $row['comment_date'] . " on <a href=\"/index.php?p=" . $row['id'] . "#comment-" . $row['comment_id'] . "\">" . $row['post_title'] . "</a></li>\n";
        }
        echo "</ul>\n";

        //forum posts
        $sql = "SELECT wpp.id AS id, wpp.post_title AS title, wpp.post_date AS date,
		wpp.post_parent AS parent, wpp.post_type AS type, parent.post_title AS parent_title
            FROM minecraft.wp_posts AS wpp
	    LEFT JOIN minecraft.wp_users ON wpp.post_author=wp_users.id
	    LEFT JOIN minecraft.wp_posts AS parent ON parent.id=wpp.post_parent
	    WHERE wp_users.display_name='$username'
		AND (wpp.post_type='reply' OR wpp.post_type='topic')
		AND wpp.post_status='publish'
	    ORDER BY wpp.post_date DESC";
        $rst = mysql_query($sql);
        // echo $sql;
        echo "<strong>Forum Posts:</strong> (". mysql_num_rows($rst) . ")\n<ul>\n";
        while ($row = mysql_fetch_array($rst, MYSQL_ASSOC)) {
            $date = $row['date'];
            if ($row['type'] == 'reply') {
                $link = $row['parent'] . "#post-" . $row['id'];
                $title = $row['parent_title'];
            } else {
                $link = $row['id'];
                $title = $row['title'];
            }
            echo "<li>$date on <a href=\"/index.php?p=$link\">$title</a></li>";
        }
        echo "</ul>\n";
    } else {
        // $bans = umc_get_banned_users();
        //var_dump($bans);
        $out = "<script type=\"text/javascript\" src=\"/admin/js/jquery.dataTables.min.js\"></script>\n"
            . "<script type=\"text/javascript\">\n"
            .'jQuery(document).ready(function() {jQuery'. "('#shoptable_users').dataTable( {\"order\": [[ 2, \"desc\" ]],\"paging\": false,\"ordering\": true,\"info\": true} );;} );\n"
            . "</script>\n"
            . "This table only tracks online time since 2013-11-20.<br>"
            . '<table id="shoptable_users"><thead>'
            . "<th>Username</th>"
            . "<th>Level</th>"
            . "<th>Registered days</th>"
            . "<th>Offline days</th>"
            . "<th>Lots</th>"
            . "<th>Online min/day</th>"
            . "<th>Online hrs</th>"
            . "</thead>\n<tbody>\n";

        $sql = "SELECT username, DATEDIFF(NOW(),firstlogin) as registered_since, parent as userlevel, count(owner) as lot_count, onlinetime, DATEDIFF(NOW(), lastlogin) as days_offline
            FROM minecraft_srvr.UUID
            LEFT JOIN minecraft_srvr.permissions_inheritance ON UUID.uuid=child
            LEFT JOIN minecraft_worldguard.user ON UUID.uuid = user.uuid
            LEFT JOIN minecraft_worldguard.region_players ON user.id=region_players.user_id
            WHERE owner = 1 AND firstlogin >'0000-00-00 00:00:00' AND username <> '_abandoned_'
            GROUP BY username, owner
            ORDER BY firstlogin";
        $rst = umc_mysql_query($sql);

        $now = time(); // or your date as well
        $your_date = strtotime("2013-11-20");
        $datediff = $now - $your_date;
        $alt_days = floor($datediff/(60*60*24));

        while ($row = umc_mysql_fetch_array($rst)) {
            $days_offline = $row['days_offline'];
            $settler_levels = array('Settler', 'SettlerDonator', 'SettlerDonatorPlus');
            if (in_array($row['userlevel'], $settler_levels) && $row['onlinetime'] >= 60) {
                umc_promote_citizen(strtolower($row['username']), $row['userlevel']);
            }
            if (($row['registered_since'] - $days_offline) > 1) {
                if ($alt_days < $row['registered_since']) {
                    // people who are not in the lb-players database should not be listed, they are too old
                    if (($alt_days - $days_offline) == 0) {
                        continue;
                    }

                    $avg_online = floor(($row['onlinetime'] / 60) / $alt_days);
                } else {
                    $avg_online = floor(($row['onlinetime'] / 60) / $row['registered_since']);
                }
            } else {
                $avg_online = 0;
            }
            $online_total = round($row['onlinetime'] / 60 / 60);
            $icon_url = umc_user_get_icon_url($row['username']);
            $out .= "<tr>"
                . "<td><img title='{$row['username']}' src='$icon_url' alt=\"{$row['username']}\"> <a href=\"?u={$row['username']}\">{$row['username']}</a></td>"
                . "<td>{$row['userlevel']}</td>"
                . "<td class='numeric_td'>{$row['registered_since']}</td>"
                . "<td class='numeric_td'>$days_offline</td>"
                . "<td class='numeric_td'>{$row['lot_count']}</td>"
                . "<td class='numeric_td'>$avg_online</td>"
                . "<td class='numeric_td'>$online_total</td>"
                . "</tr>\n";
        }
        $out .= "</tbody>\n</table>\n";
        echo $out;
    }
}

/**
 * retrieves a list of banned users, either usernames or UUIDs, depending on the format
 * $format is either 'username' or 'uuid';
 *
 * @return array
 */
function umc_banned_users() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $sql = "SELECT username, uuid FROM minecraft_srvr.banned_users;";
    $D = umc_mysql_fetch_all($sql);
    $users = array();
    foreach ($D as $row) {
        $uuid = $row['uuid'];
        $username = $row['username'];
        $users[$uuid] = $username;
    }
    return $users;
}

/*
 * this function makes sure that all banned users from the text file
 * are actually in the database
 */
function umc_ban_to_database() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    die();
    global $UMC_SETTING;
    $ban_file = json_decode(file($UMC_SETTING['banned_players_file']));
    $banned_db = umc_banned_users();
    foreach ($ban_file as $D) {
        $uuid = $D['uuid'];
        $name = strtolower($D['name']);
        $date = $D['created'];
        $source = $D['source'];
        $reason = $D['reason'];
        $admin = $D['admin'];
        if (!in_array($uuid, $banned_db)) {
            $sql = "INSERT INTO minecraft_srvr.`banned_users`(`username`, `reason`, `admin`, `date`, `uuid`, `source`)
                VALUES ('$name','$reason', '$admin', '$date', '$uuid', '$source');";
            mysql_query($sql);
        }
    }
    /* format:
     *   {
    "uuid": "18d29691-51f1-4166-b2cb-46cab2b9fba0",
    "name": "iLoveMCPigs",
    "created": "2013-12-20 11:00:54 +0800",
    "source": "(Unknown)",
    "expires": "forever",
    "reason": "Banned by an operator."
  },

     */
}


/*
 * returns all lot onwners with their first and last login dates
 * if format = string returns date years, months, days, hours etc
 * else returns days count
 */
function umc_get_lot_owner_age($format = 'string', $oneuser = false, $debug  = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $playerfilter = "";
    if ($oneuser) {
        $uuid = umc_uuid_getone($oneuser, 'uuid');
        $playerfilter = " AND UUID.uuid = '$uuid'";
    }

    $sql = "SELECT username, lastlogin, firstlogin, onlinetime
        FROM minecraft_srvr.UUID
        LEFT JOIN minecraft_worldguard.user ON user.uuid=UUID.uuid
        LEFT JOIN minecraft_worldguard.region_players ON id=user_id
        WHERE owner=1 $playerfilter
        GROUP BY username, lastlogin
        ORDER BY lastlogin ASC";
    $R = umc_mysql_fetch_all($sql);
    $users = array();
    $diff_steps = array('y'=>'years','m'=>'months','d'=>'days','h'=>'hours','i'=>'minutes','s'=>'seconds');

    /*
     *
        $now = time(); // or your date as well
        $your_date = strtotime("2013-11-20");
        $datediff = $now - $your_date;
        $alt_days = floor($datediff/(60*60*24));
     */


    // umc_error_notify("Could not get player age: $sql");
    if (count($R) == 0) {
        // umc_error_notify("Error to get user age: $sql");
        return false;
    }

    foreach ($R as $row) {
        $username = strtolower($row['username']);
        $last_time = $row['lastlogin'];
        /* if (!function_exists('umc_datetime')) {
            umc_error_longmsg("umc_Datetime not found ($sql)");
            include_once("$UMC_PATH_MC/server/bin/includes/timer.inc.php");
            if (!function_exists('umc_datetime')) {
                umc_error_longmsg("Datetime function not found (level2)");
            }

        }*/
        $last_datetime = umc_datetime($last_time);
        $first_time =  $row['firstlogin'];
        if ($debug) {echo "First_time = $first_time\n";}
        $first_datetime = umc_datetime($first_time);
        if ($debug) {echo "First_datetime = " . var_export($first_datetime, true);}
        if ($format == 'string') {
            $last_diff = umc_timer_array_diff($last_datetime);
            $first_diff = umc_timer_array_diff($first_datetime);
            foreach ($diff_steps as $code => $text) {
                $last_val = $last_diff->$code;
                $users[$username]['lastlogin'][$text] = $last_val;

                $first_val = $first_diff->$code;
                $users[$username]['firstlogin'][$text] = $first_val;
            }
        } else { // days
            $first_seconds = umc_timer_raw_diff($first_datetime);
            if ($debug) {echo "First_seconds = " . var_export($first_seconds, true);}
            $first_days = round($first_seconds / 60 / 60 / 24);
            $users[$username]['firstlogin']['days'] = $first_days;
            $users[$username]['firstlogin']['seconds'] = $first_seconds;
            $last_seconds = umc_timer_raw_diff($last_datetime);
            $last_days = round($last_seconds / 60 / 60 / 24);
            $users[$username]['lastlogin']['days'] = $last_days;
            $users[$username]['lastlogin']['seconds'] = $last_seconds;
        }
        $users[$username]['lastlogin']['full'] = $last_time;
        $users[$username]['firstlogin']['full'] = $first_time;
        $users[$username]['onlinetime']['seconds'] = $row['onlinetime'];
        $users[$username]['onlinetime']['days'] = round($row['onlinetime'] / 60 /60 / 24);
    }
    return $users;
}

/**
 * Get the hours a user was online
 *
 * @param string $user / either user or uuid
 * @return string/boolean (false if user is not found)
 */
function umc_get_online_hours($user) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    $uuid = umc_uuid_getone($user, 'uuid');
    $sql = "SELECT onlinetime FROM minecraft_srvr.UUID WHERE UUID='$uuid';";
    $data = umc_mysql_fetch_all($sql);
    if (count($data) == 0) {
        return false;
    }
    $onlinetime = round($data[0]['onlinetime'] / 60 / 60);
    return $onlinetime;
}

/**
 * promotes a user to Citizen if applicable
 *
 * @param type $user_login
 * @param type $userlevel
 * @return type
 */
function umc_promote_citizen($username, $userlevel = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    if (!$userlevel) {
        $userlevel = umc_get_userlevel($username);
    }
    $lower_username = strtolower($username);
    $settlers = array('Settler', 'SettlerDonator', 'SettlerDonatorPlus');
    if (in_array($userlevel, $settlers)) {
        /*
        $age = umc_get_lot_owner_age('array', $lower_login);
        if (!$age) {
            return;
        }
        $age_days = $age[$lower_login]['firstlogin']['days'];
        if ($age_days >= 90) {
        *
        */
        $online_hours = umc_get_online_hours($lower_username);
        if ($online_hours >= 60) {
            //user should be Citizen
            $uuid = umc_user2uuid($lower_username);
            if ($userlevel == 'Settler') {
                // pex user <user> group set <group>
                umc_exec_command("pex user $uuid group set Citizen");
                umc_log("users", "promotion", "User $username ($uuid) was promoted from $userlevel to Citizen (online hours: $online_hours)");
            } else if ($userlevel == 'SettlerDonator') {
                umc_exec_command("pex user $uuid group set CitizenDonator");
                umc_log("users", "promotion", "User $username ($uuid) was promoted from $userlevel to CitizenDonator (online: $online_hours)");
            } else if ($userlevel == 'SettlerDonatorPlus') {
                umc_exec_command("pex user $uuid group set CitizenDonatorPlus");
                umc_log("users", "promotion", "User $username ($uuid) was promoted from $userlevel to CitizenDonatorPlus (online: $online_hours)");
            } else {
                XMPP_ERROR_trigger("$username / $uuid has level $userlevel and could not be promoted to Citizen! Please report to admin!");
            }
        }
    }
}

 function umc_get_userinfo($user_raw) {
    $username = strtolower($user_raw);
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
     // get registration date from Wordpress

    $uuid = umc_user2uuid($username);
    $user['uuid'] = $uuid;

    // get userlevel, balance, onlinetime
    $sql = "SELECT userlevel, onlinetime, lastlogin, lastlogout, username, UUID.UUID, balance, lot_count, firstlogin
        FROM minecraft_srvr.UUID
        LEFT JOIN minecraft_iconomy.mineconomy_accounts ON UUID.`UUID` = mineconomy_accounts.uuid
        WHERE UUID.`UUID` = '$uuid'";

    $D = umc_mysql_fetch_all($sql);
    $d = $D[0];
    if ($d['userlevel'] == null) {
        $level = 'Guest';
    } else {
        $level = $d['userlevel'];
    }
    $user['Level'] = $level;
    $user['Last Seen'] = $d['lastlogin'];

    if ($d['onlinetime'] == NULL) {
        $online_time = "n/a";
    } else {
        $online_time = umc_seconds_to_time($d['onlinetime']);
    }

    $firstdate = substr($d['firstlogin'],0,10);
    $today_ts = strtotime("now");
    $firsttime_ts = strtotime($firstdate);
    $days = round(abs($today_ts-$firsttime_ts)/60/60/24);
    $user['User since'] = "$firstdate ($days days)";

    if ($firstdate > '2013-11-20') {// not all play time recorded
        $user['Online time since 2013-11-20'] = $online_time;
    } else {

        $user['Online time'] = $online_time;
    }
    if ($d['balance'] == NULL) {
        $user['Uncs'] = '0.00';
    } else {
        $user['Uncs'] = number_format($d['balance'], 2, ".", "'");
    }

    $user['First login'] = $d['firstlogin'];

    $karma = umc_getkarma($user['uuid'], true);
    $user['Karma'] = $karma;

    $lots = umc_user_getlots($uuid);
    $display_lots = array();
    foreach ($lots as $lot => $data) {
        $display_lots[$data['world']][] = $lot;
    }

    foreach ($display_lots as $world => $lots) {
        $World = ucfirst($world);
        if (count($lots) < 5) {
            $user["$World lots"] = implode(", ", $lots);
        } else {
            $user["$World lots"] = count($lots) . " lots";
        }

    }
    return $user;
 }
