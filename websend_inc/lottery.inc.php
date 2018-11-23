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
 * This manages the lottery process that happens when a user votes on a server
 * list. It requires the deposit box since the winnings have to go somewhere
 * even if the user in not in-game.
 */

global $UMC_SETTING, $WS_INIT, $UMC_DOMAIN;

$WS_INIT['lottery'] = array(  // the name of the plugin
    'disabled' => false,
    'events' => array(
        'PlayerJoinEvent' => 'umc_lottery_reminder',
    ),
    'default' => array(
        'help' => array(
            'title' => 'Voting Lottery',  // give it a friendly title
            'short' => 'Vote for the server and win prizes',  // a short description
            'long' => "You can vote for the lottery and win 100 Uncs + a random prize. See $UMC_DOMAIN/vote-for-us/ for detailss", // a long add-on to the short  description
            ),
    ),
    'servervote' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Test Vote',
            'long' => "Runs a test vote, results go to Uncovery.",
            'args' => '<username> <chance>',
        ),
        'security' => array(
            'level' => 'Owner',
        ),
        'function' => 'umc_lottery',
    ),
    'vote' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Display the vote links',
            'long' => "Shows a list of all active test-URLs for easier click & vote",
        ),
        'function' => 'umc_lottery_vote',
        'top' => true,
    ),
    'report' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Output vote data',
            'long' => "Display information about times since last votes.",
        ),
        'function' => 'umc_lottery_report',
    ),
);

global $lottery, $lottery_urls;

$lottery_urls = array(
    'minecraft-server-list.com' => array('url' => 'http://minecraft-server-list.com/server/54265/vote/', 'id' => 'mcsl', 'val' => 500),
    'minecraftservers.org' => array('url' => 'http://minecraftservers.org/vote/160828', 'id' => 'minecraftservers.org', 'val' => 50),
    // 'mineservers.net' => array('url' => 'http://www.mineservers.net/servers/834-uncovery-minecraft/vote', 'id' => 'mineservers.net', 'val' => 100),
    'minecraft-mp.com' => array('url' => 'http://minecraft-mp.com/server/49/vote/', 'id' => 'minecraft-mp.com', 'val' => 50),
    'minecraftservers.biz' => array('url' => 'https://minecraftservers.biz/servers/824/', 'id' => 'minecraftservers.biz', 'val' => 50),
    'minecraft-servers-list.org' => array('url' => 'http://www.minecraft-servers-list.org/index.php?a=in&u=uncovery', 'id' => 'minecraft-servers-list.org', 'val' => 50),
    // 'minecraftservers.net' => array('url' => 'http://minecraftservers.net/server.php?id=5881', 'id' => 'minecraftservers.net', 'val' => 50),
);

