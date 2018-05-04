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
 * This file contains several functions that output text to the website. This includes
 * a standardized table layout that is used by many different functions.
 */
global $UMC_FUNCTIONS;
$UMC_FUNCTIONS['get_todays_users'] = 'umc_get_todays_users';
$UMC_FUNCTIONS['web_set_fingerprint'] = 'umc_web_set_fingerprint';

/**
 * returns the current URL, filtered
 * @return string
 */
function umc_web_curr_url() {
    $s_server = filter_input_array(INPUT_SERVER, FILTER_SANITIZE_STRING);
    $url = "http://" . $s_server['HTTP_HOST'] . $s_server['REQUEST_URI'];
    return $url;
}

/**
 * show a text when content is loading through ajax
 */
function umc_web_loading_div() {
    $out = '<div id="umc_ajax_loading" style="display: none; background-color: yellow; padding: 10px;">
	Loading, please wait...
</div>';
    return $out;
}

// this displays specific content on top of the blogpost frontpage
// depending on the userlevel.
// the calling code for this is in wordpress index.php template
function umc_display_guestinfo(){
    global $UMC_USER, $UMC_DOMAIN;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    if ($UMC_USER) {
	$uuid = $UMC_USER['uuid'];
        $userlevel = $UMC_USER['userlevel'];
        $username = $UMC_USER['username'];
    }

    $latest_settlers = implode(", ", umc_get_latest_settlers(5));

    $notice = '';

    $content = "";

    # If not logged in
    if (!$UMC_USER) {
        $title = 'Welcome, stranger!';
        $content = "Please feel free to look around! In order to start building with us, please <a href=\"$UMC_DOMAIN/wp-login.php\">whitelist yourself</a>. "
            . "To know more about how to join us, please <a href=\"$UMC_DOMAIN/server-access/whitelist/\">continue here</a>.<br>"
            . "If you are a member already, don't be a stranger and <a href=\"$UMC_DOMAIN/wp-login.php\">login</a>!<br><br>"
            . 'If you want to see what awaits you inside, watch our trailer!<br>'
            . '<iframe width="550" height="315" src="//www.youtube.com/embed/RjfZaDpGCLA" allowfullscreen></iframe><br><br>';
    } else if ($userlevel == 'Guest') {
        $title = "Welcome, $username!";
        $content = "Thanks for white-listing on our server.<br>We would love to see you building with us. "
                . "<a href=\"$UMC_DOMAIN/server-access/buildingrights/\">Get builder rights now</a>!";
    } else {
        $content = "<ul>\n";
        $title = "Welcome, <span class='" . strtolower($userlevel) . "'>$username</span>";
	if (strpos($userlevel, 'Donator')) {
	    $title .= "<span class='pluscolor'>+</span>";
        }
	if (strpos($userlevel, 'Plus')) {
	    $title .= "<span class='pluscolor'>+</span>";
        }
        $votables =  umc_vote_get_votable($username, true);
	// Teamspeak information
        $content .= "<li><strong>Join us</strong> on <a href=\"$UMC_DOMAIN/communication/discord/\">Discord</a>!</li>";
	// Elder/Owner information
        if (strstr($userlevel, 'Elder') || $userlevel == 'Owner') { // elders only content
            $ban_arr = umc_get_recent_bans(3);
            $content .= "<li><strong>Logs:</strong> <a href=\"$UMC_DOMAIN/kills-logfile/\">Kills Logs</a>, <a href=\"$UMC_DOMAIN/logblock-logfile/\">Block Logs</a></li>\n"
                . "<li><strong>Recent Bans:</strong> ";
            foreach ($ban_arr as $ban => $reason) {
                $reason = trim($reason);
                $content .= "$ban ($reason), ";
            }
            $content = rtrim($content, ", ");
        $content .= "</li>\n";
        }
        // Latest settlers
        $content .= "<li><strong>Please welcome our latest settlers:</strong> $latest_settlers</li>\n";
	// Voting information
        if ($votables) {
            $content .= "<li>$votables</li>\n";
        }
	// Group information
	$content .= '<li><strong>Your stats:</strong> Your level is <strong>'.  $UMC_USER['userlevel'] . '</strong>';
	// Online time information
	$online_time = umc_get_lot_owner_age('days', $uuid);
	if ($online_time) {
	    $days = $online_time[$uuid]['firstlogin']['days'];
            $content .= ", you are on the server since <strong> $days days</strong>";
            $online_hours = umc_get_online_hours($uuid);
            $content .= " and have been online for <strong> $online_hours hours</strong>";
            if ($online_hours < 60) {
                $remaining = 60 - $online_hours;
                $content .= " but you need <strong>$remaining</strong> more hours online until Citizen status";
            }
        }
        $content .= "!</li>\n";

	// Deposit information
        $deposit = umc_show_depotlist(true, $username, true);
        if (is_array($deposit) && count($deposit) > 0) {
            $content .= "<li><strong><a href=\"https://uncovery.me/server-access/shop-manager/?page=deposit\">Your Deposit:</a></strong><ul>";
            foreach ($deposit as $depot_content) {
                $content .=  "<li>" . $depot_content['item'] . "</li>";
            }
            $content .= "</ul></li>";
        }
        $content .= "</ul>\n";
    }

    echo "<div class=\"welcome-block\"><h1 class=\"entry-title\">$title</h1>$notice\n$content\n</div>\n";
}

