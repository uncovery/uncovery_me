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

global $UMC_SETTING, $WS_INIT;

$WS_INIT['web'] = array(  // the name of the plugin
    'disabled' => false,
    'events' => false,
    'default' => array(
        'help' => array(
            'title' => 'Website',  // give it a friendly title
            'short' => 'Read & Write website contents',  // a short description
            'long' => "Allows you to read posts, comments, forum contents and write them as well.", // a long add-on to the short  description
            ),
    ),
    'read' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Read something on the website',
            'long' => "Allows you to read any post, comment or forum post on the website.",
            'args' => '<post id>',
        ),
        'function' => 'umc_web_read',
    ),
    'list' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'List recent website posts',
            'long' => "Lists recent posts from the website with an ID so you can read them in-game. Types can be [p]osts, [c]omments of [c]orum",
            'args' => '<type>',
        ),
        'function' => 'umc_web_list',
    ),
);

function umc_web_read() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $args = $UMC_USER['args'];

    $id = strtolower($args[2]);

    if (strpos($id, "c") === 0) {
        $comment_id = substr($id, 1);
        $C = get_comment($comment_id, ARRAY_A);
        $author = $C['comment_author'];
        $comment = $C['comment_content'];
        umc_header("Comment by $author");
        umc_echo($comment);
        umc_footer(true);
        return;
    }

    $P = get_post($id, 'ARRAY_A');
    if ($P && $P['post_status'] == "publish") {
        umc_header($P['post_title']);
        require_once('/home/includes/html2text/html2text.php');
        $content = convert_html_to_text($P['post_content']);
        umc_echo($content);
        if ($P['comment_count'] > 0) {
            umc_footer();
            umc_echo($P['comment_count'] . " comments.");
        }
        umc_footer(true, "https://uncovery.me/?p=$id");
    } else {
        umc_error("Invalid Post ID!");
    }
}

/**
 * Allow in-game listing of recent posts and comments
 * 
 * @global type $UMC_USER
 */
function umc_web_list() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    
    $valid_types = array("p", "c", "f");
    
    global $UMC_USER;
    $args = $UMC_USER['args'];
    if (!isset($args[2]) || !in_array($args[2], $valid_types)) {
        umc_error("You need to give a valid type (" . implode(",", $valid_types) . ")");
    }
    $type = $args[2];
    
    if ($type == 'p') {
        $args = array(
            'posts_per_page'   => 25,
            'offset'           => 0,
            'orderby'          => 'date',
            'order'            => 'DESC',
            'post_type'        => 'post',
            'post_status'      => 'publish',
            'suppress_filters' => true 
        );
        $posts_array = get_posts($args);
        $count = count($posts_array);
        umc_header("$count Recent Posts");
        foreach($posts_array as $P) {
            umc_echo($P->ID . " > " . $P->post_title);
        }
    } else if ($type == 'f') {
        $args = array(
            'posts_per_page'   => 25,
            'offset'           => 0,
            'orderby'          => 'date',
            'order'            => 'DESC',
            'post_type'        => 'topic',
            'post_status'      => 'publish',
            'suppress_filters' => true 
        );
        $posts_array = get_posts($args);
        $count = count($posts_array);
        umc_header("$count Recent Forum Posts");
        foreach($posts_array as $P) {
            umc_echo("f" . $P->ID . " > " . $P->post_title);
        }        
    } else if ($type == 'c') {
        $args = array(
            'number' => 25,
            'order' => 'DESC',
            'date_query' => array(
                'after' => '4 week ago',
                'before' => 'tomorrow',
                'inclusive' => true,
            ),    
        );
        $posts_array = get_comments($args);
        $count = count($posts_array);
        umc_header("$count Recent Comments");
        foreach($posts_array as $P) {
            // comment length shortening
            $comment = $P->comment_content;
            if (strlen($P->comment_content) > 30) {
                $comment = substr($P->comment_content, 0, 27) . "...";
            }
            umc_echo("c" . $P->comment_ID  . " ({$P->comment_author}) $comment");
        }
    }
    umc_footer("Type &a/web read ID&f to read in-game");
    
}