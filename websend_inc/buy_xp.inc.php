<?php

global $UMC_SETTING, $WS_INIT;

$WS_INIT['buyxp'] = array(  // the name of the plugin
    'disabled' => false,
    'events' => false,
    'default' => array(
        'help' => array(
            'title' => 'Buy XP',  // give it a friendly title
            'short' => 'Buy XP for Uncs',  // a short description
            'long' => "Buys XP for the value of <uncs>. The exchange rate is 0.1 for 1 XP.", // a long add-on to the short  description
            ),
    ),
    'buyxp' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Buys XP',
            'long' => "Buys the XP for the value of <Uncs>. The exchange rate is 0.1 for 1 XP.",
            'args' => '<Uncs>',
        ),
        'function' => 'umc_do_buyxp',
    ),
);

/**
 * Buy XP in-game // function is still working with usernames instead of UUID since
 * the /xp command does not work with UUIDs (yet) *
 *
 * @global type $UMC_USER
 */
function umc_do_buyxp() {
    global $UMC_USER;
    $xp_ratio = 0.1;
    $player = $UMC_USER['username'];
    $args = $UMC_USER['args'];

    // check arguments and set type
    if (isset($args[2])) {
        $amount = $args[2];
        settype($amount, 'int');
        $xp = floor($amount * $xp_ratio);
    } else {
        umc_error("{red}You need to specify the amount of Uncs you want to spend. See {yellow}/helpme buyxp");
    }

    $balance = umc_money_check($player);

    if ($xp < 1 || $amount < 1) {
        umc_error("{red}You need to buy at least 1 XP. For $amount Uncs you get only $xp XP (ratio is $xp_ratio!)");
    } else if ($amount > $balance) {
        umc_error("{red}Sorry, you do not have that much money.");
    } else {
        $newamount = $xp / $xp_ratio;
        umc_ws_cmd("xp $xp $player", 'asConsole');
        // umc_ws_cmd("money take $player $newamount", 'asConsole');
        umc_money($player, false, $newamount);
        if ($amount != $newamount) {
            umc_echo("Cannot buy XP for $amount Uncs. Spent amount has been reduced to $newamount Uncs.");
        }
        umc_announce("{gold}$player{gray} just bought {purple}$xp XP{gray} for{cyan} $newamount Uncs{gray}!");
        umc_log('buyxp', 'buy', "$player paid $newamount for $xp XP");
    }
}
