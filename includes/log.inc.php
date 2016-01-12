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
 * This manages several different logfiles as they are created by plugins.
 * IT includes several viewing modules for the website so users can lookup
 * events there.
 */
function umc_error_log() {
    global $UMC_DOMAIN;
    $s_post  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
    if (isset($s_post['delete'])) {
        $del_id = $s_post['delete'];
        $sql_find = "SELECT * FROM minecraft_log.`error_log` WHERE error_id=$del_id;";
        $rst_find = umc_mysql_query($sql_find);
        $f_row = umc_mysql_fetch_array($rst_find);
        $file = umc_mysql_real_escape_string($f_row['file']);
        $sql_del = "DELETE FROM minecraft_log.`error_log`
            WHERE `type`='{$f_row['type']}'
		AND `message`='{$f_row['message']}'
		AND `line`='{$f_row['line']}'
		AND `file`=$file;";
        $rst_del = umc_mysql_query($sql_del);
        umc_mysql_free_result($rst_del);
        umc_mysql_free_result($rst_find);
    }

    $sql = "SELECT min(error_id) AS sample, count(error_id) AS freq, `type`,`message`,`line`,`file`,`referer`,max(`datetime`) AS latest
        FROM minecraft_log.`error_log`
	GROUP BY type, file, line, message
	ORDER BY freq DESC ";
    $rst = umc_mysql_query($sql);
    $out = "<form class=\"shoptables\" action=\"$UMC_DOMAIN/error-log/\" method=\"POST\" style=\"font-size:80%\">\n"
        . "<table>\n<tr><th>Freq</th><th>Type</th><th>Message</th><th>Line</th><th>File</th><th>Date</th><th><input type=\"submit\" name=\"submit\" value=\"Solved\"></th></tr>\n";

    while ($row = umc_mysql_fetch_array($rst)) {
        $path = substr($row['file'], 15);
        $out .= "<tr><td>{$row['freq']}</td><td>{$row['type']}</td><td>{$row['message']}</td><td>{$row['line']}</td><td>$path</td><td>{$row['latest']}</td>"
            . "<td><input type=\"radio\" name=\"delete\" value=\"{$row['sample']}\"></tr>\n";
    }
    $out .= "</table>\n</form>\n";
    umc_mysql_free_result($rst);
    return $out;
}

function umc_log($plugin, $action, $text) {
    global $UMC_USER;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    if (isset($UMC_USER['username'])) {
        $player = $UMC_USER['username'];
    } else {
        $player = 'system';
    }

    /*
     * File log
     */
    //$logfolder = "/home/minecraft/server/logs/";
    //$logfile = $logfolder . $plugin . ".log";
    //$date_now = umc_datetime();
    //$now = $date_now->format("Y-m-d H:i:s");
    //$logtext = "$now | $action | $text\n";
    //file_put_contents($logfile, $logtext, FILE_APPEND);
    /*
     * database log
     */
    $sqlaction = umc_mysql_real_escape_string($action);
    $sqltext = umc_mysql_real_escape_string($text);
    $sql = "INSERT INTO `minecraft_log`.`universal_log` (`log_id`, `date`, `time`, `plugin`, `username`, `action`, `text`)
        VALUES (NULL, CURRENT_DATE(), CURRENT_TIME(),'$plugin', '$player', $sqlaction, $sqltext);";
    umc_mysql_query($sql, true);
}

// this returns the different types of plugin data in the log database
function umc_log_get_plugin_types() {
    $sql = "SELECT plugin FROM `minecraft_log`.`universal_log` GROUP BY plugin;";
    $D = umc_mysql_fetch_all($sql);

    $plugins = array();
    foreach ($D as $row) {
        $plugins[] = $row['plugin'];
    }
    return $plugins;
}

// this returns the different users of plugin data in the log database
function umc_log_get_usernames() {
    $sql = "SELECT username FROM `minecraft_log`.`universal_log` GROUP BY username;";
    $D = umc_mysql_fetch_all($sql);

    $usernames = array();
    foreach ($D as $row) {
        $usernames[] = $row['username'];
    }
    return $usernames;
}

