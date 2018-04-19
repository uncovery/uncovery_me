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
 * This manages user level upgrades based on user votes. The amount of votes needed
 * to upgrade depends on the active Elder (=max) level users on the server.
 * This makes sure that the max. amount of Elder users is proportional to the overall
 * amount of users on the server.
 */
global $UMC_SETTING, $WS_INIT, $vote_ranks, $UMC_DOMAIN;

$WS_INIT['voting'] = array(  // the name of the plugin
    'default' => array(
        'help' => array(
            'title' => 'Vote to upgrade users',  // give it a friendly title
            'short' => 'Users will be upgraded if enough people vote',  // a short description
            'long' => "Higher ranks need more votes. See $UMC_DOMAIN/vote-for-users/ for details", // a long add-on to the short  description
            ),
    ),
    'disabled' => false,
    'events' => array(
        // remind users to vote on login
        'PlayerJoinEvent' => 'umc_vote_get_votable',
        // display upgrade history on the user profile (users.php)
        'user_directory' => 'umc_vote_userprofile',
    ),
);

$vote_ranks = array(
    'guest'                 => array('lvl' => 0, 'vote' => 0, 'code' => 's', 'gap' => 0, 'next' => 'Settler'),
    'settler'               => array('lvl' => 1, 'vote' => 0, 'code' => 'c', 'gap' => 0, 'next' => 'Citizen'),
    'settlerdonator'        => array('lvl' => 1, 'vote' => 0, 'code' => 'c', 'gap' => 0, 'next' => 'CitizenDonator'),
    'citizen'               => array('lvl' => 2, 'vote' => 0, 'code' => 'a', 'gap' => 0, 'next' => 'Architect'),
    'citizendonator'        => array('lvl' => 2, 'vote' => 0, 'code' => 'a', 'gap' => 0, 'next' => 'ArchitectDonator'),
    'architect'             => array('lvl' => 3, 'vote' => 1, 'code' => 'd', 'gap' => 2, 'next' => 'Designer'),
    'architectdonator'      => array('lvl' => 3, 'vote' => 1, 'code' => 'd', 'gap' => 2, 'next' => 'DesignerDonator'),
    'designer'              => array('lvl' => 4, 'vote' => 3, 'code' => 'm', 'gap' => 4, 'next' => 'Master'),
    'designerdonator'       => array('lvl' => 4, 'vote' => 3, 'code' => 'm', 'gap' => 4, 'next' => 'MasterDonator'),
    'master'                => array('lvl' => 5, 'vote' => 6, 'code' => 'e', 'gap' => 12, 'next' => 'Elder'),
    'masterdonator'         => array('lvl' => 5, 'vote' => 6, 'code' => 'e', 'gap' => 12, 'next' => 'ElderDonator'),
    'elder'                 => array('lvl' => 6, 'vote' => 10, 'code' => 'o', 'gap' => false, 'next' => false),
    'elderdonator'          => array('lvl' => 6, 'vote' => 10, 'code' => 'o', 'gap' => false, 'next' => false),
    'owner'                 => array('lvl' => 7, 'vote' => 20, 'code' => 'o', 'gap' => false, 'next' => false)
);

// getting rid of donators as distinct ranks
$vote_ranks_raw = array(
    'guest'                 => array('lvl' => 0, 'vote' => 0, 'code' => 's', 'gap' => 0, 'next' => 'Settler'),
    'settler'               => array('lvl' => 1, 'vote' => 0, 'code' => 'c', 'gap' => 0, 'next' => 'Citizen'),
    'citizen'               => array('lvl' => 2, 'vote' => 0, 'code' => 'a', 'gap' => 0, 'next' => 'Architect'),
    'architect'             => array('lvl' => 3, 'vote' => 1, 'code' => 'd', 'gap' => 2, 'next' => 'Designer'),
    'designer'              => array('lvl' => 4, 'vote' => 3, 'code' => 'm', 'gap' => 4, 'next' => 'Master'),
    'master'                => array('lvl' => 5, 'vote' => 6, 'code' => 'e', 'gap' => 12, 'next' => 'Elder'),
    'elder'                 => array('lvl' => 6, 'vote' => 10, 'code' => 'o', 'gap' => false, 'next' => false),
    'owner'                 => array('lvl' => 7, 'vote' => 20, 'code' => 'o', 'gap' => false, 'next' => false)
);

