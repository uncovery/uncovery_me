<?php
global $WS_INIT;

$WS_INIT['donation'] = array(  // the name of the plugin
    'disabled' => false,
    'events' => array(
        'user_directory' => 'umc_donation_usersdirectory',
        'PlayerJoinEvent' => 'umc_donation_update_current_player',
        'any_websend' => 'umc_donation_currentuser_status',
        'any_wordpress' => 'umc_donation_currentuser_status',
    ),
    'default' => array(
        'help' => array(
            'title' => 'Donation',  // give it a friendly title
            'short' => 'Managing of Donations',  // a short description
            'long' => "Check your current level etc", // a long add-on to the short  description
        ),
    ),
);

global $donation_vars;
$donation_vars = array(
    'use_sandbox' => false, // do we use the sandbox or the operaion variables?
    'sandbox' => array(
        'paypal_url' => "https://www.sandbox.paypal.com/cgi-bin/webscr",
        'business_email' => 'paypal_merchant2@aimba.com',
        'button_id' => '2E77BHVBDK9AA'  // not used?
    ),
    'operation' => array(
        'paypal_url' => "https://www.paypal.com/cgi-bin/webscr",
        'business_email' => 'minecraft@uncovery.me',
        'button_id' => '39TSUWZ9XPW5G', // not used?
    ),
    'monthly_cost' => 135,
);


// sandbox instructions: https://developer.paypal.com/docs/classic/paypal-payments-standard/ht_test-pps-buttons/


/**
 * This sets checks and sets the current user's Donator status.
 *
 * @global type $UMC_USER
 */
function umc_donation_currentuser_status() {
    global $UMC_USER;
    $uuid = $UMC_USER['uuid'];
    umc_donation_playerstatus($uuid);
}



/**
 * checks is a user is donator or not. If the user is the current user, assigns the variable to $UMC_USER
 *
 * @global type $UMC_USER
 * @param type $uuid
 * @return boolean
 */
function umc_donation_playerstatus($uuid) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    if ($uuid == $UMC_USER['uuid'] && isset($UMC_USER['donator'])) {
        return $UMC_USER['donator'];
    }

    $uuid_sql = umc_mysql_real_escape_string($uuid);
    $sql = "SELECT parent AS userlevel, value AS username, name AS uuid FROM minecraft_srvr.permissions
        LEFT JOIN minecraft_srvr.`permissions_inheritance` ON name=child
        WHERE permissions.permission='name' AND `name`=$uuid_sql AND parent LIKE ('Donator') ";
    $D = umc_mysql_fetch_all($sql);
    if (count($D) == 0) {
        $donator = true;
    } else {
        $donator = false;
    }
    if ($uuid == $UMC_USER['uuid']) {
        $UMC_USER['donator'] = $donator;
    }
    return $donator;
}

function umc_donation_update_current_player() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $uuid = $UMC_USER['uuid'];
    umc_donation_update_user($uuid);
}


function umc_donation_usersdirectory($data){
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $uuid = $data['uuid'];
    // TODO move this to a plugin event
    $donator_level = umc_donation_remains($uuid);
    if (!$donator_level) {
        $donator_str = "Not a donator";
    } else if ($donator_level > 12) {
        $donator_str = 'More than 1 year';
    } else if ($donator_level) {
        $donator_level_rounded = round($donator_level, 1);
        $donator_str = "$donator_level_rounded Months";
    }
    $O['User'] = "<p><strong>Donations status:</strong> $donator_str</p>\n";
    return $O;
}