function umc_log_web_display() {
    global $UMC_USER, $UMC_DOMAIN;
    $out = '';
    if (!$UMC_USER) {
        $out = "Please <a href=\"$UMC_DOMAIN/wp-login.php\">login</a>!";
        return $out;
    } else {
        $username = $UMC_USER['username'];
    }
    if ($username !== 'uncovery' && $username !== 'azkedar') {
        return "This page is admin-only!";
    }

    $plugins = umc_log_get_plugin_types();
    $usernames = umc_log_get_usernames();
    $post_plugin = filter_input(INPUT_POST, 'plugin', FILTER_SANITIZE_STRING);
    $post_username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);

    $plugin_filter = '';
    if (isset($post_plugin) && $post_plugin != 'none') {
        if (in_array($post_plugin, $plugins)) {
            $plugin_filter = "AND plugin='$post_plugin'";
        } else {
            $out .= "<h2>Plugin cannot be found!</h2>";
        }
    }

    $username_filter = '';
    if (isset($post_username) && $post_username != 'none') {
        if (in_array($post_username, $usernames)) {
            $username_filter = "AND username='$post_username'";
        } else {
            $out .= "<h2>User cannot be found!</h2>";
        }
    }

    $out .= "<form action=\"\" method=\"post\">\n"
        . "<span>Plugin: <select name=\"plugin\"><option value=\"none\">All</option>";
    foreach ($plugins as $one_plugin) {
        $out .= "<option value=\"$one_plugin\">$one_plugin</option>";
    }
    $out .= "</select>";

    $out .= "</span><span> User: <select name=\"username\"><option value=\"none\">All</option>";
    foreach ($usernames as $one_username) {
        $out .= "<option value=\"$one_username\">$one_username</option>";
    }
    $out .= "</select><input type=\"submit\" name=\"proposebutton\" value=\"Check\"></span>"
        . "<input type=\"submit\" name=\"today\" value=\"Today only\"></form>";

    if (isset($_POST['today'])) {
        $sql = "SELECT * FROM minecraft_log.universal_log WHERE date=CURRENT_DATE() ORDER BY `date` DESC, `time` DESC;";
    } else {
        $sql = "SELECT * FROM minecraft_log.universal_log WHERE 1 $plugin_filter $username_filter ORDER BY `date` DESC, `time` DESC LIMIT 1000;";
    }

    // $out .= "$sql";
    $D = umc_mysql_fetch_all($sql);
    $out .= "<table style=\"font-size:80%\" class=\"log_table\">\n<tr><th>ID</th><th>Date</th><th>Time</th><th>Username</th><th>Plugin</th><th>Action</th><th>Text</th></tr>\n";
    $yesterday = '';
    foreach ($D as $row) {
        $row_style = '';
        if ($yesterday != $row['date']) {
            $row_style = ' style="background-color:#CCCCCC;"';
        }
        $out .="<tr$row_style><td>{$row['log_id']}</td><td>{$row['date']}</td><td>{$row['time']}</td><td>{$row['username']}</td><td>{$row['plugin']}</td><td>{$row['action']}</td><td>{$row['text']}</td></tr>";
	$yesterday = $row['date'];
    }
    $out .= "</table>\n";

    return $out;

}


function umc_display_logores() {
    global $UMC_DOMAIN, $UMC_USER;
    $out = '';
    if (!$UMC_USER) {
        $out = "Please <a href=\"$UMC_DOMAIN/wp-login.php\">login</a>!";
        return $out;
    } else {
        $username = $UMC_USER['username'];
    }
    if ($username !== 'uncovery') {
        return "This page is admin-only!";
    }

    $player = '';
    if (isset($_POST['player']) && !isset($_POST['nolight'])) {
        $player = filter_input(INPUT_POST, 'player', FILTER_SANITIZE_STRING);
        $player = umc_check_user($player);
        if (!$player) {
            $out .= "<h2>Player cannot be found!</h2>";
            $player = '';
        }
    }

    $out .= "<form action=\"\" method=\"post\">\n"
        . "<span>Person to check: <input type=\"text\" name=\"player\" value=\"$player\"> "
        . "<input type=\"submit\" name=\"proposebutton\" value=\"Check\">"
        . "<input type=\"submit\" name=\"nolight\" value=\"All No-light\">"
        . "<input type=\"submit\" name=\"alerts\" value=\"High alert\">"
        . "<input type=\"submit\" name=\"latest1000\" value=\"Last 1000\">"
        . "</span></form>";

    if (isset($_POST['latest1000'])) {
        $sql = "SELECT * FROM minecraft_log.logores_log AND world NOT LIKE 'flatlands' ORDER BY `date` DESC LIMIT 1000;";
        $banned_users = umc_get_banned_users();
    } else if (isset($_POST['nolight'])) {
        $sql = "SELECT * FROM minecraft_log.logores_log WHERE light='0' AND y<60 AND world NOT LIKE 'flatlands' ORDER BY `date` DESC LIMIT 1000;";
        $banned_users = umc_get_banned_users();
    } else if (isset($_POST['alerts'])) {
        $sql = "SELECT * FROM minecraft_log.logores_log WHERE INSTR(flagged, 'cave') = 0 AND LENGTH(flagged) > 0 AND y<60 AND world NOT LIKE 'flatlands' ORDER BY `date` DESC LIMIT 1000;";
        $banned_users = umc_get_banned_users(); //light='0' AND
    } else {
        $sql = "SELECT * FROM minecraft_log.logores_log WHERE username='$player' AND world NOT LIKE 'flatlands' ORDER BY `ID` DESC LIMIT 1000;";
    }

    $out .= "<table style=\"font-size:80%\">\n<tr><th>Day</th><th>User</th><th>Ore</th><th>World</th><th>Location</th><th>Ratio</th><th>Light</th><th>Time</th><th>Flags</th></tr>\n";
    $lastuser = '';
    $yesterday = '';
    $D = umc_mysql_fetch_all($sql);
    foreach ($D as $row) {
        // do not list banned users in the nolight section
        $username = $row['username'];
        $lower_user = strtolower($username);
        if (isset($_POST['nolight']) && in_array($lower_user, $banned_users)) {
            continue;
        } else if (($_POST['latest1000']) && in_array($lower_user, $banned_users)) {
            continue;
        } else if (($_POST['alerts']) && in_array($lower_user, $banned_users)) {
            continue;
        }
        $style = '';
        if ($row['light'] == 0) {
            $style = " style=\"color:red;\"";
        }
        $row_style = '';
        $date_arr = explode(" ", $row['date']);
        if ($yesterday != $date_arr[0]) {
            $row_style = ' style="background-color:#CCCCCC;"';
        }
        $day_time = explode(" ", $row['date']);
        $out .="<tr$row_style><td>{$day_time[0]}</td><td>{$row['username']}</td><td$style>{$row['ore']}</td><td>{$row['world']}</td><td$style>{$row['x']} / {$row['y']} / {$row['z']}</td><td>{$row['ratio']}</td>"
            ."<td>{$row['light']}</td><td>{$day_time[1]}</td><td>{$row['flagged']}</td></tr>";
	$lastuser = $row['username'];
        $yesterday = $date_arr[0];
    }
    $out .= "</table>\n";

    return $out;
}