$lottery = array(
    'diamond' => array(
        'chance' => 99,
        'type' => 'item',
        'txt' => 'a shiny, tiny, diamond',
        'detail' => array(
            'item_name' => 'diamond',
            'nbt' => '',
        ),
    ),
    'trident' => array(
        'chance' => 1,
        'type' => 'item',
        'txt' => 'a trident!!',
        'detail' => array(
            'item_name' => 'trident',
            'nbt' => '',
        ),
    ),     
    'diamond_block' => array(
        'chance' => 10,
        'type' => 'item',
        'txt' => 'an ugly, heavy diamond block',
        'detail' => array(
            'item_name' => 'diamond_block',
            'nbt' => '',
        ),
    ),
    'golden_apple' => array(
        'chance' => 70,
        'type' => 'item',
        'txt' => 'a shiny golden apple (Yum!)',
        'detail' => array(
            'item_name' => 'golden_apple',
            'nbt' => '',
        ),
    ),
    'cake' => array(
        'chance' => 50,
        'type' => 'item',
        'txt' => 'an entire cake (Happy Cakeday!)',
        'detail' => array(
            'item_name' => 'cake',
            'nbt' => '',
        ),
    ),
    'coal' => array(
        'chance' => 10,
        'type' => 'item',
        'txt' => 'a NOT shiny piece of coal',
        'detail' => array(
            'item_name' => 'coal',
            'nbt' => '',
        ),
    ),
    'enchanted_pick' => array(
        'chance' => 10,
        'type' => 'item',
        'txt' => 'a super-enchanted wooden pickaxe',
        'detail' => array(
            'item_name' => 'wooden_pickaxe',            
            'nbt' => '{RepairCost:7,Enchantments:[{lvl:1,id:"minecraft:silk_touch"},{lvl:5,id:"minecraft:efficiency"},{lvl:3,id:"minecraft:unbreaking"}]}',
        ),
    ),
    'enchanted_sword' => array(
        'chance' => 10,
        'type' => 'item',
        'txt' => 'a super-enchanted wooden sword',
        'detail' => array(
            'item_name' => 'wooden_sword',
            'nbt' => '{RepairCost:63,Enchantments:[{lvl:3,id:"minecraft:unbreaking"},{lvl:5,id:"minecraft:sharpness"},{lvl:3,id:"minecraft:sweeping"},{lvl:3,id:"minecraft:looting"},{lvl:2,id:"minecraft:fire_aspect"},{lvl:2,id:"minecraft:knockback"}]}',
        ),
    ),
    'dirtblock' => array(
        'chance' => 10,
        'type' => 'item',
        'txt' => 'a big block of extra-fine dirt',
        'detail' => array(
            'item_name' => 'dirt',
            'nbt' => '',
        ),
    ),
    'cookie' => array(
        'chance' => 70,
        'type' => 'item',
        'txt' => 'a hot cookie (OUCH!)',
        'detail' => array(
            'item_name' => 'cookie',
            'nbt' => '',
        ),
    ),
    'random_pet' => array(
        'chance' => 10,
        'type' => 'random_pet',
        'data' => 'pet',
        'txt' => 'a random Animal Egg',
        'blocks' => array(
            'donkey_spawn_egg', 'mule_spawn_egg', 'pig_spawn_egg', 'sheep_spawn_egg', 'cow_spawn_egg', 'chicken_spawn_egg', 'squid_spawn_egg', 'wolf_spawn_egg',
            'mooshroom_spawn_egg', 'ocelot_spawn_egg', 'horse_spawn_egg', 'polar_bear_spawn_egg', 'llama_spawn_egg', 'villager_spawn_egg',
        ),
    ),
    'random_unc' => array(
        'chance' => 180,
        'type' => 'random_unc',
        'data' => 'unc',
        'txt' => 'a random amount of Uncs (max 500)',
    ),
    'random_item' => array(
        'chance' => 90,
        'type' => 'random_item',
        'data' => 'common',
        'txt' => '1 of random item',
        'blocks' => array(
            
        ),
    ),
    'random_sapling' => array(
        'chance' => 100,
        'type' => 'random_sapling',
        'data' => 'common',
        'txt' => '1-64 of random sapling',
        'blocks' => array(
            "dark_oak_sapling", "jungle_sapling", "oak_sapling", "spruce_sapling", "acacia_sapling", "birch_sapling"
        ),
    ),
    'random_ore' => array(
        'chance' => 50,
        'type' => 'random_ore',
        'data' => 'ore',
        'txt' => '1-64 of random ore',
        'blocks' => array(
            'gold_ore', 'coal_ore', 'iron_ore', 'lapis_ore', 'diamond_ore', 'redstone_ore', 'emerald_ore', 'nether_quartz_ore', 
        ),
    ),
/*    'random_manuf' => array(
        'chance' => 140,
        'type' => 'random_manuf',
        'data' => 'man',
        'txt' => '1-64 of random manufactured block',
        'blocks' => array(
            
        ),
    ),*/
    'random_ench' => array(
        'chance' => 68, // rate of 69 in 1000
        'type' => 'random_ench',
        'data' => 'enchanted item',
        'txt' => 'a random single-enchanted item',
    ),
    'random_potion' => array(
        'chance' => 100, // rate of 69 in 1000
        'type' => 'random_potion',
        'data' => 'potion',
        'txt' => 'a random potion',
    ),
    'additional_home' => array(
        'chance' => 1, // rate of 1 in 1000
        'type' => 'additional_home',
        'data' => 'home',
        'txt' => 'an additional home!',
    ),
    'additional_deposit' => array(
        'chance' => 1, // rate of 1 in 1000
        'type' => 'additional_deposit',
        'data' => 'deposit',
        'txt' => 'an additional deposit slot!',
    ),
    'vanity_title' => array(
        'chance' => 10, // rate of 1 in 1000
        'type' => 'vanity_title',
        'data' => 'title',
        'txt' => 'a vanity title!',
    )
);

