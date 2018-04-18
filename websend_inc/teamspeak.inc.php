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
 * This interfaces with a teamspeak server an allows users to authorize themselves
 * in teamspeak to get user rights. This is made to prevent non-game users
 * or banned users from spamming the teamspeak server.
 */
global $UMC_SETTING, $WS_INIT, $UMC_TEAMSPEAK;

$WS_INIT['teamspeak'] = array(  // the name of the plugin
    'disabled' => true,
    'events' => array('user_banned' => 'umc_ts_clear_rights', 'user_inactive' => 'umc_ts_clear_rights'),
    'default' => array(
        'help' => array(
            'title' => 'Teamspeak',  // give it a friendly title
            'short' => 'An interface to various Teamspeak-related features.',  // a short description
            'long' => "Allows you to give yourself teamspeak Settler level, list teamspeak users etc.", // a long add-on to the short  description
            ),
    ),
    'auth' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Authorize your teamspeak client',
            'long' => "This will authorize your teamspeak client to get talking rights. Find your Teamspeak Unique ID at Settings -> Identities",
        ),
        'function' => 'umc_ts_authorize',
        'security' => array(
            'level'=>'Settler',
        ),
    ),
    'who' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'List all users on the Teamspeak server',
            'long' => "This will list all users that are currently logged in on the teamspeak server",
        ),
        'function' => 'umc_ts_display_users',
        'security' => array(
            'level'=>'Settler',
        ),
    ),
    'msg' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Send a privat message to a TS user',
            'long' => "This will send a private text message to a TS user. Please note that TS users cannot send private messages back.",
        ),
        'function' => 'umc_ts_msg_user',
        'security' => array(
            'level'=>'Settler',
        ),
    ),
    /*
    'chat' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Send a message to the TS Channel called "In-Game Chat"',
            'long' => 'This will send a message readable by anyone in the TS Channel called "In-Game Chat"',
        ),
        'function' => 'umc_ts_chat',
        'security' => array(
            'level'=>'Settler',
        ),
    ),
     *
     */
);

/**
 * Teamspeak data. The numbers come from the internally given groupd ID on the teamspeak
 * server.
 */
$UMC_TEAMSPEAK = array(
    'ts_php_path' => '/home/includes/ts3phpframework/libraries/TeamSpeak3/TeamSpeak3.php',
    'server_query_string_path' => "/home/includes/certificates/teamspeak_query.txt",
    'server' => false,
    'user_groups' => array(
        6 => array('Owner'),
        10 => array('Master','MasterDonator',
            'Elder','ElderDonator'),
        7 => array('Settler','SettlerDonator',
            'Citizen','CitizenDonator',
            'Architect','ArchitectDonator',
            'Designer','DesignerDonator'),
    ),
    'ts_groups' => array(
        6 => 'TS Admin', 10 => 'TS Moderator', 7 => 'TS Settler', 8 => 'TS Guest'
    ),
);

/**
 * Send a private message to a teamspeak user. The teamspeak user
 * cannot reply back however.
 *
 * @global type $UMC_TEAMSPEAK
 * @global type $UMC_USER
 */
function umc_ts_msg_user() {
    global $UMC_TEAMSPEAK, $UMC_USER;
    $args = $UMC_USER['args'];
    $username = $UMC_USER['username'];
    umc_ts_connect();

    $target = $args[2];
    $message = strtolower($args[3]);

    // check if user exists:
    $users = umc_ts_list_users();
    if (!in_array($target, $users)) {
        umc_error("That user does not exist on Teamspeak!");
    }

    $ts3_Client = $UMC_TEAMSPEAK['server']->clientGetByName($target);
    $ts3_Client->message("[B]Priv. msg from $username:[/B] $message");
}

/**
 * Diaplay a list of all users on the TS server
 *
 * @global type $UMC_TEAMSPEAK
 */
function umc_ts_display_users() {
    $users = umc_ts_list_channels_users();

    $count = count($users);
    umc_header("Teamspeak Users: $count");
    $C = array();
    foreach ($users as $username => $channelname) {
        $C[$channelname][] = $username;
    }
    if ($count > 0) {
        foreach ($C as $channel => $users) {
            umc_echo("{green}$channel: {white}" . implode(", ", $users));
        }
    } else {
        umc_echo("Nobody online...");
    }
    umc_footer();
}