// this returns the different users of plugin data in the log database
function umc_logblock_get_usernames() {
    $sql = "SELECT playername FROM `minecraft_log`.`lb-players` ORDER BY playername;";

    $usernames = array();
    $D = umc_mysql_fetch_all($sql);
    foreach ($D as $row) {
        $usernames[] = $row['playername'];
    }
    return $usernames;
}

function umc_logblock_get_lots($world) {
    $sql = "SELECT * FROM minecraft_worldguard.region_cuboid LEFT JOIN minecraft_worldguard.world ON world_id=id WHERE name='$world'";
    $lots = array();
    $D = umc_mysql_fetch_all($sql);
    foreach ($D as $row) {
        $lots[] = $row['region_id'];
    }
    return $lots;
}

function umc_logblock_get_lot_from_coord($world, $x, $z) {
    $sql = "SELECT * FROM minecraft_worldguard.region_cuboid
        LEFT JOIN minecraft_worldguard.world ON world_id=ID
        WHERE name='$world'
	    AND max_x>=$x
	    AND min_x<=$x
	    AND max_z>=$z
	    AND min_z<=$z";
    $D = umc_mysql_fetch_all($sql);
    if (count($D) == 0) {
        return 'n/a';
    }
    return $D[0]['region_id'];
}

function umc_logblock_get_coord_filter_from_lot($lot) {
    $sql = "SELECT * FROM minecraft_worldguard.region_cuboid
        WHERE region_id='$lot' LIMIT 1";
    $row = umc_mysql_fetch_all($sql);
    $filter = "AND x < {$row[0]['max_x']} AND z < {$row[0]['max_z']} AND x > {$row[0]['min_x']} AND z > {$row[0]['min_z']} ";
    return $filter;
}

