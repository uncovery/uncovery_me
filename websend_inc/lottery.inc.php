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
    'default' => array(
        'help' => array(
            'title' => 'Voting Lottery',  // give it a friendly title
            'short' => 'Vote for the server and win prizes',  // a short description
            'long' => "You can vote for the lottery and win 100 Uncs + a random prize. See $UMC_DOMAIN/vote-for-us/ for detailss", // a long add-on to the short  description
            ),
    ),
    'vote' => array( // this is the base command if there are no other commands
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
    'disabled' => false,
    'events' => array(
        'PlayerJoinEvent' => 'umc_lottery_reminder',
    ),
);

global $lottery;

$lottery = array(
    'diamond' => array(
        'chance' => 1000,
        'type' => 'item',
        'data' => 264,
        'txt' => 'a shiny, tiny, diamond',
        'detail' => array(
            'type' => 264,
            'data' => 0,
            'ench' => '',
        ),
    ),
    'diamondblock' => array(
        'chance' => 100,
        'type' => 'item',
        'data' => 57,
        'txt' => 'an ugly, heavy diamond block',
        'detail' => array(
            'type' => 57,
            'data' => 0,
            'ench' => '',
        ),
    ),
    'goldenapple' => array(
        'chance' => 700,
        'type' => 'item',
        'data' => 322,
        'txt' => 'a shiny golden apple (Yum!)',
        'detail' => array(
            'type' => 322,
            'data' => 0,
            'ench' => '',
        ),
    ),
    'cake' => array(
        'chance' => 500,
        'type' => 'item',
        'data' => 354,
        'txt' => 'an entire cake (Happy Cakeday!)',
        'detail' => array(
            'type' => 354,
            'data' => 0,
            'ench' => '',
        ),
    ),
    'coal' => array(
        'chance' => 100,
        'type' => 'item',
        'data' => 263,
        'txt' => 'a NOT shiny piece of coal',
        'detail' => array(
            'type' => 263,
            'data' => 0,
            'ench' => '',
        ),
    ),
    'enchanted_pick' => array(
        'chance' => 100,
        'type' => 'item',
        'data' => '270 1 DIG_SPEED:5 SILK_TOUCH:1 LOOT_BONUS_BLOCKS:3',
        'txt' => 'a super-enchanted wooden pickaxe',
        'detail' => array(
            'type' => 270,
            'data' => 0,
            'ench' => array(
                'DIG_SPEED' => 5,
                'SILK_TOUCH' => 1,
                'LOOT_BONUS_BLOCKS' => 3,
            ),
        ),
    ),
    'enchanted_sword' => array(
        'chance' => 100,
        'type' => 'item',
        'data' => '268 1 DAMAGE_ALL:5 KNOCKBACK:2 FIRE_ASPECT:2 LOOT_BONUS_MOBS:3',
        'txt' => 'a super-enchanted wooden sword',
        'detail' => array(
            'type' => 268,
            'data' => 0,
            'ench' => array(
                'DAMAGE_ALL' => 5,
                'KNOCKBACK' => 2,
                'FIRE_ASPECT' => 2,
                'LOOT_BONUS_MOBS' => 3,
            ),
        ),
    ),
    'dirtblock' => array(
        'chance' => 100,
        'type' => 'item',
        'data' => 3,
        'txt' => 'a big block of extra-fine dirt',
        'detail' => array(
            'type' => 3,
            'data' => 0,
            'ench' => '',
        ),
    ),
    'cookie' => array(
        'chance' => 700,
        'type' => 'item',
        'data' => 357,
        'txt' => 'a hot cookie (OUCH!)',
        'detail' => array(
            'type' => 357,
            'data' => 0,
            'ench' => '',
        ),
    ),
    'random_pet' => array(
        'chance' => 100,
        'type' => 'random_pet',
        'data' => 'pet',
        'txt' => 'a random Animal Egg',
        'blocks' => array(
            'spawn_egg:90', 'spawn_egg:91', 'spawn_egg:92', 'spawn_egg:93', 'spawn_egg:94',
            'spawn_egg:95', 'spawn_egg:96', 'spawn_egg:98', 'spawn_egg:100', 'spawn_egg:120',
        ),
    ),
    'random_unc' => array(
        'chance' => 1800,
        'type' => 'random_unc',
        'data' => 'unc',
        'txt' => 'a random amount of Uncs (max 500)',
    ),
    'random_common' => array(
        'chance' => 2000,
        'type' => 'random_common',
        'data' => 'common',
        'txt' => '1-64 of random common block',
        'blocks' => array(
            'grass:0', 'dirt:0', 'cobblestone:0', 'planks:0', 'planks:1', 'planks:2',
            'planks:3', 'planks:4', 'planks:5', 'sand:0', 'gravel:0', 'log:0', 'log:1',
            'log:2', 'log:3', 'log2:0', 'log2:1', 'sandstone:0', 'netherrack:0',
            'soul_sand:0', 'mycelium:0',
        ),
    ),
    'random_ore' => array(
        'chance' => 500,
        'type' => 'random_ore',
        'data' => 'ore',
        'txt' => '1-64 of random rare block',
        'blocks' => array(
            'coal_ore:0', 'iron_ore:0', 'lapis_ore:0', 'mossy_cobblestone:0', 'diamond_ore:0',
            'redstone_ore:0', 'ice:0', 'snow:0', 'clay:0', 'glowstone:0', 'pumpkin:0', 'stonebrick:0',
            'mycelium:0', 'nether_brick:0', 'emerald_ore:0', 'end_stone:0', 'redstone_block:0',
            'quartz_ore:0', 'quartz_block:0', 'coal_block:0', 'packed_ice:0',
        ),
    ),
    'random_manuf' => array(
        'chance' => 1500,
        'type' => 'random_manuf',
        'data' => 'man',
        'txt' => '1-64 of random manufactured block',
        'blocks' => array(
            'glass:0', 'dispenser:0', 'noteblock:0', 'golden_rail:0', 'detector_rail:0',
            'sticky_piston:0', 'piston:0', 'wool:0', 'stone_slab:0', 'brick_block:0',
            'bookshelf:0', 'obsidian:0', 'chest:0', 'furnace:0', 'ladder:0', 'rail:0',
            'stone_stairs:0', 'jukebox:0', 'lit_pumpkin:0', 'stained_glass:0',
            'stained_glass:1', 'stained_glass:2', 'stained_glass:3', 'stained_glass:4',
            'stained_glass:5', 'stained_glass:6', 'stained_glass:7', 'stained_glass:8',
            'stained_glass:9', 'stained_glass:10', 'stained_glass:11', 'stained_glass:12',
            'stained_glass:13', 'stained_glass:14', 'stained_glass:15', 'redstone_lamp:0',
        ),
    ),
    'random_ench' => array(
        'chance' => 690, // rate of 69 in 1000
        'type' => 'random_ench',
        'data' => 'enchanted item',
        'txt' => 'a random single-enchanted item',
    ),
    'additional_home' => array(
        'chance' => 10, // rate of 1 in 1000
        'type' => 'additional_home',
        'data' => 'home',
        'txt' => '{green} an additional home!',
    ),
);

