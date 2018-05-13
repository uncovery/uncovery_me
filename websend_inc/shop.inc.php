<?php

global $UMC_SETTING, $WS_INIT;

$WS_INIT['shop'] = array(
    'disabled' => false,
    'events' => array(
        'user_banned' => 'umc_shop_cleanout_olduser',
        'user_inactive' => 'umc_shop_cleanout_olduser',
        'user_directory' => 'umc_shop_usersdirectory',
    ),
    'default' => array(
        'help' => array(
            'title' => 'Virtual Shop',
            'short' => 'Buy and sell items to and from other players',
            'long' => 'You can post an offer or a request, and then other users can buy from or sell to you, respectively.',
        ),
    ),
    'list' => array(
        'function' => 'umc_shop_list',
        'help' => array(
            'args' => '{ro} [user]',
            'short' => 'List requests/offers from you or others',
            'long' => '{yellow}[user]{gray} is optional, assumes yourself if not given.'
            . '{cyan} * Examples:'
            . '{green}/list req{gray} => Show your own (req)uests.'
            . '{green}/list o uncovery{gray} => Show uncovery\'s (o)ffers.'
        ),
        'top' => true,
    ),
    'cancel' => array(
        'function' => 'umc_do_cancel',
        'help' => array(
            'args' => '{ro} <shop-id> [amount]',
            'short' => 'Cancel an offer for sale',
            'long' => '{gray}Use {green}/list{gray} to find the {yellow}<shop-id>{gray} first'
            . '{gray}Attempts to cancel entire amount if {yellow}[amount]{gray} not given.'
            . '{cyan} * Examples:'
            . '{green}/cancel r 1342{gray} => Cancel entire amount of (r)equest id 1342.'
            . '{green}/cancel 7232 100{gray} => Cancel 100 of (offer) 7232.'
        ),
        'top' => true,
        'security' => array(
            'worlds' => array('empire', 'kingdom', 'skylands', 'aether'),
         ),
    ),
    'find' => array(
        'function' => 'umc_do_find',
        'help' => array(
            'args' => '{ro} [item]',
            'short' => 'Find items in the shop',
            'long' => '{gray}Searches requests -or- offers. Accepts only ids or names.;{gray}Use {yellow}/search{gray} if you do not know the proper term.'
            . '{green}/find {gray} => {gray}Find by what you are holding in your hand'
            . '{green}/find {yellow}{ro} [item-id]{gray} => {gray}Find by item-id {purple}(see minecraft wiki)'
            . '{green}/find {yellow}{ro} [item-name]{gray} => {gray}Find by name'
            . '{green}/find {yellow}{ro} >[price]{gray} => {gray}Find items priced over [price]'
            . '{green}/find {yellow}{ro} <[price]{gray} => {gray}Find items prices under [price]'
            . '{green}/find {yellow}{ro} ench{gray} => {gray}Find all enchanted items'
            . '{green}/find {yellow}{ro} ench:name{gray} => {gray}Find all items with named enchantment'
            . '{green}/find {yellow}{ro} new{gray} => {gray}Find most recent requests/offers'
            . '{cyan} * Examples:'
            . '{green}/find request new{gray} => {gray}Find most recent requests.'
            . '{green}/find off diamond{gray} => {gray}Find listings offering diamond.'
            . '{green}/find goldhat ench{gray} => {gray}Find offers for enchanted goldhats.'
            . '{green}/find ench:loot{gray} => {gray}Find offers for any items with Looting.'
        ),
        'top' => true,
    ),
    'offer' => array(
        'function' => 'umc_do_offer',
        'help' => array(
            'args' => '[price] [amount]',
            'short' => 'Offer items in the shop',
            'long' => '{green}/offer{gray} => {white}Search your inventory for the item currently; in your hand, and sell all of them at existing price'
            . '{green}/offer {yellow}[price]{gray} => {white}As above, but specify a price.'
            . '{green}/offer {yellow}[price] [amount]{gray} => {white}Sell only [amount] in the shop.'
            . '{green}/offer {yellow}... silent ...{gray} => Putting "silent" anywhere will silence announcements.'
            . '{white}Example: {yellow} /offer 25 10 silent'
        ),
        'top' => true,
        'security' => array(
            'worlds' => array('empire', 'kingdom', 'skylands', 'aether'),
         ),
    ),
    'depotoffer' => array(
        'function' => 'umc_do_depotoffer',
        'help' => array(
            'args' => '[deposit-id] [price] [amount]',
            'short' => 'Offer items in the shop from deposit',
            'long' => '{green}/depotoffer {yellow}[deposit-id]{gray} => {white}Get items from your deposit by ID; in and sell all of them at existing price'
            . '{green}/depotoffer {yellow}[deposit-id] [price]{gray} => {white}As above, but specify a price.'
            . '{green}/depotoffer {yellow}[deposit-id] [price] [amount]{gray} => {white}Sell only [amount] in the shop.'
            . '{green}/depotoffer {yellow}... silent ...{gray} => Putting "silent" anywhere will silence announcements.'
            . '{white}Example: {yellow} /depotoffer 40441 25 10 silent'
        ),
        'top' => true,
    ),
    'buy' => array(
        'function' => 'umc_do_buy',
        'help' => array(
            'args' => '<shop-id> [amount]',
            'short' => 'Buy offered items',
            'long' => '{gray}Use {green}/find{gray} to find the {yellow}<shop-id>{gray} first {red}(NOT the Item ID)'
            . '{gray}The {yellow}<shop-id>{gray} is the offer number in the shop listing'
            . '{gray}If {yellow}[amount]{gray} is not given, attempts to buy {white}all{gray}.'
        ),
        'top' => true,
        'security' => array(
            'worlds' => array('empire', 'kingdom', 'skylands', 'aether'),
         ),
    ),
    'depotbuy' => array(
        'function' => 'umc_do_depotbuy',
        'help' => array(
            'args' => '<shop-id> [amount]',
            'short' => 'Buy offered items and place them in your deposit',
            'long' => '{gray}Use {green}/find{gray} to find the {yellow}<shop-id>{gray} first {red}(NOT the Item ID)'
            . '{gray}The {yellow}<shop-id>{gray} is the offer number in the shop listing'
            . '{gray}If {yellow}[amount]{gray} is not given, attempts to buy {white}all{gray}.'
        ),
        'top' => true,
    ),
    'request' => array(
        'function' => 'umc_do_request',
        'help' => array(
            'args' => '<item> <price> <amount> <item>',
            'short' => 'Request items in the shop',
            'long' => '{gray}You {red}must{gray} specify the item and specify the price, and the amount.'
            . '{white}PRICE{gray} comes before {white}AMOUNT{gray}! :)'
            . '{gray}Your funds will be deducted {white}immediately{gray}, you can {yellow}/cancel{gray} your'
            . '{gray}request to retrieve those funds before the request is fulfilled.'
            . '{gray}The item can be either numeric (id:type) or text',
        ),
        'top' => true,
    ),
    'sell' => array(
        'function' => 'umc_do_sell',
        'help' => array(
            'args' => '<shop-id> [amount]',
            'short' => 'Sell requested items',
            'long' => '{gray}You {red}must{gray} be holding some of the item in your hand.'
            . '{gray}If {yellow}[amount]{gray} is not given, attempts to fulfill as much as possible'
            . '{gray}from what you have in your current inventory.'
            . '{gray}Use {yellow}/find r new{gray}, for example, to find shop-ids of requests.'
        ),
        'top' => true,
        'security' => array(
            'worlds' => array('empire', 'kingdom', 'skylands', 'aether'),
         ),
    ),
    'depotsell' => array(
        'function' => 'umc_do_depotsell',
        'help' => array(
            'args' => '<shop-id> <deposit-id> [amount]',
            'short' => 'Sell requested items from your deposit',
            'long' => '{gray}You {red}must{gray} have the same item in your deposit as the request'
            . '{gray}If {yellow}[amount]{gray} is not given, attempts to fulfill as much as possible'
            . '{gray}from what you have in the deposit.'
            . '{gray}Use {yellow}/find r new{gray}, for example, to find shop-ids of requests.'
        ),
        'top' => true,
    ),
    'search' => array(
        'function' => 'umc_do_search',
        'help' => array(
            'args' => '<term> [page]',
            'short' => 'Search for item names matching <term>',
            'long' => '{gray}Use the results shown with other commands, such as {yellow}/find'
            . '{cyan}* Example:'
            . '{yellow}/search ore{gray} => Find item names containing the word "ore"'
            . '{yellow}/search pot 2{gray} => Returns page 2 of large subset results ie potions'
        ),
        'top' => true,
    ),
);

