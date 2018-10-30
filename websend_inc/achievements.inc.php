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


global $UMC_ACHIEVEMENTS;

$UMC_ACHIEVEMENTS = array(
    'money' => array(
        'description' => 'This achievement is given for accumulating money',
        'levels' => array(
            0 => array('value' => 0, 'title' => false, 'reward' => false),
            1 => array('value' => 1000, 'title' => 'Beggar', 'reward' => false),
            2 => array('value' => 10000, 'title' => 'Student', 'reward' => false),
            3 => array('value' => 20000, 'title' => 'Worker', 'reward' => false),
            4 => array('value' => 50000, 'title' => 'Investor', 'reward' => false),
            5 => array('value' => 100000, 'title' => 'Manager', 'reward' => false),
            6 => array('value' => 200000, 'title' => 'CEO', 'reward' => false),
            7 => array('value' => 500000, 'title' => 'Chairman', 'reward' => false),
            8 => array('value' => 1000000, 'title' => 'Mogul', 'reward' => false),
        ),
        'value_measure' => 'Uncs in account',
        'check_one' => "SELECT balance as value FROM `minecraft_iconomy`.`mineconomy_accounts` WHERE uuid='%s';",
        'check_all' => "SELECT balance as value, uuid FROM `minecraft_iconomy`.`mineconomy_accounts`;",
        'check_method' => 'sql',
    ),
    'vote lottery' => array(
        'description' => 'Vote for the server',
        'levels' => array(
            0 => array('value' => 0, 'title' => false, 'reward' => false),
            1 => array('value' => 10, 'title' => "Playing it safe", 'reward' => false),
            2 => array('value' => 100, 'title' => "Risk averse", 'reward' => false),
            3 => array('value' => 200, 'title' => "Trying your luck", 'reward' => false),
            4 => array('value' => 500, 'title' => "Risk taker", 'reward' => false),
            5 => array('value' => 1000, 'title' => "Gambler", 'reward' => false),
            6 => array('value' => 2000, 'title' => "Addict", 'reward' => false),
            7 => array('value' => 5000, 'title' => "High Roller", 'reward' => false),
            8 => array('value' => 10000, 'title' => "Casino VIP", 'reward' => false),
        ),
        'value_measure' => 'Lifetime Votes',
        'check_one' => "SELECT count(vote_id) as value FROM minecraft_log.votes_log WHERE username='%s'",
        'check_all' => "SELECT count(vote_id) as value, username as uuid FROM minecraft_log.votes_log GROUP BY username",
        'check_method' => 'sql',
    ),
    'blog comments' => array(
        'description' => 'Write many blog comments!',
        'levels' => array(
            0 => array('value' => 1, 'title' => false, 'reward' => false),
            1 => array('value' => 5, 'title' => "Silent", 'reward' => false),
            2 => array('value' => 10, 'title' => "Small Talk", 'reward' => false),
            3 => array('value' => 20, 'title' => "Chatty", 'reward' => false),
            4 => array('value' => 50, 'title' => "Talkative", 'reward' => false),
            5 => array('value' => 100, 'title' => "Conversationalist", 'reward' => false),
            6 => array('value' => 150, 'title' => "Author", 'reward' => false),
            7 => array('value' => 200, 'title' => "Novelist", 'reward' => false),
            8 => array('value' => 500, 'title' => "Book Writer", 'reward' => false),
            9 => array('value' => 1000, 'title' => "Chat Bot", 'reward' => false),
        ),
        'value_measure' => 'Comments',
        'check_one' => "SELECT count(comment_ID) as value, wp_comments.comment_author, meta_value
            FROM minecraft.wp_comments
            LEFT JOIN minecraft.wp_usermeta ON wp_usermeta.user_id=wp_comments.user_id
            WHERE meta_key='minecraft_uuid' AND meta_value='%s'
            GROUP BY comment_author",
        'check_all' => "SELECT count(comment_ID) as value, meta_value as uuid
            FROM minecraft.wp_comments
            LEFT JOIN minecraft.wp_usermeta ON wp_usermeta.user_id=wp_comments.user_id
            WHERE meta_key='minecraft_uuid'
            GROUP BY comment_author",
        'check_method' => 'sql',
    ),
    'user voting' => array(
        'description' => 'Vote for the User promotions',
        'levels' => array(
            0 => array('value' => 0, 'title' => false, 'reward' => false),
            1 => array('value' => 1, 'title' => "Communist", 'reward' => false),
            2 => array('value' => 10, 'title' => "Oppressed", 'reward' => false),
            3 => array('value' => 50, 'title' => "Influencer", 'reward' => false),
            4 => array('value' => 100, 'title' => "Democrat", 'reward' => false),
            5 => array('value' => 200, 'title' => "Revolutionary", 'reward' => false),
            6 => array('value' => 250, 'title' => "President", 'reward' => false),
            7 => array('value' => 500, 'title' => "Voting Machine", 'reward' => false),
            8 => array('value' => 1000, 'title' => "Voting Machine Hacker", 'reward' => false),
        ),
        'value_measure' => 'Lifetime Votes',
        'check_one' => "SELECT count(vote_id) as value FROM minecraft_srsr.proposals_votes group by voter_uuid WHERE voter_uuid='%s'",
        'check_all' => "SELECT count(vote_id) as value, voter_uuid as uuid FROM minecraft_srvr.proposals_votes GROUP BY voter_uuid",
        'check_method' => 'sql',
    ),
    'mails sent' => array(
        'description' => 'Send emails',
        'levels' => array(
            0 => array('value' => 0, 'title' => false, 'reward' => false),
            1 => array('value' => 1, 'title' => "Silent Bob", 'reward' => false),
            2 => array('value' => 10, 'title' => "Hermit", 'reward' => false),
            3 => array('value' => 50, 'title' => "Pen Pal", 'reward' => false),
            4 => array('value' => 100, 'title' => "Author", 'reward' => false),
            5 => array('value' => 200, 'title' => "Calligrapher", 'reward' => false),
            6 => array('value' => 250, 'title' => "Chat Bot", 'reward' => false),
            7 => array('value' => 500, 'title' => "Spammer", 'reward' => false),
        ),
        'value_measure' => 'Mails',
        'check_one' => "SELECT count(msg_id) as value FROM minecraft_srvr.user_mail group by sender_uuid WHERE sender_uuid='%s'",
        'check_all' => "SELECT count(msg_id) as value, sender_uuid as uuid FROM minecraft_srvr.user_mail GROUP BY sender_uuid",
        'check_method' => 'sql',
    ),    



                //
    /*
    'sale' => array(
        'description' => 'Make sales in the shop to this turnover',
        'levels' => array(
            1 => array('value' => 10, 'title' => false, 'reward' => false,),
            2 => array('value' => 100, 'title' => false, 'reward' => false,),
            3 => array('value' => 200, 'title' => false, 'reward' => false,),
            4 => array('value' => 500, 'title' => false, 'reward' => false,),
            5 => array('value' => 1000, 'title' => false, 'reward' => false,),
            6 => array('value' => 2000, 'title' => false, 'reward' => false,),
            7 => array('value' => 5000, 'title' => false, 'reward' => false,),
            8 => array('value' => 10000, 'title' => false, 'reward' => false,),
        ),
    ),
     *
     */
);

