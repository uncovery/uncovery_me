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
 * This allows users to give reddit-like karma to other users. It also includes
 * a web interface to display the karma in wordpress.
 */
global $UMC_SETTING, $WS_INIT;


global $UMC_ACHIEVEMENTS;

$UMC_ACHIEVEMENTS = array(
    'money' => array(
        'description' => 'This achievement is given for accumulating money',
        'levels' => array(
            1000 => array('title' => false, 'reward' => false,),
            10000 => array('title' => false, 'reward' => false,),
            20000 => array('title' => false, 'reward' => false,),
            50000 => array('title' => false, 'reward' => false,),
            100000 => array('title' => false, 'reward' => false,),
            200000 => array('title' => false, 'reward' => false,),
            500000 => array('title' => false, 'reward' => false,),
            1000000 => array('title' => false, 'reward' => false,),
        ),
    ),
    'sale' => array(
        'description' => 'Make sales in the shop to this turnover',
        'levels' => array(
            10 => array('title' => false, 'reward' => false,),
            100 => array('title' => false, 'reward' => false,),
            200 => array('title' => false, 'reward' => false,),
            500 => array('title' => false, 'reward' => false,),
            1000 => array('title' => false, 'reward' => false,),
            2000 => array('title' => false, 'reward' => false,),
            5000 => array('title' => false, 'reward' => false,),
            10000 => array('title' => false, 'reward' => false,),
        ),
    ),
    'vote' => array(
        'description' => 'Vote for the server many times',
        'levels' => array(
            10 => array('title' => false, 'reward' => false,),
            100 => array('title' => false, 'reward' => false,),
            200 => array('title' => false, 'reward' => false,),
            500 => array('title' => false, 'reward' => false,),
            1000 => array('title' => false, 'reward' => false,),
            2000 => array('title' => false, 'reward' => false,),
            5000 => array('title' => false, 'reward' => false,),
            10000 => array('title' => false, 'reward' => false,),
        ),
    ),
);


$WS_INIT['karma'] = array(
    'default' => array(
        'help' => array(
            'title' => 'User Achievements',
            'short' => 'Manage all your acheivements',
            'long' => 'Achievements show your progress in the game and you are rewarded for reaching certain levels of achievements.',
            ),
    ),
    'list' => array(
        'help' => array(
            'short' => 'Give +1 karma to another user',
            'args' => '<user>',
            'long' => 'You cannot give more than 1 karma. User keeps your karma until you give -1 or 0.',
        ),
        'function' => 'umc_setkarma',
    ),
    'disabled' => false,
    'events' => false,
);