function umc_donation_remains($uuid) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    // we assume that they are not a donator

    // today's date
    $date_now = new DateTime("now");
    // lets get all the donations
    $sql_uuid = umc_mysql_real_escape_string($uuid);
    $sql = "SELECT amount, date FROM minecraft_srvr.donations WHERE uuid=$sql_uuid;";
    $D = umc_mysql_fetch_all($sql);
    // no donations, not donator
    if (count($D) == 0) {
        return false;
    }

    // go through all donations and find out how much is still active
    // the problem here is that if a user donated 2 USD twice 3 months ago
    // he is still a donator. we have to be aware about overlapping donations
    // that extend further into the future due to the overlap
    $donation_level = 0;
    foreach ($D as $row) {
        $date_donation = new DateTime($row['date']);
        $interval = $date_donation->diff($date_now);
        $years = $interval->format('%y'); // years since the donation
        $months = $interval->format('%m');
        $donation_term = ($years * 12) + $months;
        $donation_leftover = $row['amount'] - $donation_term;
        if ($donation_leftover < 0) {
            $donation_leftover = 0; // do not create negative carryforward
        }
        $donation_level += $donation_leftover;
    }
    if ($donation_level > 0) {
        return $donation_level;
    } else {
        return false;
    }
}


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

function umc_donation_chart() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_SETTING, $UMC_USER, $donation_vars;

    if (!$UMC_USER) {
        $out = "Please <a href=\"{$UMC_SETTING['path']['url']}/wp-admin/profile.php\">login</a> to buy donator status!"
        . "<a href=\"{$UMC_SETTING['path']['url']}/wp-admin/profile.php\"><img src=\"https://www.paypalobjects.com/en_GB/HK/i/btn/btn_paynowCC_LG.gif\"></a>";
        return $out;
    } else {
        $uuid = $UMC_USER['uuid'];
        $username = $UMC_USER['username'];
    }

    $out = '';

    if ($donation_vars['use_sandbox'] == true && $username != 'uncovery') {
        return "This page is under construction, please check back soon!";
    } else if ($donation_vars['use_sandbox'] == true) {
        $out .= "<h1> SANDBOX ACTIVE</h1>";
        $settings = $donation_vars['sandbox'];
    } else {
        $settings = $donation_vars['operation'];
    }

    $chart_data = umc_donation_java_chart();
    $outstanding = $chart_data['outstanding'];
    $chart = $chart_data['chart'];
    $donation_avg = umc_donation_calc_average();
    $table = umc_donation_top_table($outstanding);
    $active_users = umc_get_active_members();

    $out .= "<div style=\"float:right; width:440px; margin-left: 30px;\">\n$chart\n$table</div>\n"
        . "<div style=\"width:auto; overflow:hidden; \">Uncovery Minecraft is run privately, without advertising or mandatory fees. We also want to stay away from \"pay-to-win\"
        and therefore also want to only provide non-essential benefits to donators. Those benefits can be seen on the bottom of
        the \"<a href=\"https://uncovery.me/user-levels/\">Userlevels &amp; Commands</a>\" page. If you ask me what I am doing with the donation money,
        you have to understand that the server is already paid by me in advance on a 2 year contract since that's much cheaper than paying month-by-month.
        So the donations that I receive go into my PayPal account that I use to pay other things through PayPal. I sometimes donate to other
        plugin authors if I want them to speed up some features for example. The target is however that if we ever have a surplus, that
        this will be used to either improve or advertise the server. The monthly server costs are {$donation_vars['monthly_cost']} USD. Donations are always welcome
        and encourage me to spend more time on the server and continue to fix, upgrade and enhance it, run contests and provide an adequate support to the users.
        <h2>Donation Status</h2>\nWe have a target to cover our monthly costs with donations.<br>\n" . umc_donation_monthly_target()
        . "If the monthly donation target is exceeded, we will use the excess to fill the gaps of the past months.<br>\n"
        . "On the right, you can see the long term development of the server income vs. expenses and does not include pre-payments done for the 2-year contract, but only the monthly costs as time goes by as if we were paying every month.\n</div>"
        . '<h2 style="clear:both;">Donate now!</h2>'
        . "\n<strong>Donations are processed manually.</strong> You will get an email from PayPal, but you will get a confirmation from the server only after we received an email from PayPal and manually processed it. \n"
        . "This can take up to 24 hours. Once you received a confirmation email from the server, your userlevel will be updated once you (re-) login to the minecraft server.\n"
        . '<br><br><form style="display:inline;" action="' . $settings['paypal_url'] . '" method="post" target="_top">'
        . '<input type="hidden" name="cmd" value="_s-xclick">'
        . '<input type="hidden" name="hosted_button_id" value="' . $settings['button_id'] . '">'
        . '<p style="text-align:center;"><input type="hidden" name="on0" value="Donator Status">'
        . "The average donation amount is <strong>$donation_avg USD</strong><br>
        Buy Donator Status as user <strong>$username<br>
            (UUID: $uuid)" . '</strong><br> Duration <select style="font-size:12px" name="os0">
            <option value="1 Month">1 Month $2.00 USD</option>
            <option value="6 Months">6 Months $7.00 USD</option>
            <option value="1 Year">1 Year $13.00 USD</option>
            <option value="2 Years">2 Years $25.00 USD</option>
            <option value="4 Years">4 Years $50.00 USD</option>
        </select>
        <input type="hidden" name="on1" value="Your Username"><input type="hidden" name="os1" value="'. $uuid . '"><br>
        <input type="hidden" name="on2" value="for Recipient(s)">Recipient: ' . umc_web_dropdown($active_users, 'os2', $uuid)
        . '<input type="hidden" name="currency_code" value="USD"><br>
            <strong>Important:</strong> If you want the amount split between several users, please do not make several donations.<br>
            Make the complete donation for yourself and then send me a message with the details.<br>
        <input type="image" src="https://www.paypalobjects.com/en_GB/HK/i/btn/btn_paynowCC_LG.gif" name="submit" alt="PayPal — The safer, easier way to pay online.">
        <img alt="" border="0" src="https://www.paypalobjects.com/en_GB/i/scr/pixel.gif" width="1" height="1">
        </p>
        </form>';
    return $out;
}