/**
 * Display the offers or requests of the current or a specific user
 *
 * @global type $UMC_USER
 * @param type $table
 */
function umc_shop_list() {
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $uuid = $UMC_USER['uuid'];
    $args = $UMC_USER['args'];

    // Check argument 1, request or offer
    $table = umc_sanitize_input($args, "table");

    // user wants list from a specific player
    if (isset($args[3])) {
        $player = umc_sanitize_input($args[3], 'player');
        $uuid = umc_user2uuid($player);
    }
    $sql = "SELECT * FROM minecraft_iconomy.$table WHERE uuid='$uuid' ORDER BY id, damage, amount DESC;";
    $D = umc_mysql_fetch_all($sql);
    $num_rows = count($D);
    if ($num_rows == 0) {
        if ($table == 'request') {
            umc_error("{gold}$player{white} has no current requests!");
        } else {
            umc_error("{gold}$player{white} has nothing in the shop!");
        }
    } else {
        $verb = 'selling';
        if ($table == 'request') {
            $verb = 'requesting';
        }
        umc_header();
        umc_echo("Shop-Id  {gold} $player{gray} is $verb");
        foreach ($D as $row) {
            $item = umc_goods_get_text($row["item_name"], $row["damage"], $row['meta']);
            if ($row['amount'] == -1) {
                $row['amount'] = 'inf.';
            }
            $format_color = 'green';
            if ($item['nbt_raw']) { // magix items are aqua
                $format_color = 'aqua';
            }
            $data = array(
                array('text' => sprintf("%7d     ", $row['id']), 'format' => 'green'),
                array('text' => $row['amount'], 'format' => 'yellow'),
                array('text' => sprintf("%7d $     ", $row['price']), 'format' => 'green'),
                array('text' => " " . $item['name'], 'format' => array($format_color, 'show_item' => array('item_name' => $item['item_name'], 'damage' => $item['type'], 'nbt' => $item['nbt_raw']))),
            );
            umc_text_format($data, false, false);
        }
        umc_pretty_bar("darkblue", "-", "{blue} $num_rows listing(s) ");
    }
}

/**
 * Cancel a specific request or offer
 * @global type $UMC_USER
 */
function umc_do_cancel() {
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $uuid = $UMC_USER['uuid'];
    $args = $UMC_USER['args'];

    // Check argument 1, request or offer
    $table = umc_sanitize_input($args, 'table');

    if (isset($args[3])) {
        $id = settype($args[3], 'int');
        if ($id > 0) {
            $id = $args[3];
        } else {
            umc_error("{red}You entered an invalid Shop Id. Please use {yellow}/list{red} and{yellow} /shophelp cancel");
        }
    } else {
        umc_error("{red}You need to enter a shop ID. Please use {yellow}/list{red} and{yellow} /shophelp cancel");
    }

    // Check argument 3, amount (if present)
    if (!isset($args[4])) {
        umc_echo("{yellow}[!]{gray} You did not enter an amount. Attempting to cancel all.");
        $amount = 'max';
    } else {
        $amount = umc_sanitize_input($args[4], "amount");
    }

    // Cancel offer
    if ($table == 'stock') {
        umc_checkout_goods($id, $amount, 'stock', true);
        umc_log('shop', 'cancel_offer', "$player cancelled ID $id amount $amount");
    } else { // Cancel request
        $sql = "SELECT * FROM minecraft_iconomy.request WHERE id='$id' LIMIT 1;";
        //umc_echo("{purple}$sql");
        $data = umc_mysql_fetch_all($sql);
        //umc_echo("{red}".mysql_error());

        if (count($data) != 1) {
            umc_error("{red}The shop-id {white}$id{red} could not be found. Please use {yellow}/list{red} and{yellow} /shophelp cancel");
        }
        $row = $data[0];
        //Set maximum amount if necessary
        if ($amount == 'max' || $amount > $row['amount']) {
            $amount = $row['amount'];
        }
        if ($row['uuid'] != $uuid) {
            //umc_echo("{$row['buyer']} != $player");
            umc_error("{red}Request ID $id does not belong to you. Unable to cancel. See {yellow}/list r");
        }

        if ($amount < $row['amount']) { // Update existing listing
            $sql = "UPDATE minecraft_iconomy.`request` SET `amount`=amount-'$amount' WHERE id={$row['id']} LIMIT 1;";
            $rst = umc_mysql_query($sql, true);
        } else { // Delete the listing entirely
            $sql = "DELETE FROM minecraft_iconomy.`request` WHERE id={$row['id']}";
            $rst = umc_mysql_query($sql, true);
        }

        $cost = $amount * $row['price'];
        umc_money(false, $player, $cost);
        umc_echo("{green}[+]{gray} Removed {yellow}$amount{gray} of your request with id {green}{$row['id']}");
        umc_echo("{green}[$]{gray} Your account has been credited {cyan}$cost{gray} Uncs");
        umc_log('shop', 'cancel_request', "$player cancelled ID {$row['id']} amount $amount and got $cost refunded");
    }
}

/**
 * Find an item in the database when you know the proper name
 *
 * @global type $UMC_USER
 * @global type $UMC_DATA_ID2NAME
 * @global type $ENCH_ITEMS
 */
function umc_do_find() {

    global $UMC_USER, $ENCH_ITEMS, $UMC_DATA_ID2NAME;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $args = $UMC_USER['args'];

    // Check argument 1, request or offer
    $table = umc_sanitize_input($args, 'table');
    $MAX_RESULTS = 50;
    $qualifier = '1';
    $sort = 'price asc';
    $find_item = null;
    $no_item_ok = false;
    $search_label = '';

    foreach (array_splice($args,3) as $arg) {

        if (is_null($arg)) {
            next;
        }

        // cast to lowercase so case doesn't remove results
        $arg = strtolower($arg);

        $match = array();

        switch ($arg) {

            // match specific enchantments being searched for
            case (preg_match('/ench:(.+)/',$arg, $match) ? $arg : false):
                $find_specific_ench = umc_sanitize_input($match[1], 'ench');
                $qualifier .= " AND meta LIKE '%$find_specific_ench%'";
                if (!isset($ENCH_ITEMS[$find_specific_ench])) {
                    umc_error("Sorry, the enchantment you are looking for ($find_specific_ench) does not exist;");
                }
                $search_label = "{purple}{$ENCH_ITEMS[$find_specific_ench]['name']}";
                break;

            // match broadly any enchanted result
            case ('ench'):
                $qualifier .= " AND meta IS NOT NULL AND meta != ''";
                $search_label = "{purple}Enchanted";
                break;

            // minimum price based search
            case (preg_match('/^>([0-9.]+)/',$arg, $match) ? $arg : false):
                $find_min_price = umc_sanitize_input($match[1],'price');
                $qualifier .= " AND price > $find_min_price";
                $search_label = "{gray}over {cyan}$find_min_price each";
                break;

            // maximum price based search
            case (preg_match('/^<([0-9.]+)/',$arg, $match) ? $arg : false):
                $find_max_price = umc_sanitize_input($match[1],'price');
                $qualifier .= " AND price < $find_max_price";
                $search_label = "{gray}under {cyan}$find_max_price each";
                break;

            // match only new results
            case ('new'):
                $sort = 'id desc';
                $search_label = ' {white}New';
                $no_item_ok = true;
                break;

            // match any result
            case ('any'):
                $no_item_ok = true;
                break;

            // catch all undefined args
            default:
                if (is_numeric($arg)) {
                    XMPP_ERROR_trigger('UMC_DATA_ID2NAME USAGE');
                    if (!isset($UMC_DATA_ID2NAME[$arg])) {
                        umc_error("Could not find this item");
                    }
                    $arg = $UMC_DATA_ID2NAME[$arg];
                }

                $find_item = umc_sanitize_input($arg, 'item');
                if (!$find_item) {
                    umc_error("Could not find this item");
                }
                $item = umc_goods_get_text($find_item['item_name'], $find_item['type']);
                //if ($UMC_ITEMS[$find_item['id']][0]['avail'] == false) {
                //    umc_error("{red}Sorry, this item (ID {$find_item['id']}) is unavailable in the game!",true);
                //}
                $qualifier .= " AND item_name='{$find_item['item_name']}' AND damage='{$find_item['type']}'";
                $search_label = $item['full'];
        }
    }

    if (!$no_item_ok && is_null($find_item)) {
        $item_slot = $UMC_USER['current_item'];
        if (!isset($UMC_USER['inv'][$item_slot])) {
            umc_error("{red}Please hold an item, or provide an item id or item name.");
        } else {
            $item_name = $UMC_USER['inv'][$item_slot]['item_name'];
            if ((isset($UMC_USER['inv'][$item_slot]['data'])) && ($UMC_USER['inv'][$item_slot]['data'] != 0)) {
                $data = $UMC_USER['inv'][$item_slot]['data'];
            } else {
                $data = 0;
            }
            $item = umc_goods_get_text($item_name, $data);
            if (!$item) {
                XMPP_ERROR_trigger("ERROR Shop find on current item: $item_name");
                umc_error("There was an error identifying the item. Please submit a ticket so this can be fixed.");
            }
            $qualifier .= " AND item_name='{$item['item_name']}' AND damage='{$item['type']}'";
            $search_label = "{green}{$item['full']}";
        }
    }

    $label = "Offers";
    if ($table == 'request') {
        $label = 'Requests';
    }
    umc_header("{cyan}$label: $search_label", true);
    $playerLabel = 'Seller';
    if ($table == 'request') {
        $playerLabel = 'Buyer';
    }
    umc_echo("{gray}Shop-Id @ Price : Amount from $playerLabel", true);
    $sql = "SELECT * FROM minecraft_iconomy.$table WHERE $qualifier ORDER BY $sort limit $MAX_RESULTS";
    XMPP_ERROR_trace('sql', $sql);
    $rst = umc_mysql_query($sql);
    $i = 0;
    $results = array();
    // get overprized items to filter them out
    while ($row_sql = umc_mysql_fetch_array($rst)) {
        array_push($results, $row_sql);
    }

    foreach (array_reverse($results) as $row) {
        $item = umc_goods_get_text($row['item_name'], $row['damage'], $row['meta']);
        $item_text = $item['full'];
        $i++;
        if ($row['amount'] == -1) {
            $amount = 'unlimited';
        } else {
            $amount = number_format($row['amount'], 0, ',', "'");
        }
        $username = umc_user2uuid($row['uuid']);
        $line = sprintf("{green}%6d", $row['id'])
            . " {gray}@ {cyan}{$row['price']} {gray}: {yellow}$amount {gray}$item_text {gray}from {gold}$username";
        umc_echo($line, true);
    }
    umc_pretty_bar("darkblue", "-", "{blue} $i listing(s) {darkgray}[Max $MAX_RESULTS]", 49, true);
}