/**
 *  This function displays the proposals the user has not voted on yet. It's displayed on user login
**/
function umc_vote_get_votable($username = false, $web = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_DOMAIN, $vote_ranks, $UMC_USER;

    $out = '';
    if (!$username) {
        $username = $UMC_USER['username'];
        $uuid = $UMC_USER['uuid'];
        $userlevel = strtolower($UMC_USER['userlevel']);
    } else {
        $uuid = umc_uuid_getone($username, 'uuid');
        $userlevel = strtolower(umc_userlevel_get($uuid));
    }

    // only active users can vote
    $is_active = umc_users_is_active($uuid);
    if (!$is_active) {
        return;
    }

    if (!$username && !isset($UMC_USER['username'])) {
        XMPP_ERROR_trigger("websend player undidentified");
    }

    $user_lvl_id = $vote_ranks[$userlevel]['lvl'];
    if ($user_lvl_id < 3) { // start voting only for designers
        return;
    }
    $sql = "SELECT proposals.pr_id, proposals.uuid, proposals.date, 60 - DATEDIFF(NOW(), proposals.date) as remainder, UUID.username as username FROM minecraft_srvr.proposals
        LEFT JOIN minecraft_srvr.proposals_votes ON proposals.pr_id=proposals_votes.pr_id AND voter_uuid='$uuid'
        LEFT JOIN minecraft_srvr.UUID ON proposals.uuid=UUID.UUID
        WHERE proposals_votes.pr_id IS NULL AND status='voting' ORDER BY proposals.`date` ASC";
    $D = umc_mysql_fetch_all($sql);

    $no_votes = array();
    // echo $sql;
    foreach ($D as $row) {
        $proposal = $row['uuid'];
        $proposal_username = $row['username'];
        $prop_lvl = strtolower(umc_userlevel_get($proposal));
        $prop_lvl_id = $vote_ranks[$prop_lvl]['lvl'];
        if ($prop_lvl_id < $user_lvl_id) {
            $days_left = $row['remainder'];
            $no_votes[$proposal_username] = $days_left;
        }
    }
    if (count($no_votes) > 0) {
        if ($web) {
            $out .= "<strong><a href=\"$UMC_DOMAIN/vote-for-users/\">Please vote</a>:</strong> (" . count($no_votes) . ") ";
            foreach ($no_votes as $proposee => $days) {
                $out .= "$proposee, ";
            }
            $out = rtrim($out, ", ");
            return $out;
        } else {
            umc_header('Your missing votes: (days remaining)', true);
            foreach ($no_votes as $proposee => $days) {
                $out .= "{red}$proposee {grey}($days){white}, ";
            }
            umc_echo($out, true);
            umc_echo("{gold}Please vote ASAP! Only YOU can determine the future of the server! $UMC_DOMAIN/vote-for-users/", true);
            umc_footer(true);

        }
    } else {
        return false;
    }
}

/**
 * Voting statistics for the website
 *
 * @return string
 */
