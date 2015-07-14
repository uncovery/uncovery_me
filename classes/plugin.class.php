<?php

global $UMC_PLUGINS;

/**
 * Plgugin class, manages plugins
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