function umc_get_todays_users() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    $online_users = umc_read_markers_file('array');
    $sql = "SELECT username, lastlogin, uuid FROM minecraft_srvr.UUID "
        . "WHERE lastlogin >= now() - INTERVAL 1 DAY ORDER BY lastlogin DESC";
    $data = umc_mysql_fetch_all($sql);
    $json = false;
    $json_arr = array();
    $s_get  = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
    if (isset($s_get['json'])) {
        $json = true;
    }
    $out = "<div id=\"todays_users\">";
    $count = count($data);
    $opacity_step = 1 / $count;
    $opacity = 1;
    foreach ($data as $user) {
        $url = umc_user_get_icon_url($user['uuid'], false);
        if (!$url) { // we do not update here, only when people login
            continue;
        }
        $time = $user['lastlogin'];
        $datetime = umc_datetime($time);
        $timestamp = $datetime->getTimestamp();
        $date_text = umc_timer_format_diff($datetime);
        if (isset($online_users[strtolower($user['username'])])) {
            $label = "{$user['username']} (Online since $date_text)";
            $class = " online_user";
            $online = 'online';
        } else {
            $class = ' offline_user';
            $label = "{$user['username']} ($date_text ago)";
            $online = 'offline';
        }
        if ($json) {
            $json_arr[] = array($user['username'], $timestamp, $url, $online);
        }
        $out .= "<span class=\"today_user_icon$class\"><img style=\"opacity:$opacity;\" src=\"$url\" title=\"{$user['username']}\"><label class=\"today_user_label\">$label</label></span>\n";
        $opacity = round($opacity - $opacity_step, 4);
    }
    $out .= "</div>";

    if ($json) {
        $json_data = json_encode($json_arr);
        return $json_data;
    } else {
        echo $out;
    }
}

//displays the IRC iframe for the site
function umc_display_irc() {
    global $current_user;
    get_currentuserinfo();

    $email = $current_user->user_email;
    $username = $current_user->display_name;

    $out = '';
    if (strlen($email) < 4) {
        // display please login message
        $out = "<h2>Sorry, but you need to log in to use the chat on this page!</h2>";
    } else {
        $out = "<iframe border=1 src=\"http://webchat.freenode.net?nick=$username&channels=uncovery%20creeper45&uio="
            . "ND10cnVlJjk9dHJ1ZSYxMT0xODUmMTI9dHJ1ZQ7c\" width=\"647\" height=\"400\"></iframe>";
    }
    return $out;
}