$WS_INIT['achievements'] = array(
    'default' => array(
        'help' => array(
            'title' => 'User Achievements',
            'short' => 'Manage all your acheivements',
            'long' => 'Achievements show your progress in the game and you are rewarded for reaching certain levels of achievements.',
            ),
    ),
    'list' => array(
        'help' => array(
            'short' => 'Give +1 karma to another user',
            'args' => '<user>',
            'long' => 'You cannot give more than 1 karma. User keeps your karma until you give -1 or 0.',
        ),
        'function' => 'umc_setkarma',
    ),
    'disabled' => false,
    'events' => array(
        'user_directory' => 'umc_achievements_display_web',
    ),
);

/**
 * Iterate all achievements and check what level (active) users have
 * and writes it to a database
 *
 * @global array $UMC_ACHIEVEMENTS
 * @param type $user
 * @return type
 */
function umc_achievements_update($uuid = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_ACHIEVEMENTS;

    if (!$uuid) {
        $users = umc_get_active_members('uuid');
        $check_type = 'check_all';
    } else {
        $check_type = 'check_one';
    }

    foreach ($UMC_ACHIEVEMENTS as $A => $a) {
        switch ($a['check_method']) { // SQL or function
            case 'sql':
                $check_query = $a[$check_type];
                if ($check_type == 'check_all') {
                    // if we check all users, first query all user records and then match them with the active members list
                    $L = umc_mysql_fetch_all($check_query);
                    $all_levels = array();
                    foreach ($L as $l) {
                        $level_uuid = $l['uuid'];
                        $all_levels[$level_uuid] = $l['value'];
                    }

                    // now that we have an array of all level records, match with users in active lit
                    foreach ($users as $uuid) {
                        if (isset($all_levels[$uuid])) {
                            // find out the achievement level
                            $value = $all_levels[$uuid];
                            $level = umc_achievements_level_check($value, $A);
                            // insert it into the DB
                            umc_achievements_level_record($A, $uuid, $level);
                        }
                    }
                } else {
                    // if we need only one user, insert the UUID into the query
                    $check_sql = sprintf($check_query, $uuid);
                    // execute the adjusted query
                    $L = umc_mysql_fetch_all($check_sql);
                    if (count($L) == 1) {
                        $level = umc_achievements_level_check($L[0]['value'], $A);
                        // insert it into the DB
                        umc_achievements_level_record($A, $uuid, $level);
                    }
                }
                break;

            case 'function':
                // nothing happens yet since we dont have achievements that need functions yet
                break;
        }
    }
}


