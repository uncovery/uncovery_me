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
 * This is the web-based shop manager function that allows users to see the shop
 * contents online
 */

function umc_shopmgr_main() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
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
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;

    if (!$UMC_USER) {
        return "You have to be logged in to see this";
    }

    // $player = $UMC_USER['username'];
    $uuid = $UMC_USER['uuid'];
    $sql = "SELECT id, concat(item_name,'|',damage, '|', meta) AS item, s_link.username as sender, amount as quantity, r_link.username as recipient, date
        FROM minecraft_iconomy.deposit
        LEFT JOIN minecraft_srvr.UUID as s_link ON sender_uuid=s_link.UUID
        LEFT JOIN minecraft_srvr.UUID as r_link ON recipient_uuid=r_link.UUID
        WHERE sender_uuid='$uuid' OR recipient_uuid='$uuid' AND sender_uuid NOT LIKE 'reusable%'
        ORDER BY id, damage, amount DESC;";
    $D = umc_mysql_fetch_all($sql);

    $non_numeric_columns = array('item', 'sender', 'recipient');
    $sort_column = "1, 'desc'";
    $check = umc_web_table('deposit', $sort_column, $D, $pre_table = '', array(), $non_numeric_columns);
    if (!$check) {
        XMPP_ERROR_trigger("Error creating web_table with SQL $sql");
        return "Error creating data table. Admin was notified, please wait until it is fixed";
    } else {
        return $check;
    }
}

function umc_shopmgr_items() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_DATA;

    $s_get = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
    $non_numeric_cols = array('item_name');
    $items = array();
    // get all data
    if (!isset($s_get['item']) || !isset($UMC_DATA[$s_get['item']])) {
        foreach ($UMC_DATA as $item_name => $data) {
            // $item = umc_goods_get_text($name);
            $variants = '';
            $title = $item_name;
            $sub_text = '';
            $sub_count = 0;
            if (isset($data['group'])) {
                $variants = "(" . count($data['subtypes']) . " types)"; //TODO: This counts non-available subtypes as well!
                $title = $data['group'];
                $sub_count = count($data['subtypes']);
                $sub_text = "$sub_count sub-types";
            }
            // get stock
            $stock_amount = umc_shop_count_amounts('stock', $item_name);
            $request_amount = umc_shop_count_amounts('request', $item_name);

            $items[$item_name] = array(
                'item_name' => $item_name,
                'sub_types'=> $sub_text,
                'stock' => $stock_amount,
                'requests' => $request_amount,
                'stack_size' => $data['stack'],
            );
        }
        return umc_web_table("goods", "0, 'asc'", $items, '', array(), $non_numeric_cols);
    // get only one item's sub-items
    } else if (isset($s_get['item']) && isset($UMC_DATA[$s_get['item']])) {
        // get only one subitem
        $item_name = $s_get['item'];
        // XMPP_ERROR_send_msg("{$s_get['item']} {$s_get['type']}");
        // we have a specific item
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
        } else {  // we might have various items, list them

            if (!isset($s_get['type']) || ($s_get['type']==0 && !isset($UMC_DATA[$s_get['item']]['subtypes'][$s_get['type']]))) {
                $item_type = false;
            } else {
                $item_type = $s_get['type'];
            }


            $stock_amount = umc_shop_count_amounts('stock', $item_name, $item_type);
            $request_amount = umc_shop_count_amounts('request', $item_name, $item_type);
            $items[0] = array('item_name' => "$item_name|$item_type|", 'stock' => $stock_amount, 'requests' => $request_amount);
            // stuff has sub items, display those
            if (isset($UMC_DATA[$item_name]['subtypes'])) {
                foreach ($UMC_DATA[$item_name]['subtypes'] as $type => $data) {
                    if (isset($data['avail']) && $data['avail'] == false) {
                        continue;
                    }
                    $stock_amount = umc_shop_count_amounts('stock', $item_name, $type);
                    $request_amount = umc_shop_count_amounts('request', $item_name, $type);
                    $items[$type] = array('item_name' => "$item_name|$type|", 'stock' => $stock_amount, 'requests' => $request_amount);
                }
            // no sub items, display data
            } else {

            }
            return umc_web_table("goods", "0, 'asc'", $items, '', array(), $non_numeric_cols);
        }
    }
}