function umc_lottery_reminder() {
    global $UMC_USER, $UMC_DOMAIN;
    $player = $UMC_USER['username'];

    $sql = "SELECT count(vote_id) as counter FROM minecraft_log.votes_log WHERE `username`='$player' AND TIMESTAMPDIFF(HOUR, datetime, NOW()) < 24 ORDER BY `vote_id` DESC  ";
    $D = umc_mysql_fetch_all($sql);
    $counter = $D[0]['counter'];
    if ($counter < 5) {
        umc_echo ("NOTE: You have voted only $counter times in the 24 hours before the last restart. "
            . "Please vote: $UMC_DOMAIN/vote-for-us/");
    }
}

function umc_lottery_show_chances() {
    global $lottery;
    $sum = 1;
    echo "<table>\n<tr><th>Prize</th><th>Chance</th><th>Numbers</th></tr>\n";
    foreach ($lottery as $data) {
        $temp = $sum + $data['chance'] - 1;
        if ($sum == $temp) {
            $num_txt = $sum;
        } else {
            $num_txt = "$sum - $temp";
        }
        echo "<tr><td>{$data['txt']}</td><td>{$data['chance']}</td><td>$num_txt</td></tr>";
        $sum = $sum + $data['chance'];
/*        if (isset($data['blocks'])) {
            echo "<tr><td colspan=3>";
            foreach ($data['blocks'] as $block) {
                $item_id = umc_get_id($block, 0);
                echo $item_id['name'] . " ";
            }
            echo "</td></tr>";
        }
*/
    }
    $sum--;
    echo "<tr><td>Sum:</td><td>$sum%</td><td></td></tr>";
    echo "</table>";

}

