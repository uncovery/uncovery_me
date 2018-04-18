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
 * This plugin manages the Hunger games. It's one of the most complex systems on the
 * server. Please work with caution.
 */
global $WS_INIT, $HUNGER;

$HUNGER['channel'] = "Trading";
$HUNGER['trophy_cost'] = 1000;
$HUNGER['announce'] = true;

$WS_INIT['hunger'] = array(
    'disabled' => false,
    'events' => array(
        'PlayerJoinEvent' => 'umc_hunger_updatestatus',
        'PlayerQuitEvent' => 'umc_hunger_updatestatus',
        'PlayerChangedWorldEvent' => 'umc_hunger_updatestatus',
        'PlayerGameModeChangeEvent' => 'umc_hunger_updatestatus',
        'PlayerPortalEvent' => 'umc_hunger_updatestatus',
        'PlayerRespawnEvent' => 'umc_hunger_updatestatus',
        'PlayerDeathEvent' => 'umc_hunger_updatestatus',
    ),
    'default' => array(
        'help' => array(
            'title' => 'Hunger games',
            'short' => 'Manages Hunger games',
            'long' => 'In these games you are warped to a mint world, reduced in size without any tools. Mine, craft, build & survive!',
            ),
    ),
    'announce' => array(
        'help' => array(
            'args' => '',
            'short' => 'Announce a new game',
            'long' => 'This allows other players to join the game. and prepare for it.',
            ),
        'function' => 'umc_hunger_announce',
        'security' => Array(
            'worlds' => Array( 'empire', 'kingdom', 'draftlands', 'skylands', 'aether', 'flatlands', 'city'),
            'level' => 'Settler',
         ),
    ),
    'join' => array(
        'help' => array(
            'args' => '',
            'short' => 'Join the currently announced game',
            'long' => 'This will allow you to warp to the hunger world.',
            ),
        'function' => 'umc_hunger_addplayer',
        'security' => Array(
            'worlds' => Array( 'empire', 'kingdom', 'draftlands', 'skylands', 'aether', 'flatlands', 'city'),
            'level' => 'Settler',
         ),
    ),
    'start' => array(
        'help' => array(
            'args' => '',
            'short' => 'Starts the current game',
            'long' => 'Only the person who started it can start it.',
            ),
        'function' => 'umc_hunger_start',
    ),
    'stop' => array(
        'help' => array(
            'args' => '',
            'short' => 'Stops the current game {red}[try if broken]',
            'long' => 'Only the person who announced it can stop it. It will kill all users in the hunger world.  This can be used to abort a game if the hunger game appears to be stuck, or if everyone in a game has left the server.',
            ),
        'function' => 'umc_hunger_stop',
    ),
    'status' => array(
        'help' => array(
            'args' => '[game-id]',
            'short' => 'Show game status',
            'long' => 'Show the status of the current hunger game, or of [game-id].',
            ),
        'function' => 'umc_hunger_status',
    ),
    'check' => array(
        'help' => array(
            'args' => '',
            'short' => 'Check if there is a winner',
            'long' => 'Check if the hunger game should have ended, but did not;'
            . 'For example, if people log out without leaving hunger.;',
        ),
        'function' => 'umc_hunger_check',
    ),
    'trophy' => array(
        'help' => array(
            'args'  => '[game-id] [player]',
            'short' => "Claim a trophy {red}[{$HUNGER['trophy_cost']} Uncs]",
            'long'  => 'Allows you to obtain, as a trophy, {green}the head of any opponent{white} you defeated in hunger;'
                    . '{yellow}Under These Conditions:;'
                    . '1. {gray}You must have {yellow}WON{gray} the game with {yellow}[game-id];'
                    . '2. {gray}You may only claim {yellow}1 HEAD{gray} per game.;'
                    . '3. {gray}You may claim the head of {green}any player{gray} who lost that game, regardless of whether you personally killed them or not.;'
                    . "4. {gray}Claiming a head {red}Costs {$HUNGER['trophy_cost']} Uncs{gray}.;"
                    . '5. {gray}You may only claim a head {yellow}within 24 hours{gray} of your victory.;'
                    . '6. {gray}You must be in a survival world and have an {yellow}empty hand{gray} to receive your trophy.;'
        ),
        'function' => 'umc_hunger_trophy',
        'security' => array(
            'worlds' => array('empire', 'kingdom', 'skylands', 'aether'),
         ),
    ),
);

/**
 * set HUNGER variables with current game and players
 * @global type $HUNGER
 */
