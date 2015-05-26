<?php

function umc_contest_index() {
    
    return "this feature is under construction";
}

function umc_new_listcontests($status = 'active') {
    $sql = "SELECT title, id from minecraft_srvr.contest_contests WHERE status='$status' ORDER by id ASC;";
    $rst = mysql_query($sql);

    $out = "<ul>";
    while ($row = mysql_fetch_array($rst)) {
        $out .= "<li>" . $row['title'] . "</li>";
    }
    $out .= "</ul>";
    return $out;
}

function umc_contests_status() {
    global $UMC_SETTING;
    $status_arr = array('voting', 'active');
    $out = '<ul>';

    foreach ($status_arr as $status) {
        $sql = "SELECT title, id from 'minecraft_srvr.contest_contests WHERE status='$status' ORDER by id ASC;";
        $rst = mysql_query($sql);
        
        if (mysql_num_rows($rst) > 0) {
            $title = ucfirst("$status:") ;
            $out .= "<li><strong>$title</strong>";
            $out .= "<ul>";
            while ($row = mysql_fetch_array($rst)) {
                $link = $UMC_SETTING['path']['url'] . "/contestsmanager/?action=show_contest&type=" . $row['id'];
                $out .= "<li><a href=\"$link\">{$row['title']}</a></li>";
            }
            $out .= "</ul></li>";
        }
    }
    $out .= "</ul>";
    echo $out;
}

?>