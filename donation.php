<?php
// UUID DONE

function umc_donationform() {
    global $UMC_SETTING, $UMC_USER;
    $out = umc_donation_stats();

    if (!$UMC_USER) {
        $out = "Please <a href=\"{$UMC_SETTING['path']['url']}/wp-admin/profile.php\">login</a> to buy donator status!"
            . "<a href=\"{$UMC_SETTING['path']['url']}/wp-admin/profile.php\"><img src=\"https://www.paypalobjects.com/en_GB/HK/i/btn/btn_paynowCC_LG.gif\" alt=\"Donate\"></a>";
        return $out;
    }
    $out .= "<p style=\"text-align:center;\"><a href=\"{$UMC_SETTING['path']['url']}/help-2/donations/\"><img src=\"https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif\"></a>";
    return $out;
}

/**
 * returns an array of all people who are currently ++ status and how long they have left (in months / 30 days)
 *
 * @param type $uuid
 * @return type array
 */
function umc_users_donators($uuid = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $uuid_str = '';
    if ($uuid) {
        $uuid_str = "AND uuid='$uuid' ";
    }
    $sql = 'SELECT sum(`amount`), `uuid`, sum(amount - (DATEDIFF(NOW(), `date`) / 30)) as leftover '
        . 'FROM minecraft_srvr.donations '
        . 'WHERE amount - (DATEDIFF(NOW(), `date`) / 30) > 0 ' . $uuid_str
        . 'GROUP BY uuid '
        . 'ORDER BY `leftover` DESC';
    $result = umc_mysql_fetch_all($sql);
    if ($uuid && count($result) == 0) {
        return false;
    } else if ($uuid) {
        return $result[0]['leftover'];
    }
    $R = array();
    foreach ($result as $D) {
        $R[$D['uuid']] = $D['leftover'];
    }
    return $R;
}


