<?php

global $GITHUB;
$GITHUB['owner'] = 'uncovery';
$GITHUB['repo'] = 'uncovery_me'; 
$GITHUB['page'] = 'http://uncovery.me/server-features/development-status/';

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


function umc_github_link() {
    global $GITHUB;
    $repo = $GITHUB['repo'];
    $owner = $GITHUB['owner'];
            
    $sort_column = 0;
    $client = umc_github_client_connect($owner, $repo);

    $menu_arr = array(
        'open_issues' => true,
        'closed_issues' => true,
        'issue_detail' => false,
        'commits' => true,
    );

    $s_get  = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
    if (!isset($s_get['action']) || !isset($menu_arr[$s_get['action']])) {
        $action = 'open_issues';
    } else {
        $action = $s_get['action'];
        if ($action == 'issue_detail') {
            if (isset($s_get['id']) && is_numeric($s_get['id'])) {
                $get_issue = intval($s_get['id']);
            } else {
                $action = 'open_issues';
            }
        }
    }

    $menu = "<br><ul>\n";
    foreach ($menu_arr as $code => $show_flag) {
        if ($show_flag) {
            $menu_nice = str_replace("_", " ", ucwords($code));
            if ($code == $action) {
                $link = $menu_nice;
            } else {
                $link = "<a href=\"?action=$code\">$menu_nice</a>";
            }
            $menu .= "<li>$link</li>\n";
        }
    }
    $menu .= "</ul>";

    $out = $menu;

    // {$issue['pull_request']}
    switch ($action) {
        case 'open_issues':
            $issues = $client->api('issue')->all($owner, $repo, array('state' => 'open', 'per_page' => 100));
            break;
        case 'closed_issues':
            $issues = $client->api('issue')->all($owner, $repo, array('state' => 'closed', 'per_page' => 100));
            break;
        case 'issue_detail':
            $out .= umc_github_issue_details($client, $owner, $repo, $get_issue);
            return $out;
        case 'commits':
            $commits = $client->api('repo')->commits()->all($owner, $repo, array('sha' => 'master', 'per_page' => 100));
            break;
    }

    $out .= "<script type=\"text/javascript\">
           var table_name = \"$repo\";
           var numeric_columns = [];
           var sort_columns = [[$sort_column]];
           var strnum_columns = [];
       </script>";

    $out .= "<script type=\"text/javascript\" src=\"/admin/js/jquery.dataTables.min.js\"></script>\n"
          . "<script type=\"text/javascript\">"
          .'jQuery(document).ready(function() {jQuery'. "('#shoptable_$repo').dataTable( {\"order\": [[ $sort_column ]],\"paging\": false,\"ordering\": true,\"info\": false} );;} );"
          . "</script>";

    $data_out = '';
    if ($action == 'commits') {
        $th_row = '<th>Date</th><th>Message</th>';
        foreach ($commits as $commit) {
            $updated = substr($commit['commit']['committer']['date'], 0, 10);
            $data_out .= "<tr><td>$updated</td><td>{$commit['commit']['message']}</td><td></tr>";
        }
    } else {
        $th_row = '<th>#</th><th>Title</th><th>Labels</th><th>Updated</th>';
        foreach ($issues as $issue) {
            if (!isset($issue['pull_request'])) {
                $data_out .= "<tr><td>{$issue['number']}</td><td><a href=\"?action=issue_detail&amp;id={$issue['number']}\">{$issue['title']}</a></td><td>";
                foreach ($issue['labels'] as $label) {
                    $data_out .= " <span style='background-color: #{$label['color']}'>&nbsp;{$label['name']}&nbsp;</span> ";
                }
                $updated = substr($issue['updated_at'], 0, 10);
                $data_out .= "</td><td>$updated</td></tr>\n";
            }
        }
    }

    $out .= "
       <table id='shoptable_$repo'>
         <thead>
           <tr>
             $th_row
           </tr>
         </thead>
         <tbody>
           $data_out
         </tbody>
       </table>";
    return $out;
}

function umc_github_issue_details($client, $owner, $repo, $get_issue) {
    $issue = $client->api('issue')->show($owner, $repo, $get_issue);
    $comments = $client->api('issue')->comments()->all($owner, $repo, $get_issue);

    $labels = '';
    foreach ($issue['labels'] as $label) {
        $labels .= " <span style='background-color: #{$label['color']}'>&nbsp;{$label['name']}&nbsp;</span> ";
    }
    $created = substr($issue['created_at'], 0, 10);
    $updated = substr($issue['updated_at'], 0, 10);
    $body = nl2br($issue['body']);

    $comments_html = '';
    if ($issue['comments'] > 0) {
        $comments_html = "<tr><td colspan=5 style='text-align:center'><strong>Comments</strong></td></tr>\n";
        foreach ($comments as $comment) {
            $comment_body = nl2br($comment['body']);
            $updated = substr($comment['updated_at'], 0, 10);
            $comments_html .= "<tr><td><strong>{$comment['user']['login']}</strong></td><td>$updated</td><td colspan=3>$comment_body</td></tr>\n";
        }
    }


    $out = "<table class='dataTable'>\n
    <tr>
        <td><strong>ID:</strong> {$issue['number']}</td>
        <td colspan=4><strong>Title:</strong> {$issue['title']}</td>
    </tr>
    <tr>
        <td><strong>Labels:</strong></td><td>$labels</td>
        <td><strong>Created by:</strong> {$issue['user']['login']}</td>
        <td><strong>Created at:</strong> $created</td>
        <td><strong>Updated at:</strong> $updated</td>
    </tr>
    <tr>
        <td><strong>Description:</strong></td>
        <td colspan=4>$body</td>
    </tr>
    $comments_html
    </table>\n";

    return $out;
}

function umc_github_wordpress_update() {
    require_once('/home/minecraft/public_html/wp-load.php'); 
    
    global $GITHUB;
    $repo = $GITHUB['repo'];
    $owner = $GITHUB['owner'];
    $page = $GITHUB['page'];
    
    $client = umc_github_client_connect($owner, $repo);
    
    $today_obj = new DateTime(NULL);
    $today_obj->modify('-1 day');
    // $date_new->setTimezone(new DateTimeZone('Asia/Hong_Kong'));    
    $today_str = $today_obj->format('Y-m-d\T00:00:00\Z');
    
    $issue_arr = array(
    );
    
    $issues = $client->api('issue')->all($owner, $repo, array('state' => 'all', 'since' => $today_str));
    if (count($issues) == 0) {
        return;
    }
    foreach ($issues as $issue) {
        if (!isset($issue['pull_request'])) {
            $labels = '';
            $issue_opened_date = substr($issue['created_at'], 0, 10);
            $issue_updated_date = substr($issue['updated_at'], 0, 10);
            foreach ($issue['labels'] as $label) {
                $labels .= " <span style='background-color: #{$label['color']}'>&nbsp;{$label['name']}&nbsp;</span> ";
            }            
            $text = "Issue No. {$issue['number']}, <a href=\"$page?action=issue_detail&amp;id={$issue['number']}\">{$issue['title']}</a> ($labels)";
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

    $out = "This is a daily update on the status of the work done behind the scenes. You can see the complete status <a href=\"$page\">here</a>.\n<ul>\n"; 
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