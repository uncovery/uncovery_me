<?php
/**
 * This file is called by wordpress to run all the plugins. It does not go through
 * core_include.inc.php so you need to include whatever you need on-demand.
 */
global $umc_wp_register_questions;

global $XMPP_ERROR;
$XMPP_ERROR['config']['project_name'] = 'Uncovery.me';
require_once('/home/includes/xmpp_error/xmpp_error.php');

/**
 * Initialize plugins so that the hooks in Wordpress are correct
 */
function umc_wp_init_plugins() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    // chose different page templates depending on condition
    add_filter('template_include', 'umc_wp_template_picker', 99);
    // add fields to registration form
    add_action('register_form', 'umc_wp_register_addFields');
    // add check to posting registration form
    add_action('register_post', 'umc_wp_register_checkFields', 10, 3);
    // add actions when the user registers successfullt
    add_action('user_register', 'umc_wp_register_addWhitelist', 10, 1);
    // make notification when new post is made to blog or forum
    add_action('transition_post_status', 'umc_wp_notify_new_post', 10, 3);
    // make notification when new comment is made on post
    add_action('comment_post', 'umc_wp_notify_new_comment', 10, 2);
    remove_action('wp_head', 'start_post_rel_link', 10, 0 );
    remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0);
    new Minecraft_Icons();
}


/**
 * pick specific templates based on POST variables
 *
 * @param type $template
 * @return type
 */
function umc_wp_template_picker($template) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $s_post = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
    // to prevent recursive inclusion of headers and footers, we only load the
    // core contents of a page when we submit a form through Ajax.
    if (isset($s_post['ajax_form_submit'])) {
        $new_template = locate_template(array('emptypage.php'));
        if ('' != $new_template) {
            return $new_template ;
        }
    }
    return $template;
}


/**
 * Notify in-game when there is a new comment posted to the blog
 *
 * @param type $comment_id
 * @param type $arg2
 */
function umc_wp_notify_new_comment($comment_id, $arg2){

    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $comment = get_comment( $comment_id, 'ARRAY_A' );
    $author = $comment['comment_author'];
    $parent = $comment['comment_post_ID'];

    $post = get_post( $parent, 'ARRAY_A');
    $title = $post['post_title'];

    $cmd = "ch qm n New Comment on Post &a$title &fby $author&f";
    require_once('/home/minecraft/server/bin/index_wp.php');
    umc_exec_command($cmd, 'asConsole');
}

/**
 * Notify in-game when there is a new post made to forum or blog
 * @param type $new_status
 * @param type $old_status
 * @param type $post
 */
function umc_wp_notify_new_post($new_status, $old_status, $post) {

    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    if ($old_status != 'publish' && $new_status == 'publish' ) {
        $post_title = $post->post_title;

        if ($post->post_type == 'post' && $post->post_parent == 0) {
            $cmd = "ch qm u New Blog Post: &a$post_title&f";
        } else {
                $type = ucwords($post->post_type);
            if ($type == 'Reply') {
                $parent = get_post($post->post_parent);
                $post_title = $parent->post_title;
            } else if ($type == 'Page') {
                return; //die('umc_wp_notify_new_post');
            }
            $author_id = $post->post_author;
            $user = get_userdata($author_id);
            $username = $user->display_name;
            $cmd = "ch qm n New Forum $type: &a$post_title &fby $username&f";
        }
        require_once('/home/minecraft/server/bin/index_wp.php');
        umc_exec_command($cmd, 'asConsole');
    }
}

$umc_wp_register_questions = array(
    0 => array('text'=>'What user level will you have after whitelisting?', 'true'=>1,
        'answers'=>array('0'=>'Admin - I can do anything!', '1'=>'Guest - I can only look around', '2'=>'Settler - I get a place to build!')),
    1 => array('text'=>'What do you need to do to get building rights?', 'true'=>1,
        'answers'=>array('0'=>'I have it already!', '1'=>'I have to apply for Settler status on the website!')),
    2 => array('text'=>'Which username do you choose here?', 'true'=>0,
        'answers'=>array('0'=>'My Minecraft username', '1'=>'My email address', '2'=>'31337 sh0073rz')),
    3 => array('text'=>'How do you know the IP of the server?', 'true'=>0,
        'answers'=>array('0'=>'Its written in the email I get when I fill this out correctly', '1'=>'I will have to guess', '2'=>'I ask for it in the forum')),
    4 => array('text'=>'In which world do you spawn?', 'true'=>2,
        'answers'=>array('0'=>'City world (survival mode)', '1'=>'Empire world (creative mode)', '2'=>'City world (creative mode)')),
);

/**
 * Add fields to the new user registration page that the user must fill out to register
 *
 * @since 0.5
 * @access private
 * @author Andrew Ferguson
*/
function umc_wp_register_addFields(){
    global $umc_wp_register_questions, $UMC_DOMAIN;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());

    // echo "Due to technical reasons, we cannot accept any new users right now. Please check back later";
    // die('umc_wp_register_addFields');

    $out = '<p><strong>GMail is currently blocking our emails. If you are registering a new account, please use an alternative email if possible. We are working to fix the issue.</strong>
        <label for="email_confirm">Confirm E-mail<br />
        <input type="text" name="email_confirm" id="email_confirm" class="input" value="" size="25" /></label>
    </p>';

    $out .= "<strong>Please also answer these questions AFTER reading <a href=\"$UMC_DOMAIN/whitelist/\">this page</a>:</strong><br /><br />\n\n";
    foreach ($umc_wp_register_questions as $q_index => $item) {
        $question = $item['text'];
        $answers = $item['answers'];
        $out .= "<label>$question</label><br />\n";
        foreach ($answers as $a_index => $text){
            $out .= "<input type=\"radio\" name=\"$q_index\" value=\"$a_index\" /> <label>$text</label><br>\n";
        }
        $out .= "<br /><br />\n\n";
    }
    echo $out;
}