function umc_display_logblock() {
    global $UMC_USER, $UMC_DOMAIN;
    $out = '';

    $line_limit = 1000;

    if (!$UMC_USER) {
        $out = "Please <a href=\"$UMC_DOMAIN/wp-login.php\">login</a>!";
        return $out;
    }

    $userlevel = $UMC_USER['userlevel'];
    $admins = array('Owner', 'Elder', 'ElderDonator', 'ElderDonatorPlus');
    if (!in_array($userlevel, $admins)) {
        return "This page is admin-only!";
    }

    $worlds = array('empire', 'nether', 'darklands');
    $usernames = umc_logblock_get_usernames();
    $post_world = filter_input(INPUT_POST, 'world', FILTER_SANITIZE_STRING);
    $post_username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $post_lot = filter_input(INPUT_POST, 'lot', FILTER_SANITIZE_STRING);
    $post_line = filter_input(INPUT_POST, 'line', FILTER_SANITIZE_STRING);

    // world filter
    if (isset($post_world)) {
        if (!in_array($post_world, $worlds)) {
            $out .= "<h2>World cannot be found!</h2>";
        }
    } else {
        $post_world = 'empire';
    }
    $world_filter = "lb-$post_world";

    // lot filter
    $lots = umc_logblock_get_lots($post_world);

    $lot_filter = '';
    if (isset($post_lot) && $post_lot != 'none') {
        if (!in_array($post_lot, $lots)) {
            $out .= "<h2>Lot cannot be found!</h2>";
        } else {
            $lot_filter = umc_logblock_get_coord_filter_from_lot($post_lot);
        }
    } else {
        $post_lot = '';
    }

    // user filter
    $username_filter = '';
    if (isset($post_username) && $post_username != 'none') {
        if (in_array($post_username, $usernames)) {
            $username_filter = "AND playername='$post_username'";
        } else {
            $out .= "<h2>User cannot be found!</h2>";
        }
    }

    // line filter
    if (!isset($post_line)) {
        $post_line = 0;
    }
    $count_sql = '';
    if (isset($_POST['today'])) {
        $count_sql = "SELECT count(id) AS counter FROM `minecraft_log`.`$world_filter`
            WHERE date=CURRENT_DATE() $lot_filter;";
    } else {
        $count_sql = "SELECT count(id) AS counter FROM `minecraft_log`.`$world_filter`
            LEFT JOIN `minecraft_log`.`lb-players` ON `$world_filter`.`playerid`=`lb-players`.`playerid`
            WHERE 1 $username_filter $lot_filter;";
    }
    $D = umc_mysql_fetch_all($count_sql);
    $num_rows = $D[0]['counter'];

    // make a dropdown for the line to start in for pagination
    $lines = array();
    $line = 0;
    while ($line <= ($num_rows - $line_limit)) {
        $line += $line_limit;
        $max_limit = min(($line + $line_limit - 1), $num_rows);
        $lines[$line] = $max_limit;
    }

    if (isset($_POST['today'])) {
        $sql = "SELECT * FROM `minecraft_log`.`$world_filter`
            LEFT JOIN `minecraft_log`.`lb-players` ON `$world_filter`.`playerid`=`lb-players`.`playerid`
            WHERE date=CURRENT_DATE() $lot_filter ORDER BY `date` DESC LIMIT $post_line,$line_limit;";
    } else {
        $sql = "SELECT * FROM `minecraft_log`.`$world_filter`
            LEFT JOIN `minecraft_log`.`lb-players` ON `$world_filter`.`playerid`=`lb-players`.`playerid`
            WHERE 1 $username_filter $lot_filter
	    ORDER BY `id` DESC LIMIT $post_line,$line_limit;";
    }

    $out .= "<form action=\"\" method=\"post\">\n"
        . "World: <select name=\"world\">";
    foreach ($worlds as $one_world) {
        $out .= umc_log_dropdown_preselect($one_world, $one_world, $post_world);
    }
    $out .= "</select> Lot: <select name=\"lot\"><option value=\"none\">All</option>";
    foreach ($lots as $one_lot) {
        $out .= umc_log_dropdown_preselect($one_lot, $one_lot, $post_lot);
    }
    $out .= "</select> User: <select name=\"username\"><option value=\"none\">All</option>";
    foreach ($usernames as $one_username) {
        $out .= umc_log_dropdown_preselect($one_username, $one_username, $post_username);
    }
    $out .= "</select> Line: <select name=\"line\"><option value=\"0\">0 -> 999</option>";
    $selected = array();
    $selected[$post_line] = " selected=\"selected\"";
    foreach ($lines as $one_line => $next_line) {
        $out .= umc_log_dropdown_preselect($one_line, "$one_line -> $next_line", $post_line);
    }
    $out .= "</select><input type=\"submit\" name=\"proposebutton\" value=\"Check\">"
        . "<input type=\"submit\" name=\"today\" value=\"Today only\"></form>";

    $out .= "<table style=\"font-size:80%\" class=\"log_table\">\n<tr><th>ID</th><th>Date</th><th>Time</th><th>Username</th><th>Removed</th><th>Placed</th><th>Lot</th><th>Coordinates</th></tr>\n";
    $yesterday = '';
    $D = umc_mysql_fetch_all($sql);
    foreach ($D as $row) {
        $row_style = '';
        $date_arr = explode(" ", $row['date']);
        if ($yesterday != $date_arr[0]) {
            $row_style = ' style="background-color:#CCCCCC;"';
        }

        if ($row['replaced'] == 0) {
            $remove_item = "";
        } else {
            $remove_item = umc_logores_item_name($row['replaced']);
        }
        if ($row['type'] == 0) {
            $place_item = "XXX";
        } else {
            $place_item = umc_logores_item_name($row['type'], $row['data']);
        }

        $one_lot = umc_logblock_get_lot_from_coord($post_world, $row['x'], $row['z']);

        $out .="<tr$row_style><td>{$row['id']}</td><td>{$date_arr[0]}</td><td>{$date_arr[1]}</td><td>{$row['playername']}</td><td>$remove_item</td><td>$place_item</td><td>$one_lot</td><td>{$row['x']} / {$row['y']} / {$row['z']}</td></tr>";
	$yesterday = $date_arr[0];
    }
    $out .= "</table>\n";

    return $out;

}