function umc_ts_authorize() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER, $UMC_TEAMSPEAK;
    XMPP_ERROR_trigger("Test");
    $check = umc_ts_connect();

    if (!$check) {
        umc_error("Could not connect to Teamspeak server!");
    }
    // get client by name
    $uuid = $UMC_USER['uuid'];
    $userlevel = $UMC_USER['userlevel'];
    $username = strtolower($UMC_USER['username']);

    // get required servergroup
    foreach ($UMC_TEAMSPEAK['user_groups'] as $g_id => $usergroups) {
        if (in_array($userlevel, $usergroups)) {
            $target_group = $g_id;
            break;
        }
    }
    XMPP_ERROR_trace("target group", $target_group);

    umc_header();

    // first, we clean out old clients that are registered with minecraft
    umc_ts_clear_rights($uuid, true);
    XMPP_ERROR_trace("Done clearing old rights");

    // then, we try to find a new user on the TS server to give rights to
    umc_echo("Your TS level is " . $UMC_TEAMSPEAK['ts_groups'][$target_group]);
    XMPP_ERROR_trace("User TS level is ", $UMC_TEAMSPEAK['ts_groups'][$target_group]);
    umc_echo("Looking for user $username in TS...");
    $users = umc_ts_list_users();
    $found = 0;
    // iterate all users to make sure that there are not 2 with the same nickname
    $first_match = false;
    foreach ($users as $id => $U) {
        XMPP_ERROR_trace("comparing user $username to ", $U['username']);
        if ($U['username'] == $username) {
            $found++;
            if (!$first_match) {
                $first_match = $id;
            }
            XMPP_ERROR_trace("found user ", "{$U['username']} / $id");
        }
    }
    XMPP_ERROR_trace("found no of users: ", $found);

    if ($found == 0) {
        umc_echo("You need to logon to Teamspeak with the EXACT same username (\"$username\")");
        umc_echo("Once you did that, please try again");
        umc_footer();
        return false;
    } else if ($found > 1) {
        umc_echo("There are 2 users with the same username (\"$username\") online.");
        umc_echo("Process halted. Make sure you are the only one with the correct username");
        umc_echo("If there is someone else hogging your username, please send in a /ticket");
        umc_footer();
        return false;
    }

    // we have a user
    XMPP_ERROR_trace("getting user client groups");
    // remove all groups
    $this_user_obj = $users[$first_match]['object'];
    $ts_dbid = $this_user_obj["client_database_id"];
    // remove all groups
    $servergroups = array_keys($UMC_TEAMSPEAK['server']->clientGetServerGroupsByDbid($ts_dbid));
    foreach ($servergroups as $sgid) {
        umc_echo($this_user_obj["client_nickname"] . " is member of group " . $UMC_TEAMSPEAK['ts_groups'][$sgid]);
        if ($sgid != $target_group && $sgid != 8) {
            umc_echo("Removing usergroup $sgid...");
            $UMC_TEAMSPEAK['server']->serverGroupClientDel($sgid, $ts_dbid); // remove user from group
        } else if ($sgid == $target_group) {
            umc_echo("Not removing usergroup $sgid...");
            $target_group = false;
        }
    }
    // add the proper group
    if ($target_group) { // add target group of required
        umc_echo("Adding you to group " . $UMC_TEAMSPEAK['ts_groups'][$target_group]);
        $this_user_obj->addServerGroup($target_group);
    }
    // get UUID
    $target_ts_uuid = $this_user_obj["client_unique_identifier"];
    $ts_uuid = umc_mysql_real_escape_string($target_ts_uuid);
    $ins_sql = "UPDATE minecraft_srvr.UUID SET ts_uuid=$ts_uuid WHERE UUID='$uuid';";
    umc_mysql_query($ins_sql, true);
    umc_echo("Adding TS ID $ts_uuid to database");
    umc_footer("Done!");


}

/**
 * Reset TS user rights to "Guest" for a specific user
 * This can be done even if the user is not online
 *
 * @param string $uuid
 * @param boolean $echo
 * @return boolean
 */
function umc_ts_clear_rights($uuid, $echo = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    umc_echo("Trying to remove old permissions:");
    global $UMC_TEAMSPEAK;

    // find out the TS id the user has been using from the database
    $check_sql = "SELECT ts_uuid FROM minecraft_srvr.UUID WHERE UUID='$uuid';";
    $D = umc_mysql_fetch_all($check_sql);
    if ($D[0]['ts_uuid'] == '') {
        if ($echo) {
            umc_echo("Old Client: No previous TS account detected.");
        }
        return false;
    } else {
        umc_echo("Found old permissions.");
        $ts_uuid = $D[0]['ts_uuid'];
    }
    XMPP_ERROR_trace("ts_uuid", $ts_uuid);

    umc_echo("Connecting to TS server.");
    umc_ts_connect(true);

    // find the TS user by that TS UUID
    echo("Searching for you on the TS server.");

    try {
        $ts_Clients_match = $UMC_TEAMSPEAK['server']->clientFindDb($ts_uuid, true);
    } catch (TeamSpeak3_Exception $e) {
        XMPP_ERROR_trace("Error ", $e->getCode() . ": " . $e->getMessage());
        $ts_Clients_match = 0;
        umc_echo("There is no client on the teamspeak server!");
        return;
    }
    if (count($ts_Clients_match) > 0) {
        XMPP_ERROR_trace("ts_Clients_match", $ts_Clients_match);
        umc_echo("Found user entries on TS server");
        $client_dbid = $ts_Clients_match[0];
        umc_echo("Client userid=$client_dbid");
        // enumerate all the groups the user is part of
        $servergroups = array_keys($UMC_TEAMSPEAK['server']->clientGetServerGroupsByDbid($client_dbid));
        XMPP_ERROR_trace("servergroups", $servergroups);
        // remove all servergroups except 8 (Guest)
        umc_echo("Removing all old usergroups:");
        foreach ($servergroups as $sgid) {
            if ($sgid != 8) {
                XMPP_ERROR_trace("Deleting group", $sgid);
                $UMC_TEAMSPEAK['server']->serverGroupClientDel($sgid, $client_dbid);
                if ($echo) {
                    umc_echo("Old Client: Removing Group " . $UMC_TEAMSPEAK['ts_groups'][$sgid]);
                }
            }
        }
        // also remove TS UUID from DB
        $ins_sql = "UPDATE minecraft_srvr.UUID SET ts_uuid='' WHERE ts_uuid='$ts_uuid';";
        XMPP_ERROR_trace("removing uuid from DB", $ts_uuid);
        umc_mysql_query($ins_sql, true);
        return true;
    } else {
        XMPP_ERROR_trace("Prev UUID not found");
        if ($echo) {
            umc_echo("Old Client: Previous TS UUID was invalid, nothing to do");
        }
        return false;
    }
}

