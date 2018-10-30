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
 * This file manages the plugins in websend_inc, their help functions and events
 */

function umc_plg_enum() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $folder = '/home/minecraft/server/bin/websend_inc';
    $target = '/home/minecraft/server/bin/assets/plugins.inc.php';

    $plugins = array();

    $it = new FilesystemIterator($folder);
    foreach ($it as $fileinfo) {
        $filename = $fileinfo->getFilename();
        if (substr($filename, -8)== '.inc.php') {
            $plugins[] = $folder . "/" . $filename;
        }
    }
    umc_array2file($plugins, 'PLUGIN_LIST', $target);
}

/**
 * TODO: This list should be created automatically for a cache.
 * The reading of the whole directory each time takes too much time.
 * handles the inclusion of all available plugins in the folder
 */
function umc_plg_include() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $plugin_listfile = '/home/minecraft/server/bin/assets/plugins.inc.php';
    global $PLUGIN_LIST;
    require_once($plugin_listfile);
    foreach ($PLUGIN_LIST as $plugin) {
        require_once($plugin);
    }
}

/**
 * looks for the existance of a called command in plugin definitions
 *
 * @global type $WS_INIT
 * @global type $UMC_USER
 * @param type $name
 * @return type
 */
function umc_wsplg_find_command($name) {
    global $WS_INIT, $UMC_USER;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $args = $UMC_USER['args'];

    $command = false;
    if (isset($UMC_USER['args'][1]) && isset($WS_INIT[$name][$UMC_USER['args'][1]])) { // Get command configuration for given command
        $command = $WS_INIT[$name][$UMC_USER['args'][1]];
    } else if (isset($WS_INIT[$name])) {
        $command = $WS_INIT[$name]['default'];
    } else { // Go through plugins looking for top-level commands
        foreach ($WS_INIT as $plugin_name => $plugin_data) {
            foreach ($plugin_data as $cmd_name => $cmd_data) {
                if (isset($cmd_data['top']) && $cmd_data['top'] === true && $cmd_name == $name) {
                    $name = $plugin_name;
                    array_splice($UMC_USER['args'],0,0,$plugin_name);
                    $args = $UMC_USER['args'];
                    $command = $cmd_data;
                }
            }
        }
    }
   return $command;
}

/**
 * handles the execution of plugin commands
 *
 * @global type $UMC_USER
 * @global type $WS_INIT
 * @global type $UMC_SETTING
 * @param type $module
 * @return boolean
 */
function umc_wsplg_dispatch($module) {
    global $UMC_USER, $WS_INIT, $UMC_SETTING;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $admins = $UMC_SETTING['admins'];
    $player  = $UMC_USER['username'];
    $userlevel = $UMC_USER['userlevel'];

    $command = umc_wsplg_find_command($module);
    if (!$command) {
        umc_error("{red}Sorry $player, that command is not found.");
    }
    // we call this here since $UMC_USER was changes in the line above
    $args = $UMC_USER['args'];
    if (!in_array($player, $admins) && isset($WS_INIT[$args[0]]['disabled']) && $WS_INIT[$args[0]]['disabled'] == true) {
        umc_error("{yellow}Sorry $player, {red}{$args[0]}{yellow} is currently down for maintenance.");
    }

    if (isset($command['function']) && function_exists($command['function'])) {
        if (isset($command['security']) && !in_array($player, $admins)) { // Are there security restrictions?

            // restricts command to the named worlds
            if (isset($command['security']['worlds'])) {
                // XMPP_ERROR_send_msg("$player Checking for world:" . $UMC_USER['world']);
                if (!in_array($UMC_USER['world'], $command['security']['worlds'])) {
                    //XMPP_ERROR_send_msg("$player Checking for world:" . $UMC_USER['world'] . " failed!");
                    umc_error("{red}That command is restricted to the following worlds: {yellow}".join(", ",$command['security']['worlds']));
                } else {
                    // XMPP_ERROR_send_msg("$player Checking for world:" . $UMC_USER['world'] . " did NOT fail!");
                }
            }

            // restricts command to a minimum user level or higher
            if (isset($command['security']['level'])) {
                if (!umc_rank_check($userlevel, $command['security']['level'])) {
                    umc_error('{red}That command is restricted to user level {yellow}'.$command['security']['level'].'{red} or higher.');
                }
            }

            // restricts command to specific users (for testing / debug / collaboration)
            if(isset($command['security']['users'])) {
                if(!in_array($UMC_USER['username'], $command['security']['users'])) {
                    umc_error('{red}That command is restricted');
                }
            }

        } else {
            XMPP_ERROR_trace("test", $command);
        }
        $function = $command['function'];
        $function();
        return true;
    } else {
        return umc_show_help($args);
    }
}

/**
 * show the in-game help for a plugin
 *
 * @global type $UMC_USER
 * @global type $WS_INIT
 * @param type $args
 * @return boolean
 */
