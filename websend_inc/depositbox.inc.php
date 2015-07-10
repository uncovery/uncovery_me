<?php

global $UMC_SETTING, $WS_INIT, $UMC_USER;

$WS_INIT['depositbox'] = array(
    'disabled' => false,
    'events' => false,
    'default'   => array(
        'help' => array(
            'title' => 'Virtual Deposit Boxes',
            'short' => 'Store and exchange items virtually',
            'long' => 'Your deposit allows you to receive goods in a virtual space, which can be withdrawn at any time in any survival world.',
        ),
    ),
    'depotlist' => array(
        'function' => 'umc_show_depotlist',
        'help' => array(
            'args' => '',
            'short' => 'Show the contents of your deposit;',
            'long' => '{green}/depotlist{gray} => {white}Show items you deposited or received;'
        ),
        'top' => true,
    ),
    'depositall' => array(
        'function' => 'umc_do_depositall',
        'help' => array(
            'args' => '[user]',
            'short' => 'Deposit all items in your inventory',
            'long' => '{green}/deposit{gray} => {white}Search your inventory and deposit as much as will fit.;'
            . '{green}/deposit {yellow}[user]{gray} => {white}Send all of the items to [user];'
        ),
        'top' => true,
        'security' => array(
            'worlds' => array( 'empire', 'kingdom', 'skylands', 'aether', 'the_end'),
        ),
    ),
    'deposit' => array(
        'function' => 'umc_do_deposit',
        'help' => array(
            'args' => '[user] [amount]',
            'short' => 'Deposit items for you or others;',
            'long' => '{green}/deposit{gray} => {white}Search your inventory for the item currenty; in your hand, and deposit all of them;'
            . '{green}/deposit {yellow}[user]{gray} => {white}Send all of the item to [user];'
            . '{green}/deposit {yellow}[user] [amount]{gray} => {white}Send only [amount] to [user];'
        ),
        'top' => true,
        'security' => array(
            'worlds' => array( 'empire', 'kingdom', 'skylands', 'aether', 'the_end'),
        ),
    ),
    'withdraw' => array(
        'function' => 'umc_do_withdraw',
        'help' => array(
            'args' => '<shop-id> [amount]',
            'short' => 'Withdraw items from deposit;',
            'long' => '{white}Use {green}/depotlist{white} to find the {yellow}<shop-id>{white} first;'
            . '{green}/withdraw {yellow}<shop-id>{gray} => {white}Withdraw the items held in <shop-id>;'
            . '{green}/withdraw {yellow}<shop-id> [amount]{gray} => {white}Withdraw only [amount];'
            . '{green}/withdraw {yellow}all{gray} => {white}Withdraw everyhing from your deposit;'
            . '{green}/withdraw {yellow}@<sender>{gray} => {white}Withdraw all items from <sender>;'
            . '{white} Example: {yellow} /withdraw @lottery;'
        ),
        'top' => true,
        'security' => array(
            'worlds' => array( 'empire', 'kingdom', 'skylands', 'aether'),
         ),
    ),
    'consolidate' => array(
        'function' => 'umc_depositbox_consolidate',
        'help' => array(
            'args' => '',
            'short' => 'Combine similar items',
            'long'  => '{green}/consolidate{gray} => {white}Combine deposit boxes with the same content;'
                    .  '{white}but different senders.'
        ),
    ),
);

$UMC_SETTING['depositbox_limit'] = array(
    'Guest' => 0,
    'Settler' => 0, 'SettlerDonator' => 1, 'SettlerDonatorPlus' => 2,
    'Citizen'=> 1, 'CitizenDonator'=> 2, 'CitizenDonatorPlus'=> 3,
    'Architect' => 2, 'ArchitectDonator' => 3, 'ArchitectDonatorPlus' => 4,
    'Designer' => 3, 'DesignerDonator' => 4, 'DesignerDonatorPlus' => 5,
    'Master' => 4, 'MasterDonator' => 5, 'MasterDonatorPlus' => 6,
    'Elder' => 5, 'ElderDonator' => 6, 'ElderDonatorPlus' => 7,
    'Owner' => 40);


/**
 * Show a list of deposit box contents
 *
 * @global type $UMC_USER
 * @global array $UMC_SETTING
 * @param type $silent
 * @param type $user
 * @param type $web
 * @return string|boolean
 */