// offer
function umc_do_offer() {
    umc_do_offer_internal(false);
}

function umc_do_depotoffer() {
   umc_do_offer_internal(true);
}

/**
 * place an item into the stock database
 *
 * @global type $UMC_USER
 * @param type $deposit
 */
function umc_do_offer_internal($deposit) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $uuid = $UMC_USER['uuid'];
    $userlevel = $UMC_USER['userlevel'];
    $args = $UMC_USER['args'];

    // we check first if the deposit is empty before we allow trading
    $remaining = umc_depositbox_checkspace($uuid, $userlevel);
    if ($remaining < 0) {
        umc_error("You cannot trade items if your deposit is overfilled! Use /depotlist and /withdraw to empty your deposit.");
    }

    $item_slot = $UMC_USER['current_item'];
    $inv = $UMC_USER['inv'];

    if ($deposit) {
        if (!isset($args[2])) {
            umc_error("{red}You did not specify a valid deposit ID. {white}Type {yellow}/shop{white} for help.");
        }
	$id = $args[2];
	array_splice($args, 2, 1);

        if (is_numeric($id)) {
            $sql = "SELECT * from minecraft_iconomy.deposit WHERE recipient_uuid='$uuid' and id='$id'";
            $dep_data = umc_mysql_fetch_all($sql);
            if (count($dep_data) < 1) {
                    umc_error("You have no such deposit ID");
            }
        } else {
            umc_error("{red}You did not specify a valid deposit ID. {white}Type {yellow}/shop{white} for help.");
        }

   	$row = $dep_data[0];
	$item_name = $row['item_name'];
	$item_type = $row['damage'];
	$depot_id = $row['id'];
        $meta = $row['meta'];
	$inv_amount = $row['amount'];
    } else {
	if (!isset($inv[$item_slot])) {
	    umc_error("{red}You need to hold the item you want to offer!");
	}
	$item_name = $inv[$item_slot]['item_name'];
	$item_type = $inv[$item_slot]['data'];
        if (strpos($inv[$item_slot]['nbt'], "{") === 0) { //we have nbt
            $meta = $inv[$item_slot]['nbt'];
        } else if ($inv[$item_slot]['meta']) { // we do not want "false" to be serialized
            $meta = serialize($inv[$item_slot]['meta']);
        } else {
            $meta = '';
        }
	if (!is_numeric($item_type)) {
	    $item_type = 0;
	}
	$inv_amount = umc_check_inventory($item_name, $item_type, $meta);
    }

    if ($inv_amount == 0) {
        XMPP_ERROR_trigger("Failed to count items in inventory for $item_name, $item_type, $meta");
        umc_error("An error occured. The Admin has been notified. Process cancelled");
    }
    $item = umc_goods_get_text($item_name, $item_type, $meta);
    umc_echo("You have $inv_amount {$item['full']} in your inventory ");
    $do_check = false;
    $pos_check = array_search('check', $args);
    if ($pos_check) {
        $do_check = true;
        array_splice($args, $pos_check, 1);
    }

    $silent = false;
    $pos_silent = array_search('silent', $args);
    if ($pos_silent) {
        $silent = true;
        array_splice($args, $pos_silent, 1);
    }

    // this should always return only one row
    $meta_sql = umc_mysql_real_escape_string($meta);
    $sql = "SELECT * FROM minecraft_iconomy.stock
        WHERE item_name='{$item['item_name']}'
        AND damage='$item_type'
        AND meta=$meta_sql
        AND uuid='$uuid';";
    $sql_data = umc_mysql_fetch_all($sql);
    if (count($sql_data) > 1) {
        XMPP_ERROR_trigger("User $player has more than 1 offer for the same item ({$item['full']}) (SQL: $sql");
        $row = $sql_data[0];
    } else if (count($sql_data) == 0) {
        $row = false; // no offer yet
    } else {
        $row = $sql_data[0];
    }

    // sell item at same price, check if exists
    if (!isset($args[2])) {
        if ($row) {
            $price = $row['price'];
        } else {
            umc_error("{red}Since you do not have the same item already in the shop you need to specify a price.");
        }
    } else {
        $price = umc_sanitize_input($args[2], 'price');
    }

    // check for price excesses
    $excess_price = $price / 100;
    $meta_sql = umc_mysql_real_escape_string($meta);
    $sql_pcheck = "SELECT * FROM minecraft_iconomy.stock
        WHERE item_name='{$item['item_name']}'
	    AND damage='$item_type'
	    AND meta=$meta_sql
	    AND price<'$excess_price';";
    $D3 = umc_mysql_fetch_all($sql_pcheck);
    $excess_count = count($D3);

    // sell item at same price, check if exists
    if ($excess_count > 0) {
        umc_error("{red}Your price is too expensive! There are people offering the exact same item at 1/100th of yours!");
    }

    // check if an argument was given for amount.
    if (isset($args[3])) {
        $amount = umc_sanitize_input($args[3], 'amount');
        $amount_str = $amount;
        if ($amount > $inv_amount) {
            umc_error("{red}You don't have that many, you only have {yellow}$inv_amount{red}. "
                . "Specify less than this much or don't specify a number to sell all.");
        }
    } else {
        $amount = $inv_amount;
        $amount_str = $inv_amount;
    }

    $sum = 0;
    if (!$do_check) {
        $posted_id = 0;
        if ($row) { // Update existing listing
            $sum = $amount + $row['amount'];
            umc_echo("{green}[+]{gray}You already had {$row['amount']} {$item['full']} in the shop. Adding $amount to ID {$row['id']}");
            $sql = "UPDATE minecraft_iconomy.`stock` SET `amount` = amount + '$amount', price='$price' WHERE `stock`.`id`={$row['id']} LIMIT 1;";
            $rst = umc_mysql_query($sql, true);
            $posted_id = $row['id'];
        } else { // Create a new listing.
            $sum = $amount;
            $meta_sql = umc_mysql_real_escape_string($meta);
            $sql = "INSERT INTO minecraft_iconomy.`stock` (`id` ,`damage` ,`uuid` ,`item_name` ,`price` ,`amount` ,`meta`)
                VALUES (NULL , '$item_type', '$uuid', '{$item['item_name']}', '$price', '$amount', $meta_sql);";
            if (strlen($item['item_name']) < 3) {
                XMPP_ERROR_trigger("Error posting offer, $sql");
                umc_error("There was an error, please send a ticket with the details so it can be fixed");
            }

            $rst = umc_mysql_query($sql);
            $posted_id = umc_mysql_insert_id();
        }
	if ($deposit) {
            $check = umc_db_take_item('deposit', $depot_id, $amount, $uuid);
            umc_log('deposit', 'deposit_to_shop', "removing $amount from $player deposit slot $depot_id");
            umc_echo("Taking $amount from depot $depot_id to shop");
	} else {
            $check = umc_clear_inv($item['item_name'], $item_type, $amount, $meta);
            umc_log('deposit', 'inv_to_shop', "removing $amount of {$item['full_clean']} from $player");
	}
        umc_echo("{green}[+]{gray} You now have {yellow}$sum {$item['full']}{gray} in the shop (ID: $posted_id).");
        if (!$silent) {
            // calculate total listing value for hovertext
            $listing_value = $sum * $price;
            // compose raw JSON message with @a selector (all online players)
            // TODO: this should be changed to use umc_text_format()
            $cmd = 'tellraw @a ['
                . '{"text":"[!] ' . $player . ' offers ","color":"gold"},'
                . '{"text":"' . $sum . ' ' . $item['full_nocolor'] .  ' @ ' . $price . '/pc! ",'
                .     '"hoverEvent":{"action":"show_text","value":"Listing value ' . $listing_value . '"}},'
                . '{"text":"ID:' . $posted_id . '","color":"green",'
                .     '"clickEvent":{"action":"suggest_command","value":"/buy ' . $posted_id . ' ' . $sum . '"},'
                .     '"hoverEvent":{"action":"show_text","value":"Click to prefill buy command"}}'
                . ']';
            // issue the command
            umc_ws_cmd($cmd, 'asConsole');
        }
    } else {
        if ($row) {
            $sum = $amount + $row['amount'];
            umc_echo("{white}[?]{gray} This would update your existing offering to "
                    . "{yellow}$sum  {$item['full']}{darkgray} @ {cyan}{$price}{gray} each.");
        } else {
            umc_echo("{white}[?]{gray} This would create a new offering for "
                    . "{yellow}$amount {$item['full']}{darkgray} @ {cyan}{$price}{gray} each.");
        }
    }
}

