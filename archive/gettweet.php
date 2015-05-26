<?php

include_once('/home/minecraft/server/bin/websend.php');

$feed_url = "http://search.twitter.com/search.json?q=from:uncoveryme&rpp=1&include_entities=true";

function parse_json($feed) {
    $out = json_decode($feed, true);
    if (!isset($out['results'][0])) {
        var_dump($out);
    }
    $result =  $out['results'][0];
    $text = $result['text'];
    var_dump($out);
    if (isset($result['entities']['urls'][0])) {
        $urls = $result['entities']['urls'][0];
        $good_url = $urls['expanded_url'];
        $bad_url = $urls['url'];
        $text = str_replace($bad_url, $good_url, $text);
    }
    return $text;
}

$tweet = parse_json(file_get_contents($feed_url));

$last_tweet_file = "/home/minecraft/server/bin/tweet/last-tweet.txt";

if (file_exists($last_tweet_file) && file_get_contents($last_tweet_file) == $tweet) {
    //echo "No change.";
} else {
    $fh = fopen($last_tweet_file, "w") or die("Cannot open file!");
    fwrite($fh, $tweet);
    fclose($fh);

    $command = "chsay announce $tweet ;";
    //umc_exec_command($command);
}

?>