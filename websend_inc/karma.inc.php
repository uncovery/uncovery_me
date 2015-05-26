<?php

global $UMC_SETTING, $WS_INIT;

$WS_INIT['karma'] = array(
    'default' => array(
        'help' => array(
            'title' => 'User Karma',
            'short' => 'Give / take karma to other users as recognition',
            'long' => 'Karma is a way to reward users that have done good for the community. You can give +1, 0 or -1 karma to a user. You can change your opinion anytime.',
            ),
    ),
    '+' => array(
        'help' => array(
            'short' => 'Give +1 karma to another user',
            'args' => '<user>',
            'long' => 'You cannot give more than 1 karma. User keeps your karma until you give -1 or 0.',
        ),
        'function' => 'umc_setkarma',
    ),
    '-' => array(
        'help' => array(
            'short' => 'Give -1 karma to another user',
            'args' => '<user>',
            'long' => 'You cannot give more than -1 karma. The user Keeps that -1 karma until you give +1 or 0.',
        ),
        'function' => 'umc_setkarma',
    ),
    '0' => array(
        'help' => array(
            'short' => 'Give 0 karma to another user',
            'args' => '<user>',
            'long' => 'This resets the karma given to ther user from you to 0;',
        ),
        'function' => 'umc_setkarma',
    ),
    'get' => array(
        'help' => array(
            'short' => 'Get either your own karma or that of another user',
            'args' => '[user]',
            'long' => 'Omitting {yellow}[user]{gray} shows your karma. Otherwise the karma of the user given.',
        ),
        'function' => 'umc_getkarma',
    ),
    'top' => array(
        'help' => array(
            'short' => 'Get the top 10 karma users.',
            'args' => '',
            'long' => 'This omits banned users.',
        ),
        'function' => 'umc_topkarma',
    ),
    'bottom' => array(
        'help' => array(
            'short' => 'Get the top 10 karma users',
            'args' => '',
            'long' => 'This omits banned users.',
        ),
        'function' => 'umc_bottomkarma',
    ),
    'disabled' => true,
    'events' => false,
);

/**
 * This allows users in-game to set karma for another user
 *
 * @global type $UMC_USER
 */
function umc_setkarma() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $sender_uuid = $UMC_USER['uuid'];
    $sender = $UMC_USER['username'];
    $sender_lvl = $UMC_USER['userlevel'];
    $args = $UMC_USER['args'];

    if ($sender_lvl == 'guest') {
        umc_error('Guests cannot give karma to others');
    }

    // get user age
    $age_sql = "SELECT DATEDIFF(NOW(),firstlogin) as online_days FROM minecraft_srvr.UUID "
        . "WHERE uuid='$sender_uuid'";
    $age_data = umc_mysql_fetch_all($age_sql);

    $online_time = $age_data[0]['online_days'];
    if ($online_time < 10) {
        umc_error("You cannot give karma yet, you are too new on the server!");
    }

    $karma_arr = array('+' => 1, '-' => -1, '0' => 0);
    if (isset($args[1]) && array_key_exists($args[1], $karma_arr)) {
        $new_karma = $karma_arr[$args[1]];
    } else {
        umc_error('You need to indicate the karma value with +,- or 0;');
    }

    if (!isset($args[2])) {
        umc_error('You need to enter the user to give karma to!;');
    } else if (strtolower($args[2]) == strtolower($sender)) {
        umc_error('You cannot give karma to yourself!;');
    }

    $receiver = umc_sanitize_input($args[2], 'player');
    if ($receiver == 'uncovery') {
        umc_error("Thou shalt not judge the maker of all things!");
    }
    // get receiver UUID
    $receiver_uuid = umc_user2uuid($receiver);

    // get user age
    $rec_age_sql = "SELECT DATEDIFF(NOW(),firstlogin) as online_days FROM minecraft_srvr.UUID "
        . "WHERE uuid='$receiver_uuid'";
    $rec_age_data = umc_mysql_fetch_all($rec_age_sql);
    $rec_online_time = $rec_age_data[0]['online_days'];

    // receiver user level
    $receiver_lvl = umc_get_uuid_level($receiver_uuid);
    if (($rec_online_time < 10) || ($receiver_lvl == 'Guest')) {
        umc_error("You cannot give karma to this user, he is too new!");
    }

    // check if there is the same karma already, otherwise fix
    $sql = "SELECT karma FROM minecraft_srvr.karma "
        . "WHERE sender_uuid='$sender_uuid' AND receiver_uuid='$receiver_uuid';";
    $data_arr = umc_mysql_fetch_all($sql);
    if (count($data_arr) > 0) {
        $oldkarma = $data_arr[0]['karma'];
        if ($new_karma == $oldkarma) {
            umc_echo("You already gave $receiver $oldkarma karma!");
            // show the karma of the recipient to the user
            umc_getkarma($receiver_uuid);
            exit;
        } else {
            umc_echo("Giving $receiver $new_karma karma instead of $oldkarma karma.");
            $update_sql = "UPDATE minecraft_srvr.karma set karma=$new_karma "
                    . "WHERE sender_uuid='$sender_uuid' AND receiver_uuid='$receiver_uuid';";
        }
    } else {
        umc_echo("Giving $new_karma karma to $receiver.");
        $update_sql = "INSERT INTO minecraft_srvr.karma (sender_uuid, receiver_uuid, karma) "
                . "VALUES ('$sender_uuid', '$receiver_uuid', $new_karma);";
    }
    umc_mysql_query($sql, true);
    umc_log('karma', 'set', "$sender ($sender_uuid) set $new_karma for $receiver ($receiver_uuid)");
    umc_getkarma($receiver_uuid);
}

