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
 * This plugin manages the user commands for the deposit boxes. This plugin is required
 * for the shop plugin and several others that push items into the deposit.
 * The only way to find out which functions use this is to do a search for the
 * below function names.
 *
 * ToDo: Rename the "umc_do... " into "umc_deposit_do..." functions throughout the system.
 */

global $UMC_SETTING, $WS_INIT, $UMC_USER;

$WS_INIT['depositbox'] = array(
    'disabled' => false,
    'events' => array('user_ban' => 'umc_deposit_wipe', 'user_delete' => 'umc_deposit_wipe'),
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
    'check' => array(
        'function' => 'umc_depositbox_check',
        'help' => array(
            'args' => '',
            'short' => 'Get details on deposit box purchases.',
            'long' => 'Displays detailed output relating to depositbox pricing, currently owned box counts and maximum ownable box counts.'
        ),
    ),
    'buy' => array(
        'function' => 'umc_depositbox_purchase',
        'help' => array(
            'args' => '',
            'short' => 'Purchase a deposit box',
            'long' => 'Purchase a deposit box that is used to virtually store goods.'
        ),
    ),
);

// settings array to hold maximum numbers of purchasable homes
// TODO - add a buy command
$UMC_SETTING['deposits']['max'] = array(
    'Guest' => 0,
    'Settler' => 5, 'SettlerDonator' => 5,
    'Citizen'=> 10, 'CitizenDonator'=> 10,
    'Architect' => 20, 'ArchitectDonator' => 20,
    'Designer' => 30, 'DesignerDonator' => 30,
    'Master' => 40, 'MasterDonator' => 40,
    'Elder' => 50, 'ElderDonator' => 50,
    'Owner' => 9000);

// original settings array holding group based depositbox limits
$UMC_SETTING['depositbox_limit'] = array(
    'Guest' => 0,
    'Settler' => 0, 'SettlerDonator' => 2,
    'Citizen'=> 1, 'CitizenDonator'=> 3,
    'Architect' => 2, 'ArchitectDonator' => 4,
    'Designer' => 3, 'DesignerDonator' => 5,
    'Master' => 4, 'MasterDonator' => 6,
    'Elder' => 5, 'ElderDonator' => 7,
    'Owner' => 40);

/**
 * attempt a purchase of the next depositbox
 *
 * @global type $UMC_USER
 * @global array $UMC_SETTING
 */
function umc_depositbox_purchase(){
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER, $UMC_SETTING;
    $player = $UMC_USER['username'];
    $uuid = $UMC_USER['uuid'];
    $userlevel = $UMC_USER['userlevel'];
    $purchased = umc_depositbox_realcount($uuid, 'total') - umc_depositbox_realcount($uuid, 'system');

    umc_echo("You have $purchased depositboxes.");
    $next = $purchased + 1;
    $max_deposits = $UMC_SETTING['deposits']['max'][$userlevel];
    $bank = umc_money_check($UMC_USER['uuid']);
    $cost = umc_depositbox_calc_costs($next);

    // check player is not box capped
    if ($next > $max_deposits) {
        umc_error("You already reached your maximum deposit count for your rank ($max_deposits)!");
    }

    // check if the user has the cash to afford their new home
    if ($bank < $cost) {
        umc_error("You do not have enough cash to buy another deposit box! You have only $bank Uncs. You need $cost Uncs.");
    }

    // transfer the money
    umc_money($uuid, false, $cost);

    // create blank reusable entries
    umc_depositbox_create($uuid);

    // create log of action taken
    $text = "$player bought deposit box $next for $cost uncs";
    umc_log('depositbox','purchase',$text);

    // provide user feedback
    umc_echo("{gold}[!] {green}You bought your next deposit box ($next) for $cost uncs!");
}

/**
 * debugging command to return data relating to depositbox usage.
 * to be simplified for final release once debugging completed.
 *
 * @global type $UMC_USER
 * @global array $UMC_SETTING
 */
