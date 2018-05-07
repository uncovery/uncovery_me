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
 * This file manages several central aspects of users, creates several different
 * lists of users and other user information. Some of these functions should be
 * relocated to uuid.inc.php
 */

global $UMC_FUNCTIONS;
$UMC_FUNCTIONS['update_usericons'] = 'umc_update_usericons';


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
 * checks if a user is active or not
 *
 * @param type $uuid
 */
function umc_users_is_active($uuid) {
    $sql_uuid = umc_mysql_real_escape_string($uuid);
    $sql = "SELECT lot_count FROM minecraft_srvr.UUID WHERE UUID=$sql_uuid AND lot_count > 0;";
    $D = umc_mysql_fetch_all($sql);
    if (count($D) > 0) {
        return true;
    }
    return false;
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
 * returns a list of all users that own lots.
 * The output can be either "name" or "counter" to output either
 * the username or the lot count. The key is always the UUID
 *
 * @param type $output
 * @return type
 */
function umc_get_active_members($output = 'name') {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $active_members = array();
    $sql = "SELECT user.uuid as uuid, lower(username) as name, count(region_players.region_id) as counter
        FROM minecraft_worldguard.region_players
        LEFT JOIN minecraft_worldguard.user ON user_id=id
        LEFT JOIN minecraft_srvr.UUID ON user.uuid=UUID.UUID
        WHERE owner=1 AND user.uuid IS NOT NULL AND username IS NOT NULL
        GROUP BY uuid ORDER BY name";
    $data = umc_mysql_fetch_all($sql);
    foreach ($data as $row) {
        $active_members[$row['uuid']] = $row[$output];
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
        $uuid = $username;
        $sql = "SELECT display_name FROM minecraft.wp_usermeta
            LEFT JOIN minecraft.wp_users ON ID=user_id
            WHERE meta_key = 'minecraft_uuid' AND meta_value LIKE $username_quoted;";
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
/**
 * this function is deprecated and moved to the lots plugin
 * IF you find this function to be used anywhere, please point to the new function instead.
 * Also please do not update/change this, instead use the new function in lot.inc.php
 *
 * @param type $uuid
 * @param type $world
 * @return type
 */
function umc_user_getlots($uuid, $world = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    // worldguard stores everything in lower case.
    $filter = '';
    if ($world) {
        if (is_array($world)){
            $worlds = implode("','", $world);
            $filter = "AND world.name IN('$worlds')";
        } else {
            $filter = "AND world.name = '$world'";
        }
    }

    $sql = "SELECT region_id, world.name FROM minecraft_worldguard.`region_players`
        LEFT JOIN minecraft_worldguard.user ON user_id = user.id
        LEFT JOIN minecraft_worldguard.world ON world_id = world.id
        WHERE Owner=1 AND uuid='$uuid' $filter ORDER BY region_id;";
    $R = umc_mysql_fetch_all($sql);
    $out = array();
    //echo $sql;
    foreach ($R as $row) {
        $link = umc_lot_get_tile($row['region_id'], $row['name']);
        if (!$link) {
            $link = '';
        }
        $lot = $row['region_id'];
        $out[$lot] = array('world' => $row['name'], 'lot' => $lot, 'image' => $link);
    }
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
    $D = umc_mysql_fetch_all($sql);
    $out = $D[0]['counter'];
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
    return "<img class=\"lot_tile\" src=\"$UMC_DOMAIN/map/lots/$world/$lot.png\" alt=\"$lot\">";
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
    $D = umc_mysql_fetch_all($sql);
    $out = array();
    foreach ($D as $row) {
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
    umc_plugin_eventhandler('user_banned', $uuid);
    umc_wp_ban_user($uuid);

    umc_log('mod', 'ban', "$admin banned $username/$uuid because of $reason");
    XMPP_ERROR_send_msg("$admin banned $username because of $reason");
}


function umc_user_directory() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    // list all users
    $username_get = filter_input(INPUT_GET, 'u', FILTER_SANITIZE_STRING);
    if (!is_null($username_get)) {
        $O = array();

        $wordpress_id = umc_user_get_wordpress_id($username_get);
        $username = strtolower(umc_check_user($username_get));
        if (!$wordpress_id) {
            return "User does not exist!";
        }
        $uuid = umc_user2uuid($username);
        // check if the user is active
        $count_lots = umc_user_countlots($uuid);
        if ($count_lots == 0) {
            return "User is not active!";
        }

        // user icon
        $O['User'] = get_avatar($wordpress_id, $size = '96')
            . "<p><strong>Username:</strong> $username</p>\n"
            . "<p><strong>UUID:</strong> $uuid</p>\n";

        $previous_names = umc_uuid_username_history($uuid);
        if ($previous_names) {
            $O['User'] .=  "<p><strong>Usernames History:</strong> $previous_names</p>\n";
        }

        // is user banned?
        if (umc_user_is_banned($uuid)) {
            $O['User'] .= "<p><strong>User is BANNED!</strong></p>\n";
            return;
        }

        // get userlevel
        $level = umc_userlevel_get($uuid);
        $karma = umc_getkarma($uuid, true);
        $money = umc_money_check($uuid);

        $O['User'] .= "<p><strong>Level:</strong> $level</p>\n"
            . "<p><strong>Karma:</strong> $karma</p>\n"
            . "<p><strong>Money:</strong> $money Uncs</p>\n";

        // get lots
        $lots = umc_user_getlots($uuid);
        foreach ($lots as $data) {
            $world = ucwords($data['world']);
            $combined_worlds = array('Empire', 'Flatlands', 'Skyblock');
            if (in_array($world, $combined_worlds)) {
                $world = 'Small lots';
            }
            if (!isset($O[$world])) {
                $O[$world] = '';
            }
            $O[$world] .= $data['image'];
        }

        // get member since
        $online_time = umc_get_lot_owner_age('days', $uuid);
        if ($online_time) {
            $lastlogin = $online_time[$uuid]['lastlogin']['days'];
            $firstlogin = $online_time[$uuid]['firstlogin']['days'];
            $O['User'] .= "<p><strong>Member since:</strong> $firstlogin days</p>\n"
                . "<p><strong>Offline since:</strong> $lastlogin days</p>\n";
        }
        // get user bio
        $sql = "SELECT meta_value FROM minecraft.wp_users
            LEFT JOIN minecraft.wp_usermeta ON wp_users.ID = wp_usermeta.user_id
            WHERE display_name='$username' AND meta_key='description';";
        $D = umc_mysql_fetch_all($sql);
        if (count($D) > 0) {
            $row = $D[0];
            $O['User'] .= "<p><strong>Bio:</strong> " . $row['meta_value'] . "</p>\n";
        }

        /** //TODO: This has to be updated to show the forum posts of the new forum
        //forum posts
        $sql3 = "SELECT wpp.id AS id, wpp.post_title AS title, wpp.post_date AS date,
		wpp.post_parent AS parent, wpp.post_type AS type, parent.post_title AS parent_title
            FROM minecraft.wp_posts AS wpp
	    LEFT JOIN minecraft.wp_users ON wpp.post_author=wp_users.id
	    LEFT JOIN minecraft.wp_posts AS parent ON parent.id=wpp.post_parent
	    WHERE wp_users.display_name='$username'
		AND (wpp.post_type='reply' OR wpp.post_type='topic')
		AND wpp.post_status='publish'
	    ORDER BY wpp.post_date DESC";
        $D3 = umc_mysql_fetch_all($sql3);
        // echo $sql;
        if (count($D3) > 0) {
            $O['Forum'] = "<strong>Forum Posts:</strong> (". count($D3) . ")\n<ul>\n";
            foreach ($D3 as $row) {
                $date = $row['date'];
                if ($row['type'] == 'reply') {
                    $link = $row['parent'] . "#post-" . $row['id'];
                    $title = $row['parent_title'];
                } else {
                    $link = $row['id'];
                    $title = $row['title'];
                }
                $O['Forum'] .= "<li>$date on <a href=\"/index.php?p=$link\">$title</a></li>\n";
            }
            $O['Forum'] .= "</ul>\n";


        } */
        $ret = umc_plugin_eventhandler('user_directory', array('uuid' => $uuid, 'username' => $username, 'first_join' => $online_time[$uuid]['firstlogin']['full']));
        foreach ($ret as $plugin_content) {
            foreach ($plugin_content as $section => $text) {
                // initialize the section in case the plugin created it
                if (!isset($O[$section])) {
                    $O[$section] = '';
                }
                // add the content from the plugin
                $O[$section] .= $text;
            }
        }

        echo umc_jquery_tabs($O);
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
            . "<th>Voting ratio</th>"
            . "</thead>\n<tbody>\n";

        $sql = "SELECT UUID.uuid as uuid, username, DATEDIFF(NOW(),firstlogin) as registered_since, parent as userlevel, count(owner) as lot_count, onlinetime, DATEDIFF(NOW(), lastlogin) as days_offline
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
            $vote_stats = umc_lottery_stats($row['uuid']);
            $settler_levels = array('Settler', 'SettlerDonator');
            if (in_array($row['userlevel'], $settler_levels) && $row['onlinetime'] >= 60) {
                umc_promote_citizen($row['uuid'], $row['userlevel']);
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
                . "<td><img title='{$row['username']}' src='$icon_url' alt=\"{$row['username']}\">&nbsp;<a href=\"?u={$row['username']}\">{$row['username']}</a></td>"
                . "<td>{$row['userlevel']}</td>"
                . "<td class='numeric_td'>{$row['registered_since']}</td>"
                . "<td class='numeric_td'>$days_offline</td>"
                . "<td class='numeric_td'>{$row['lot_count']}</td>"
                . "<td class='numeric_td'>$avg_online</td>"
                . "<td class='numeric_td'>$online_total</td>"
                . "<td class='numeric_td'>$vote_stats</td>"
                . "</tr>\n";
        }
        $out .= "</tbody>\n</table>\n";
        $out .= "Voting Ratio: This shows how often a user has voted for the server on server lists
            compared to how often they logged in within the last 30 days. So if a user was here on one day and voted on one day, they get a 1.
            If someone did not login at all, they get a \"n/a\". If someone logged in on 4 days but voted only once, they get a 0.25";
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

    //temp fix for wrong userlogin
    umc_uuid_firstlogin_update($uuid);

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
                $users[$uuid]['lastlogin'][$text] = $last_val;

                $first_val = $first_diff->$code;
                $users[$uuid]['firstlogin'][$text] = $first_val;
            }
        } else { // days
            $first_seconds = umc_timer_raw_diff($first_datetime);
            if ($debug) {echo "First_seconds = " . var_export($first_seconds, true);}
            $first_days = round($first_seconds / 60 / 60 / 24);
            $users[$uuid]['firstlogin']['days'] = $first_days;
            $users[$uuid]['firstlogin']['seconds'] = $first_seconds;
            $last_seconds = umc_timer_raw_diff($last_datetime);
            $last_days = round($last_seconds / 60 / 60 / 24);
            $users[$uuid]['lastlogin']['days'] = $last_days;
            $users[$uuid]['lastlogin']['seconds'] = $last_seconds;
        }
        $users[$uuid]['lastlogin']['full'] = $last_time;
        $users[$uuid]['firstlogin']['full'] = $first_time;
        $users[$uuid]['onlinetime']['seconds'] = $row['onlinetime'];
        $users[$uuid]['onlinetime']['days'] = round($row['onlinetime'] / 60 /60 / 24);
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
 * for various functions (in-game info & web userlist)
 *
 * @param type $user_raw
 * @return string
 */
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
    $username_history = umc_uuid_username_history($uuid);
    if ($username_history) {
        $user['Username History'] = $username_history;
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

    if ($firstdate < '2013-11-20') {// not all play time recorded
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

    $homes_count = umc_home_count(false, $uuid);
    $user['Homes count'] = $homes_count;

    $karma = umc_getkarma($user['uuid'], true);
    $user['Karma'] = $karma;

    $vote_ratio = umc_lottery_stats($user['uuid']);
    $user['Vote Ratio'] = $vote_ratio;

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
