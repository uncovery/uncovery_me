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
 * This plugin enables a central, internal mail system including a simple web interface
 * What is important to know is that emails NEVER get deleted, they only get
 * marked as deleted since there only one table that the emails get stored in. Senders
 * and recipients read from the same table.
 */

global $UMC_SETTING, $WS_INIT;
global $UMC_FUNCTIONS;
$UMC_FUNCTIONS['test_mail'] = 'umc_test_mail';

/*
 * TODO: when a user receives email alerts, all emails sent to the user should be automatically marked as "read".
 * TODO: Sent alert emails should have a link for "reply" in them.
 */

$WS_INIT['mail'] = array(  // the name of the plugin
    'disabled' => false,
    'events' => array(
        'PlayerJoinEvent' => 'umc_mail_new_check',
    ),
    'default' => array(
        'help' => array(
            'title' => 'User mail system',  // give it a friendly title
            'short' => 'Send and receive messages to other users',  // a short description
            'long' => "Internal email system. Will send messages to users. You can have one draft email to work on.", // a long add-on to the short  description
            ),
    ),
    'new' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Create a new message',
            'long' => "Creates a new message to a user.",
            'args' => '<recipient> <title>',
        ),
        'function' => 'umc_mail_new',
    ),
    'text' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Adds text to a message',
            'long' => "Add text to the current draft. Existing messages will be appended to.",
            'args' => '<message>',
        ),
        'function' => 'umc_mail_text',
    ),
    'send' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Sends an email',
            'long' => "Send an email created with /mail new",
        ),
        'function' => 'umc_mail_send',
    ),
    'read' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Reads an email',
            'long' => "Read an email in your inbox. If there is no message ID given, it will read the next unread message.",
            'args' => '<message id>',
        ),
        'function' => 'umc_mail_read',
    ),
    'delete' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Deletes an email',
            'long' => "Delete an email from your mailbox. Use /mail delete all to erase all messages.",
            'args' => '<message id|all> ',
        ),
        'function' => 'umc_mail_delete',
    ),
    'draft' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Show the current draft',
            'long' => "Shows the current drafted email",
            'args' => '',
        ),
        'function' => 'umc_mail_draft',
    ),
    'list' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'List your emails',
            'long' => "Show all your mail, or by folder (inbox, outbox, trash)",
            'args' => '[inbox|outbox|trash]',
        ),
        'function' => 'umc_mail_list',
    ),
    'ticket' => array(
        'function' => 'umc_mail_ticket',
        'help' => array(
            'short' => 'Send an email to an admin to ask for help',
            'long' => 'This will start a normal email, but you do not have to add a recipient. '
                . 'It will be automatically to Uncovery',
        ),
        'top' => true,
    ),
);

function umc_mail_new_check() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $uuid = $UMC_USER['uuid'];
    $result = umc_mail_check($uuid);
    if (!$result) {
        umc_echo("If you have technical problems, please use {red}/ticket <question>{white}");
    }
}

function umc_mail_ticket() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    umc_mail_new('uncovery');
}

function umc_mail_new($recipient = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $username = $UMC_USER['username'];
    $uuid = $UMC_USER['uuid'];
    $args = $UMC_USER['args'];

    if (umc_mail_draft_existing()) {
        umc_error("You already have a draft in your mailbox. Please use {green}/mail draft{white} to see it.");
    }

    if (!$recipient) {
        $user_input = $args[2];
        if ($user_input == $username) {
            umc_error("You cannot send emails to yourself!");
        }
        $recipient = umc_check_user($user_input);
    } else {
        array_unshift($args, "[Ticket]");
    }
    $recipient_uuid = umc_user2uuid($recipient);

    // check recipient
    if (!umc_check_user($recipient_uuid)) {
        umc_error("The user $recipient does not exist!");
    }

    if (!isset($args[3])) {
        umc_error("You need to provide at title to your mail!");
    }
    $title = '';
    for ($i=3; $i<count($args); $i++) {
        $title .= " " . $args[$i];
    }
    if (strlen($title) > 32) {
        umc_error("The title is too long! (32 letters max)");
    }

    $mysql_title = trim(umc_mysql_real_escape_string($title));
    $sql = "INSERT INTO minecraft_srvr.user_mail (`sender_uuid`, `recipient_uuid`, `title`, `message`, `status`, `date_time`)
            VALUES ('$uuid','$recipient_uuid', $mysql_title,'','draft', NOW());";
    umc_mysql_query($sql);
    $id = umc_mysql_insert_id();
    umc_mail_display($id);
}