/**
 * This function shows block logs only for the active user's lots
 * and only changes done by other users.
 *
 * @global type $UMC_USER
 * @global type $UMC_DOMAIN
 * @return string
 */
function umc_log_logblock() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER, $UMC_DOMAIN;
    $out = 'This shows only changes done on your lots by other users. This is currently only possible for Empire & Kingdom lots';

    $line_limit = 1000;

    if (!$UMC_USER) {
        $out = "Please <a href=\"$UMC_DOMAIN/wp-login.php\">login</a>!";
        return $out;
    }

    $uuid = $UMC_USER['uuid'];
    $worlds = array('empire', 'kingdom');
    $lots = umc_user_getlots($uuid, $worlds);
    if (count($lots) == 0 ) {
        return "You do not have any lots!";
    }

    $post_lot = filter_input(INPUT_POST, 'lot', FILTER_SANITIZE_STRING);
    $post_line = filter_input(INPUT_POST, 'line', FILTER_SANITIZE_STRING);
    $post_username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);

    // world filter
    if (!is_null($post_lot)) {
        if (!isset($lots[$post_lot])) {
            return "<h2>Invalid lot!</h2>";
        }
    } else {
        reset($lots);
        list($post_lot, $lot_data) = each($lots);
    }

    $post_world = $lots[$post_lot]['world'];
    $lot_filter = umc_logblock_get_coord_filter_from_lot($post_lot);
    $world_filter = "lb-$post_world";

    // user filter
    $username_filter = "AND UUID <> '$uuid'";
    if (isset($post_username) && $post_username != 'none') {
        $username_filter = "AND playername='$post_username'";
    }

    // line filter
    if (!isset($post_line)) {
        $post_line = 0;
    }

    $nodata = false;
    $count_sql = "SELECT count(id) AS counter FROM `minecraft_log`.`$world_filter`
        LEFT JOIN `minecraft_log`.`lb-players` ON `$world_filter`.`playerid`=`lb-players`.`playerid`
        WHERE 1 $username_filter $lot_filter;";
    $C = umc_mysql_fetch_all($count_sql);
    if (count($C) > 0) {
        $num_rows = $C[0]['counter'];
        if ($num_rows == 0) {
            $nodata = true;
        }
    } else {
        $nodata = true;
    }

    $out .= "<form action=\"\" method=\"post\">\n"
        . "Lot: <select name=\"lot\">";
    foreach ($lots as $one_lot => $lot_data) {
        $out .= umc_log_dropdown_preselect($one_lot, $one_lot, $post_lot);
    }
    $out .= "</select> Line: <select name=\"line\"><option value=\"0\">0 -> 999</option>";
    $selected = array();
    if (!$nodata) {
        $lines = array();
        $line = 0;
        while ($line <= ($num_rows - $line_limit)) {
            $line += $line_limit;
            $max_limit = min(($line + $line_limit - 1), $num_rows);
            $lines[$line] = $max_limit;
        }
        $selected[$post_line] = " selected=\"selected\"";
        foreach ($lines as $one_line => $next_line) {
            $out .= umc_log_dropdown_preselect($one_line, "$one_line -> $next_line", $post_line);
        }
        $out .= "</select>&nbsp;<input type=\"submit\" name=\"proposebutton\" value=\"Check\"></form>";
    } else {
        $out .= "<input type=\"submit\" name=\"proposebutton\" value=\"Check\"></form>There is no data for this lot!";
        return $out;
    }
    $out .= "<table style=\"font-size:80%\" class=\"log_table\">\n<tr><th>ID</th><th>Date</th><th>Time</th><th>Username</th><th>Removed</th><th>Placed</th><th>Lot</th><th>Coordinates</th></tr>\n";
    $yesterday = '';

    $sql = "SELECT * FROM `minecraft_log`.`$world_filter`
            LEFT JOIN `minecraft_log`.`lb-players` ON `$world_filter`.`playerid`=`lb-players`.`playerid`
            WHERE 1 $username_filter $lot_filter
	    ORDER BY `id` DESC LIMIT $post_line,$line_limit;";
    $D = umc_mysql_fetch_all($sql);
    foreach ($D as $row) {
        $row_style = '';
        $date_arr = explode(" ", $row['date']);
        if ($yesterday != $date_arr[0]) {
            $row_style = ' style="background-color:#CCCCCC;"';
        }

        if ($row['replaced'] == 0) {
            $remove_item = "";
        } else {
            $remove_item = umc_logores_item_name($row['replaced']);
        }
        if ($row['type'] == 0) {
            $place_item = "XXX";
        } else {
            $place_item = umc_logores_item_name($row['type'], $row['data']);
        }

        $one_lot = umc_logblock_get_lot_from_coord($post_world, $row['x'], $row['z']);

        $out .="<tr$row_style><td>{$row['id']}</td><td>{$date_arr[0]}</td><td>{$date_arr[1]}</td><td>{$row['playername']}</td><td>$remove_item</td><td>$place_item</td><td>$one_lot</td><td>{$row['x']} / {$row['y']} / {$row['z']}</td></tr>";
	$yesterday = $date_arr[0];
    }
    $out .= "</table>\n";

    return $out;

}

