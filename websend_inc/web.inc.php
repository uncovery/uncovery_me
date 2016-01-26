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
);

function umc_web_read() {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_USER;
    $args = $UMC_USER['args'];

    $id = $args[2];
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
        umc_footer(true, "http://uncovery.me/?p=$id");
    } else {
        umc_error("Invalid Post ID!");
    }
}