function umc_display_irc_password() {
    global $current_user;
    get_currentuserinfo();

    $email = $current_user->user_email;
    $out = '';
    if (strlen($email) < 4) {
        // display please login message
        $out = "<h2>Sorry, but you need to log in to see the passsword!</h2>";
    } else {
        $out = "creeper45";
    }
    return $out;
}


function umc_server_status() {
    global $UMC_DOMAIN;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    $errno = '';
    $errstr = '';
    $fp = @fsockopen('uncovery.me', 25565, $errno, $errstr, 1);
    if (!$fp){
        echo "$errstr ($errno)<br />\n";
        return "<img src=\"$UMC_DOMAIN/admin/img/offline.png\" height=\"50\"><Br>";
    } else {
        global $UMC_USER;
        $out = "<img src=\"$UMC_DOMAIN/admin/img/online.png\" height=\"50\"><br>";
        if ($UMC_USER) {
            $date_new = umc_datetime();
            $now = $date_new->format('Y-m-d H:i');

            $out .= '<strong>Server Address:</strong> uncovery.me<br>'
                . '<strong>Server Port:</strong> 25565<br>'
                . "<strong>Server Time:</strong> $now<br>"
                . "<strong>Next reboot in:</strong> " . umc_time_until_restart() . "<br>";

            $result = count_users();
            $out .= '<strong>Registered Users:</strong> '.  $result['total_users'] . '<br>';
            //$rights = umc_read_data('permissions');
            //$users = $rights['permissions']['users'];
            //$result = count($users);
            //$out .= '<strong>Builders:</strong> '.  $result . '<br>';
            $out .= '<strong>Online Users:</strong> ';
            $online_users = umc_read_markers_file('array');
            $no_users = count($online_users);
            if ($no_users > 0 && $online_users != '') {
                $out .= "($no_users) ";
                foreach ($online_users as $data) {
                    $out .= "<a href=\"$UMC_DOMAIN/users-2/?u={$data['name']}\">{$data['name']}</a>, ";
                }
            } else {
                $out .= "nobody";
            }
            $out = rtrim($out, ", ");
        } else {
            $out = 'Please login!';
        }
    }

    return $out;
}

/**
 *
 * @param string $table_name name of the table to enable several per page
 * @param string $sort_column initial sorting column, format "7, 'desc'"
 * @param misc $data recordset of all data in the table or array
 * @param string $pre_table html to insert before table
 * @param int $hide_cols one colums to hide
 * @param array $non_numeric_cols names of columens that are not numerical
 * @param array array('column' => 'function');
 * @return string
 */