function umc_log_dropdown_preselect($value, $text, $presel_value) {
    $out = '';
    if ($value == $presel_value) {
        $out .= "<option value=\"$value\" selected=\"selected\">$text</option>";
    } else {
        $out .= "<option value=\"$value\">$text</option>";
    }

    return $out;
}

function umc_logores_item_name($type, $data = 0) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $item_arr = umc_goods_get_text($type, $data);
    if (!$item_arr) {
        return "$type:$data";
    }
    return $item_arr['name'];
}

function umc_universal_web_stats() {
    global $UMC_DOMAIN;
    $sql = "SELECT `date`, COUNT( DISTINCT username) AS users
        FROM minecraft_log.universal_log
        WHERE (plugin,action) IN (('system','login'))
        GROUP BY `date`
        ORDER BY `date`;";

    $out = '<h2>Unique user logins per day</h2>';
    $maxval = 0;
    $minval = 0;
    $legend = array();
    $ydata = array();
    $sites = array();

    // Some data (line 1):
    foreach ($sites as $site) {
        $g->set_data($ydata[$site]);
        // $g->line( 1, '#0000FF', $site, 10 );
        $g->area_hollow( 2, 3, 25, '#CC3399' );
    }
    $out .= "<script type='text/javascript' src=\"$UMC_DOMAIN/admin/js/amcharts/amcharts.js\"></script>\n"
        . "<script type='text/javascript' src=\"$UMC_DOMAIN/admin/js/amcharts/serial.js\"></script>\n"
        . "<div id=\"chartdiv\" style=\"width: 100%; height: 362px;\"></div>\n"
        . "<script type='text/javascript'>//<![CDATA[\n"
        . "var chart;\n"
        . "var chartData = [\n";
    //
    $D = umc_mysql_fetch_all($sql);
    foreach ($D as $row) {
        $maxval = max($maxval, $row['users']);
        $minval = min($minval, $row['users']);
        $date = $row['date'];
        $legend[$date] = $date;
        $ydata[$date] = $row['users'];
    }

    foreach ($ydata as $date => $count) {
        $out .= "{\"Date\": \"$date\",";
        $out .= "\"Users\": $count,";
        $out .= "},\n";
    }
    $out .= "];\n";

    $out .= 'AmCharts.ready(function () {
    // SERIAL CHART
    chart = new AmCharts.AmSerialChart();
    chart.pathToImages = "http://www.amcharts.com/lib/3/images/";
    chart.dataProvider = chartData;
    chart.marginTop = 10;
    chart.categoryField = "Date";

    // AXES
    // Category
    var categoryAxis = chart.categoryAxis;
    categoryAxis.gridAlpha = 0.07;
    categoryAxis.axisColor = "#DADADA";
    categoryAxis.startOnAxis = true;

    // Value
    var valueAxis = new AmCharts.ValueAxis();
    valueAxis.stackType = "regular"; // this line makes the chart "stacked"
    valueAxis.gridAlpha = 0.07;
    valueAxis.title = "Users";
    chart.addValueAxis(valueAxis);';

    $out .= "var graph = new AmCharts.AmGraph();
        graph.type = \"line\";
        graph.hidden = false;
        graph.title = \"Users\";
        graph.valueField = \"Users\";
        graph.lineAlpha = 1;
        graph.fillAlphas = 0.6; // setting fillAlphas to > 0 value makes it area graph
        graph.balloonText = \"<span style=\'font-size:12px; color:#000000;\'>Logins: <b>[[value]]</b></span>\";
        chart.addGraph(graph);";

    $out .= '// LEGEND
        var legend = new AmCharts.AmLegend();
        legend.position = "top";
        legend.valueText = "[[value]]";
        legend.valueWidth = 100;
        legend.valueAlign = "left";
        legend.equalWidths = false;
        legend.periodValueText = "total: [[value.sum]]"; // this is displayed when mouse is not over the chart.
        chart.addLegend(legend);

        // CURSOR
        var chartCursor = new AmCharts.ChartCursor();
        chartCursor.cursorAlpha = 0;
        chart.addChartCursor(chartCursor);

        // SCROLLBAR
        var chartScrollbar = new AmCharts.ChartScrollbar();
        chartScrollbar.color = "#FFFFFF";
        chart.addChartScrollbar(chartScrollbar);

        // WRITE
        chart.write("chartdiv");
        });
        //]]></script>';

    return $out;
}

