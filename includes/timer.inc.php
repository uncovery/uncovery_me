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
 * This is a central modle to allow time-based events and expiry for statuses as 
 * well as some general time management tools, time zone conversion etc.
 */

/*
 * returns a date-time object with todays timezone
 * get a MySQL timestamp with $now = $date_now->format("Y-m-d H:i:s");
 * The timezone here should be in the config file.
 */
function umc_datetime($date = NULL) {
    if ($date != NULL) {
        $date .= "+08:00"; // incoming timezones are already HKT
    }
    $date_new = new DateTime($date);
    $date_new->setTimezone(new DateTimeZone('Asia/Hong_Kong'));
    return $date_new;
}

/**
 * Converts a JSON date to a DateTime Object
 *
 * @param string $json_date
 * @return DateTimeObj or false
 */
function umc_timer_from_json($json_date) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    // do we have a timezone string or not?
    if (strlen($json_date) == 13) {
        $json_date .= "-0000";
    }
    //1433044095000 <- No timezone
    //1365004652303-0500 <- timezone

    $pattern = '/(\d{10})(\d{3})([\+\-]\d{4})/';
    $format  = "U.u.O";
    $mask    = '%2$s.%3$s.%4$s';

    $matches = false;
    $r = preg_match($pattern, $json_date, $matches);
    if (!$r) {
        XMPP_ERROR_trigger("Failed to match date in $json_date");
    }
    $buffer = vsprintf($mask, $matches);
    $result = DateTime::createFromFormat($format, $buffer);
    if (!$result) {
        XMPP_ERROR_trigger(sprintf('Failed To Create from Format "%s" for "%s".', $format, $buffer));
    }
    return $result;

}

// sets a timer
// if timer exists, it will add the days to the existing timer
// if existing timer is expired, new timer is form today
function umc_timer_set($user, $type, $days = 0, $hours = 0, $minutes = 0) {
    // check if the same timer type is already set
    $existing_timer = umc_timer_get($user, $type);
    $date_today = umc_datetime();
    $today = $date_today->format('Y-m-d H:i:s');

    $new_timer = false;
    if (!$existing_timer) { // no timer exits or is expired
        $existing_timer = $today; // new timer from today
        $new_timer = true;
    }

    $date_timeout = umc_datetime(); // substract the current day
    $date_timeout->add(new DateInterval('P'.$days.'DT'.$hours.'H'.$minutes.'M'));
    $timeout = $date_timeout->format('Y-m-d H:i:s');

    if ($new_timer) {
        $sql = "INSERT INTO minecraft_srvr.timers (`type`,`time_set`,`time_out`,`username`) VALUES ('$type', '$today' , '$timeout', '$user');";
        umc_log('timer', 'new', "$type timer from $today to $timeout for $user");
    } else {
        $sql = "UPDATE minecraft_srvr.timers SET time_out='$timeout', time_set='$today'  WHERE username='$user' AND type='$type' LIMIT 1;";
        umc_log('timer', 'update', "$type timer from $today to $timeout for $user");
    }
    // umc_echo($sql);
    $rst = umc_mysql_query($sql);
    $count = umc_mysql_affected_rows(true);
    return $count;
}
/*
 * cancels an existing timer. returns expiry date if the timer was still active
 * returns false if there was no timer set or if timer was already expired
 * otherwise returns datetime object
 */
function umc_timer_cancel($user, $type) {
    $existing_timer = umc_timer_get($user, $type);
    if ($existing_timer) {
        $sql = "DELETE FROM minecraft_srvr.timers WHERE username='$user' AND type='$type';";
        umc_mysql_query($sql, true);
        umc_log('timer', 'cancel', "$type timer for $user");
        return $existing_timer;
    } else {
        return false;
    }
}

// checks if a timer is expired or not
// returns datetime object
// expired timers will be removed
function umc_timer_get($user, $type) {
    // check if a timer is set
    $sql = "SELECT time_out FROM minecraft_srvr.timers WHERE username='$user' AND type='$type';";
    $D = umc_mysql_fetch_all($sql);

    if (count($D) > 0) {
        // no, check if timed out

        $date_now = umc_datetime(); // substract the current day
        $date_row = umc_datetime($D[0]['time_out']);
        // difference in seconds ofr check
        $diff = $date_row->getTimestamp() - $date_now->getTimestamp();

        if ($diff > 0) {
            return $date_row;
        } else {
            $sql_del = "DELETE FROM minecraft_srvr.timers WHERE username='$user' AND type='$type';";
            umc_log('timer', 'removed', "$type timer for $user");
            umc_mysql_query($sql_del, true);
            return false;
        }
    } else {
        return false; // no such timer set
    }
}

/*
 * creates a nice display string for the time difference between to DateTime objects
 */
function umc_timer_format_diff($datetime1, $datetime2 = false) {
    if (!$datetime2) { // create current time if not otherwise set
        $datetime2 = umc_datetime();
    }
    // get diff in seconds
    $seconds = abs($datetime1->getTimestamp() - $datetime2->getTimestamp());
    $days = floor($seconds / 86400); // full days
    $leftover_hours = $seconds - ($days * 86400);
    $hours = floor($leftover_hours / 3600);
    $leftover_minutes = $leftover_hours - ($hours * 3600);
    $minutes = floor($leftover_minutes / 60);
    //$leftover_seconds = $leftover_minutes - ($minutes * 60);

    if ($days > 0) {
        return "$days days, $hours hours";
    } else if ($hours > 0) {
        return "$hours hours, $minutes min";
    } else {
        return "$minutes minutes";
    }
}

/*
 * sends back a raw second count of how much time is left
 */
function umc_timer_raw_diff($datetime1, $datetime2 = false) {
    if (!$datetime2) { // create current time if not otherwise set
        $datetime2 = umc_datetime();
    }
    $seconds = abs($datetime1->getTimestamp() - $datetime2->getTimestamp());
    return $seconds;
}

function umc_seconds_to_time($time) {
    $dtF = new DateTime("@0");
    $dtT = new DateTime("@$time");
    return $dtF->diff($dtT)->format('%a days, %h hours, %i min');
}

function umc_timer_array_diff($datetime1, $datetime2 = false) {
   if (!$datetime2) { // create current time if not otherwise set
        $datetime2 = umc_datetime();
    }
    $interval = $datetime1->diff($datetime2);
    return $interval;
}

function umc_time_until_restart() {
    global $UMC_SETTING;
    $restart_time = $UMC_SETTING['restart_time'];

    $target_date = umc_datetime("tomorrow $restart_time");
    $interval = umc_timer_array_diff($target_date);
    $out = '';
    if ($interval->h > 0) {
        $out .= $interval->h . " hours and ";
    }
    $out .= $interval->i . " min";
    return $out;
}