/**
 * Make a chart of all donations in the past
 *
 * @return type
 */
function umc_donation_java_chart() {
    global $donation_vars;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    $sql_chart = "SELECT SUM(amount) as monthly, SUM(amount) - {$donation_vars['monthly_cost']} as monthly_bline, DATE_FORMAT(`date`, '%Y-%m') as 'month'
        FROM minecraft_srvr.`donations` GROUP BY DATE_FORMAT(`date`, '%Y-%m') ORDER BY `date`";
    $D = umc_mysql_fetch_all($sql_chart);

    // first, we take all the data we have into an array
    $ydata = array();
    foreach ($D as $row) {
        $ydata[$row['month']] = $row['monthly_bline'];
    }
    // now we have a donation amount for each existing month, we need to add the
    // months without a donation

    $start_date = '2011-01-01';
    // we need to start with this date here instead of the date from the first
    // donation. Also, we need to iterate every month in case there was no
    // donation for one month
    $final_data = array();
    $sum = 0;
    $first_date = new DateTime($start_date);
    $today_date = new DateTime();

    // since there are months without data, we need to iterate all months since the start
    while ($first_date < $today_date) {
        //we format properly to get the month
        $check_date = $first_date->format('Y-m');
        if (isset($ydata[$check_date])) {
            $this_month = $ydata[$check_date];
        } else {
            $this_month = - $donation_vars['monthly_cost']; // no donation, so only minus
        }
        $sum += $this_month;
        $final_data[$check_date]['value'] = $sum;
        // add one month, go to next
        $first_date->add(new DateInterval('P1M'));
        XMPP_ERROR_trace($check_date, $sum);
    }
    ksort($ydata);

    $outstanding = $sum * -1;
    $out = umc_web_javachart($final_data, 'Month', 'none', false, 'amchart', false, 300);
    return array('chart' => $out, 'outstanding' => $outstanding);
}

function umc_donation_calc_average() {
    $sql_count = "SELECT count(UUID) AS count FROM minecraft_srvr.donations;";
    $rst_count = umc_mysql_query($sql_count);
    $row_count = umc_mysql_fetch_array($rst_count);
    umc_mysql_free_result($rst_count);
    $donator_count = $row_count['count'];

    $sql_sum = "SELECT sum(amount) as sum from minecraft_srvr.donations;";
    $row_sum = umc_mysql_fetch_all($sql_sum);
    $donation_sum = $row_sum[0]['sum'];
    $donation_avg = round($donation_sum / $donator_count, 2);
    return $donation_avg;
}

