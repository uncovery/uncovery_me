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
 * This is a simple FAQ that is displayed on the website.
 * This could be moved to the websend_inc folder and supplied with the required
 * config if we decided to make a in-game function out of it
 */

// we want to have a fixed, unique ID for every FAQ to be able to link to it
// we can have several categories
global $UMC_FAQ;
$UMC_FAQ = array(
    1 => array(
        'question' => 'I built something amazing for the city! Can you copy it over?',
        'answer' => 'Do not build something because you think the city needs it. Do not ask me to copy things because “you built it for the city”.
            Specially not if it took you 30 minutes to build it. If you want your stuff to be seen, post it on the forum.
            If I think the city needs it, I will copy it.',
        'categories' => array('worlds', 'in-game'),
    ),
    2 => array(
        'question' => 'I want to change my Minecraft Username. What do I have to do?',
        'answer' => '<ul>
            <li>Remove all your inventory from your body in all worlds – deposit it or put it into normal chests, not ender chests</li>
            <li>Remove all your contents from all ender chests in all worlds</li>
            <li>Use your XP, it will be gone, too</li>
            <li>Log off from the website and the game</li>
            <li>change your name with mojang</li>
            <li>Login at the game</li>
            <li>Login at the website</li>
            <li>check if the “welcome” message on the website changed</li></ul>',
        'categories' => array('account'),
    ),
    3 => array(
        'question' => 'How can I have more lots? I need more space!',
        'answer' => 'If you are settler, you can either buy kingdom lots or build in the darklands.
            To get more space in other worlds, please see the bottom of <a href="http://uncovery.me/about-this-server/user-levels/">this page</a>.',
        'categories' => array('userlevels', 'lots', 'account'),
    ),
    4 => array(
        'question' => 'I would like to have a notice/sign board in spawn with rules, ads, information, whatever.',
        'answer' => 'Sorry, this will not happen. I have enough to do keeping the website up to date with stuff. Also, writing/editing the in-game signs is a really big pain.
            I do not want to do that. Also, I do not want that people rely on several places to find info. If you have one watch, you know the time.
            If you have two, you never know which one is correct.',
        'categories' => array('in-game', 'rules', 'website'),
    ),
    5 => array(
        'question' => 'I need something but cannot find it. Can you sell it to me?',
        'answer' => 'No. I only sell a limited number of items that are difficult to obtain, which you can see in the shop when you type “/list uncovery”. I do not buy anything. Don\’t ask for it.',
        'categories' => array('in-game', 'blocks'),
    ),
    6 => array(
        'question' => 'I won’t be online for some time, will you delete my stuff?',
        'answer' => 'Yes. If you do not login on the server for some time, your lot will expire. The normal lot expiry time is 1 month after your last login. If you want to extend your leave, you can donate.',
        'categories' => array('account', 'lots'),
    ),
    7 => array(
        'question' => 'I would like to restart from scratch, can you reset my lot? Or can I switch from Empire to Flatlands?',
        'answer' => 'No. If I start doing this, I will have people migrating from one lot to the other and I have all the work.
            You select a lot, you keep it. I allow people to switch from Flatlands to Empire as an exception, but only in one way – once.
            I cannot keep track of people switching forward and backward. Every switch is work for me, and I have better things to do than to deal with your indecisiveness.',
        'categories' => array('lots', 'worlds', 'in-game'),
    ),
    8 => array(
        'question' => 'Can you enable the /item command for certain people? Can you give out free items/blocks etc?',
        'answer' => 'No. You chose to build in a survival map, then you build with survival tools. People mine, and build from what they mine.
            If you are not fine with that, You should have picked a flatlanbds lot.',
        'categories' => array('blocks', 'commands', 'in-game'),
    ),
    9 => array(
        'question' => 'Can you give us Worldedit Commands?',
        'answer' => 'No. Worldedit is unable to respect lot protection and  it is VERY easy to crash a server abusing worldedit.
            The only exception are for Elders in the draftlands.',
        'categories' => array('commands', 'in-game'),
    ),
    10 => array(
        'question' => 'How long until you reset the map of world X?',
        'answer' => 'Well if all goes well, never. We only reset maps if the biomes change dramatically.
            But even then. we only reset empty lots and allow people to reset their lots on demand. This excludes the kingdom.
            If there are new blocks, we just reset the darklands so you can mine them there',
        'categories' => array('worlds'),
    ),
    11 => array(
        'question' => 'Can you reset the nether? I cannot find any glowstone around the spawn!',
        'answer' => 'No, I will not. It’s a survival game. The nether does not have a size limitation. You can walk out far and find tons of glowstone.
            The only exception is when an update introduces new terrain generation in the nether, as happened with 1.5 and beta 1.8.',
        'categories' => array('worlds'),
    ),
    12 => array(
        'question' => 'I lost something during a crash/death/theft/bug/whatever, can you give it back to me?',
        'answer' => 'In most cases no, I cannot. If I cannot verify what you had before and how much you exactly lost, there is no way.
            If you had bad luck, that’s what you will have to live with. If someone tricked you, same. If there was lag, a crash even.
            I only refund if there is a bug in the system that I can 1:1 trace and know what you had before, what you lost and why.',
        'categories' => array('blocks', 'problems'),
    ),
    13 => array(
        'question' => 'Can I allow others to build inside my lot?',
        'answer' => 'Yes, you can do so yourself. Please see the <a href="http://uncovery.me/about-this-server/user-levels/">user levels</a> page for instructions.',
        'categories' => array('commands', 'lots', 'in-game'),
    ),
    14 => array(
        'question' => 'Can an admin give me items / do something larger for me / convert blocks / remove stuff?',
        'answer' => 'No, sorry, not possible. If I start doing that its first of all not fair towards others and second of all, I have better things to do.
            I spend enough time with admin tasks for you guys. Also the server motto is "build your dream" and not "have someone else build your dream.
            If you want to build crazy stuff without the work, you better go to a creative world instead of a survival world.',
        'categories' => array('commands', 'in-game'),
    ),
    15 => array(
        'question' => 'I have chosen the wrong lot! It’s all in the water! Can I change?',
        'answer' => 'No. You cannot. You are supposed to go there first and check it out. I am not working extra to reward your laziness.
            Take it as a challenge and build something on the water.',
        'categories' => array('lots'),
    ),
    16 => array(
        'question' => 'How long does it take to get to higher level such as "Architect" or "Master"?',
        'answer' => 'This depends only on you. Those levels are voted on by other users. Make sure people see you and like what you do, and you will get a higher level in no time.',
        'categories' => array('userlevels'),
    ),
    17 => array(
        'question' => 'I have this client mod "x" that I want to install. Is that OK?',
        'answer' => 'The rule is: If it gives you an advantage over others who do not use it, it\'s forbidden.
            The only client side mods that are allowed are normal texture packs, stuff like optifine and a minimap that shows you your immediate surroundings.
            Texture packs that show you things that the normal texture does not show are not allowed.
            All other modifications are not allowed either. No flying, speeding, no-clip (walking through walls), whatever it is – it is not allowed.',
        'categories' => array('rules', 'commands', 'in-game'),
    ),
    18 => array(
        'question' => 'I want to start my own server! Can you help me?',
        'answer' => 'No. I am busy enough working on my server, I do not need additional work on yours.
            Please do not ask questions how I got stuff done, how to install things or how I configured whatever.',
        'categories' => array('misc'),
    ),
    19 => array(
        'question' => 'Are there limitations on breeding mobs?',
        'answer' => 'Yes.  Use the /headcount command to see the limit in the region where you are standing in-game, or the chunk if you are in a world without defined regions.
            If you attempt to breed more mobs than are allowed, the mobs simply will not spawn.',
        'categories' => array('mobs', 'rules', 'in-game'),
    ),
    20 => array(
        'question' => 'Why aren\'t Iron Golems spawning?',
        'answer' => 'Natural spawning of Iron Golems has been disabled due to issues with golem farms causing lag.',
        'categories' => array('mobs', 'in-game'),
    ),
    21 => array(
        'question' => 'Can I help other players with the Settler quiz if they are having trouble?',
        'answer' => 'No, you should not. Players are informed on the quiz page that asking for the answers can get them banned.
            In turn, nobody should be assisting guests with the Settler quiz. The intent is that players should pass the quiz without assistance.
            Of course, this is difficult to enforce in reality, with people in the same house, on the phone, or whatever,
            but to the extent we can do anything about it, helping with the quiz is prohibited.',
        'categories' => array('website', 'rules'),
    ),
    22 => array(
        'question' => 'My kingdom lot has snow on the ground, but it never snows! How can I make it snow?',
        'answer' => 'The kingdom was created a long time ago. Since then, biome definitions have changed. The snow might be still around from the time when the old biome had snow on it.
            The new one might not have snow at all. Please go to the location in question and press F3.
            Check what the name of the biome is and then check if it’s on the list of Snowy Biomes.',
        'categories' => array('lots', 'worlds', 'in-game'),
    ),
    23 => array(
        'question' => 'I will be on holiday, will my lot reset?',
        'answer' => 'Yes. If you do not login within the reset time, it will reset and I will not do anyhting about it.',
        'categories' => array('lots'),
    ),
    24 => array(
        'question' => 'Can I keep my lot even if I do not login for a month?',
        'answer' => 'Yes. Become DonatorPlus. You will keep your lot for the duration of your donation status',
        'categories' => array('userlevels', 'lots'),
    ),
    25 => array(
        'question' => 'My friends lot will be reset, but all my stuff is there and we built the lot together, what can I do?',
        'answer' => 'Go there and take what is yours before it resets. I will NOT stop the lot from resetting. If anything o your stuff is gone during a reset, it is your problem.',
        'categories' => array('lots'),
    ),
    26 => array(
        'question' => 'Can you install McMMo (or any other plugin/mod)?',
        'answer' => 'McMMo is a huge plugin with a lot of work to configure it an make it balanced. We can make a world that works with McMMo, but I would neeed to be convinced that
            people actually use it. If you think it\'s that great, I would invite you to make a forum post and ask people if they want it. If you get enough people to support it, I might install it',
        'categories' => array('misc', 'worlds'),
    ),
    27 => array(
        'question' => 'Can we install chest shops?',
        'answer' => 'No. We have hundreds of users and people need to walk to most of the places. Having chest shops is good for people near spawn but not for people further away.
            In order to get a fair system, we would need shops somewhere in the center. Having hundreds of shops in spawn is not feasible however.
            To have a fair situation for everyone, we created the shop system where location of your lot does not matter.
            Installing shops in chests is only additional plugins to maintain and load on the server without any significant benefit for game-play',
        'categories' => array('blocks', 'in-game'),
    ),
    28 => array(
        'question' => 'Something is not working, what should I do?',
        'answer' => 'First of all, please check if someone else has the same issue. If that is the case, please check the <a href="http://uncovery.me/server-features/development-status/">
            Bugs list</a>. If you can find the problem there, then it\'s a known problem. If you want to accelerate the fix for this problem, you can post to the forum. If the problem is not
            on the list, you can send a mail to uncovery with the /mail command.',
        'categories' => array('problems'),
    ),
    29 => array(
        'question' => 'What are active/inactive users?',
        'answer' => 'Active users are users who have their own lot. Inactive users are any other level user who does not have a lot, either because they never got one (e.g. Guests) or
            because they lost it due to inactivity. Many thing thats you can do with other users require the other user to be an active user. This includes sending items to their deposit,
            adding them to your lot and more.',
        'categories' => array('problems', 'in-game', 'userlevels', 'rules', 'misc'),
    ),
    30 => array(
        'question' => 'Some item frames disappeared on my lot!',
        'answer' => 'Unfortunately, this can happen. Item frames seem to be bugged and simply might disappear from time to time. Please do not store your most valuable items in
            item frames since the item in the frame will disappear along with the frame.',
        'categories' => array('problems', 'in-game', 'blocks', 'misc'),
    ),
    31 => array(
        'question' => 'I have been griefed, what shall I do?',
        'answer' => 'Generally we want users to settle griefing issues among themselves. So first of all, you should try to find out who griefed you. If it happened on your lot,
            and only one other person had access to your lot, ask that person if they did it. You can also check the block logs page on the website to find out who did that.
            IF you cannot find out who did it, or if the person refuses that they griefed you, send a message to the admin with the possibly exact details:
            What has been done, on what coordinates, in what world and when. You will then get a message back who did it - we log almost everything.
            You can then go and tell the person that the logfile proves that they have done the damage, and ask them kindly to undo the damage. If they refuse to repair or even to admit the
            damage, please contact the admin again. We will then see if a ban is required.',
        'categories' => array('problems', 'in-game', 'blocks', 'rules'),
    ),
    32 => array(
        'question' => 'Why do users need their own lot before I can add them to mine?',
        'answer' => 'We track user activity through their lot ownership. Once a user becomes inactive, they lose their lot. This is the first indicator that they also should not be able to do a number of other things anymore.
            We did this for a several reasons: Active users (i.e. with a lot) started depositing large quantities of items for random inactive users and abusing the limitations
            on deposit space like that. Other active users thought it\'s cool to add dozens of inactive users to their lot and messed up the 2D map that way.
            Further, when a user had large amount of shop offers and became inactive, the offers stayed around and active users had less chances to sell their stuff.
            So we automatically move all shop contents of inactive users to their deposit. So we treat users without lots as inactive users and don\'t allow them to interact with in-game systems until they get a lot.',
        'categories' => array('problems', 'in-game', 'lots', 'rules', 'commands', 'userlevels'),
    ),
    33 => array(
        'question' => 'Why can\'t I build a nether portal in my empire lot?',
        'answer' => 'It is NOT possible to build a working nether portal on your lot. The nether can be only reached through the portal in the spawn area. This is a technical matter. Reasons are:
            The normal world is size limited. Since the nether is at a larger scale than normal worlds, 100 blocks there is 800 blocks in the empire. If you walk only a couple of hundred blocks away from the nether spawn, you would already be far out from the borders of the empire map limits due to the size ratios 1:8.
            Creating a nether portal in the normal world makes one in the nether, and making one in the nether either outside of the empire limits or at best in the middle of someone’s lot and building - damaging it.
            Also, the nether is not that far away. You can type /spawn and walk a few steps to get to the nether portal, so it’s not as if it was hard to reach the nether
            However, you can create a nether portal (standard with obsidian and flint & steel) when in the nether, and that portal will bring you to the darklands. The nether scales to the darklands 1:8 so you can make a portal in the nether every 100 blocks which will bring you 800 blocks further in the darklands. HOWEVER, you cannot go back through these portals! They are a one-way street into the darklands!',
        'categories' => array('worlds', 'in-game', 'lots', 'rules'),
    ),
);

