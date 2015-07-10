<?php

global $UMC_SETTING, $WS_INIT;

$WS_INIT['ticket'] = array(
    'disabled' => true,
    'events' => array(
        // 'PlayerJoinEvent' => 'umc_ticket_list_playerjoin',
    ),
    'default' => array(
        'help' => array(
            'title' => 'Help Tickets',
            'short' => 'A Support system.',
            'long' => 'This will send an email with your issue to an admin. Integrated with the website.',
            ),
    ),
    'new' => array(
        'help' => array(
            'short' => 'Create a new help ticket',
            'args' => '<help question>',
            'long' => 'An admin will be alerted about the ticket. Please check your email for replies!',
            ),
        'function' => 'umc_ticket_new',
    ),
    'list' => array(
        'help' => array(
            'short' => 'List all open help tickets',
            'args' => '',
            'long' => '',
            ),
        'function' => 'umc_ticket_list',
    ),
    'close' => array(
        'help' => array(
            'short' => 'Close an open ticket',
            'args' => '<ticket id>',
            'long' => 'If you do not need help anymore, you can close a ticket.',
            ),
        'function' => 'umc_ticket_close',
    ),
    'read' => array(
        'help' => array(
            'short' => 'Read a ticket conversation',
            'args' => '<ticket id>',
            'long' => 'This shows the complete conversation and ticket status.',
            ),
        'function' => 'umc_ticket_read',
    ),
    'reply' => array(
        'help' => array(
            'short' => 'Reply to a ticket',
            'args' => '<ticket id> <reply text>',
            'long' => 'The reply will be visible on the website also!',
            ),
        'function' => 'umc_ticket_reply',
    ),
);

function umc_ticket_options() {
    $sql = "SELECT * FROM minecraft.wp_options WHERE option_name='wpscSupportTicketsAdminOptions';";
    $D = umc_mysql_fetch_all($sql);
    if (count($D) > 0) {
        return unserialize($D[0]['option_value']);
    } else {
        return false;
    }
}

function umc_ticket_close() {
    global $WSEND;
    $player = $WSEND['player'];
    $player_id = umc_user_id($player);
    $args = $WSEND['args'];

    if (!isset($args[1]) && !isset($args[2])) {
        umc_show_help($args);
        die();
    }

    $key = intval($args[2]);
    if (!is_numeric($key)) {
        umc_error("Invalid ticket ID");
    }

    $sql = "SELECT * FROM minecraft.wp_wpscst_tickets WHERE user_id=$player_id AND resolution LIKE 'Open' AND primkey='$key';";
    $D = umc_mysql_fetch_all($sql);
    if (count($D) == 0) {
        umc_error("Ticket ID $key not found!");
    }

    $sql2 = "UPDATE minecraft.`wp_wpscst_tickets` SET `resolution` = 'Closed' WHERE `primkey`=$key;";
    umc_mysql_fetch_all($sql2);
    umc_echo("Ticket ID $key was successfully closed!");
}


function umc_ticket_read() {
    global $WSEND;
    $player = $WSEND['player'];
    $player_id = umc_user_id($player);
    $args = $WSEND['args'];

    if (!isset($args[1]) && !isset($args[2])) {
        umc_show_help($args);
    }

    $key = intval($args[2]);
    if (!is_numeric($key)) {
        umc_error("Invalid ticket ID");
    }

    $sql = "SELECT * FROM minecraft.wp_wpscst_tickets WHERE user_id=$player_id AND primkey='$key';";
    $rst = mysql_query($sql);
    if (mysql_num_rows($rst) == 0) {
        umc_error("Ticket ID $key not found!");
    }
    $row = mysql_fetch_array($rst, MYSQL_ASSOC);
    umc_pretty_bar('red', '-', " Help Ticket $key ", $width = 52);
    umc_echo('{yellow}Question: {white}' . strip_tags(base64_decode($row['initial_message'])));
    $status = $row['resolution'];

    $sql = "SELECT * FROM  minecraft.`wp_wpscst_replies` WHERE ticket_id='$key' ORDER BY timestamp ASC;";
    $rst = mysql_query($sql);
    if (mysql_num_rows($rst) > 0) {
        while ($row = mysql_fetch_array($rst, MYSQL_ASSOC)) {
            umc_pretty_bar('white', '- ', '', $width = 30);
            $reply_user = umc_user_id($row['user_id']);
            umc_echo("{yellow}$reply_user{white}: " . strip_tags(base64_decode($row['message'])));
        }
    }
    umc_pretty_bar('white', '- ', '', $width = 30);
    if ($status == 'Open') {
        umc_echo('{red}This ticket is still open. Please close if the issue is solved or reply to it!');
    } else {
        umc_echo('{white}This ticket has been closed!');
    }
    umc_pretty_bar('red', '-', ' End ', $width = 52);
}