function umc_hunger_find_current_game() {
    global $HUNGER;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    XMPP_ERROR_trace('HUNGER', $HUNGER);

    if (!isset($HUNGER['current_game'])) {
        $sql = "SELECT *, timediff(now(),start) as duration
            FROM minecraft_iconomy.hunger_games
            WHERE status='preparing' OR status='started' LIMIT 1;";
        $data = umc_mysql_fetch_all($sql);
        if (count($data) > 0) {
            $HUNGER['current_game'] = $data[0];
            umc_hunger_find_players();
            XMPP_ERROR_trace("Hunger game & players added!");
        } else {
            XMPP_ERROR_trace("No Hunger game available!");
        }
    } else if (!isset($HUNGER['current_game']['players'])) {
        umc_hunger_find_players();
        XMPP_ERROR_trace("Hunger players added!");
    } else {
        XMPP_ERROR_trace("Hunger game exists, players already added!");
    }
    XMPP_ERROR_trace('HUNGER @ end of umc_hunger_find_current_game', $HUNGER);
}

/**
 * Find players in the current game and add them to $HUNGER
 * preparing players are alive
 *
 * @global array $HUNGER
 * @param bool $only_alive -> add only alive players
 */
function umc_hunger_find_players($game_id = false) {
    global $HUNGER, $UMC_USER;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    XMPP_ERROR_trace('HUNGER', $HUNGER);
    $username = $UMC_USER['username'];

    $current_game = false;
    if (!$game_id) { // get players from the current game
        if (!isset($HUNGER['current_game'])) {
            XMPP_ERROR_trigger("umc_hunger_find_players: No game found");
            return;
        }
        $game_id = $HUNGER['current_game']['id'];
        $current_game = true;
    }

    $sql = "SELECT status, uuid FROM minecraft_iconomy.hunger_players WHERE game_id=$game_id;";
    $D = umc_mysql_fetch_all($sql);
    if ($current_game) { // we have a current game
        // first, get all players from current game global variable if exists
        $players = array();
        if (isset($HUNGER['current_game']['players'])) {
            if (isset($HUNGER['current_game']['players']['alive'])) {
                foreach ($HUNGER['current_game']['players']['alive'] as $uuid => $username) {
                    $players[$uuid] = $username;
                }
            }
            if (isset($HUNGER['current_game']['players']['dead'])) {
                foreach ($HUNGER['current_game']['players']['dead'] as $uuid => $username) {
                    $players[$uuid] = $username;
                }
            }
        }
        // now get players who are in database in case there are none in the variable yet
        $dead = array();
        $alive = array();
        foreach ($D as $row) {
            if (!isset($players[$row['uuid']])) {
                $username = umc_user2uuid($row['uuid']);
            } else {
                $username = $players[$row['uuid']];
            }
            if ($row['status'] == 'playing' || $row['status'] == 'preparing') {
                $alive[$row['uuid']] = strtolower($username);
            } else {
                $dead[$row['uuid']] = strtolower($username);
            }
        }
        if (count($dead) > 0) {
            $HUNGER['current_game']['players']['dead'] = $dead;
        }
        if (count($alive) > 0) {
            $HUNGER['current_game']['players']['alive'] = $alive;
        }
    } else { // get an old game
        foreach ($D as $row) {
            $username = umc_user2uuid($row['uuid']);
            $HUNGER['old_game'][$game_id]['player'][$row['uuid']] = $username;
        }
    }
}


/**
 * announce a hunger game and add the first user
 *
 * @global type $UMC_USER
 *
 */
function umc_hunger_announce() {
    global $UMC_USER, $HUNGER;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    XMPP_ERROR_trace('HUNGER', $HUNGER);
    $player = $UMC_USER['username'];
    $uuid = $UMC_USER['uuid'];

    // check if there is an unfinished game
    umc_hunger_find_current_game();

    // there is a game already
    if (isset($HUNGER['current_game'])) {
        XMPP_ERROR_trigger("Hunger Announce failed due to existing game");
        $admin_uuid = $HUNGER['current_game']['admin'];
        $admin_username = umc_user2uuid($admin_uuid);
        umc_echo("[Hunger] There is another game running, started by user $admin_username");
        if ($admin_username == $player) {
            umc_error("[Hunger] That's you. You may stop the game with: /hunger stop");
        }
        // check if the player is still online
        if (!in_array($admin_username, $UMC_USER['online_players'])) {
            umc_error("[Hunger] $admin_username is not online, so you may stop the game by typing /hunger stop");
        } else {
            umc_error("[Hunger] $admin_username is still online. You may ask him/her to stop the game.");
        }
    // we can start a new game
    } else {
        umc_hunger_remove_perms('all');
        umc_ws_cmd("pex reload", 'asConsole');
        XMPP_ERROR_send_msg("$player announced new Hunger game");
        if ($HUNGER['announce']) {
            umc_mod_broadcast("[Hunger] A Hunger Game is being organized by $player!", $HUNGER['channel']);
            umc_mod_broadcast("[Hunger] Use '/hunger join' to join the game!", $HUNGER['channel']);
        } else {
            umc_echo("[Hunger] A Hunger Game is being organized by $player!");
            umc_echo("[Hunger] Use '/hunger join' to join the game!");
        }
        // get center
        $center = umc_hunger_find_random_location();

        // create database entry
        $sql = "INSERT INTO minecraft_iconomy.`hunger_games` (`admin`, `status`, `x`, `z`)
            VALUES ('$uuid', 'preparing', {$center['x']}, {$center['z']});";
        umc_mysql_query($sql, true);

        umc_hunger_addplayer($uuid);

        // we have one player, setup minimal world
        umc_hunger_adjust_world_size();
        // add the player

        // kill everyone in the hunger world, just in case
        umc_hunger_kill_all_in_world();
    }
}




