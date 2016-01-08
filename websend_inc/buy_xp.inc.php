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

$WS_INIT['buyxp'] = array(  // the name of the plugin
    'disabled' => false,
    'events' => false,
    'default' => array(
        'help' => array(
            'title' => 'Buy XP',  // give it a friendly title
            'short' => 'Buy XP for Uncs',  // a short description
            'long' => "Buys XP for the value of <uncs>. The exchange rate is 0.1 Unc per 1 XP.", // a long add-on to the short  description
            ),
    ),
    'buyxp' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Buys XP',
            'long' => "Buys XP to the value of <Uncs>. The exchange rate is 0.1 Unc per 1 XP.",
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
	
    $player = $UMC_USER['username'];
    $args = $UMC_USER['args'];
    $xp_ratio = 10;
	
    // check to see if player has entered a value of xp to buy
    if (isset($args[2])) {
        
        // amount player is trying to spend
        $amount = $args[2];

        // cast argument to type int to sanitise the data
        settype($amount, 'int');
	
        // amount of xp calculated
        $xp = floor($amount * $xp_ratio);
        
        // retrieve the players balance to check if they can afford
        $balance = umc_money_check($player);

        // validation checks, can afford, is an actual purchase.
        $canafford = true;
        $validvalue = true;
        
        if ( $xp < 1 || $amount < 1 ) {	
            $validvalue = false;
            umc_error("{red}You need to buy at least 1 XP. For $amount Uncs you get only $xp XP (ratio is $xp_ratio!)");
        }

	if ( $amount > $balance ) {
	    $canafford = false;
            umc_error("{red}Sorry, you cannot afford this purchase. You currently have $balance uncs.");
        }

        // apply purchase
        if ($canafford && $validvalue) {
        
            // send the console command to give the player experience
            umc_ws_cmd("xp $xp $player", 'asConsole');
            
            // take the purchase amount from players account.
            // take from, give to, positive value
            umc_money($player, false, $amount);
            
            // announce the purchase to encourage players to consider buying xp
            umc_announce("{gold}$player{gray} just bought {purple}$xp XP{gray} for{cyan} $newamount Uncs{gray}!");
            
            // log the purchase
            umc_log('buyxp', 'buy', "$player paid $amount for $xp XP");
        }
    
    } else {
        umc_error("{red}You need to specify the amount of Uncs you want to spend. See {yellow}/helpme buyxp");
    }
	
}