/**
 * Prints a list of all voting servers in-game for easier voting
 * @global array $lottery_urls
 */
function umc_lottery_vote() {
    // get the votes of the current user in the last 24 hours

    global $UMC_USER, $lottery_urls;

    $uuid_sql = umc_mysql_real_escape_string($UMC_USER['uuid']);
    $sql = "SELECT website FROM minecraft_log.votes_log WHERE username=$uuid_sql AND datetime > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    $W = umc_mysql_fetch_all($sql);
    if (count($W) == count($lottery_urls)) {
       umc_echo("You voted on all lists in the last 24 hours already! Thanks!");
    }
    $voted = array();
    foreach ($W as $row) {
        $voted[] = $row['website'];
    }
    umc_header("Voting servers:");
    foreach ($lottery_urls as $L) {
        if (!in_array($L['id'], $voted)) {
            $data = array(
                array('text' => " - ", 'format' => array('white')),
                array('text' => $L['id'], 'format' => array('open_url' => $L['url'], 'yellow')),
                array('text' => ", \${$L['val']} UNC reward" , 'format' => array('white')),
            );
            umc_text_format($data, false, false);
        }
    }
        $data = array(
            array('text' => "(Click on the yellow text to open in browser)", 'format' => array('white')),
        );
        umc_text_format($data, false, false);
    umc_footer();
}

/**
 * Prints a list of all voting servers on the web for easier voting
 * @global array $lottery_urls
 */
function umc_lottery_vote_web() {
    // get the votes of the current user in the last 24 hours

    global $UMC_USER, $lottery_urls;
    $out = '';
    $voted = array();

    // don't show servers the user voted already for
    if ($UMC_USER) {
        $uuid_sql = umc_mysql_real_escape_string($UMC_USER['uuid']);
        $sql = "SELECT website FROM minecraft_log.votes_log WHERE username=$uuid_sql AND datetime > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $W = umc_mysql_fetch_all($sql);
        if (count($W) == count($lottery_urls)) {
           $out .= "You voted on all lists in the last 24 hours already! Thanks!";
           return $out;
        }
        foreach ($W as $row) {
            $voted[] = $row['website'];
        }
    }

    $out .= "Voting servers:<br>
        <ul>\n";
    foreach ($lottery_urls as $L) {
        if (!in_array($L['id'], $voted)) {
            $out .= "<li><a href=\"{$L['url']}\" target=\"_blank\">{$L['id']}</a> ({$L['val']} Uncs)</li>";
        }
    }

    if (count($voted) > 0) {
        $out .= "</ul>";
        $out .= "You voted already on these in the last 24 hours:
            <ul>\n";
        foreach ($lottery_urls as $L) {
            if (in_array($L['id'], $voted)) {
                $out .= "<li><a href=\"{$L['url']}\" target=\"_blank\">{$L['id']}</a></li>";
            }
        }
    }

    $out .= "</ul>";

    return $out;
}


/**
 * runs on user login to remind them to vote.
 *
 * @global type $UMC_USER
 * @global type $UMC_DOMAIN
 */
function umc_lottery_reminder() {
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $uuid = $UMC_USER['uuid'];
    
    // do not tell Guests to vote
    $userlevel = $UMC_USER['userlevel'];
    if ($userlevel == "Guest") {
        return;
    }

    $checkdate = date("Y-m-d H:i:s", strtotime("-24 hours"));

    // TODO: the votes log fieldname is username, but there are UUIDs inside, need to fix that
    $sql = "SELECT count(vote_id) as counter
            FROM minecraft_log.votes_log
            WHERE `username`='$uuid'
            AND `datetime`>='$checkdate'
            ORDER BY `vote_id` DESC;";

    $D = umc_mysql_fetch_all($sql);
    $counter = $D[0]['counter'];

    if ($counter < 5) {

        // politely remind users they need to vote dammit!
        $title =  'title ' . $player . ' title {"text":"Please vote!","color":"green"}';

        // TODO there should be a separate event to handle this
        // add some variety to login welcome messages!
        $messages = array(
            'Welcome back ' . $player .'!',
            $player . '! Great to see you!',
            'Hello again ' . $player,
            "Maybe you should visit the darklands today?",
            "Maybe you should visit the darklands today?",
            "Considered taking a stroll in the empire?",
            "Have you tried the command /find request new",
            "Experience can be bottled using /bottlexp",
            "Hold an item and type /offer <your price> to list it for sale!",
            "Rome wasnt built in a day...",
            "Thanks for coming by to play!",
            "You can find items to buy using /find <itemname>",
            "Darklands is a resource gathering world. But beware the moon...",
            'Hey, your friends were looking for you ' . $player,
            "Use /whereami for information about your position!",
            "Use /uncs to display your current balance!",
            "Did you know you can buy additional homes for Uncs?",
            "Did you know you can buy additional desposit boxes for Uncs?",
            "We missed you $player!",
        );

        // select a random position in the title array
        $key = array_rand($messages);
        $subtitle = $messages[$key];

        umc_ws_cmd("title $player subtitle {\"text\":\"$subtitle\",\"color\":\"gold\"}", 'asConsole');
        umc_ws_cmd($title, 'asConsole');

    }
}