function umc_donation_chart() {
    global $UMC_SETTING, $UMC_USER;

    if (!$UMC_USER) {
        $out = "Please <a href=\"{$UMC_SETTING['path']['url']}/wp-admin/profile.php\">login</a> to buy donator status!"
        . "<a href=\"{$UMC_SETTING['path']['url']}/wp-admin/profile.php\"><img src=\"https://www.paypalobjects.com/en_GB/HK/i/btn/btn_paynowCC_LG.gif\"></a>";
        return $out;
    } else {
        $uuid = $UMC_USER['uuid'];
        $username = $UMC_USER['username'];
    }

    $show_users = $UMC_SETTING['donation_users'];

    $out = '';

    $sql_count = "SELECT count(UUID) AS count FROM minecraft_srvr.donations;";
    $rst_count = umc_mysql_query($sql_count);
    $row_count = umc_mysql_fetch_array($rst_count);
    umc_mysql_free_result($rst_count);
    $donator_count = $row_count['count'];

    $sql_sum = "SELECT sum(amount) as sum from minecraft_srvr.donations;";
    $rst_sum = umc_mysql_query($sql_sum);
    $row_sum = umc_mysql_fetch_array($rst_sum, MYSQL_ASSOC);
    umc_mysql_free_result($rst_sum);
    $donation_sum = $row_sum['sum'];
    $donation_avg = round($donation_sum / $donator_count, 2);

    $sum = 0;
    $lastdate = "2010-11";

    $sql_chart = "SELECT SUM(amount) as monthly, year(date) as date_year, month(date) as date_month FROM minecraft_srvr.`donations` GROUP BY YEAR(date), MONTH(date);";
    $rst_chart = mysql_query($sql_chart);

    $ydata = array();
    $legend = array();
    $minval = $maxval = 0;
    while ($row = mysql_fetch_array($rst_chart, MYSQL_ASSOC)) {
        $month = sprintf("%02d", $row['date_month']);
        $date = $row['date_year'] . '-' .  $month;

        $datetime1 = new DateTime("$lastdate-01");
        $datetime2 = new DateTime("$date-01");
        $interval = $datetime1->diff($datetime2);
        $int = $interval->format('%m');
        $int--;
        for ($i = $int; $i > 0; $i--) {
            // echo "$i $int - ";
            $e_date = date("Y-m", mktime(0, 0, 0, $row['date_month'] - $i, 01, $row['date_year']));
            // $e_month = $row['date_month'] - $i;
            // $e_date = $row['date_year'] . '-' . $e_month;
            $sum = $sum - 135;
            $ydata[] = $sum;
            //echo $e_date . ": " . $sum . "<br>";
            $legend[] = $e_date . "-01";
            $maxval = max($sum, $maxval);
            $minval = min($sum, $minval);

        }
        $sum = $sum + $row['monthly'] - 135;
        //echo $date . ": " . $sum . "<br>";
        $ydata[] = $sum;
        $legend[] = $date . "-01";
        $lastdate = $date;
        $maxval = max($sum, $maxval);
        $minval = min($sum, $minval);
    }
    $outstanding = $sum * -1;

    // var_dump($ydata);

    require_once($UMC_SETTING['path']['html'] . '/admin/flash/open-flash-chart.php');
    $g = new graph();
    //$g->title("Donation Stats", '{font-size: 15px; color: #000000}');
    $g->bg_colour = '#FFFFFF';

    // Some data (line 1):
    $g->set_data($ydata);
    $legend1 = "Cost vs. donations balance in USD";
    $g->line( 1, '#0000FF', $legend1, 10 );
    // $g->set_y_legend( $legend1, 12, '#0000FF' );
    $g->set_y_max( $maxval );
    $g->set_y_min( $minval );
    $g->y_axis_colour( '#0000FF', '#DFDFDF' );

    $g->x_axis_colour( '#DFDFDF', '#FFFFFF' );
    $g->set_x_legend( 'Uncovery Minecraft Server uptime', 12, '#000000' );

    // The X Axis labels are the time, 00:00, 01:00, 02:00 etc...
    $g->set_x_labels( $legend );
    $g->set_x_label_style( 8, '#000000', 1, 1, '#DFDFDF' ); // lines in the background

    $g->y_label_steps( 10 );

    $g->set_width( '100%' );
    $g->set_height( 300 );

    $g->set_output_type('js');
    $g->set_js_path('/admin/flash/');
    $g->set_swf_path('/admin/flash/');

    $out =  $g->render() . "<div style=\"float:left; width:50%;\"><h2>Donation Status</h2>\nWe have a target to cover our monthly costs with donations." . umc_donation_monthly_target()
        . "If the donation target is exceeded, we will use the excess to fill the gaps of the past months.<br>"
        . "On the right, you can see the long term development of the server income vs. expenses and does not include pre-payments done for the 2-year contract, but only the monthly costs as time goes by as if we were paying every month."
        . '<h2>Donate now!</h2>'
        . "<strong>Donations are processed manually.</strong> You will get an email from PayPal, but you will get a confirmation from the server only after we received an email from PayPal and manually processed it. "
        . "This can take up to 24 hours. Once you received a confirmation email from the server, your userlevel will be updated once you (re-) login to the minecraft server.</div>"
        . "<a href=\"{$UMC_SETTING['path']['url']}/support/\">Contact me</a> if you want to donate a different amount or for someone else."
        . '<form style="display:inline;" action="https://www.paypal.com/cgi-bin/webscr" method="post"><input type="hidden" name="cmd" value="_s-xclick"><input type="hidden" name="hosted_button_id" value="39TSUWZ9XPW5G">'
        . '<p style="text-align:center; width:50%"><input type="hidden" name="on0" value="DonatorPlus Status">'
        . "The average donation amount is <strong>$donation_avg USD</strong><br>"
        . 'Buy DonatorPlus Status as user <strong>'
        . "$username<br>(UUID: $uuid)" . '</strong> for <select style="font-size:12px" name="os0">
            <option value="1 Month">1 month: $2.00 USD</option>
            <option value="6 Months">6 months: $7.00 USD</option>
            <option value="1 Year" selected>1 year: $13.00 USD</option>
            <option value="2 Years">2 years: $25.00 USD</option>
            <option value="4 Years">4 years: $50.00 USD</option>
        </select><input type="hidden" name="on1" value="Your Username"><input type="hidden" name="os1" value="'. $uuid . '"><input type="hidden" name="os2" value="'. $username . '">
        <input type="hidden" name="currency_code" value="USD"><br>
        <input type="image" src="https://www.paypalobjects.com/en_GB/HK/i/btn/btn_paynowCC_LG.gif" border="0" name="submit" alt="PayPal — The safer, easier way to pay online.">
        <img alt="" border="0" src="https://www.paypalobjects.com/en_GB/i/scr/pixel.gif" width="1" height="1">
        </p>
        </form>';

    $sql = "SELECT SUM(amount) as sum, uuid FROM minecraft_srvr.`donations` GROUP BY uuid ORDER by sum DESC LIMIT 25;";
    $rst = umc_mysql_query($sql);
    $out .= '<h1>Top 25 Donators</h1>If you are on this list and would like to be named, please tell me.<table style="width:30%;">';
    $out .= "<tr><td style=\"text-align:right\">". $outstanding . " USD</td><td style=\"text-align:right\">Uncovery</td</tr>";
    while ($row = umc_mysql_fetch_array($rst, MYSQL_ASSOC)) {
        if ((isset($show_users[$row['uuid']])) && ($uuid == $row['uuid'])) {
            $user = $username . " (You)";
        } else if ($uuid == $row['uuid']) {
            $user = "You ($username)";
        } else if (isset($show_users[$row['uuid']])) {
            $user = umc_user2uuid($row['uuid']);
        } else {
            $user = 'anonymous';
        }
        $out .= "<tr><td style=\"text-align:right\">". $row['sum'] . " USD</td><td style=\"text-align:right\">$user</td</tr>";
    }
    umc_mysql_free_result($rst);
    $out .= "</table>";
    return $out;
}


