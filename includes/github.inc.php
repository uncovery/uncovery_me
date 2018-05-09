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
 * This displays the GitHub Issues list on the webiste.
 * This should be a plugin in websend_inc and provide an in-game list as well.
 */
global $GITHUB;
$GITHUB['owner'] = 'uncovery';
$GITHUB['repo'] = 'uncovery_me';
$GITHUB['page_direct_issues'] = 'https://github.com/uncovery/uncovery_me/issues/';

// https://github.com/KnpLabs/php-github-api

function umc_github_client_connect($owner, $repo) {
    require_once '/home/includes/composer/vendor/autoload.php';
    $cache_dir = "/tmp/github-$repo-$owner-cache";
    $token_file = __DIR__ . "/github-$repo-$owner.token";
    $client = new \Github\Client(
        new \Github\HttpClient\CachedHttpClient(array('cache_dir' => $cache_dir))
    );

    $token = file_get_contents($token_file);

    $client->authenticate($token, \Github\Client::AUTH_HTTP_TOKEN);
    return $client;
}

/**
 * Post an update to the website with the recent updates of Github issues
 * this should happen on server restart by event
 * 
 * @global array $GITHUB
 * @return type
 */
function umc_github_wordpress_update() {
    require_once('/home/minecraft/public_html/wp-load.php');

    global $GITHUB;
    $repo = $GITHUB['repo'];
    $owner = $GITHUB['owner'];

    $client = umc_github_client_connect($owner, $repo);

    $today_obj = new DateTime(NULL);
    $today_obj->modify('-1 day');
    // $date_new->setTimezone(new DateTimeZone('Asia/Hong_Kong'));
    $today_str = $today_obj->format('Y-m-d\T16:00:00\Z');

    $issue_arr = array();

    $issues = $client->api('issue')->all($owner, $repo, array('state' => 'all', 'since' => $today_str));
    if (count($issues) == 0) {
        return;
    }
    foreach ($issues as $issue) {
        if (!isset($issue['pull_request'])) {
            $labels = array();
            $issue_opened_date = substr($issue['created_at'], 0, 10);
            $issue_updated_date = substr($issue['updated_at'], 0, 10);
            foreach ($issue['labels'] as $label) {
                $labels[] = "<span style='background-color: #{$label['color']}'>{$label['name']}</span>;";
            }
            if (count($issue['labels']) > 0) {
                $label_txt = " (". implode(", ", $labels) .")";
            }
            $text = "Issue No. {$issue['number']}, <a href='{$GITHUB['page_direct_issues']}{$issue['number']}'>{$issue['title']}</a> $label_txt";
            if ($issue['state'] == 'open') {
                if ($issue_opened_date == $issue_updated_date) {
                    $issue_arr['opened'][$issue['number']] = $text;
                } else {
                    $issue_arr['updated'][$issue['number']] = $text;
                }
            } else {
                $issue_arr['closed'][$issue['number']] = $text;
            }
        }
    }

    $out = "This is a daily update on the status of the work done behind the scenes.\n "
        . "Our webserver is completely open source, hosted on GitHub. You can help improve the server by fixing issues "
        . "<a href=\"{$GITHUB['page_direct_issues']}\">here</a>.\n<ul>\n";
    foreach ($issue_arr as $section => $lines) {
        $section_str = ucwords($section);
        $out .= "    <li><strong>Issues $section_str:</strong>\n";
        $out .= "        <ul>\n";
        foreach ($lines as $line) {
            $out .= "            <li>$line</li>\n";
        }
        $out .= "        </ul>\n";
        $out .= "    </li>\n";
    }
    $out .= "</ul>\n";

    $post = array(
        'comment_status' => 'open', // 'closed' means no comments.
        'ping_status' => 'closed', // 'closed' means pingbacks or trackbacks turned off
        'post_author' => 1, //The user ID number of the author.
        'post_content' => $out, //The full text of the post.
        'post_status' => 'publish', //Set the status of the new post.
        'post_title' => "Today's development updates", //The title of your post.
        'post_type' => 'post' //You may want to insert a regular post, page, link, a menu item or some custom post type
    );
    wp_insert_post($post);
}