/*
 * add text to an email
 */
function umc_mail_text() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $args = $UMC_USER['args'];

    // check recipient
    $id = umc_mail_draft_existing();
    if (!$id) {
        umc_error("You need to create a new draft using {green}/mail new <recipient> <title>{white} first!");
    }

    if (!isset($args[2])) {
        umc_error("You need to provide text to your mail!");
    }
    $message = '';
    for ($i=2; $i<count($args); $i++) {
        $message .= " " . $args[$i];
    }
    $sql_message = umc_mysql_real_escape_string(trim($message));
    $sql = "UPDATE minecraft_srvr.user_mail SET `message` = CONCAT(`message`, $sql_message), date_time=NOW() WHERE msg_id=$id LIMIT 1";
    umc_mysql_query($sql, true);
    umc_mail_display($id);
}

/*
 * send the current draft email
 */
function umc_mail_send() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $id = umc_mail_draft_existing();
    if (!$id) {
        umc_error("You need to create a new message using {green}/mail new <recipient> <title>{white} first!");
    }
    $sql = "UPDATE minecraft_srvr.user_mail SET status = 'sent', date_time=NOW() WHERE msg_id=$id LIMIT 1";
    umc_mysql_query($sql, true);
    umc_echo("Mail ID $id was sent successfully!");
    $uuid = $UMC_USER['uuid'];
    umc_mail_check($uuid);
    umc_mail_send_alert($id);
}

/**
 * Function to send emails to a recipient faster. This is used by other functions
 * to send status emails through code instead of user interactions. Does not use
 * the draft function. One has to chose if the user is the system or the initiator
 * of the calling function. In the latter case the sent mail will appear in the
 * outbox of the sender which might be a bit confusing.
 *
 * @param type $message
 * @param type $recipient
 * @param type $sender
 */
function umc_mail_quick_send($title, $message, $recipient_uuid, $sender_uuid = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    // user "Server" in case none is given
    if (!$sender_uuid) {
        $sender_uuid = 'Server00-0000-0000-0000-000000000000';
        $message .= "\nThis email is system-generated. Do not reply to this email.";
    }

    $title_sql = umc_mysql_real_escape_string($title);
    $message_sql = umc_mysql_real_escape_string($message);

    $sql = 'INSERT INTO minecraft_srvr.user_mail (`sender_uuid`, `recipient_uuid`, `title`, `message`, `status`, `date_time`) '
        . "VALUES ('$sender_uuid', '$recipient_uuid', $title_sql, $message_sql, 'sent', NOW());";
    umc_mysql_query($sql, true);
}

/*
 * show the current draft email
 */
function umc_mail_draft() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $id = umc_mail_draft_existing();
    if (!$id) {
        umc_error("There is no current mail draft to display!");
    }
    umc_mail_display($id);
}

/*
 * display a full email to a user
 */
function umc_mail_display($id) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $uuid = $UMC_USER['uuid'];
    $sql = "SELECT * FROM minecraft_srvr.`user_mail` WHERE msg_id=$id AND (recipient_uuid='$uuid' OR sender_uuid='$uuid');";
    $D = umc_mysql_fetch_all($sql);
    umc_header("Uncovery Mail Services");
    if (count($D) == 0){
        umc_echo("No mail found");
        umc_footer();
        return;
    }
    $row = $D[0];

    $recipient_uuid = $row['recipient_uuid'];
    $recipient = umc_user2uuid($recipient_uuid);
    $sender_uuid = $row['sender_uuid'];
    $sender = umc_user2uuid($sender_uuid);
    $status = $row['status'];
    $title = stripslashes(html_entity_decode($row['title']));
    $message = stripslashes(html_entity_decode($row['message']));

    umc_echo("{green}Message ID:{white} $id {green}Sender:{white} $sender {green}Recipient: {white}$recipient");
    umc_echo("{green}Title:{white} $title");
    if ($message == '' && $status == 'draft') {
        umc_echo("{green}Message:{grey} This title does not have a message text. "
            . "If you want to add text to the message, please use {green}/mail text <message>{white}.");
        umc_echo("If the title is enough, send it with {green}/mail send{white} now!");
    } else if ($message == '') {
        umc_echo("{green}Message:{grey}No message");
    } else {
        umc_echo("{green}Message:{white}");
        $one_line_message = preg_replace( "/\r|\n/", "|", $message);
        $message_lines = explode("|", $one_line_message);
        foreach ($message_lines as $line) {
            umc_echo($line);
        }
    }
    umc_footer();
    if ($player == $recipient && $status == 'sent') {
        $read_sql = "UPDATE minecraft_srvr.`user_mail` SET status='read' WHERE msg_id=$id;";
        umc_mysql_query($read_sql, true);
    }
}