function umc_depositbox_check() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER, $UMC_SETTING;

    $used = umc_depositbox_realcount($UMC_USER['uuid'], 'total');
    $purchased = $purchased = umc_depositbox_realcount($UMC_USER['uuid'], 'total') - umc_depositbox_realcount($UMC_USER['uuid'], 'system');
    $system = umc_depositbox_realcount($UMC_USER['uuid'], 'system');
    $empty = umc_depositbox_realcount($UMC_USER['uuid'], 'empty');
    $occupied = umc_depositbox_realcount($UMC_USER['uuid'], 'occupied');
    $cost = umc_depositbox_calc_costs($purchased + 1);

    $userlevel = $UMC_USER['userlevel'];
    $max_deposits = $UMC_SETTING['deposits']['max'][$userlevel];
    $bank = umc_money_check($UMC_USER['uuid']);

    // output the return values to the chat window
    umc_header("Checking Depositbox Status");
    umc_echo("You currently have $purchased owned boxes.");
    umc_echo("$empty entries are owned, empty boxes.");
    umc_echo("$occupied entries are owned and have contents inside.");
    umc_echo("$system entries are unowned, recieved from system users.");
    umc_echo("You are currently using $used deposit boxes.");
    umc_echo("Your maximum number of boxes available for purchase is $max_deposits.");
    umc_echo("The cost to purchase your next box is $cost Uncs.");
    umc_echo("You currently have $bank Uncs.");
    umc_footer();
}

/**
 * calculates the cost of deposit box number passed to purchase
 * TODO: Put the costs somehow into a config instead of hardcoding it?
 *
 * @param type $count
 * @return type
 */
function umc_depositbox_calc_costs($count) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $base = 10;
    $cost = pow($count, 3) * $base;
    return $cost;
}

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

    $web = false;
    if ($UMC_ENV == 'wordpress') {
        $web = true;
    }

    $sql = "SELECT *
        FROM minecraft_iconomy.deposit
        WHERE (sender_uuid='$uuid' OR recipient_uuid='$uuid')
            AND sender_uuid<>'reusable-0000-0000-0000-000000000000'
        ORDER BY item_name, damage, amount DESC;";
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
                $format_color = 'green';
                if ($item['nbt_raw']) { // magix items are aqua
                    $format_color = 'aqua';
                }
                $data = array( // down arrow in Unicode: [\u25BC]
                    array('text' => sprintf("%7d     ", $row['id']), 'format' => 'green'),
                    array('text' => $row['amount'], 'format' => 'yellow'),
                    array('text' => " " . $item['name'], 'format' => array($format_color, 'show_item' => array('item_name' => $item['item_name'], 'damage' => $item['type'], 'nbt' => $item['nbt_raw']))),
                    array('text' => "  [\u25BC]", 'format' => array('blue', 'run_command' => '/withdraw ' . $row['id'], 'show_text' => 'Withdraw all')),
                );
                if ($row['amount'] >= 64) {
                    $data[] = array('text' => "  [\u25BC64]", "format" => array('blue', 'run_command' => '/withdraw ' . $row['id'] . " 64", 'show_text' => 'Withdraw 64'));
                }
                if ($row['amount'] > 1) {
                    $data[] = array('text' => "  [\u25BC1]", "format" => array('blue', 'run_command' => '/withdraw ' . $row['id'] . " 1", 'show_text' => 'Withdraw one only'));
                }
                umc_text_format($data, false, false);
            }
        }
        if (!$web) {
            $allowed = umc_depositbox_realcount($uuid, 'total') - umc_depositbox_realcount($uuid, 'system');
            umc_pretty_bar("darkblue", "-", " {green}$count / $allowed slots used ");
            umc_echo("{cyan}[*] {green}Withdraw with {yellow}/withdraw <Depot-Id> {green} or click on {blue}[\u25BC]");
            umc_footer();
        } else {
            return $web_arr;
        }
    }
}