//  Start a game (after announced and players join)
//
function umc_hunger_start() {
    global $HUNGER, $UMC_USER;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    XMPP_ERROR_trigger("Starting hunger game");
    $uuid = $UMC_USER['uuid'];
    // Check if a game can be started, find the game_id if so
    umc_hunger_find_current_game();
    if (!isset($HUNGER['current_game'])) {
        umc_error("[Hunger] There is no game to start. Use '/hunger announce' first.");
    }
    if ($HUNGER['current_game']['admin'] != $uuid) {
        $admin_username = umc_user2uuid($HUNGER['current_game']['admin']);
        umc_error("[Hunger] You didn't announce game #{$HUNGER['current_game']['id']}. Only $admin_username can start the game.");
    }
    if ($HUNGER['current_game']['status'] != 'preparing') {
        umc_error("[Hunger] The game is has already been started.");
    }

    $id = $HUNGER['current_game']['id'];

    $player_list = $HUNGER['current_game']['players']['alive'];

    // check if everyone is there
    $hunger_world_users = umc_users_by_world('hunger');

    $finalplayers = array();
    $droppedplayers = array();

    foreach ($player_list as $player_uuid => $player) {
        if (isset($player_uuid, $hunger_world_users)) {
            $finalplayers[$player_uuid] = $player;
        } else {
            $droppedplayers[$player_uuid] = $player;
        }
    }
    // Can't start unless there are at least 2 players
    if (count($finalplayers) < 2) {
        umc_echo("[Hunger] You need at least 2 players to join to start the game.");
        XMPP_ERROR_trigger("Hunger did not start, not enough players");
        //return;
    }

    // Starting the game...
    // Update game and player status
    $sql = "UPDATE minecraft_iconomy.hunger_games set status='started', start=NOW() WHERE id=$id;";
    umc_mysql_query($sql, true);

    foreach ($droppedplayers as $uuid => $player) {
        if ($HUNGER['announce']) {
            umc_mod_broadcast("[Hunger] The user $player did not make it into the hunger world before the game start and will be removed", $HUNGER['channel']);
        } else {
            umc_echo("[Hunger] The user $player did not make it into the hunger world before the game start and will be removed");
        }
        $sql = "UPDATE hunger_players set status='noshow' WHERE status='preparing' and game_id=$id AND uuid='$uuid';";
        umc_mysql_query($sql, true);
    }

    // adjust world size
    umc_hunger_adjust_world_size(count($finalplayers));

    foreach ($finalplayers as $uuid => $player) {
        // we start the game, so let them edit the world
        $cmd = "pex user $uuid add modifyworld.* hunger";
        XMPP_ERROR_send_msg("Giving build rights: $cmd");
        umc_ws_cmd($cmd, 'asConsole');
        umc_ws_cmd("ci $player", 'asConsole');
        $sql = "UPDATE minecraft_iconomy.hunger_players set status='playing' WHERE status='preparing' and game_id=$id AND uuid='$uuid';";
        umc_mysql_query($sql, true);
    }

    $world_size = $HUNGER['current_game']['size'];
    if ($HUNGER['announce']) {
        umc_mod_broadcast("[Hunger] The hunger game has begun! World Size: $world_size", $HUNGER['channel']);
        umc_mod_broadcast("Participants: " . implode(", ", $finalplayers), $HUNGER['channel']);
    } else {
        umc_echo("[Hunger] The hunger game has begun! World Size: $world_size");
        umc_echo("Participants: " . implode(", ", $finalplayers));
    }
    XMPP_ERROR_send_msg("hunger game started with size $world_size and players: ". implode(", ", $finalplayers));
}