/*
 * check for new mail
 */
function umc_mail_check($uuid = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $user_filter = '';
    $check = false;
    if ($uuid) {
        $user_filter = " AND recipient_uuid='$uuid'";
    }
    $sql = "SELECT * FROM minecraft_srvr.user_mail WHERE status='sent'$user_filter;";
    $D = umc_mysql_fetch_all($sql);
    $mails = array();
    foreach ($D as $row) {
        $user_uuid = $row['recipient_uuid'];
        $id = $row['msg_id'];
        $title = $row['title'];
        $mails[$user_uuid][$id] = array(
            'sender_uuid' => $row['sender_uuid'],
            'title' => stripslashes($title)
        );
    }

    foreach ($mails as $uuid => $data) {
        $user = umc_user2uuid($uuid);
        $count = count($data);
        $check = umc_echo("You have $count new mail(s):");
        if (!$check) {
            continue;
        }
        foreach ($data as $id => $mail) {
            umc_echo("{green}ID:{white} $id {green}From: {white}$user{green}:{white}{$mail['title']}");
            $check = true;
        }
    }
    return $check;
}

/**
 * this checks if there is a draft email for the current user. returns the ID if true, otherwise false
 */
function umc_mail_draft_existing() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $uuid = $UMC_USER['uuid'];

    $sql = "SELECT * FROM minecraft_srvr.`user_mail` WHERE sender_uuid='$uuid' AND status='draft'";
    $D = umc_mysql_fetch_all($sql);
    if (count($D) == 0) {
        return false;
    } else {
        $row = $D[0];
        $id = $row['msg_id'];
        return $id;
    }
}

/*
 * read the next unread email
 */
function umc_mail_read() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $uuid = $UMC_USER['uuid'];
    $args = $UMC_USER['args'];

    if (!isset($args[2])) {
        $sql = "SELECT * FROM minecraft_srvr.`user_mail` WHERE recipient_uuid='$uuid' AND status='sent'";
        $error = "There are no unread emails!";
    } else if (is_numeric($args[2])) {
        $id = $args[2];
        $sql = "SELECT * FROM minecraft_srvr.`user_mail` WHERE (recipient_uuid='$uuid' OR sender_uuid='$uuid') AND msg_id='$id';";
        $error = "There is no mail with that ID!";
    }

    $D = umc_mysql_fetch_all($sql);
    if (count($D) == 0) {
        umc_echo($error);
    } else {
        $row = $D[0];
        $id = $row['msg_id'];
        umc_mail_display($id);
    }
}

function umc_mail_delete() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $uuid = $UMC_USER['uuid'];
    $args = $UMC_USER['args'];
    if (!isset($args[2])) {
        umc_error("You need to give an ID to be deleted!");
    } else if ($args[2] == 'all') {
        $filter = ';';
        $result = "All messages deleted!";
    } else if (!is_numeric($args[2])) {
        umc_error("You need to use a message id or 'all' to delete messages");
    } else {
        $id = $args[2];
        $filter = " AND msg_id=$id";
        $result = "Message ID $id was removed from your mailbox!";
    }

    $sql = "SELECT * FROM minecraft_srvr.`user_mail` WHERE (recipient_uuid='$uuid' OR sender_uuid='$uuid') $filter";
    $D = umc_mysql_fetch_all($sql);
    if (count($D) == 0) {
        umc_error("Could not find any email to delete!");
    }
    foreach ($D as $row) {
        if ($row['sender_uuid'] == $uuid) {
            $role = 'sender';
        } else if ($row['recipient_uuid'] == $uuid) {
            $role = 'recipient';
        }
        umc_mail_delete_update_status($row['status'], $role, $row['msg_id']);
    }
    umc_echo($result);
}