function umc_vote_stats() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $pr_stats = array('success' => 0, 'voting' => 0, 'closed' => 0);
    $sql = "SELECT count(pr_id) as counter, `status` FROM minecraft_srvr.proposals GROUP BY `status`;";
    $P = umc_mysql_fetch_all($sql);
    foreach ($P as $row) {
        $status = $row['status'];
        $pr_stats[$status] = $row['counter'];
    }

    $vote_stats = array();
    $v_sql = "SELECT count(vote_id) as counter, vote FROM minecraft_srvr.proposals_votes GROUP BY vote;";
    $V = umc_mysql_fetch_all($v_sql);
    foreach ($V as $row) {
        $status = $row['vote'];
        $vote_stats[$status] = $row['counter'];
    }

    // find time for successful votes
    $time_sql = "SELECT AVG(DATEDIFF(proposals_votes.`date`, proposals.`date`)) AS average, max( DATEDIFF(proposals_votes.`date`, proposals.`date`)) AS maximum
        FROM minecraft_srvr.proposals
        LEFT JOIN minecraft_srvr.proposals_votes ON proposals.pr_id = proposals_votes.pr_id
        WHERE STATUS = 'success'";
    $T = umc_mysql_fetch_all($time_sql);
    $time_row = $T[0];
    $max = $time_row['maximum'];
    $avg = $time_row['average'];

    // how many proposals per day
    $day_sql = "SELECT count( `pr_id` ) / DATEDIFF( MAX( `date` ) , MIN( `date` ) ) as counter FROM minecraft_srvr.`proposals` ";
    $D = umc_mysql_fetch_all($day_sql);
    $day_row = $D[0];
    $prop_freq = $day_row['counter'];

    // how many proposals votes per day
    $prop_sql = "SELECT count( `vote_id` ) / DATEDIFF( MAX( `date` ) , MIN( `date` ) ) as counter FROM minecraft_srvr.`proposals_votes` ";
    $X = umc_mysql_fetch_all($prop_sql);
    $prop_row = $X[0];
    $vote_freq = $prop_row['counter'];

    $good_votes = 0;
    $bad_votes = 0;
    If (isset($vote_stats['1'])) {
        $good_votes = $vote_stats['1'];
    }
    if (isset($pr_stats['1'])) {
        $bad_votes = $pr_stats['-1'];
    }

    $proposals = $pr_stats['success'] + $pr_stats['voting'] + $pr_stats['closed'];

    // display stats
    $out = "<h2>Vote Stats</h2><ul>"
        . "<li>Overall proposals: $proposals</li>"
        . "<li>Proposals per day: " . number_format($prop_freq, 1) . "</li>"
        . "<li>Overall votes: " . ($good_votes + $bad_votes) . "</li>"
        . "<li>Votes per day: " . number_format($vote_freq, 1) . "</li>"
        . "<li>Proposal fail ratio: " . number_format( ($pr_stats['failed'] / $pr_stats['success']) * 100, 2 ) . "%</li>"
        . "<li>Vote down ratio: " . number_format( ($vote_stats['-1'] / $vote_stats['1']) * 100, 2 ) . "%</li>"
        . "<li>Max days for proposals to succeed: " .$max . " days</li>"
        . "<li>Avg days for proposals to succeed: " .number_format($avg, 1) . " days</li></ul>";
    return $out;
}

/**
 * website display of voting process
 *
 * @global array $vote_ranks
 * @global type $UMC_DOMAIN
 * @global type $UMC_USER
 * @return string
 */