// buy
function umc_do_buy() {
    umc_do_buy_internal(false);
}

function umc_do_depotbuy() {
    umc_do_buy_internal(true);
}

/**
 * Buy items
 *
 * @global type $UMC_USER
 * @param type $to_deposit
 * @return type
 */
function umc_do_buy_internal($to_deposit = false) {
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $args = $UMC_USER['args'];
    $uuid = $UMC_USER['uuid'];
    $userlevel = $UMC_USER['userlevel'];

    // we check first if the deposit is empty before we allow trading
    $remaining = umc_depositbox_checkspace($uuid, $userlevel);
    $minimum = 0;
    if($to_deposit) {
	$minimum = 1;
    }
    if ($remaining < $minimum) {
        umc_log("shop", "cancel_sale", "sale cancelled, inventory full");
        umc_error("You cannot trade items if your deposit is overfilled!");
    }

    $do_check = false;
    $pos = array_search('check', $args);
    if ($pos) {
        $do_check = true;
        array_splice($args, $pos, 1);
    }

    if (!isset($args[2])) {
        umc_show_help();
        return;
    } elseif (!is_numeric($args[2])) {
        umc_error("{red}Invalid shop_id: '{yellow}{$args[2]}{red}'. Did you mean '{yellow}/find {$args[2]}{red}'?");
    }
    $id = $args[2];
    if (!isset($args[3])) {
        umc_echo("{yellow}[!]{gray} You did not enter an amount. Attempting to buy all.");
        $amount = false;
    }
    $amount = umc_sanitize_input($args[3], 'amount');

    $sql = "SELECT * FROM minecraft_iconomy.stock WHERE id='$id' LIMIT 1;";
    $data_row = umc_mysql_fetch_all($sql);
    if (count($data_row) == 0) {
        umc_error("Sorry, there is no shop ID $id! Please try again.");
    }
    $row = $data_row[0];
    $seller = umc_user2uuid($row['uuid']);
    $item = umc_goods_get_text($row['item_name'], $row['damage'], $row['meta']);

    if ($row) {
        if (!$amount && ($row['amount'] == -1)) {
            umc_error("{red}That item has an unlimited supply. Please enter an amount!");
        }

        if ($row['uuid'] == $uuid && $player != 'uncovery') {
            umc_error("{red}You cannot buy your own goods. Use {yellow}/cancel <id>{red} instead!");
        }
        if (!$amount || ($amount > $row['amount'] && $row['amount'] != -1)) { // buy as much as possible
            $amount = $row['amount'];
        }
        $sum = $amount * $row['price'];
        if ($do_check) {
            umc_echo("{white}[?] {gray}Buying {yellow}$amount {$item['full']}{gray} for {cyan}{$row['price']}{gray} each from {gold}$seller");
        } else {
            umc_echo("{green}[+] {gray}Buying {yellow}$amount {$item['full']}{gray} for {cyan}{$row['price']}{gray} each from {gold}$seller");
        }
        $balance = umc_money_check($uuid);
        if ($balance < $sum) {
            umc_echo("{red}[!]{gray} Insufficient funds ({white}$sum{gray} needed).;{purple}[?]{white} Why don't you vote for the server and try again?");
        } else {
            $new_balance = $balance - $sum;
            if ($do_check) {
                umc_echo("{white}[?]{gray} You would have {green}$new_balance Uncs{gray} remaining after spending {cyan}$sum",true);
                return;
            }
	    if (!$to_deposit) {
            	umc_check_space($amount, $item['item_name'], $item['type']);
	    }
            umc_echo("{green}[+]{gray} You have {green}$new_balance Uncs{gray} remaining after spending {cyan}$sum");
            // transfer money player1 > player2
            umc_money($uuid, $row['uuid'], $sum);
            $seller = umc_user2uuid($row['uuid']);

            umc_log('shop', 'buy', "$player bought from $seller/{$row['uuid']} $amount {$item['full_clean']} for $sum, money was tranferred");
            // give goods to player1
            $leftover = umc_checkout_goods($id, $amount, 'stock', false, $to_deposit);
            $msg = "$player bought $amount {$item['name']} for {$row['price']} Uncs/pcs (= $sum Uncs)! $leftover left in stock!";
            umc_mod_message($seller, $msg);
        }
    } else {
        umc_error("{red}The shop-id {white}$id{red} could not be found. Please use {yellow}/find{red} and{yellow} /shophelp buy");
    }
}