function umc_ticket_reply() {
    global $WSEND;
    $player = $WSEND['player'];
    $player_id = umc_user_id($player);
    $args = $WSEND['args'];

    if (!isset($args[1]) && !isset($args[2])) {
        umc_show_help($args);
    }

    $key = intval($args[2]);
    if (!is_numeric($key)) {
        umc_error("Invalid ticket ID");
    }

    if (!isset($args[3])) {
        umc_error("You have to enter a message!");
    }

    array_shift($args);
    array_shift($args);
    array_shift($args);
    $text = trim(implode(' ', $args));

    $message = $text;

    $user = mysql_real_escape_string($user);
    $sql = "SELECT * FROM minecraft.wp_wpscst_tickets WHERE user_id=$player_id AND primkey='$key';";
    $rst = mysql_query($sql);
    if (mysql_num_rows($rst) == 0) {
        umc_error("Ticket ID $key not found!");
    }
    $row = mysql_fetch_array($rst, MYSQL_ASSOC);
    $status = $row['resolution'];
    if ($status == 'Closed') {
        umc_error('This ticket has been closed!');
    }

    $new_message = base64_encode($message);
    $ins_sql = "INSERT INTO minecraft.`wp_wpscst_replies` (`primkey`, `ticket_id`, `user_id`, `timestamp`, `message`) "
        . "VALUES (NULL , '{$key}', '{$player_id}', '".time()."', '{$new_message}');";
    $ins_rst = mysql_query($ins_sql);
    umc_pretty_bar('red', '-', " Sent reply to ticket $key", $width = 55);
    umc_echo($message);
    umc_pretty_bar('red', '-', '', $width = 52);

    $player_email = umc_user_email($player);
    $devOptions = umc_ticket_options();

    $to      = $player_email; // Send this to the ticket creator
    $subject = $devOptions['email_new_reply_subject'] . " Ticket ID [$key]";
    $message = $devOptions['email_new_reply_body'] . " \r\n ";
    $headers = 'From: ' . $devOptions['email'] . "\r\n" .
        'Reply-To: ' . $devOptions['email'] .  "\r\n" .
        'X-Mailer: PHP/' . phpversion();
    mail($to, $subject, $message, $headers);

    $to      = $devOptions['email']; // Send this to the admin^M
    $subject = __("[Uncovery Minecraft] Ticket ID [$key] Reply from $player", 'wpsc-support-tickets');
    $message = __("There is a new reply:  \r\n" . $text . "\r\n", 'wpsc-support-tickets')
        . get_admin_url().'admin.php?page=wpscSupportTickets-edit&primkey='.$key;
    $headers = 'From: ' . $devOptions['email'] . "\r\n" .
    'Reply-To: ' . $devOptions['email'] .  "\r\n" .
    'X-Mailer: PHP/' . phpversion();
    mail($to, $subject, $message, $headers);
}

/*
 * Calls the ticket list function when players join the server
 */
function umc_ticket_list_playerjoin() {
    umc_ticket_list(true);
}

/**
 *
 * @global type $WSEND
 * @param type $start set this to true to show help on tickets when list is empty
 */