// Stop (abort) the current game before its natural conclusion
//
function umc_hunger_stop() {
    global $HUNGER, $UMC_USER;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $player = $UMC_USER['username'];
    $uuid = $UMC_USER['uuid'];

    umc_hunger_find_current_game();
    $admin_username = umc_user2uuid($HUNGER['current_game']['admin']);
    if ($HUNGER['current_game']) {
        umc_echo("[Hunger] Current game found, started by user $admin_username");
        /*if ($game['status'] == 'started') {
            umc_echo("{red}The game is in progress and can't be stopped unless there is a winner...");
            umc_hunger_check_winner($game['id']);
            return;
        }*/
        // check if the player is the admin for the current game or if the admin is offline
        if ($uuid == $HUNGER['current_game']['admin'] || (!in_array($admin_username, $UMC_USER['online_players']))) {
            if ($HUNGER['announce']) {
                umc_mod_broadcast("[Hunger] The current hunger game has been cancelled by $player.", $HUNGER['channel']);
            } else {
                umc_echo("[Hunger] The current hunger game has been cancelled by $player.");
            }
            $sql = "UPDATE minecraft_iconomy.hunger_games SET end=NOW(), status='aborted' WHERE id={$HUNGER['current_game']['id']};";
            umc_mysql_query($sql, true);
        } else {
            umc_error("[Hunger] $admin_username is still online. Ask him/her to stop the game.");
        }
    } else {
        umc_echo("[Hunger] There is no hunger game to stop right now.");
    }
    // remove all players
    umc_hunger_kill_all_in_world();

    // do this anyhow for security
    XMPP_ERROR_send_msg("hunger game stopped");
    umc_hunger_remove_perms('all');
    umc_log('hunger', 'stop', "game was stopped by $player");

}

function umc_hunger_abort() {
    global $HUNGER;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    umc_hunger_find_current_game();

    if (!isset($HUNGER['current_game'])  || !$HUNGER['current_game']) {
        umc_echo("No game found");
    } else {
        $game = $HUNGER['current_game'];
    }
    if ($HUNGER['announce']) {
        umc_mod_broadcast("[Hunger] The current hunger game has been {red}cancelled{purple}.",$HUNGER['channel']);
    } else {
        umc_echo("[Hunger] The current hunger game has been {red}cancelled{purple}.");
    }
    $sql = "UPDATE minecraft_iconomy.hunger_games SET end=NOW(), status='aborted' WHERE id={$game['id']};";
    umc_mysql_query($sql, true);
    // do this anyhow for security
    umc_hunger_remove_perms('all');
    umc_log('hunger', 'abort', "game was cancelled");
    XMPP_ERROR_send_msg("hunger game aborted");
}

/**
 * mode is either warp or modify
 * This gives people build permission in the hunger lot
 */
function umc_hunger_remove_perms($mode = 'all') {
    $perms = array(
        'modify' => array(
            'find' => "permission='modifyworld.*' AND world='hunger'",
            'permission' => "modifyworld.* hunger",
        ),
        'warp' => array(
            'find' => "permission='essentials.warps.hunger' AND world='hunger'",
            'permission' => "essentials.warps.hunger",
        ),
    );

    if ($mode == 'all') {
        $var = $perms['modify'];
    } else {
        $var = $perms[$mode];
    }

    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $sql = "SELECT name FROM minecraft_srvr.permissions WHERE {$var['find']};";
    $data = umc_mysql_fetch_all($sql);
    foreach ($data as $row) {
        $uuid = $row['name'];
        $cmd = "pex user $uuid remove {$var['permission']}";
        umc_ws_cmd($cmd, 'asConsole');
        XMPP_ERROR_send_msg("removed {$var['permission']} permissions from $uuid");
    }

    if ($mode == 'all') {
        umc_hunger_remove_perms('warp');
    }
}

function umc_hunger_status() {
    global $UMC_PLAYER, $HUNGER;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    umc_hunger_find_current_game();
    $args = $UMC_PLAYER['args'];
    if (isset($args[2])) {
        $game_id = $args[2];
    } else {
        $game_id = false;
    }
    $game = false;
    if (!$game_id && isset($HUNGER['current_game'])) {
        $game = $HUNGER['current_game'];
        $id = $game['id'];
        $player_list = $HUNGER['current_game']['players'];
    } else if ($game_id) {
        $sql = "SELECT *, timediff(end,start) as duration FROM minecraft_iconomy.hunger_games WHERE id = $game_id";

        $data = umc_mysql_fetch_all($sql);
        if (count($data) > 0) {
            $game = $data[0];
            $id = $game['id'];
            $player_list = $game['players'];
        }
    } else {
        umc_error("There is no current game!");
    }
    umc_header();
    if ($game) {
        $num_players = count($player_list);
        $admin_username = umc_user2uuid($game['admin']);

        if ($game['status'] == 'preparing') {
            umc_echo("[Hunger] {gray}A Hunger Game has been announced by {gold}$admin_username");
            umc_echo("[Hunger] {gray}This game is still being organized. {green}$num_players {gray}player(s) participating:");
            umc_hunger_format_player_list("{gold}", $player_list);
        } else if ($game['status'] == 'aborted') {
            umc_echo("[Hunger] {gray}This hunger game was announced by $admin_username, but was aborted.");
        } else {
                $duration = umc_pretty_duration($game['duration']);
            if ($game['status'] == 'started') {
                umc_echo("[Hunger] {green}A Hunger Game is in progress (ID $id), organized by {gold}$admin_username");
                umc_echo("[Hunger] {green}The game has lasted {$duration}{green} so far. Player Status:");
            } else {
                umc_echo("[Hunger] {gold}A completed Hunger Game organized by $admin_username");
                umc_echo("[Hunger] {gold}The game lasted {$duration}{green}. Final Player Status:");
            }

            $sql = "SELECT hunger_players.status as status, timediff(hunger_players.death,hunger_games.start) as duration, username
                FROM minecraft_iconomy.hunger_games
                LEFT JOIN minecraft_iconomy.hunger_players ON hunger_players.game_id = hunger_games.id
                LEFT JOIN minecraft_srvr.UUID ON hunger_players.uuid=UUID.UUID
                WHERE hunger_games.id = $id";
            $rst = umc_mysql_query($sql);
            while ($row = umc_mysql_fetch_array($rst)) {
                $duration = umc_pretty_duration($row['duration']);
                if ($row['status'] == 'playing') {
                    $player_status = "{green}[Playing]";
                }
                else if ($row['status'] == 'left') {
                    $player_status = "{gray}[Left @ {$duration}{gray}]";
                }
                else if ($row['status'] == 'dead') {
                    $player_status = "{red}[Died @ {$duration}{red}]";
                }
                else if ($row['status'] == 'winner') {
                    $player_status = "{white}[Winner!]";
                }
                else {
                    $player_status = "{blue}[Error]";
                }
                $player = $row['username'];
                umc_echo("{gold}$player $player_status");
            }
        }
    } else {
        umc_echo("[Hunger] {red}No game currently in progress. See {yellow}/hunger history");
    }
    umc_footer();
}