function umc_vote_web() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $vote_ranks, $UMC_DOMAIN, $UMC_USER;
    $lvl_percent = array('a' => 0.30, 'd' => 0.40, 'm' => 0.7, 'e' => 1);

    // display the stats first
    $out = umc_vote_stats();

    // return "<h1>Sorry, due to technical issues, voting is temporarily suspended</h1>";

    if (!$UMC_USER) {
        $out = "Please <a href=\"$UMC_DOMAIN/wp-login.php\">login</a> to vote!";
        return $out;
    } else {
        $out .= "<h2>Proposals & Votes</h2>";
        $username = $UMC_USER['username'];
        $uuid = $UMC_USER['uuid'];
        $user_lvl = strtolower($UMC_USER['userlevel']);
    }

    // we allow new proposals only in odd months
    $proposals_enabled = false;
    $proposals_disabled_reason = "Proposals are currently not possible. We allow proposals only every in odd months (Jan, March etc). Please stop by next month.";
    $current_month = date('j');
    if ($current_month % 2 == 0) {
        $proposals_enabled = true;
    }


    // only active users can vote
    $is_active = umc_users_is_active($uuid);
    if (!$is_active) {
        return "Sorry, but you do not have a lot, so you cannot vote.";
    }

    // only designers ++ can vote
    $user_lvl_id = $vote_ranks[$user_lvl]['lvl'];
    if ($user_lvl_id < 3) { // start voting only for designers
        return "Sorry, you need to be Designer or above to vote!";
    }

    // get user numbers for levels, make SQL code
    $lvl_str_arr = array(
        'a' => "'Architect', 'ArchitectDonator'",
        'd' => "'Designer', 'DesignerDonator'",
        'm' => "'Master', 'MasterDonator'",
        'e' => "'Elder', 'ElderDonator'",
    );

    $lvl_amounts = array('a' => 0, 'd' => 0, 'm' => 0, 'e' => 0);
    $lvl_min_req = array('a' => 0, 'd' => 0, 'm' => 0, 'e' => 0);

    foreach ($lvl_str_arr as $lvl_code => $lvl_str) {
        // This takes all lots where the owners are in one of the user 4 levels that can vote
        $sql = "SELECT user_id, UUID.UUID as uuid, username FROM `minecraft_worldguard`.`region_players`
            LEFT JOIN minecraft_worldguard.user ON region_players.user_id=user.id
            LEFT JOIN minecraft_srvr.UUID ON minecraft_worldguard.user.uuid=minecraft_srvr.UUID.UUID
            LEFT JOIN minecraft_srvr.permissions_inheritance ON minecraft_srvr.UUID.UUID=minecraft_srvr.permissions_inheritance.child
            WHERE parent IN ($lvl_str) AND type=1 AND owner=1 GROUP BY user_id;";
        $C = umc_mysql_fetch_all($sql);
        // count all the people in the userlevel to know how many votes are needed
        $lvl_amounts[$lvl_code] = count($C);
    }

    // calc needed votes
    $full_vote = $lvl_amounts['e'] * $vote_ranks['elder']['vote'];
    foreach ($lvl_amounts as $lvl => $lvl_amount) {
        $lvl_min_req[$lvl] = round($full_vote * $lvl_percent[$lvl]);
    }

    $proposed = filter_input(INPUT_POST, 'proposal', FILTER_SANITIZE_STRING);
    $reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING);
    $wordcount = str_word_count($reason);

    // process a new proposal
    if (isset($proposed) && strlen($proposed) > 1) {
        $proposed = umc_check_user($proposed);

        if (!$proposed) { // check if we have a valid username
            $out .= "Sorry $username, but you need to input an existing user to propose!";
        } else {
            $proposed_data = umc_uuid_getboth($proposed, 'username');
            $proposed_username = $proposed_data['username'];
            $proposed_uuid = $proposed_data['uuid'];
            // what user level is it?
            $prop_lvl = strtolower(umc_userlevel_get($proposed_uuid));
            $prop_lvl_id = $vote_ranks[$prop_lvl]['lvl'];
            $next_level = $vote_ranks[$prop_lvl]['next'];
            // check if the user was recently promoted
            $sql = "SELECT round((UNIX_TIMESTAMP() - UNIX_TIMESTAMP(`date`)) / (60 * 60 * 24 * 30.5)) as month_gap
                FROM minecraft_srvr.proposals
                WHERE `uuid` LIKE '$proposed_uuid'
                ORDER BY `date` DESC LIMIT 1;";
            $D = umc_mysql_fetch_all($sql);
            $row = array();
            if (count($D) > 0) {
                $month_gap = $D[0]['month_gap']; // get the first (latest) entry
            } else {
                $month_gap = 0;
            }

            // let's check if there are elder proposals already
            $elder_check_sql = "SELECT count(pr_id) as counter FROM minecraft_srvr.proposals
                LEFT JOIN minecraft_srvr.permissions_inheritance ON permissions_inheritance.child=proposals.uuid
                WHERE proposals.status LIKE \"voting\" AND parent LIKE \"Master%\"";
            $C = umc_mysql_fetch_all($elder_check_sql);
            $elder_count = $C[0]['counter'];
            if ($prop_lvl_id == 5 && $elder_count > 0) { // this is a master proposed for Elder
                $out .= "<strong>Sorry $username, but there can be only one user proposed for Elder at a time! Please wait until the current Elder vote is over and then re-submit your proposal.</strong>";
            } else if ($month_gap < $vote_ranks[$prop_lvl]['gap']) {
                $needed_gap = $vote_ranks[$prop_lvl]['gap'];
                $out .= "<strong>Sorry $username, but $proposed_username can only be promoted to the next level after $needed_gap month. The last promotion was $month_gap ago!</strong>";
            } else if ($user_lvl_id < 6 && ($user_lvl_id < ($prop_lvl_id + 1))) {
                $out .= "<strong>Sorry $username, but you need to be at a higher level to propose $proposed_username for a higher rank!</strong>";
            } else if ($prop_lvl_id > 5) {
                $out .= "<strong>Sorry $username, but $proposed_username has reached max level already!</strong>";
            } else if (umc_user_countlots($proposed) < 1) { // is this an active user?
                $out .= "<strong>Sorry $username, but you can only propose users who have a lot!</strong>";
            } else if ($prop_lvl_id < 2){
                $out .= "<strong>Sorry $username, but you can only propose users who are at least Citizen level!</strong>";
            } else if ($username == $proposed) {
                $out .= "<strong>Sorry $username, but you cannot propose yourself!</strong>";
            } else if ($wordcount < 100) {
                $out .= "<strong>Sorry, $username, but the reason for the upgrade has to be at least 100 words long!";
            } else if (!$proposals_enabled) {
                $out .= "<strong>$proposals_disabled_reason</strong>";
            } else {
                // ok to be promoted
                $reason_sql = umc_mysql_real_escape_string($reason);
                $ins_proposal_sql = "INSERT INTO `minecraft_srvr`.`proposals` (`pr_id`, `uuid`, `proposer_uuid`, `date`, `status`, `reason`, `target_level`)
                    VALUES (NULL, '$proposed_uuid', '$uuid', NOW(), 'voting', $reason_sql, $next_level);";
                umc_mysql_query($ins_proposal_sql);
                $pr_id = umc_mysql_insert_id();
                $sql = "INSERT INTO minecraft_srvr.`proposals_votes` (`pr_id`, `voter_uuid`, `date`, `vote`) VALUES ($pr_id, '$uuid', NOW(), 1);";
                umc_mysql_query($sql, true);
                $out .= "Thanks $username, $proposed_username as been submitted for voting, and your vote has been set, too!";

                if ($prop_lvl_id == 5) { // we propose a Master for promotion, inform all elders
                    umc_vote_elder_notify($proposed);
                }
            }
        }
    }



    // propose new person
    if ($user_lvl_id > 3) {
        if (!$proposals_enabled) {
            $out .= "<h2>$proposals_disabled_reason</h2>";
        } else {
            // the javascript for the wordcount is in /data/js/global.js
            $out .= "
            <form action=\"\" method=\"post\">
                <div>
                    <span>Propose a person to be upgraded: <input type=\"text\" name=\"proposal\"> </span>
                    <div>Reason for promotion (100 words minimum):
                        <textarea id=\"reason\" onkeyup=\"WordCount(this, 'charNum')\" name=\"reason\" style=\"width:80%;height:100px;\"></textarea>
                    </div>
                    <span><input type=\"submit\" name=\"proposebutton\" value=\"Propose user!\"></span> Word count:<span id=\"charNum\"></span>
                </div>
            </form>";
        }
    } else {
        $out .= "(Since your level is too low, you cannot propose users yet.)";
    }

    // close old proposals
    $upd_sql = "UPDATE minecraft_srvr.proposals SET `status`='failed' WHERE status = 'voting' AND date < NOW() - INTERVAL 1 month";
    umc_mysql_query($upd_sql, true);

    // list proposed people
    $sql = "SELECT UUID.username, status, pr_id, date, proposals.uuid, reason FROM minecraft_srvr.proposals
        LEFT JOIN minecraft_srvr.UUID ON proposals.uuid=UUID.UUID
        WHERE status IN ('voting','closed') ORDER BY `date` ASC;";
    $D = umc_mysql_fetch_all($sql);
    $header = '';
    if ($username == 'uncovery') {
        $header = '<th>Score</th>';
    }
    $out .= "<br><form action=\"\" method=\"post\">\n<input type=\"hidden\" name=\"uuid\" value=\"$uuid\">\n<input type=\"hidden\" name=\"voting\" value=\"true\">\n<table>\n"
        . "<tr><th>Proposal</th><th>Date</th><th>Current Level</th><th>Your Vote</th><th>Vote date</th>$header</tr>\n";
    $proposals = 0;
    $upgraded_users = array();

    foreach ($D as $row) {
        $prop_lvl =  strtolower(umc_userlevel_get($row['uuid']));
        $prop_status = $row['status'];
        $prop_lvl_id = $vote_ranks[$prop_lvl]['lvl'];
        $proposed = $row['uuid'];
        $proposed_name = $row['username'];

        // check if user is allowed to vote for this person
        if ($user_lvl_id <= $prop_lvl_id) {
            continue;
        }
        $proposals++;
        $sel_support = $sel_veto = $sel_abstain = '';
        // pre-select that the user has not voted yet
        $sel_none = ' selected="selected"';
        $vote_date = "n/a";
        $pr_id = $row['pr_id'];

        // check if vote has been cast right now
        if (isset($_POST['voting']) && ($_POST['uuid'] == $uuid)) {
            if (($prop_status == 'closed') && ($username == 'uncovery') && (isset($_POST['CL_' . $pr_id])) && ($_POST['CL_' . $pr_id] != 'closed')) {
                $new_vote = filter_input(INPUT_POST, 'CL_' . $pr_id, FILTER_SANITIZE_STRING);
                // var_dump($_POST);
                $sql = "UPDATE minecraft_srvr.`proposals` SET `status` = '$new_vote' WHERE `proposals`.`pr_id`=$pr_id LIMIT 1;";
                umc_mysql_query($sql);
                // echo $sql;
                if ($new_vote == 'success') {
                    XMPP_ERROR_trigger("$proposed got promoted!");
                    $cmd = "pex promote $proposed";
                    umc_exec_command($cmd, 'asConsole', false);
                    umc_exec_command($cmd, 'asConsole', false);
                    umc_exec_command($cmd, 'asConsole', false);
                    $upgraded_users[$proposed_name] = $prop_lvl;
                    umc_log('voting', "promotion", "$proposed_name ($proposed) was promoted from $prop_lvl through votes");
                }
                continue;
            } else if ($prop_status != 'closed'){
                $new_vote = filter_input(INPUT_POST, 'PR_' . $pr_id, FILTER_SANITIZE_STRING);
                // find existing votes
                $sql = "SELECT * FROM minecraft_srvr.`proposals_votes` WHERE pr_id=$pr_id and voter_uuid='$uuid';";
                $C = umc_mysql_fetch_all($sql);
                if (count($C) > 0) {
                    $row_check = $C[0];
                    $vote_id = $row_check['vote_id'];
                    if ($new_vote == 0) {
                        $sql = "DELETE FROM minecraft_srvr.`proposals_votes` WHERE pr_id=$pr_id and voter_uuid='$uuid';";
                        umc_mysql_query($sql, true);
                    } else if ($row_check['vote'] != $new_vote) {
                        $sql = "REPLACE INTO minecraft_srvr.`proposals_votes` (`vote_id`, `pr_id`, `voter_uuid`, `date`, `vote`)
			    VALUES ($vote_id, $pr_id, '$uuid', NOW(), $new_vote);";
                        umc_mysql_query($sql, true);
                    }
                } else if ($new_vote != 'x') {
                    $sql = "INSERT INTO minecraft_srvr.`proposals_votes` (`pr_id`, `voter_uuid`, `date`, `vote`)
                        VALUES ($pr_id, '$uuid', NOW(), $new_vote);";
                    umc_mysql_query($sql, true);
                }
            } else if ($prop_status == 'closed') {
                // a user tried to vote on a closed vote... what to do?
            }
        }

        // load existing votes
        $total_score = 0;
        $sql = "SELECT date, voter_uuid, UUID.username, vote, date FROM minecraft_srvr.proposals_votes
                LEFT JOIN minecraft_srvr.UUID ON voter_uuid=UUID.UUID
                WHERE pr_id=$pr_id ORDER BY date DESC;";
        $R = umc_mysql_fetch_all($sql);
        $email_close = "$UMC_DOMAIN/vote-for-users/\n";
        foreach ($R as $row_calc) {
            $vote_date = $row_calc['date'];
            $voter_lvl = strtolower(umc_userlevel_get($row_calc['voter_uuid']));
            $voter_weight = $vote_ranks[$voter_lvl]['vote'];
            $voter_score = $voter_weight * $row_calc['vote'];
            $total_score = $total_score + $voter_score;
            if ($username == 'uncovery') {
                // we show other users votes only to the admin
                $out .= "<tr><td>Vote:</td><td>{$row_calc['username']}</td><td>$voter_lvl</td><td>{$row_calc['vote']}</td><td>{$row_calc['date']}</td><td>$voter_score</td></tr>\n";
            }
            // this is only for the email sent to the admin after a vote is closed
            $email_close .= "Vote: {$row_calc['username']} ($voter_lvl) on {$row_calc['date']} gave points: $voter_score\n";
        }

        // close votes that have enough points
        $lvl_code = $vote_ranks[$prop_lvl]['code'];
        $min_req = $lvl_min_req[$lvl_code];
        if (abs($total_score) >= $min_req && $prop_status == 'voting') {
            // close vote
            $sql = "UPDATE minecraft_srvr.`proposals` SET `status` = 'closed' WHERE `proposals`.`pr_id`=$pr_id LIMIT 1 ";
            umc_mysql_query($sql, true);
            // send email with status report
            $email_text = $email_close . "Total Score: $total_score\n\rRequired: " . abs($lvl_min_req[$lvl_code]);
            $headers = 'From:minecraft@uncovery.me' . "\r\nReply-To:minecraft@uncovery.me\r\n" . 'X-Mailer: PHP/' . phpversion();
            mail('minecraft@uncovery.me', "Voting closed for " . $row['username'], $email_text, $headers, "-fminecraft@uncovery.me");
            $prop_status = 'closed';
        } else if (($prop_status == 'closed') && ($total_score < abs($lvl_min_req[$lvl_code]))) {
            //$sql = "UPDATE minecraft_srvr.`proposals` SET `status` = 'voting' WHERE `proposals`.`pr_id`=$pr_id LIMIT 1 ";
            //mysql_query($sql);
        }

        $header2 = '';
        // show total score only to admin
        if ($username == 'uncovery') {
            $header = "<td><strong>$total_score</strong> (of $min_req)</td>";
            $header2 = "<td></td>";
        }

        // load your own score
        $score_sql = "SELECT * FROM minecraft_srvr.proposals_votes WHERE voter_uuid = '$uuid' AND pr_id=$pr_id";
        $D = umc_mysql_fetch_all($score_sql);
        if (count($D) > 0) {
            $row_votes = $D[0];
            $vote_id = $row_votes['vote_id'];
            $your_vote = $row_votes['vote'];
            $vote_date = $row_votes['date'];
            $sel_none = "";
            // check if an alternative vote has been cast right now
            if ($your_vote == 1) {
                $sel_support = " selected=\"selected\"";
            } else if ($your_vote == -1) {
                $sel_veto = " selected=\"selected\"";
            } else if ($your_vote == 0) {
                $sel_abstain = " selected=\"selected\"";
            } else {
                XMPP_ERROR_trigger("Vote detected but not valid");
            }
        }

        // show voting buttons
        if ($prop_status == 'closed') {
            $vote = "Voting closed!<input type=\"hidden\" name=\"PR_$pr_id\" value=\"done\">";
            if ($username == 'uncovery') {
                $vote_date = "<select name=\"CL_$pr_id\">
                    <option value=\"closed\">Voting closed</option>
                    <option value=\"success\">Upgrade</option>
                    <option value=\"failed\">Fail</option>
                </select>";
            }
        } else {
            $vote = "<select name=\"PR_$pr_id\">
                <option value=\"0\"$sel_abstain>Abstain</option>
                <option value=\"1\"$sel_support>Supported</option>
                <option value=\"-1\"$sel_veto>Vetoed</option>
                <option value=\"x\"$sel_none>No vote yet</option>
            </select>";
        }
        $out .= "<tr>
                <td><strong><a href=\"$UMC_DOMAIN/users-2/?u={$row['username']}\">{$row['username']}</a></strong></td>
                <td>{$row['date']}</td>
                <td>$prop_lvl</td>
                <td>$vote</td>
                <td>$vote_date</td>$header
            </tr>
            <tr>
                <td><strong>Reason:</strong></td><td colspan=4>{$row['reason']}</td>$header2
            </tr>";
    }

    if ($proposals == 0) {
        $out .= "<tr><td colspan=6>There are no proposals that you can vote for at the moment!</td><tr>\n";
    }

    $out .= "</table>\n<input type=\"submit\" name=\"votebutton\" value=\"Submit votes!\">\n</form><br>\n";

    // process successful votes, create post to blog
    if (count($upgraded_users) >0) {
        umc_vote_post_news($upgraded_users);
    }
    return $out;
}

