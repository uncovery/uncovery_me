<?php

if ($UMC_ENV == 'wordpress') {
    register_activation_hook( __FILE__, array( 'Minecraft_Icons', 'activation' ) );
    register_deactivation_hook( __FILE__, array( 'Minecraft_Icons', 'deactivation' ) );
    // register_uninstall_hook( __FILE__, array( 'Minecraft_Icons', 'uninstall' ) ) ;
    new Minecraft_Icons();
}

class Minecraft_Icons {
    // Initializes the plugin by setting filters and administration functions.
    public function __construct() {
        add_filter('avatar_defaults', array($this, 'add_uncovery_avatar' ));
        add_filter('get_avatar', array($this, 'get_uncovery_avatar'), 1, 5);

        // If BuddyPress is enabled and uncovery is chosen as avatar
        if(is_plugin_active('buddypress/bp-loader.php')) { // && get_option( 'avatar_default' ) == 'uncovery' ) 
            add_filter('bp_core_fetch_avatar_no_grav', array($this, 'bp_core_fetch_avatar_no_grav'));
            add_filter('bp_core_default_avatar_user', array($this, 'bp_core_default_avatar_user'), 10, 2);

        }
    } // end constructor
    
    // BuddyPress support
    public function bp_core_fetch_avatar_no_grav() {
        return true;
    } // end bp_core_fetch_avatar_no_grav
	
    public function bp_core_default_avatar_user($url, $params) {
        // http://uncovery.me/websend/user_icons/b330abbd-355c-4c31-97bc-74c14cbd690c.20.png
        $user_info = get_userdata($params['item_id']);
        $uuid = umc_wp_get_uuid_from_userlogin($user_info->user_login);
        $uncovery_url = "http://uncovery.me/websend/user_icons/$uuid.20.png";
        return $uncovery_url;
    } // end bp_core_default_avatar_user
	
    /**
    * Apply a filter to the default avatar list and add Minotars
    */
    public function add_uncovery_avatar( $avatar_defaults ) {
        $avatar_defaults['uncovery'] = 'Minecraft Avatar';
        return $avatar_defaults;
    } // end add_uncovery_avatar
	
    /**
    * Apply a filter to the default get_avatar function to add
    * Minotar functionality
    */
    public function get_uncovery_avatar($avatar, $id_or_email, $size, $default, $alt) {
        if($default == 'uncovery') {
            //Alternative text
            if (false === $alt) {
                $safe_alt = '';
            } else {
                $safe_alt = esc_attr($alt);
            }

            //Get username
            if (is_numeric($id_or_email)) {
                $id = (int) $id_or_email;
                $user = get_userdata($id);
                if ($user) {
                    $username = $user->user_login;
                }
            } else if (is_object($id_or_email)) {
                if (!empty($id_or_email->user_id)) {
                    $id = (int) $id_or_email->user_id;
                    $user = get_userdata($id);
                    if ($user) {
                        $username = $user->user_login;
                    }
                } elseif ( !empty($id_or_email->comment_author) ) {
                        $username = $id_or_email->comment_author;
                }
            } else {
                require_once(ABSPATH . WPINC . '/ms-functions.php');
                $user = get_user_by('email', $id_or_email);
                $username = $user->user_login;
            }
            $uuid = umc_wp_get_uuid_from_userlogin($username);
            $uncovery = 'https://crafatar.com/avatars/' . $uuid . '?size=' . $size;
            $avatar = "<img alt='".$safe_alt."' src='".$uncovery."' class='avatar avatar-".$size." photo' height='".$size."' width='".$size."' />";
        }
        return $avatar;
    }

    // Static Functions

    // This is executed when the plugin is activated
    static function activation() {
        update_option( 'avatar_default_before_uncovery', get_option( 'avatar_default' ) );
        update_option( 'avatar_default', 'uncovery' );
    }
    // This is executed when the plugin is deactivated
    static function deactivation() {
        if(get_option( 'avatar_default_before_uncovery') and get_option('avatar_default') == 'uncovery') {
            update_option( 'avatar_default', get_option('avatar_default_before_uncovery' ));
        } // end if
        delete_option( 'avatar_default_before_uncovery' );
    }
	
    // This is executed when the user clicks on the uninstall
    // link that calls for the plugin to uninstall itself
    static function uninstall() {
        if( get_option( 'avatar_default_before_uncovery' ) and get_option( 'avatar_default' ) == 'uncovery' ) {
            update_option( 'avatar_default', get_option( 'avatar_default_before_uncovery' ) );
        } // end if
        else if ( get_option( 'avatar_default_before_uncovery' ) ) {
            delete_option( 'avatar_default_before_uncovery' );
        } // end elseif
    }
} // end class

/**
 * downloads all user icons from uncovery
*/
function umc_update_usericons($users = false, $retry = false, $size = 20) {
    global $UMC_PATH_MC;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $path = "$UMC_PATH_MC/server/bin/data/user_icons/";
    $steve_head = '/home/minecraft/server/bin/data/steve.png';

    $failed_users = array();
    if (!$users) {
        $oneuser = false;
        $users = umc_get_active_members();
    } else if (count($users) == 0) {
        XMPP_ERROR_send_msg("umc_update_usericons got zero users!");
    } else {
        $oneuser = true;
    }

    foreach ($users as $uuid) {
        if ($uuid == '_abandoned_') {
            continue;
        }
        $url = "https://crafatar.com/avatars/$uuid?size=$size";
        XMPP_ERROR_trace('url', $url);
        $file = $path . $uuid . ".$size.png";
        // check if we need to update

        // umc_error_msg("Downloading user icon $lower_user from Minotar");

        $img = file_get_contents($url);
        if ($http_response_header[0] != 'HTTP/1.1 200 OK') {
            XMPP_ERROR_trace("HTTP Response codes:", $http_response_header);
            XMPP_ERROR_trigger("Error downloading icon!");
            $img = false;
        }

        if (!$img) {
            if ($retry) {
                XMPP_ERROR_trace("Icon download failed on retry, using std. steve-face", $url);
                if (!file_exists($file)) {
                    // get standard steve face
                    if (!file_exists($steve_head)) {
                        XMPP_ERROR_trace("Steve head icon not available");
                    } else {
                        $check = copy('/home/minecraft/server/bin/data/steve.png', $file);
                        if (!$check || !file_exists($file)) {
                            XMPP_ERROR_trace("Could not create steve head for file $file");
                        } else {
                            XMPP_ERROR_trace("used steve head for $file");
                        }
                    }
                }
            } else {
                $failed_users[] = $uuid;
            }
        } else {
            $written = file_put_contents($file, $img);
            if (!$written) {
                XMPP_ERROR_send_msg("User icon could not be saved to $file!");
            }
        }
    }
    // retry the failed users, only once:
    if (!$retry && count($failed_users) > 0) {
        XMPP_ERROR_trace(count($failed_users) . " failed usericons, triggering retry");
        umc_update_usericons($failed_users, true);
    }
}

function umc_user_get_icon_url($uuid_requested, $size = 20) {
    global $UMC_DOMAIN, $UMC_PATH_MC;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    if (strstr($uuid_requested, ' ')) {
        return '';
    }
    // make sure it's a uuid
    $uuid = umc_uuid_getone($uuid_requested, 'uuid');
    
    $path = "$UMC_PATH_MC/server/bin/data/user_icons/";
    if (!file_exists($path . $uuid . ".$size.png")) {
        // this tries to download the latest version, otherwise falls back to steve icon
        umc_update_usericons(array($uuid));
    }
    $url = "$UMC_DOMAIN/websend/user_icons/$uuid.$size.png";
    return $url;
}
