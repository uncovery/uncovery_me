<?php

global $UMC_FUNCTIONS;
$UMC_FUNCTIONS['get_todays_users'] = 'umc_get_todays_users';

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
 * Place Ajax JS, used in Footer.php template
 * @return string
 */
function umc_web_ajax_js() {
    $out = "<script type=\"text/javascript\">
    function umcAjaxFormProcess(destination, event) {
        jQuery('#umc_ajax_container').slideUp();
        jQuery('#umc_ajax_loading').slideDown();
        var formData = jQuery('#' + event.target.id).serialize() + '&ajax_form_submit=true';
        var action = jQuery('input[type=submit][clicked=true]').val();
	var append = \"&action=\" + action;
	var formData = formData + append;
        jQuery.post(destination, formData,
            function (data) {
                jQuery('#umc_ajax_container').html(data);
                jQuery('#umc_ajax_loading').delay(500).slideUp();
                jQuery('#umc_ajax_container').delay(500).slideDown();
            }
        );
        return false;
    }
    jQuery(\"form input[type=submit]\").click(function() {
            jQuery(\"input[type=submit]\", $(this).parents(\"form\")).removeAttr(\"clicked\");
            jQuery(this).attr(\"clicked\", \"true\");
    });
</script>
";
    return $out;
    /**

    $id = "TestForm";
    $def_form = "<div id=\"umc_ajax_container\">"
        . "<form onsubmit=\"return umcAjaxFormProcess('" . umc_web_curr_url() . "', event)\" id=\"newmailform\" method=\"post\">\n"
        . "<div>First form"
        . "<textarea name=\"message1\" value=\"\" rows=\"2\" style=\"width:100%;\"></textarea>"
        . "<input type=\"submit\" name=\"action\" value=\"Send\"></div>"
        . "</form></div>";
    **/
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

    $content = "<ul>\n";

    # If not logged in
    if (!$UMC_USER) {
        $title = 'Welcome, stranger!';
        $content = "Please feel free to look around! In order to start building with us, please <a href=\"$UMC_DOMAIN/wp-login.php\">whitelist yourself</a>. "
            . "To know more about how to join us, please <a href=\"$UMC_DOMAIN/server-access/whitelist/\">continue here</a>.<br>"
            . "If you are a member already, don\'t be a stranger and <a href=\"$UMC_DOMAIN/wp-login.php\">login</a>!<br><br>"
            . 'If you want to see what awaits you inside, watch our trailer!<br>'
            . '<iframe width="550" height="315" src="//www.youtube.com/embed/RjfZaDpGCLA" frameborder="0" allowfullscreen></iframe><br><br>';
    } else if ($userlevel == 'Guest') {
        $title = "Welcome, $username!";
        $content = "Thanks for white-listing on our server.<br>We would love to see you building with us. "
                . "<a href=\"$UMC_DOMAIN/server-access/buildingrights/\">Get builder rights now</a>!";
    } else {
        $title = "Welcome, <span class='" . strtolower($userlevel) . "'>$username</span>";
	if (strpos($userlevel, 'Donator')) {
	    $title .= "<span class='pluscolor'>+</span>";
        }
	if (strpos($userlevel, 'Plus')) {
	    $title .= "<span class='pluscolor'>+</span>";
        }
        $votables =  umc_vote_get_votable($username, true);
	// Teamspeak information
        $content .= "<li><strong>Join us</strong> on <a href=\"$UMC_DOMAIN/communication/teamspeak/\">Teamspeak</a>!</li>";
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
            $content .= "<li><strong>Your Deposit:</strong><ul>";

            foreach ($deposit as $depot_content) {
                $content .=  "<li>" . $depot_content['item'] . "</li>";
            }
            $content .= "</ul></li>";
        }
    }
    $content .= "</ul>\n";
    echo "<div class=\"welcome-block\"><h1 class=\"entry-title\">$title</h1>$notice\n$content\n</div>\n";
}

