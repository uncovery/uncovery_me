<?php

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