/**
 * this returns the karma of a target or the current player, if no target set
 * returns either as value or as websend message, depening on the scenario
 *
 * @global type $UMC_USER
 * @global type $UMC_ENV
 * @param type $target
 * @param type $return
 * @return string
 */
function umc_getkarma($target = false, $return = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER, $UMC_ENV;
    if ($UMC_ENV == 'websend') {
        $user = $UMC_USER['username'];
        $user_uuid = $UMC_USER['uuid'];
        $args = $UMC_USER['args'];
    } else {
        $user = NULL;
        $args = NULL;
    }

    // do we have a target set? If no, assume current user
    if ($target) {
        $receiver_uuid = $target;
        $receiver = umc_user2uuid($target);
    } else if (!isset($args[2])) { // get user's own karma
        $receiver = $user;
        $receiver_uuid = $user_uuid;
    } else { // get argument from command
        $receiver = umc_sanitize_input($args[2], 'player');
        $receiver_uuid = umc_user2uuid($receiver);
    }

    if ($receiver == 'uncovery') {
        if ($return) {
            return "n/a";
        } else if ($UMC_ENV == 'websend') {
            umc_error("Thou shalt not judge the maker of all things!");
        } else {
            return;
        }
    }

    $banned = umc_user_is_banned($receiver_uuid);
    if ($banned) {
        if ($return) {
            return "(banned)";
        }
        umc_echo("User $receiver is banned");
        exit;
    }

    $pos_sql = "SELECT SUM(karma) AS sum_karma, receiver_uuid, username FROM minecraft_srvr.karma "
        . "LEFT JOIN minecraft_srvr.UUID on receiver_uuid=uuid "
        . "WHERE receiver_uuid='$receiver_uuid' AND karma > 0 AND sender_uuid IN (SELECT uuid FROM minecraft_worldguard.region_players "
        . "LEFT JOIN minecraft_worldguard.user ON user_id=id "
        . "WHERE owner=1 GROUP BY uuid) "
        . "GROUP BY receiver_uuid ORDER BY SUM(karma) DESC, username ASC;";
    $pos_data = umc_mysql_fetch_all($pos_sql);

    if (count($pos_data) > 0) {
        $pos_karma = $pos_data[0]['sum_karma'];
    } else {
        $pos_karma = 0;
    }

    $neg_sql = "SELECT SUM(karma) AS sum_karma, receiver_uuid, username FROM minecraft_srvr.karma "
        . "LEFT JOIN minecraft_srvr.UUID on receiver_uuid=uuid "
        . "WHERE receiver_uuid='$receiver_uuid' AND karma < 0 AND sender_uuid IN (SELECT uuid FROM minecraft_worldguard.region_players "
        . "LEFT JOIN minecraft_worldguard.user ON user_id=id "
        . "WHERE owner=1 GROUP BY uuid) "
        . "GROUP BY receiver_uuid ORDER BY SUM(karma) DESC, username ASC;";
    $neg_data = umc_mysql_fetch_all($neg_sql);
    if (count($neg_data) > 0) {
        $neg_karma = $neg_data[0]['sum_karma'];
    } else {
        $neg_karma = 0;
    }


    $karma = $pos_karma + $neg_karma;

    if ($pos_karma == NULL && $neg_karma == NULL) {
        if ($return) {
            return 'n/a';
        }
        umc_echo("User $receiver has no karma record");
    } else {
        if ($pos_karma == NULL) {
            $pos_karma = 0;
        }
        if ($neg_karma == NULL) {
            $neg_karma = 0;
        }
        if ($return) {
            return "$pos_karma/$neg_karma";
        }
        umc_echo("User $receiver has $karma karma ($pos_karma/$neg_karma).");
    }
}