/**
 * calculate the number of votes vs. the number of logins of a user over the last 30 days.
 *
 * @param type $uuid
 * @return string|int
 */
function umc_lottery_stats($uuid) {
    // check how often the user logged in during the last 30 days
    $username = umc_uuid_getone($uuid, 'username');

    $sql_uname = umc_mysql_real_escape_string($username);
    $sql = "SELECT date
        FROM minecraft_log.universal_log
        WHERE username LIKE $sql_uname AND `plugin` LIKE 'system' AND `action` LIKE 'login' AND `date` > DATE_SUB(NOW(), INTERVAL 30 day)
        group by `date`";
    $L = umc_mysql_fetch_all($sql);
    $login_count = count($L);

    $sql_uuid = umc_mysql_real_escape_string($uuid);
    $sql_votes = "SELECT datetime FROM minecraft_log.`votes_log`
        WHERE username=$sql_uuid AND `datetime` > DATE_SUB(NOW(), INTERVAL 30 day)
        group by DAY(datetime)";
    $V = umc_mysql_fetch_all($sql_votes);
    $vote_count = count($V);

    if ($username == 'uncovery') {
        return "n/a";
    }
    if ($login_count == 0) {
        return "n/a";
    } else if ($vote_count == 0) {
        return 0;
    }

    $ratio = number_format($vote_count / $login_count, 2);
    return $ratio;
}


/**
 * displays a report to the initiating user displaying their vote history to $lim rolls and $hours hours.
 * ie you can check for 500 hours worth of rolls, but limit result count to $lim
 *
 * @param type $hours
 * @param int $lim
 */
function umc_lottery_report($hours = 24, $lim = 50){

    $D = umc_lottery_retrieve_entries($hours);
    $c = count($D);

    // display a reminder
    if ($c < 5){
        umc_echo("{yellow} Please vote! This is *super important* to attract more players, get rich and win fantastic rewards!");
    }

    // display the total
    umc_echo("{yellow} [!] {green} Our records show you have voted $c times in the last $hours hours!");

    //set a maximum number of vote records to display back to the user
    $now = new DateTime("now");

    // iterate through array and output results to limits
    foreach ($D as $row) {

        $timestamp = $row['datetime'];
        $diff = $timestamp->diff($now);
        $hours = $diff->h;
        $website = $row['website'];
        $reward = $row['reward'];
        $reward_amount = $row['reward_amount'];

        // echo the records retrieved
        umc_echo("{yellow}[-]{grey}[$website] $hours hours ago: $reward_amount $reward");

        // limit the count displayed back to the user due to mc messaging limits
        $lim -= 1;
        if ($lim <= 1){
            umc_echo("red}[!] Too many records to display!");
            break;
        }

    }

}

/**
 *
 * returns an array of vote rolls (to 150) in last specified hours.
 *
 * @global type $UMC_USER
 * @param type $hours
 * @return type
 */
function umc_lottery_retrieve_entries($hours = 24){
    global $UMC_USER;
    $uuid = $UMC_USER['uuid'];

    $checkdate = date("Y-m-d H:i:s", strtotime("-" . $hours . "hours"));

    // select all lottery rolls within last 24 hours
    $sql = "SELECT *
            FROM minecraft_log.votes_log
            WHERE `username`='$uuid'
            AND `datetime`>='$checkdate'
            ORDER BY `vote_id` DESC
            LIMIT 150;";

    // run the query to retrieve the data
    $D = umc_mysql_fetch_all($sql);

    // send back the array of entries within time period
    return($D);
}

