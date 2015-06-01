<?php

function umc_shopmgr_main() {
    global $UMC_USER, $UMC_DOMAIN;
    // $s_post  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
    $s_get  = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);

    if (!$UMC_USER) {
        return "You have to be logged in to see this!";
    }

    if (!isset($s_get['page'])) {
        $sel_page = 'deposit';
    } else {
        $sel_page = $s_get['page'];
    }

    $out = '';
    if (!$UMC_USER) {
        return "<strong>You need to be <a href=\"$UMC_DOMAIN/wp-login.php\">logged in</a></strong> to see this!\n";
    } else {
        $username = $UMC_USER['username'];
        $userlevel = $UMC_USER['userlevel'];
    }
    $balance = number_format(umc_money_check($username), 2, ".", "'");
    $out .= "Welcome $username ($userlevel)! You currently have $balance Uncs.<br>";
    $pages = array(
        'deposit' => "shopmgr_show_deposit",
        'goods' => "shopmgr_items",
        'offers' => "shopmgr_offers",
        'requests' => "shopmgr_requests",
        'users' => "shopmgr_transactions",
        'statistics' => "shopmgr_stats",
        'deposit_help' => "shopmgr_show_help_deposit",
        'shop_help' => "shopmgr_show_help_shop",
    );
    $out .= '<ul class="lot_tabs">' . "\n";

    foreach ($pages as $page => $function) {
        $page_name = umc_pretty_name($page);
        if ($page == $sel_page) {
            $out .= "<li class=\"active_world\">$page_name</li>";
        } else {
            $out .= "<li><a href=\"?page=$page\">$page_name</a></li>";
        }
    }
    $out .= "</ul><br>";

    $out .= "<div class=\"formbox\"><form class=\"lotform\">";
    $function = 'umc_' . $pages[$sel_page];
    if (function_exists($function)) {
        $out .= $function();
    } else {
        XMPP_ERROR_trigger("Shop manager function $function for $sel_page invalid!");
        echo "Page $function for $sel_page not found!";
    }

    $out .= "</form></div>";
    return $out;
}

function umc_shopmgr_show_deposit() {
    global $UMC_USER;

    if (!$UMC_USER) {
        return "You have to be logged in to see this";
    }

    // $player = $UMC_USER['username'];
    $uuid = $UMC_USER['uuid'];
    $sql = "SELECT id, concat(item_name,'|',damage, '|', meta) AS item, s_link.username as sender, amount as quantity, r_link.username as recipient, date "
        . "FROM minecraft_iconomy.deposit "
        . "LEFT JOIN minecraft_srvr.UUID as s_link ON sender_uuid=s_link.UUID "
        . "LEFT JOIN minecraft_srvr.UUID as r_link ON recipient_uuid=r_link.UUID "
        . "WHERE sender_uuid='$uuid' OR recipient_uuid='$uuid' "
        . "ORDER BY id, damage, amount DESC;";
    $data_rst = mysql_query($sql);

    $non_numeric_columns = array('item', 'sender', 'recipient');
    $sort_column = "1, 'desc'";
    $check = umc_web_table('deposit', $sort_column, $data_rst, $pre_table = '', array(), $non_numeric_columns);
    if (!$check) {
        XMPP_ERROR_trigger("Error creating web_table with SQL $sql");
        return "Error creating data table. Admin was notified, please wait until it is fixed";
    } else {
        return $check;
    }
}