function umc_donation_top_table($outstanding) {
    global $UMC_SETTING, $UMC_USER;
    $show_users = $UMC_SETTING['donation_users'];
    $username = $UMC_USER['username'];
    $uuid = $UMC_USER['uuid'];

    $sql = "SELECT SUM(amount) as sum, uuid FROM minecraft_srvr.`donations` GROUP BY uuid ORDER by sum DESC LIMIT 25;";
    $D = umc_mysql_fetch_all($sql);
    $out = "<h2>Top 25 Donators</h2>If you are on this list and would like to be named, please tell me.\n<table>";
    $out .= "\n    <tr><td style=\"text-align:right\">". money_format('%.2n', $outstanding) . " USD</td><td style=\"text-align:right\">Uncovery</td></tr>\n";
    foreach ($D as $row) {
        if ((isset($show_users[$row['uuid']])) && ($uuid == $row['uuid'])) {
            $user = $username . " (You)";
        } else if ($uuid == $row['uuid']) {
            $user = "You ($username)";
        } else if (isset($show_users[$row['uuid']])) {
            $user = umc_user2uuid($row['uuid']);
        } else {
            $user = 'anonymous';
        }
        $out .= "    <tr><td style=\"text-align:right\">". $row['sum'] . " USD</td><td style=\"text-align:right\">$user</td></tr>\n";
    }
    $out .= "</table>\n";
    return $out;
}

/**
 * Show donation stats in a short form for the website sidebar
 *
 * @return string
 */