// withdraw
function umc_do_withdraw() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
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
        $id = strtolower($args[2]);
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
            $sql = "SELECT `id`, `item_name`, `amount`, `nbt`
                FROM minecraft_iconomy.deposit 
                WHERE sender_uuid='$sender_uuid'
                AND recipient_uuid='$uuid'";
            umc_log('deposit', 'withdraw', "$player tried to withdraw $amount from $sender");
        } else if ($id == 'all') { // withdrawing the whole deposit
            umc_echo("{green}[+]{gray} Withdrawing everything from your deposit...");
            $sql = "SELECT `id`, `item_name`, `nbt`, `amount`
                FROM minecraft_iconomy.deposit 
                WHERE recipient_uuid='$uuid'
                AND sender_uuid <> 'reusable-0000-0000-0000-000000000000';";
            umc_log('deposit', 'withdraw', "$player tried to withdraw all");
        } else { // by item name
            $find_item = umc_goods_get_text($id);
            //if (in_array($find_item['id'], $umc_unavailable)) {
            //    umc_echo("{red}This item is unavailable. Please check the wiki for the proper item!",true);
            //}
            // we need to stop here in case the $id cannot be identified
            if (!$find_item) {
                umc_error("There is nobody or item with that name to withdraw. Please check the manual");
            }
            $sql = "SELECT `id`, `item_name`, `amount`, `nbt` FROM minecraft_iconomy.deposit
                WHERE recipient_uuid='$uuid'
		AND item_name='{$find_item['item_name']}'";
            umc_log('deposit', 'withdraw', "$player tried to withdraw {$find_item['item_name']} {$find_item['nbt']}");
        }

        $D2 = umc_mysql_fetch_all($sql);
        if (count($D2) > 0) {
            $all_items = array();
            foreach ($D2 as $row) {
                $id = $row['id'];
                $nbt = $row['nbt'];
                $item_name = $row['item_name'];
                if ($amount == 'max') {
                    $this_amount = $row['amount'];
                } else if ($row['amount'] > $amount) {
                    $this_amount = $amount;
                    $amount = 0;
                } else {
                    $this_amount = $row['amount'];
                    $amount -= $this_amount;
                }
                $all_items[$id] = array('item_name' => $item_name, 'amount' => $this_amount, 'nbt' => $nbt);
            }
            umc_check_space_multiple($all_items);
            umc_log('deposit', 'withdraw', "$player is withdrawing $amount of $item_name");
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
 * Force an item into the deposit of the recipient, irrespective of empty boxes
 *
 * @param type $recipient
 * @param type $item_name
 * @param type $data
 * @param type $meta
 * @param type $amount
 * @param type $sender
 */
function umc_deposit_give_item($recipient, $item_name, $data, $meta, $amount, $sender) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_DATA_ID2NAME, $UMC_DATA;
    if (is_numeric($item_name)) {
        XMPP_ERROR_trigger('UMC_DATA_ID2NAME USAGE');
        $item_name = $UMC_DATA_ID2NAME[$item_name];
    }

    if (!isset($UMC_DATA[$item_name])) {
        XMPP_ERROR_trigger("Could not deposit item $item_name for user $recipient!");
    }

    if (is_array($meta) > 0) {
        $meta = serialize($meta);
    } else if (strpos($meta, "{") === 0) {
        $meta = $meta;
    } else {
        $meta = '';
    }
    $recipient_uuid = umc_uuid_getone($recipient, 'uuid');
    $sender_uuid = umc_uuid_getone($sender, 'uuid');

    $meta_sql = umc_mysql_real_escape_string($meta);
    $sql = "SELECT * FROM minecraft_iconomy.deposit
        WHERE item_name='$item_name' AND recipient_uuid='$recipient_uuid'
        AND damage='$data' AND meta=$meta_sql AND sender_uuid='$sender_uuid';";
    $D = umc_mysql_fetch_all($sql);

        // check first if some of item from same source is already in deposit
    if (count($D) > 0) {
        $row = $D[0];
        $sql = "UPDATE minecraft_iconomy.`deposit` SET `amount`=amount+$amount WHERE `id`={$row['id']} LIMIT 1;";
    } else {
        // otherwise create a new deposit box
        $meta_sql = umc_mysql_real_escape_string($meta);
        $sql = "INSERT INTO minecraft_iconomy.`deposit` (`damage` ,`sender_uuid` ,`item_name` ,`recipient_uuid` ,`amount` ,`meta`)
            VALUES ('$data', '$sender_uuid', '$item_name', '$recipient_uuid', '$amount', $meta_sql);";
    }
    umc_mysql_execute_query($sql);
}