function umc_process_donation() {
    XMPP_ERROR_trigger("Donation Process form was accessed!");
    $pp_hostname = "www.paypal.com"; // Change to www.sandbox.paypal.com to test against sandbox

    // read the post from PayPal system and add 'cmd'
    $req = 'cmd=_notify-synch';
    $s_get  = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
    $tx_token = $s_get['tx'];
    // $auth_token = "GX_sTf5bW3wxRfFEbgofs88nQxvMQ7nsI8m21rzNESnl_79ccFTWj2aPgQ0"; // default
    $auth_token = "vu9gQ8x6WzmwUTS1NwZM6ulo1paOeY64v9532nFKlyQ_PwC4vrKwzFf2CmS"; // mine
    $req .= "&tx=$tx_token&at=$auth_token";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://$pp_hostname/cgi-bin/webscr");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    //set cacert.pem verisign certificate path in curl using 'CURLOPT_CAINFO' field here,
    //if your server does not bundled with default verisign certificates.
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Host: $pp_hostname"));
    $res = curl_exec($ch);
    curl_close($ch);

    if(!$res){
        //HTTP ERROR
    } else {
         // parse the data
        $lines = explode("\n", $res);
        $keyarray = array();
        if (strcmp ($lines[0], "SUCCESS") == 0) {
            for ($i=1; $i<count($lines);$i++){
                list($key,$val) = explode("=", $lines[$i]);
                $keyarray[urldecode($key)] = urldecode($val);
            }
            // the below values are currently not used, so this will be disabled.
            // the line XMPP_ERROR_trigger($res); should be used to identify set values and process the donation accordingly.
            
            // check the payment_status is Completed
            // $payment_status = $keyarray['payment_status'];
            // check that txn_id has not been previously processed
            // $txn_id = $keyarray['txn_id'];
            // check that receiver_email is your Primary PayPal email
            // $receiver_email = $keyarray['receiver_email'];
            // check that payment_amount/payment_currency are correct
            // $payment_amount = $keyarray['payment_amount'];
            // = 'USD';
            XMPP_ERROR_trigger($res);
            // process payment
            $firstname = $keyarray['first_name'];
            $lastname = $keyarray['last_name'];
            $itemname = $keyarray['item_name'];
            $amount = $keyarray['payment_gross'];

            echo ("<p><h3>Thank you for your purchase!</h3></p>");

            echo ("<b>Payment Details</b><br>\n");
            echo ("<li>Name: $firstname $lastname</li>\n");
            echo ("<li>Item: $itemname</li>\n");
            echo ("<li>Amount: $amount</li>\n");
            echo ("");
        } else if (strcmp ($lines[0], "FAIL") == 0) {
            // log for manual investigation
        }
    }

    echo "Your transaction has been completed, and a receipt for your purchase has been emailed to you.<br> "
        . "You may log into your account at <a href='https://www.paypal.com'>www.paypal.com</a> "
        . "to view details of this transaction.<br>";
    // return $out;

}


/**
 * Show donation stats in a short form for the website sidebar
 *
 * @return string
 */