/**
 * Post news to the blog when a user has been promoted.
 */
function umc_vote_post_news($upgraded_users) {
    global $vote_ranks;
    $text = "Please see the latest upgrades from the voting system:<ul>";
    $userlist_arr = array();
    foreach ($upgraded_users as $upgraded_user => $userlvl) {
        $nextrank = $vote_ranks[$userlvl]['next'];
        $text .= "<li>$upgraded_user (from $userlvl to $nextrank)</li>";
        $userlist_arr[] = $upgraded_user;
    }
    $userlist = implode(", ", $userlist_arr);
    $text .= "</ul>Congratz and thanks to all voters!";
    $post = array(
        'comment_status' => 'open', // 'closed' means no comments.
        'ping_status' => 'closed', // 'closed' means pingbacks or trackbacks turned off
        'post_author' => 1, //The user ID number of the author.
        'post_content' => $text, //The full text of the post.
        'post_status' => 'publish', //Set the status of the new post.
        'post_title' => "Today's upgrades: $userlist", //The title of your post.
        'post_type' => 'post' //You may want to insert a regular post, page, link, a menu item or some custom post type
    );
    wp_insert_post($post);
}


/**
 * notify elders when someone is proposed for elder vote
 *
 * @global type $UMC_DOMAIN
 * @param type $proposed
 */