/**
 *
 * returns an html formatted table displaying the list of rolls and percentages for vote rolls
 *
 * @global array $lottery
 */
function umc_lottery_show_chances() {
    global $lottery;

    $sum = 0;
    $lastchance = 0;
    echo "<table>\n<tr><th>Prize</th><th style=\"text-align:right;\">Chance</th><th style=\"text-align:right;\">Numbers</th></tr>\n";

    foreach ($lottery as $data) {
        $chance = $data['chance'] / 10;
        $sum += $data['chance'];
        $num_txt = $sum;
        if ($lastchance + 1 <> $sum) {
            $num_txt = $lastchance + 1 . " - " . $num_txt;
        }
        echo "<tr><td>{$data['txt']}</td><td style=\"text-align:right;\">$chance %</td><td style=\"text-align:right;\">$num_txt</td></tr>";
        $lastchance = $sum;
    }

    $final_sum = $sum / 10;
    echo "<tr><td><strong>Sum:</strong></td><td style=\"text-align:right;\"><strong>$final_sum %</strong></td><td></td></tr>";
    echo "</table>";

}

function umc_lottery() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER, $lottery, $ENCH_ITEMS, $lottery_urls, $UMC_DATA;

    $user_input = trim($UMC_USER['args'][2]);

    // check if there is a valid, active user on the server before applying the vote.
    $user = umc_check_user($user_input);
    if (!$user) {
        umc_log("lottery", "voting", "user $user does not exist");
        return false;
    }

    // get the voting players uuid
    $uuid = umc_user2uuid($user);

    $active_check = umc_users_is_active($uuid);
    if (!$active_check) {
        umc_log("lottery", "voting", "user $user / $uuid is not an active user!");
        XMPP_ERROR_send_msg("user $user / $uuid is not an active user!");
        return false;
    }

    // check if user is online so we don't message someone who isn't there
    $user_is_online = false;
    if (isset($UMC_USER['online_players'][$uuid])) {
        $user_is_online = true;
    }

    // allow uncovery to test chance rolls for debugging purposes
    $chance = false;
    if (($user == 'uncovery') && (isset($UMC_USER['args'][3]))) {
        $chance = $UMC_USER['args'][3];
        umc_echo("Rolling a $chance!");
    }

    // get the roll array based on chance
    $roll = umc_lottery_roll_dice($chance);

    // define the rewards and item more legibly
    $item = $roll['item'];
    $luck = $roll['luck'];
    $prize = $lottery[$item];

    // get the metadata if required for the item
    if (isset($prize['detail'])) {
        $detail = $prize['detail'];
    }
    $type = $prize['type'];

    // based on item type, give reward to the player
    $non_deposit = array('additional_home', 'additional_deposit', 'vanity_title', 'random_unc');
    $give_data = 0;
    $give_type = 0;
    $give_amount = 1;
    $give_nbt = '';
    if (in_array($type, $non_deposit)) {
        $give_type = $type;
        switch ($type) {
            case 'additional_home':
                $newname = 'lottery' . "_" . umc_random_code_gen(4);
                umc_home_add($uuid, $newname, true);
                $item_txt = "an addtional home!!";
                break;
            case 'additional_deposit':
                umc_depositbox_create($uuid);
                $item_txt = "an addtional deposit box!!";
                break;
            case 'vanity_title':
                $current_title = umc_vanity_get_title();
                if ($current_title) {
                    return umc_lottery();
                }
                $luck2 = mt_rand(7, 14);
                umc_vanity_set($luck2, "I won the lottery!");
                $item_txt = "a vanity title fo $luck2 days!!";
                break;
            case 'random_unc':
                $luck2 = mt_rand(1, 500);
                umc_money(false, $user, $luck2);
                $item_txt = "$luck2 Uncs";
                break;
        }
    } else {
        // instantiate block variables
        switch ($type) {
            case 'item':
                $item_txt = $prize['txt'];
                $give_type = $detail['item_name'];
                $give_nbt = $detail['nbt'];
                break;
            case 'random_ench':
                // pick which enchantment
                $rand_ench = array_rand($ENCH_ITEMS);
                $rand_ench_type = $ENCH_ITEMS[$rand_ench]['key'];

                $ench_arr = $ENCH_ITEMS[$rand_ench];
                //pick which item to enchant
                $rand_item = array_rand($ench_arr['items']);
                $rand_item_id = $ench_arr['items'][$rand_item];
                // pick level of enchantment
                $lvl_luck = mt_rand(1, $ench_arr['max']);
                //echo "$item $ench_txt $lvl_luck";
                // '{RepairCost:7,Enchantments:[{lvl:1,id:"minecraft:silk_touch"},{lvl:5,id:"minecraft:efficiency"},{lvl:3,id:"minecraft:unbreaking"}]}'
                $ench_nbt = "{Enchantments:[{lvl:$lvl_luck,id:\"minecraft:$rand_ench_type\"}]}";
                $item = umc_goods_get_text($rand_item_id, 0, $ench_nbt);
                $item_name = $item['item_name'];
                $full = $item['full'];
                $item_txt = "a " . $full;
                $give_type = $item_name;
                $give_nbt = $ench_nbt;
                break;
            case 'random_pet': // same as blocks below but only 1 always
            case 'random_ore':
            case 'random_sapling':
                // umc_echo($type);
                $block = $prize['blocks'];
                $luck2 = mt_rand(0, count($prize['blocks']) - 1);
                $given_block = $block[$luck2];
                $give_nbt = "";
                $give_type = $given_block;
                $item = umc_goods_get_text($give_type, $give_data, $give_nbt);
                $item_txt = "a " . $item['full'];
                break;
            case 'random_item':
                $block = $UMC_DATA; // $prize['blocks'];
                $luck3 = 1; // mt_rand(1, 64);
                $stack = 0;
                while ($stack == 0) {
                    $give_type = array_rand($UMC_DATA);
                    $stack = $UMC_DATA[$give_type]['stack'];
                }
                $item = umc_goods_get_text($give_type);
                $item_txt = "$luck3 " . $item['full'];
                $give_amount = $luck3;
                break;                
            case 'random_potion':
                $types = array('lingering_potion', 'potion', 'splash_potion');
                $type_luck = mt_rand(0, count($types) - 1);
                $give_type = $types[$type_luck];
                global $UMC_POTIONS;
                $potion_luck = mt_rand(0, count($UMC_POTIONS) - 1);
                $potion_keys = array_keys($UMC_POTIONS);
                $potion_code = $potion_keys[$potion_luck];
                $item_txt = $give_type;
                $give_nbt = "{Potion:\"minecraft:$potion_code\"}";
                $give_amount = 1;
        }
        umc_deposit_give_item($uuid, $give_type, '', $give_nbt, $give_amount, 'lottery');
    }


    if ($user != 'uncovery') {// testing only
        $item_nocolor = umc_ws_color_remove($item_txt);
        umc_mod_broadcast("$user voted, rolled a $luck and got $item_nocolor!", 'asConsole');
        umc_log('votelottery', 'vote', "$user rolled $luck and got $item_nocolor ($give_type:$give_data)");
        $userlevel = umc_userlevel_get($uuid);
        if ($user_is_online && in_array($userlevel, array('Settler', 'Guest'))) {
            $msg = "You received $item_txt from the lottery! Use '/withdraw @lottery' to get it!";
            umc_mod_message($user, $msg);
        }

        // add vote to the database
        $service_raw = strtolower($UMC_USER['args'][3]);
        // fix service
        $search = array('http://www.', 'https://www.', 'http://', 'https://');
        $service_fixed = str_replace($search, '', $service_raw);
        $service = umc_mysql_real_escape_string($service_fixed);
        // sql log
        $sql_reward = umc_mysql_real_escape_string($type);
        $ip = umc_mysql_real_escape_string($UMC_USER['args'][4]);
        $uuid_sql = umc_mysql_real_escape_string($uuid);
        $sql = "INSERT INTO minecraft_log.votes_log (`username`, `datetime`, `website`, `ip_address`, `roll_value`, `reward`)
            VALUES ($uuid_sql, NOW(), $service, $ip, $luck, $sql_reward);";
        umc_mysql_query($sql, true);
    } else {
        $service_fixed = 0;
        XMPP_ERROR_trigger("$user voted, rolled a $luck and got $item_txt! ($give_type $give_nbt)");
    }

    if ($user_is_online) {
        // give reinforcing feedback - set subtitle (not displayed)
        $subtitle =  'title ' . $user . ' subtitle {"text":"Thanks for your vote!","color":"gold"}';
        umc_ws_cmd($subtitle, 'asConsole');
    }

    // find the right reward uncs amount
    foreach ($lottery_urls as $L) {
        if ($service_fixed == $L['id']) {
            $reward = $L['val'];
            umc_money(false, $user, $reward);
            // display the feedback - displays subtitle AND title
            if ($user_is_online) {
                $title = 'title ' . $user . ' title {"text":"+'. $reward. ' Uncs","color":"gold"}';
                umc_ws_cmd($title, 'asConsole');
            }
            // and exit
            return;
        }
    }
}

