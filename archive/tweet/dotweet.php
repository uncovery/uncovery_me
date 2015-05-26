<?php

function umc_do_tweet($message) {

    $twitter_api_url = "http://twitter.com/statuses/update.xml";
    $twitter_data = $message;
    $twitter_user = "uncoveryme";
    $twitter_password = "axSEGK0PeS3CeB6";

    $ch = curl_init($twitter_api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $twitter_data);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERPWD, "{$twitter_user}:{$twitter_password}");

    $twitter_data = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode != 200) {
        return false;
    } else {
        return true;
    }
}


?>