function umc_vote_elder_notify($proposed) {
    global $UMC_DOMAIN;
    $sql = "SELECT user_email, UUID, username FROM minecraft_srvr.`UUID`
        LEFT JOIN minecraft.wp_usermeta ON UUID.UUID=meta_value
        LEFT JOIN minecraft.wp_users ON user_id=ID
        WHERE `userlevel` LIKE 'Elder%' AND lot_count > 0";
    $D = umc_mysql_fetch_all($sql);
    $subject = "$proposed proposed for Elder, please vote!";
    $content = "Dear Elder, \r\n\r\nthe user $proposed has been proposed to be promoted to Elder. Please go to\r\n\r\n$UMC_DOMAIN/vote-for-users/\r\n\r\n"
        . "and vote on this proposal. Please either SUPPORT or VETO the proposal.\r\n"
        . "Please note that the vote will be closed as 'failed' unless all Elders cast a vote within the coming 2 months.\r\n"
        . "Thanks a lot for supporting Uncovery Minecraft!\r\n\r\nBest regards,\r\nUncovery";
    $headers = 'From:minecraft@uncovery.me' . "\r\nReply-To:minecraft@uncovery.me\r\n" . 'X-Mailer: PHP/' . phpversion();
    mail('minecraft@uncovery.me', $subject, $content, $headers, "-fminecraft@uncovery.me");
    foreach ($D as $row) {
        mail($row['user_email'], '[Uncovery Minecraft] '.  $subject, $content, $headers, "-fminecraft@uncovery.me");
        umc_mail_send_backend($row['UUID'], 'ab3bc877-4434-45a9-93bd-bab6df41eabf', $content, $subject, 'send'); // send from uncovery's UUID
    }
}

