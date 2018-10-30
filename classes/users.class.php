<?php

global $UMC_USERS; // this should contain all users that are set as an object

class UMC_User {
    // TODO: get a users profile link with icon and HTML href

    // base items
    private $username;     // the current minecraft username
    private $uuid;         // the mojang uuid
    private $wordpress_id; // the numerical user id of the user
    private $is_current;   // is the user causing the code to run also this user?

    private $is_banned;
    private $username_history;
    private $avatar;
    private $context;

    private $registered_date;
    private $userlevel;

    // donators
    private $is_donator;
    private $donator_time_left;

    // for active users
    private $is_active; // does the user have a lot?
    private $lot_count; // we could eliminate the above by having this here zero.
    private $lots;

    // in-game variables
    private $is_online;
    private $world;
    private $health;
    private $coordinates;
    private $inventory;
    private $current_item;

    /**
     * Creating a user always requires a UUID
     * @param type $uuid
     */
    public function __construct($uuid) {
        XMPP_ERROR_trace(__CLASS__ . " // " .  __FUNCTION__, func_get_args());
        $this->$uuid = $uuid;
    }

    // get the uuid, either from the set value, wordpress_id or username
    public function get_uuid() {
        XMPP_ERROR_trace(__CLASS__ . " // " .  __FUNCTION__, func_get_args());
        return $this->uuid;
    }

    public function set_username($username) {
        XMPP_ERROR_trace(__CLASS__ . " // " .  __FUNCTION__, func_get_args());
        $this->username = $username;
    }

    // requires the UUID to be set
    public function get_username() {
        XMPP_ERROR_trace(__CLASS__ . " // " .  __FUNCTION__, func_get_args());
        return $this->username;
    }
     public function set_userlevel($userlevel) {
        XMPP_ERROR_trace(__CLASS__ . " // " .  __FUNCTION__, func_get_args());
        $this->userlevel = $userlevel;
    }

    // requires the UUID to be set
    public function get_userlevel() {
        XMPP_ERROR_trace(__CLASS__ . " // " .  __FUNCTION__, func_get_args());
        return $this->userlevel;
    }
    public function ban($reason) {
        XMPP_ERROR_trace(__CLASS__ . " // " .  __FUNCTION__, func_get_args());
        global $UMC_USERS;

        $cmd = "ban $this->username $reason";
        if ($this->context == 'websend') {
            umc_ws_cmd($cmd, 'asConsole', false, false);
            $admin = $UMC_USERS['current_user']->username;
        } else {
            umc_exec_command($cmd, 'asConsole', false);
            $admin = 'wordpress';
        }
        $sql = "INSERT INTO minecraft_srvr.`banned_users`(`username`, `reason`, `admin`, `uuid`) VALUES ('$this->username','$reason', '$admin', '$this->uuid');";
        umc_mysql_query($sql, true);

        $text = "$admin banned $$this->username ($this->uuid) because of $reason";
        umc_log('mod', 'ban', $text);
        XMPP_ERROR_send_msg($text);

        // iterate plugins to check for plugin relared post ban processes
    }
}