function umc_lottery() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER, $lottery, $ENCH_ITEMS;

    $user_input = $UMC_USER['args'][2];

    // check if there is a valid user on the server before applying the vote.
    $user = umc_check_user($user_input);
    if (!$user) {
        umc_log("lottery", "voting", "user $user does not exist");
        return false;
    }

    // get the voting players uuid
    $uuid = umc_user2uuid($user);

    // give reinforcing feedback - set subtitle (not displayed)
    $subtitle =  'title ' . $user . ' subtitle {text:"Thanks for your vote!",color:gold}';
    umc_ws_cmd($subtitle, 'asConsole');

    // display the feedback - displays subtitle AND title
    $title = 'title ' . $user . ' title {text:"+100 Uncs",color:gold}';
    umc_ws_cmd($title, 'asConsole');

    // allow uncovery to test chance rolls for debugging purposes
    $chance = false;
    if (($user == 'uncovery') && (isset($UMC_USER['args'][5]))) {
        $chance = $UMC_USER['args'][5];
    }

    // get the roll array based on chance
    $roll = umc_lottery_roll_dice($chance);

    // umc_echo(umc_ws_vardump($roll));

    // define the rewards and item more legibly
    $item = $roll['item'];
    $luck = $roll['luck'];
    $prize = $lottery[$item];

    //echo "type = {$prize['type']}<br>;";

    //echo "complete chance: $chance<br>;";
    //var_dump($prize);

    // get the metadata if required for the item
    if (isset($prize['detail'])) {
        $detail = $prize['detail'];
    }
    $type = $prize['type'];

    // always give 100 uncs irrespective of roll.
    umc_money(false, $user, 100);

    // instantiate block variables
    $given_block_data = 0;
    $given_block_type = 0;

    //var_dump($prize);

    // based on item type, give reward to the player
    switch ($type) {
        case 'item':
            umc_deposit_give_item($uuid, $detail['type'], $detail['data'],  $detail['ench'], 1, 'lottery');
            $item_txt = $prize['txt'];
            break;
        case 'additional_home':
            umc_home_add($uuid, 'lottery');
            $item_txt = "an addtional home!!";
            break;
        case 'random_unc':
            $luck2 = mt_rand(1, 500);
            umc_money(false, $user, $luck2);
            $item_txt = "$luck2 Uncs";
            break;
        case 'random_potion':
            $luck2 = mt_rand(0, 63);
            umc_deposit_give_item($uuid, 373, $luck2, '', 1, 'lottery');
            $item_txt = $prize['txt'];
            break;
        case 'random_ench':
            // pick which enchantment
            $rand_ench = array_rand($ENCH_ITEMS);

            $ench_arr = $ENCH_ITEMS[$rand_ench];
            //pick which item to enchant
            $rand_item = array_rand($ench_arr['items']);
            $rand_item_id = $ench_arr['items'][$rand_item];
            // pick level of enchantment
            $lvl_luck = mt_rand(1, $ench_arr['max']);
            //echo "$item $ench_txt $lvl_luck";
            $item_ench_arr = array($rand_ench => $lvl_luck);
            $item = umc_goods_get_text($rand_item_id, 0, $item_ench_arr);
            $item_name = $item['item_name'];
            $full = $item['full'];
            umc_deposit_give_item($uuid, $item_name, 0, $item_ench_arr, 1, 'lottery');
            $item_txt = "a " . $full;
            break;
        case 'random_pet': // same as blocks below but only 1 always
            umc_echo($type);
            $block = $prize['blocks'];
            $luck2 = mt_rand(0, count($prize['blocks']) - 1);
            $given_block = explode(":", $block[$luck2]);
            $given_block_type = $given_block[0];
            $given_block_data = $given_block[1];
            umc_deposit_give_item($uuid, $given_block_type, $given_block_data, '', 1, 'lottery');
            $item = umc_goods_get_text($given_block_type, $given_block_data);
            $item_txt = "a " .$item['full'];
            break;
        case 'random_common':
        case 'random_ore':
        case 'random_manuf':
            $block = $prize['blocks'];
            $luck2 = mt_rand(0, count($prize['blocks']) - 1);
            $luck3 = mt_rand(1, 64);
            $given_block = explode(":", $block[$luck2]);
            $given_block_type = $given_block[0];
            $given_block_data = $given_block[1];
            umc_deposit_give_item($uuid, $given_block_type, $given_block_data, '', $luck3, 'lottery');
            $item = umc_goods_get_text($given_block_type, $given_block_data);
            $item_txt = "$luck3 " . $item['full'];
            break;
    }
    if ($user != 'uncovery') {// testing only
        $item_nocolor = umc_ws_color_remove($item_txt);
        umc_ws_cmd("ch qm N $user voted, rolled a $luck and got $item_nocolor!", 'asConsole');
        umc_log('votelottery', 'vote', "$user rolled $luck and got $item_nocolor ($given_block_type:$given_block_data)");
        $userlevel = umc_get_userlevel($user);
        if (in_array($userlevel, array('Settler', 'Guest'))) {
            $msg = "You received $item_txt from the lottery! Use {green}/withdraw @lottery{white} to get it!";
            umc_msg_user($user, $msg);
        }
    } else {
        umc_echo("$user voted, rolled a $luck and got $item_txt!");
    }
    // add vote to the database
    $service_raw = umc_mysql_real_escape_string($UMC_USER['args'][3]);
    // fix service
    $search = array('http://www.', 'https://www.', 'http://', 'https://');
    $service= str_replace($search, '', $service_raw);
    $ip = umc_mysql_real_escape_string($UMC_USER['args'][4]);
    $sql = "INSERT INTO minecraft_log.votes_log (`username`, `datetime`, `website`, `ip_address`)
        VALUES ('$uuid', NOW(), $service, $ip);";
    umc_mysql_query($sql, true);
    XMPP_ERROR_trigger("Vote done!");
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
        $roll = mt_rand(1, 10000);
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

        XMPP_ERROR_trace("Chance check between $lastrank and $rank");
        // if roll matches the item chances range
        if ($roll <= $rank && $roll > $lastrank) {
            return array('item' => $item, 'luck' => $roll); // return the item and the roll
        }

        $lastrank = $rank; // set lastrank to running total of chance
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
    global $UMC_DOMAIN;
    $sql = "SELECT count( vote_id ) AS vote_count, website, DATE_FORMAT(`datetime`,'%Y-%m-%d') AS date
        FROM minecraft_log.votes_log
        WHERE website <> 'minecraftservers'
        GROUP BY website, DAY( `datetime` ),MONTH( `datetime` ), YEAR( `datetime` )
        ORDER BY YEAR( `datetime` ) , MONTH( `datetime` ), DAY( `datetime` ) ";

    $D = umc_mysql_fetch_all($sql);
    $out = '';
    $maxval = 0;
    $minval = 0;
    $legend = array();
    $ydata = array();
    $sites = array();

    $out .= "<script type='text/javascript' src=\"$UMC_DOMAIN/admin/js/amcharts.js\"></script>\n"
        . "<script type='text/javascript' src=\"$UMC_DOMAIN/admin/js/serial.js\"></script>\n"
        . "<div id=\"chartdiv\" style=\"width: 100%; height: 362px;\"></div>\n"
        . "<script type='text/javascript'>//<![CDATA[\n"
        . "var chart;\n"
        . "var chartData = [\n";
    //
    foreach ($D as $row) {
        $maxval = max($maxval, $row['vote_count']);
        $minval = min($minval, $row['vote_count']);
        $date = $row['date'];
        $legend[$date] = $date;
        if ($row['website'] == 'MCSL') {
            $site = 'Minecraft-Server-List.com';
        } else {
            $site = $row['website'];
        }
        $sites[$site] = $site;
        $ydata[$date][$site] = $row['vote_count'];
    }

    foreach ($ydata as $date => $date_sites) {
        $out .= "{\"date\": \"$date\",";
        foreach ($date_sites as $date_site => $count) {
            $out .= "\"$date_site\": $count,";
        }
        $out .= "},\n";
    }
    $out .= "];\n";

    $out .= 'AmCharts.ready(function () {
    // SERIAL CHART
    chart = new AmCharts.AmSerialChart();
    chart.pathToImages = "http://www.amcharts.com/lib/3/images/";
    chart.dataProvider = chartData;
    chart.marginTop = 10;
    chart.categoryField = "date";

    // AXES
    // Category
    var categoryAxis = chart.categoryAxis;
    categoryAxis.gridAlpha = 0.07;
    categoryAxis.axisColor = "#DADADA";
    categoryAxis.startOnAxis = true;

    // Value
    var valueAxis = new AmCharts.ValueAxis();
    valueAxis.stackType = "regular"; // this line makes the chart "stacked"
    valueAxis.gridAlpha = 0.07;
    valueAxis.title = "Votes";
    chart.addValueAxis(valueAxis);';

    foreach ($sites as $site) {
        $out .= "var graph = new AmCharts.AmGraph();
        graph.type = \"line\";
        graph.hidden = false;
        graph.title = \"$site\";
        graph.valueField = \"$site\";
        graph.lineAlpha = 1;
        graph.fillAlphas = 0.6; // setting fillAlphas to > 0 value makes it area graph
        graph.balloonText = \"<span style=\'font-size:12px; color:#000000;\'>$site: <b>[[value]]</b></span>\";
        chart.addGraph(graph);";
    }

    $out .= '// LEGEND
        var legend = new AmCharts.AmLegend();
        legend.position = "top";
        legend.valueText = "[[value]]";
        legend.valueWidth = 100;
        legend.valueAlign = "left";
        legend.equalWidths = false;
        legend.periodValueText = "total: [[value.sum]]"; // this is displayed when mouse is not over the chart.
        chart.addLegend(legend);

        // CURSOR
        var chartCursor = new AmCharts.ChartCursor();
        chartCursor.cursorAlpha = 0;
        chart.addChartCursor(chartCursor);

        // SCROLLBAR
        var chartScrollbar = new AmCharts.ChartScrollbar();
        chartScrollbar.color = "#FFFFFF";
        chart.addChartScrollbar(chartScrollbar);

        // WRITE
        chart.write("chartdiv");
        });
        //]]></script>';

    return $out;
}
?>