/**
 * primary deposit gateway function
 *
 * @global type $UMC_USER
 * @global type $UMC_DATA
 * @param type $all
 */
function umc_do_deposit_internal($all = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER, $UMC_DATA;
    $player = $UMC_USER['username'];
    $uuid = $UMC_USER['uuid'];
    $args = $UMC_USER['args'];
    $all_inv = $UMC_USER['inv'];
    $allowed = umc_depositbox_realcount($uuid, 'total') - umc_depositbox_realcount($uuid, 'system');

    // if all not specified, get item in current slot
    if (!$all) {
        $item_slot = $UMC_USER['current_item'];
        if (!isset($all_inv[$item_slot])) {
            umc_error("{red}You need to hold the item you want to deposit! (current slot: $item_slot)");
        }
        $all_inv = array($item_slot => $all_inv[$item_slot]);
    }
    $seen = array();

    // iterate through whole inventory
    foreach ($all_inv as $slot) {
        $item_id = $slot['item_name'];

        // check for bugs
        if (!isset($UMC_DATA[$item_id])) {
            XMPP_ERROR_trigger("Invalid item deposit: $item_id!");
            $UMC_DATA[$item_id] = array('stack' => 64, 'avail' => true);
        }

        // deal with item metadata
        $data = $slot['data'];
        if ($slot['nbt']) {
            $meta = $slot['nbt'];
        } else if ($slot['meta']) {
            if (is_array($slot['meta'])) {
                $meta = serialize($slot['meta']); // enchanted stuff
            } else {
                $meta = $slot['meta']; // we have NBT data
            }
        } else {
            $meta = false;
        }

        // don't assign the same item twice
        $item = umc_goods_get_text($slot['item_name'], $slot['data'], $meta);
        if (isset($seen[$item['full']])) {
            continue;
        }

        if ($item['notrade']) {
            umc_error("Sorry, this item is not enabled for deposit (yet).");
        }

        // check for more bugs
        $inv = umc_check_inventory($slot['item_name'], $slot['data'], $meta);
        if ($inv == 0) {
            XMPP_ERROR_trigger("Item held could not be found in inventory: {$slot['item_name']}, {$slot['data']}, " . var_export($meta, true));
            umc_error("There was a system error. The admin has been notified. Deposit aborted.");
        }

        // decide who the reciever is
        if (isset($args[2]) && $args[2]) {
            $recipient = umc_sanitize_input($args[2], 'player');
            if ($recipient == 'uncovery') {
                umc_error("Thanks for your generosity, but Uncovery does not need that!");
            }
            $recipient_uuid = umc_user2uuid($recipient);
        } else {
            $recipient = $player;
            $recipient_uuid = $uuid;
            if (!$all) {
                umc_echo("{yellow}[!]{gray} No recipient given. Depositing for {gold}$player");
            }
        }

        // check for quantity argument
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

        // retrieve the data from the db
        $meta_sql = umc_mysql_real_escape_string($meta);
        $sql = "SELECT * FROM minecraft_iconomy.deposit
            WHERE item_name='{$item['item_name']}' AND recipient_uuid='$recipient_uuid'
            AND damage='$data' AND meta=$meta_sql AND sender_uuid='$uuid';";
        $D = umc_mysql_fetch_all($sql);

        // create the seen entry so we do not do this again
        $seen[$item['full']] = 1;

        // get amount of empty deposit boxes reciever has
        $emptyboxes = umc_depositbox_realcount($recipient_uuid, 'empty');

        // check first if item already is being sold
        if (count($D) > 0) {
            $row = $D[0];
            umc_echo("{green}[+]{gray} You already have {$item['full']}{gray} in the deposit for {gold}$recipient{gray}, adding {yellow}$amount{gray}.");
            $sql = "UPDATE minecraft_iconomy.`deposit` SET `amount`=amount+'$amount' WHERE `id`={$row['id']} LIMIT 1;";
            umc_mysql_query($sql, true);
        } else {
            //check if recipient has space
            if ($emptyboxes < 1) {
                umc_error("{red}[!] {gold}$recipient{gray} does not have any more deposit spaces left");
            }

            // check if recipient is an active user
            $target_active = umc_user_countlots($recipient);
            if ($target_active == 0 && $recipient != 'lot_reset') {
                umc_error("{red}[!] {gold}$recipient{gray} is not an active user (has no lots), so you cannot deposit items for them!");
            }

            // catch more bugs
            if (strlen($item['item_name']) < 3) {
                XMPP_ERROR_trigger("Error depositing, item name too short!");
                umc_error("There was an error with the deposit. Please send a ticket to the admin so this can be fixed.");
            }

            // provide feedback
            $log_text = "{green}[+]{gray} Depositing {yellow}$amount_str {$item['full']}{gray} for {gold}$recipient";
            umc_echo($log_text);

            $sentFromSystem = umc_depositbox_system_UUID_check($uuid);

            if ($sentFromSystem){
                // if sender is a system sender, create a new deposit box
                // this will get cleaned up when emptied because the sender will be system id'd
                // we get the ID of the row we have to fill
                $box_id = umc_depositbox_create($recipient_uuid);
            } else {
                // if sender is not a system sender
                // "fill" an existing depositbox
                // select a single row to update from the database matching the user and having reusable sender
                $sql_recipient_uuid = umc_mysql_real_escape_string($recipient_uuid);
                $sql = "SELECT * FROM minecraft_iconomy.deposit
                    WHERE recipient_uuid = $sql_recipient_uuid
                    AND sender_uuid = 'reusable-0000-0000-0000-000000000000'
                    LIMIT 1;";
                $D = umc_mysql_fetch_all($sql);
                $box_id = $D[0]['id'];
            }

            // once selected, update the fields with the new data
            $sql_meta = umc_mysql_real_escape_string($meta);
            $sql_uuid = umc_mysql_real_escape_string($uuid);
            $sql_item_name = umc_mysql_real_escape_string($item['item_name']);
            $sql_line = "UPDATE minecraft_iconomy.deposit
                SET amount=$amount, sender_uuid=$sql_uuid, damage=$data, amount=$amount,
                meta=$sql_meta, item_name=$sql_item_name
                WHERE id=$box_id LIMIT 1;";
            umc_mysql_execute_query($sql_line, true);

            // log the outcome
            umc_log("Deposit","do_deposit", $log_text);
        }

        umc_clear_inv($item['item_name'], $data, $amount, $meta);
    }

    // get players occupied box count
    $count = umc_depositbox_realcount($uuid, 'occupied');
    
    umc_shop_transaction_record($uuid, $recipient_uuid, $amount, 0, $item['item_name'], $data, $meta);

    umc_echo("{green}[+]{gray} You have now used {white}$count of $allowed{gray} deposit boxes");
}