function umc_donation_stats() {
    $monthly_costs = 135;
    $start_date = '2010-11-01';

    // calculate number of months
    $datetime1 = new DateTime($start_date);
    $datetime2 = new DateTime("now");
    $interval = $datetime1->diff($datetime2);
    $years = $interval->format('%y');
    $months = ($years * 12) + $interval->format('%m');

    setlocale(LC_MONETARY, 'en_US');

    $cost = $months * - $monthly_costs;
    $cost_html = money_format('%i', $cost); // add the overlap costs of 2012-08

    $sql = "SELECT SUM(amount) as donated FROM minecraft_srvr.donations;";
    $rst = umc_mysql_query($sql);
    $row = umc_mysql_fetch_array($rst, MYSQL_ASSOC);
    $donated = $row['donated'];
    $donated_html = money_format('%i', $donated);
    $balance = $cost + $donated;
    $balance_format = money_format('%i', $balance);

    $cost_str = "<span style=\"color:red; font-weight:bold;\">$cost_html</span>";
    $donated_str = "<span style=\"color:green; font-weight:bold;\">$donated_html</span>";
    if ($balance < 0) {
        $balance_html = "<span style=\"color:red; font-weight:bold;\">$balance_format</span>";
    } else {
        $balance_html = "<span style=\"color:green; font-weight:bold;\">$balance_format</span>";
    }

    return umc_donation_monthly_target()
        . "Overall costs since $months months:"
        . "<table class=\"donation\" style=\"width:100%\"><tr><td><strong>Cost so far:</strong></td><td class=\"numbers\">$cost_str</td></tr>"
        . "<tr><td><strong>Donated:</strong></td><td class=\"numbers\" style=\"border-bottom:1px solid black;\">$donated_str</td></tr>"
        . "<tr><td><strong>Balance:</strong></td><td class=\"numbers\">$balance_html</td></tr></table>";
}

/**
 * This assumes a monthly targetof 135 USD and shows how much of the monthly target we have reached
 *
 * @return string
 */
function umc_donation_monthly_target() {
    $datetime_now = umc_datetime();
    $this_year_month_first = $datetime_now->format('Y-m') . "-01";

    $founding_month = '2010-11-02';
    $datetime_founding = umc_datetime($founding_month);
    $seconds_since_founding = $datetime_now->diff($datetime_founding);
    $months_since_founding = (($seconds_since_founding->format('%y') * 12) + $seconds_since_founding->format('%m'));

    $monthly_costs = 135;

    $sql = "SELECT SUM(amount) as donated FROM minecraft_srvr.donations WHERE date >= '$this_year_month_first';";
    $rst = umc_mysql_query($sql);
    $row = umc_mysql_fetch_array($rst, MYSQL_ASSOC);
    $donated = $row['donated'];
    $percent = floor($donated / ($monthly_costs / 100));
    $percent_css = $percent;
    // since 0% also shows a green bar, we just color it red.
    if ($percent == 0) {
        $color = 'red';
    } else {
        $percent_css = $percent - 1;
        $color = 'green';
    }
    $thanks = '';
    if ($percent >= 100) {
        $thanks  = " Thanks for contributing!";
    }

    $overall_costs = $months_since_founding * $monthly_costs;
    $overall_sql = "SELECT SUM(amount) as donated FROM minecraft_srvr.donations;";
    $overall_rst = umc_mysql_query($overall_sql);
    $overall_row = umc_mysql_fetch_array($overall_rst, MYSQL_ASSOC);
    $overall_donated = $overall_row['donated'];
    $overall_percent = floor($overall_donated / ($overall_costs / 100));
    $overall_percent_css = $overall_percent;
    // since 0% also shows a green bar, we just color it red.
    if ($overall_percent == 0) {
        $overall_color = 'red';
    } else {
        $overall_percent_css = $overall_percent - 1;
        $overall_color = 'green';
    }

    $out = "\nThis month's donation target:\n";
    $out .= "<div style=\"overflow: hidden; width:100%; background:red; border:1px solid #000000; border-radius: 4px; -moz-border-radius: 4px; -webkit-border-radius: 4px;\">\n"
            . "    <div style=\"width:$percent_css%; background:$color; float:left; padding-left:5px;color:#ffffff; \">\n"
            . "        $percent%$thanks\n"
            . "    </div>\n"
            . "    <div style=\"clear:both;\"></div>\n"
            . "</div>\n"
            . "\nOverall donation target:\n"
            . "<div style=\"overflow: hidden; width:100%; background:red; border:1px solid #000000; border-radius: 4px; -moz-border-radius: 4px; -webkit-border-radius: 4px;\">\n"
            . "    <div style=\"width:$overall_percent_css%; background:$overall_color; float:left; padding-left:5px;color:#ffffff; \">\n"
            . "        $overall_percent%\n"
            . "    </div>\n"
            . "    <div style=\"clear:both;\"></div>\n"
            . "</div>\n";
    return $out;
}