function umc_web_table($table_name, $sort_column, $data, $pre_table = '', $hide_cols = array(), $non_numeric_cols = false, $formats = false, $page_data = false) {
    global $UMC_SETTING;
    $headers = '';
    if (!$non_numeric_cols) {
        // default numeric cols if nothing else defined
        // this should disappear once all inputing functions are updated
        $non_numeric_cols = array('username', 'item', 'buyer', 'seller', 'meta', 'item_name');
    }

    $numeric_columns = array();
    if (is_array($data)) {
        if (count($data) == 0) {
            return "$pre_table<br>No data found<hr>";
        }
        $keys = array_keys(current($data));
    } else {
        XMPP_ERROR_trigger("Data type passed to umc_web_table is not array!");
        return '';
    }

    $counter = 0;
    foreach ($keys as $col) {
        if (in_array($col, $hide_cols)) {
            continue;
        } else {
            $style = "style='text-align:left'";
            $input_class = "";
            if (!in_array($col, $non_numeric_cols)) {
                array_push($numeric_columns, $counter);
                $style = "style='text-align:right'";
                $input_class = " numeric";
            }
            $headers .= "<th $style>" . umc_pretty_name($col) . "</th>\n";
            $counter++;
        }
    }


    $data_out = '';

    foreach($data as $row) {
        $data_out .= umc_web_table_create_line($row, $numeric_columns, $formats, $hide_cols);
    }

    $numeric_columns_str = implode(",", $numeric_columns);

    $out = "<script type=\"text/javascript\">
            var table_name = \"$table_name\";
            var numeric_columns = [$numeric_columns_str];
            var sort_columns = [[$sort_column]];
            var strnum_columns = [];
        </script>";

    $out .= "<script type=\"text/javascript\" src=\"/admin/js/jquery.dataTables.min.js\"></script>\n"
          . "<script type=\"text/javascript\">"
          .'jQuery(document).ready(function() {jQuery'. "('#shoptable_$table_name').dataTable( {\"autoWidth\": false, \"order\": [[ $sort_column ]],\"paging\": false,\"ordering\": true,\"info\": false} );;} );"
          . "</script>";

    $out .= "$pre_table
        <table id='shoptable_$table_name'>
          <thead>
            <tr>
              $headers
            </tr>
          </thead>
          <tbody>
            $data_out
          </tbody>
        </table>";

    if ($page_data) {

        $num_records = $page_data['record_count'];
        $page_url = $page_data['page_url'];

        $current_page = $page_data['current_page'];
        if (isset($page_data['page_length'])) {
            $page_length = $page_data['page_length'];
        } else {
            $page_length = $UMC_SETTING['list_length'];
        }
        $page_count = round($num_records / $page_length);

        $current_entry = $page_length * ($current_page - 1);
        $last_entry = $current_entry + $page_length;

        if ($num_records < $page_length) {
            $out .= "$num_records entries found.";
            return $out;
        }

        $out .= "$num_records entries, showing $current_entry-$last_entry. Select Page: ";

        $jump = false;
        for ($i=1; $i<=$page_count; $i++) {
            // we show the first 3 pages, the last 3 pages
            if (
                ($i <= 3) || ($i > ($page_count - 3)) ||  // show first and last 3 pages
                (($i < ($current_page + 2)) && ($i > ($current_page - 2))) // show the 3 pages around the current
               ) {
                if ($i == $current_page) {
                    $out .= " $i ";
                } else {
                    $url = sprintf($page_url, $i);
                    $out .= " <a href=\"{$url}\">$i</a> ";
                }
                $jump = false;
            } else {
                if (!$jump) {
                    $out .= " ... ";
                }
                $jump = true;
            }
        }
    }

    return $out;
}

/**
 * Create a line in a table and set the style per cell in case it's numeric
 */
function umc_web_table_create_line($row, $numeric_columns, $formats, $hide_cols) {
    $data = "<tr>\n";
    $counter = 0;

    foreach ($row as $field => $val) {
        $style = "";
        if (in_array($counter, $numeric_columns)) {
            $style = "class='numeric_td'";
        }
        if (in_array($field, $hide_cols)) {
            continue;
        }
        if (($formats) && isset($formats[$field])) {
            $formatfunction = $formats[$field];
            $text = $formatfunction($field, $row);
        } else {
            $text = umc_web_table_format_column($field, $val);
        }
        $data .= "<td $style>$text</td>\n";
        $counter++;
    }
    $data .= "</tr>\n";
    return $data;
}