function umc_log_kill_display() {
    global $UMC_USER, $UMC_DOMAIN;
    $out = '';

    $line_limit = 1000;

    if (!$UMC_USER) {
        $out = "Please <a href=\"$UMC_DOMAIN/wp-login.php\">login</a>!";
        return $out;
    }

    $userlevel = $UMC_USER['userlevel'];
    $admins = array('Owner', 'Elder', 'ElderDonator', 'ElderDonatorPlus');
    if (!in_array($userlevel, $admins)) {
        return "This page is admin-only!";
    }

    $worlds = array('empire', 'kingdom');
    $usernames = umc_logblock_get_usernames();
    $post_world = filter_input(INPUT_POST, 'world', FILTER_SANITIZE_STRING);
    $post_killer = filter_input(INPUT_POST, 'killer', FILTER_SANITIZE_STRING);
    $post_lot = filter_input(INPUT_POST, 'lot', FILTER_SANITIZE_STRING);
    $post_line = filter_input(INPUT_POST, 'line', FILTER_SANITIZE_STRING);

    // world filter
    if (isset($post_world)) {
        if (!in_array($post_world, $worlds)) {
            $out .= "<h2>World cannot be found!</h2>";
        }
    } else {
        $post_world = 'empire';
    }
    $world_filter = "lb-$post_world-kills";

    // lot filter
    $lots = umc_logblock_get_lots($post_world);

    $lot_filter = '';
    if (isset($post_lot) && $post_lot != 'none') {
        if (!in_array($post_lot, $lots)) {
            $out .= "<h2>Lot cannot be found!</h2>";
        } else {
            $lot_filter = umc_logblock_get_coord_filter_from_lot($post_lot);
        }
    } else {
        $post_lot = '';
    }

    // user filter
    $killer_filter = '';
    if (isset($post_killer) && $post_killer != 'none') {
        if (in_array($post_killer, $usernames)) {
            $killer_filter = "AND killers.playername='$post_killer'";
        } else {
            $out .= "<h2>Killer cannot be found!</h2>";
        }
    }

    // line filter
    if (!isset($post_line)) {
        $post_line = 0;
    }
    $count_sql = '';
    if (isset($_POST['today'])) {
        $count_sql = "SELECT count(id) as counter FROM `minecraft_log`.`$world_filter`
            WHERE date=CURRENT_DATE() $lot_filter;";
    } else {
        $count_sql = "SELECT count(id) as counter, playername as killer FROM `minecraft_log`.`$world_filter`
            LEFT JOIN `minecraft_log`.`lb-players` as killers ON `$world_filter`.`killer`=`killers`.`playerid`
            WHERE 1 $killer_filter $lot_filter;";
    }
    // echo $count_sql;

    $D = umc_mysql_fetch_all($count_sql);
    $num_rows = $D[0]['counter'];

    // make a dropdown for the line to start in for pagination
    $lines = array();
    $line = 0;
    while ($line <= ($num_rows - $line_limit)) {
        $line += $line_limit;
        $max_limit = min(($line + $line_limit - 1), $num_rows);
        $lines[$line] = $max_limit;
    }

    $badmobs = '(33,138,1114,1115,1117,1123,1126,1128,1129,1131,1136,1930)';

    if (isset($_POST['today'])) {
        $sql = "SELECT id, date, weapon, x,y,z, victims.playername AS victim, killers.playername AS killer FROM `minecraft_log`.`$world_filter`
            LEFT JOIN `minecraft_log`.`lb-players` as victims ON `$world_filter`.`victim`=`victims`.`playerid`
            LEFT JOIN `minecraft_log`.`lb-players` as killers ON `$world_filter`.`killer`=`killers`.`playerid`
            WHERE date=CURRENT_DATE() AND killers.playerid NOT IN $badmobs AND victims.playerid NOT IN $badmobs $lot_filter
            ORDER BY `date` DESC LIMIT $post_line,$line_limit;";
    } else {
        $sql = "SELECT id, date, weapon, x,z,y, victims.playername AS victim, killers.playername AS killer FROM `minecraft_log`.`$world_filter`
            LEFT JOIN `minecraft_log`.`lb-players` as victims ON `$world_filter`.`victim`=`victims`.`playerid`
            LEFT JOIN `minecraft_log`.`lb-players` as killers ON `$world_filter`.`killer`=`killers`.`playerid`
            WHERE killers.playerid NOT IN $badmobs AND victims.playerid NOT IN $badmobs $killer_filter $lot_filter
            ORDER BY `id` DESC LIMIT $post_line,$line_limit;";
    }

    $out .= "<form action=\"\" method=\"post\">\n"
        . "World: <select name=\"world\">";
    $selected = array();
    $selected[$post_world] = " selected=\"selected\"";
    foreach ($worlds as $one_world) {
        $sel_str = '';
        if (isset($selected[$one_world])) {
            $sel_str = $selected[$one_world];
        }
        $out .= "<option value=\"$one_world\"$sel_str>$one_world</option>";
    }
    $out .= "</select> Lot: <select name=\"lot\"><option value=\"none\">All</option>";
    $selected = array();
    $selected[$post_lot] = " selected=\"selected\"";
    foreach ($lots as $one_lot) {
        $sel_str = '';
        if (isset($selected[$one_lot])) {
            $sel_str = $selected[$one_lot];
        }
        $out .= "<option value=\"$one_lot\"$sel_str>$one_lot</option>";
    }
    $out .= "</select> Killer: <select name=\"killer\"><option value=\"none\">All</option>";
    $selected = array();
    $selected[$post_killer] = " selected=\"selected\"";
    foreach ($usernames as $one_username) {
        $sel_str = '';
        if (isset($selected[$one_username])) {
            $sel_str = $selected[$one_username];
        }
        $out .= "<option value=\"$one_username\"$sel_str>$one_username</option>";
    }
    $out .= "</select> Line: <select name=\"line\"><option value=\"0\">0 -> 999</option>";
    $selected = array();
    $selected[$post_line] = " selected=\"selected\"";
    foreach ($lines as $one_line => $next_line) {
        $sel_str = '';
        if (isset($selected[$one_line])) {
            $sel_str = $selected[$one_line];
        }
        $out .= "<option value=\"$one_line\"$sel_str>$one_line -> $next_line</option>";
    }
    $out .= "</select><input type=\"submit\" name=\"proposebutton\" value=\"Check\">"
        . "<input type=\"submit\" name=\"today\" value=\"Today only\"></form>";

    $out .= "<table style=\"font-size:80%\" class=\"log_table\">\n<tr><th>ID</th><th>Date</th><th>Time</th><th>Killer</th><th>Weapon</th><th>Victim</th><th>Lot</th><th>Coordinates</th></tr>\n";
    $yesterday = '';
    $D = umc_mysql_fetch_all($sql);
    foreach ($D as $row) {
        $row_style = '';
        $date_arr = explode(" ", $row['date']);
        if ($yesterday != $date_arr[0]) {
            $row_style = ' style="background-color:#CCCCCC;"';
        }
        $one_lot = umc_logblock_get_lot_from_coord($post_world, $row['x'], $row['z']);
        $weapon = $remove_item = umc_logores_item_name($row['weapon']);
        $killer = $row['killer'];
        if ($killer == 'Arrow') {
            $weapon = $killer;
            $killer = '?';
        }
        $out .="<tr$row_style><td>{$row['id']}</td><td>{$date_arr[0]}</td><td>{$date_arr[1]}</td><td>$killer</td><td>$weapon</td><td>{$row['victim']}</td><td>$one_lot</td><td>{$row['x']} / {$row['y']} / {$row['z']}</td></tr>";
	$yesterday = $date_arr[0];
    }
    $out .= "</table>\n";

    return $out;
}