// search
function umc_do_search() {
    global $UMC_USER, $ITEM_SEARCH;
    $args = $UMC_USER['args'];
    $max = 50;

    // check for nonsense queries returning large sets
    if (!isset($args[2]) || strlen($args[2]) < 3) {
        umc_error("{red}You need at least 3 letters to search for!");
    }

    $term_raw = $args[2];
    $pageindex = 1;

    // check for multiple search terms, or if it is just a pagination index
    if (isset($args[3])) {
        if(!is_numeric($args[3])){
            umc_error("You can search only for one term such as '$term_raw', not for '$term_raw {$args[3]}'!");
        } else {
            $pageindex = ($args[3]);
        }
    }

    // cast to lowercase so case doesn't remove results
    $term = strtolower($term_raw);

    // chat formatting of results
    umc_header();
    umc_echo("{gray}Searching for {white}$term{gray}...");
    umc_echo("{green}Item name => {blue} Alias{grey},{blue}...");

    $finds = array();

    // populate array with found results
    foreach ($ITEM_SEARCH as $name => $data) {
        if ((strpos($name, $term, 0) !== false) || ($term == $data['item_name'])) { //find partial or full matches
            $finds[$data['item_name']][] = $name; // make sure we don't duplicate stuff by setting key as well
        }
    }

    // check for broadform too many results
    $len = count($finds);
    if ($len > $max) {
        umc_error("Too many results ($len)! Please see https://uncovery.me/server-access/shop-manager/?page=goods");
    }

    // return results, paginated if required
    foreach ($finds as $item_name => $data) {

        // if data has more than max subsets
        if (count($data) > $max) {

            // get number of pages of results
            $pagemax = ceil(count($data)/$max);

            // data validation checks
            if ($pageindex > $pagemax) {$pageindex = $pagemax;}
            if ($pageindex < 1) {$pageindex = 1;}

            // set the offset based on supplied page
            $offset = ($pageindex - 1) * $max;

            // return the subset array
            $subset = array_slice($data, $offset, $max);

            // output the subset
            $text = "{yellow}Page $pageindex of $pagemax :{blue}" . (implode("{gray}, {blue}", $subset));

        } else {
            $text =  implode("{gray}, {blue}", $data);
        }
        umc_echo("{green}$item_name {gray}=> {blue}" . $text);
    }

    umc_pretty_bar("darkblue", "-", "{blue} $len match(es) found");
}

function umc_do_sell() {
    umc_do_sell_internal(false);
}

function umc_do_depotsell() {
    umc_do_sell_internal(true);
}

function umc_do_sell_internal($from_deposit=false) {
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $args = $UMC_USER['args'];
    $uuid = $UMC_USER['uuid'];

    if (!isset($args[2]) || (!is_numeric($args[2]))) {
        umc_error("{red}You need a valid shop_id to sell to a requester. Use {yellow}/find request ...");
        umc_error("{red}Maybe you meant {yellow}/offer{red}, for posting an offer for sale in the shop.");
    }

    $do_check = false;
    $pos = array_search('check', $args);
    if ($pos) {
        $do_check = true;
        array_splice($args, $pos, 1);
    }

    $id = $args[2];
    $sql_request = "SELECT * FROM minecraft_iconomy.request WHERE id='$id' LIMIT 1;";
    $request_data = umc_mysql_fetch_all($sql_request);
    if (count($request_data) == 0) {
        umc_error("{red}The shop-id {white}$id{red} could not be found. Please use {yellow}/find{red} and{yellow} /shophelp sell");
    }
    $request = $request_data[0];
    $request_item = umc_goods_get_text($request['item_name'], $request['damage'], $request['meta']);

    if ($request['uuid'] == $uuid && $player != 'uncovery') {
        umc_error("{red}You cannot fulfill your own requests. Use {yellow}/cancel r <id>{red} instead!");
    }
    // do we sell items from deposit?
    if ($from_deposit) {
    	$depot_id = $args[3];
	array_splice($args, 3, 1);
	$sql = "SELECT * from minecraft_iconomy.deposit WHERE recipient_uuid='$uuid' and id='$depot_id'";
	$D = umc_mysql_fetch_all($sql);
	if (count($D) < 1) {
            umc_error("You have no such deposit ID");
	}
    	$depot_row = $D[0];
	$inv = $depot_row['amount'];
        $depot_item = umc_goods_get_text($depot_row['item_name'],  $depot_row['damage'],  $depot_row['meta']);
	if ($depot_item != $request_item) {
	    //umc_echo("{green}$type:$data {gray} => {red}".$check_inv[$item_slot]['id'].":".$check_inv[$item_slot]['data']);
	    umc_error("{red}The item in deposit-id {white}$depot_id{red} doesn't match request {white}$id{red}.");
	}
        if ($depot_item['notrade']) {
            umc_error("Sorry, this item is not able to be traded (yet).");
        }
    } else {
	$item_slot = $UMC_USER['current_item'];
	$check_inv = $UMC_USER['inv'];
	if (!isset($check_inv[$item_slot])) {
	    umc_error("{red}You need to hold the item you want to sell!");
	}
        $inv_item = umc_goods_get_text($check_inv[$item_slot]['item_name'], $check_inv[$item_slot]['data'], $check_inv[$item_slot]['meta']);
	if ($inv_item != $request_item) {
            //umc_error_longmsg("Sell failed");
	    //umc_echo("{green}$type:$data {gray} => {red}".$check_inv[$item_slot]['id'].":".$check_inv[$item_slot]['data']);
	    umc_error("{red}The item you're holding doesn't match request id {white}$id{red}.");
	}
        if ($inv_item['notrade']) {
            umc_error("Sorry, this item is not able to be traded (yet).");
        }
    }

    if (!isset($args[3])) {
        umc_echo("{yellow}[!]{gray} You did not enter an amount. Attempting to sell all.");
        $amount = false;
    } else {
        $args[3] = intval($args[3]);
        $amount = umc_sanitize_input($args[3], 'amount');
    }

    if (!$amount) { // sell as much as possible
        $amount = $request['amount'];
    }
    if ($amount > $request['amount']) {
        umc_error("{red}That's too much, the request is only for {yellow}{$request['amount']}{red} pieces.");
    }

    if(!$from_deposit) {
	$inv = umc_check_inventory($request_item['item_name'], $request_item['type'], $request_item['meta']);
	if ($inv == 0) {
	    umc_error("{red}You don't have any of the requested item.");
	}
    }
    if ($inv < $amount) {
	umc_echo("{yellow}[!]{gray} Not enough for entire request.  Selling as much as possible.");
	$amount = $inv;
    }
    $recipient_uuid = $request['uuid'];
    $recipient = umc_user2uuid($recipient_uuid);
    if ($do_check) {
        umc_echo("{white}[?]{gray} This would sell {yellow}$amount {$request_item['full']}{darkgray} for {cyan}{$request['price']}{gray} each to {gold}$recipient");
        $sum = $amount * $request['price'];
        umc_echo("{white}[?]{gray} Your account would be credited {cyan}$sum{gray} Uncs.");
        return;
    }
    umc_echo("{green}[+] {gray}Selling {yellow}$amount {$request_item['full']}{darkgray} for {cyan}{$request['price']}{gray} each to {gold}$recipient");
    if ($from_deposit) {
	umc_db_take_item('deposit', $depot_id, $amount, $uuid);
    } else {
	umc_clear_inv($request_item['item_name'], $request_item['type'], $amount, $request_item['meta']);
    }
    $sum = $amount * $request['price'];
    umc_money(false, $player, $sum);

    umc_echo("{green}[$]{gray} Your account has been credited {cyan}$sum{gray} Uncs.");

    $request_item_meta_sql = umc_mysql_real_escape_string($request_item['meta']);
    $sql = "SELECT * FROM minecraft_iconomy.deposit
        WHERE item_name='{$request_item['item_name']}'
	    AND recipient_uuid='{$request['uuid']}'
            AND damage='{$request_item['type']}'
	    AND meta=$request_item_meta_sql
	    AND sender_uuid='shop0000-0000-0000-0000-000000000000';";
    $D = umc_mysql_fetch_all($sql);

    // check first if item already is in the recipient's deposit
    if (count($D) > 0) {
        $update_row = $D[0];
        umc_echo("{green}[+]{gray} There is already {$request_item['full']}{gray} in the deposit for {gold}$recipient{gray}, adding {yellow}$amount{gray}.");
        $sql = "UPDATE minecraft_iconomy.`deposit` SET `amount`=amount+'$amount' WHERE `id`={$update_row['id']} LIMIT 1;";
    } else {
        // create a new deposit box
        umc_echo("{green}[+]{gray} Depositing {yellow} $amount {$request_item['full']}{gray} for {gold}$recipient");
        $meta_sql = umc_mysql_real_escape_string($request_item['meta']);
        $sql = "INSERT INTO minecraft_iconomy.`deposit` (`damage` ,`sender_uuid` ,`item_name` ,`recipient_uuid` ,`amount` ,`meta`)
            VALUES ('{$request_item['type']}', 'shop0000-0000-0000-0000-000000000000', '{$request_item['item_name']}', '$recipient_uuid', '$amount', $meta_sql);";
    }
    umc_mysql_query($sql, true);

    if ($amount < $request['amount']) { // Update existing listing
        $sql = "UPDATE minecraft_iconomy.`request` SET `amount`=amount-'$amount' WHERE id={$request['id']} LIMIT 1;";
        umc_mysql_query($sql, true);
        //umc_echo("{purple}".$sql);
        //umc_echo("{red}".mysql_error());
    } else { // Delete the listing entirely
        $sql = "DELETE FROM minecraft_iconomy.`request` WHERE id={$request['id']}";
        umc_mysql_query($sql, true);
        //umc_echo("{red}".mysql_error());
    }
    // record transaction
    umc_shop_transaction_record($uuid, $request['uuid'], $amount, $sum, $request_item['item_name'], $request_item['type'], $request_item['meta']);

    // message recipient
    $msg = "$player sold you $amount {$request_item['full']} per your request, check your /depotlist!";
    umc_mod_message($recipient, $msg);

    // record logfile
    umc_log('shop', 'sell_on_request', "$player sold $amount of {$request_item['full_clean']} to $recipient (ID: {$request['id']})");
}

