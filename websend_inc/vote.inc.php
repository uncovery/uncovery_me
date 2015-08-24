<?php

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
        'PlayerJoinEvent' => 'umc_vote_get_votable',
    ),
);

$vote_ranks = array(
    'Guest'                 => array('lvl' => 0, 'vote' => 0, 'code' => 's', 'next' => 'Settler'),
    'Settler'               => array('lvl' => 1, 'vote' => 0, 'code' => 'c', 'next' => 'Citizen'),
    'SettlerDonator'        => array('lvl' => 1, 'vote' => 0, 'code' => 'c', 'next' => 'CitizenDonator'),
    'SettlerDonatorPlus'    => array('lvl' => 1, 'vote' => 0, 'code' => 'c', 'next' => 'CitizenDonatorPlus'),
    'Citizen'               => array('lvl' => 2, 'vote' => 0, 'code' => 'a', 'next' => 'Architect'),
    'CitizenDonator'        => array('lvl' => 2, 'vote' => 0, 'code' => 'a', 'next' => 'ArchitectDonator'),
    'CitizenDonatorPlus'    => array('lvl' => 2, 'vote' => 0, 'code' => 'a', 'next' => 'ArchitectDonatorPlus'),
    'Architect'             => array('lvl' => 3, 'vote' => 1, 'code' => 'd', 'next' => 'Designer'),
    'ArchitectDonator'      => array('lvl' => 3, 'vote' => 1, 'code' => 'd', 'next' => 'DesignerDonator'),
    'ArchitectDonatorPlus'  => array('lvl' => 3, 'vote' => 1, 'code' => 'd', 'next' => 'DesignerDonatorPlus'),
    'Designer'              => array('lvl' => 4, 'vote' => 2, 'code' => 'm', 'next' => 'Master'),
    'DesignerDonator'       => array('lvl' => 4, 'vote' => 2, 'code' => 'm', 'next' => 'MasterDonator'),
    'DesignerDonatorPlus'   => array('lvl' => 4, 'vote' => 2, 'code' => 'm', 'next' => 'MasterDonatorPlus'),
    'Master'                => array('lvl' => 5, 'vote' => 4, 'code' => 'e', 'next' => 'Elder'),
    'MasterDonator'         => array('lvl' => 5, 'vote' => 4, 'code' => 'e', 'next' => 'ElderDonator'),
    'MasterDonatorPlus'     => array('lvl' => 5, 'vote' => 4, 'code' => 'e', 'next' => 'ElderDonatorPlus'),
    'Elder'                 => array('lvl' => 6, 'vote' => 8, 'code' => 'o', 'next' => false),
    'ElderDonator'          => array('lvl' => 6, 'vote' => 8, 'code' => 'o', 'next' => false),
    'ElderDonatorPlus'      => array('lvl' => 6, 'vote' => 8, 'code' => 'o', 'next' => false),
    'Owner'                 => array('lvl' => 7, 'vote' => 16, 'code' => 'o', 'next' => false)
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
    } else {
        $uuid = umc_uuid_getone($username, 'uuid');
    }

    if (!$username && !isset($UMC_USER['username'])) {
        XMPP_ERROR_trigger("websend player undidentified");
    }

    $user_lvl = umc_get_userlevel($username);
    $user_lvl_id = $vote_ranks[$user_lvl]['lvl'];
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
        $prop_lvl = umc_get_uuid_level($proposal);
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
    $sql = "SELECT AVG(DATEDIFF(proposals_votes.`date`, proposals.`date`)) AS average, max( DATEDIFF(proposals_votes.`date`, proposals.`date`)) AS maximum
        FROM minecraft_srvr.proposals
        LEFT JOIN minecraft_srvr.proposals_votes ON proposals.pr_id = proposals_votes.pr_id
        WHERE STATUS = 'success'";
    $D = umc_mysql_fetch_all($sql);
    $row = $D[0];
    $max = $row['maximum'];
    $avg = $row['average'];

    // how many proposals per day
    $sql = "SELECT count( `pr_id` ) / DATEDIFF( MAX( `date` ) , MIN( `date` ) ) as counter FROM minecraft_srvr.`proposals` ";
    $D = umc_mysql_fetch_all($sql);
    $row = $D[0];
    $prop_freq = $row['counter'];

    // how many proposals per day
    $sql = "SELECT count( `vote_id` ) / DATEDIFF( MAX( `date` ) , MIN( `date` ) ) as counter FROM minecraft_srvr.`proposals_votes` ";
    $D = umc_mysql_fetch_all($sql);
    $row = $D[0];
    $vote_freq = $row['counter'];

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


