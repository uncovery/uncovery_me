<?php

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
            'level'=>'Owner',
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
        'chance' => 10,
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
        'chance' => 1,
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
        'chance' => 7,
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
        'chance' => 5,
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
        'chance' => 1,
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
        'chance' => 1,
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
        'chance' => 1,
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
        'chance' => 1,
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
        'chance' => 7,
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
        'chance' => 1,
        'type' => 'random_pet',
        'data' => 'pet',
        'txt' => 'a random Animal Egg',
        'blocks' => array(
            'spawn_egg:90', 'spawn_egg:91', 'spawn_egg:92', 'spawn_egg:93', 'spawn_egg:94',
            'spawn_egg:95', 'spawn_egg:96', 'spawn_egg:98', 'spawn_egg:100', 'spawn_egg:120',
        ),
    ),
    'random_unc' => array(
        'chance' => 18,
        'type' => 'random_unc',
        'data' => 'unc',
        'txt' => 'a random amount of Uncs (max 500)',
    ),
    'random_common' => array(
        'chance' => 20,
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
        'chance' => 5,
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
        'chance' => 15,
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
        'chance' => 8,
        'type' => 'random_ench',
        'data' => 'enchanted item',
        'txt' => 'a random single-enchanted item',
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
        echo "<tr><td>{$data['txt']}</td><td>{$data['chance']}%</td><td>$num_txt</td></tr>";
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
    //  umc_error_notify("User $user, $chance (umc_lottery)");
    global $UMC_USER, $lottery, $ENCH_ITEMS;

    $user_input = $UMC_USER['args'][2];
    $user = umc_check_user($user_input);
    if (!$user) {
        umc_log("lottery", "voting", "user $user does not exist");
        return false;
    }
    $uuid = umc_user2uuid($user);
    $chance = false;
    if (($user == 'uncovery') && (isset($UMC_USER['args'][3]))) {
        $chance = $UMC_USER['args'][3];
    }

    $roll = umc_lottery_roll_dice($chance);
    // umc_echo(umc_ws_vardump($roll));
    $item = $roll['item'];
    $luck = $roll['luck'];

    $prize = $lottery[$item];
    //echo "type = {$prize['type']}<br>;";

    //echo "complete chance: $chance<br>;";
    //var_dump($prize);
    if (isset($prize['detail'])) {
        $detail = $prize['detail'];
    }
    $type = $prize['type'];

    // always give 100 uncs
    umc_money(false, $user, 100);

    $given_block_data = 0;
    $given_block_type = 0;
    //var_dump($prize);
    switch ($type) {
        case 'item':
            umc_deposit_give_item($uuid, $detail['type'], $detail['data'],  $detail['ench'], 1, 'lottery');
            $item_txt = $prize['txt'];
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
    // echo "$user voted for the server and got $item_txt!;";
}

function umc_lottery_roll_dice($chance = false) {
    global $lottery;
    $rank = 0;
    $lastrank = 0;
    if ($chance) {
        $luck = $chance;
    } else {
        $luck = mt_rand(1, 100);
    }
    //echo "You drew the lucky number $luck!;";
    //echo "luck = $luck<br>";
    $last_item = false;
    foreach ($lottery as $item => $data) {
        if (!$last_item) {
            $last_item = $item;
        }
        $chance = $data['chance'];
        $rank = $rank + $chance;
        //$ranking[$item] = $rank;
        if ($luck <= $rank && $luck > $lastrank) {
            return array('item' => $item, 'luck' => $luck);
        }
        $lastrank = $rank;
        $last_item = $item;
    }
}

function umc_lottery_log_import() {
    global $UMC_PATH_MC;
    $filename = "$UMC_PATH_MC/server/bukkit/plugins/Votifier/votes.log";
    $temp_name = "$UMC_PATH_MC/server/bukkit/plugins/Votifier/parsing.log";
    // move file somewhere else
    rename($filename, $temp_name);
    $pattern = '/Vote \(from:(.*) username:(.*) address:(.*) timeStamp:(.*)\)/';
    $handle = @fopen($temp_name, "r");
    if ($handle) {
        $count = 0;
        while (($line = fgets($handle)) !== false) {
            // Vote (from:Minecraft-Server-List.com username:SleepyStrangeKid address:108.162.221.10 timeStamp:1383485842)
            // Vote (from:Minestatus username:A_Silent_Winter address:173.137.154.46 timeStamp:2013-11-03 05:44:00 -0800)
            preg_match($pattern, $line, $matches);

            $date_time = umc_lottery_lot_fix_time($matches[4]);
            // echo $matches[4] . " => $date_time <br>";
            $sql = "INSERT INTO minecraft_log.votes_log (`username`, `datetime`, `website`, `ip_address`)
                VALUES ('{$matches[2]}', '$date_time', '{$matches[1]}', '{$matches[3]}');";
            umc_mysql_query($sql, true);
            $count++;
        }
        if (!feof($handle)) {
            XMPP_ERROR_trigger("Error: unexpected fgets() fail (umc_lottery_log_import)");
        }
        fclose($handle);
    }
    unlink($temp_name);
    umc_log('lottery', "import", "imported $count lines of votes");
}

/**
 * this function tests different date format to make sure they are properly parsed into the database
 */
function umc_test_lottery_date() {
    $date = '2014-07-23 03:31:37 -0700';
    echo umc_lottery_lot_fix_time($date) . "<br>";
    $date = '1406488618339';
    echo umc_lottery_lot_fix_time($date) . "<br>";
    $date = '2014-07-23 03:31:37';
    echo umc_lottery_lot_fix_time($date) . "<br>";
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

    // count top voters
    /*
    $top_sql = "SELECT count(vote_id) as counter, username FROM minecraft_log.votes_log GROUP BY username ORDER BY count(vote_id) DESC LIMIT 10;";
    $top_rst = mysql_query($top_sql);

    $out .= "Top Ten voters:\n<table style=\"width=50%\">\n"
        . "<tr><td>Rank</td><td>Username</td><td>Votes</td></tr>\n";
    $rank = 1;
    while ($row = mysql_fetch_array($top_rst)) {
        $out .="<tr><td>$rank</td><td>{$row['username']}</td><td>{$row['counter']}</td></tr>\n";
        $rank++;
    }
    $out .= "</table>\n";
    */
    return $out;
}
?>