function umc_donation_parser() {
    global $UMC_USER;
    $username = $UMC_USER['username'];

    $amount_arr = array(2 => '1.62', 7 => '6.43', 13 => '12.19', 25 => '23.72', 50 => '47.75');

    if ($username !== 'uncovery') {
        return('You do not have access to this page!');
    }

    if (isset($_POST['submit_donation'])) {
        // Check if transaction exists
        $id = $_POST['transaction_id'];
        $sql = "SELECT * FROM minecraft_srvr.donations WHERE txn_id = '$id';";
        $rst = mysql_query($sql);
        if ((mysql_num_rows($rst)) > 0) {
            $out = 'This transaction ID already exists: ' . $id;
        } else {
            $amount = $_POST['amount'];
            $uuid = $_POST['uuid'];
            $email = $_POST['email'];
            $date = date('Y-m-d');
            $out = "Processing a new transaction (ID: $id)<br>";
            $sql = "INSERT INTO minecraft_srvr.donations SET `txn_id`='$id', `amount`='$amount', `uuid`='$uuid', `email`='$email', `date`='$date';";
            $out .= $sql . "<br>" . 'DONE!';
            mysql_query($sql);
            $out .= "<br>Sending email...";
            // send email to user
            $subject = "[Uncovery Minecraft] Donation activated!";
            $headers = "From: minecraft@uncovery.me" . "\r\n" .
                "Reply-To: minecraft@uncovery.me" . "\r\n" .
                'X-Mailer: PHP/' . phpversion();
            $mailtext = "Dear $username, \r\n\r\nWe have just received and activated your donation. Thanks a lot for contributing to Uncovery Minecraft!\r\n"
                . "After substracting PayPal fees, the donation value is $amount USD.\r\n"
                . "Your userlevel will be updated as soon as you login to the server next time. You can also check it on the frontpage of the website under 'Server Status'.\r\n"
                . "Thanks again, and have fun building your dream!\r\n\r\nSee you around,\r\nUncovery";
            mail($email, $subject, $mailtext, $headers);
            $out .= "DONE!";
        }
        return $out;
    } else if (isset($_POST['donation_text']) || isset($_POST['new_uuid'])) {
        $email = strip_tags($_POST['donation_text']);
        if (isset($_POST['new_uuid'])) {
            $username = $_POST['username'];
            $uuid = umc_user2uuid($username);
            $value = $_POST['amount'];
            $emailaddress = $_POST['email'];
            $id = $_POST['transaction_id'];
        } else {
            $pattern = '/Your Username: (.*)\r/';
            preg_match($pattern, $email, $result);
            $uuid = $result[1];
            $username = umc_user2uuid($uuid);

            // Transaction ID:
            $pattern = '/Transaction ID: ([0-9A-Za-z]*)\r/';
            preg_match($pattern, $email, $result);
            $id = $result[1];

            // You received a payment of $2.00 USD from Thomas Westrich (twestrich15@yahoo.com)
            // You received a payment of 13.00 USD from (lucky_eddy@hotmail.com).
            $pattern = '/You received a payment of .* \((.*)\)/';
            preg_match($pattern, $email, $result);
            $emailaddress = $result[1];

            $pattern = '/Unit price: \$([0-9]*).00 USD/';
            preg_match($pattern, $email, $result);
            $USD = $result[1];
            $value = $amount_arr[$USD];
        }
        $out =  '<form style="text-align:left" method="post">'
            . '<p>Trasaction ID: <input type="text" name="transaction_id" value="' . $id . '"><br>'
            . 'Amount (' . $USD . '): <input type="text" name="amount" value="'. $value . '"><br>'
            . 'Email: <input type="text" name="email"  size="35" value="'. $emailaddress . '"><br>'
            . 'UUID: <input type="text" name="uuid" size="35" value="'. $uuid . '"><br>'
            . 'Username: <input type="text" name="username" value="'. $username . '"> <input type="submit" value="Find UUID" name="new_uuid"><br>'
            . '<input name="submit_donation" type="submit"></form>';

        // You received a payment of $2.00 USD from Thomas Westrich (twestrich15@yahoo.com)

        // Your Username: hardcorepuding

        return $out . '</p><pre style="font-size=6; background-color:#F0F0F0; border: 1px solid #000000;">' . $email . "</pre>";
    } else {
        $out = '<form style="text-align:center" method="post"><input type="submit">'
            . '<p><textarea name="donation_text" rows="20" style="width=100%;"></textarea></p>'
            . '<input type="submit"></form>';
        return $out;
    }
}