/**
 * Checks registration fields to make sure they are filled out (although that is the extent of the checking).
 * If they are not, an error is added to WP_Error
 *
 * @param $user_login string
 * @param $user_email string
 * @param $errors object WP_Error object that contains the list of existing registration errors, if any
 * @since 0.5
 * @access private
 * @author Andrew Ferguson
*/
function umc_wp_register_checkFields($user_login, $user_email, $errors){
    global $umc_wp_register_questions;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $error = false;
    $s_post = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);


    $chars = array('@', ":", "\\", "/");
    $count = 0;
    str_replace($chars, "", $user_login, $count);

    if (strlen($user_login) == 0 || strlen($user_email) == 0) {
        return false;
    } else if ($count > 0) {
        $error_msg = "You need to enter your minercaft username, not an email addres etc!";
        $errors->add('demo_error',__($error_msg));
        return $errors;
    } else if ((!isset($s_post['user_email'])) || (!isset($s_post['email_confirm'])) || ($s_post['user_email'] != $s_post['email_confirm'])) {
        $error_msg = "<strong>ERROR:</strong> Your email and your confirmed email address do not match. Please check and try again.";
        $errors->add('demo_error',__($error_msg));
        return $errors;
    } else {
        foreach ($umc_wp_register_questions as $q_index => $item) {
            if (!isset($s_post[$q_index]) || ($s_post[$q_index] != $item['true'])) {
                $error = true;
                $error_msg = "<strong>ERROR:</strong>You entered one or more wrong answers to the questions below. "
                    . "Please go back to <a href=\"http://uncovery.me/whitelist/\">this page</a>, read it properly and try again.";
            }
        }
        if ($error) {
            $errors->add('demo_error',__($error_msg));
            return $errors;
        } else {
            require_once('/home/minecraft/server/bin/index_wp.php');
            global $UMC_USER;
            if (!$UMC_USER || !$UMC_USER['uuid']) {
                $error_msg = "<strong>ERROR:</strong>We could not verify your username right now. If you own a copy of Minecraft under the username '$user_login', you can try to login to the server once. "
                    . "It will not let you login, but our system will get a confirmation from Mojang in case your username exists. "
                    . "If you are sure that this is your username, try to connect to uncovery.me with your minecraft client once. You can try to register afterwards here again. "
                    . "If you are certain that you are using the right username and still get this error, please submit a <a href=\"http://uncovery.me/help-2/support/\">support ticket</a>.";
                $errors->add('demo_error',__($error_msg));
                return $errors;
            } else if (umc_user_is_banned($UMC_USER['uuid'])) {
                $error_msg = "<strong>ERROR:</strong>Sorry, you were banned from the server. Please find another one.";
                $errors->add('demo_error',__($error_msg));
                return $errors;
            }
        }
    }
}

/**
 * register the user to the whitelist
 * ad the uuid to the UUID table
 * add the UUID to the Meta data in wordpress
 *
 * @param type $user_id
 */
function umc_wp_register_addWhitelist($user_id){
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $current_user = get_user_by('id', $user_id);
    $username = $current_user->user_login;
    require_once('/home/minecraft/server/bin/index_wp.php');
    //$check = umc_read_data('whitelist');
    // umc_update_data('whitelist', array($username => $username));
    umc_exec_command("whitelist add $username", 'asConsole', false);
    // add UUID to use meta
    $UUID = umc_user2uuid($username);
    add_user_meta($user_id, 'minecraft_uuid', $UUID);
    // add user to UUID table
    umc_uuid_record_usertimes('firstlogin');
}


class Minecraft_Icons {
    // Initializes the plugin by setting filters and administration functions.
    public function __construct() {
        add_filter('avatar_defaults', array($this, 'add_uncovery_avatar' ));
        add_filter('get_avatar', array($this, 'get_uncovery_avatar'), 1, 5);

        // If BuddyPress is enabled and uncovery is chosen as avatar
        /*if(is_plugin_active('buddypress/bp-loader.php')) { // && get_option( 'avatar_default' ) == 'uncovery' ) 
            add_filter('bp_core_fetch_avatar_no_grav', array($this, 'bp_core_fetch_avatar_no_grav'));
            add_filter('bp_core_default_avatar_user', array($this, 'bp_core_default_avatar_user'), 10, 2);
        }*/
    } // end constructor
    
    // BuddyPress support
    public function bp_core_fetch_avatar_no_grav() {
        return true;
    } // end bp_core_fetch_avatar_no_grav
	
    public function bp_core_default_avatar_user($url, $params) {
        require_once('/home/minecraft/server/bin/includes/wordpress.inc.php');
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
        XMPP_ERROR_trace(__FUNCTION__, func_get_args());
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
                    } else {
                        return '';
                    }
                } else if (!empty($id_or_email->comment_author)) {
                    $username = $id_or_email->comment_author;
                }
            } else if (strstr($username, '@')) { // email
                require_once(ABSPATH . WPINC . '/ms-functions.php');
                $user = get_user_by('email', $id_or_email);
                $username = $user->user_login;
            } else { // by displayname
                $username = $id_or_email;
            }
            require_once('/home/minecraft/server/bin/includes/wordpress.inc.php');
            $uuid = umc_wp_get_uuid_from_userlogin($username);
            $uncovery = 'https://crafatar.com/avatars/' . $uuid . '?size=' . $size;
            $avatar = "<img alt='".$safe_alt."' src='".$uncovery."' class='avatar avatar-".$size." photo' height='".$size."' width='".$size."' />";
        }
        return $avatar;
    }
} // end class