<?php

function umc_plg_include() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $folder = '/home/minecraft/server/bin/websend_inc';
    $handle = opendir($folder);
    if ($handle) {
        while (false !== ($entry = readdir($handle))) {
            $start = substr($entry, 0, 1);
            $ext = substr($entry, -4);
            if (($start != ".") && ($ext == '.php')) {
                require_once($folder . "/" . $entry);
            }
        }
        closedir($handle);
    }
}

function umc_wsplg_find_command($name) {
    global $WSEND, $WS_INIT, $UMC_USER;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $args = $WSEND['args'];

    $command = false;
    if (isset($WSEND['args'][1]) && isset($WS_INIT[$name][$WSEND['args'][1]])) { // Get command configuration for given command
        $command = $WS_INIT[$name][$WSEND['args'][1]];
    } else if (isset($WS_INIT[$name])) {
        $command = $WS_INIT[$name]['default'];
    } else { // Go through plugins looking for top-level commands
        foreach ($WS_INIT as $plugin_name => $plugin_data) {
            foreach ($plugin_data as $cmd_name => $cmd_data) {
                if (isset($cmd_data['top']) && $cmd_data['top'] === true && $cmd_name == $name) {
                    $name = $plugin_name;
                    array_splice($WSEND['args'],0,0,$plugin_name); // Pretend they invoked it with the plugin name
                    array_splice($UMC_USER['args'],0,0,$plugin_name);
                    $args = $WSEND['args'];
                    $command = $cmd_data;
                }
            }
        }
    }
   return $command;
}


function umc_wsplg_dispatch($module) {
    global $WSEND, $WS_INIT, $UMC_SETTING;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $admins = $UMC_SETTING['admins'];
    $player  = $WSEND['player'];

    $command = umc_wsplg_find_command($module);
    if (!$command) {
        return umc_show_help($WSEND['args']);
    }
    // we call this here since $WSEND was changes in the line above
    $args = $WSEND['args'];
    if (!in_array($player, $admins) && isset($WS_INIT[$args[0]]['disabled']) && $WS_INIT[$args[0]]['disabled'] == true) {
        umc_error("{yellow}Sorry, {red}{$args[0]}{yellow} is currently down for maintenance.");
    }

    if (isset($command['function']) && function_exists($command['function'])) {
        if(isset($command['security']) && !in_array($player, $admins)) { // Are there security restrictions?
            // This command is restricted to the named worlds
            if(isset($command['security']['worlds'])) {
                if(!in_array($WSEND['world'], $command['security']['worlds'])) {
                    umc_error("{red}That command is restricted to the following worlds: {yellow}".join(", ",$command['security']['worlds']));
                }
            }

            // This command is restricted to a user level or higher
            if(isset($command['security']['level'])) {
                if(!umc_rank_check(umc_get_userlevel($player),$command['security']['level'])) {
                    umc_error('{red}That command is restricted to user level {yellow}'.$command['security']['level'].'{red} or higher.');
                }
            }
        }
        $function = $command['function'];
        $function();
        return true;
    } else {
        return umc_show_help($args);
    }
}