/**
 * ONLY ADDS a new deposit into the deposit database table for a specific owner to be used.
 * returns the ID of the box for further usage
 *
 * @param string $owner_uuid the UUID of the owner
 * @return int the row id of the created box
 */
function umc_depositbox_create($owner_uuid) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $owner_sql = umc_mysql_real_escape_string($owner_uuid);
    $sql = "INSERT INTO minecraft_iconomy.`deposit` (`damage` ,`sender_uuid` ,`item_name` ,`recipient_uuid` ,`amount` ,`meta`)
        VALUES (0, 'reusable-0000-0000-0000-000000000000', '', $owner_sql, 0, '');";
    umc_mysql_execute_query($sql);
    $id = umc_mysql_insert_id();
    return $id;
}

/**
 * Check how much space someone has left in their deposit
 *
 * @param type $uuid
 * @return type
 */
function umc_depositbox_checkspace($uuid) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $count = umc_depositbox_realcount($uuid, 'empty');
    return $count;
}

/**
 * takes a sender UUID value and returns true if it is a system UUID (ie shop, deposit etc)
 *
 * @param type $uuid
 * @return boolean
 */
function umc_depositbox_system_UUID_check($uuid) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    if (strpos($uuid, '-0000-0000-000000000000') == true ) {
        if ($uuid != 'reusable-0000-0000-0000-000000000000') {
                return true;
        }
    }

    return false;

}

/**
 * flexible depositbox counting routine.
 *
 * @param type $uuid
 * @param type $searchtype
 * 'empty' 'total' 'purchased' 'system' 'occupied'
 * @return int
 */