function umc_show_depotlist($silent = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER, $UMC_SETTING, $UMC_ENV;
    $player = $UMC_USER['username'];
    $uuid = $UMC_USER['uuid'];

    if (isset($UMC_USER['args'][2]) && !in_array($player, $UMC_SETTING['admins'])) {
        umc_error("You are not allowed to look at other's boxes'");
    } else if (isset($UMC_USER['args'][2])) {
        $player = $UMC_USER['args'][2];
        $uuid = umc_uuid_getone($player, 'uuid');
    }
    $userlevel = umc_get_uuid_level($uuid);

    $web = false;
    if ($UMC_ENV == 'wordpress') {
        $web = true;
    }

    $sql = "SELECT * FROM minecraft_iconomy.deposit WHERE sender_uuid='$uuid' OR recipient_uuid='$uuid' ORDER BY id, damage, amount DESC;";
    $D = umc_mysql_fetch_all($sql);
    $num_rows = count($D);
    $web_arr = array();
    if ($silent && $num_rows == 0) {
        return '';
    } else if ($num_rows == 0) {
        if ($web) {
            return false;
        } else {
            umc_error("{gold}$player{red} has nothing in the deposit!");
        }
    } else {
        if (!$web) {
            umc_header();
            umc_echo("{gray}Depot-Id   Description");
        }
        $count = 0;
        foreach ($D as $row) {
            $sender_uuid = $row['sender_uuid'];
            $sender = umc_user2uuid($sender_uuid);

            $recipient_uuid = $row['recipient_uuid'];
            $recipient = umc_user2uuid($recipient_uuid);
            $item = umc_goods_get_text($row["item_name"], $row["damage"], $row['meta']);
            if (!$item) { // could not identify item_name
                XMPP_ERROR_trigger("Error deposit ID {$row['id']}, Item Name {$row["item_name"]} could not be found!");
            }
            if ($row['amount'] == -1) {
                $row['amount'] = 'inf.';
            }
            if ($recipient_uuid == $uuid) {
                $count++;
            }
            $label = "";
            if ($sender_uuid != $uuid && $recipient_uuid == $uuid) {
                $label = "{green}from {gold}" . $sender;
            } elseif ($sender_uuid == $uuid && $recipient_uuid != $uuid) {
                $label = "{blue}for {gold}" . $recipient;
            }
            if ($web) {
                $web_arr[$row['id']] = array(
                    'item' => "{$row['amount']} {$item['full']}",
                    'sender' => $sender,
                    'recipient' => $recipient,
                );
            } else {
                umc_echo(sprintf("{green}%7d     {yellow}%s", $row['id'], $row['amount']
                    ." {$item['full']} $label"));
            }
        }
        if (!$web) {
            $allowed = $UMC_SETTING['depositbox_limit'][$userlevel];
            umc_pretty_bar("darkblue", "-", " {green}$count / $allowed slots used ");
            umc_echo("{cyan}[*] {green}Withdraw with {yellow}/withdraw <Depot-Id>");
            umc_footer();
        } else {
            return $web_arr;
        }
    }
}