function umc_log_chat_import() {
    global $UMC_PATH_MC;
    $pattern_path = "$UMC_PATH_MC/server/bukkit/plugins/Herochat/logs/*";
    $files = umc_glob_recursive($pattern_path);
    $pattern_line = '/([0-9.]{10} [0-9:]{8})( \[[A-Z]\])? ?\*? ?(\[Trivia\]|[_0-9a-zA-Z]*)( -> ([_0-9a-zA-Z]*|)?)?(.*: )?(.*)/';
    $target_path = '/disk2/backup/log/minecraft';

    // erase the file
    foreach ($files as $file) {
        $text_arr = file($file);
        // get the first text
        $sql = "INSERT INTO `minecraft_log`.`chat_log` (`timestamp`, `source`, `target`, `text`, `raw`) VALUES \n";
        if (count($text_arr) == 0) {
            continue;
        }
        foreach ($text_arr as $line) {
            $match= array();
            $raw = umc_mysql_real_escape_string(trim($line));
            preg_match($pattern_line, $line, $match);
            // $raw = bzcompress($match[0]);
            $time = $match[1];
            $source = trim($match[3]);
            $target = trim($match[4]);
            if (strlen($match[2]) > 0) {
                $target = trim($match[2]);
            } else if (strlen($match[5]) > 0) {
                $target = trim($match[5]);
            }
            $text = trim($match[7]);
            $text_sql = umc_mysql_real_escape_string($text);
            if (strlen($time) > 0) {
                $sql .= "('$time', '$source', '$target', $text_sql, $raw),";
            }
        }
        $ins = substr($sql, 0, -1). ";";
        $date_today = umc_datetime();
        $today = $date_today->format('Y|m|d|H|i');
        $date_parts = explode("|", $today);
        $year = $date_parts[0];
        $month = $date_parts[1];
        $day = $date_parts[2];
        $hour = $date_parts[3];
        $min = $date_parts[3];
        umc_mysql_query($ins, true);
        $file = "$year-$month-{$day}_{$hour}_{$min}_chat_log.tar.bz2";
        rename($file, "$target_path/$year/$month/$file");
    }
}