/**
 * Create a HTML version of the TS server status
 *
 * @global type $UMC_TEAMSPEAK
 * @return string
 */
function umc_ts_viewer() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_TEAMSPEAK;
    umc_ts_connect(true);

    // no line breaks here!
    $pattern = "<div id='%0' class='%1 %3' summary='%2'><span class='%4'>%5</span><span class='%6' title='%7'>%8 %9</span><span class='%10'>%11%12</span></div>\n";

    $ts_viewer = new TeamSpeak3_Viewer_Html(
        "/admin/img/teamspeak/viewer/",
        "/admin/img/teamspeak/flags/",
        "data:image",
        $pattern
    );

    $out = $UMC_TEAMSPEAK['server']->getViewer($ts_viewer);

    // process text line by line

    $out_lines = explode("\n", $out);
    $out_new = '';
    foreach ($out_lines as $line) {
        if (!strstr($line, 'corpus query')) { // filter out serverquery accounts
            $out_new .= $line . "\n";
        }
    }

    $out_new .= "<div style=\"text-align:right;\"><a href=\"https://uncovery.me/communication/teamspeak/\">Help / Info</a></div>";
    return $out_new;
}

function umc_ts_list_channels_users() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_TEAMSPEAK;
    $check = umc_ts_connect();
    if (!$check) {
        return false;
    }
    $ts3_Channels = $UMC_TEAMSPEAK['server']->channelList();
    $users = array();
    foreach ($ts3_Channels as $ts3_Channel) {
        $channel_name = $ts3_Channel->__toString();
        // $id = $ts3_Channel->getId();
        $chan_users = umc_ts_list_users($ts3_Channel);
        foreach ($chan_users as $username) {
           $users[$username] = $channel_name;
        }
    }
    return $users;
}


/**
 * function to create an array of users on teamspeak
 * except system users
 *
 * @global type $UMC_TEAMSPEAK
 * @return type
 */
function umc_ts_list_users($channel = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_TEAMSPEAK;
    $check = umc_ts_connect();
    if (!$check) {
        return false;
    }
    $users = array();
    if (!$channel ) {
        $channel = $UMC_TEAMSPEAK['server'];
    }

    $usercount = count($channel->clientList());
    if ($usercount == 0) {
        XMPP_ERROR_send_msg("No users found");
        return false;
    }
    XMPP_ERROR_trace("Users found:", $usercount);

    foreach ($channel->clientList() as $ts_Client) {
        // $var = get_object_vars($ts_Client);
        $username = $ts_Client["client_nickname"];
        $id = (string)($ts_Client["client_unique_identifier"]);
        XMPP_ERROR_trace("User", "$username $id");

        // we have 2 system users which we do not want to list
        // they are both called "mc_bot..."
        if (strpos($username, 'mc_bot') === false) {
            $users[$id] = array(
                'username' => strtolower($username),
                'object' => $ts_Client,
            );
        }
    }

    return $users;
}

/**
 * Utility command that connects to teamspeak and returns a hopefully connected
 * Teamspeak object in the teamspeak config array.
 *
 * @global type $UMC_TEAMSPEAK
 * @param boolean $error_reply shall we return an in-game error if connection fails?
 */
function umc_ts_connect($error_reply = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_TEAMSPEAK;
    // the server query object, including the password for it, is located outside the code.
    $query_string = file_get_contents($UMC_TEAMSPEAK['server_query_string_path']);

    // only reconnect if we did not do so before
    if (!$UMC_TEAMSPEAK['server']) {
        // include the teamspeak php frameworks
        require_once($UMC_TEAMSPEAK['ts_php_path']);
        $ts_connection = TeamSpeak3::factory($query_string);
        if ($ts_connection) {
            $UMC_TEAMSPEAK['server'] = $ts_connection;
            return true;
        } else {
            XMPP_ERROR_trigger('Could not connect to Teamspeak Server! Is it running?');
            if ($error_reply) {
                umc_error("Sorry, the teamspeak server is down, please send a /ticket!");
            }
            return false;
        }
    }
    return true;
}
