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
 * This manages vantity titles that users can rent for any given time.
 */

global $UMC_SETTING, $WS_INIT, $VANITY_PRICES;

$VANITY_PRICES = array(
    'color' => 50, // per day
    'alphanum' => 10,
    'symbol' => 30,
);

$WS_INIT['vanity'] = array(  // the name of the plugin
    'disabled' => false,
    'events' => array(
        'PlayerJoinEvent' => 'umc_vanity_check',
    ),
    'default' => array(
        'help' => array(
            'title' => 'Vanity Titles',  // give it a friendly title
            'short' => 'Rent a vanity title for some time',  // a short description
            'long' => "You get a vanity title for some time. Prices is determined by the amount of letters, colors and punctuation. Offensive titles are bannable!", // a long add-on to the short  description
            ),
    ),
    'set' => array(
        'help' => array(
            'short' => 'Rent a vanity title for some time',
            'long' => "You get a vanity title for a number of days. use /vanity quote to find out the price. Offensive titles are bannable!",
            'args' => '<days> <text>',
        ),
        'function' => 'umc_vanity_set',
    ),
    'quote' => array(
        'help' => array(
            'short' => 'Get a quote for a vanity title',
            'long' => "Tells you how much a vanity title will cost for x days and shows you how it will look like. Offensive titles are bannable!",
            'args' => '<days> <text>',
        ),
        'function' => 'umc_vanity_quote',
    ),
    'check' => array(
        'help' => array(
            'short' => 'Check when your title will expire',
            'long' => "Tells you when your current title will expire (in days)",
        ),
        'function' => 'umc_vanity_check',
    ),
    'cancel' => array(
        'help' => array(
            'short' => 'Cancel your title and get some refund.',
            'long' => "This will cancel your current title and refund any leftover cash. The first day is never refunded.",
        ),
        'function' => 'umc_vanity_cancel',
    ),
);

function umc_vanity_quote() {
    global $UMC_USER, $VANITY_PRICES;
    $player = $UMC_USER['username'];
    $args = $UMC_USER['args'];

    if (!isset($args[2]) || !is_numeric($args[2]) || ($args[2] < 1)) {
        umc_error("{red}You need to specify a number of days");
    } else if (!isset($args[3])) {
        umc_error("{red}You need to specify the title you want to have. See {yellow}/helpme vanity");
    }
    $days = $args[2]++;
    $vanity_raw = '';
    // concatenate all into a string
    for ($i=3; $i<count($args); $i++) {
        $vanity_raw .= " " . $args[$i];
    }
    $vanity = trim($vanity_raw);
    umc_vanity_sanitize($vanity);

    $quote_array = umc_vanity_quote_title($vanity);
    if ($quote_array['length'] > 20) {
        umc_error("Your title is too long ({$quote_array['length']} vs. 20 max!");
    }

    umc_header("Vanity Title Quote");
    // check for color code in the end
    // second last letter
    $second_last = substr($vanity, -2, 1);
    if ($second_last == '&') {
        umc_echo("You have a color code in the end, {red}this is not needed!{white}");
    }
    // calculate expiry
    $date_today = umc_datetime();
    $time_set = $date_today->format('Y-m-d H:i:s');

    $date_timeout = umc_datetime(); // substract the current day
    $date_timeout->add(new DateInterval('P' .$days. 'D'));
    $time_out = $date_timeout->format('Y-m-d H:i:s');

    $interval = $date_today->diff($date_timeout);
    $days_interval = $interval->days;
    $hours_interval = $interval->h;

    $balance = umc_money_check($player);
    $totalcost = $quote_array['cost'] * $days;

    umc_echo("The text [$vanity{white}] will cost you $totalcost Unc (=$days days @ {$quote_array['cost']}):");
    umc_echo("{$quote_array['color']} Colors @ {$VANITY_PRICES['color']} = {$quote_array['color_price']}");
    umc_echo("{$quote_array['alphanum']} Letters @ {$VANITY_PRICES['alphanum']} = {$quote_array['alphanum_price']}");
    umc_echo("{$quote_array['symbol']} Symbols @ {$VANITY_PRICES['symbol']} = {$quote_array['symbol_price']}");
    umc_echo("Your title will expire on $time_out (server time)!");
    if ($totalcost > $balance) {
        umc_echo("You do not have enough money to pay for this title for $days days and $hours_interval hours. You need $totalcost but have only $balance!");
    } else {
        umc_echo("Use /vanity set <days> <title> to set a title now!");
    }
    umc_footer(true);
}

