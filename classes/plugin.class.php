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

global $UMC_PLUGINS;

/**
 *
 */
class UMC_plugin {
    private $active; // determines if the plugin is active or not
    
    private $events; // list of events that trigger functions in the plugin
    
    private $commands; // list of commands one can run on this plugin
    
    private $description;
    /**
     * This function runs during the activation of the plugin
     * 
     */
    public function __construct() {
        XMPP_ERROR_trace(__CLASS__ . " // " .  __FUNCTION__, func_get_args());
        
    }
    
    /**
     * Finds out if the plugin is active or not
     */
    public function get_status() {
        if ($this->active) {
            return true;
        } else {
            return false;
        }
    }
    
    public function run_event($event) {
        if (isset($event, $this->events)) {
            $function = $this->events[$event]['action'];
            return $function();
        }
    }
    
    public function help($command = false) {
        if ($command && isset($this->commands[$command])) {
            $help = $this->commands->$command->description;
        } else {
            $help = $this->description;
        }
        // format help depending on target media (website, websend)
    }
}
