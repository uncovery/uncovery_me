<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

global $UMC_SETTING;
$UMC_SETTING['nocheatplus']['logfile'] = '/home/minecraft/server/bukkit/plugins/NoCheatPlus/nocheatplus.log';

function umc_nocheatplus_web() {
    // get actions
    $sql = "SELECT action, count(log_id) as counter FROM minecraft_log.nocheatplus GROUP BY action ORDER BY action;";
    $A = umc_mysql_fetch_all($sql);
    $data = array();
    foreach ($A as $row) {
        $data[strtolower($row['action'])] = $row['action'] . " (" . $row['counter'] . ")";
    }
    
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
    if (!isset($data[$action])) {
        $action = 'passable';
    }
    
    $out = umc_web_dropdown($data, "action", $action, true);
    
    
    
    
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