<?php
/*
 * returns a date-time object with todays timezone
 * get a MySQL timestamp with $now = $date_now->format("Y-m-d H:i:s");
 */
function umc_datetime($date = NULL) {
    if ($date != NULL) {
        $date .= "+08:00"; // incoming timezones are already HKT
    }
    $date_new = new DateTime($date);
    $date_new->setTimezone(new DateTimeZone('Asia/Hong_Kong'));
    return $date_new;
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

