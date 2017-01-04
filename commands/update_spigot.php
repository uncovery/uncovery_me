<?php

umc_spigot_update();

function umc_spigot_update() {
    $build_tools_url = 'https://hub.spigotmc.org/jenkins/job/BuildTools/lastSuccessfulBuild/artifact/target/BuildTools.jar';
    $build_tools_target = '/home/minecraft/server/buildtools/BuildTools.jar';
    $version = ''; // ' --rev 1.10';
    // execute download
    file_put_contents($build_tools_target, fopen($build_tools_url, 'r'));
    // run the process
    $cmd = 'cd /home/minecraft/server/buildtools; java -jar ' . $build_tools_target . $version;
    exec($cmd);
}