function umc_vote_web() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $vote_ranks, $UMC_DOMAIN, $UMC_USER;
    $lvl_percent = array('a' => 0.30, 'd' => 0.40, 'm' => 0.7, 'e' => 1);

    $out = umc_vote_stats();

    // return "<h1>Sorry, due to technical issues, voting is temporarily suspended</h1>";

    if (!$UMC_USER) {
        $out = "Please <a href=\"$UMC_DOMAIN/wp-login.php\">login</a> to vote!";
        return $out;
    } else {
        $out .= "<h2>Proposals & Votes</h2>";
        $username = $UMC_USER['username'];
        $uuid = $UMC_USER['uuid'];
        $user_lvl = $UMC_USER['userlevel'];
    }

    $user_lvl_id = $vote_ranks[$user_lvl]['lvl'];
    if ($user_lvl_id < 3) { // start voting only for designers
        return "Sorry, you need to be Designer or above to vote!";
    }

    // get user numbers for levels
    $lvl_str_arr = array(
        'a' => "'Architect', 'ArchitectDonator', 'ArchitectDonatorPlus'",
        'd' => "'Designer', 'DesignerDonator', 'DesignerDonatorPlus'",
        'm' => "'Master', 'MasterDonator', 'MasterDonatorPlus'",
        'e' => "'Elder', 'ElderDonator', 'ElderDonatorPlus'",
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
    $full_vote = $lvl_amounts['e'] * $vote_ranks['Elder']['vote'];
    foreach ($lvl_amounts as $lvl => $lvl_amount) {
        $lvl_min_req[$lvl] = round($full_vote * $lvl_percent[$lvl]);
    }

    // TODO insert here a cleanup process that deletes old votes of non-promoted users


    $proposed = filter_input(INPUT_POST, 'proposal', FILTER_SANITIZE_STRING);
    if (isset($proposed) && strlen($proposed) > 1) {
        $proposed = umc_check_user($proposed);

        if (!$proposed) {
            $out .= "Sorry $username, but you need to input an existing user to propose!";
        } else {
            $proposed_data = umc_uuid_getboth($proposed, 'username');
            $proposed_username = $proposed_data['username'];
            $proposed_uuid = $proposed_data['uuid'];
            // what user level is it?
            $prop_lvl = umc_get_uuid_level($proposed_uuid);
            $prop_lvl_id = $vote_ranks[$prop_lvl]['lvl'];
            // check if the user was recently promoted
            $sql = "SELECT UNIX_TIMESTAMP(`date`) as mysql_ts FROM minecraft_srvr.proposals  WHERE `uuid` LIKE '$proposed_uuid' ORDER BY `date` DESC;";
            $D = umc_mysql_fetch_all($sql);
            $row = array();
            if (count($D) > 0) {
                $row = $D[0]; // get the first (latest) entry
            } else {
                $row['mysql_ts'] = 0;
            }
            if ((time() - $row['mysql_ts']) < 5270400) {
                $out .= "<strong>Sorry $username, but $proposed_username was last proposed for promotion less than 2 months ago!</strong>";
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
            } else {
                // ok to be promoted
                $ins_proposal_sql = "INSERT INTO `minecraft_srvr`.`proposals` (`pr_id`, `uuid`, `proposer_uuid`, `date`, `status`)
                    VALUES (NULL, '$proposed_uuid', '$uuid', NOW(), 'voting');";
                umc_mysql_query($ins_proposal_sql);
                $pr_id = umc_mysql_insert_id();
                $sql = "INSERT INTO minecraft_srvr.`proposals_votes` (`pr_id`, `voter_uuid`, `date`, `vote`) VALUES ($pr_id, '$uuid', NOW(), 1);";
                umc_mysql_query($sql, true);
                $out .= "Thanks $username, $proposed_username as been submitted for voting, and your vote has been set, too!";

                if ($prop_lvl_id == 5) { // we propose a Master for promotion, inform all elders
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
                    mail('minecraft@uncovery.me', $subject, $content, $headers);
                    foreach ($D as $row) {
                        mail($row['user_email'], '[Uncovery Minecraft] '.  $subject, $content, $headers);
                        umc_mail_send_backend($row['UUID'], 'ab3bc877-4434-45a9-93bd-bab6df41eabf', $content, $subject, 'send'); // send from uncovery's UUID
                    }
                }


            }
        }
    }

    // propose new person
    if ($user_lvl_id > 3) {
        $out .= "<form action=\"\" method=\"post\">\n"
            . "<span>Propose a person to be upgraded: <input type=\"text\" name=\"proposal\"> "
            . "<input type=\"submit\" name=\"proposebutton\" value=\"Propose user!\">"
            . "</span></form>";
    } else {
        $out .= "(Since your level is too low, you cannot propose users yet.)";
    }

    // close old proposals
    $upd_sql = "UPDATE minecraft_srvr.proposals SET `status`='failed' WHERE status = 'voting' AND date < NOW() - INTERVAL 2 month";
    umc_mysql_query($upd_sql, true);

    // list proposed people
    $sql = "SELECT UUID.username, status, pr_id, date, proposals.uuid FROM minecraft_srvr.proposals
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
        $prop_lvl =  umc_get_userlevel($row['username']);
        $prop_status = $row['status'];
        $prop_lvl_id = $vote_ranks[$prop_lvl]['lvl'];
        $proposed = $row['uuid'];
        $proposed_name = $row['username'];

        // check if user is allowed to vote for this person
        if ($user_lvl_id <= $prop_lvl_id) {
            continue;
        }
        $proposals++;
        $sel_support = $sel_veto = '';
        $sel_none = " selected=\"selected\"";
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
                $sel_support = $sel_veto = $sel_none = '';
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
                } else if ($new_vote != 0) {
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
                WHERE pr_id=$pr_id AND vote <> 0 ORDER BY date DESC;";
        $R = umc_mysql_fetch_all($sql);
        $email_close = "$UMC_DOMAIN/vote-for-users/\n";
        foreach ($R as $row_calc) {
            $vote_date = $row_calc['date'];
            $voter_lvl = umc_get_uuid_level($row_calc['voter_uuid']);
            $voter_weight = $vote_ranks[$voter_lvl]['vote'];
            $voter_score = $voter_weight * $row_calc['vote'];
            $total_score = $total_score + $voter_score;
            if ($username == 'uncovery') {
                $out .= "<tr><td>Vote:</td><td>{$row_calc['username']}</td><td>$voter_lvl</td><td>{$row_calc['vote']}</td><td>{$row_calc['date']}</td><td>$voter_score</td></tr>\n";
                // prepare email to send if this will be closed
            }
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
            mail('minecraft@uncovery.me', "Voting closed for " . $row['username'], $email_text, $headers);
            $prop_status = 'closed';
        } else if (($prop_status == 'closed') && ($total_score < abs($lvl_min_req[$lvl_code]))) {
            //$sql = "UPDATE minecraft_srvr.`proposals` SET `status` = 'voting' WHERE `proposals`.`pr_id`=$pr_id LIMIT 1 ";
            //mysql_query($sql);
        }

        // show total score
        if ($username == 'uncovery') {
            $header = "<td><strong>$total_score</strong> (of $min_req)</td>";
        }

        // load your own score
        $sql = "SELECT * FROM minecraft_srvr.proposals_votes WHERE voter_uuid = '$uuid' AND pr_id=$pr_id";
        $D = umc_mysql_fetch_all($sql);
        $vote_date = "n/a";
        if (count($D) > 0) {
            $row_votes = $D[0];
            $vote_id = $row_votes['vote_id'];
            $your_vote = $row_votes['vote'];
            $vote_date = $row_votes['date'];
            // check if an alternative vote has been cast right now
            if ($your_vote == 1) {
                $sel_support = " selected=\"selected\"";
            } else if ($your_vote == -1) {
                $sel_veto = " selected=\"selected\"";
            }
        }

        // show voting buttons
        $vote_close = '';
        $min_req = $lvl_min_req[$lvl_code];
        if ($prop_status == 'closed') {
            $vote = "Voting closed!<input type=\"hidden\" name=\"PR_$pr_id\" value=\"done\">";
            if ($username == 'uncovery') {
                $vote_date = "<select name=\"CL_$pr_id\"><option value=\"closed\">Voting closed</option><option value=\"success\">Upgrade</option><option value=\"failed\">Fail</option></select>";
            }
        } else {
            $vote = "<select name=\"PR_$pr_id\"><option value=\"0\" $sel_none>Abstain</option><option value=\"1\"$sel_support>Supported</option><option value=\"-1\"$sel_veto>Vetoed</option></select>";
        }
        $vote_lvl = umc_get_userlevel($row['username']);
        $out .= "<tr><td><strong><a href=\"$UMC_DOMAIN/users-2/?u={$row['username']}\">{$row['username']}</a></strong></td><td>{$row['date']}</td><td>$prop_lvl</td><td>$vote</td><td>$vote_date</td>$header</tr>\n";
    }

    if ($proposals == 0) {
        $out .= "<tr><td colspan=6>There are no proposals that you can vote for at the moment!</td><tr>\n";
    }

    $out .= "</table>\n<input type=\"submit\" name=\"votebutton\" value=\"Submit votes!\">\n</form><br>\n";

    // process successful votes, create post to blog
    if (count($upgraded_users) >0) {
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
    return $out;
}