function umc_hunger_check() {
    global $HUNGER;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    XMPP_ERROR_trace('HUNGER', $HUNGER);
    umc_hunger_find_current_game();

    if (!isset($HUNGER['current_game'])) {
        umc_error("[Hunger] There is no current hunger game.");
    } elseif ($HUNGER['current_game']['status'] != 'started') {
        umc_error("[Hunger] The game hasn't started yet.");
    } elseif ($HUNGER['current_game']['duration'] < '00:00:30') {
        umc_error("[Hunger] The game has to be at least 30 seconds old to use this function.");
    } else {
        // check if there are any players who are not in the right world
        $map_players = umc_users_by_world('hunger');
        umc_hunger_find_players();

        $db_players = $HUNGER['current_game']['players']['alive'];
        foreach ($db_players as $db_uuid => $dbp) {
            $found = false;
            foreach ($map_players as $mapp) {
                if ($dbp == $mapp) {
                    $found = true;
                }
            }
            if (!$found) {
                $sql = "UPDATE minecraft_iconomy.`hunger_players` SET status='left', death=NOW() WHERE uuid='$db_uuid' and game_id = {$HUNGER['current_game']['id']};";
                umc_mysql_query($sql, true);
            }
        }
        if(!umc_hunger_check_winner()) {
            umc_error("The game is still on, at least 2 players appear to be in-game.");
        }
    }
}


function umc_hunger_trophy() {
    global $UMC_USER, $HUNGER;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $args = $UMC_USER['args'];
    $player = $UMC_USER['username'];
    $uuid = $UMC_USER['uuid'];
    $game_id =  isSet($args[2]) ? $args[2]+0 : null;
    if(!$game_id) {
        umc_error("[Hunger] {red}You must specify a [game-id] for which you were the winner.");
    }
    $victim = strtolower(umc_sanitize_input($args[3],'player'));
    $victim_uuid = umc_user2uuid($victim);
    $sql_games = "SELECT *, timediff(NOW(),end) as age FROM minecraft_iconomy.hunger_games WHERE id = $game_id";
    $rst_games = umc_mysql_query($sql_games);
    if (mysql_num_rows($rst_games) > 0) {
        $game = umc_mysql_fetch_array($rst_games);
    }
    if(!$game) {
        umc_error("[Hunger] {red}No such game with game-id {yellow}$game_id");
    }
    if($game['winner'] != $uuid) {
        umc_error("[Hunger] {red}You were not the winner of game-id {yellow}$game_id{red}.");
    }
    if($game['trophy_claimed'] == 'y') {
        umc_error("[Hunger] {red}You already claimed a trophy for game-id {yellow}$game_id{red}.");
    }
    if($game['age'] > '24:00:00') {
        // umc_error("{red}That game ended more than 24 hours ago, the corpses are too rotten.");
    }
    if ($victim_uuid == $uuid) {
        umc_error("[Hunger] {red}You can't claim your own head. Weirdo.");
    }
    umc_echo("finding players of game $game_id...");

    $HUNGER = umc_hunger_find_players($game_id);
    $players = $HUNGER['old_game'][$game_id]['players'];
    if(!in_array($victim, $players)) {
        umc_error("[Hunger] {red}{gold}$victim{red} was not vanquished in that game.");
    }
    umc_echo("checking your account balance....");
    $balance = umc_money_check($player);
    if($balance < $HUNGER['trophy_cost']) {
        umc_error("[Hunger] {red}You can't afford a trophy, they cost {green}{$HUNGER['trophy_cost']} Uncs");
    }
    umc_echo("Account is fine...");
    $item_slot = $UMC_USER['current_item'];
    if($item_slot != 0 || isset($UMC_USER['inv'][$item_slot])) {
        umc_error("[Hunger] {red}You have to pick first hotbar slot, and it has to be empty.");
    }
    umc_echo("[Hunger] all good, taking trophy...");
    #-- All good, do the work!
    $sql = "UPDATE minecraft_iconomy.hunger_games SET trophy_claimed = 'y' WHERE id = $game_id";
    umc_mysql_query($sql, true);
    umc_echo("[Hunger] charging {$HUNGER['trophy_cost']}...");
    umc_money($player, false, $HUNGER['trophy_cost']);

    umc_echo("[Hunger] {yellow}[$]{gray} You have been charged {yellow}{$HUNGER['trophy_cost']}{gray} uncs.");
    umc_echo("[Hunger] getting head...");

    umc_ws_cmd("minecraft:give $player skull 1 3 {SkullOwner:\"$victim\"}","asConsole");
    umc_echo("[Hunger] {purple}Enjoy this small memento of your victory!");
    umc_log('hunger', 'trophy', "$player got the head of $victim");
}