/*
 * checks if a vanity title is set and if the timer is still active
 * if no more timer is active, removes the title
 */
function umc_vanity_check($report = true) {
    // check remaining days
    global $UMC_USER;
    $player = $UMC_USER['username'];

    $current_title = umc_vanity_get_title();
    $uuid = umc_user2uuid($player);
    $date_out = umc_timer_get($player, 'custom_title');
    if (!$date_out && $current_title) { // if there is an expired timer AND an existing title
        umc_exec_command("pex user $uuid suffix \"\"", 'asConsole');
        if ($report) {
            umc_echo('Your title has expired. Use /vanity to get a new one!');
        }
    } else if (!$current_title) {
        umc_echo('You do not have a title set. Use /vanity to get a new one!');
    } else if ($report) {
        $date_today = umc_datetime();
        $time_out = $date_out->format('Y-m-d H:i:s');
        $diff_str = umc_timer_format_diff($date_today, $date_out);
        umc_echo("Your title will expire on $time_out (in $diff_str)!");
    }
}

/*
 * This cancels the timer, then refunds, and then calles the check to have the check cancel the title
 */
function umc_vanity_cancel() {
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $userlevel = $UMC_USER['userlevel'];
    umc_header("Vanity Title Cancellation");
    $date_out = umc_timer_cancel($player, 'custom_title');
    if ($date_out) {
        $time_out = $date_out->format('Y-m-d H:i:s');
        $donator_str = false;
        if (strstr($userlevel, 'Donator')) {
            $donator_str = '&6++&f';
        } else if ($userlevel == 'Owner') {
            $donator_str = '&6++&f';
        }
        umc_echo("Title was active until $time_out");
        $date_now = umc_datetime();
        // substract the current day
        $interval = $date_now->diff($date_out);
        $days_interval = $interval->days;
        $hours_interval = $interval->h;
        umc_echo("This means you had $days_interval days and $hours_interval hours left.");
        $title = umc_vanity_get_title();
        if ($donator_str) {
            $title = str_replace($donator_str, '', $title);
        }
        $fixed_title = substr($title, 2, -3);
        $quote_arr = umc_vanity_quote_title($fixed_title);
        umc_echo("Your title cost {$quote_arr['cost']} per day, started days are not refunded.");
        $days_interval--; // substract the current day
        $refund = $quote_arr['cost'] * $days_interval;
        if ($refund <= 0) {
            umc_echo("There is nothing to refund!");
        } else {
            umc_money(false, $player, $refund);
            umc_echo("You just got $refund Uncs refunded, and the title was removed!");
            umc_log('vanity', 'cancel', "$player refunded $refund");
        }
    } else {
        umc_echo("Your title did not have any days left and was removed");
    }
    umc_vanity_check(false); // remove the title
    umc_footer(true);
}

function umc_vanity_sanitize($vanity) {
    $pattern = '/[^-_=!@#%\|:a-zA-Z0-9& ]|&[g-z]/u';
    $check = preg_match_all($pattern, strtolower($vanity));
    // umc_error_notify(var_export($matches, true) . var_export($check, true));
    if ($check) {
        umc_error("Your title can only contain numbers, letters, &-color codes from 1-f and basic punctuation such as -_=!@#%|;:");
    } else {
        return true;
    }
}