function umc_shopmgr_item_stats($item, $type) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $sql = "SELECT `damage`, AVG(cost / amount) AS price, `meta`, `item_name`, DATE_FORMAT(`date`,'%Y-%u') AS week
        FROM minecraft_iconomy.transactions
        WHERE item_name='$item' AND damage='$type'
	    AND cost > 0
	    AND seller_uuid NOT LIKE 'cancel%'
	    AND buyer_uuid NOT LIKE 'cancel%'
	    AND date > '0000-00-00 00:00:00'
        GROUP BY week ";
    $D = umc_mysql_fetch_all($sql);

    $count = count($D);
    if ($count == 0) {
        return "No data found";
    }

    $L = array();
    foreach ($D as $d) {
        $L[$d['week']]['item'] = $d['price'];
    }

    $out = umc_web_javachart($L, 'weeks', 'regular', false);
    return $out;
}

function umc_shop_count_amounts($table, $item_name, $type=false, $meta=false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $type_sql = "";
    if (is_numeric($type)) {
        $type_sql = "AND damage=$type ";
    }
    $meta_sql = '';
    if (strlen($meta) > 0) {
        $meta_sql = "AND meta='$meta' ";
    }
    $stock_sql = "SELECT sum(amount) as sum
        FROM minecraft_iconomy.$table
        WHERE item_name='$item_name' $type_sql $meta_sql;";
    $stock_data = umc_mysql_fetch_all($stock_sql);
    if (count($stock_data) > 0) {
        $stock_amount = $stock_data[0]['sum'];
    } else {
        $stock_amount = 0;
    }
    return $stock_amount;
}

function umc_shopmgr_offers($where = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    if (!$where) {
        $uuid = $UMC_USER['uuid'];
        $where = "stock.uuid ='$uuid'";
        $user_data = 'SUM(transactions.amount * transactions.cost) AS income';
    } else {
        $user_data = 'stock.uuid AS vendor';
    }

    // $username = $UMC_USER['username'];
    $sql = "
	SELECT
	    stock.id AS shop_id,
	    CONCAT(stock.item_name,'|', stock.damage, '|', stock.meta) AS item_name,
            stock.amount AS quantity,
	    stock.price,
	    SUM(transactions.amount) AS sold_amount,
            $user_data,
	    MAX(transactions.date) AS latest_sale
        FROM minecraft_iconomy.stock
        LEFT JOIN minecraft_iconomy.transactions ON stock.uuid = transactions.seller_uuid
	    AND stock.item_name=transactions.item_name
	    AND stock.price=transactions.cost
	    AND stock.damage=transactions.damage
	    AND stock.meta=transactions.meta
        WHERE $where
        GROUP BY stock.id, transactions.cost, transactions.damage, transactions.meta";
    $D = umc_mysql_fetch_all($sql);

    $sort_column = '0, "desc"';
    $check = umc_web_table('shopstock', $sort_column, $D);
    if (!$check) {
        XMPP_ERROR_trigger("Error creating web_table with SQL $sql");
        return "Error creating data table. Admin was notified, please wait until it is fixed";
    } else {
        return $check;
    }
}

function umc_shopmgr_requests($where = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    if (!$where) {
        $uuid = $UMC_USER['uuid'];
        $where = "request.uuid ='$uuid'";
        $user_data = 'SUM(transactions.amount * transactions.cost) AS income';
    } else {
        $user_data = 'request.uuid as requestor';
    }

    $sql = "
	SELECT request.id AS shop_id,
	    CONCAT(request.item_name,'|', request.damage, '|', request.meta) AS item_name,
            request.amount AS quantity,
	    request.price,
	    SUM(transactions.amount) AS sold_amount,
            $user_data, MAX(transactions.date) AS latest_sale
        FROM minecraft_iconomy.request
        LEFT JOIN minecraft_iconomy.transactions ON request.uuid = transactions.buyer_uuid
	    AND request.item_name=transactions.item_name
	    AND request.price=transactions.cost
	    AND request.damage=transactions.damage
	    AND request.meta=transactions.meta
        WHERE $where
        GROUP BY request.id, transactions.cost, transactions.damage, transactions.meta";
    $D = umc_mysql_fetch_all($sql);

    $sort_column = '0, "desc"';
    $check = umc_web_table('shoprequests', $sort_column, $D);
    if (!$check) {
        XMPP_ERROR_trigger("Error creating web_table with SQL $sql");
        return "Error creating data table. Admin was notified, please wait until it is fixed";
    } else {
        return $check;
    }
}

function umc_shopmgr_buyers() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $out = "This data only covers the last month, max 100 entries";
    // 1 month ago date:
    $lastmonth = date("Y-m-d", strtotime("-1 month"));

    $sql_buyer = "SELECT `username` AS buyer, count(id) AS transactions, round(sum(`cost`),2) AS value, sum(`amount`) AS items, min(`date`) lastest_transaction
        FROM minecraft_iconomy.`transactions`
        LEFT JOIN minecraft_srvr.UUID ON buyer_uuid=UUID
        WHERE date > '$lastmonth' AND UUID <> 'cancel00-depo-0000-0000-000000000000' AND cost > 0
        GROUP BY buyer_uuid
        ORDER BY date DESC
        LIMIT 100";
    $D = umc_mysql_fetch_all($sql_buyer);

    $sort_buyer = '2, "desc"';
    $check = umc_web_table('shopplayers_buyers', $sort_buyer, $D);
    if (!$check) {
        XMPP_ERROR_trigger("Error creating web_table with SQL $sql_buyer");
        return "Error creating data table. Admin was notified, please wait until it is fixed";
    } else {
        return $out . $check;
    }
}

