<?php

global $UMC_SETTING, $WS_INIT;

$WS_INIT['money'] = array(  // the name of the plugin
    'disabled' => false,
    'events' => false,
    'default' => array(
        'help' => array(
            'title' => 'Money',  // give it a friendly title
            'short' => 'Simple Money features',  // a short description
            'long' => "See how much money you have, give some to other people etc.", // a long add-on to the short  description
        ),
    ),
    'status' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Check account status',
            'long' => "This commdn shows you how much money you have in your account",
        ),
        'function' => 'umc_money_status',
    ),
    'give' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Give money to someone',
            'long' => "Deduct money from your account and give it to someone else.",
            'args' => '<target> <Uncs>',
        ),
        'function' => 'umc_money_give',
    ),
);

function umc_money_status() {
    global $WSEND;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $player = $WSEND['player'];

    $balance = umc_money_check($player);
    umc_echo("Your bank account balance is $balance Uncs!");
}

function umc_money_give() {
    global $WSEND;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $player = $WSEND['player'];
    $args = $WSEND['args'];

    if (!isset($args[2])) {
        umc_error('You need to enter the user to give money to!');
    } else if (strtolower($args[2]) == strtolower($player)) {
        umc_error('You cannot give money to yourself!');
    }

    if (!isset($args[3])) {
        umc_error('You need to enter amount of money!');
    } else if ($args[3] <= 0) {
        umc_error('You will need to give more than that!');
    }

    $target = umc_sanitize_input($args[2], 'player');
    $sum = umc_sanitize_input($args[3], 'price');

    $check = umc_money($player, $target, $sum);
    if ($check) {
        umc_error('The transaction failed! Make sure that the target user exists');
    } else {
        umc_echo("You successfully transferred $sum Uncs to $target");
    }
    umc_msg_user($target, "You just received $sum Uncs from $player!");
    umc_log('money', 'give', "$player gave $target $sum Uncs");
    umc_money_status();
}

/**
 * Handles money transfers
 * UUID enabled
 *
 * @global type $UMC_USER
 * @param type $source
 * @param type $target
 * @param type $amount
 * @return boolean
 */
function umc_money($source = false, $target = false, $amount_raw = 0) {
    global $UMC_ENV;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    if ($source) {
        $source = umc_check_user($source);
    }

    if ($target) {
        $target = umc_check_user($target);
    }
    $amount = abs($amount_raw);

    if ($source) { // take from someone
        $source_uuid = umc_uuid_getone($source, 'uuid');
        $balance = umc_money_check($source);
        if ($balance > $amount) {
            $sql = "UPDATE `minecraft_iconomy`.`mineconomy_accounts`
                SET `balance`=`balance`-'$amount'
		WHERE `mineconomy_accounts`.`uuid` = '$source_uuid';";
            umc_mysql_query($sql);
        } else if ($UMC_ENV == 'websend') {
            umc_error("There is not enough money in the account! You need $amount but have only $balance Uncs.");
        }
    }

    if ($target) { // give to someone
        $target_uuid = umc_uuid_getone($target, 'uuid');
        $balance = umc_money_check($target);
        $sql = "UPDATE `minecraft_iconomy`.`mineconomy_accounts`
	    SET `balance` = `balance` + '$amount'
            WHERE `mineconomy_accounts`.`uuid` = '$target_uuid';";
        umc_mysql_query($sql);
    }
    // logging
    if (!$target) {$target = "System";}
    if (!$source) {$source = "System";}
    umc_log('money', 'transfer', "$amount was transferred from $source to $target");
}

/**
 * this checks if the user exists, and creats it if not. returns the amount in the account.
 * @global type $UMC_ENV
 * @param type $user
 * @return int
 */
function umc_money_check($user) {

    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    if ($user == '') {
        XMPP_ERROR_trigger("Failed to get account status for empty user!");
    }
    // check if the user has an acoount
    if (strlen($user) <= 17) {
        $uuid = umc_user2uuid($user);
    } else {
        $uuid = $user;
        $user = umc_user2uuid($uuid);
        if ($user == '') {
            XMPP_ERROR_trigger("Failed to get username for $uuid!");
            die();
        }
    }
    $sql = "SELECT balance FROM `minecraft_iconomy`.`mineconomy_accounts` WHERE uuid='$uuid';";
    $data = umc_mysql_fetch_all($sql);
    // has account to receive, return value
    if (count($data) > 0) { // get amount
        return $data[0]['balance'];
    } else if (count($data) == 0) { // create account
        // check if there is a user entry but no UUID
        $sql2 = "SELECT balance FROM `minecraft_iconomy`.`mineconomy_accounts` WHERE account='$user';";
        $data2 = umc_mysql_fetch_all($sql2);
        if (count($data2) == 1) {
            // yes, add the UUID
            $fix_sql = "UPDATE `minecraft_iconomy`.`mineconomy_accounts`
		 SET uuid='$uuid'
                 WHERE account='$user';";
            umc_mysql_query($fix_sql, true);
            // try again
            return umc_money_check($user);
        } else {
            // insert row
            $sql3 = "INSERT INTO `minecraft_iconomy`.`mineconomy_accounts` (`uuid`, `account`, `balance`)
                VALUES ('$uuid', '$user', '0');";
            umc_mysql_query($sql3, true);
            return 0;
        }
    }
}