// withdraw
function umc_do_withdraw() {
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $uuid = $UMC_USER['uuid'];
    $args = $UMC_USER['args'];
    if (!isset($args[3])) {
        $amount = 'max';
    } else {
        $amount = umc_sanitize_input($args[3], 'amount');
    }
    // get an item by deposit-ID
    if (isset($args[2]) && is_numeric($args[2])) {
        $id = $args[2];
        umc_echo("{green}[+] {gray}Withdrawing ID {green}$id{gray}...");
        umc_log('deposit', 'withdraw', "$player tried to withdraw $amount of $id");
        umc_checkout_goods($id, $amount, 'deposit');

    // get several items, either all or by sender name
    } else if (isset($args[2])) {
        $id = $args[2];
        // make list of possible senders to avoid SQL injection
        $checklist = array('@lottery');
        $check_sql = "SELECT * FROM minecraft_iconomy.deposit WHERE recipient_uuid='$uuid' OR sender_uuid='$uuid';";
        $D = umc_mysql_fetch_all($check_sql);
        foreach ($D as $check_row) {
            $checklist[] = '@' . $check_row['sender_uuid'];
        }
        if (in_array($id, $checklist)) { // withdrawing all items from specific sender
            $sender = substr($id, 1); // strip the @ from the name
            $sender_uuid = umc_user2uuid($sender);
            umc_echo("{green}[+]{gray} Withdrawing items sent by {gold}$sender{gray} from your deposit");
            $sql = "SELECT `id`, `item_name`, `amount` FROM minecraft_iconomy.deposit WHERE sender_uuid='$sender_uuid' AND recipient_uuid='$uuid'";
            umc_log('deposit', 'withdraw', "$player tried to withdraw $amount from $sender");
        } else if ($id == 'all') { // withdrawing the whole deposit
            umc_echo("{green}[+]{gray} Withdrawing everything from your deposit...");
            $sql = "SELECT `id`, `item_name`, `amount` FROM minecraft_iconomy.deposit WHERE recipient_uuid='$uuid'";
            umc_log('deposit', 'withdraw', "$player tried to withdraw all");
        } else { // by item name
            $find_item = umc_goods_get_text($id);
            //if (in_array($find_item['id'], $umc_unavailable)) {
            //    umc_echo("{red}This item is unavailable. Please check the wiki for the proper item!",true);
            //}
            $sql = "SELECT `id`, `item_name`, `amount` FROM minecraft_iconomy.deposit
                WHERE recipient_uuid='$uuid'
		    AND item_name='{$find_item['item_name']}'
		    AND damage='0'";
            umc_log('deposit', 'withdraw', "$player tried to {$find_item['item_name']}:0");
        }

        $D = umc_mysql_fetch_all($sql);
        if (count($D) > 0) {
            $all_items = array();
            foreach ($D as $row) {
                $id = $row['id'];
                $item_name = $row['item_name'];
                if($amount == 'max') {
                    $this_amount = $row['amount'];
                } else if ($row['amount'] > $amount) {
                    $this_amount = $amount;
                    $amount = 0;
                } else {
                    $this_amount = $row['amount'];
                    $amount -= $this_amount;
                }
                $all_items[$id] = array('item_name' => $item_name, 'amount' => $this_amount);
            }
            umc_check_space_multiple($all_items);
            foreach ($all_items as $id => $data) {
                umc_checkout_goods($id, $data['amount'], 'deposit');
            }
            if($amount > 0) {
                umc_echo("{yellow}[!]{gray} Not enough items were found, withdrew maximum possible.");
            }
        } else {
            umc_error("{red}There are no such items in your deposit.");
        }
    } else {
        umc_error('{red}You need an ID number or a sender name. Please use {yellow}/shophelp');
    }
}


// deposit
function umc_do_deposit() {
    umc_do_deposit_internal();
}

// depositall
function umc_do_depositall() {
    umc_do_deposit_internal(true);
}

/**
 * Put an item into the deposit of the recipient
 *
 * @param type $recipient
 * @param type $item_name
 * @param type $data
 * @param type $meta
 * @param type $amount
 * @param type $sender
 */
function umc_deposit_give_item($recipient, $item_name, $data, $meta, $amount, $sender) {
    global $UMC_DATA_ID2NAME, $UMC_DATA;
    if (is_numeric($item_name)) {
        $item_name = $UMC_DATA_ID2NAME[$item_name];
    }

    if (!isset($UMC_DATA[$item_name])) {
        XMPP_ERROR_trigger("Could not deposit item $item_name for user $recipient!");
    }

    if (is_array($meta) > 0) {
        $meta = serialize($meta);
    } else {
        $meta = '';
    }
    $recipient_uuid = umc_uuid_getone($recipient, 'uuid');
    $sender_uuid = umc_uuid_getone($sender, 'uuid');

    $sql = "SELECT * FROM minecraft_iconomy.deposit
        WHERE item_name='$item_name' AND recipient_uuid='$recipient_uuid'
        AND damage='$data' AND meta='$meta' AND sender_uuid='$sender_uuid';";
    $D = umc_mysql_fetch_all($sql);

        // check first if item already is being sold
    if (count($D) > 0) {
        $row = $D[0];
        $sql = "UPDATE minecraft_iconomy.`deposit` SET `amount`=amount+$amount WHERE `id`={$row['id']} LIMIT 1;";
    } else {
        // create a new deposit box
        $sql = "INSERT INTO minecraft_iconomy.`deposit` (`damage` ,`sender_uuid` ,`item_name` ,`recipient_uuid` ,`amount` ,`meta`)
            VALUES ('$data', '$sender_uuid', '$item_name', '$recipient_uuid', '$amount', '$meta');";
    }
    //umc_echo($sql);
    umc_mysql_query($sql, true);
}

