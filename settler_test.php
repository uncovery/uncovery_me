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
 * This is the settler test. It guides a user through the process of getting their
 * first lot.
 */

/**
 * Settler  test
 *
 * @global type $UMC_USER
 * @global type $UMC_SETTING
 * @global type $UMC_DOMAIN
 * @return string
 */
function umc_settler_new() {
    global $UMC_USER, $UMC_SETTING, $UMC_DOMAIN;

    $out = '';

    if (!$UMC_USER) {
        return "You have to be <a href=\"$UMC_DOMAIN/wp-login.php\">logged in</a> to use this!";
    }

    $steps = array(
        1 => array(
            'begging' => true,
            'griefing'=> true,
            'pixel_art' => true,
            'minimaps' => false,
            'xray_and_cheats' => true,
            'not_reading_the_website' => true,
            'excessive_swearing' => true,
            'walls_around_your_lot' => true,
            'shaders' => false,
        ),
     );

    $player = strtolower($UMC_USER['username']);
    $uuid = $UMC_USER['uuid'];
    $userlevel = $UMC_USER['userlevel'];
    $email = $UMC_USER['email'];

    if ($userlevel != 'Guest') {
        $out .= "You are not a Guest and can use the <a href=\"https://uncovery.me/server-access/lot-manager/\">Lot manager</a> to get a lot!";
    }

    if (umc_user_is_banned($uuid)) {
        return "Sorry, you are banned from the server!";
    }

    $icon_url = umc_user_get_icon_url($player);
    $user_icon = "<img src=\"$icon_url\">";
    // get user location
    $s_post  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
    $step = 0;
    if (isset($s_post['step'])) {
        $step = $s_post['step'];
    }
    $loc = umc_read_markers_file('array');
    $lot = false;
    if (isset($s_post['lot'])) {
        $lot = $s_post['lot'];
    }
    $world = false;
    if (isset($s_post['world'])) {
        $world = $s_post['world'];
    }

    if (isset($loc[$player])) {
        $player_world = $loc[$player]['world'];
        $player_lot = umc_lot_get_from_coords($loc[$player]['x'], $loc[$player]['z'], $loc[$player]['world']);
        $x = $loc[$player]['x'];
        $z = $loc[$player]['z'];
    } else {
        $player_lot = false;
        $player_world = false;
        $x = false;
        $z = false;
    }

    $error = '';
    // answer check
    switch ($step) {
        case 2:
            if (!isset($s_post['stepone'])) {
                $step = 1;
                umc_log('settler_test', 'step_1_fail', "$player did not answer any questions!");
                $error = "You need to answer the questions on the previous page!";
            } else {
                $problem = false;
                $answers = array();
                foreach ($steps[1] as $choice => $value) {
                    if (($value == true) && (!in_array($choice, $s_post['stepone']))) { // Bad stuff
                        $problem = true;
                        $answers[] = $choice;
                    } else if (($value == false) && (in_array($choice, $s_post['stepone']))) { // but is
                        $problem = true;
                        $answers[] = $choice;
                    }
                }
                if ($problem) {
                    umc_log('settler_test', 'step_1_fail', "$player failed step 1 with wrong answers " . implode("|", $answers));
                    $error = "<h1>Error</h1>One or more answers in the previous page were wrong.<br>Please go back and check before proceeding.<br>"
                        . "If you need help, please read the <a href=\"$UMC_DOMAIN/about-this-server/rules/\">rest of the rules</a>";
                    $step = 1;
                }
            }
            break;
        case 3:
            if (!isset($s_post['world'])) {
                $step = 2;
                umc_log('settler_test', 'step_2_fail', "$player failed step 2");
                $error = 'You need to choose a playing mode to continue!';
            }
            break;
        case 4:
            if (!isset($s_post['lot'])) {
                $step = 3;
                umc_log('settler_test', 'step_3_fail', "$player failed step 3, no lot chosen");
                $error = 'You need to choose lot to continue!';
            }
            break;
        case 5:
            if (!$player_world) {
                $step = 4;
                umc_log('settler_test', 'step_4_fail', "$player failed step 4 (not in game)");
                $error = 'You need to be in the game to continue!';
                // var_dump($loc);
            } else if ($player_world != 'city' || $x > 953 || $x < 938 || $z < -814 || $z > -793) {
                $step = 4;
                umc_log('settler_test', 'step_4_fail', "$player failed step 4 (not in spawn house)");
                $error = 'You need to type /warp spawn and then continue!';
            }
            break;
        case 6:
            if ($player_world != $s_post['world']) {
                $step = 5;
                umc_log('settler_test', 'step_5_fail', "$player failed step 5");
                $error = "You need to go through the {$s_post['world']} portal! Type <strong>/warp spawn</strong> and try again!";
            }
            break;
        case 8:
            if (strtolower($player_lot) != strtolower($s_post['check_lot'])) {
                $step = 7;
                umc_log('settler_test', 'step_7_fail', "$player failed step 7 by entering " . $s_post['check_lot'] . "instead of $player_lot");
                $error = 'You need to enter the lot you see on-screen into the form to continue!';
            }
            break;
        case 9;
            if (!$player_lot) {
                $step = 8;
                umc_log('settler_test', 'step_8_fail', "$player failed step 8");
                $error = "We could not find you on the map. Please make sure you are on the server!";
            } else if ($player_lot != $s_post['lot']) {
                $step = 8;
                umc_log('settler_test', 'step_8_fail', "$player failed step 8");
                $error = "You need to get lot {$s_post['lot']} before you can continue. Please either walk there or use /jump {$s_post['lot']}. You are now in lot $player_lot!";
            }
            break;
    }

    // questions
    $out .= "<strong style=\"font-size: 24px; border: 1px solid red; margin: 5px; padding: 5px;\">$error</strong><br>";
    switch ($step) {
        case 0:
            umc_log('settler_test', 'start', "$player started the settler test");
            $out .= "
                <noscript>
                    <h1>YOU NEED TO ENABLE JAVASCRIPT TO USE THIS!</h1>
                </noscript>
                <form action=\"$UMC_DOMAIN/server-access/buildingrights/\" method=\"post\">
                <h1>Step 1: Welcome!</h1>
                <h2>Once you finish this test, you will be settler!</h2>
                <h2>Before Applying</h2>
                <ul>
                    <li>You must have some experience playing Minecraft.</li>
                    <li>Read the <a href=\"$UMC_DOMAIN/faq/\">FAQ</a></li>
                    <li>Read the <a href=\"$UMC_DOMAIN/rules/\">Rules</a></li>
                    <li>Read the <a href=\"$UMC_DOMAIN/user-levels/\">User Levels and Commands</a> page.</li>
                </ul>
                This process will guide you through the process of getting building rights on the server and at the same time give you a lot and make sure you get there.
                You will need to login to the server with your minecraft client during the process and keep this website open.<br>
                <input type=\"hidden\" name=\"step\" value=\"1\">
                <input type=\"submit\" name=\"Next\" value=\"Next\">\n";
            break;
        case 1:
            $warning = '';
            $failcount = umc_settler_get_fails($player);
            if ($failcount > 10) {
                $warning = "<div style=\"color:red;font-weight:bold;font-size:120%;\">You have failed the test more than 10 times. If you fail too often, you will be banned from the server!</div>";
            } else if ($failcount > 20) {
                umc_user_ban($player, "Settler test failed");
                return $warning = "<div style=\"color:red;font-weight:bold;font-size:120%;\">You have failed the test too often. You are now banned from the server!</div>";
            }
            umc_log('settler_test', 'step_1', "$player is at step 1");
            $out .= "<form action=\"$UMC_DOMAIN/server-access/buildingrights/\" method=\"post\">\n"
                . "<h1>Step 2: Rules & regulations</h1>\n"
                . $warning
                . "Here are some basic rules you should know:"
                . "<ul>\n<li><strong>Do not beg for anything</strong> - Including upgrades, handouts or help with this process.</li>\n"
                . "<li><strong>No pixel-art</strong>. This is not 'paint by numbers'. We build stuff.</li>\n"
                . "<li><strong>Be considerate of others</strong>. No griefing, no stealing, no killing. We log everything, we will find out.</li>\n"
                . "<li><strong>Don't cheat!</strong> No Xray, no mods, no bug abuse.</li>\n"
                . "<li><strong>Learn yourself!</strong> Look on the <a href=\"$UMC_DOMAIN/about-this-server/\">website</a> for answers first, then ask other users.</li>\n"
                . "<li><strong>We ban forever - no appeals!</strong> You better read the <a href=\"$UMC_DOMAIN/about-this-server/rules/\">rest of the rules</a>.</li>\n"
                . "</ul>\n"
                . "<strong>Pick the items that are not allowed (and will therefore get you banned) (Note: Asking others about the answers will get you banned, too!): </strong><br>\n"
                . "<input type=\"hidden\" name=\"step\" value=\"2\">\n";

            foreach ($steps[1] as $image => $check) {
                $image_text = umc_pretty_name($image);
                $out .= "<span style=\"float:left; text-align:center;\"><img height=\"180\" src=\"$UMC_DOMAIN/websend/$image.png\"><br>"
                    . "<input type=\"checkbox\" name=\"stepone[]\" value=\"$image\">$image_text</span>\n";
            }
            $out .= '<br style="clear:both;">'
                . "<input type=\"submit\" name=\"Next\" value=\"Next\">\n";

            break;
        case 2:
            umc_log('settler_test', 'step_2', "$player is at step 2");
            $out .=  "<form action=\"$UMC_DOMAIN/server-access/buildingrights/\" method=\"post\">\n"
                . '<h1>Step 3: Creative or Survival?</h1>'
                . 'You can either build in a flat, creative world or a wild, survival world!<br>Choose one:<br>'
                . "<span style=\"float:left; text-align:center;\"><img width=\"400\" src=\"$UMC_DOMAIN/websend/empire.png\"><br><input type=\"radio\" name=\"world\" value=\"empire\">Survival mode in the Empire world</span>"
                . "<span style=\"float:left; text-align:center;\"><img width=\"400\" src=\"$UMC_DOMAIN/websend/flatlands.png\"><br><input type=\"radio\" name=\"world\" value=\"flatlands\">Creative mode in the Flatlands world</span><br style=\"clear:both;\">"
                . "<input type=\"hidden\" name=\"step\" value=\"3\">\n"
                . "<input type=\"submit\" name=\"Next\" value=\"Next\">\n";
            break;
        case 3:
            umc_log('settler_test', 'step_3', "$player is at step 3");
            $spawn_lot = $UMC_SETTING['world_data'][$world]['spawn'];
            $tile = umc_user_get_lot_tile(strtolower($spawn_lot));
            $out .= "<form action=\"$UMC_DOMAIN/admin/index.php?function=create_map&world=$world&freeonly=true\" method=\"post\">\n"
                . "<h1>Step 4: Find a lot!</h1>\n"
                . "<img style=\"float:right;\" width=\"300\" src=\"$UMC_DOMAIN/websend/$world.png\">Great! You chose to build in the <strong>$world</strong> world!<br>"
                . "You are now ready to pick a lot!<br>"
                . "If you made a mistake and want to play a different mode, please go back now and chose another mode. There will be no going back later.<br><br>"
                . "<strong>Now you need to find a lot that you like.</strong><br>"
                . "When you click 'Next', a map of the $world world will open.<br>"
                . "Click on the \"Find Spawn\" button in the top-left corner. It looks like this:<br>"
                . "<img src=\"/websend/find_spawn.png\"><br>"
                . "You will now see a flashing lot, which is the entrance to the $world world.<br>"
                . "It's convenient to find a lot close to it. <br>"
                . "The spawn lot looks like this:<br>$tile"
                . "<input type=\"hidden\" name=\"settler_test\" value=\"settler_test\">\n"
                . "<br><br><input type=\"submit\" name=\"Next\" value=\"Next\">\n";
            break;
        case 4:
            umc_log('settler_test', 'step_4', "$player is at step 4");
            $tile = umc_user_get_lot_tile(strtolower($lot));
            $out .= "<form action=\"$UMC_DOMAIN/server-access/buildingrights/\" method=\"post\">\n"
                . '<h1>Step 5: You chose a lot!</h1>'
                . "<span style=\"float:left;\">$tile</span>The lot you have chosen is <strong>$lot in the $world</strong> world. You can see a satellite image of it on the left.<br>You should now go there, to make sure it's what you want!<br>";

            if ($player_world) {
                $out .= "<br>To go there, you need to go to the portal house. From there you can get into the $world world.<br>"
                    . "Please type <strong>/warp spawn</strong> in game to get there, it should look like this inside:<br><img src=\"/websend/portals.png\">"
                    . "Once you see this, please come back here and press "
                    . "<input type=\"submit\" name=\"Next\" value=\"Next\">\n"
                    . "<input type=\"hidden\" name=\"lot\" value=\"$lot\">\n"
                    . "<input type=\"hidden\" name=\"world\" value=\"$world\">\n"
                    . "<input type=\"hidden\" name=\"step\" value=\"5\">\n";
            } else {
                $out .= "Please <strong>login to the server</strong> now with your minecraft client at <strong>uncovery.me</strong> and then press "
                    . "<input type=\"hidden\" name=\"world\" value=\"$world\">\n"
                    . "<input type=\"submit\" name=\"action\" value=\"Continue\">\n"
                    . "<input type=\"hidden\" name=\"lot\" value=\"$lot\">\n"
                    . "<input type=\"hidden\" name=\"step\" value=\"5\">\n";
            }
            break;
        case 5:
            umc_log('settler_test', 'step_5', "$player is at step 5");
            $out .= "<form action=\"$UMC_DOMAIN/server-access/buildingrights/\" method=\"post\">\n"
                . "<h1>Step 6: Get to the $world world</h1>";
            // check if the player is actually in the portal house

            // player is not in portal house
            if ($player_world != 'city' || $x > 953 || $x < 938 || $z < -814 || $z > -793) {
                $out .= "You need to be in the portal house to continue. Please type <strong>/warp spawn</strong> again to get there. "
                    . "It should look like this inside:<br><img src=\"/websend/portals.png\"> Once you see this, press\n"
                    . "<input type=\"submit\" name=\"Next\" value=\"Next\">\n"
                    . "<input type=\"hidden\" name=\"lot\" value=\"$lot\">\n"
                    . "<input type=\"hidden\" name=\"world\" value=\"$world\">\n"
                    . "<input type=\"hidden\" name=\"step\" value=\"5\">\n";
            } else {
                $out .= "Since you chose a lot in the $world world, you need to go through the $world portal. "
                    . "It looks like this: Notice the name of the world is written on the sign."
                    . "<img src=\"/websend/{$world}_portal.png\">"
                    . "Please step through and press "
                    . "<input type=\"submit\" name=\"Next\" value=\"Next\">\n"
                    . "<input type=\"hidden\" name=\"lot\" value=\"$lot\">\n"
                    . "<input type=\"hidden\" name=\"world\" value=\"$world\">\n"
                    . "<input type=\"hidden\" name=\"step\" value=\"6\">\n";
            }
            break;
        case 6:
            umc_log('settler_test', 'step_6', "$player is at step 6");
            $spawn_lot = strtoupper($UMC_SETTING['world_data'][$world]['spawn']);
            $lower_lot = strtolower($lot);
            $lot_sql = "SELECT region_cuboid.region_id AS lot, sqrt(pow(max_x,2)+pow(max_z,2)) AS distance, max_x, max_z
                FROM minecraft_worldguard.world
                LEFT JOIN minecraft_worldguard.region_cuboid ON world.id=region_cuboid.world_id
                WHERE region_cuboid.region_id='$lower_lot';";
            $D = umc_mysql_fetch_all($lot_sql);
            $lot_data = $D[0];

            // north/south difference
            if ($lot_data['max_x'] < 0) {
                $direction1 = "north";
            } else if ($lot_data['max_x']  >= 0) {
                $direction1 = "south";
            }
            // north/south difference
            if ($lot_data['max_z'] < 0) {
                $direction2 = "west";
            } else if ($lot_data['max_z']  >= 0) {
                $direction2 = "east";
            }
            $out .= "<form action=\"$UMC_DOMAIN/admin/index.php?function=create_map&world=$world&freeonly=true\" method=\"post\">\n"
                . "<h1>Step 7: Getting to lot $lot in the $world world</h1>"
                . "Getting to your world is easy! You are now at the center of the $world world."
                . "Your lot is <strong>$direction1/$direction2</strong> from spawn! <br>"
                . "You can find out which direction you are looking with the <strong>/compass</strong> command.<br>"
                . "As a Guest level player, you cannot be killed by mobs until you finished this here.<br>"
                . "So you have to leave the spawn lot either through the $direction1 or the $direction2 exit.<br>"
                . "To know where you are, you can follow your icon $user_icon on the map while you get around.<br>"
                . "Please click NEXT to open the map, there you find your icon click the button next to it!<br><br>"
                . "<input type=\"submit\" name=\"next\" value=\"Next\">\n"
                . "<input type=\"hidden\" name=\"track_player\" value=\"$player\">\n"
                . "<input type=\"hidden\" name=\"world\" value=\"$world\">\n"
                . "<input type=\"hidden\" name=\"lot\" value=\"$lot\">\n";
            $x = $loc[$player]['x'];
            $z = $loc[$player]['z'];
            break;
        case 7:
            umc_log('settler_test', 'step_7', "$player is at step 7");
            // whereami
            $out .= "<h1>Step 8: Find out where you are in-game</h1>"
                . "<form action=\"$UMC_DOMAIN/server-access/buildingrights/\" method=\"post\">\n"
                . "Now that you know how to find yourself on the map, you need to find out where you are when in-game.<br>"
                . "The command to find your location in-game is called <strong>/whereami</strong>.<br>"
                . "Please go into the game and type <strong>/whereami</strong><br>"
                . "You will see something like this:<br>"
                . "<img src=\"/websend/whereami.png\"><br>"
                . "In this example, you can see the Lot (in the first line) is <img src=\"/websend/whereami_detail.png\"> So you would enter 'emp_z7'.<br>"
                . "Please go now into the game, type <strong>/whereami</strong>, and enter the information here:<br>"
                . "I am now in lot <input type=\"text\" name=\"check_lot\" value=\"\" size=\"7\"> and then press "
                . "<input type=\"submit\" name=\"next\" value=\"Next\">\n"
                . "<input type=\"hidden\" name=\"step\" value=\"8\">\n"
                . "<input type=\"hidden\" name=\"world\" value=\"$world\">\n"
                . "<input type=\"hidden\" name=\"lot\" value=\"$lot\">\n";
            // enter which lot you are in right now
            break;
        case 8:
            // walk to your lot
            umc_log('settler_test', 'step_8', "$player is at step 8");
            $lower_lot = strtolower($lot);
            $out .=  "<form action=\"$UMC_DOMAIN/admin/index.php\" method=\"post\">\n"
                . "<h1>Step 9: Walk to your lot $lot!</h1>"
                . "Now you have everything you need to get to your lot!<br>You should follow your steps on the 2D map.<br>"
                . "You can either walk there, or use the command <pre>/lot warp $lot</pre> to get there. Please note that this command is only available while you are Guest.<br>"
                . "Press 'Next' to open the 2D map and follow your icon to lot $lot!<br>"
                . "<input type=\"submit\" name=\"next\" value=\"Next\">\n"
                . "<input type=\"hidden\" name=\"guide_lot\" value=\"$player\">\n"
                . "<input type=\"hidden\" name=\"world\" value=\"$world\">\n"
                . "<input type=\"hidden\" name=\"freeonly\" value=\"true\">\n"
                . "<input type=\"hidden\" name=\"function\" value=\"create_map\">\n"
                . "<input type=\"hidden\" name=\"step\" value=\"9\">\n"
                . "<input type=\"hidden\" name=\"world\" value=\"$world\">\n"
                . "<input type=\"hidden\" name=\"lot\" value=\"$lower_lot\">\n";
           break;
        case 9:
            umc_log('settler_test', 'step_9', "$player is at step 9");
            // do you like it? claim it
            $out .= "<h1>Step 10: Do you like the lot {$s_post['lot']}?</h1>"
                . "<form action=\"$UMC_DOMAIN/server-access/buildingrights/\" method=\"post\">\n"
                . '<input type="radio" name="step" value="10" checked>Yes! I take it! I will type <strong>/homes buy ' . $world . '</strong> now so I can warp back here!<br>'
                . '<input type="radio" name="step" value="1">No,I would like to start over!<br>'
                . "<input type=\"hidden\" name=\"lot\" value=\"$lot\">\n"
                . "<input type=\"hidden\" name=\"world\" value=\"$world\">\n"
                . "<input type=\"submit\" name=\"next\" value=\"Finish!\">\n";
            break;
        case 10:
            umc_log('settler_test', 'step_10', "$player is at step 10");
            // final confirmation
            $out .="<h1>Step 11: Congratulations!</h1>"
               . "You have been promoted to Settler!<br>";
            if ($userlevel == 'Guest') {
                $cmd = "pex promote {$UMC_USER['uuid']}";
                umc_exec_command($cmd);
                // update UUID database
                $sql = "UPDATE minecraft_srvr.UUID SET userlevel='Settler' WHERE UUID='{$UMC_USER['uuid']}';";
                umc_mysql_query($sql);
                umc_exec_command('pex reload');
                umc_mod_broadcast("Congrats $player for becoming Settler!");
                XMPP_ERROR_send_msg("$userlevel $player got promoted with command " . $cmd);
                umc_log('settler_test', 'promotion', "$player ({$UMC_USER['uuid']})was promoted to settler (new test)");
                $headers = "From: minecraft@uncovery.me\r\n" .
                    "Reply-To: minecraft@uncovery.me\r\n" .
                    'X-Mailer: PHP/' . phpversion();
                $subject = "[Uncovery Minecraft] Settler applicaton";
                $mailtext = "The user: $player (email: $email) was promoted to Settler and got lot $lot.\n\n";
                $check = mail('minecraft@uncovery.me', $subject, $mailtext, $headers, "-fminecraft@uncovery.me");
                if (!$check) {
                    XMPP_ERROR_trigger("The settler promotion email could not be sent!");
                }
                // check userlevel to make sure
                $new_level = umc_userlevel_get($uuid, true);
                if ($new_level != 'Settler') {
                    XMPP_ERROR_trigger("$userlevel $player did NOT got promoted with command " . $cmd . " he's still $new_level");
                }
            } else {
                $out .= "Thanks for taking this test! Since you are $userlevel already, we will not promote you to Settler.<br>";
            }
            // try to assign the lot
            $check = umc_lot_manager_check_before_assign($uuid, $lot);
            $out .= "Trying to assign this lot to you: <strong>{$check['text']}</strong><br>";
            if ($check['result'] == false) {
                XMPP_ERROR_send_msg("Settler Test lot assignment failed!");
                $out .= "There was an error giving the lot you reserved to you. You can get any other through your <a hreaf=\"$UMC_DOMAIN/server-access/lot-manager/\">lot manager</a>!<br>";
            } else {
                umc_lot_add_player($uuid, $lot, 1, $check['cost']);
                $out .= $check['text'];
            }
            break;
        default:
            $out .= "This option was not recognized, please reload the page!";
    }

    $out .= "</form>\n";
    return $out;
}

/**
 * Count the amount of time a user has failed the settler test
 *
 * @param string $username
 * @return int amount of fails
 */
function umc_settler_get_fails($username) {
    $sql = "SELECT count(log_id) as counter FROM minecraft_log.`universal_log` WHERE action='step_1_fail' AND username='$username';";
    $data = umc_mysql_fetch_all($sql);
    if (count($data) > 0) {
        $count = $data[0]['counter'];
        return $count;
    } else {
        return 0;
    }
}
