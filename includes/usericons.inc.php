<?php

/**
 * downloads all user icons from uncovery
*/
function umc_update_usericons($users = false, $size = 20) {
    global $UMC_PATH_MC;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $path = "$UMC_PATH_MC/server/bin/data/user_icons/";
    $steve_head = '/home/minecraft/server/bin/data/steve.png';


    if (!$users) {
        $users = umc_get_active_members();
    } else if (is_array($users) && count($users) == 0) {
        XMPP_ERROR_send_msg("umc_update_usericons got zero users!");
    } else if (!is_array($users)) {
        $U = umc_uuid_getboth($users);
        $users = array(
            $U['uuid'] => $U['username'],
        );
    }

    // queue all requests and then perform them in a batch
    $requests = array();
    foreach ($users as $uuid => $username) {
        if ($uuid == 'abandone-0000-0000-0000-000000000000') {
            continue;
        }
        $requests[$uuid] = "http://crafatar.com/avatars/$uuid?size=$size";
    }

    // get data of all requests
    $D = umc_get_fcontent($requests);
    // parse data replies
    $failed_users = array();
    foreach ($D as $uuid => $R) {
        XMPP_ERROR_trace('uuid', $uuid);
        $file = $path . $uuid . ".$size.png";

        if ($R['response']['content_type'] !== 'image/png' && $R['response']['http_code'] !== 200) {
            $failed_users[] = array(
                'uuid' => $uuid, 
                'url' => $R['response']['url'],
                'reason' => 'Could not download file',
            );
            // get standard steve face
            if (!file_exists($steve_head)) {
                XMPP_ERROR_trigger("Steve head icon not available");
            } else {
                $check = copy($steve_head, $file);
                if (!$check || !file_exists($file)) {
                    XMPP_ERROR_trigger("Could not create steve head for file $file");
                } else {
                    XMPP_ERROR_trace("used steve head for $file");
                }
            }
        } else {
            $written = file_put_contents($file, $R['content']);
            if (!$written) {
                $failed_users[] = array(
                    'uuid' => $uuid, 
                    'url' => $R['response']['url'],
                    'reason' => "Could not save file to $file",
                );
            }
        }
    }

    if (count($failed_users) > 0) {
        XMPP_ERROR_trace("failed users:", $failed_users);
        XMPP_ERROR_trigger("Users failed to get icon, see error report for details");
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
