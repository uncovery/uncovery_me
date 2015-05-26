<?php

// /home/minecraft/server/chunk/copychunk /home/minecraft/server/bukkit/city /home/minecraft/server/bukkit_admin/kingdom -22 -82 100 25 138 -119 260 -12
include_once('/home/minecraft/server/bin/index_wp.php');
umc_restore_from_backup();

/*
    $good_map = "/disk2/backup/bukkit_164/empire";
    $bad_map = "/home/minecraft/server/bukkit/empire";
    $exec_path = '/home/minecraft/server/chunk/copychunk';


    $min_x = -22;
    $min_z = -82;
    $max_x = 100;
    $max_z = 25;
    $new_min_x = 138;
    $new_min_z = -119;
    $new_max_x = 260;
    $new_max_z = -12;


        // $exec_cmd = "$exec_path $good_map $bad_map $min_x $min_z $max_x $max_z $new_min_x $new_min_z $new_max_x $new_max_z";

    for($min_z=-82; $min_z>-119; $min_z--) {
        for($min_x = -22; $min_x<100; $min_x++) {
            $new_min_x = $min_x + 160;
            $new_min_z = $min_z - 37;
            $exec_cmd = "$exec_path $good_map $bad_map $min_x $min_z $min_x $min_z $new_min_x $new_min_z $new_min_x $new_min_z";
            echo $exec_cmd . "\n";
            exec($exec_cmd, $output);
            //var_dump($output)
            file_put_contents("/home/minecraft/server/bin/commands/temp_log.txt", implode("\n", $output), FILE_APPEND);
            $output = array();
        }
    }


*/
?>