function umc_webkarma() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $members = umc_get_active_members();
    // list onliny active receivers
    $all_sql = "SELECT sum(karma), receiver_uuid, username
        FROM minecraft_srvr.karma
        LEFT JOIN minecraft_srvr.UUID on receiver_uuid=UUID.UUID
        GROUP BY receiver_uuid
        ORDER BY sum(karma) DESC, username ASC";
    $all_data = umc_mysql_fetch_all($all_sql);
    $out_data = array();
    foreach ($all_data as $row) {
        $receiver = $row['username'];
        $receiver_uuid = $row['receiver_uuid'];
        if (!isset($members[$receiver_uuid]) || ($receiver == 'uncovery')) {
            continue;
        }
        $sql = "SELECT karma, sender_uuid, username FROM minecraft_srvr.karma 
            LEFT JOIN minecraft_srvr.UUID on sender_uuid=UUID
            WHERE receiver_uuid = '$receiver_uuid'";
        $sender_data = umc_mysql_fetch_all($sql);
        $pos_karma = 0;
        $neg_karma = 0;
        foreach ($sender_data as $send_row) {
            $sender_uuid = $send_row['sender_uuid'];
            if (!isset($members[$sender_uuid])) {
                continue;
            }
            if ($send_row['karma'] > 0) {
                $pos_karma = $pos_karma + $send_row['karma'];
            } else if ($send_row['karma'] < 0) {
                $neg_karma = $neg_karma + $send_row['karma'];
            }
        }
        $sum_karma = $pos_karma + $neg_karma;
        $out_data[] = array('username' => $receiver, 'karma' => $sum_karma, 'Positive Karma' => $pos_karma, 'Negative Karma' => $neg_karma);
    }
    echo umc_web_table("karma", 1, $out_data, '', array(), array('username'), false);
}

function umc_topkarma() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $sql = "SELECT SUM(karma) AS sum_karma, receiver FROM minecraft_srvr.karma "
        . "WHERE sender IN (SELECT name FROM minecraft_worldguard.region_players "
        . "LEFT JOIN minecraft_worldguard.user ON user_id=id "
        . "WHERE owner=1 GROUP BY uuid) "
        . "GROUP BY receiver ORDER BY SUM(karma) DESC, receiver ASC LIMIT 0,10";
    $rst = mysql_query($sql);
    umc_echo("Top ten Karma users:");
    umc_echo(" ∞     =>    Uncovery");
    while ($row = mysql_fetch_array($rst, MYSQL_ASSOC)) {
        $sum_karma = $row['sum_karma'];
        $receiver = $row['receiver'];
        if (!umc_user_is_banned($receiver) && $receiver != 'uncovery') {
            umc_echo("$sum_karma    =>    $receiver");
        }
    }
}

function umc_bottomkarma() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $sql = "SELECT SUM(karma) AS sum_karma, receiver FROM minecraft_srvr.karma "
        . "WHERE sender IN (SELECT name FROM minecraft_worldguard.region_players "
        . "LEFT JOIN minecraft_worldguard.user ON user_id=id "
        . " WHERE owner=1 GROUP BY name) "
        . "GROUP BY receiver ORDER BY SUM(karma) ASC, receiver ASC LIMIT 0,10;";
    $rst = mysql_query($sql);
    umc_echo("Bottom ten Karma users:");
    umc_echo("-∞     =>    Uncovery");
    while ($row = mysql_fetch_array($rst, MYSQL_ASSOC)) {
        $sum_karma = $row['sum_karma'];
        $receiver = $row['receiver'];
        if (!umc_user_is_banned($receiver)) {
            umc_echo("$sum_karma    =>    $receiver");
        }
    }
}

?>