function umc_shopmgr_items() {
    global $UMC_DATA;

    $s_get = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
    $non_numeric_cols = array('item_name');
    $items = array();
    // get all data
    if (!isset($s_get['item']) || !isset($UMC_DATA[$s_get['item']])) {
        foreach ($UMC_DATA as $name => $data) {
            // $item = umc_goods_get_text($name);
            $variants = '';
            $title = $name;
            $sub_id = 0;
            $sub_text = '';
            $sub_count = 0;
            if (isset($data['group'])) {
                $variants = "(" . count($data['subtypes']) . " types)";
                $title = $data['group'];
                $sub_count = count($data['subtypes']);
                $sub_text = "$sub_count sub-types";
                $sub_id = '?';
            }
            // get stock
            $stock_amount = umc_shop_count_amounts('stock', $name);
            $request_amount = umc_shop_count_amounts('request', $name);

            if ($data['avail']) {
                $items[$name] = array(
                    'item_name' => "$name|$sub_id|",
                    'sub_types'=> $sub_text,
                    'stack_size' => $data['stack'],
                    'stock' => $stock_amount,
                    'requests' => $request_amount
                );
            }
        }
        return umc_web_table("goods", "0, 'asc'", $items, '', array(), $non_numeric_cols);
    // get only one item's sub-items
    } else if (isset($s_get['item']) && isset($UMC_DATA[$s_get['item']])) {
        // get only one subitem
        $item_name = $s_get['item'];
        if (isset($s_get['type']) && (($s_get['type'] == '0') || (isset($UMC_DATA[$item_name]['subtypes'][$s_get['type']])))) {
            $item_type = $s_get['type'];
            $item = umc_goods_get_text($s_get['item'], $item_type);
            $out = "<h2>Requests for " . $item['full'] . "</h2>"
                . umc_shopmgr_requests("request.item_name = '$item_name' AND request.damage = '$item_type'")
                . "<h2>Offers for " . $item['full'] . "</h2>"
                . umc_shopmgr_offers("stock.item_name = '$item_name' AND stock.damage = '$item_type'")
                . "<h2>Price history for " . $item['full'] . "</h2>"
                . umc_shopmgr_item_stats($item_name, $item_type);
            return $out;
        } else {
        // we are looking for a specific item, so let's get it as a header
            if (!isset($s_get['type']) || ($s_get['type']==0 && !isset($UMC_DATA[$s_get['item']]['subtypes'][$s_get['type']]))) {
                $item_type = 0;
            } else {
                $item_type = $s_get['type'];
            }
            
            $stock_amount = umc_shop_count_amounts('stock', $item_name, $item_type);
            $request_amount = umc_shop_count_amounts('request', $item_name, $item_type);
            $items[0] = array('item_name' => "$item_name|$item_type|", 'stock' => $stock_amount, 'requests' => $request_amount);
            // stuff has sub items, display those
            if (isset($UMC_DATA[$item_name]['subtypes'])) {
                foreach ($UMC_DATA[$item_name]['subtypes'] as $type => $data) {
                    //if ($UMC_DATA[$s_get['item']]['subtypes'][$s_get['type']]['avail']) {
                        $stock_amount = umc_shop_count_amounts('stock', $item_name, $type);
                        $request_amount = umc_shop_count_amounts('request', $item_name, $type);
                        $items[$type] = array('item_name' => "$item_name|$type|", 'stock' => $stock_amount, 'requests' => $request_amount);
                    //}
                }
            // no sub items, display data
            } else {
                
            }
            return umc_web_table("goods", "0, 'asc'", $items, '', array(), $non_numeric_cols);
        }
    }
}

