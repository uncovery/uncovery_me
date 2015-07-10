<?php

function umc_contest_index() {

    return "this feature is under construction";
}

function umc_new_listcontests($status = 'active') {
    $sql = "SELECT title, id from minecraft_srvr.contest_contests WHERE status='$status' ORDER by id ASC;";
    $D = umc_mysql_fetch_all($sql);

    $out = "<ul>";
    foreach ($D as $row) {
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
        $D = umc_mysql_fetch_all($sql);

        if (count($D) > 0) {
            $title = ucfirst("$status:") ;
            $out .= "<li><strong>$title</strong>";
            $out .= "<ul>";
            foreach ($D as $row) {
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