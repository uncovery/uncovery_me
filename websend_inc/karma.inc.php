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
 * This allows users to give reddit-like karma to other users. It also includes 
 * a web interface to display the karma in wordpress.
 */
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
    'disabled' => false,
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
    $age_sql = "SELECT DATEDIFF(NOW(),firstlogin) as online_days FROM minecraft_srvr.UUID
        WHERE uuid='$sender_uuid'";
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
    $rec_age_sql = "SELECT DATEDIFF(NOW(),firstlogin) as online_days FROM minecraft_srvr.UUID
        WHERE uuid='$receiver_uuid'";
    $rec_age_data = umc_mysql_fetch_all($rec_age_sql);
    $rec_online_time = $rec_age_data[0]['online_days'];

    // receiver user level
    $receiver_lvl = umc_userlevel_get($receiver_uuid);
    if (($rec_online_time < 10) || ($receiver_lvl == 'Guest')) {
        umc_error("You cannot give karma to this user, he is too new!");
    }

    // check if there is the same karma already, otherwise fix
    $sql = "SELECT karma FROM minecraft_srvr.karma
        WHERE sender_uuid='$sender_uuid' AND receiver_uuid='$receiver_uuid';";
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
            $update_sql = "UPDATE minecraft_srvr.karma set karma=$new_karma
                WHERE sender_uuid='$sender_uuid' AND receiver_uuid='$receiver_uuid';";
            umc_mysql_query($update_sql, true);
        }
    } else {
        umc_echo("Giving $new_karma karma to $receiver.");
        $update_sql = "INSERT INTO minecraft_srvr.karma (sender_uuid, receiver_uuid, karma)
            VALUES ('$sender_uuid', '$receiver_uuid', $new_karma);";
        umc_mysql_query($update_sql, true);
    }
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

    # Get the user's + and - karma entries
    $pos_sql = "SELECT SUM(karma) AS sum_karma
	FROM minecraft_srvr.karma
        LEFT JOIN minecraft_srvr.UUID AS senders ON sender_uuid=uuid
        WHERE receiver_uuid='$receiver_uuid' AND karma > 0
	  AND senders.lot_count > 0
        GROUP BY receiver_uuid";
    $neg_sql = "SELECT SUM(karma) AS sum_karma
	FROM minecraft_srvr.karma
        LEFT JOIN minecraft_srvr.UUID AS senders ON sender_uuid=uuid
        WHERE receiver_uuid='$receiver_uuid' AND karma < 0
	  AND senders.lot_count > 0
        GROUP BY receiver_uuid";

    $pos_data = umc_mysql_fetch_all($pos_sql);
    $neg_data = umc_mysql_fetch_all($neg_sql);

    # If the user has no karma entries, use 0
    if (count($pos_data) > 0) {
        $pos_karma = $pos_data[0]['sum_karma'];
    } else {
        $pos_karma = 0;
    }
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
    $all_sql = "SELECT SUM(karma), receiver_uuid, receivers.username AS username
        FROM minecraft_srvr.karma
        LEFT JOIN minecraft_srvr.UUID AS receivers ON receiver_uuid=receivers.uuid
        LEFT JOIN minecraft_srvr.UUID AS senders ON sender_uuid=senders.uuid
	WHERE senders.lot_count > 0
        GROUP BY receiver_uuid
        ORDER BY SUM(karma) DESC, username ASC";
    $all_data = umc_mysql_fetch_all($all_sql);
    $out_data = array();
    foreach ($all_data as $row) {
        $receiver = $row['username'];
        $receiver_uuid = $row['receiver_uuid'];
        if (!isset($members[$receiver_uuid]) || ($receiver == 'uncovery')) {
            continue;
        }
        $sql = "SELECT karma FROM minecraft_srvr.karma
            LEFT JOIN minecraft_srvr.UUID AS sender ON sender_uuid=sender.uuid
            WHERE receiver_uuid = '$receiver_uuid'
	      AND sender.lot_count > 0";
        $sender_data = umc_mysql_fetch_all($sql);
        $pos_karma = 0;
        $neg_karma = 0;
        foreach ($sender_data as $send_row) {
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
    $sql = "SELECT SUM(karma) as sum_karma, receivers.username as receiver_name FROM minecraft_srvr.karma
        LEFT JOIN minecraft_srvr.UUID as senders ON sender_uuid=senders.UUID
        LEFT JOIN minecraft_srvr.UUID as receivers ON receiver_uuid=receivers.UUID
        WHERE senders.lot_count > 0 AND receivers.lot_count > 0
        GROUP BY receivers.username
        ORDER BY sum(karma) DESC LIMIT 0,10";
    $D = umc_mysql_fetch_all($sql);
    umc_echo("Top ten Karma users:");
    umc_echo(" ∞     =>    Uncovery");
    foreach ($D as $row) {
        $sum_karma = $row['sum_karma'];
        $receiver = $row['receiver_name'];
        if (!umc_user_is_banned($receiver) && $receiver != 'uncovery') {
            umc_echo("$sum_karma    =>    $receiver");
        }
    }
}

function umc_bottomkarma() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $sql = "SELECT SUM(karma) as sum_karma, receivers.username as receiver_name FROM minecraft_srvr.karma
        LEFT JOIN minecraft_srvr.UUID as senders ON sender_uuid=senders.UUID
        LEFT JOIN minecraft_srvr.UUID as receivers ON receiver_uuid=receivers.UUID
        WHERE senders.lot_count > 0 AND receivers.lot_count > 0
        GROUP BY receivers.username
        HAVING sum(karma) < 0
        ORDER BY sum(karma) ASC LIMIT 0,10";
    $D = umc_mysql_fetch_all($sql);
    umc_echo("Bottom ten Karma users:");
    umc_echo("-∞     =>    Uncovery");
    foreach ($D as $row) {
        $sum_karma = $row['sum_karma'];
        $receiver = $row['receiver_name'];
        if (!umc_user_is_banned($receiver)) {
            umc_echo("$sum_karma    =>    $receiver");
        }
    }
}

/*
 CREATE TABLE IF NOT EXISTS `karma` (
  `karma_id` int(11) NOT NULL,
  `sender_uuid` varchar(36) NOT NULL,
  `receiver_uuid` varchar(36) NOT NULL,
  `karma` tinyint(1) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `karma`
  ADD PRIMARY KEY (`karma_id`),
  ADD KEY `sender_uuid` (`sender_uuid`),
  ADD KEY `receiver_uuid` (`receiver_uuid`),
  ADD KEY `sender_uuid_2` (`sender_uuid`,`receiver_uuid`);

ALTER TABLE `karma`
  MODIFY `karma_id` int(11) NOT NULL AUTO_INCREMENT;
 */