/**
 * Create a request
 *
 * @global type $UMC_USER
 * @return type
 */
function umc_do_request() {
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $uuid = $UMC_USER['uuid'];
    $args = $UMC_USER['args'];

    // this returns a item_array already
    $item_check = umc_sanitize_input($args[2], 'item');
    if (!$item_check) {
        umc_error("{red}Unknown item ({yellow}$args[2]{red}). Try using {yellow}/search{red} to find names.");
    } else {
        $item = umc_goods_get_text($item_check['item_name'], $item_check['type']);
    }
    $item_name = $item['item_name'];
    $type = $item['type'];
    $meta = '';

    if ($item['notrade']) {
        umc_error("Sorry, this item is not able to be traded (yet).");
    }

    $do_check = false;
    $pos = array_search('check', $args);
    if ($pos) {
        $do_check = true;
        array_splice($args, $pos, 1);
    }

    // TODO: This should be checking for the item type instead of assuming 0
    //if ($UMC_ITEMS[$type][0]['avail'] == false) {
    //    umc_error("{red}Sorry, this item (ID $type) is unavailable in the game!",true);
    //}

    $meta_sql = umc_mysql_real_escape_string($meta);
    $sql = "SELECT * FROM minecraft_iconomy.request
        WHERE item_name='$item_name' AND damage='$type' AND meta=$meta_sql AND uuid='$uuid';";
    $sql_data = umc_mysql_fetch_all($sql);
    if (count($sql_data) == 0) {
        $row = false;
    } else {
        $row = $sql_data[0];
    }


    // check if the price was specified
    if (!isset($args[3])) {
        if ($row) {
            $price = $row['price'];
            umc_echo("You are requesting the same item already, adjusting amount only.");
        } else {
            umc_error("{red}Since you do not have the same item already in the shop you need to specify a price.");
        }
    } else {
        $price = umc_sanitize_input($args[3], 'price');
    }

    // check if the amount was specified
    $amount = umc_sanitize_input($args[4], 'amount');
    if (!$row && $amount == NULL) {
        // requesting 0 amount available is not possible
        umc_error("{red}You need to specify an amount, too!");
    } else if ($row && $amount == NULL) {
        umc_echo("No amount given, adjusting price for existing listing only.");
        $amount = 0;
        if ($price == $row['price']) {
            umc_error("Your request price & amount is the same as the current. Either change the price, or the amount!");
        }
    }

    $cost = $price * $amount;

    // if there is an existing row, recalculate price accordingly
    if ($row) {
        // give money back from the original request
        $current_price = $row['amount'] * $row['price']; // let's say 200
        // calculate how much this one + the old item amount would cost
        $new_price = ($amount + $row['amount']) * $price; // let's say 100, so refund 100
        // do the sum for the balance, can be negative
        $cost = $new_price - $current_price; // -100 = 100 - 200
    }

    $balance = umc_money_check($uuid);
    if ($balance < $cost) {
        umc_error("{red}[!]{gray} Insufficient funds ({white}$cost{gray} needed). "
            . "{purple}[?]{white} Why don't you vote for the server and try again?");
    }

    if ($do_check) {
        if ($row) {
            $sum = $amount + $row['amount'];
            umc_echo("{white}[?]{gray} This would update your existing request to "
                    . "{yellow}$sum {$item['full']}{darkgray} @ {cyan}{$price}{gray} each.");
        } else {
            umc_echo("{white}[?]{gray} This would create a new request for "
                    . "{yellow}$amount {$item['full']}{darkgray} @ {cyan}{$price}{gray} each.");
        }
        if ($cost > 0) { // negative balances are charges
            umc_echo("{white}[?]{white} Your account would be charged {cyan}$cost{gray} Uncs.");
        } else {
            umc_echo("{white}[?]{white} Your account would be credited {cyan}" . ($cost * -1) . "{gray} Uncs.");
        }
        return;
    }

    $sum = 0;
    $posted_id = 0;
    if ($row) { // Update existing listing
        $sum = $amount + $row['amount'];
        umc_echo("{green}[+]{gray} You already requested {yellow}"
                . "{$row['amount']} @ {$row['price']} {$item['full']}{gray}. New amount is {yellow}$amount{gray} @ price $price.");
        $sql = "UPDATE minecraft_iconomy.`request` SET `amount` = amount + '$amount', price='$price' WHERE id={$row['id']};";
        $rst = umc_mysql_query($sql);
        $posted_id = $row['id'];
    } else { // Create a new listing.
        $sum = $amount;
        umc_echo("{green}[+]{gray} You are now requesting {yellow}"
                . "$sum {$item['full']}{gray} in the shop.");
        $meta_sql = umc_mysql_real_escape_string($meta);
        $sql = "INSERT INTO minecraft_iconomy.`request` (`id` ,`damage` ,`uuid` ,`item_name` ,`price` ,`amount` ,`meta`)
                VALUES (NULL , '$type', '$uuid', '{$item['item_name']}', '$price', '$amount', $meta_sql);";
        //XMPP_ERROR_trigger($sql);
        $rst = umc_mysql_query($sql);
        $posted_id = umc_mysql_insert_id();
    }
    if ($cost > 0) { // positive balances are charges
        umc_echo("{yellow}[$]{white} Your account has been charged {cyan}$cost{gray} Uncs.");
        // take money from player. The money function convwerts to absolute values
        umc_money($player, false, $cost);
    } else {
        umc_echo("{green}[$]{white} Your account has been credited {cyan}" . ($cost * -1) . "{gray} Uncs.");
        // give money to player
        umc_money(false, $player, $cost);
    }


    $format_color = 'green';
    if ($item['nbt_raw']) { // magix items are aqua
        $format_color = 'aqua';
    }
    $data = array(
        array('text' => $player . " ", 'format' => 'gold'),
        array('text' => "requesting ", 'format' => 'red'),
        array('text' => $sum . " ", 'format' => 'yellow'),
        array('text' => $item['full_clean'] . " ", 'format' => $format_color),
        array('text' => " @ ", 'format' => 'dark_gray'),
        array('text' => $price, 'format' => 'aqua'),
        array('text' => " each", 'format' => 'gray'),
        array('text' => ", shop-id ", 'format' => 'dark_gray'),
        array('text' => $posted_id, 'format' => 'gray')
    );
    umc_text_format($data, false, false);

}

/**
 * move all items from the shop to deposit for a specific user
 * Normally only used when users are banned or inactive, on lot reset
 *
 * @param type $uuid
 */
function umc_shop_cleanout_olduser($uuid) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    umc_log('shop', 'reset', "cleaning shop for user $uuid");
    // delete all requests
    $requests_sql = "DELETE FROM minecraft_iconomy.request WHERE uuid='$uuid';";
    umc_mysql_query($requests_sql, true);

    // move all items from stock to deposit
    $sql = "SELECT * FROM minecraft_iconomy.stock WHERE uuid='$uuid';";
    $rst = umc_mysql_query($sql);
    while ($row = umc_mysql_fetch_array($rst)) {
        umc_checkout_goods($row['id'], 'max', 'stock', true, true, $uuid);
    }
    umc_log('user_manager', 'shop-cleanout', "$uuid had his items moved from stock & request to deposit");
}