function umc_do_deposit_internal($all = false) {
    global $UMC_USER, $UMC_SETTING, $UMC_DATA;
    $player = $UMC_USER['username'];
    $uuid = $UMC_USER['uuid'];
    $args = $UMC_USER['args'];

    // make sure user holds item
    $all_inv = $UMC_USER['inv'];
    if(!$all) {
        $item_slot = $UMC_USER['current_item'];
        if (!isset($all_inv[$item_slot])) {
            umc_error("{red}You need to hold the item you want to deposit! (current slot: $item_slot);");
        }
        $all_inv = array($item_slot => $all_inv[$item_slot]);
    }
    $sent_out_of_space_msg = 0;
    $seen = array();

    foreach ($all_inv as $slot) {
        $item_id = $slot['item_name'];
        if (!isset($UMC_DATA[$item_id])) {
            XMPP_ERROR_trigger("Invalid item deposit cancelled!");
            umc_error("Sorry, the item in your inventory is bugged, uncovery was notfied and this should be fixed soon. IF you want to speed it up, please send a ticket with as much detail as possible.");
        }
        $data = $slot['data'];
        if ($slot['meta']) {
            $meta = serialize($slot['meta']);
        } else {
            $meta = false;
        }
        // don't assign the same twice
        $item = umc_goods_get_text($slot['item_name'], $slot['data'], $slot['meta']);
        if (isset($seen[$item['full']])) {
            continue;
        }
        $inv = umc_check_inventory($slot['item_name'], $slot['data'], $slot['meta']);
        if ($inv == 0) {
            XMPP_ERROR_trigger("Item held could not be found in inventory: {$slot['item_name']}, {$slot['data']}, " . var_export($slot['meta'], true));
            umc_error("There was a system error. The admin has been notified. Deposit aborted.");
        }

        if (isset($args[2]) && $args[2] != 'lot_reset') {
            $recipient = umc_sanitize_input($args[2], 'player');
            $recipient_uuid = umc_user2uuid($recipient);
        } else if (isset($args[2]) && $args[2] == 'lot_reset') {
            $recipient_uuid = 'reset000-lot0-0000-0000-000000000000';
            $recipient = $args[2];
        } else {
            $recipient = $player;
            $recipient_uuid = $uuid;
            if (!$all) {
                umc_echo("{yellow}[!]{gray} No recipient given. Depositing for {gold}$player");
            }
        }
        if (!$all && isset($args[3])) {
            $amount = umc_sanitize_input($args[3], 'amount');
            $amount_str = $amount;
            if ($amount > $inv) {
                umc_echo("{yellow}[!]{gray} You do not have {yellow}$amount {green}{$item['full']}{gray}. Depositing {yellow}$inv{gray}.");
                $amount = $inv;
                $amount_str = $inv;
            }
        } else {
            $amount = $inv;
            $amount_str = $inv;
        }
        umc_echo("{yellow}[!]{gray} You have {yellow}$inv{gray} items in your inventory, depositing {yellow}$amount");

        // check if recipient has space
        $userlevel = umc_get_uuid_level($recipient_uuid);
        $allowed = $UMC_SETTING['depositbox_limit'][$userlevel];
        $remaining = umc_depositbox_checkspace($recipient_uuid, $userlevel);
        $count = $allowed - $remaining;
        // umc_echo("Group: $userlevel Allowed: $allowed Remaining $remaining");

        $sql = "SELECT * FROM minecraft_iconomy.deposit
            WHERE item_name='{$item['item_name']}' AND recipient_uuid='$recipient_uuid'
            AND damage='$data' AND meta='$meta' AND sender_uuid='$uuid';";
        $D = umc_mysql_fetch_all($sql);

        // create the seen entry so we do not do this again
        $seen[$item['full']] = 1;

        // check first if item already is being sold
        if (count($D) > 0) {
            $row = $D[0];
            umc_echo("{green}[+]{gray} You already have {$item['full']}{gray} in the deposit for {gold}$recipient{gray}, adding {yellow}$amount{gray}.");
            $sql = "UPDATE minecraft_iconomy.`deposit` SET `amount`=amount+'$amount' WHERE `id`={$row['id']} LIMIT 1;";
        } else {
            //check if recipient has space
            if ($count >= $allowed && $player != 'uncovery' && $recipient != 'lot_reset') {
                if(!$sent_out_of_space_msg) {
                    umc_echo("{red}[!] {gold}$recipient{gray} does not have any more deposit spaces left "
                        . "(Used {white}$count of $allowed{gray} available for group {white}$userlevel{gray})!");
                    $sent_out_of_space_msg = 1;
                }
                continue;
            }
            // check if recipient is an active user
            $target_active = umc_user_countlots($recipient);
            if ($target_active == 0 && $recipient != 'lot_reset') {
                umc_error("{red}[!] {gold}$recipient{gray} is not an active user, so you cannot deposit items for them!");
            }
            // create a new deposit box

            if (strlen($item['item_name']) < 3) {
                XMPP_ERROR_trigger("Error depositing, item name too short!");
                umc_error("There was an error with the deposit. Please send a ticket to the admin so this can be fixed.");
            }
            umc_echo("{green}[+]{gray} Depositing {yellow}$amount_str {$item['full']}{gray} for {gold}$recipient");
            $sql = "INSERT INTO minecraft_iconomy.`deposit` (`damage` ,`sender_uuid` ,`item_name` ,`recipient_uuid` ,`amount` ,`meta`)
                    VALUES ('$data', '$uuid', '{$item['item_name']}', '$recipient_uuid', '$amount', '$meta');";
            $count++;
            umc_log("Deposit","do_deposit", $sql);
        }
        umc_mysql_query($sql, true);
        umc_clear_inv($item['item_name'], $data, $amount, $meta);
    }
    if($recipient == 'lot_reset') {
        $allowed = 'unlimited';
    }
    umc_echo("{green}[+]{gray} You have now used {white}$count of $allowed{gray} deposit boxes");
}