/**
 * Create a jQuery accordion
 *
 * @global array $UMC_FAQ
 * @param type $id
 * @return string
 */
function umc_faq_web($id = 'accordion') {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_FAQ;

    $s_get  = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
    if (isset($s_get['id']) && isset($UMC_FAQ[$s_get['id']])) {
        $active = $s_get['id'] - 1;
    } else {
        $active = 0;
    }
    if (isset($s_get['cat'])) {
        $presel_cat = $s_get['cat'];
    } else {
        $presel_cat = 'all';
    }

    $out = "<script>
    jQuery(document).ready(function($) {
        $( \"#$id\" ).accordion({
            collapsible: true, heightStyle: \"content\", active: $active
        });
    });
</script>"
    . "<div id=\"$id\">\n";

    $cat_arr = array('all' => 'All');

    foreach ($UMC_FAQ as $faq_id => $F) {
        // we cannot do array_merge since we need to set the keys for the dropdown
        foreach ($F['categories'] as $cat) {
            $cat_arr[$cat] = ucwords($cat);
        }
        $cat_text = implode(", ", $F['categories']);
        $answer_text = nl2br($F['answer']);
        if (($presel_cat == 'all') || in_array($presel_cat, $F['categories'])) {
            $out .= "    <h3 id=\"FAQ$faq_id\">$faq_id: {$F['question']}</h3>
            <div>
                <p class=\"answer\">$answer_text</p>
                <p class=\"categories\"><a href=\"?id=$faq_id#FAQ$faq_id\">Direct link</a> | Categories: $cat_text</p>
            </div>";
        }

    }
    $out .= "</div>";

    $drop = "Categories: <form style=\"display:inline;\" action=\"\" method=\"get\">\n"
        . umc_web_dropdown($cat_arr, 'cat', $presel_cat, true)
        . "</form>";

    $out = $drop . $out;
    return $out;
}