/**
 * Iterate an achievement to find out what the current userlevel is
 *
 * @global array $UMC_ACHIEVEMENTS
 * @param type $value
 * @param type $achievement
 * @return int
 */
function umc_achievements_level_check($value, $achievement) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_ACHIEVEMENTS;
    $levels = $UMC_ACHIEVEMENTS[$achievement]['levels'];

    $current_level = 0;
    foreach ($levels as $level => $l_data) {
        XMPP_ERROR_trace("Current level is ", $current_level);
        XMPP_ERROR_trace("checking for level ", $level);
        $l_value = $l_data['value'];
        XMPP_ERROR_trace("level value is ", $l_value);
        if (!isset($levels[$level + 1]) && ($value > $l_value)) { // we have the top level
            XMPP_ERROR_trace("Reached top level!", "My value: $value, Max value: $l_value");
            return $level;
        } else if ($value == $l_value) { // we have exactly the current level
            XMPP_ERROR_trace("found exact level match!");
            return $level;
        } else if ($value < $l_value) { // we went one level too high
            XMPP_ERROR_trace("Went too high!");

            // calculate the fraction of the gap to the next level for progress indicator
            $last_level_value = $levels[$current_level]['value'];
            XMPP_ERROR_trace("Last level value:", $last_level_value);

            $my_gap_to_next_level = $l_value - $value;
            XMPP_ERROR_trace("Missing points:", $my_gap_to_next_level);
            $gap_between_levels = $l_value - $last_level_value;
            XMPP_ERROR_trace("Gap between levels", $gap_between_levels);
            $gap_closed_by = $my_gap_to_next_level / $gap_between_levels;
            XMPP_ERROR_trace("percentage reach to current level", $gap_closed_by);
            $return_level = $current_level + $gap_closed_by;
            XMPP_ERROR_trace("Final level number", $return_level);
            return $return_level;
        }
        $current_level = $level;
        XMPP_ERROR_trace("looping, lest level was ", $current_level);
    }
    // return last level if nothing found
    return $level;
}