function umc_donation_stats() {
    global $donation_vars;

    $start_date = '2010-11-01';

    // calculate number of months
    $datetime1 = new DateTime($start_date);
    $datetime2 = new DateTime("now");
    $interval = $datetime1->diff($datetime2);
    $years = $interval->format('%y');
    $months = ($years * 12) + $interval->format('%m');

    setlocale(LC_MONETARY, 'en_US');

    $cost = $months * - $donation_vars['monthly_cost'];
    $cost_html = money_format('%i', $cost); // add the overlap costs of 2012-08

    $sql = "SELECT SUM(amount) as donated FROM minecraft_srvr.donations;";
    $D = umc_mysql_fetch_all($sql);
    $donated = $D[0]['donated'];
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
    global $donation_vars;
    $datetime_now = umc_datetime();
    $this_year_month_first = $datetime_now->format('Y-m') . "-01";

    $founding_month = '2010-11-02';
    $datetime_founding = umc_datetime($founding_month);
    $seconds_since_founding = $datetime_now->diff($datetime_founding);
    $months_since_founding = (($seconds_since_founding->format('%y') * 12) + $seconds_since_founding->format('%m'));

    $sql = "SELECT SUM(amount) as donated FROM minecraft_srvr.donations WHERE date >= '$this_year_month_first';";
    $X = umc_mysql_fetch_all($sql);
    $donated = $X[0]['donated'];
    $percent = floor($donated / ($donation_vars['monthly_cost'] / 100));
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

    $overall_costs = $months_since_founding * $donation_vars['monthly_cost'];
    $overall_sql = "SELECT SUM(amount) as donated FROM minecraft_srvr.donations;";
    $D = umc_mysql_fetch_all($overall_sql);
    $overall_donated = $D[0]['donated'];
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


/**
 * Parse the donation result and automatically record it in the database
 * Uses Paypal IDN https://developer.paypal.com/docs/classic/products/instant-payment-notification/
 *
 * @global type $UMC_SETTING
 * @return type
 */


function umc_process_donation() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $donation_vars;

    // code from https://github.com/paypal/ipn-code-samples/tree/master/php

    // Currently, the IPN result AND the URL after payment point to the same, this, page
    // they are being called in 2 different processes however. The payment confirmation process might be running in the background
    // and will process successfully. However, the user will get back to this page without
    // any POST data to further process the payment. So we show this message and the user will have to wait for an email
    // to confirm that the payment was successfully processed in the other URL call.

    if (!count($_POST)) {
        // throw new Exception("Missing POST Data");
        $text = "Thank you very much for donating! It is highly appreciated and will surely help to keep this server running longer.
            I am always working on giving extra privileges to donators, so keep watching out!
            We are currently processing your donation. You will get an email regarding the status";
        return $text;
    }

    require('/home/includes/paypal/PaypalIPN.php');

    $ipn = new PaypalIPN();
    XMPP_ERROR_trace('IPN DATA', $ipn);
    // Use the sandbox endpoint during testing.

    if ($donation_vars['use_sandbox'] == true) {
        XMPP_ERROR_trace('Sandbox is being used!');
        $ipn->useSandbox();
        $setting = $donation_vars['sandbox'];
    } else {
        $setting = $donation_vars['operation'];
    }

    XMPP_ERROR_trace('Verifying IPN...');
    $verified = $ipn->verifyIPN();

    if (!$verified) {
        XMPP_ERROR_trigger("Paypal Payment Not Verified!");
        return "We could not automatically process your payment. We will do that manually, but we promise to get back to you ASAP. "
            . "You donation length won't be affected by this. Once the donation is activated, you will receive an email!";
    }


    XMPP_ERROR_trigger("Donation Process form was accessed!");

    $s_post = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);

    // process payment
    $firstname = $s_post['first_name'];
    $lastname = $s_post['last_name'];
    $itemname = $s_post['item_name'];
    $amount = $s_post['payment_gross'];
    $text = "<p><h3>Thank you for your purchase!</h3></p>
        <b>Payment Details</b><br>
        <li>Name: $firstname $lastname</li>
        <li>Item: $itemname</li>
        <li>Amount: $amount</li>
        Your transaction has been completed, and a receipt for your purchase has been emailed to you.<br>
        You may log into your account at <a href='https://www.paypal.com'>www.paypal.com</a>
        to view details of this transaction.<br>";

    // list of verifiable entries:
    // OK check whether the payment_status is Completed
    // TODO check that txn_id has not been previously processed
    // OK check that receiver_email is your PayPal email
    // TODO check that payment_amount/payment_currency are correct
    // assign posted variables to local variables

    $verify_entries = array(
        'payment_status' => 'Completed',
        'business' => $setting['business_email'],
        'option_selection2' => false, // ÜUID b85cd837-2d00-47c5-999d-ef90ae36d868
        'payer_email' => false, // player email, URL encoded SamBecker0523%40gmail.com
        'payment_gross' => false, // '25.00'
        'payment_fee' => false, //'1.40'
        'txn_id' => false, // 4TT776949B495984P
        // 'btn_id' => '52930807',
        'option_selection3' => false,
    );

    $is_ok = true;
    $sql_vals = array();
    foreach ($verify_entries as $entry => $value) {
        if ($value && $s_post[$entry] != $value) {
            $is_ok = false;
            XMPP_ERROR_trace("WRONG ENTRY: $entry", "Should be '$value', is '{$s_post[$entry]}'");
        } else { // if the array value = false, just store the value in SQL
            $sql_vals[$entry] = umc_mysql_real_escape_string($s_post[$entry]);
        }
    }
    
    $uuid = umc_uuid_getone($s_post['option_selection2']);
    $username = umc_uuid_getone($uuid, 'username');
    
    // add the entry to the database
    $headers = "From: minecraft@uncovery.me" . "\r\n" .
        "Reply-To: minecraft@uncovery.me" . "\r\n" .
        'X-Mailer: PHP/' . phpversion();
    $final_value = umc_mysql_real_escape_string($s_post['payment_gross'] - $s_post['payment_fee']);
    $recipient_text = '';
    if ($uuid != $s_post['option_selection3']) {
        $rec_username = umc_uuid_getone($s_post['option_selection3'], 'username');
        $recipient_text = "The donation to be in benefit of $rec_username, as you asked.";
    }
    if ($is_ok) {
        $date = umc_mysql_real_escape_string(date('Y-m-d'));
        $sql = "INSERT INTO minecraft_srvr.donations (`amount`, `uuid`, `email`, `date`, `txn_id`)
            VALUES ($final_value, {$sql_vals['option_selection3']}, {$sql_vals['payer_email']}, $date, {$sql_vals['txn_id']})";
        umc_mysql_query($sql, true);
        XMPP_ERROR_trigger("Donation SQL executed!");
        $subject = "[Uncovery Minecraft] Donation activated!";
        $mailtext = "Dear $username, \r\n\r\nWe have just received and activated your donation. Thanks a lot for contributing to Uncovery Minecraft!\r\n"
            . "After substracting PayPal fees, the donation value is $final_value USD. $recipient_text\r\n"
            . "Your userlevel will be updated as soon as you login to the server next time. You can also check it on the frontpage of the website.\r\n"
            . "Thanks again, and have fun building your dream!\r\n\r\nSee you around,\r\nUncovery";
        $text .= "Thank you very much for donating! It is highly appreciated and will surely help to keep this server running longer. "
        . "I am always working on giving extra privileges to donators, so keep watching out!";
        // send email to admin
        mail('minecraft@uncovery.me', 'Donation Success', "$username ($uuid) made a donation of $final_value for $rec_username!", $headers, "-fminecraft@uncovery.me");
    } else {
        $subject = "[Uncovery Minecraft] Donation pending!";
        $text .= "There was an issue processing your payment automatically. Please wait until we have manually processed and it you will get an email from us.";
        XMPP_ERROR_trigger("Not all values correct for donation!");
        $mailtext = "Dear $username, \r\n\r\nWe have just received your donation. Thanks a lot for contributing to Uncovery Minecraft!\r\n"
            . "After substracting PayPal fees, the donation value is $final_value USD. $recipient_text\r\n"
            . "Your userlevel will be updated as soon as we processed your donation. You can also check it on the frontpage of the website.\r\n"
            . "Thanks again, and have fun building your dream!\r\n\r\nSee you around,\r\nUncovery";
    }
    mail($s_post['payer_email'], $subject, $mailtext, $headers, "-fminecraft@uncovery.me");
    return $text;
}