// Column formatting
function umc_web_table_format_column($name, $value) {
    global $UMC_DOMAIN, $UMC_DATA, $UMC_DATA_ID2NAME;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    $people_types = array('username', 'buyer', 'seller', 'sender', 'recipient');
    $uuid_types = array('vendor', 'requestor');
    if ($name == 'item_name') {
        $type = 0;
        $type_str = '';
        $id_parts = explode("|",$value);
        if (isset($id_parts[1])) {
            $type = $id_parts[1];
            $type_str = "&amp;type=$type";
        }
        $meta = '';
        $meta_str = '';
        if (isset($id_parts[2])) {
            $meta = $id_parts[2];
            $meta_str = "&amp;meta=$meta";
        }
        $item_arr = umc_goods_get_text($id_parts[0], $type, $meta);
        if (!$item_arr) {
            XMPP_ERROR_send_msg("Could not identify {$id_parts[0]}, $type, $meta (field $name) for web table");
        }
        $out = "<a href=\"?page=goods&amp;item={$id_parts[0]}$type_str$meta_str\">" . $item_arr['full'] . "</a>\n";
        return $out;
    } else if ($name == 'item') {
        $id_parts = explode("|",$value);
        if (is_numeric($id_parts[0]))  {
            XMPP_ERROR_trigger('UMC_DATA_ID2NAME USAGE');
            $item_name = $UMC_DATA_ID2NAME[$id_parts[0]];
        } else {
            $item_name  = $id_parts[0];
        }

        $item = umc_goods_get_text($item_name, $id_parts[1]);
        $item_dmg = $id_parts[1];
        $item_meta = $id_parts[2];

        if (($item_dmg == '') || (isset($UMC_DATA[$item_name]['damage']) && $UMC_DATA[$item_name]['damage'] > 0)) { // damage item have dmg id 0 for icon
            $item_dmg = 0;
        }

        if (isset($UMC_DATA[$item_name]['subtypes'])) {
            $icon_dmg = 0;
        } else {
            $icon_dmg = $item_dmg;
        }
        if ($item['name']) {
            $text = "{$item['icon']}&nbsp;" . $item['full_clean'];
        } else {
            $text = "($value)";
        }
        $out = "<a href=\"$UMC_DOMAIN/trading/shop/?query=stock_detail&amp;item=$item_name\">$text</a>\n";
        return  $out;
    } else if (in_array($name, $people_types)) {
        // if ($value == '')
        $icon_url = umc_user_get_icon_url($value);
        return "<a href=\"https://uncovery.me/server-features/users-2/?u=$value\"><img title='$value' src='$icon_url' width=\"16\" alt=\"$value\">&nbsp;$value</a>";
    } else if (in_array($name, $uuid_types)) {
        $username = umc_user2uuid($value);
        $icon_url = umc_user_get_icon_url($username);
        return "<a href=\"https://uncovery.me/server-features/users-2/?u=$username\"><img title='$username' src='$icon_url' width=\"16\" alt=\"$username\">&nbsp;$username</a>";
    } else if (preg_match("/price/i",$name)) {
        return number_format($value,2,".","");
    } else if ($name == 'quantity' && $value < 1) {
        return "&infin;";
    }
    return $value;
}

/**
 * @param array $data: An associative array in the following format:
 *      Tab Title (String) => Tab Content (String - HTML OK)
 *      if the data is supposed to be loaded via AJAX,  use
 *      Tab Title (String) => Tab URL (http://....)
 * @return string
 *
 * **NOTE: This function also supports popovers in the following format:
 *      <div class='popover'>
 *              Doesn't have to be <div>, can be any element: tr, span, etc. The
 *              important part is the class.  This will make the mouse cursor a pointer
 *              on hover, and when it is clicked the popover-content will appear
 *          <div class='popover-content'>
 *              This is the HTML inside the popover.  It MUST be inside the parent,
 *              but other than that there should be no restrictions.
 *          </div>
 *      </div>
 */
function umc_jquery_tabs($data) {
    # External JS and Stylesheets -- These should _not_
    # be needed after everything is properly configured
    $out = "<link id=\"webui-popover\" rel=\"stylesheet\" href=\"http://www.f85.net/jquery.webui-popover.min.css\">\n"
        . "<script src=\"http://www.f85.net/jquery.webui-popover.min.js\"></script>\n"
        . "<link id=\"jqueryui\" rel=\"stylesheet\" href=\"https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/themes/smoothness/jquery-ui.css\">\n";

    $out .= "<div class='umc_jquery_tabs umc_fade_in'>\n"
        . "<ul>\n";

    # Set up tab titles
    $i = 0;
    foreach ($data as $tab => $tab_html) {
        // if the tab is externally loaded, use the HTML as URL instead
        //if (strpos($tab_html, "http://") == 0) {
        //    $tab_link = $tab_html;
        //} else {
            $tab_link = "#tab$i";
        //}
        $out .= "<li><a href='$tab_link'><span>$tab</span></a></li>\n";
        $i += 1;
    }
    $out .= "</ul>\n";

    # Set up tab content <div>s
    $i = 0;
    foreach ($data as $tab => $tab_html) {
        //if (strpos($tab_html, "http://") === false) {
            $out .= "<div id='tab$i'>\n$tab_html\n</div>";
        //}
        $i += 1;
    }
    $out .= "</div>\n";

    return $out;
}