function umc_show_help($args = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER, $WS_INIT;
    $player = $UMC_USER['username'];
    $userlevel = $UMC_USER['userlevel'];

    if ($args) { // if show_help is called from another program, we simulate a /help command being issued.
        $args = array_merge(array('call'), $UMC_USER['args']);
    } else {
        $args = $UMC_USER['args'];
    }

    $command = false;
    $command_name = '';
    $plugin_name = '';
    if (isset($args[1])) {
        $command = umc_wsplg_find_command($args[1]);
        $command_name = $args[1];
    }
    // If we have a help query, a command name, but it didn't match any known commands
    if ($args[0] == 'help' && $command_name && !$command) {
        umc_error("{white}Action {green}$command_name{white} not recognized, try {yellow}/helpme");
    }

    umc_header('Uncovery Help', true);
    umc_echo("{gray}   <..> = mandatory   [..] = optional   {ro} = request or offer", true);
    $non_commands = array('default', 'events', 'disabled');
    if ($command_name) {
        if (isset($command['help']['title'])) { // This is a 'default' listing
            umc_pretty_bar("darkblue", "-", "{darkcyan}".$command['help']['title'], 52, true);
            umc_echo($command['help']['long'], true);
            foreach ($WS_INIT[$command_name] as $cmd => $cmd_data) {
                if (!in_array($cmd, $non_commands)) {

                    // This command is restricted to a user level or higher
                    if (isset($cmd_data['security']['level']) && $player != 'uncovery') {
                        if (!umc_rank_check($userlevel, $cmd_data['security']['level'])) {
                            continue;
                        }
                    }

                    // restricts command to specific users (for testing / debug / collaboration)
                    if(isset($cmd_data['security']['users'])) {
                        if(!in_array($UMC_USER['username'], $cmd_data['security']['users'])) {
                            continue;
                        }
                    }

                    if (!isset($cmd_data['top']) || !$cmd_data['top']) {
                        $plugin_name = $command_name . ' ';
                    }
                    $command_args = '';
                    if (isset($cmd_data['help']['args'])) {
                        $command_args = "{yellow}" . $cmd_data['help']['args'];
                    }
                    umc_echo("{green}/$plugin_name$cmd $command_args{gray} => {white}" . $cmd_data['help']['short'], true);
                }
            }
        } else if (isset($command)) { // sub-command help
            if (!isset($command['top']) || !$command['top']) {
                $plugin_name = $command_name . ' ';
            }
            $args_str = '';
            if (isset ($command['help']['args'])) {
                $args_str = "{yellow}" . $command['help']['args'];
            }
            umc_echo("{green}/$plugin_name$command_name $args_str{gray} => {white}" . $command['help']['short'], true);
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
            // don't include plugins that are disabled in help.
            if (isset($cmd_data['disabled']) && ($cmd_data['disabled'] === true)) {
                continue;
            }
            // This command is restricted to a user level or higher
            if (isset($cmd_data['default']['security']['level']) && $player != 'uncovery') {
                if(!umc_rank_check($userlevel, $cmd_data['default']['security']['level'])) {
                    continue;
                }
            }

            // restricts command to specific users (for testing / debug / collaboration)
            if(isset($cmd_data['security']['users'])) {
                if(!in_array($UMC_USER['username'], $cmd_data['security']['users'])) {
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

/**
 * Displays the help for all plugins or one specific one in HTML for use on the website.
 * TODO: The css for this should be improved and moved into a separate CSS file where
 * all plugin CSS are stored.
 *
 * @global type $WS_INIT
 * @global type $UMC_USER
 * @param type $one_plugin
 * @return string
 */
function umc_plugin_web_help($one_plugin = false) {
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
        if ($one_plugin && $plugin <> $one_plugin) {
            continue;
        }
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

            // restricts command to specific users (for testing / debug / collaboration)
            if(isset($value['security']['users'])) {
                if(!in_array($UMC_USER['username'], $value['security']['users'])) {
                    continue;
                }
            }

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

/**
 * Central event handler. Any function can trigger an event of name $event by calling this
 * function and passing optional parameters. This function then iterates all plugins and
 * looks if there is one that recognizes the event. The plugin that recognize the event has
 * another function name configured and this event handler then executes the plugins' function
 * and passes the parameters to it. The plugin function can then return the result back to whatever
 * triggered the event.
 *
 * We do not support a plugin to have several ections for the same event. If there are several things
 * that need to happen for one event and one plugin, this needs to be handled in the plugin itself.
 *
 * @global type $WS_INIT
 * @param string $event event name
 * @param array $parameters event parameters
 * @return misc
 */
function umc_plugin_eventhandler($event, $parameters = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $WS_INIT;

    $return_vars = array();
    foreach ($WS_INIT as $plugin => $data) {
        // check if there is a setting for the current event and if the plugin is enabled
        if (($data['events'] != false) && (isset($data['events'][$event])) && ($data['disabled'] == false)) {
            // if ($UMC_USER['username'] == 'uncovery') {XMPP_ERROR_trace($event, $data);}
            // execute function
            $function = $data['events'][$event];
            if (!is_string($function) || !function_exists($function)) {
                XMPP_ERROR_trigger("plugin $plugin eventhandler failed event $event because $function is not a valid function");
                return false;
            }
            // execute the function, optionally with parameters
            if ($parameters) {
                XMPP_ERROR_trace("Executing Plugin $plugin function $function with parameters:", $parameters);
                $return_vars[] = $function($parameters);
            } else {
                XMPP_ERROR_trace("Executing Plugin $plugin function $function without parameters");
                $return_vars[] = $function();
            }
            // umc_log('plugin', $plugin, "Executing Event $event " . var_export($parameters, true));
        } else {
            // nothing done since plugin either does not have the current event or is disabled.
        }
    }
    return $return_vars;
}