function umc_get_todays_users() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    $online_users = umc_read_markers_file('array');
    $sql = "SELECT username, lastlogin FROM minecraft_srvr.UUID "
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
        $url = umc_user_get_icon_url($user['username']);
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
            $uuid = $UMC_USER['uuid'];
            $username = strtolower($UMC_USER['username']);
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
            // $out .= "<br>". umc_donation_stats();
            $dlevel = umc_donation_level($UMC_USER['username']);
            if ($dlevel) {
                $out .= "<br><strong>Your donation lasts</strong>  $dlevel more months.";
            }
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
function umc_web_table($table_name, $sort_column, $data, $pre_table = '', $hide_cols = array(), $non_numeric_cols = false, $formats = false) {
    $headers = '';
    if (!$non_numeric_cols) {
        // default numeric cols if nothing else defined
        // this should disappear once all inputing functions are updated
        $non_numeric_cols = array('username', 'item', 'buyer', 'seller', 'meta', 'item_name');
    }

    $numeric_columns = array();
    if (is_array($data)) {
        if (count($data) == 0) {
            return "No data found<hr>";
        }
        $keys = array_keys(current($data));
    } else {
        if (!$data) {
            return false;
        }
        if (mysql_num_rows($data) == 0) {
            return "No data found<hr>";
        }
        $row = mysql_fetch_array($data, MYSQL_ASSOC);
        $keys = array_keys($row);
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
    if (is_array($data)) {
        foreach($data as $row) {
            $data_out .= umc_web_table_create_line($row, $numeric_columns, $formats, $hide_cols);
        }
    } else {
        mysql_data_seek($data, 0);
        while ($row = mysql_fetch_array($data, MYSQL_ASSOC)) {
            $data_out .= umc_web_table_create_line($row, $numeric_columns, $formats, $hide_cols);
        }
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
          .'jQuery(document).ready(function() {jQuery'. "('#shoptable_$table_name').dataTable( {\"order\": [[ $sort_column ]],\"paging\": false,\"ordering\": true,\"info\": false} );;} );"
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
    global $ENCH_ITEMS, $UMC_DOMAIN, $UMC_DATA, $UMC_DATA_ID2NAME;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    $people_types = array('username', 'buyer', 'seller', 'sender', 'recipient');
    $uuid_types = array('vendor', 'requestor');
    if ($name == 'item_name') {
        $id_parts = explode("|",$value);
        $item_arr = umc_goods_get_text($id_parts[0], $id_parts[1], $id_parts[2]);
        if (!$item_arr) {
            XMPP_ERROR_send_msg("Could not identify $name $value for web table");
        }
        $type = "&amp;type={$id_parts[1]}";
        $out = "<a href=\"?page=goods&amp;item={$id_parts[0]}$type\">" . $item_arr['full'] . "</a>\n";
        return $out;
    } else if ($name == 'item') {
        $meta = '';
        $id_parts = explode("|",$value);
        if (is_numeric($id_parts[0]))  {
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

        if ($item_meta != '') {
            $meta = unserialize($item_meta);
            $meta_list = array();
            foreach ($meta as $type => $level) {
                $nice_meta = $ENCH_ITEMS[$type]['short'];
                array_push($meta_list, "$nice_meta $level");
            }
            $meta = ", " . implode(", ", $meta_list);
        }

        if (isset($UMC_DATA[$item_name]['subtypes']) && $UMC_DATA[$item_name]['subtypes'][$item_dmg]['icon_url'] == '?') {
            $icon_dmg = 0;
        } else {
            $icon_dmg = $item_dmg;
        }
        if ($item['name']) {
            $text = "{$item['icon']}&nbsp;" . $item['name'] . $meta;
        } else {
            $text = "($value)";
        }
        $out = "<a href=\"$UMC_DOMAIN/trading/shop/?query=stock_detail&amp;item=$item_name\">$text</a>\n";
        return  $out;
    } else if (in_array($name, $people_types)) {
        // if ($value == '')
        $icon_url = umc_user_get_icon_url($value);
        return "<a href=\"?page=users&amp;user=$value\"><img title='$value' src='$icon_url' width=\"16\" alt=\"$value\">&nbsp;$value</a>";
    } else if (in_array($name, $uuid_types)) {
        $username = umc_user2uuid($value);
        $icon_url = umc_user_get_icon_url($username);
        return "<a href=\"?page=users&amp;user=$username\"><img title='$username' src='$icon_url' width=\"16\" alt=\"$username\">&nbsp;$username</a>";
    } else if (preg_match("/price/i",$name)) {
        return number_format($value,2,".","");
    } else if ($name == 'quantity' && $value < 1) {
        return "&infin;";
    }
    return $value;
}

function umc_web_sphere_generator() {
    $out = '
    <script type=\'text/javascript\' src=\'http://uncovery.me/admin/js/sphere.js\'></script>
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

function umc_github_link() {
    require_once ('/home/includes/github/index.php');
    echo unc_github_issues('uncovery', 'uncovery_me');
}

/**
 * Create a dropdown for all active users
 *
 * @param type $fieldname
 * @param type $presel_uuid
 * @return string
 */
function umc_web_active_users_dropdown($fieldname, $presel_uuid = false) {
    $users = umc_get_active_members();
    $out = "<select name=\"$fieldname\">\n";
    foreach ($users as $uuid => $username) {
        $sel = '';
        if ($uuid == $presel_uuid) {
            $sel = ' selected="selected"';
            $username .= " (yourself)";
        }
        $out .= "    <option value=\"$uuid\"$sel>$username</option>\n";
    }
    $out .= "</select>\n";
    return $out;
}

/**
 * Tablist should be an array('title' => 'link'
 *
 * @param type $tablist
 */
function umc_web_tabs($tabs_arr, $current_page, $tab_content) {
    // menu
    $out = "/n<div class=\"umc_tabs\">\n    <ul>\n";
    foreach ($tabs_arr as $tab => $tab_code) {
        $tab_title = umc_pretty_name($tab);
        if ($tab_code == $current_page) {
            $out .= "        <li class=\"umc_active_tab\">$tab_title</li>\n";
        } else {
            $out .= "        <li class=\"umc_inactive_tab\"><a href=\"?tab=$tab_code\">$tab_title</a></li>\n";
        }
    }
    $out .= "    </ul>\n</div>\n";
    $out .= "<div class=\"umc_tab_content\">\n$tab_content</div>\n";
    return $out;
}