function umc_mail_list() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $uuid = $UMC_USER['uuid'];
    $args = $UMC_USER['args'];

    $folders = array('inbox', 'outbox', 'trash');
    if ((isset($args[2]) && !in_array($args[2], $folders))) {
        umc_error("You need to chose one of the folders {green}inbox{white}, {green}outbox{white} or {green}trash{white}");
    }

    if (!isset($args[2])) {
        $folder = "All folders";
        $filter = 'all';
        $no_mail = "You have no emails";
    } else {
        $folder = "Folder " . ucwords($args[2]);
        $filter = $args[2];
        $no_mail = "You have no emails in $folder";
    }

    umc_header("Uncovery Mail $folder");
    $status_arr = array(
        'all' => "(recipient_uuid='$uuid' AND status NOT IN ('deleted_recipient','deleted_both')) OR (sender_uuid='$uuid' AND status NOT IN ('deleted_sender','deleted_both'))",
        'inbox' => "recipient_uuid='$uuid' AND (status='sent' OR status='read')",
        'outbox' => "sender_uuid='$uuid' AND (status='sent')",
        'trash' => "(recipient_uuid='$uuid' AND status IN ('deleted_recipient','deleted_both')) OR (sender_uuid='$uuid' AND status IN ('deleted_sender','deleted_both'))",
    );

    $sql_filter = $status_arr[$filter];
    $sql = "SELECT * FROM minecraft_srvr.`user_mail` WHERE $sql_filter ORDER BY date_time ASC;";
    $D = umc_mysql_fetch_all($sql);

    if (count($D) == 0) {
        umc_echo("No emails in this mailbox");
        umc_footer();
        return;
    }

    foreach ($D as $row) {
        if ($row['sender_uuid'] == $uuid) {
            $recipient = umc_user2uuid($row['recipient_uuid']);
            $wholine = "{red}->{white}$recipient";
            if ($row['status'] == 'sent') {
                $folder = 'Outbox';
            } else if (strstr($row['status'], 'deleted')) {
                $folder = 'Trash';
            } else if ($row['status'] == 'draft') {
                $folder = 'Draft';
            }
        } else {
            $sender = umc_user2uuid($row['sender_uuid']);
            $wholine = "$sender{red}->{white}";
            if ($row['status'] == 'read') {
                $folder = 'Inbox';
            } else if (strstr($row['status'], 'deleted')) {
                $folder = 'Trash';
            } else if ($row['status'] == 'sent') {
                $folder = 'Unread';
            }
        }
        umc_echo("{green}#{$row['msg_id']} {yellow}$folder{grey} {$row['date_time']}{white} $wholine: {$row['title']}");
    }
    umc_footer();
}

function umc_test_mail() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $recipient = "oliver@spiesshofer.com";
    $sender = "minecraft@uncovery.me";
    $headers = "From:$sender <$sender>\r\n" .
        "Reply-To: $sender\r\n" .
        "X-Mailer: PHP/" . phpversion();
    $mail_title = "Test mail";
    $body = "This is a test mail";

    $check = mail($recipient, $mail_title, $body, $headers, "-fminecraft@uncovery.me");
    var_dump($check);
    echo "Sent to $recipient";
}

/**
 * checks if the email recipient should be alerted via email, and sends alert
 * if appropriate
 *
 * @param type $mail_id
 */
function umc_mail_send_alert($mail_id = 3886) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_DOMAIN;
    $sql = "SELECT user_mail.*, user_email FROM minecraft_srvr.user_mail
        LEFT JOIN minecraft_srvr.UUID ON recipient_uuid=UUID
        LEFT JOIN minecraft.wp_users ON username=display_name
        WHERE msg_id=$mail_id;";
    $data = umc_mysql_fetch_all($sql);
    $mail = $data[0];
    $alerts = umc_wp_get_meta($mail['recipient_uuid'], 'mc_mail_alerts');
    if ($alerts == 'true') {
        $msg_id = $mail['msg_id'];
        $sender = umc_user2uuid($mail['sender_uuid']);
        $title = $mail['title'];
        $message = $mail['message'];
        $user_email = $mail['user_email'];
        $mail_title = "[Uncovery Mail] $title";
        $body = "You received a new in-game email from $sender:\n"
            . "You can reply to the message here: $UMC_DOMAIN/server-access/mail/?action=mail&id=$msg_id\n"
            . "Subject: $title\n"
            . "-------------START--------------\n"
            . $message
            . "\n--------------END---------------\n";
        $headers = "From:$sender <no-reply@uncovery.me>\r\n" .
            "Reply-To: no-reply@uncovery.me\r\n" .
            "X-Mailer: PHP/" . phpversion();
        mail($user_email, $mail_title, $body, $headers, "-fminecraft@uncovery.me");
    }
}

