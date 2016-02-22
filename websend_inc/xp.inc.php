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
 * This is a simple plugin that allows users to buy XP from the server
 */

global $UMC_SETTING, $WS_INIT;

$WS_INIT['xp'] = array(  // the name of the plugin
    'disabled' => false,
    'events' => array('ws_user_init_xp' => 'umc_xp_init_user'),
    'default' => array(
        'help' => array(
            'title' => 'Bottle XP',  // give it a friendly title
            'short' => 'Transfer XP into bottles',  // a short description
            'long' => "Tranfer XP from your user into bottles. The bottles are placed into your inventory.", // a long add-on to the short  description
            ),
    ),
    'bottlexp' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Bottle all your XP',
            'long' => "Bottles all current XP at the rate of 10 xp per bottle. There is a natural variance of XP from -70% to +10%.",
        ),
        'function' => 'umc_xp_bottle',
        'security' => array(
            'worlds' => array('empire', 'kingdom', 'skylands', 'aether', 'the_end'),
        ),
        'top' => true,
    ),
    /*'buyxp' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Buys XP',
            'long' => "Buys XP to the value of <Uncs>. The exchange rate is 1 Unc per 1 XP. Use /buyxp check to check your current XP levels.",
            'args' => '<Uncs>',
        ),
        'function' => 'umc_do_buyxp',
    ),*/
);

function umc_xp_bottle(){

    global $UMC_USER;

    $player = $UMC_USER['username'];
    $user_xp = $UMC_USER['xp'];

    // make sure they have enough xp.
    if ($user_xp < 10) {
        umc_error("{red}You need at least 10 XP points to bottle. You currently have only $user_xp.");
    }

    // calculate
    $bottle_count = floor($user_xp / 10);
    $taking_xp = $bottle_count * 10;
    $uuid = umc_user2uuid($player);
    $new_xp = $user_xp - $taking_xp;

    // set the nex exp value
    umc_ws_cmd("exp set $player $new_xp", 'asConsole');

    // give the item into deposit
    // umc_deposit_give_item($recipient, $item_name, $data, $meta, $amount, $sender)
    umc_deposit_give_item($uuid, 'experience_bottle', 0, '', $bottle_count, 'shop0000-0000-0000-0000-000000000000');

    // create the log
    umc_log('buyxp', 'bottle', "$player bottled $taking_xp into $bottle_count bottles.");

    // give some player feedback
    umc_echo("{green} You deposited $bottle_count bottles into your deposit box.");

}

// returns the TOTAL points of experience of a player based on level fraction and xp
function umc_xp_init_user($params){
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $rawlevelfraction = $params['xp'];
    $rawlevel= $params['xplevel'];

    $points_in_levels = umc_xp_lvl_to_points($rawlevel);
    $points_in_fraction = round(umc_xp_pointstolvl($rawlevel) * $rawlevelfraction);
    $total_xp_as_points = $points_in_levels + $points_in_fraction;
    //XMPP_ERROR_trace('$total_xp_as_points', $total_xp_as_points);
    return $total_xp_as_points;
}


// returns the amount of exp needed to be obtained to advance from specific level as a points value
function umc_xp_pointstolvl($inputlevel){
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    // reference material
    // http://minecraft.gamepedia.com/Experience
    // conversions dated 12/01/2016 - v 1.8.9 accurate

    $xp = 0;
    if (is_numeric($inputlevel) && $inputlevel > 0) {
        // levels 0-16
        if ($inputlevel <= 16 && $inputlevel > 0) {
            $xp = 2 * $inputlevel + 7;
        }
        // levels 17-31
        if ($inputlevel >= 17 && $inputlevel <= 31) {
            $xp = 5 * $inputlevel - 38;
        }
        // levels 32+
        if ($inputlevel >= 32) {
            $xp = 9 * $inputlevel - 158;
        }
    }
    return $xp;
}


// returns the amount of exp points equivalent to input level
function umc_xp_lvl_to_points($inputlevel){
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    // reference material
    // http://minecraft.gamepedia.com/Experience
    // conversions dated 12/01/2016 - v 1.8.9 accurate

    $xp = 0;
    if (is_numeric($inputlevel) && $inputlevel > 0) {
        // levels 0-16
        if ($inputlevel <= 16) {
            $xp = ($inputlevel ^ 2) + (6 * $inputlevel);
        } else if ($inputlevel >= 17 && $inputlevel <= 31) {
            $xp = (2.5 * pow($inputlevel,2)) - (40.5 * $inputlevel) + 360;
        } else if ($inputlevel >= 32) {
            $xp = (4.5 * pow($inputlevel,2)) - (162.5 * $inputlevel) + 2220;
        }
    }
    return $xp;
}


/**
 * Buy XP in-game // function is still working with usernames instead of UUID since
 * the /xp command does not work with UUIDs (yet) *
 *
 * @global type $UMC_USER

function umc_do_buyxp() {
    global $UMC_USER;

    $player = $UMC_USER['username'];
    $args = $UMC_USER['args'];
    $xp_ratio = 1;

    // check to see if player has entered a value of xp to buy
    if (isset($args[2])) {

    	// feedback on current xp point values
        $user_xp = $UMC_USER['xp'];
	umc_echo("{white} You started with $user_xp experience points.");

        // amount player is trying to spend
        $amount = $args[2];

        // cast argument to type int to sanitise the data
        settype($amount, 'int');

        // amount of xp calculated
        $xp = floor($amount * $xp_ratio);

        // retrieve the players balance to check if they can afford
        $balance = umc_money_check($player);

        if ( $xp < 1 || $amount < 1 ) {
            umc_error("{red}You need to buy at least 1 XP. For $amount Uncs you get only $xp XP (ratio is $xp_ratio!)");
        }

	if ( $amount > $balance ) {
            umc_error("{red}Sorry, you cannot afford this purchase. You currently have $balance uncs.");
        }

        // calculate the total new xp point value
        $new_xp = $user_xp + $xp;

        // apply purchase
        // send the console command to give the player experience
        umc_ws_cmd("exp set $player $new_xp", 'asConsole');

        // take the purchase amount from players account.
        // take from, give to, positive value
        umc_money($player, false, $amount);

        // announce the purchase to encourage players to consider buying xp
        umc_announce("{gold}$player{gray} just bought {purple}$xp XP{gray} for{cyan} $amount Uncs{gray}!");
	umc_echo("{white} You ended with $new_xp experience points.");

        // log the purchase
        umc_log('buyxp', 'buy', "$player paid $amount for $xp XP, going from $user_xp to $new_xp");

    } else {
        umc_error("{red}You need to specify the amount of Uncs you want to spend. See {yellow}/helpme buyxp");
    }

}
*/