// returns an array with the item and roll value
function umc_lottery_roll_dice($chance = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $lottery;

    // vars set to 0 :S
    $rank = 0;
    $lastrank = 0;

    // if chance is defined, set roll to chance
    if ($chance) {
        $roll = $chance;
    } else {
        // TODO - range should be defined by count of chances in lottery array
        $roll = mt_rand(1, 1000);
    }

    XMPP_ERROR_trace("Rolled: ", $roll);
    // set last_item to false why
    $last_item = false;

    // iterate through lottery array of data
    foreach ($lottery as $item => $data) {
        // while last item flag is false
        if (!$last_item) {
            $last_item = $item; // set last item to current item in lottery array
        }

        $chance = $data['chance']; // get the chance of the item roll
        $rank = $rank + $chance; // add chance to running total

        $txt_rank = $lastrank + 1; // we fix since we use > to compare
        XMPP_ERROR_trace("Chance check between $txt_rank and $rank");
        // if roll matches the item chances range
        if ($roll <= $rank && $roll > $lastrank) {
            return array('item' => $item, 'luck' => $roll); // return the item and the roll
        }

        $lastrank = $rank ; // set lastrank to running total of chance
        $last_item = $item; // set the last item to item currently iterated
    }
    // we should not arrive here in any case
    XMPP_ERROR_trigger("Dice roll ($roll) in lottery did not match lottery item!");
}