function umc_mail_delete_update_status($oldstatus, $role, $msg_id) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $newstatus = $oldstatus;
    $sender_array = array(
        'draft'=>'deleted_both',
        'sent' =>'deleted_both',
        'read' =>'deleted_sender',
        'deleted_receiver' => 'deleted_both',
        'deleted_sender' => 'Read',
        'deleted_both'=>'deleted_receiver'
    );
    $receiver_array = array(
        'draft'=>'draft',
        'sent' =>'sent',
        'read' =>'deleted_receiver',
        'deleted_receiver' => 'deleted_sender',
        'deleted_sender' => 'deleted_both',
        'deleted_both'=>'deleted_sender'
    );
    if ($role == 'sender') {
        $newstatus = $sender_array[$oldstatus];
    } else if ($role == 'recipient') {
        $newstatus = $receiver_array[$oldstatus];
    } else {
        XMPP_ERROR_trigger("Invalid delete status");
    }
    $update_sql = "UPDATE `minecraft_srvr`.`user_mail` SET `status` = '$newstatus' WHERE `user_mail`.`msg_id` = $msg_id;";
    umc_mysql_query($update_sql, true);
}

/**
 * displays an interface for email on the website
 *
 * @global type $UMC_USER
 * @return string
 */
function umc_mail_web() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER, $UMC_DOMAIN;
    if (!$UMC_USER) {
        return "You have to be logged in to use this!";
    }
    $uuid = $UMC_USER['uuid'];
    $username = $UMC_USER['username'];
    $folder_arr = array(
    //    'all' => "(recipient='$player' AND status NOT IN ('deleted_recipient','deleted_both')) OR (sender='$player' AND status NOT IN ('deleted_sender','deleted_both'))",
        'inbox' => "recipient_uuid='$uuid' AND (status='sent' OR status='read')",
        'outbox' => "sender_uuid='$uuid' AND (status='sent')",
        'drafts' => "sender_uuid='$uuid' AND (status='draft')",
        'trash' => "(recipient_uuid='$uuid' AND status IN ('deleted_receiver','deleted_both')) OR (sender_uuid='$uuid' AND status IN ('deleted_sender','deleted_both'))",
    );
    $selected = array();

    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
    if (!isset($action)) {
        $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING);
    }

    $out = '<div id="umc_ajax_container" class="webmail" style="display:block">' . "\n";

    // XMPP_ERROR_trigger("Mail");
    $sani_post  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
    $out .= "\n<!-- POST CHECK // \n" . var_export($sani_post, true) . " \n// end -->\n";
    $sani_get  = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
    $out .= "\n<!-- GET CHECK // \n" . var_export($sani_get, true) . " \n// end -->\n";

    $subject = '';
    $message = '';
    $recipient = '';
    $error = '';
    $msg_id = '';

    if ($action == 'Mark all read') {
        $read_sql = "UPDATE minecraft_srvr.`user_mail` SET status='read' WHERE recipient_uuid='$uuid';";
        umc_mysql_query($read_sql, true);
    }

    if ($action == 'Reply') {
        $subject = htmlentities(filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_STRING));
        // type comparison to avoid false (ie not found) evaluating to 0;
        if (strpos($subject, 'Re:') !== 0) {
            $subject = "Re: ". $subject;
        }
        $recipient = filter_input(INPUT_POST, 'sender', FILTER_SANITIZE_STRING);
        $action = "New Mail";
    } else if ($action == 'Delete' || $action == 'Undelete') {
        $msg_id = filter_input(INPUT_POST, 'msg_id', FILTER_SANITIZE_NUMBER_INT);
        $recipient_uuid = filter_input(INPUT_POST, 'recipient_uuid', FILTER_SANITIZE_STRING);
        $sender_uuid = filter_input(INPUT_POST, 'sender_uuid', FILTER_SANITIZE_STRING);
        $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
        if ($sender_uuid == $uuid) {
            $role = 'sender';
        } else if ($recipient_uuid == $uuid) {
            $role = 'recipient';
        }
        umc_mail_delete_update_status($status, $role, $msg_id);
    } else if ($action == 'Send' || $action == 'Save Draft') {
        // send message
        $subject = filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_STRING);
        $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);
        $recipient = strtolower(filter_input(INPUT_POST, 'recipient', FILTER_SANITIZE_STRING));
        $recipient_uuid = umc_user2uuid($recipient);

        $check = umc_check_user($recipient_uuid);
        if ($recipient == $username) {
            $check = false;
            $error = "You cannot send emails to yourself!";
        } else if (!$check) {
            $error = "ERROR: Recipient '$recipient' could not be found!";
            $recipient = '';
        }

        $msg_id = filter_input(INPUT_GET, 'msg_id', FILTER_SANITIZE_NUMBER_INT);
        if (strlen($message) < 5 ) {
            $error = "Your message is too short!";
            $action = "New Mail";
            $check = false;
        } else if (strlen($subject) < 5) {
            $error = "Your subject is too short!";
            $action = "New Mail";
            $check = false;
        }

        if ($action == 'Send' && !$check) { // only complain if we are trying to send
            $action = "New Mail";
        } else {
            umc_mail_send_backend($recipient_uuid, $uuid, $message, $subject, $action, $msg_id);
            $action = '';
        }
    }
    $out .= $error;

    if ($action == 'edit') {
        $msg_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
        $onemail_sql = "SELECT `msg_id`, `date_time`, `recipient_uuid`, username, `title`, `message`, `status`
                FROM minecraft_srvr.`user_mail`
                LEFT JOIN minecraft_srvr.UUID ON recipient_uuid=UUID
                WHERE msg_id=$msg_id AND sender_uuid='$uuid' AND status='draft';";
        $mail_data = umc_mysql_fetch_all($onemail_sql);
        if (count($mail_data) == 0) {
            $out .= "ERROR: The draft email with ID $msg_id could not be found!";
        } else {
            $mail = $mail_data[0];
            $recipient = $mail['username'];
            $subject = htmlentities(trim($mail['title']));
            $message = htmlentities(trim($mail['message']));
            $msg_id = $mail['msg_id'];
            $action = "New Mail";
        }
    }
    $msg_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

    if ($action == 'New Mail') { //onsubmit=\"return umcAjaxFormProcess('" . umc_web_curr_url() . "', event)\"
        $out .= "<form id=\"newmailform\" method=\"post\"><div>\n"
            . "<span style=\"max-width:50%;\">Recipient: <input type=\"text\" name=\"recipient\" value=\"$recipient\" style=\"width:35%;\" maxlength=\"32\"></span>\n "
            . "<span style=\"max-width:50%;\">Subject: <input type=\"text\" name=\"subject\" value=\"$subject\" style=\"width:35%;\" maxlength=\"32\"></span><br>\n"
            . "Message:<br><textarea name=\"message\" value=\"\" rows=\"10\" style=\"width:100%;\">$message</textarea><input type=\"hidden\" name=\"msg_id\" value=\"\">\n"
            . "<input type=\"submit\" name=\"action\" value=\"Send\"><input type=\"submit\" name=\"action\" value=\"Save Draft\"><input type=\"submit\" name=\"action\" value=\"Cancel\">\n"
            . "</div></form>";
    } else if ($action == 'mail' && is_numeric($msg_id)) {
        $onemail_sql = "SELECT `msg_id`, `date_time`, `sender_uuid`, `recipient_uuid`, `title` as subject, `message`, `status` FROM minecraft_srvr.`user_mail`
                WHERE msg_id=$msg_id AND (recipient_uuid='$uuid' OR sender_uuid='$uuid');";
        $mail_data = umc_mysql_fetch_all($onemail_sql);
        if (count($mail_data) == 0) {
            $out .= "ERROR: The email with ID $msg_id could not be found!<br>";
            $out .= "<a href=\"$UMC_DOMAIN/server-access/mail/\">Back</a>";
        } else { // onsubmit=\"return umcAjaxFormProcess('" . umc_web_curr_url() . "', event)\"
            $out .= "<a href=\"$UMC_DOMAIN/server-access/mail/\">Back</a><br>";
            $out .= "\n<form id=\"newmailform\" method=\"POST\" action=\"$UMC_DOMAIN/server-access/mail/\">\n<div>";
            $mail = array();
            foreach ($mail_data[0] as $field => $value) {
                $mail[$field] = htmlentities(stripslashes(trim($value)));
            }
            $buttons = "<div style=\"float:right\">";
            if (in_array($mail['status'], array('deleted_receiver','deleted_both'))) {
                $buttons .= " <input type=\"submit\" name=\"action\" value=\"Undelete\">";
            } else {
                $buttons .= " <input type=\"submit\" name=\"action\" value=\"Delete\">";
            }
            if ($mail['recipient_uuid'] == $uuid) {
                $buttons .= " <input type=\"submit\" name=\"action\" value=\"Reply\">";
            }

            $buttons .= "</div>";
            if ($mail['status'] == 'sent') {
                $read_sql = "UPDATE minecraft_srvr.`user_mail` SET status='read' WHERE msg_id={$mail['msg_id']};";
                umc_mysql_query($read_sql, true);
                $mail['status'] = 'read';
            }
            $sender = umc_user2uuid($mail['sender_uuid']);
            $recipient = umc_user2uuid($mail['recipient_uuid']);
            $out .= "<div class=\"line\"><div style=\"float:left;width:33%;\"><label>From:</label><span class=\"field\">$sender</span></div>\n"
                . "<div style=\"float:left;width:33%;\"><label>To:</label><span class=\"field\">$recipient</span></div>\n"
                . "<div style=\"float:left;width:33%;\"><label>Date:</label><span class=\"field\">{$mail['date_time']}</span></div>\n"
                . "<div style=\"clear:both;\"></div>\n</div>"
                . "$buttons<div class=\"line\" style=\"overflow:hidden\"><label>Subject:</label><span class=\"field\">{$mail['subject']}</span></div>\n"
                . "<div style=\"clear:both;\"></div>\n"
                . "<div class=\"line\"><label>Message:</label><br>"
                . "<div class=\"field\">{$mail['message']}</div>\n</div>\n"
                . "<input type=\"hidden\" name=\"status\" value=\"{$mail['status']}\">"
                . "<input type=\"hidden\" name=\"sender\" value=\"$sender\">"
                . "<input type=\"hidden\" name=\"subject\" value=\"{$mail['subject']}\">"
                . "<input type=\"hidden\" name=\"recipient_uuid\" value=\"{$mail['recipient_uuid']}\">"
                . "<input type=\"hidden\" name=\"msg_id\" value=\"$msg_id\">"
                . "<input type=\"hidden\" name=\"sender_uuid\" value=\"{$mail['sender_uuid']}\">"
                . "</div></form>";
        }
    } else { // show folder
        if (!isset($post_folder)) {
            $post_folder = filter_input(INPUT_POST, 'folder', FILTER_SANITIZE_STRING);
        }
        $sql_filter = $folder_arr['inbox'];
        if (isset($post_folder) && $post_folder != 'inbox') {
            if (isset($folder_arr[$post_folder])) {
                $sql_filter = $folder_arr[$post_folder];
            } else {
                $out .= "<h2>Folder $post_folder cannot be found!</h2>";
            }
        }
        // get the current value
        $alerts_saved = umc_wp_get_meta($uuid, 'mc_mail_alerts');

        $alerts_choice = filter_input(INPUT_POST, 'email_alerts', FILTER_SANITIZE_STRING);
        $submit = filter_input(INPUT_POST, 'submit_form', FILTER_SANITIZE_STRING);

        // update database only if form was submitted
        if ($submit == 'submit_form') {
            if ($alerts_choice == 'email_alerts' && $alerts_saved == 'false') {
                umc_wp_set_meta($uuid, 'mc_mail_alerts', 'true');
                $alerts_saved = 'true';
            } else if ($alerts_choice == NULL && $alerts_saved == 'true') {
                umc_wp_set_meta($uuid, 'mc_mail_alerts', 'false');
                $alerts_saved = 'false';
            }
        }

        $out .= "<form action=\"\" method=\"post\">\n<div class=\"line\">\nFolder: <select name=\"folder\" onchange='this.form.submit()'>";
        $selected[$post_folder] = " selected=\"selected\"";
        foreach ($folder_arr as $folder => $str_filter) {
            $folder_str = ucwords($folder);
            $sel_str = '';
            if (isset($selected[$folder])) {
                $sel_str = $selected[$folder];
            }
            $out .= "<option value=\"$folder\"{$sel_str}>$folder_str</option>";
        }
        $checked = '';

        if ($alerts_saved == 'true') {
            $checked = 'checked="checked"';
        }

        $out .= "</select>\n<input type=\"submit\" name=\"action\" value=\"New Mail\"><input type=\"submit\" name=\"action\" value=\"Mark all read\"><input type=\"hidden\" name=\"submit_form\" value=\"submit_form\">\n"
            . "<span style=\"float:right;\"><input type=\"checkbox\" name=\"email_alerts\" value=\"email_alerts\" $checked onchange='this.form.submit()'> Send e-mail alerts</span>"
            . "</div></form>\n";

        $sql = "SELECT `msg_id`, `date_time`, s_ref.username as sender, r_ref.username as recipient, `title` as subject, status
                FROM minecraft_srvr.`user_mail`
                LEFT JOIN minecraft_srvr.UUID as s_ref on sender_uuid=s_ref.UUID
                LEFT JOIN minecraft_srvr.UUID as r_ref on recipient_uuid=r_ref.UUID
                WHERE $sql_filter ORDER BY date_time DESC;";
        $status_header = "";
        if ($post_folder == 'outbox') {
            $status_header = '<th>Status</th>';
        }
        $D = umc_mysql_fetch_all($sql);

        $non_numeric = array('date_time', 'sender', 'recipient', 'subject');
        $formats = array('sender' => 'umc_mail_web_formats','status'=>'umc_mail_web_formats','recipient' => 'umc_mail_web_formats', 'subject' => 'umc_mail_web_formats');
        $hide_cols = array('msg_id');
        $check = umc_web_table("mail", "0, 'desc'", $D, '', $hide_cols, $non_numeric, $formats);
        if (!$check) {
            XMPP_ERROR_trigger("Error creating web_table with SQL $sql");
            $out .= "Error creating data table. Admin was notified, please wait until it is fixed";
        } else {
            $out .= $check;
        }
    }
    $out .= "</div>\n";
    return $out;
}