function umc_donation_level($user, $debug = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    $U = umc_uuid_getboth($user);
    $uuid = $U['uuid'];
    $username = $U['username'];
    if ($debug) {
        XMPP_ERROR_send_msg("Checking donation upgrade of user $username");
    }


    global $UMC_SETTING;
    $date_now = new DateTime("now");

    $sql = "SELECT amount, date FROM minecraft_srvr.donations WHERE uuid='$uuid';";
    $level = umc_get_uuid_level($uuid);
    if ($debug) {
        XMPP_ERROR_send_msg("Level: $level / SQL: $sql");
    }

    $rst = mysql_query($sql);

    // if there are 0 donations, user should not be changes
    if (mysql_num_rows($rst) == 0 && (strstr($level, "Donator"))) {
        XMPP_ERROR_trigger("User $username ($uuid) never donated but has a donator level ($level)");
    } else if (mysql_num_rows($rst) == 0) {
        if ($debug) {
            XMPP_ERROR_send_msg("$username ($uuid) does not have any donations");
        }
        return;
    }

    $donation_level = 0;

    // go through all donations and find out how much is still active
    while ($row = mysql_fetch_array($rst, MYSQL_ASSOC)) {
        $date_donation = new DateTime($row['date']);
        $interval = $date_donation->diff($date_now);
        $years = $interval->format('%y');
        $months = $interval->format('%m');
        $donation_term = ($years * 12) + $months;
        $donation_leftover = $row['amount'] - $donation_term;
        if ($donation_leftover < 0) {
            $donation_leftover = 0; // do not create negative carryforward
        }
        $donation_level = $donation_level + ceil($donation_leftover);
        if ($debug) {
            XMPP_ERROR_send_msg("Interval: $years, $months = $donation_term months, $donation_leftover are leftover, Donation level: $donation_level");
        }
    }
    // get userlevel and check if demotion / promotion is needed
    if ($debug) {
        XMPP_ERROR_send_msg("user $username ($uuid) has donation level of $donation_level, now is $level");
    }

    // current userlevel
    $ranks_lvl = array_flip($UMC_SETTING['ranks']);
    $cur_lvl = $ranks_lvl[$level];

    // get current promotion level
    if (strpos($level, 'DonatorPlus')) {
        $current = 2;
    } else if (strpos($level, 'Donator')) {
        $current = 1;
    } else {
        $current = 0;
    }

    // get future promotion level
    if (mysql_num_rows($rst) == 0) {
        $future = 0;
    } else if ($donation_level >= 1) {
        $future = 2;
    } else if ($donation_level < 1) {
        $future = 1;
    }
    if ($debug) {
        XMPP_ERROR_send_msg("future = $future, current = $current");
    }

    $change = $future - $current;
    if ($change == 0) {
        if ($debug) {
            XMPP_ERROR_send_msg("User has right level, nothing to do");
        }
        return false; // bail if no change needed
    }

    if ($debug) {
        XMPP_ERROR_send_msg("User will change $change levels");
    }

    // get currect rank index

    if ($debug) {
        XMPP_ERROR_send_msg("Current Rank index = $cur_lvl");
    }
    // calculate base level
    $base_lvl = $cur_lvl - $current;
    if ($debug) {
        XMPP_ERROR_send_msg("User base level = $base_lvl");
    }
    $new_lvl = $base_lvl + $future;
    if ($new_lvl == $cur_lvl) {
        XMPP_ERROR_trigger("Donations upgrade: Nothing to do, CHECK this should have bailed earlier!");
        return false;
    }
    $new_rank = $UMC_SETTING['ranks'][$new_lvl];

    if ($debug) {
        XMPP_ERROR_send_msg("User $username upgraded from $level to $new_rank");
    }
    umc_exec_command("pex user $uuid group set $new_rank");
    umc_log('Donations', 'User Level promotion', "User $username upgraded from $level to $new_rank");

    return ceil($donation_level); // . "($donation_level $current - $future - $change)";
}
