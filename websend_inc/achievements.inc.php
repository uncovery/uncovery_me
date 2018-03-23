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
        'check' => "SELECT balance as value FROM `minecraft_iconomy`.`mineconomy_accounts` WHERE uuid='%s';",
        'check_type' => 'sql',
    ),
    'voting' => array(
        'description' => 'Vote for the server many times',
        'levels' => array(
            0 => array('value' => 0, 'title' => false, 'reward' => false),
            1 => array('value' => 10, 'title' => false, 'reward' => false),
            2 => array('value' => 100, 'title' => false, 'reward' => false),
            3 => array('value' => 200, 'title' => false, 'reward' => false),
            4 => array('value' => 500, 'title' => false, 'reward' => false),
            5 => array('value' => 1000, 'title' => false, 'reward' => false),
            6 => array('value' => 2000, 'title' => false, 'reward' => false),
            7 => array('value' => 5000, 'title' => false, 'reward' => false),
            8 => array('value' => 10000, 'title' => false, 'reward' => false),
        ),
        'value_measure' => 'Lifetime Votes',
        'check' => "SELECT count(vote_id) as value FROM minecraft_log.votes_log WHERE username='%s'",
        'check_type' => 'sql',
    ),
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
function umc_achievements_update($user = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_ACHIEVEMENTS;
    
    if (!$user) {
        $users = umc_get_active_members('uuid');
    } else {
        $users = array($user);
    }

    foreach ($users as $uuid) {
        foreach ($UMC_ACHIEVEMENTS as $A => $a) {
            $check = $a['check'];
            $check_type = $a['check_type'];
            switch ($check_type) {
                case 'sql':
                    $check_sql = sprintf($check, $uuid);
                    $D = umc_mysql_fetch_all($check_sql);
                    $value = $D[0]['value'];
                    break;
                case 'function':
                    $value = $check($uuid);
                    break;
            }
            
            $level = umc_achievements_levelcheck($value, $A);
            if ($level > 0) {
                $sql = "INSERT INTO minecraft_srvr.achievements (uuid,achievement,level)
                    VALUES ('$uuid','$A',$level)
                    ON DUPLICATE KEY UPDATE level=$level;";
                umc_mysql_execute_query($sql);
            }
        }
    }
}

/**
 * display the achievement for one specific user
 * UUID is given by the event 'user_directory' or directly $parameters[0] = $uuid
 * 
 * @param type $parameters
 */
function umc_achievements_display_web($parameters) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $uuid = $parameters[0];
    
    $out = "<p><strong>Achievements:</strong><br>";
    
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
    $achievement_text = ucwords($achievement);
    $out = "
        <div class=\"ach\">
            <p class=\"ach_text\">Level</p>
            <p class=\"ach_number\">$level_number</p>
            <p class=\"ach_desc\">$achievement_text</p>
            <p class=\"ach_progress_wrap\"><p class=\"ach_progress\" style=\"width:{$level_fraction}%;\">{$level_fraction_display}%</p></p>
            $title
         </div>";
    return $out;
}


/**
 * Iterate an achievement to find out what the current userlevel is
 * 
 * @global array $UMC_ACHIEVEMENTS
 * @param type $value
 * @param type $achievement
 * @return int
 */
function umc_achievements_levelcheck($value, $achievement) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_ACHIEVEMENTS;
    $levels = $UMC_ACHIEVEMENTS[$achievement]['levels'];
    
    $current_level = 0;
    foreach ($levels as $level => $l_data) {
        $l_value = $l_data['value'];
        if ($value < $l_value) {
            // calculate the fraction of the gap to the next level for progress indicator
            $last_level_value = $levels[$current_level]['value'];
            $this_level_value = $l_value;
            $level_gap = $this_level_value - $last_level_value;
            $gap_closed_by = $value / $level_gap;
            $return_level = $current_level + $gap_closed_by;
            return $return_level;
        } else {
            $current_level = $level;
        }
    }
    
    // return last level if nothing found
    return $level;
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
        <h2>" . ucwords($A) . ":</h2><div>{$a['description']}<br>
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