function umc_mail_send_backend($recipient_uuid, $sender_uuid, $message_raw, $subject_raw, $action, $msg_id = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $recipient = umc_mysql_real_escape_string($recipient_uuid);
    $sender = umc_mysql_real_escape_string($sender_uuid);
    $message = umc_mysql_real_escape_string($message_raw);
    $subject = umc_mysql_real_escape_string($subject_raw);

    $status = 'draft';
    if (strtolower($action) ==  'send') {
        $status = 'sent';
    }
    if ($msg_id) {
        $sql = "UPDATE minecraft_srvr.user_mail
            SET `sender_uuid`=$sender, `recipient_uuid`=$recipient, `title`=$subject, `message`=$message, `status`='$status', `date_time`=NOW()
            WHERE msg_id=$msg_id;";
    } else {
        $sql = "INSERT INTO minecraft_srvr.user_mail (`sender_uuid`, `recipient_uuid`, `title`, `message`, `status`, `date_time`)
            VALUES ($sender,$recipient,$subject,$message,'$status', NOW());";
    }
    umc_mysql_query($sql, true);

    if ($action == 'Send') {
        $mail_id = umc_mysql_insert_id();
        umc_mail_send_alert($mail_id);
    }
}


/**
 * Formats a column in the mail display
 * @param string $column
 * @param string $value
 */
function umc_mail_web_formats($column, $row) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    switch ($column) {
        case 'status':
            $username = $UMC_USER['username'];
            if ($username == $row['recipient']) {
                $status_list = array('draft'=>'Draft','sent' =>'Unread','read'=>'Read','deleted_receiver'=>'Deleted','deleted_sender'=>'Sent','deleted_both'=>'Deleted');
            } else {
                $status_list = array('draft'=>'Draft','sent' =>'Sent','read'=>'Sent','deleted_receiver'=>'Sent','deleted_sender'=>'Deleted','deleted_both'=>'Deleted');
            }
            $out = $status_list[$row['status']];
            break;
        case 'sender':
        case 'recipient':
            $out = $row[$column];
            break;
        case 'subject':
            if ($row['status'] == 'draft') {
                $action = 'edit';
            } else {
                $action = 'mail';
            }
            $out = "<a href=\"?action=$action&amp;id={$row['msg_id']}\">$row[$column]</a>";
    }
    return $out;
}