function umc_web_sphere_generator() {
    $out = '
    <script type=\'text/javascript\' src=\'https://uncovery.me/admin/js/sphere.js\'></script>
    <div>
    Radius: <input type="text" id="txtRadius" value="5" /><br>
    Fill: <input type="checkbox" id="chkFill" value="true" checked /> <small>Either fill the sphere, or calculate which position may be left empty.</small><br>
    Hints: <input type="checkbox" id="chkHints" value="true" checked /> <small>On each layer darken the previous layer to ease building.</small><br>
    Block-align: <input type="checkbox" id="chkMiddle" value="true" /><br>
    <input type="button" value="Generate" onclick="generate();" />
    </div>
    <div id="sphere">
    </div>';
    return $out;
}

/**
 * Create a generic dropdown
 *
 * @param type $data in the form of array('key' => 'value')
 * @param type $fieldname is the form field to be used in POST
 * @param type $presel_key an optional key to have the dropdown be preselected on
 * @param type $submit_on_change use onchange="this.form.submit()"
 * @return string
 */
function umc_web_dropdown($data, $fieldname, $presel_key = false, $submit_on_change = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $submit_js = '';
    if ($submit_on_change) {
        $submit_js = ' onchange="this.form.submit()"';
    }
    $out = "<select name=\"$fieldname\"$submit_js>\n";
    foreach ($data as $key => $value) {
        $sel = '';
        if ($key == $presel_key) {
            $sel = ' selected="selected"';
        }
        $out .= "    <option value=\"$key\"$sel>$value</option>\n";
    }
    $out .= "</select>\n";
    return $out;
}

/**
 * Tablist should be an array('title' => 'link'
 *
 * @param type $tablist
 */
function umc_web_tabs($tabs_menu, $current_page, $tab_content) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $out = "/n<div class=\"umc_tabs\">\n    <ul>\n";
    foreach ($tabs_menu as $tab_code => $tab_title) {
        if ($tab_code == $current_page) {
            $out .= "        <li class=\"umc_active_tab\">$tab_title</li>\n";
        } else {
            $out .= "        <li class=\"umc_inactive_tab\"><a href=\"?tab=$tab_code\">$tab_title</a></li>\n";
        }
    }
    $out .= "    </ul>\n</div>\n
    <div class=\"umc_tab_content\">\n$tab_content\n</div>\n";
    return $out;
}

/**
 * returns likely accounts shared by UUIDs
 * and donations given after the user last logged in
 *
 */