// ----------------------- Helper Functions -----------------------------


/**
 * Check if there are players which left the world and remove permissions
 * accordingly. Is managed in the WS_INIT array on top
 *
 * @global type $UMC_USER
 */
function umc_hunger_updatestatus() {
    global $UMC_USER, $HUNGER;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $player = $UMC_USER['username'];
    $uuid = $UMC_USER['uuid'];
    if (isset($UMC_USER['world'])) {
        $world = $UMC_USER['world'];
    } else {
        $world = false;
    }
    $event = $UMC_USER['args'][1];
    // XMPP_ERROR_trigger("Event: " . $event);

    $remove_right_events = array(
        'PlayerChangedWorldEvent',
        'PlayerGameModeChangeEvent',
        'PlayerPortalEvent',
        'PlayerRespawnEvent',
        'PlayerTeleportEvent',
        'PlayerDeathEvent',
    );

    // anyone who joins the game in the hunger world should be kicked out
    if ($event == 'PlayerJoinEvent' && ($world == 'hunger')) {
        umc_hunger_removeplayer($uuid, false);
        umc_echo("[Hunger] {red}Somehow you are stuck in hunger! Let's fix that... [kill]");
        umc_ws_cmd("ci $player", 'asConsole');
        umc_ws_cmd("kill $player", 'asConsole');
    // users quitting from hunger also quit the game
    } else if ($event == 'PlayerQuitEvent' && ($world == 'hunger')) {
        umc_hunger_removeplayer($uuid, false);
    // game player in running games should be removed on teleport
    } else if ($event == 'PlayerDeathEvent') {
        // some player died somewhere, check $UMC_USER for players that are in the game AND have health=0
        foreach ($UMC_USER['player_data'] as $player_uuid => $player_data) {
            if ($player_data['Health'] == 0 || $player_data['GameMode'] == 'CREATIVE') {
                umc_hunger_removeplayer($player_uuid, true);
            }
        }
    } else {
        umc_hunger_find_current_game();
        $game = false;
        if (isset($HUNGER['current_game'])) {
            $game = $HUNGER['current_game'];
        }
        // player died
        if ($game && isset($uuid, $game['players']['alive']) && ($game['status'] != 'preparing'))  {
            if (in_array($event, $remove_right_events)) { // we have a game, and an event
                $died = false;
                if ($event == 'PlayerRespawnEvent' || $event == 'PlayerDeathEvent') {
                    $died = true;
                }
                umc_hunger_removeplayer($uuid, $died);
            }
        }
    }
}


// Add a player to the hunger game
function umc_hunger_addplayer() {
    global $HUNGER, $UMC_USER;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    umc_hunger_find_current_game();
    $player_uuid = $UMC_USER['uuid'];
    $player = $UMC_USER['username'];

    if (isset($HUNGER['current_game'])) {
        $game = $HUNGER['current_game'];
        if ($game['status'] == 'preparing') {
            $game_id = $game['id'];
        } else {
            umc_error("[Hunger] The hunger game has already started, it's too late to join");
        }
    } else {
        umc_error("[Hunger] Could not find any active games to join");
    }

    $sql_find = "SELECT id FROM minecraft_iconomy.hunger_players WHERE game_id = $game_id AND uuid='$player_uuid'";
    $find_data = umc_mysql_fetch_all($sql_find);
    if (count($find_data) > 0) {
        umc_echo("[Hunger] You already joined this game!");
        return;
    }

    $admin = umc_user2uuid($HUNGER['current_game']['admin']);

    umc_ws_cmd("tell $admin The user $player just joined the hunger game!", 'asConsole');
    if ($HUNGER['announce']) {
        umc_mod_broadcast("[Hunger] The user $player just joined the hunger game!", $HUNGER['channel']);
    } else {
        umc_echo("[Hunger] The user $player just joined the hunger game!");
    }
    // Warp the user

    umc_ws_cmd("pex user $player_uuid add essentials.warps.hunger", 'asConsole');
    umc_ws_cmd("warp hunger $player");

    $sql = "INSERT INTO minecraft_iconomy.`hunger_players` (`uuid`, `game_id`, `status`) VALUES ('$player_uuid', $game_id, 'preparing');";
    umc_mysql_query($sql, true);
    umc_echo("[Hunger] You ($player) were added to Hunger Game #$game_id.");
    XMPP_ERROR_send_msg("Added user $player to the hunger game");
}