function umc_show_help($args = false) {
    global $WSEND, $WS_INIT;
    $player = $WSEND['player'];
    $userlevel = umc_get_userlevel($player);

    if ($args) { // if show_help is called from another program, we simulate a /help command being issued.
        $args = array_merge(array('call'), $WSEND['args']);
    } else {
        $args = $WSEND['args'];
    }

    $command = false;
    if (isset($args[1])) {
        $command = umc_wsplg_find_command($args[1]);
    }
    if ($args[0] == 'help' && isset($args[1]) && !$command) {
        $given_command = $args[1];
        if(isset($args[2])) { 
            $given_command .= " " . $args[2]; 
        }
        umc_error("{white}Action {green}$given_command{white} not recognized, try {yellow}/helpme");
    }

    umc_header('Uncovery Help', true);
    umc_echo("{gray}   <..> = mandatory   [..] = optional   {ro} = request or offer", true);
    $plugin_name = "";
    $non_commands = array('default', 'events', 'disabled');
    if (isset($args[1])) {
        if (isset($command['help']['title'])) { // This is a 'default' listing
            umc_pretty_bar("darkblue", "-", "{darkcyan}".$command['help']['title'], 52, true);
            umc_echo($command['help']['long'], true);
            foreach ($WS_INIT[$args[1]] as $cmd => $cmd_data) {
                if (!in_array($cmd, $non_commands)) {
                    // This command is restricted to a user level or higher
                    if (isset($cmd_data['security']['level']) && $player != 'uncovery') {
                        if (!umc_rank_check($userlevel, $cmd_data['security']['level'])) {
                            continue;
                        }
                    }
                    if (!isset($cmd_data['top']) || !$cmd_data['top']) {
                        $plugin_name = $args[1] . " ";
                    } else {
                        $plugin_name = $cmd . " ";
                    }
                    $command_args = '' ;
                    if (isset($cmd_data['help']['args'])) {
                        $command_args = "{yellow}" . $cmd_data['help']['args'];
                    }
                    umc_echo("{green}/$plugin_name$cmd $command_args{gray} => {white}" . $cmd_data['help']['short'], true);
                }
            }
        } else if (isset($command)) { // sub-command help
            if (!isset($command['top']) || !$command['top']) {
                $plugin_name = $args[1] . " ";
            }
            if ($plugin_name == $command_name && $plugin_name != '') {
                $command_name = '';
            } else if (isset($args[2])) {
                $command_name = $args[2];
            } else {
                $command_name = $args[1];
            }
            $args_str = '';
            if (isset ($command['help']['args'])) {
                $args_str = "{yellow}" . $command['help']['args'];
            }
            umc_echo(
                "{green}/{$plugin_name}{$command_name} $args_str"
                . "{gray} => {white}" . $command['help']['short'],true);
            umc_pretty_bar("darkgray","-","",49,true);
            foreach (split(';',$command['help']['long']) as $line) {
                if ($line != '') {
                    umc_echo($line, true);
                }
            }
        } else {
            umc_echo("{white}No help found for command {red}/{$args[1]} {$args[2]}{white}.", true);
            // umc_show_help($args);
            // umc_echo("{white}Try {yellow}/helpme {$args[1]}{white} to see valid commands.", true);
        }
    } else { // Show general help.
        foreach ($WS_INIT as $plugin => $cmd_data) {
            // This command is restricted to a user level or higher
            if (isset($cmd_data['default']['security']['level']) && $player != 'uncovery') {
                if(!umc_rank_check($userlevel, $cmd_data['default']['security']['level'])) {
                    continue;
                }
            }
            umc_echo("{green}/$plugin{gray} - " . $cmd_data['default']['help']['short'], true);
        }
        umc_echo("{gray}Use {yellow}/helpme <command>{gray} for more details.", true);
    }
    umc_footer(true);
    return true;
}

function umc_plugin_web_help() {
    global $WS_INIT ,$UMC_USER;

    if (!$UMC_USER) {
        $userlevel = 'Guest';
    } else {
        $userlevel = $UMC_USER['userlevel'];
    }

    $skip_values = array('events', 'disabled', 'default');

    arsort($WS_INIT);
    $out = "<table style=\"font-size:85%;\">\n<tr><th>Plugin / Command</th><th>Description</th></tr>\n";
    foreach ($WS_INIT as $plugin => $data) {
        if (count($data) <= 3) {
            continue;
        }
        if ($data['disabled']) {
            continue;
        }
        $long_title = htmlentities($data['default']['help']['long']);
        $title = $data['default']['help']['title'];
        $out .= "<tr style=\"background-color:#99CCFF;font-size:110%;\"><td colspan=2 style=\"padding:10px;\"><strong>$title:</strong> $long_title</td></tr>";
        foreach ($data as $text=>$value) {
            if (in_array($text, array('disabled', 'events'))) {
                continue;
            }
            $sec = '';
            if (isset($value['security']['level'])) {
                if(!umc_rank_check($userlevel, $value['security']['level'])) {
                    continue;
                } else {
                    $sec = "\n<br><smaller>Required Userlevel: {$value['security']['level']}</smaller>";
                }
            }
            $args = '';
            if (isset($value['help']['args'])) {
                $args = htmlentities($value['help']['args']);
            }
            if (!isset($value['help']['long'])) {
                XMPP_ERROR_trigger("Could not find long help info for plugin $plugin -> $text");
            }
            $help_a = str_replace(";", "\r", $value['help']['long']);
            $help_b = nl2br(htmlentities($help_a));
            $help_c = umc_ws_color_remove($help_b);
            if (in_array($text, $skip_values)) {
                continue;
            }
            if (isset($value['top']) && $value['top'] === true) {
                $cmd = "<code style=\"white-space:nowrap;\">/$text $args$sec</code>";
            } else {
                $cmd = "<code style=\"white-space:nowrap;\">/$plugin $text $args</code>$sec";
            }
            $out .= "<tr><td style=\"padding-right:5px;\">$cmd</td><td>$help_c</td></tr>";
        }
    }

    $out .= "</table>";
    return $out;
}

?>
