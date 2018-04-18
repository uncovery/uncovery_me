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
 * This manages adventure-like stories where users can place signs in-game
 * that display longer info to players and perform certain other actions.
 * This includes a web interface to manage the stories.
 */
global $UMC_SETTING, $WS_INIT;

$WS_INIT['story'] = array(
    'disabled' => false,
    'events' => false,
    'default' => array(
        'help' => array(
            'title' => 'Adventure Stories',
            'short' => 'Displays a story and does adventure functions.',
            'long' => 'Stories can be created by all users on the website. If you find a sign with /story <code> you can type that command and see that story.;',
            ),
    ),
    'show' => array(
        'help' => array(
            'short' => 'Displays a story and does adventure functions.',
            'args' => '<story code>',
            'long' => 'Stories can be created by all users on the website. If you find a sign with /story <code> you can type that command and see that story.;',
            ),
        'function' => 'umc_story_show',
    ),
);

global $game_modes;
$game_modes = array(
    'unchanged' => 'Unchanged',
    'creative' => 'Creative',
    'survival' => 'Survival',
    'adventure' => 'Adventure',
);

function umc_story_admin() {
    global $UMC_USER, $game_modes;
    if (!$UMC_USER) {
        die('You need to be online to use this!');
    }
    if (!isset($UMC_USER['uuid'])) {
        XMPP_ERROR_trigger("UUID not set");
    }
    $uuid = $UMC_USER['uuid'];

    $story = 'Please enter text here';
    $pass = umc_get_code();
    $action_text = "Add a new entry";
    $action_code = "add";
    $title = "Insert a title";
    $survival = 0;
    $items = '';
    $warp = '';
    $clear_inv = 0;
    $game_mode = 'unchanged';


    if (isset($_POST['delete'])) {
        $code = umc_mysql_real_escape_string(strip_tags($_POST['storycode']));
        $sql = "DELETE FROM minecraft_iconomy.story WHERE uuid='$uuid' AND code=$code;";
        umc_mysql_query($sql);
    } else if (isset($_POST['add'])) {
        $code = umc_mysql_real_escape_string(strip_tags($_POST['storycode']));
        $warp = umc_mysql_real_escape_string(strip_tags($_POST['warp']));
        $save_story = umc_mysql_real_escape_string(strip_tags($_POST['story']));
        $save_title = umc_mysql_real_escape_string(strip_tags($_POST['storyline']));
        $save_items =  umc_mysql_real_escape_string(strip_tags($_POST['items']));
        $save_survival = 0;
        if (isset($_POST['game_mode']) && isset($game_modes[$_POST['game_mode']])) {
            $game_mode = umc_mysql_real_escape_string($_POST['game_mode']);
        } else {
            $game_mode = umc_mysql_real_escape_string('unchanged');
        }
        $save_clear_inv = 0;
        if (isset($_POST['clear_inv'])) {
            $save_clear_inv = -1;
        }
        if ($save_story != 'Please enter text here') {
            $sql2_raw = "INSERT INTO minecraft_iconomy.story (`uuid`, `story`, `code`, `storyline`, `game_mode`, `items`, `clear_inv`, `warp`)
                VALUES ('$uuid', $save_story, $code, $save_title, $game_mode, $save_items, '$save_clear_inv', $warp)";
            $sql2 = str_replace('&','_', $sql2_raw); // this removes strings that can be abused by the minimap
            umc_mysql_query($sql2);
        }
    } else if (isset($_POST['edit'])) {
        $code = umc_mysql_real_escape_string(strip_tags($_POST['storycode']));
        $sql = "SELECT * FROM minecraft_iconomy.story WHERE uuid='$uuid' AND code=$code;";
        $D = umc_mysql_fetch_all($sql);
        if (count($D) > 0) {
            $row = $D[0];
            $story = stripslashes(strip_tags($row['story']));
            $pass = $row['code'];
            $warp = stripslashes($row['warp']);
            $action_text = "Edit story and save";
            $action_code = "update";
            $title = $row['storyline'];
            $survival = $row['forcesurvival'];
            $game_mode = $row['game_mode'];
            $items = $row['items'];
            $clear_inv = $row['clear_inv'];
        }
    } else if (isset($_POST['update'])) {
        $code = umc_mysql_real_escape_string(strip_tags($_POST['storycode']));

        $save_story = umc_mysql_real_escape_string(strip_tags($_POST['story']));
        $save_title = umc_mysql_real_escape_string(strip_tags($_POST['storyline']));
        $save_items =  umc_mysql_real_escape_string(strip_tags($_POST['items']));
        if (isset($_POST['game_mode']) && isset($game_modes[$_POST['game_mode']])) {
            $game_mode = umc_mysql_real_escape_string($_POST['game_mode']);
        } else {
            $game_mode = umc_mysql_real_escape_string('unchanged');
        }
        $warp = '';
        if (isset($_POST['warp'])) {
            $warp = umc_mysql_real_escape_string(strip_tags($_POST['warp']));
        }
        $save_clear_inv = 0;
        if (isset($_POST['clear_inv'])) {
            $save_clear_inv = -1;
        }
        $sql = "UPDATE minecraft_iconomy.story
	    SET story= $save_story,
		storyline=$save_title,
		warp=$warp,
		game_mode=$game_mode,
            	`items`=$save_items,
		`clear_inv`='$save_clear_inv'
            WHERE uuid='$uuid' and code=$code;";
        $sql_update = str_replace('&','_', $sql); // this removes strings that can be abused by the minimap
        umc_mysql_query($sql_update, true);
    }

    $sql = "SELECT * FROM minecraft_iconomy.story WHERE uuid='$uuid' ORDER BY storyline, id;";
    $D = umc_mysql_fetch_all($sql);
    if (count($D) > 0) {
        echo "<table><tr><td style=\"padding:3px;\"><strong>Storyline</strong></td><td style=\"padding:3px;\"><strong>Game mode<br>Clear Inv?</strong></td><td style=\"padding:3px;\"><strong>Code</strong></td>"
            . "<td style=\"padding:3px;\"><strong>Hits</strong></td>"
            . "<td style=\"padding:3px;\"><strong>Story & items</strong></td><td style=\"padding:3px;\"><strong>Actions</strong></td></tr>\n";
        foreach ($D as $row) {
            $count_sql = "SELECT count(uuid) as counter FROM minecraft_iconomy.story_users WHERE story_id='{$row['id']} GROUP BY story_id';";
            $D = umc_mysql_fetch_all($count_sql);
            $count_row = $D[0];
            $hitcount = $count_row['counter'];
            $story_short = substr($row['story'], 0 , 50) . '...';
            $game_mode_text = $game_modes[$row['game_mode']];
            $txt_clear_inv = 'No';
            if ($row['clear_inv'] == -1) {
                $txt_clear_inv = 'Yes';
            }

            if (strlen($row['items']) > 0) {
                $story_short = $story_short . "<br><strong>Items:</strong>" . $row['items'];
            }
            $buttons = "<form action=\"#result\" method=\"post\"><input type=\"submit\" name=\"edit\" class=\"button-primary\"value=\"Edit\"/> "
                    . "<input type=\"submit\" name=\"delete\" class=\"button-primary\"value=\"Delete\"/><input type=\"hidden\" name=\"storycode\" value=\"{$row['code']}\"></form>";
            echo "<tr><td>{$row['storyline']}</td><td>$game_mode_text / $txt_clear_inv</td><td>{$row['code']}</td><td>$hitcount</td><td>$story_short</td><td>$buttons</td></tr>\n";
        }
        echo "</table>";
    }

    $inv_checked = '';
    if ($clear_inv == -1) {
        $inv_checked = ' checked="checked"';
    }


    $mode_drop = umc_web_dropdown($game_modes, 'game_mode', $game_mode, false);

    $out = "<hr><a name=\"result\">$action_text:</a><form action=\"#result\" method=\"post\">\n
        <strong>Your story code: $pass</strong><br>
        Title: <input type=\"text\" name=\"storyline\" value=\"$title\"> <br>
        Game mode: $mode_drop (creative works in city & flatlands only)<br>
        Clear inventory? <input type=\"checkbox\" name=\"clear_inv\" value=\"clear_inv\"$inv_checked/> (city & flatlands only)<br>
        Give items: <input type=\"text\" name=\"items\" value=\"$items\"> (Format: item_id:damage:amount;... city & flatlands only)<br>
        Warp to point: <input type=\"text\" name=\"warp\" value=\"$warp\"> (Format: 'story_yourwarp'; Ask Uncovery to create a warp point for you, only works in city. Do not include the story_ here)<br>
        <textarea rows=\"10\" name=\"story\">$story</textarea>
        <input type=\"hidden\" name=\"storycode\" value=\"$pass\">
        <input type=\"submit\" name=\"$action_code\" id=\"wp-submit\" class=\"button-primary\"
        value=\"Save\" tabindex=\"100\" /></form>\n\n";
    echo $out;
}