// Remove a player from the hunger game
//
function umc_hunger_removeplayer($died = true) {
    global $HUNGER, $UMC_USER;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    $uuid = $UMC_USER['uuid'];
    $username = $UMC_USER['username'];

    if (isset($HUNGER['current_game'])) {
        $game_id = $HUNGER['current_game']['id'];
        $status = $HUNGER['current_game']['status'];
    } else {
        return;
    }

    if (!$died) {
        $sql = "UPDATE minecraft_iconomy.`hunger_players` SET status='left', death=NOW()
            WHERE game_id=$game_id AND uuid='$uuid';";
    } else {
        $sql = "UPDATE minecraft_iconomy.`hunger_players` SET status='dead', death=NOW()
            WHERE game_id=$game_id AND uuid='$uuid';";
    }
    $row_count = umc_mysql_execute_query($sql);
    if ($row_count == 0) {
        return;
    }

    // read results from DB
    umc_hunger_find_players();
    umc_ws_cmd("pex user $uuid remove essentials.warps.hunger", 'asConsole');
    umc_ws_cmd("pex user $uuid remove modifyworld.* hunger", 'asConsole');
    XMPP_ERROR_send_msg("$username was removed from the hunger game");

    $winner = false;
    // Check for a win if the game is still on
    if ($status == 'started') {
        $winner = umc_hunger_check_winner();
        if (!$winner) {
            $admin_uuid = $HUNGER['current_game']['admin'];
            $admin = umc_user2uuid($admin_uuid);
            umc_echo("[Hunger] {green}You ({gold}$username{green}) were removed from Hunger Game {white}#$game_id.");
            if ($HUNGER['announce']) {
                umc_mod_broadcast("[Hunger] The user {gold}$username{purple} just {cyan}left{gold} the hunger game!", $HUNGER['channel']);
            } else {
                umc_echo("[Hunger] The user {gold}$username{purple} just {cyan}left{gold} the hunger game!");
            }
            umc_ws_cmd("tell $admin The user $username just left the hunger game!", 'asConsole');
        }
    } else if ($status == 'preparing') {
        // did we remove the last player of a game being prepared?
        $sql = "SELECT * FROM minecraft_iconomy.`hunger_players` WHERE status='preparing' AND game_id=$game_id;";
        $D3 = umc_mysql_fetch_all($sql);
        $num = count($D3);
        if ($num == 0) {
            // last player has quit, end the game
            umc_hunger_abort();
        }
    }
}

// Check if the win condition has been met.

function umc_hunger_check_winner() {
    global $HUNGER;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    umc_hunger_find_current_game();

    $player_list = $HUNGER['current_game']['players']['alive'];
    $id = $HUNGER['current_game']['id'];

    // if there is only one player. it's the winner
    if (sizeof($player_list) == 1) {
        $winner_uuid = key($player_list);
        XMPP_ERROR_send_msg("Found winner! $winner_uuid");
        $winner = current($player_list);
        // last player is dead, so we have an aborted game.
        if (!in_array($winner, $HUNGER['current_game']['players']['alive'])) {
            $sql_game = "UPDATE minecraft_iconomy.`hunger_games`
                SET status='aborted', end=NOW() WHERE id = $id;";
            umc_mysql_query($sql_game, true);
            $sql_player = "UPDATE minecraft_iconomy.`hunger_players`
                SET status='left' WHERE uuid='$winner_uuid' and game_id = $id;";
            umc_mysql_query($sql_player, true);
            if ($HUNGER['announce']) {
                umc_mod_broadcast("The Hunger Game has been aborted, no active players online.", $HUNGER['channel']);
            } else {
                umc_echo("The Hunger Game has been aborted, no active players online.");
            }
        } else { // properly finished game
            $sql_winner = "UPDATE minecraft_iconomy.`hunger_games`
                SET status='ended', winner='$winner_uuid', end=NOW() WHERE id = $id;";
            umc_mysql_query($sql_winner, true);
            $sql = "UPDATE minecraft_iconomy.`hunger_players` SET status='winner' WHERE uuid='$winner_uuid' and game_id = $id;";
            umc_mysql_query($sql, true);
            if ($HUNGER['announce']) {
                umc_mod_broadcast("The Hunger Game has ended! $winner wins!;", $HUNGER['channel']);
            } else {
                umc_echo("The Hunger Game has ended! $winner wins!;");
            }
        }
        umc_hunger_remove_perms('all');
        umc_hunger_kill_all_in_world();
        return true;
    } else {
        return false;
    }
}