/**
 * display the achievement for one specific user
 * UUID is given by the event 'user_directory' or directly $parameters[0] = $uuid
 *
 * @param type $parameters
 */
function umc_achievements_display_web($parameters) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $uuid = $parameters['uuid'];

    $out = "<p><strong>Achievements:</strong><br>See the <a href=\"https://uncovery.me/about-this-server/achievements/\">Achievement page</a> for level information.";

    $sql = "SELECT achievement, level FROM minecraft_srvr.achievements WHERE uuid='$uuid' AND level>0 ORDER BY achievement;";
    $A = umc_mysql_fetch_all($sql);
    foreach ($A as $a) {
        $out .= umc_achievements_icon($a);
    }
    $out .= "</p>\n";
    $O['User'] = $out;
    return $O;
}

function umc_achievements_icon($a) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_ACHIEVEMENTS;
    $level = $a['level'];
    $level_number = floor($level);
    $level_fraction = ($level - $level_number) * 100;
    $level_fraction_display = round($level_fraction, 1);
    $achievement = $a['achievement'];
    $ach_data = $UMC_ACHIEVEMENTS[$achievement]['levels'][$level_number];
    $title = "<p class=\"ach_title\">&nbsp;</p>";
    if ($ach_data['title']){
        $title = "<p class=\"ach_title\">&quot;{$ach_data['title']}&quot;</p>";
    }

    $percentage = '';
    if (isset($UMC_ACHIEVEMENTS[$achievement]['levels'][$level_number + 1])) {
        $percentage = "<p class=\"ach_progress_wrap\"><p class=\"ach_progress\" style=\"width:{$level_fraction}%;\">{$level_fraction_display}%</p></p>";
    } else {
        $percentage = "<p class=\"ach_progress_wrap\"><p class=\"ach_progress_final\">Final level!!</p></p>";
    }

    // todo: don't show % if max level is reached.
    $achievement_text = ucwords($achievement);
    $out = "
        <div class=\"ach\">
            <div class=\"ach_badge\">
                <p class=\"ach_text\">Level</p>
                <p class=\"ach_number\">$level_number</p>
                $percentage
                <p class=\"ach_title\">$title</p>
            </div>
            <p class=\"ach_desc\">$achievement_text</p>
         </div>
         ";
    return $out;
}

/**
 * Lists all achievements
 *
 * @param type $mode
 */
function umc_achievements_list_all($mode = 'web') {
    global $UMC_ACHIEVEMENTS;

    $out = '';
    foreach ($UMC_ACHIEVEMENTS as $A => $a) {
        $out .= "
        <h2 id=\"$A\">" . ucwords($A) . ":</h2><div>{$a['description']}<br>
        <table>
            <tr>
                <th>Levels</th><th>Measure</th><th>Title</th><th>Reward</th>
            </tr>";
        foreach ($a['levels'] as $lvl => $l) {
            if ($lvl == 0) {
                continue;
            }
            $title = $l['title'];
            if (!$title) {
                $title = 'n/a';
            }
            $reward = $l['reward'];
            if (!$reward) {
                $reward = 'n/a';
            }
            $out .= "<tr><td>$lvl</td><td>{$l['value']} {$a['value_measure']}</td><td>$title</td><td>$reward</td></tr>";
        }
        $out .= "</table>\n</div>";
    }
    return $out;

}

// just a short function to record the level into the DB
function umc_achievements_level_record($ach_name, $uuid, $level) {
    $sql = "INSERT INTO minecraft_srvr.achievements (uuid,achievement,level)
        VALUES ('$uuid','$ach_name',$level)
        ON DUPLICATE KEY UPDATE level=$level;";
    umc_mysql_execute_query($sql);
}