/**
 * update the donator status user depending on their past donations.
 *
 * @param type $uuid
 * @return boolean
 */
function umc_donation_update_user($uuid) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $is_donator = umc_donation_remains($uuid);

    $userlevel = umc_userlevel_get($uuid);
    if ($userlevel == 'Owner') {
        return false;
    }

    $base_level_arr = umc_userlevel_get_base($userlevel);
    $base_level = $base_level_arr['level_name'];
    if ($is_donator) {
        if (strpos($userlevel, 'DonatorPlus')) { // all good
            $new_level = $base_level . "Donator";
            umc_userlevel_assign_level($uuid, $new_level);
        } else if (strpos($userlevel, 'Donator')) { // all good
            return;
        } else {
            $new_level = $userlevel . "Donator";
            umc_userlevel_assign_level($uuid, $new_level);
        }
    } else { // not donator
        if ($userlevel != $base_level) { // downgrade
            umc_userlevel_assign_level($uuid, $base_level);
        }
    }
}

/**
 * return an array of all current donators
 *
 * @return type
 */
function umc_donation_list_donators() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $sql = "SELECT child as uuid FROM minecraft_srvr.permissions_inheritance WHERE parent LIKE '%Donator';";
    $D = umc_mysql_fetch_all($sql);
    $out_arr = array();
    foreach($D as $row) {
        $out_arr[] = $row['uuid'];
    }
    return $out_arr;
}

/**
 *
 CREATE TABLE IF NOT EXISTS `donations` (
  `id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `username` varchar(60) NOT NULL,
  `uuid` varchar(36) NOT NULL,
  `email` varchar(256) NOT NULL,
  `date` date NOT NULL,
  `txn_id` mediumtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `donations`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `donations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
 */