function umc_lottery_lot_fix_time($datetime) {
    if (!strstr($datetime, ':')) { // unix timestamp
        $datetime = "@$datetime";
        if (strlen($datetime) > 10) {
            $datetime = substr($datetime, 0, 11);
        }
        $date_new = new DateTime($datetime);
    } else {
        $pieces = explode(" ", $datetime);
        if (count($pieces) == 2) {
            $date_new = new DateTime($datetime);
        } else {
            // 2014-07-18 09:07:48 -0700
            $date_new = DateTime::createFromFormat('Y-m-d H:i:s T', $datetime);
        }
    }
    if (!$date_new) {
        XMPP_ERROR_trigger("Error: failed to parse date format $datetime (umc_lottery_lot_fix_time)");
    }
    $date_new->setTimezone(new DateTimeZone('Asia/Hong_Kong'));
    $time = $date_new->format('Y-m-d H:i:s');
    return $time;
}

function umc_lottery_web_stats() {
    // get a timestamp 6 months ago
    $old_date = date("Y-m-d H:i:s", strtotime("-6 months"));
    $yesterday = date("Y-m-d H:i:s", strtotime("-1 day"));

    $sql = "SELECT count( vote_id ) AS vote_count, website, DATE_FORMAT(`datetime`,'%Y-%m-%d') AS date
        FROM minecraft_log.votes_log
        WHERE website <> 'minecraftservers' AND datetime > '$old_date' AND datetime < '$yesterday'
        GROUP BY website, DAY( `datetime` ),MONTH( `datetime` ), YEAR( `datetime` )
        ORDER BY YEAR( `datetime` ) , MONTH( `datetime` ), DAY( `datetime` ) ";

    $D = umc_mysql_fetch_all($sql);
    $out = '<h2>Voting stats for the last 6 months:</h2>';
    $ydata = array();

    foreach ($D as $row) {
        if ($row['website'] == 'mcsl') {
            $site = 'minecraft-server-list.com';
        } else if ($row['website'] == '') {
            $site = 'unknown';
        } else {
            $site = $row['website'];
        }
        $ydata[$row['date']][$site] = $row['vote_count'];
    }

    $out .= umc_web_javachart($ydata, 'date', 'regular');
    return $out;
}