/**
 * Check how much space someone has left in their deposit, depending on their user level
 *
 * @global array $UMC_SETTING
 * @param type $uuid
 * @param type $userlevel
 * @return type
 */
function umc_depositbox_checkspace($uuid, $userlevel = false) {
    global $UMC_SETTING;

    $sql = "SELECT * FROM minecraft_iconomy.deposit WHERE recipient_uuid='$uuid';";
    $data = umc_mysql_fetch_all($sql);
    $count = count($data);
    if (!$userlevel) {
        $userlevel = umc_get_uuid_level($uuid);
    }
    $allowed = $UMC_SETTING['depositbox_limit'][$userlevel];
    $remaining = $allowed - $count;
    return $remaining;
}


function umc_depositbox_consolidate() {
    global $UMC_USER;
    $uuid = $UMC_USER['uuid'];
    // find all doupliecate entries
    $sql_doubles = " SELECT count(id) AS counter, item_name, damage, meta
        FROM minecraft_iconomy.deposit
        WHERE recipient_uuid='$uuid'
        GROUP BY item_name, damage, meta HAVING COUNT(id) > 1";
    $doubles = umc_mysql_fetch_all($sql_doubles);
    $source_boxes = count($doubles);
    $target_boxes = 0;
    if ($source_boxes > 0) {
        foreach ($doubles as $row) {
            // then we take each entry that is not created by the user and move it to a box created by the user
            $sql_fix = "SELECT * FROM minecraft_iconomy.deposit
                WHERE item_name='{$row['item_name']}'
		    AND damage='{$row['damage']}
		    AND meta='{$row['meta']}
		    AND recipient_uuid='$uuid'
		    AND sender_uuid !='$uuid';;";
            $fix_data = umc_mysql_fetch_all($sql_fix);
            if (count($fix_data) > 0) {
                $target_boxes++;
                foreach ($fix_data as $fix_row) {
                    umc_db_take_item('deposit', $fix_row['id'], $fix_row['amount'], $uuid);
                    umc_deposit_give_item($uuid, $fix_row['item_name'], $fix_row['damage'], $fix_row['meta'], $fix_row['amount'], $uuid);
                }
            }
        }
    }
    if($source_boxes > 0) {
        umc_echo("{green}[+]{gray} Found {yellow}$source_boxes{gray} items spread over several boxes consolidated them to $target_boxes deposit boxes!.");
    } else {
        umc_echo("{yellow}[?]{gray} Unable to consolidate depositbox, no compatible items found.");
    }
}
