<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

global $UMC_SETTING;
$UMC_SETTING['nocheatplus']['logfile'] = '/home/minecraft/server/bukkit/plugins/NoCheatPlus/nocheatplus.log';

function umc_nocheatplus_web() {
    global $UMC_DOMAIN;
    $drop_sql = 'SELECT count(log_id) as counter, `action`
        FROM minecraft_log.nocheatplus
        GROUP BY `action`';
    $A = umc_mysql_fetch_all($drop_sql);
    $drop_data = array();
    foreach ($A as $row) {
        $drop_data[$row['action']] = ucwords($row['action']) . " (" . $row['counter'] . ")";
    }
    
    $post_action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
    if (is_null($post_action)) {
        $action = 'passable';
    } else {
        $action = $post_action;
    }
    
    $out = "<form action=\"\" method=\"post\">\n";
    $out .= umc_web_dropdown($drop_data, "action", $action, true);
    
    $sql_action = umc_mysql_real_escape_string($action);
    $sql = "SELECT count(log_id) AS hit_count, DATE_FORMAT(`date`,'%Y-%u') AS date, sum(level)/count(log_id) as average
        FROM minecraft_log.nocheatplus
        WHERE action=$sql_action
        GROUP BY `action`, DATE_FORMAT(`date`,'%Y-%u')
        ORDER BY `date` ASC";
    $D = umc_mysql_fetch_all($sql);
    
    $data_arr = array();
    foreach ($D as $d) {
        $data_arr[$d['date']]["hit_count"] = $d['hit_count'];
        $data_arr[$d['date']]["average"] = $d['average'];
    }
    
    $out .= "\n<script type='text/javascript' src=\"$UMC_DOMAIN/admin/js/amcharts.js\"></script>\n"
        . "<script type='text/javascript' src=\"$UMC_DOMAIN/admin/js/serial.js\"></script>\n"
        . "<div id=\"chartdiv\" style=\"width: 100%; height: 400px;\"></div>\n"
        . "<script type='text/javascript'>//<![CDATA[\n"
        . "var chart;\n"
        . "var chartData = [\n";
    
    $actions = array();
    foreach ($data_arr as $date => $action_data) {
        $out .= "{\"date\": \"$date\",";
        foreach ($action_data as $action => $count) {
            $out .= "\"$action\": $count,";
            $actions[$action] = $action;
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
    // valueAxis.stackType = "regular"; // this line makes the chart "stacked"
    valueAxis.gridAlpha = 0.07;
    valueAxis.title = "Average";
    valueAxis.id = "average";
    valueAxis.position = "left";
    chart.addValueAxis(valueAxis);
    
    var valueAxis = new AmCharts.ValueAxis();
    // valueAxis.stackType = "regular"; // this line makes the chart "stacked"
    valueAxis.gridAlpha = 0.07;
    valueAxis.title = "Hitcount";
    valueAxis.id = "hit_count";
    valueAxis.position = "right";
    chart.addValueAxis(valueAxis);';           

    foreach ($actions as $action) {
        $out .= "\nvar graph = new AmCharts.AmGraph();
        graph.type = \"line\";
        graph.hidden = false;
        graph.title = \"$action\";
        graph.valueField = \"$action\";
        graph.valueAxis = \"$action\",
        graph.lineAlpha = 1;
        graph.fillAlphas = 0.6; // setting fillAlphas to > 0 value makes it area graph
        graph.balloonText = \"<span style=\'font-size:12px; color:#000000;\'>$action: <b>[[value]]</b></span>\";
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
    
    $out .= "</form>";
    return $out;
}


function umc_nocheatplus_logimport() {
    global $UMC_SETTING;
    $file_path = $UMC_SETTING['nocheatplus']['logfile'];

    $regex = '/(^.{0,17}) \[INFO\] ([a-zA-Z_0-9]*) failed ([a-zA-Z_0-9]*):(.*)VL (\d*).$/';
    
    $invalid_str = array(
        '[NoCheatPlus]',
        'settings could have changed',
        'Configuration reloaded',
        'Logger started',
        'Logging system initialized',
        'Version information',
        '# Server #',
        '-Spigot-',
        'runs the command',
    );
    $required_str = '[INFO]';
    $line = 0;
    foreach (new SplFileObject($file_path) as $line) {
        $line ++;
        if (!strpos($line, $required_str)) {
            continue;
        }
        $inval_line = false;
        foreach ($invalid_str as $check) {
            if (strpos($line, $check)) {
                $inval_line = true;
            }
        }
        if ($inval_line) {
            continue;
        }
        $M = false;
        preg_match($regex, $line, $M);
        /*
        $M ⇒
            0 ⇒ "13.10.10 09:59:46 [INFO] miner22122 failed SurvivalFly: tried to move from -294.43, 65.17, -110.90 to -300.08, 64.00, -110.37 over a distance of 5.79 block(s). VL 472."
            1 ⇒ "13.10.10 09:59:46"
            2 ⇒ "miner22122"
            3 ⇒ "SurvivalFly"
            4 ⇒ " tried to move from -294.43, 65.17, -110.90 to -300.08, 64.00, -110.37 over a distance of 5.79 block(s). "
            5 ⇒ "472"
         */
        if (count($M) < 6) {
            XMPP_ERROR_trace("Matches for $line:", $M);
            XMPP_ERROR_trigger("line $line not recognized: $line");
            break;
        }
        $date = umc_mysql_real_escape_string(trim($M[1]));
        $username = umc_mysql_real_escape_string(trim($M[2]));
        $action = umc_mysql_real_escape_string(strtolower(trim($M[3])));
        $text = umc_mysql_real_escape_string(trim($M[4]));
        $vl = umc_mysql_real_escape_string(trim($M[5]));
        // $sql_check = "SELECT count(log_id) as counter FROM minecraft_log.nocheatplus WHERE `date`=$date AND username=$username AND action=$action AND level=$vl;";
        // $C = umc_mysql_fetch_all($sql_check);
        // if ($C[0]['counter'] < 1){ 
            $sql = "INSERT INTO minecraft_log.nocheatplus(`date`, `username`, `action`, `level`, `text`) 
                VALUES 
                ($date,$username,$action,$vl,$text)";
            umc_mysql_execute_query($sql);
        // }
    }
}