/**
 * This updates the size of the world border we play in according to the number of players
 * it turns out 25x25 blocks per user
 * returns the world size
 *
 * @param type $x
 * @param type $z
 * @param type $playercount
 */
function umc_hunger_adjust_world_size() {
    global $HUNGER, $HUNGER;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    XMPP_ERROR_trace('HUNGER', $HUNGER);

    umc_hunger_find_current_game();

    $duration = $HUNGER['current_game']['duration'];

    $player_count = count($HUNGER['current_game']['players']['alive']);
    $size = round(sqrt($player_count * 10000) / 2);

    $d = explode(":",$duration);
    $minutes = $d[1] + ($d[0] * 60);

    if ($minutes > 10) {
        $newsize = $size - $minutes;
    } else {
        $newsize = $size;
    }
    $final_size = max($newsize, 50);
    $HUNGER['current_game']['size'] = $final_size;
    $x = $HUNGER['current_game']['x'];
    $z = $HUNGER['current_game']['z'];

    $command = "wb hunger set $final_size $final_size $x $z";
    umc_ws_cmd($command, 'asConsole');
}

/**
 * tries to find a location far out that was not used as a hunger game before
 * this will also create the warp point in essentials
 *
 * @return array('x' => $center_x, 'z' => $center_z)
 */
function umc_hunger_find_random_location() {
    // 30 Mio is the MC hard limit
    $min_val = 1000;
    $max_val = 300000 - 2000;  //we take the max and some margin

    // find a center
    $center_x = rand($min_val, $max_val);
    $center_z = rand($min_val, $max_val);
    // which quarter of the map?
    $dir_x = rand(0,1);
    if ($dir_x == 0) {
        $center_x = $center_x * -1;
    }
    $dir_z = rand(0,1);
    if ($dir_z == 0) {
        $center_z = $center_z * -1;
    }
    // check if a game existed on that location
    $sql = "SELECT id FROM minecraft_iconomy.hunger_games
        WHERE x > ($center_x - 500)
	    AND x < ($center_x + 500)
	    AND z < ($center_z + 500)
	    AND z > ($center_z - 500);";
    $data = umc_mysql_fetch_all($sql);
    // too close, try again
    if (count($data) > 0) {
        umc_log('hunger', 'found_location_fail', "Found alrady existing location X: $center_x Z: $center_z - retrying");
        XMPP_ERROR_trigger("hunger rejected location X: $center_x Z: $center_z, trying again");
        return umc_hunger_find_random_location();
    } else {
        XMPP_ERROR_send_msg("hunger Found location X: $center_x Z: $center_z");
        // update warp point
        $text = "yaw: 0.0\nname: hunger\npitch: 0.0\nz: $center_z\ny: 250\nworld: hunger\nx: $center_x";
        $filename = '/home/minecraft/server/bukkit/plugins/Essentials/warps/hunger.yml';
        file_put_contents($filename, $text);
        // reload essentials
        umc_ws_cmd('ess reload', 'asConsole');
        return array('x' => $center_x, 'z' => $center_z);
    }
}

function umc_pretty_duration($duration) {
    $d = split(":",$duration);
    if(!isSet($d[1])) {
        return;
    }
    $firstcol = 'gray';
    $secondcol = 'gray';
    if ($d[0] > 0) {
        $firstcol = 'cyan';
    }
    if ($d[1] >0 || $d[0] > 0) {
        $secondcol = 'cyan';
    }
    $code = "{{$firstcol}}{$d[0]}{blue}:{{$secondcol}}{$d[1]}{blue}:{cyan}{$d[2]}";
    return $code;
}

// --- Display-related ---
// Show a formatted player listing
function umc_hunger_format_player_list($prefix, $player_list) {
    $cnt = 0;
    $list_text = "$prefix";
    XMPP_ERROR_trace("check", var_export($player_list, true));
    foreach ($player_list as $status => $player_data) {
        $list_text="{green}$status:$prefix ";
        $inner = '';
        foreach($player_data as $uuid => $player) {
            if ($cnt % 5 == 0 && $cnt > 0) {
                $inner= ";$prefix";
            }
            if ($cnt == sizeof($player_list)) {
                $list_text .= " and $player";
            } else if ($cnt == 0) {
                $list_text .= "$player";
            } else {
                $list_text .= ", $inner$player";
            }
            $cnt++;
        }
    }
    umc_echo($list_text);
}

/*
 * Kills everyone in the hungr world.
 */
function umc_hunger_kill_all_in_world() {
    $hunger_world = umc_users_by_world('hunger');
    foreach ($hunger_world as $h_player) {
        umc_ws_cmd("ci $h_player;", 'asConsole');
        umc_ws_cmd("kill $h_player;", 'asConsole');
    }
}