function umc_web_usercheck() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    $tables = array(
        'Same IP' => 'last_ip',
        'Same Browser' => 'browser_id',
    );
    $out = '';
    foreach ($tables as $table_name => $crit_field) {
        $sql = "SELECT $crit_field FROM minecraft_srvr.UUID WHERE $crit_field <> ''
               GROUP BY $crit_field HAVING count($crit_field) > 1 ORDER BY lastlogin DESC, count($crit_field) DESC";

        $L = umc_mysql_fetch_all($sql);
        $out_arr = array();
        foreach ($L as $l) {
            $line_sql = "SELECT username, userlevel, lot_count, round(onlinetime / 24) as OnlineTime, DATE(lastlogin) as last_login, INET_NTOA(last_ip) as ip,
                browser_id AS 'Browser ID'
                FROM minecraft_srvr.UUID WHERE $crit_field = '{$l[$crit_field]}'
                ORDER BY lastlogin DESC, last_ip DESC";
            $D = umc_mysql_fetch_all($line_sql);
            foreach ($D as $d) {
                $out_arr[] = $d;
            }
        }
        $out .= umc_web_table($table_name, 0, $out_arr, "<h2>$table_name</h2>");
    }

    $sql_donations = 'SELECT id as d_id, amount, UUID.username, email, date as d_date, DATE(lastlogin) as last_login, userlevel, lot_count '
        . 'FROM minecraft_srvr.donations '
        . 'LEFT JOIN minecraft_srvr.UUID on minecraft_srvr.donations.uuid=UUID.UUID '
        . 'WHERE UUID.lastlogin < date ORDER BY lastlogin DESC';
    $C = umc_mysql_fetch_all($sql_donations);
    $out .= umc_web_table('Late Donations', 0, $C, "<h2>Late Donations</h2>");

    $sql_double_account = 'SELECT count(user_id), meta_value FROM wp_usermeta
        WHERE meta_key = \'minecraft_uuid\'
        group by meta_value
        having count(user_id) > 1
        ORDER BY count(user_id)  DESC';
    $U = umc_mysql_fetch_all($sql_double_account);
    $out_data = array();
    foreach ($U as $data) {
        $sql_check = "SELECT * FROM wp_usermeta
            LEFT JOIN wp_users on ID=user_id WHERE meta_value=\"{$data['meta_value']}\"";
        $X = umc_mysql_fetch_all($sql_check);
        foreach ($X as $xdata) {
            $out_data[] = array(
                'username' => $xdata['display_name'],
                'uuid' => $data['meta_value'],
                'user_login' => $xdata['user_login'],
                'user_registered' => $xdata['user_registered'],
                'lot_count' => umc_user_countlots($data['meta_value']),
            );
        }
    }
    $out .= umc_web_table('Double accounts', 0, $out_data, "<h2>Double accounts</h2>");
    return $out;
}

/**
 * Update the browser fingerprint.
 * Called by javascript from js in umc_wp_fingerprint_call()
 *
 * @global type $UMC_USER
 */
function umc_web_set_fingerprint() {
    $id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_STRING);
    $uuid = filter_input(INPUT_GET, 'uuid', FILTER_SANITIZE_STRING);
    $sql = "UPDATE minecraft_srvr.UUID SET browser_id='$id' WHERE UUID='$uuid';";
    umc_mysql_query($sql);
}

function umc_web_userstats() {
    $sql = 'SELECT count(UUID) as count, SUBSTRING(userlevel,1,1) as level, DATE_FORMAT(firstlogin, "%Y-%u") as date
        FROM minecraft_srvr.UUID
        WHERE firstlogin > 0
        GROUP BY SUBSTRING(userlevel,1,1), DATE_FORMAT(firstlogin,"%Y-%u")';
    $D1 = umc_mysql_fetch_all($sql);

    $X = array();
    foreach ($D1 as $row) {
        if ($row['level'] == 'G') {
            $level = 'guest';
        } else {
            $level = 'settler';
        }
        $X[$row['date']][$level] = $row['count'];
    }

    $out = "<h2>Guest to Settler conversion stats:</h2>\n"
        . umc_web_javachart($X, 'weeks', 'regular', false, 'settlers');

    $sql2 = "SELECT `date`, COUNT( DISTINCT username) AS users
        FROM minecraft_log.universal_log
        WHERE (plugin,action) IN (('system','login'))
        GROUP BY `date`
        ORDER BY `date`;";

    $D2 = umc_mysql_fetch_all($sql2);
    $L = array();
    $days_this_month = 0;
    $sum_this_month = 0;
    foreach ($D2 as $row) {
        $L[$row['date']]['users'] = $row['users'];
        $days_this_month++;
        $day = intval(substr($row['date'], 9,2));
        $sum_this_month += $row['users'];
        if ($day == 1) {
            $avg_this_month = $sum_this_month / $days_this_month;
            $days_this_month = 0;
            $sum_this_month = 0;
            $L[$row['date']]['avg'] = $avg_this_month;

        }
    }
    $out .= "<h2>Unique user logins per day:</h2>\n"
        . umc_web_javachart($L, 'weeks', 'none', false, 'userlogins');
    return $out;

}