function umc_ticket_list($start = false) {
    global $UMC_USER;

    $uuid = $UMC_USER['uuid'];
    $sql = "SELECT * FROM minecraft.wp_wpscst_tickets WHERE user_id='$uuid' AND resolution LIKE 'Open';";
    $rst = mysql_query($sql);
    if (mysql_num_rows($rst) == 0) {
        if ($start) {
            umc_echo("{red}If something does not work, please type {green}/ticket", true);
        } else {
            umc_echo("You have no open tickets!");
            return '';
        }
    } else {
        umc_pretty_bar('red', '-', ' Your open help tickets ', 55);
        umc_echo("{white}Id   {white}Question");
        while ($row = mysql_fetch_array($rst, MYSQL_ASSOC)) {
            $id = $row['primkey'];
            $text = strip_tags(base64_decode($row['initial_message']));
            // check for replies
            $re_sql = "SELECT * FROM minecraft.`wp_wpscst_replies` WHERE ticket_id='$id' ORDER BY timestamp DESC LIMIT 0,1;";
            $re_rst = mysql_query($re_sql);
            $answer = $text;
            if (mysql_num_rows($re_rst) > 0) {
                $re_row = mysql_fetch_array($re_rst, MYSQL_ASSOC);
                $reply_person = $re_row['user_id'];
                if ($reply_person !== $uuid) {
                    $answer = "{red}YOU HAVE A REPLY!";
                }
            }
            umc_echo("{green}$id    $answer");
        }
        umc_echo("{blue}Use /ticket read <id> to read the ticket!");
        umc_echo("{red}Please close tickets that are resolved with /ticket close!");
        umc_pretty_bar('red', '-', '', $width = 52);
    }
}

function umc_ticket_new() {
    global $WSEND;
    $player = $WSEND['player'];
    $player_email = umc_user_email($player);
    $player_id = umc_user_id($player);
    $devOptions = umc_ticket_options();
    $args = $WSEND['args'];

    if (!isset($args[1])) {
        umc_show_help($args);
    }

    array_shift($args);
    array_shift($args);
    $text = trim(implode(' ', $args));

    if (stristr($text, 'have a problem') || (strlen($text) < 8)) {
        umc_error("You need to specify the problem better!");
    }

    $sql_text = mysql_real_escape_string($text);

    $wpscst_title = base64_encode(strip_tags($text));
    $wpscst_initial_message = base64_encode($text);
    $wpscst_department = base64_encode(strip_tags('in-game'));

    $sql = "
    INSERT INTO minecraft.`wp_wpscst_tickets` (
        `primkey`, `title`, `initial_message`, `user_id`, `email`, `assigned_to`, `severity`, `resolution`,
        `time_posted`, `last_updated`, `last_staff_reply`, `target_response_time`, `type`) VALUES (
            NULL,
            '{$wpscst_title}',
            '{$wpscst_initial_message}',
            '$player_id',
            '$player_email',
            '0',
            'Normal',
            'Open',
            '".current_time( 'timestamp' )."',
            '".current_time( 'timestamp' )."',
            '',
            '2 days',
            '{$wpscst_department}'
        );
    ";

    $rst = mysql_query($sql);
    $lastID = mysql_insert_id();

    // user email
    $to      = $player_email; // Send this to the ticket creator
    $subject = $devOptions['email_new_ticket_subject'] . " Ticket ID [$lastID]";
    $message = $devOptions['email_new_ticket_body'] . "\r\nTicket contents: \r\n$text";
    $headers = 'From: ' . $devOptions['email'] . "\r\n" .
        'Reply-To: ' . $devOptions['email'] .  "\r\n" .
        'X-Mailer: PHP/' . phpversion();
    mail($to, $subject, $message, $headers);

    // admin email
    $to      = $devOptions['email']; // Send this to the admin^M
    $subject = __("[Uncovery Minecraft] New Ticket from $player", 'wpsc-support-tickets');
    $message = __("New Ticket [$lastID] from $player:\r\n" . $text . "\r\n", 'wpsc-support-tickets')
        . get_admin_url().'admin.php?page=wpscSupportTickets-edit&primkey='.$lastID . "\r\n" . var_dump($devOptions);
    $headers = 'From: ' . $player_email . "\r\n" .
    'Reply-To: ' . $player_email .  "\r\n" .
    'X-Mailer: PHP/' . phpversion();
    mail($to, $subject, $message, $headers);

    umc_pretty_bar('red', '-', ' Help Ticket Created ', $width = 52);
    umc_echo('Your question:');
    umc_echo("{yellow}$text");
    umc_echo('You also received an email. Please check your inbox.');
    umc_pretty_bar('red', '-', '', $width = 52);
}
?>