function umc_depositbox_realcount($uuid, $searchtype = 'total') {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    // fetch all entries targeting suplied uuid
    $sql_uuid = umc_mysql_real_escape_string($uuid);

    $base_sql = "SELECT count(id) as counter FROM minecraft_iconomy.deposit WHERE recipient_uuid=$sql_uuid ";
    $types_array = array(
        'empty' => "AND amount=0;",
        'system' => "AND sender_uuid LIKE '%-0000-0000-000000000000' AND sender_uuid <> 'reusable-0000-0000-0000-000000000000';",
        'occupied' => "AND amount>0;",
        'total' => ";",
    );

    $C = umc_mysql_fetch_all($base_sql . $types_array[$searchtype]);
    $count = $C[0]['counter'];
    return $count;
}

/**
 * consolidates existing deposit boxes with similar contents into a single entry
 *
 * @global type $UMC_USER
 */
function umc_depositbox_consolidate() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $uuid = $UMC_USER['uuid'];

    // find all duplicate entries
    $sql_doubles = " SELECT count(id) AS counter, item_name, damage, meta, sender_uuid
        FROM minecraft_iconomy.deposit
        WHERE recipient_uuid='$uuid'
        AND sender_uuid<>'reusable-0000-0000-0000-000000000000'
        GROUP BY item_name, damage, meta HAVING COUNT(id) > 1";

    $doubles = umc_mysql_fetch_all($sql_doubles);
    $source_boxes = count($doubles);
    $target_boxes = 0;

    // iterate returned set
    if ($source_boxes > 0) {
        foreach ($doubles as $row) {
            // then we take each entry that is not created by the user and move it to a box created by the user
            // existing entry must be made by user
            $meta_sql = umc_mysql_real_escape_string($row['meta']);
            $sql_fix = "SELECT * FROM minecraft_iconomy.deposit
                WHERE item_name='{$row['item_name']}'
		    AND damage='{$row['damage']}'
		    AND meta=$meta_sql
		    AND recipient_uuid='$uuid'
		    AND sender_uuid !='$uuid';";
            $fix_data = umc_mysql_fetch_all($sql_fix);

            if (count($fix_data) > 0) {

                // if sender is system, don't create a new box. do a checkspace instead
                if (umc_depositbox_system_UUID_check($row['sender_uuid'])) {
                    // check empty box count to ensure they have space
                    if(umc_depositbox_realcount($uuid, 'empty') <= 0) {
                        umc_error("{red}You have no free deposit slots to consolidate into. Free some space and try again.");
                    }
                }

                $target_boxes++;
                foreach ($fix_data as $fix_row) {
                    umc_db_take_item('deposit', $fix_row['id'], $fix_row['amount'], $uuid);
                    umc_deposit_give_item($uuid, $fix_row['item_name'], $fix_row['damage'], $fix_row['meta'], $fix_row['amount'], $uuid);
                }
            }
        }
    }

    // provide user feedback based on results
    if($source_boxes > 0) {
        umc_echo("{green}[+]{gray} Found {yellow}$source_boxes{gray} items spread over several boxes consolidated them to $target_boxes deposit boxes!.");
    } else {
        umc_echo("{yellow}[?]{gray} Unable to consolidate depositbox, no compatible items found.");
    }
}

/**
 * This function is run when a user gets deleted or banned.
 * We wipe all deposits to this user.
 *
 * @param type $uuid
 */
function umc_deposit_wipe($uuid) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $uuid_sql = umc_mysql_real_escape_string($uuid);
    $sql = "DELETE FROM minecraft_iconomy.deposit WHERE recipient_uuid='$uuid_sql'";
    umc_mysql_execute_query($sql);
}

/**
 * Deposit database schema for reference purposes
 */
/*
CREATE TABLE IF NOT EXISTS `deposit` (
  `id` int(11) NOT NULL,
  `sender_uuid` varchar(37) NOT NULL,
  `recipient_uuid` varchar(39) NOT NULL,
  `damage` int(11) DEFAULT NULL,
  `amount` int(11) NOT NULL,
  `meta` text NOT NULL,
  `item_name` varchar(125) NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
*/
