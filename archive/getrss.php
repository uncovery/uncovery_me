<?php

include_once('/home/minecraft/server/bin/index_wp.php');
require_once('/var/www/bin/magpierss/rss_fetch.inc');

$feedurls = array(
    "http://uncovery.me/feed/" => 'Blog Post', 
    "http://uncovery.me/comments/feed/" => 'Comment', 
    "http://uncovery.me/forums/feed/"  => 'Forum Post',
);

foreach ($feedurls as $feedurl => $type) {
    $rss = fetch_rss($feedurl);
    //var_dump($rss);

    // get only the first item
    $items = array_slice($rss->items, 0, 1);

    $current_items = array();
    foreach ($items as $item) {
        $url = $item['guid'];
        $url = make_bitly_url($url);
        /*$pos = strpos($url, '#post-');
        if ($pos !== false) {
            preg_match('/#post-([0-9]*)/', $url, $matches);
            $id = $matches[1];
            //$url = " http://uncovery.me/?p=$id";
        }*/

        $text = "New $type: {$item['title']} $url by {$item['dc']['creator']}";
        //echo $text;
        update_rss($text, $type);
    }
}


function make_bitly_url($url, $format = 'xml', $version = '2.0.1') {
    $appkey = 'R_7885c7338712a9f1f6852a9bd0655b7b';
    $login = 'uncovery';
    //create the URL
    $bitly = 'http://api.bit.ly/shorten?version=' . $version . '&longUrl='
        . urlencode($url) . '&login=' . $login . '&apiKey=' . $appkey . '&format=' . $format;
  
    //get the url
    //could also use cURL here
    $response = file_get_contents($bitly);
    if (!$response) {
        echo "Bit.ly unavailable";
        return $url;
    }
  
    //parse depending on desired format
    if (strtolower($format) == 'json') {
        $json = @json_decode($response,true);
        return $json['results'][$url]['shortUrl'];
    } else {
        $xml = simplexml_load_string($response);
        return 'http://bit.ly/'.$xml->results->nodeKeyVal->hash;
    }
}

function update_rss($news, $type) {
    $latest_rss_file = "/home/minecraft/server/bin/tweet/latest_rss.txt";

    if (file_exists($latest_rss_file)) { 
        $rss = file_get_contents($latest_rss_file);
        $rss_arr = unserialize($rss);
    } else {
        $rss_arr = array();
    }

    if (!isset($rss_arr[$type]) || $rss_arr[$type] != $news) {
        // new entry
        $rss_arr[$type] = $news;

        $rss = serialize($rss_arr);
        $fh = fopen($latest_rss_file, "w") or die("Cannot open file!");
        fwrite($fh, $rss);
        fclose($fh);

        $command = "chsay announce $news";
        // umc_exec_command($command);
        // umc_ws_cmd($command, 'asCconsole', false, false);
    }
}

?>