function umc_shop_usersdirectory($data) {
    $uuid = $data['uuid'];
    $username = umc_uuid_getone($uuid, 'username');

    $O['Shop'] = "<p><strong>Purchase History:</strong></p>\nNote: Items with a ? indicate missing icons only.";

    $count_sql = "SELECT count(id) as counter
        FROM minecraft_iconomy.transactions
        LEFT JOIN minecraft_srvr.UUID ON seller_uuid=UUID
        WHERE buyer_uuid='$uuid' AND date > '0000-00-00 00:00:00' AND seller_uuid NOT LIKE '%-0000-000000000000' AND cost > 0 AND buyer_uuid <> seller_uuid AND username IS NOT NULL";
    $C = umc_mysql_fetch_all($count_sql);
    $recordcount = $C[0]['counter'];

    // cost/amount as '\$/PC',

    $current_page = filter_input(INPUT_GET, 'listpage' , FILTER_SANITIZE_NUMBER_INT);
    if (!$current_page) {
        $current_page = 1;
    }

    $page_length = 50;
    $gap = $page_length * ($current_page - 1);

    $sql = "SELECT date, CONCAT(item_name,'|', damage, '|', meta) AS item_name, amount, cost, username as seller
        FROM minecraft_iconomy.transactions
        LEFT JOIN minecraft_srvr.UUID ON seller_uuid=UUID
        WHERE buyer_uuid='$uuid' AND date > '0000-00-00 00:00:00' AND seller_uuid NOT LIKE '%-0000-000000000000' AND cost > 0 AND buyer_uuid <> seller_uuid AND username IS NOT NULL
        ORDER BY date DESC
        LIMIT $gap, $page_length;";
    $D = umc_mysql_fetch_all($sql);

    $pageinfo = array(
        'record_count' => $recordcount,
        'page_length' => $page_length,
        'current_page' => $current_page,
        'page_url' => "https://uncovery.me/server-features/users-2/?u=$username&listpage=%s#tab7",
    );

    $sort_column = '0, "desc"';
    $O['Shop'] .= umc_web_table('user_purchases', $sort_column, $D, '', array(), false, false, $pageinfo);

     $O['Shop'] .= "<p><strong>Sales History:</strong></p>\n";

    $sales_count_sql = "SELECT count(id) as counter
        FROM minecraft_iconomy.transactions
        LEFT JOIN minecraft_srvr.UUID ON buyer_uuid=UUID
        WHERE seller_uuid='$uuid' AND date > '0000-00-00 00:00:00' AND buyer_uuid NOT LIKE '%-0000-000000000000' AND cost > 0 AND buyer_uuid <> seller_uuid AND username IS NOT NULL";
    $CS = umc_mysql_fetch_all($sales_count_sql);
    $sales_recordcount = $CS[0]['counter'];

    // cost/amount as '\$/PC',

    $sales_current_page = filter_input(INPUT_GET, 'saleslistpage' , FILTER_SANITIZE_NUMBER_INT);
    if (!$sales_current_page) {
        $sales_current_page = 1;
    }

    $sales_page_length = 50;
    $sales_gap = $sales_page_length * ($sales_current_page - 1);

    $sales_sql = "SELECT date, CONCAT(item_name,'|', damage, '|', meta) AS item_name, amount, cost, username as buyer
        FROM minecraft_iconomy.transactions
        LEFT JOIN minecraft_srvr.UUID ON buyer_uuid=UUID
        WHERE seller_uuid='$uuid' AND date > '0000-00-00 00:00:00' AND buyer_uuid NOT LIKE '%-0000-000000000000' AND cost > 0 AND buyer_uuid <> seller_uuid AND username IS NOT NULL
        ORDER BY date DESC
        LIMIT $sales_gap, $sales_page_length;";
    $SD = umc_mysql_fetch_all($sales_sql);

    $sales_pageinfo = array(
        'record_count' => $sales_recordcount,
        'page_length' => $sales_page_length,
        'current_page' => $sales_current_page,
        'page_url' => "https://uncovery.me/server-features/users-2/?u=$username&saleslistpage=%s#tab7",
    );

    $O['Shop'] .= umc_web_table('user_sales', $sort_column, $SD, '', array(), false, false, $sales_pageinfo);



    return $O;
}