function umc_story_show() {
    global $UMC_USER, $UMC_COLORS, $game_modes;
    $username = $UMC_USER['username'];
    $uuid = $UMC_USER['uuid'];
    $args = $UMC_USER['args'];
    $mode = $UMC_USER['mode'];
    $world = $UMC_USER['world'];

    if (!isset($args[2])) {
        umc_error("You have to enter a story code!");
    } else {
        $code = $args[2];
    }

    if (strpos($code, '?')) {
        umc_error('Your code is incomplete! Please replace the ? with the code that you received at the last station!');
    }
    if (strlen($code) != 5) {
        umc_error('Your code needs to have 5 letters!');
    }

    $sql_code = umc_mysql_real_escape_string($code);
    $sql = "SELECT * FROM minecraft_iconomy.story WHERE code=$sql_code;";
    $D = umc_mysql_fetch_all($sql);
    $disallowed_items = array(0,8,9,10,11,34,36,43,51,52,55,26,59,60,63,64,68,71,75,78,83,90,92,93,94,95,97,99,100,104,105,115,117,118,119,120,122);
    $out = '';
    if (count($D) > 0) {
        $row = $D[0];
        $story = stripslashes($row['story']);
        $title = stripslashes($row['storyline']);
        $warp = stripslashes($row['warp']);
        if (($row['game_mode'] != 'unchanged') && (($world == 'city') || ($world == 'flatlands'))) {
            umc_ws_cmd("gamemode {$row['game_mode']} $username;", 'asConsole');
            $mode_text = $game_modes[$row['game_mode']];
            umc_echo("Your game mode has been set to $mode_text");
        }
        if (($row['clear_inv'] == -1) && (($world == 'city') || ($world == 'flatlands'))) {
            umc_ws_cmd("ci $username;", 'asConsole');
        }
        $items = stripslashes($row['items']);
        if ((strlen($items) > 0) && (($world  =='city') || ($world == 'flatlands'))) {
            $items = explode(";", $items);
            if (count($items) > 0) {
                foreach ($items as $item) {
                    $data = explode(':', $item);
                    if ((count($data) == 3) && (!in_array($data[0], $disallowed_items))) {
                        if (is_numeric($data[0]) && is_numeric($data[1]) && is_numeric($data[2])) {
                            umc_echo("Sorry, this story is broken, but we will fix this!");
                            XMPP_ERROR_trigger("Story $code is broken!");
                        } else if (!is_numeric($data[0]) && is_numeric($data[1]) && is_numeric($data[2])){
                            umc_ws_give($username, $data[0], $data[2], $data[1]);
                        }
                    }
                }
            }
        }
        if ((strlen($warp) > 0) && ($world  =='city')) {
            $warp = "story_" . $warp;
            umc_ws_cmd("warp $warp $username;", 'asConsole');
        }

        $uuid = $row['uuid'];
        $creator_name = umc_user2uuid($uuid);

        // check for duplicate entries
        $sql = "SELECT * FROM minecraft_iconomy.story_users WHERE `uuid`='$uuid' and `story_id`='{$row['id']}';";
        $D3 = umc_mysql_fetch_all($sql);
        $count = count($D3);
        if ($count == 0) {
            $sql = "INSERT INTO minecraft_iconomy.story_users (`uuid`, `story_id`) VALUES ('$uuid', '{$row['id']}');";
            umc_mysql_query($sql, true);
        }

        $pages = explode("[BR]", $story);
        $pagecount = count($pages);
        if (isset($args[2]) && is_numeric($args[2]) && isset($pages[($args[2] - 1)])) {
            $page = $args[2];
        } else {
            $page = 1;
        }
        $arr_page = $page - 1;
        $this_page_raw = $pages[$arr_page];

        $search = array('[player]', "\n");
        $replace = array($username, ';');

        // todo this needs to be cleaned up
        foreach ($UMC_COLORS as $colorcode => $data) {
            foreach ($data['names'] as $color_name) {
                $search[] = "[". $color_name . "]";
                $replace[] = "{" . $color_name . "}";
            }
        }

        $this_page = str_replace($search, $replace, $this_page_raw);
        $out = "{white}----------------- {green}Story Page ($page/$pagecount): {white}-----------------;"
            . "{yellow}$title {white}by $creator_name;"
            . $this_page;
        if (count($pages) > $page) {
            $nextpage = $page + 1;
            $out .= "{white}------- {green}Please type /story $code $nextpage to read on! {white}--------;";
        } else {
            $out .= ";-----------------------------------------------------;";
        }
    } else {
        $out .="The story code could not be found!";
    }
    $lines = explode(";", $out);
    foreach ($lines as $line) {
        umc_echo(trim($line), true);
    }
}

function umc_get_code() {
    $pass = umc_random_code_gen();

    $sql = "SELECT * FROM minecraft_iconomy.story WHERE code='$pass';";
    $D3 = umc_mysql_fetch_all($sql);
    $count = count($D3);
    if ($count > 0) {
        umc_get_code();
    }
    return $pass;
}