/**
 * Generic 2D Chart generator. Supports multiple axis
 *
 * @global type $UMC_DOMAIN
 * @param array $data as in array('Jan 2016' => array('row1' => 1, 'row2' => 2), ..) ;
 * @param string $y_axis_name name for the Y-axis
 * @param string $stacktype any of "none", "regular", "100%", "3d".
 * @param array $axis_groups as in array('row1' => 'left', 'row2' => right) or false
 * @param string $name to name the whole chart. Needed when we have several in one page.
 * @param bool $sum Do we should the sum of all items on the top?
 * @param int $hight pixel height of the chart
 * @return string
 */
function umc_web_javachart($data, $y_axis_name, $stacktype, $axis_groups = false, $name = 'amchart', $sum = true, $height = 500) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    // check the stack type
    $valid_stacktypes = array("none", "regular", "100%", "3d");
    if (!in_array($stacktype, $valid_stacktypes)) {
        XMPP_ERROR_trigger('Invalid stacktype!');
    }

    $out = '<script src="https://www.amcharts.com/lib/3/amcharts.js"></script>
    <script src="https://www.amcharts.com/lib/3/serial.js"></script>
    <script src="https://www.amcharts.com/lib/3/themes/light.js"></script>'
       . "\n<div style=\"width: 100%; height: {$height}px; font-size: 11px;\" id=\"$name\"></div>\n";

    $out .= "<script type=\"text/javascript\">
        var chart = AmCharts.makeChart(\"$name\", {"
        . '
        "type": "serial",
        "theme": "none",
        "marginRight":30,' . "\n";
    if ($sum) {
            $out .= '"legend": {
                "equalWidths": false,
                "periodValueText": "total: [[value.sum]]",
                "position": "top",
                "valueAlign": "left",
                "valueWidth": 100
            },' . "\n";
    }
    $out .= '"dataProvider": ['. "\n";

    $graphs = array();
    foreach ($data as $row => $line) {
        $out .= "{";
        $out .= "\"$y_axis_name\": \"$row\",";
        foreach ($line as $field => $value) {
            $graphs[$field] = ucwords($field);
            $out .= " \"$field\": $value,";
        }
        $out .= "},\n";
    }
    $out .='],
        "valueAxes": [{
            "stackType": "'.$stacktype.'",
            "gridAlpha": 0.07,
            "position": "left",
            "title": "Amount"
        }],
        "graphs": [' ."\n";
    $valaxis = '';
    foreach ($graphs as $graph => $title) {
        $graphaxis = '';
        if ($axis_groups) {
            if (isset($axis_groups[$graph])) {
                $valaxis .= '{"id": "'.$graph.'", "title": "'.$title.'", "position": "'.$axis_groups[$graph].'"},';
                $graphaxis = ',"valueAxis": "'.$graph.'",';
            }
        }
        $out .= "{
            \"title\": \"$title\",
            \"valueField\": \"$graph\",
            \"fillAlphas\": 0.6,
            \"balloonText\": \"$title: [[value]]\"
            $graphaxis},\n";
    }
    $out .= '
        ],
        "plotAreaBorderAlpha": 0,
        "marginTop": 10,
        "marginLeft": 0,
        "marginBottom": 0,
        "chartScrollbar": {"dragIconHeight": 15, "scrollbarHeight": 10},
        "chartCursor": {
            "cursorAlpha": 0
        },
        "categoryField": "'.$y_axis_name.'",
        "categoryAxis": {
            "startOnAxis": true,
            "axisColor": "#DADADA",
            "gridAlpha": 0.07,
            "title": "'.ucwords($y_axis_name).'",
        },' . "\n";
    if ($axis_groups) {
        $out .= "\"valueAxes\": [$valaxis],\n";
    }
    $out .= '
        "export": {
            "enabled": true
        }
    });
</script>' . "\n";
    return $out;
}