function umc_vanity_set($days = false, $vanity = false) {
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $uuid = $UMC_USER['uuid'];
    $args = $UMC_USER['args'];
    $userlevel = $UMC_USER['userlevel'];

    if (!$days) {
        if (!isset($args[2])) {
            umc_error("{red}You need to specify a number of days");
        }
        $days = $args[2];
    }

    $vanity_raw = '';
    if (!$vanity) {
        // concatenate all into a string
        if (!isset($args[3])) {
            umc_error("{red}You need to specify the title you want to have. See {yellow}/helpme vanity");
        }
        for ($i=3; $i<count($args); $i++) {
            $vanity_raw .= " " . $args[$i];
        }
        $vanity = trim($vanity_raw);
    }

    // umc_echo("$userlevel");
    if (!is_numeric($days) || ($days < 1)) {
        umc_error("{red}You need to specify a number of days");
    }

    $donator_str = '';
    if (strstr($userlevel, 'Donator')){
        $donator_str = '&6++&f';
    } else if ($userlevel == 'Owner') {
        $donator_str = '&6++&f';
    }
    $final_title = ' [' . $vanity . '&f]';

    // check for invalid chars
    umc_vanity_sanitize($vanity);

    $quote_array = umc_vanity_quote_title($vanity);
    $total_cost = $quote_array['cost'] * $days;
    $balance = umc_money_check($player);

    if ($quote_array['length'] > 20) {
        umc_error("Your title is too long ({$quote_array['length']} vs. 20 max!");
    }

    if ($total_cost > $balance) {
        umc_error("You do not have enough money to pay for this title for $days days. You need $total_cost but have only $balance!");
    }

    // check if there is a title already set
    $check = umc_vanity_get_title();

    if ($final_title == $check . $donator_str) {
        umc_header("Vanity Title");
        umc_echo("The same title is already set and will be extended by $days days!");
    } else if ($check && ($final_title != $check . $donator_str)) {
        umc_error("You have a different title already set. You need to cancel that one first or set the same one to extend it!");
    } else { // no title set yet
        $uuid = umc_user2uuid($player);
        umc_header("Vanity Title");
        umc_echo("No title yet set, setting new one...");
        umc_exec_command("pex user $uuid suffix \"$final_title$donator_str\"", 'asConsole');
    }
    // set timer
    umc_money($player, false, $total_cost);

    umc_timer_set($player, 'custom_title', $days);
    $date_out = umc_timer_get($player, 'custom_title');
    $time_out = $date_out->format('Y-m-d H:i:s');

    $date_today = umc_datetime();

    $interval = $date_today->diff($date_out);
    $days_interval = $interval->days;
    $hours_interval = $interval->h;
    umc_echo("Your title [$vanity{white}] will expire on $time_out (in $days_interval days and $hours_interval hours)!");
    umc_echo("Your account has been debited $total_cost!");
    umc_log('vanity', 'set', "$player paid $total_cost for $vanity for $days days");
    umc_footer(true);
}

function umc_vanity_quote_title($title) {
    global $VANITY_PRICES;
    $color_no = $symbol_no = $alphanum_no = 0;

    $match_array = array(
        'color_no' => '/&[0-9a-f]|[☠]/',
        'alphanum_no' => '/\w/',
        'symbol_no' => '/[-=!@#%\|:]/',
    );

    foreach ($match_array as $type => $pattern) {
        $$type = preg_match_all($pattern, $title);
    }
    $alphanum_no = $alphanum_no - $color_no;

    $color_price = ($color_no * $VANITY_PRICES['color']);
    $alphanum_price = ($alphanum_no * $VANITY_PRICES['alphanum']);
    $symbol_price = ($symbol_no * $VANITY_PRICES['symbol']);
    $cost = $color_price + $alphanum_price + $symbol_price;

    $out = array(
        'length' => $alphanum_no + $symbol_no,
        'alphanum' => $alphanum_no,
        'symbol' => $symbol_no,
        'color' => $color_no,
        'alphanum_price' => $alphanum_price,
        'symbol_price' => $symbol_price,
        'color_price' => $color_price,
        'cost' => $cost,
    );
    return $out;
}

/*
 * this checks the current title and returns it.
 * if there is no title, return false
 */
function umc_vanity_get_title() {
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $uuid = umc_user2uuid($player);
    $sql = "SELECT value FROM minecraft_srvr.permissions WHERE name='$uuid' AND permission='suffix';";
    $D = umc_mysql_fetch_all($sql);
    if (count($D) > 0) {
        $row = $D[0];
        if (strlen($row['value']) > 0) {
            return $row['value'];
        }
    }
    return false;
}

function umc_vanity_web() {
    $sql = "SELECT name, value, username
            FROM minecraft_srvr.permissions
            LEFT JOIN minecraft_srvr.UUID ON name=UUID
            WHERE permission='suffix' AND value NOT LIKE \"\" AND type=1
            ORDER BY name;";
    $D = umc_mysql_fetch_all($sql);
    $out = "<table>\n<tr><th>No.</th><th>Username</th><th>Title</th></tr>\n";
    $count = 0;
    foreach ($D as $row) {
        $user = $row['username'];
        $count++;
        $title = $row['value'];
        $title_formatted = umc_mc_color_to_html("$title");
        $out .= "<tr><td>$count</td><td style=\"font-weight:bold;\">$user</td><td style=\"text-shadow:2px 2px #000; color:#fff; background-color:#777; padding:4px;\">$title_formatted</td></tr>\n";
    }
    $out .= "</table>\n";
    return $out;
}