function umc_shopmgr_sellers() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $out = "This data only covers the last month, max 100 entries";
    // 1 month ago date:
    $lastmonth = date("Y-m-d", strtotime("-1 month"));

    $sql = "SELECT `username` AS seller, count(id) AS transactions, round(sum(`cost`),2) AS value, sum(`amount`) AS items, min(`date`) lAStest_transaction
        FROM minecraft_iconomy.`transactions`
        LEFT JOIN minecraft_srvr.UUID ON seller_uuid=UUID
        WHERE date > '$lastmonth' AND UUID <> 'cancel00-depo-0000-0000-000000000000' AND cost > 0
        GROUP BY seller_uuid
        ORDER BY date DESC
        LIMIT 100";
    $D = umc_mysql_fetch_all($sql);

    $sort_column = '2, "desc"';
    $check = umc_web_table('shopplayers_sellers', $sort_column, $D);
    if (!$check) {
        XMPP_ERROR_trigger("Error creating web_table with SQL $sql");
        return "Error creating data table. Admin was notified, please wait until it is fixed";
    } else {
        return $out . $check;
    }
}

function umc_shopmgr_transactions() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
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

    // what did the user sell?
    $out .= "<h2>Users Selling</h2>";
    $sql = "SELECT username, count(id) as transactions, ROUND(SUM(cost)) as sum_costs, ROUND(SUM(amount)) as item_count, MAX(date) as latest_transaction
        FROM minecraft_srvr.UUID
        LEFT JOIN minecraft_iconomy.transactions ON UUID = transactions.seller_uuid
        WHERE lot_count > 0 AND cost > 0 AND id IS NOT NULL
        GROUP BY UUID";
    $D1 = umc_mysql_fetch_all($sql);

    $sort_column = '1, "DESC"';
    $out .= umc_web_table('shopusers_soldbyplayer', $sort_column, $D1);

    $out .= "<h2>Users Buying</h2>";
    $buyer_sql = "SELECT username, count(id) as transactions, ROUND(SUM(cost)) as sum_costs, ROUND(SUM(amount)) as item_count, MAX(date) as latest_transaction
        FROM minecraft_srvr.UUID
        LEFT JOIN minecraft_iconomy.transactions ON UUID = transactions.buyer_uuid
        WHERE lot_count > 0 AND cost > 0 AND id IS NOT NULL AND seller_uuid <> 'cancel00-sell-0000-0000-000000000000'
        GROUP BY UUID";
    $D2 = umc_mysql_fetch_all($buyer_sql);

    $sort_column2 = '1, "DESC"';
    $check = umc_web_table('shopplayers_sellers', $sort_column2, $D2);

    if (!$check) {
        XMPP_ERROR_trigger("Error creating web_table with SQL $sql");
        return "Error creating data table. Admin was notified, please wait until it is fixed";
    } else {
        return $out . $check;
    }
}

function umc_shopmgr_goods_detail($item, $type) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
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
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
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
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $out = "<h2>Deposit help</h2>";
    $post_arr = get_post(12351);
    return $out . $post_arr->post_content;
}

function umc_shopmgr_show_help_shop() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $out = "<h2>Shop help</h2>";
    $post_arr = get_post(12355);
    return $out . $post_arr->post_content;
}

/**
 * shows a graphic of the shop trading volume in pieces and values over time
 */
function umc_shopmgr_stats() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $sql = "SELECT DATE_FORMAT(`date`,'%Y-%u') AS week, SUM(amount) AS amount, SUM(cost) AS value
        FROM minecraft_iconomy.transactions
        WHERE date>'2012-03-00 00:00:00'
	    AND seller_uuid NOT LIKE 'cancel%'
	    AND buyer_uuid NOT LIKE 'cancel%'
	GROUP BY week;";
    $D = umc_mysql_fetch_all($sql);

    $ydata = array();
    foreach ($D as $row) {
        $date = $row['week'];
        $ydata[$date]['value'] = round($row['value']);
        $ydata[$date]['amount'] = $row['amount'];
    }

    $out = umc_web_javachart($ydata, 'weeks', 'none', array('amount' => 'left', 'value' => 'right'), 'userlogins');
    return $out;
}