/**
 * This adds information about the user's promotion history to their user profile
 * via the event user_directory
 *
 * @param type $data_array
 */
function umc_vote_userprofile($data_array) {
    $uuid = $data_array['uuid'];
    $first_join = $data_array['first_join'];

    $sql = "SELECT `date`, reason, target_level
        FROM minecraft_srvr.proposals
        WHERE proposals.uuid LIKE '$uuid' AND status='success'
        ORDER BY `date` DESC";
    $D = umc_mysql_fetch_all($sql);

    $out = '';
    foreach ($D as $d) {
        $date = substr($d['date'], 0, 10);
        $level = ucwords($d['target_level']);
        $out .= "$date: became $level<br>";
        if (strlen($d['reason'] > 1)){
            $out .= "Reason:{$d['reason']}<br>";
        }
    }
    // get citizen promotion
    $citizen_sql = "SELECT `date` FROM minecraft_log.universal_log WHERE `text` LIKE '%$uuid was promoted from Settler%'";
    $C = umc_mysql_fetch_all($citizen_sql);
    if (count($C) > 0) {
        $citizen_date = $C[0]['date'];
        $out .= "$citizen_date: became Citizen<br>";
    } else {
        $out .= "<smaller>Citizen promotion date unknown, we only keep this record since January 2016</smaller><br>";
    }

    $first_date = substr($first_join, 0, 10);
    $out .= "$first_date: First joined<br>";

    $out .= "Note: This list only includes promotions that have been voted on (since September 2013).";

    $data['Promotions'] = $out;
    return $data;
}
