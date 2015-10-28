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
 * this file only contains in-game help for all other functions that are not in the system
 * this is not enabled yet in-game.
 */

// enumerate and set help along with the plugins that are installed
global $UMC_USER, $WS_INIT;

/*
 * RegionBreedLimit, LogBlock, Hats, Herochat, Essentials, EssentialsSpawn, PurpleIRC, DisguiseCraft, hats
 */

// local commands, to be included only if plugins are present
$ws_all_plugins['Essentials'] = array(  // the name of the plugin
    'disabled' => false,
    'events' => false,
    'default' => array(
        'help' => array(
            'title' => 'Teleport commands',  // give it a friendly title
            'short' => 'Commands regarding teleporting',  // a short description
            'long' => "Commands regarding teleporting", // a long add-on to the short  description
            ),
    ),
    'home' => array (
        'help' => array (
            'short' => 'Teleport you o a preset location',
            'long' => "You can set a location as a home and then teleport to that location. Homes can be set with /sethome",
            'args' => '<location name>',
        ),
        'top' => true,
        'permission' => 'essentials.home',
    ),
    'sethome' => array (
        'help' => array (
            'short' => 'Set a location to teleport to later',
            'long' => "Set your current location as a home. Can be teleported to later with /home <location>",
            'args' => '<location>',
        ),
        'top' => true,
        'permission' => 'essentials.sethome',
    ),
    'spawn' => array (
        'help' => array (
            'short' => 'Teleport to spawn',
            'long' => "This will teleport you to your spawn location in the city. The location depends on your userlevel.",
        ),
        'top' => true,
        'permission' => 'essentials.spawn',
    ),
    'tp' => array (
        'help' => array (
            'short' => 'Teleport to another user',
            'long' => "This will teleport you to another user",
            'args' => '<username>',
        ),
        'top' => true,
        'permission' => 'essentials.tp',
    ),
    'tpa' => array (
        'help' => array (
            'short' => 'Asks a player if you can teleport to them',
            'long' => "The target player gets a message that you want to teleport to them. They can then accept that with /tpaccept",
            'args' => '<username>',
        ),
        'top' => true,
        'permission' => 'essentials.tpa',
    ),
    'tpaccept' => array (
        'help' => array (
            'short' => 'Accept a /tpa request.',
            'long' => "This will accept a /tpa request for a player to teleport to you",
        ),
        'top' => true,
        'permission' => 'essentials.tpaccept',
    ),
    'tpahere' => array (
        'help' => array (
            'short' => 'Asks the specified player to accept transport to your location.',
            'long' => "You can request a player to teleport to you. The player can accept it with /tpaccept",
            'args' => '<username>',
        ),
        'top' => true,
        'permission' => 'essentials.tpahere',
    ),
    'warp' => array (
        'help' => array (
            'short' => 'Warps you to a warp-point',
            'long' => "Warps you to a warp-point set by the admin",
            'args' => '<username>',
        ),
        'top' => true,
        'permission' => 'essentials.warp',
    ),

);

$ws_all_plugins['disguisecraft'] = array(  // the name of the plugin
    'disabled' => false,
    'events' => false,
    'default' => array(
        'help' => array(
            'title' => 'Disguises',  // give it a friendly title
            'short' => 'Commands to disguise yourself',  // a short description
            'long' => "Disguisecraft allows you to disguise yourself as a mob.", // a long add-on to the short  description
            ),
    ),
    'd' => array (
        'help' => array (
            'short' => 'Disguise as a mob',
            'long' => "You can disguise as a mob",
            'args' => '<mob name>',
        ),
        'top' => true,
        'permission' => 'disguisecraft.mob',
    ),
    'undis' => array (
        'help' => array (
            'short' => 'Remove a disguise',
            'long' => "Remove a mob disguise",
        ),
        'top' => true,
        'permission' => 'disguisecraft.mob',
    ),
);

// this only adds the help for plugins that are actually installed
// go through all installed plugins
// add the help files from this page to the websend help system
foreach ($UMC_USER['plugins'] as $plugin) {
    if (isset($ws_all_plugins[$plugin])) {
        $WS_INIT[$plugin] = $ws_all_plugins[$plugin];
    }
}