/**
function umc_display_shop() {
    global $umc_shop_queries, $UMC_USER, $UMC_DATA;
    umc_shop_define_queries();

    if (!$UMC_USER) {
        return "You need to be logged in!";
    }

    $query = isset($_GET['query']) ? $_GET['query'] : 'stock_summary';

    if(!isset($umc_shop_queries[$query])) {
        echo "Requested query unknown.";
        return;
    }

    $sql = $umc_shop_queries[$query];
    // echo "<h2>" . umc_pretty_name($query) . "</h2>\n";
    $drop_html = '';
    if ($query == 'stock_detail') {
        $sani_item  = filter_input(INPUT_GET, 'item', FILTER_SANITIZE_NUMBER_INT);
        if (isset($sani_item)) {
           $item = $sani_item;
        } else {
            $item = 4;
        }
        $sql .= " WHERE item=$item";

        $items_arr = array();
        foreach ($UMC_DATA as $id => $T) {
            if (count($T) > 1 && isset($T['group'])) {
                $item_name = umc_pretty_name($T['group']['name']);
            } else {
                $item_name = umc_pretty_name($T[0]['name']);
            }
            $items_arr[$id] = $item_name;
        }
        asort($items_arr);

        // build stock detail dropdown
        $drop_html = "<form><span>Select item:</span><input type=\"hidden\" name=\"query\" value=\"stock_detail\">"
            . "<select name=\"item\" onchange=\"this.form.submit()\" class='search_init'>\n";
        $selected = array();
        $selected[$sani_item] = " selected=\"selected\"";
        foreach ($items_arr as $id => $item_name) {
            $sel_str = '';
            if (isset($selected[$id])) {
                $sel_str = $selected[$id];
            }
            $drop_html .= "<option value=\"$id\"$sel_str>$item_name</option>\n";
        }
        $drop_html .= "</select></form>\n";
    }

    // Construct the list of links to different data views
    $links = array();
    foreach ($umc_shop_queries as $q => $x) {
        if ($query == $q) {
            array_push($links,"<strong>".umc_pretty_name($q)."</strong>");
        }
        else {
            array_push($links,"<a href='?query=$q'>".umc_pretty_name($q)."</a>");
        }
    }

    // Query our data
    $data_rst = mysql_query($sql);

    // Show date/time column toggle if this is the history view
    $toggle_date = '';
    if ($query == 'history') {
        $toggle_date = "<a style='font-size:80%' href='javascript:fnShowHide(6)'>Show/Hide Timestamps</a>";
    }

    $links_str = "<br>" . implode(' | ',$links);
    if ($query == 'stock_detail') {
        $sort_column = '0, "desc"';
    } elseif ($query == 'history') {
        $sort_column = '7, "desc"';
    } else {
        $sort_column = '0, "asc"';
    }
    echo $links_str;
    $check = umc_web_table('shopgeneric', $sort_column, $data_rst, "$toggle_date $drop_html", array('date'));
    if (!$check) {
        XMPP_ERROR_trigger("Error creating web_table with SQL $sql");
        echo "Error creating data table. Admin was notified, please wait until it is fixed";
    } else {
        echo $check;
    }
}



function umc_show_shop_table_html() {
    global $UMC_USER;

    if (!$UMC_USER) {
        return "You have to be logged in to see this";
    }

    $username = $UMC_USER['username'];
    $sql = "SELECT minecraft_iconomy.stock.id AS shop_id, concat(minecraft_iconomy.stock.item,'|', minecraft_iconomy.stock.damage, '|', minecraft_iconomy.stock.meta) AS item, "
        . "minecraft_iconomy.stock.amount AS quantity, minecraft_iconomy.stock.price, SUM(minecraft_iconomy.transactions.amount) as sold_amount, "
        . "SUM(minecraft_iconomy.transactions.amount * minecraft_iconomy.transactions.cost) as income, MAX(minecraft_iconomy.transactions.date) as latest_sale "
        . "FROM minecraft_iconomy.stock "
        . "LEFT JOIN minecraft_iconomy.transactions ON minecraft_iconomy.stock.seller = minecraft_iconomy.transactions.seller AND "
        . "minecraft_iconomy.stock.item_name=minecraft_iconomy.transactions.item_name AND "
        . "minecraft_iconomy.stock.price=minecraft_iconomy.transactions.cost AND "
        . "minecraft_iconomy.stock.damage=minecraft_iconomy.transactions.damage AND "
        . "minecraft_iconomy.stock.meta=minecraft_iconomy.transactions.meta "
        . "WHERE minecraft_iconomy.stock.seller ='$username' "
        . "GROUP BY minecraft_iconomy.stock.id, minecraft_iconomy.transactions.cost, minecraft_iconomy.transactions.damage, minecraft_iconomy.transactions.meta";
    // echo $sql;
    $data_rst = mysql_query($sql);

    $sort_column = '0, "desc"';
    $check = umc_web_table('shopstock', $sort_column, $data_rst);
    if (!$check) {
        XMPP_ERROR_trigger("Error creating web_table with SQL $sql");
        return "Error creating data table. Admin was notified, please wait until it is fixed";
    } else {
        return $check;
    }
}

function umc_show_request_table_html() {
    global $UMC_USER;

    if (!$UMC_USER) {
        return "You have to be logged in to see this";
    }

    $username = $UMC_USER['username'];
    $sql = "SELECT minecraft_iconomy.request.id AS shop_id, concat(minecraft_iconomy.request.item,'|', minecraft_iconomy.request.damage, '|', minecraft_iconomy.request.meta) AS item, "
        . "minecraft_iconomy.request.amount AS quantity, minecraft_iconomy.request.price, SUM(minecraft_iconomy.transactions.amount) as sold_amount, "
        . "SUM(minecraft_iconomy.transactions.amount * minecraft_iconomy.transactions.cost) as income, MAX(minecraft_iconomy.transactions.date) as latest_sale "
        . "FROM minecraft_iconomy.request "
        . "LEFT JOIN minecraft_iconomy.transactions ON minecraft_iconomy.request.buyer = minecraft_iconomy.transactions.buyer AND "
        . "minecraft_iconomy.request.item_name=minecraft_iconomy.transactions.item_name AND "
        . "minecraft_iconomy.request.price=minecraft_iconomy.transactions.cost AND "
        . "minecraft_iconomy.request.damage=minecraft_iconomy.transactions.damage AND minecraft_iconomy.request.meta=minecraft_iconomy.transactions.meta "
        . "WHERE minecraft_iconomy.request.buyer ='$username' "
        . "GROUP BY minecraft_iconomy.request.id, minecraft_iconomy.transactions.cost, minecraft_iconomy.transactions.damage, minecraft_iconomy.transactions.meta";
    $data_rst = mysql_query($sql);

    $sort_column = '0, "desc"';
    $check = umc_web_table('shoprequests', $sort_column, $data_rst);
    if (!$check) {
        XMPP_ERROR_trigger("Error creating web_table with SQL $sql");
        return "Error creating data table. Admin was notified, please wait until it is fixed";
    } else {
        return $check;
    }
}

function umc_show_sales_history_html() {
    global $UMC_USER;

    if (!$UMC_USER) {
        return "You have to be logged in to see this";
    }

    $username = $UMC_USER['username'];
    $sql = "SELECT id AS shop_id, concat(item,'|',damage, '|', meta) AS item, amount AS quantity, cost, buyer, seller, date "
        . "FROM minecraft_iconomy.transactions "
        . "WHERE (seller='$username' OR buyer='$username') AND buyer NOT IN ('cancel_deposit','cancel_item') AND seller NOT IN ('cancel_deposit','cancel_item') "
        . "ORDER BY date DESC "
        . "LIMIT 0, 500;";
    $data_rst = mysql_query($sql);

    $sort_column = '0, "desc"';
    $check = umc_web_table('shophistory', $sort_column, $data_rst, '');
    if (!$check) {
        XMPP_ERROR_trigger("Error creating web_table with SQL $sql");
        return "Error creating data table. Admin was notified, please wait until it is fixed";
    } else {
        return $check;
    }
}

function umc_shop_list_bad_prices($array = false) {
    $sql = "SELECT count(id) as qty, concat(item_name,'|',damage, '|', meta) AS item_code, item_name, damage, meta, max(price) as max_p, min(price) as min_p,  max(price) / min(price) as multiplier "
            . 'FROM minecraft_iconomy.stock '
            . 'GROUP BY damage, item_name, meta '
            . 'HAVING qty>1 AND multiplier > 500 '
            . 'ORDER BY multiplier DESC ';
    // echo $sql;
    $rst = umc_mysql_query($sql);
    $out = "<table>\n<tr><th>ID</th><th>Seller</th><th>Item</th><th>High Price</th><th>Reference price</th><th>Multiplier</th></tr>\n";
    $data = array();
    while ($row = umc_mysql_fetch_array($rst)) {
        $damage = $row['damage'];
        $item_name = $row['item_name'];
        $max = $row['max_p'];
        $min = $row['min_p'];
        $meta = $row['meta'];
        $multi = round($row['multiplier'], 0);
        $sub_sql = "SELECT * FROM minecraft_iconomy.stock WHERE damage = $damage AND item_name='$item_name' AND price=$max AND meta='$meta';";
        $sub_rst = mysql_query($sub_sql);
        while ($sub_row = mysql_fetch_array($sub_rst, MYSQL_ASSOC)) {
            $seller = $sub_row['uuid'];
            $id = $sub_row['id'];
            if ($array == 'by_player') {
                $data[$seller][] = $id;
            } else if ($array == 'by_id') {
                $data[$id] = $seller;
            } else {
                $item_text = umc_goods_get_text($item_name, $damage, $meta);
                $out .= "<tr><td>$id</td><td>$seller</td><td>{$item_text['full']}</td><td>$max</td><td>$min</td><td>$multi</td></tr>\n";
            }
        }
    }
    $out .= "</table>\n";
    if ($array) {
        return $data;
    } else {
        return $out;
    }
}


function umc_shop_define_queries() {

    global $umc_shop_queries;
    $umc_shop_queries = array(

    'stock_summary' => "
SELECT concat(item_name,'|0|') AS item,
       count(seller) AS sellers,
       min(price) AS min_price,
       max(price) AS max_price,
       sum(replace(amount,'-1','0')) AS quantity
FROM   minecraft_iconomy.stock
GROUP BY item
ORDER BY abs(item)
",

'stock_detail' => "
SELECT concat(item_name,'|',damage, '|', meta) AS item,
       seller,
       id AS shop_id,
       amount AS quantity,
       price
FROM   minecraft_iconomy.stock
",

'history' => "
SELECT buyer,
       amount AS quantity,
       concat(item_name,'|',damage, '|', meta) AS item,
       (cost/amount) AS price_each,
       cost AS total_price,
       seller,
       date
FROM minecraft_iconomy.transactions
WHERE seller NOT LIKE 'cancel_%'
  AND buyer  NOT LIKE 'cancel_%'
  AND seller != 'Lottery'
  AND buyer  != 'Lottery'
  AND seller != 'Exchange'
  AND buyer  != 'Exchange'
  AND seller != buyer
  ORDER BY abs(id) DESC
  LIMIT 1000
",

'item_history' => "
SELECT concat(item_name,'|',damage) AS item,
       count(id) AS num_transactions,
       sum(amount) AS total_quantity,
       sum(cost) AS total_price,
       (cost/amount) AS average_price
FROM minecraft_iconomy.transactions
WHERE seller NOT LIKE 'cancel_%'
  AND buyer  NOT LIKE 'cancel_%'
  AND seller != 'Lottery'
  AND buyer  != 'Lottery'
  AND seller != 'Exchange'
  AND buyer  != 'Exchange'
  AND seller != buyer
GROUP BY concat(item,':',damage)
ORDER BY abs(item), abs(damage)
",

'seller_history' => "
SELECT seller,
       count(*) as total_sales,
       sum(amount) as total_quantity,
       sum(cost) as total_price
FROM minecraft_iconomy.transactions
WHERE seller NOT LIKE 'cancel_%'
  AND buyer  NOT LIKE 'cancel_%'
  AND seller != 'Lottery'
  AND seller not like 'contest %'
  AND buyer  != 'Lottery'
  AND seller != 'Exchange'
  AND buyer  != 'Exchange'
  AND seller != buyer
GROUP BY seller
",

'buyer_history' => "
SELECT buyer,
       count(*) as total_purchases,
       sum(amount) as total_quantity,
       sum(cost) as total_price
FROM minecraft_iconomy.transactions
WHERE seller NOT LIKE 'cancel_%'
  AND buyer  NOT LIKE 'cancel_%'
  AND seller != 'Lottery'
  AND buyer  != 'Lottery'
  AND seller != 'Exchange'
  AND buyer  != 'Exchange'
  AND seller != buyer
GROUP BY buyer
"
);

}
 * */
