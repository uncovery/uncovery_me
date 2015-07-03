<?php

/**
 * This could be moved to the websend_inc folder and supplied with the required
 * config if we decided to make a in-game function out of it
 */

// we want to have a fixed, unique ID for every FAQ to be able to link to it
// we can have several categories
$FAQ = array(
    1 => array(
        'question' => 'I built something amazing for the city! Can you copy it over?',
        'answer' => 'Do not build something because you think the city needs it. Do not ask me to copy things because “you built it for the city”.
            Specially not if it took you 30 minutes to build it. If you want your stuff to be seen, post it on the forum.
            If I think the city needs it, I will copy it.',
        'categories' => array('building', 'worlds'),
    ),
    2 => array(
        'question' => 'I want to change my Minecraft Username. What do I have to do?',
        'answer' => '1) remove all your inventory from your body in all worlds – deposit it or put it into normal chests, not ender chests
            2) Remove all your contents from all ender chests in all worlds
            3) Use your XP, it will be gone, too
            4) Log off from the website and the game
            5) change your name with mojang
            6) Login at the game
            7) Login at the website
            8) check if the “welcome” message on the website changed',
        'categories' => array('account'),
    ),
    3 => array(
        'question' => 'How can I have more lots? I need more space!',
        'answer' => 'If you are settler, you can either buy kingdom lots or build in the darklands.
            To get more space in other worlds, please see the bottom of <a href="http://uncovery.me/about-this-server/user-levels/">this page</a>.',
        'categories' => array('userlevels', 'lots', 'account'),
    ),
);

function umc_faq_web() {



}