function umc_shopmgr_item_stats($item, $type) {
    global $UMC_DOMAIN;
    $sql = "SELECT `damage`, AVG(cost / amount) as price, `meta`, `item_name`, DATE_FORMAT(`date`,'%Y-%u') as week "
        . "FROM minecraft_iconomy.transactions "
        . "WHERE item_name='$item' AND damage='$type' AND cost > 0 AND seller_uuid NOT LIKE 'cancel%' AND buyer_uuid NOT LIKE 'cancel%' AND date > '0000-00-00 00:00:00'  "
        . "GROUP BY week ";
    $rst = umc_mysql_query($sql);

    $out = "<script type='text/javascript' src=\"$UMC_DOMAIN/admin/js/amcharts.js\"></script>\n"
        . "<script type='text/javascript' src=\"$UMC_DOMAIN/admin/js/serial.js\"></script>\n"
        . "<div id=\"chartdiv\" style=\"width: 100%; height: 362px;\"></div>\n"
        . "<script type='text/javascript'>//<![CDATA[\n"
        . "var chart;\n"
        . "var chartData = [\n";
    //
    $sum = 0;
    $count = 0;
    while ($row = umc_mysql_fetch_array($rst, MYSQL_ASSOC)) {
        //$maxval_amount = max($maxval_amount, $row['amount']);
        //$maxval_value = max($maxval_value, $row['value']);
        $date = $row['week'];
        $price = $row['price'];
        // {"date": "2013-15","Amount": 121304,"Value": 72679,},
        $out .= "{\"date\": \"$date\",\"price\": \"$price\"},\n";
        $count++;
        $sum += $price;
    }
    $out .= "];\n";

    // $average = $sum / $count;
    
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

    // Amount
    var valueAxis = new AmCharts.ValueAxis();
    valueAxis.id = "Avg Price per week";
    valueAxis.gridAlpha = 0.07;
    valueAxis.title = "Price";
    valueAxis.position = "right";
    chart.addValueAxis(valueAxis);
    var graph = new AmCharts.AmGraph();
    graph.valueAxis = "price"
    graph.type = "line";
    graph.hidden = false;
    graph.title = "Avg Price per week";
    graph.valueField = "price";
    graph.lineAlpha = 1;
    graph.fillAlphas = 0.6; // setting fillAlphas to > 0 value makes it area graph
    graph.balloonText = "<span style=\'font-size:12px; color:#000000;\'>[[date]]: <b>[[price]]</b> Uncs</span>";
    chart.addGraph(graph);';

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

function umc_shop_count_amounts($table, $item_name, $type=false, $meta=false) {
    $type_sql = "";
    if (is_numeric($type)) {
        $type_sql = "AND damage=$type ";
    }
    $meta_sql = '';
    if (strlen($meta) > 0) {
        $meta_sql = "AND meta='$meta' ";
    }
    $stock_sql = "SELECT sum(amount) as sum "
        . "FROM minecraft_iconomy.$table "
        . "WHERE item_name='$item_name' $type_sql $meta_sql;";
    $stock_data = umc_mysql_fetch_all($stock_sql);
    if (count($stock_data) > 0) {
        $stock_amount = $stock_data[0]['sum'];
    } else {
        $stock_amount = 0;
    }
    return $stock_amount;
}

function umc_shopmgr_offers($where = false) {
    global $UMC_USER;
    if (!$where) {
        $uuid = $UMC_USER['uuid'];
        $where = "stock.uuid ='$uuid'";
        $user_data = 'SUM(transactions.amount * transactions.cost) as income';
    } else {
        $user_data = 'stock.uuid as vendor';
    }

    // $username = $UMC_USER['username'];
    $sql = "SELECT stock.id AS shop_id, concat(stock.item_name,'|', stock.damage, '|', stock.meta) AS item_name, "
        . "stock.amount AS quantity, stock.price, SUM(transactions.amount) as sold_amount, "
        . "$user_data, MAX(transactions.date) as latest_sale "
        . "FROM minecraft_iconomy.stock "
        . "LEFT JOIN minecraft_iconomy.transactions ON stock.uuid = transactions.seller_uuid AND "
        . "stock.item_name=transactions.item_name AND "
        . "stock.price=transactions.cost AND "
        . "stock.damage=transactions.damage AND "
        . "stock.meta=transactions.meta "
        . "WHERE $where "
        . "GROUP BY stock.id, transactions.cost, transactions.damage, transactions.meta";
    $data_rst = mysql_query($sql);

    $sort_column = '0, "desc"';
    $check = umc_web_table('shopstock', $sort_column, $data_rst);
    if (!$check) {
        XMPP_ERROR_trigger("Error creating web_table with SQL $sql");
        return "Error creating data table. Admin was notified, please wait until it is fixed";
    } else {
        return $check;
    }
}

function umc_shopmgr_requests($where = false) {
    global $UMC_USER;
    if (!$where) {
        $uuid = $UMC_USER['uuid'];
        $where = "request.uuid ='$uuid'";
        $user_data = 'SUM(transactions.amount * transactions.cost) as income';
    } else {
        $user_data = 'request.uuid as requestor';
    }

    $sql = "SELECT request.id AS shop_id, concat(request.item_name,'|', request.damage, '|', request.meta) AS item_name, "
        . "request.amount AS quantity, request.price, SUM(transactions.amount) as sold_amount, "
        . "$user_data, MAX(transactions.date) as latest_sale "
        . "FROM minecraft_iconomy.request "
        . "LEFT JOIN minecraft_iconomy.transactions ON request.uuid = transactions.buyer_uuid AND "
        . "request.item_name=transactions.item_name AND "
        . "request.price=transactions.cost AND "
        . "request.damage=transactions.damage AND request.meta=transactions.meta "
        . "WHERE $where "
        . "GROUP BY request.id, transactions.cost, transactions.damage, transactions.meta";
    $data_rst = mysql_query($sql);

    $sort_column = '0, "desc"';
    $check = umc_web_table('shoprequests', $sort_column, $data_rst);
    if (!$check) {
        XMPP_ERROR_trigger("Error creating web_table with SQL $sql");
        return "Error creating data table. Admin was notified, please wait until it is fixed";
    } else {
        return $check;
    }
}

function umc_shopmgr_buyers() {
    $out = "This data only covers the last month, max 100 entries";
    // 1 month ago date:
    $lastmonth = date("Y-m-d", strtotime("-1 month"));

    $sql_buyer = "SELECT `username` as buyer, count(id) as transactions, round(sum(`cost`),2) as value, sum(`amount`) as items, min(`date`) lastest_transaction
        FROM minecraft_iconomy.`transactions`
        LEFT JOIN minecraft_srvr.UUID ON buyer_uuid=UUID
        WHERE date > '$lastmonth' AND UUID <> 'cancel00-depo-0000-0000-000000000000' AND cost > 0
        GROUP BY buyer_uuid
        ORDER BY date DESC
        LIMIT 100";
    $buyer_rst = mysql_query($sql_buyer);

    $sort_buyer = '2, "desc"';
    $check = umc_web_table('shopplayers_buyers', $sort_buyer, $buyer_rst);
    if (!$check) {
        XMPP_ERROR_trigger("Error creating web_table with SQL $sql_buyer");
        return "Error creating data table. Admin was notified, please wait until it is fixed";
    } else {
        return $out . $check;
    }
}

function umc_shopmgr_sellers() {
    $out = "This data only covers the last month, max 100 entries";
    // 1 month ago date:
    $lastmonth = date("Y-m-d", strtotime("-1 month"));

    $sql = "SELECT `username` as seller, count(id) as transactions, round(sum(`cost`),2) as value, sum(`amount`) as items, min(`date`) lastest_transaction
        FROM minecraft_iconomy.`transactions`
        LEFT JOIN minecraft_srvr.UUID ON seller_uuid=UUID
        WHERE date > '$lastmonth' AND UUID <> 'cancel00-depo-0000-0000-000000000000' AND cost > 0
        GROUP BY seller_uuid
        ORDER BY date DESC
        LIMIT 100";
    $data_rst = mysql_query($sql);

    $sort_column = '2, "desc"';
    $check = umc_web_table('shopplayers_sellers', $sort_column, $data_rst);
    if (!$check) {
        XMPP_ERROR_trigger("Error creating web_table with SQL $sql");
        return "Error creating data table. Admin was notified, please wait until it is fixed";
    } else {
        return $out . $check;
    }
}

function umc_shopmgr_transactions() {
    $out = "This data only covers the last month, max 100 entries";
    
    $s_get  = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
    
    $seller_str = '';
    $buyer_str = '';
    $username = "anyone";
    if (isset($s_get['user'])) {
        $username = $s_get['user'];    
        $uuid = umc_uuid_getone($username, 'uuid');        
        $seller_str = "AND seller_uuid = '$uuid'";
        $buyer_str = "buyer_uuid = '$uuid' AND";
    }

    $lastmonth = date("Y-m-d", strtotime("-1 month"));
    
    // what did the user sell?
    $out .= "<h2>Items sold by $username</h2>";
    $sql = "SELECT concat(item_name,'|', damage, '|', meta) AS item_name, cost as income, amount, username as buyer, date
        FROM minecraft_iconomy.`transactions`
        LEFT JOIN minecraft_srvr.UUID ON buyer_uuid=UUID
        WHERE date > '$lastmonth' AND cost > 0 $seller_str
        ORDER BY date DESC
        LIMIT 100";
    
    $data_rst = mysql_query($sql);

    $sort_column = '4, "desc"';
    $out .= umc_web_table('shopusers_soldbyplayer', $sort_column, $data_rst);
    
    $out .= "<h2>Items bought by $username</h2>";
    $sql2 = "SELECT concat(item_name,'|', damage, '|', meta) AS item_name, cost as expense, amount, username as seller, date
        FROM minecraft_iconomy.`transactions`
        LEFT JOIN minecraft_srvr.UUID ON seller_uuid=UUID
        WHERE date > '$lastmonth' AND cost > 0 AND $buyer_str seller_uuid <> 'cancel00-sell-0000-0000-000000000000'
        ORDER BY date DESC
        LIMIT 100";
    $data_rst2 = mysql_query($sql2);

    $sort_column2 = '4, "desc"';
    $check = umc_web_table('shopplayers_sellers', $sort_column2, $data_rst2);
    
    if (!$check) {
        XMPP_ERROR_trigger("Error creating web_table with SQL $sql");
        return "Error creating data table. Admin was notified, please wait until it is fixed";
    } else {
        return $out . $check;
    }    
}

function umc_shopmgr_goods_detail($item, $type) {
    $item_arr = umc_goods_get_text($item, $type);
    $out = $item_arr['full'] . "<hr>\n";
    // show stock
    $out .= umc_shopmgr_stocklist('stock', $item, $type, false);
    //show requests
    $out .= umc_shopmgr_stocklist('request', $item, $type, false);
    return $out;
}

/**
 * Creates a list of the given item on stock.
 *
 * @global type $UMC_DATA
 * @param type $table
 * @param type $item
 * @param type $type
 * @param type $uuid
 * @return type
 */
function umc_shopmgr_stocklist($table, $item = false, $type = 0, $uuid = false) {
    global $UMC_DATA;

    $where = "WHERE damage=$type";
    // do not show item column if there is only one type
    $what = 'concat(item_name,'|',damage, '|', meta) AS item_name, ';
    if ($item && isset($UMC_DATA[$item])) {
        $where .= " AND item_name='$item'";
        $what = '';
    }
    if ($uuid && umc_check_user($uuid)) {
        $where .= " AND uuid='$uuid'";
    }

    $sql = "SELECT id AS shop_id, $what uuid, amount AS quantity, price FROM minecraft_iconomy.$table $where";
    $data_rst = umc_mysql_query($sql);

    $sort_column = '0, "desc"';
    $non_numeric = array('item_name', 'uuid');
    $check = umc_web_table('shop'. $table, $sort_column, $data_rst, '', array(), $non_numeric);
    if (!$check) {
        XMPP_ERROR_trigger("Error creating web_table with SQL $sql");
        return "Error creating data table. Admin was notified, please wait until it is fixed";
    } else {
        return $check;
    }
}

function umc_shopmgr_show_help_deposit() {
    $out = "<h2>Deposit help</h2>";
    $post_arr = get_post(12351);
    return $out . $post_arr->post_content;
}

function umc_shopmgr_show_help_shop() {
    $out = "<h2>Shop help</h2>";
    $post_arr = get_post(12355);
    return $out . $post_arr->post_content;
}

/**
 * shows a graphic of the shop trading volume in pieces and values over time
 */
function umc_shopmgr_stats() {
    global $UMC_DOMAIN;
    $sql = "SELECT DATE_FORMAT(`date`,'%Y-%u') as week, SUM(amount) as amount, SUM(cost) as value "
        . "FROM minecraft_iconomy.transactions "
        . "WHERE date>'2012-03-00 00:00:00' AND seller_uuid NOT LIKE 'cancel%' AND buyer_uuid NOT LIKE 'cancel%' GROUP BY week;";
    $rst = umc_mysql_query($sql);
    //$maxval_amount = 0;
    //$maxval_value = 0;
    //$minval = 0;
    $ydata = array();
    $lines = array('Amount', 'Value');

    $out = "<script type='text/javascript' src=\"$UMC_DOMAIN/admin/js/amcharts.js\"></script>\n"
        . "<script type='text/javascript' src=\"$UMC_DOMAIN/admin/js/serial.js\"></script>\n"
        . "<div id=\"chartdiv\" style=\"width: 100%; height: 362px;\"></div>\n"
        . "<script type='text/javascript'>//<![CDATA[\n"
        . "var chart;\n"
        . "var chartData = [\n";
    //
    while ($row = umc_mysql_fetch_array($rst, MYSQL_ASSOC)) {
        //$maxval_amount = max($maxval_amount, $row['amount']);
        //$maxval_value = max($maxval_value, $row['value']);
        $date = $row['week'];
        $ydata[$date]['Amount'] = $row['amount'];
        $ydata[$date]['Value'] = round($row['value']);
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
    valueAxis.id = "Amount";
    valueAxis.gridAlpha = 0.07;
    valueAxis.title = "Amount";
    valueAxis.position = "left";
    chart.addValueAxis(valueAxis);

    // Amount
    var valueAxis = new AmCharts.ValueAxis();
    valueAxis.id = "Value";
    valueAxis.gridAlpha = 0.07;
    valueAxis.title = "Value";
    valueAxis.position = "right";
    chart.addValueAxis(valueAxis);';

    foreach ($lines as $line) {
        if ($line == 'Value') {
            $index = 'Uncs';
        } else {
            $index = 'Units';
        }
        $out .= "var graph = new AmCharts.AmGraph();
        graph.valueAxis = \"$line\"
        graph.type = \"line\";
        graph.hidden = false;
        graph.title = \"$line\";
        graph.valueField = \"$line\";
        graph.lineAlpha = 1;
        graph.fillAlphas = 0.6; // setting fillAlphas to > 0 value makes it area graph
        graph.balloonText = \"<span style=\'font-size:12px; color:#000000;\'><b>[[value]]</b> $index</span>\";
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
