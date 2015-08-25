<?php

global $UMC_FUNCTIONS;
$UMC_FUNCTIONS['usericon_get'] = 'umc_usericon_get';

function umc_usericon_get($users = false, $update = true) {
    XMPP_ERROR_trigger($users);
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_PATH_MC;
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

    $users_raw = array();
    foreach ($users as $uuid => $username) {
        $uuid_raw = str_replace("-", "", $uuid);
        $users_raw[$uuid] = $url = "https://sessionserver.mojang.com/session/minecraft/profile/$uuid_raw";
    }

    $no_skin = array();
    $failed_users = array();
    $skin_urls = array();
    $D = unc_serial_curl($users_raw, 0, 50, '/home/includes/unc_serial_curl/google.crt');
    foreach ($D as $uuid => $d) {
        // we only update the skin if it does not exist
        if (!$update && file_exists("$UMC_PATH_MC/server/bin/data/full_skins/$uuid.png")) {
            continue;
        }
        
        if ($uuid == 'abandone-0000-0000-0000-000000000000') {
            continue;
        }
        if ($d['response']['http_code'] !== 200) {
            $failed_users[] = array(
                'uuid' => $uuid,
                'url' => $d['response']['url'],
                'reason' => 'Could not download user data',
            );    
        }
        
        $base64_texture = '';
        $d_arr = json_decode($d['content']);
        if (!$d_arr) {
            XMPP_ERROR_trigger("Failed to retrieve session profile for $uuid");
        }
        //object(stdClass)#2 (3) {
        //  ["id"]=>
        //  string(32) "ab3bc877443445a993bdbab6df41eabf"
        //  ["name"]=>
        //  string(8) "uncovery"
        //  ["properties"]=>
        //  array(1) {
        //    [0]=>
        //    object(stdClass)#3 (2) {
        //      ["name"]=>
        //      string(8) "textures"
        //      ["value"]=>
        //      string(308) "eyJ0aW1lc3RhbXAiOjE0NDA0NzUyOTQ2NDksInByb2ZpbGVJZCI6ImFiM2JjODc3NDQzNDQ1YTk5M2JkYmFiNmRmNDFlYWJmIiwicHJvZmlsZU5hbWUiOiJ1bmNvdmVyeSIsInRleHR1cmVzIjp7IlNLSU4iOnsidXJsIjoiaHR0cDovL3RleHR1cmVzLm1pbmVjcmFmdC5uZXQvdGV4dHVyZS9jYWVhMjljODY2ZDkyMTVhYWJjMTk5MDQyMTE1ZWMwNTUzMzJkNjZlMGI4ZWY2ZjkyNjNmZTRiMWZlNzZlIn19fQ=="
        //    }
        //  }
        //}
        if (!isset($d_arr->properties)) {
            XMPP_ERROR_trace("json", $d_arr);
            XMPP_ERROR_trigger("Failed to retrieve properties for $uuid");
        }
        $prop_count = count($d_arr->properties);
        for ($i=0; $i<$prop_count; $i++) {
            if ($d_arr->properties[$i]->name == 'textures') {
                $base64_texture = $d_arr->properties[$i]->value;
            } else {
                echo "Wrong property: " . $d_arr->properties[$i]->name;
            }
        }
        $raw_texture = base64_decode($base64_texture);
        // {"timestamp":1440475294649,"profileId":"ab3bc877443445a993bdbab6df41eabf","profileName":"uncovery","textures":{"SKIN":{"url":"http://textures.minecraft.net/texture/caea29c866d9215aabc199042115ec055332d66e0b8ef6f9263fe4b1fe76e"}}}

        $texture_arr = json_decode($raw_texture);
        if (!$texture_arr) {
            XMPP_ERROR_trigger("Failed to decode texture: $raw_texture");
        }

        $time_stamp = $texture_arr->timestamp;
        // check if the file on the drive is newer
        $current_file = "$UMC_PATH_MC/server/bin/data/full_skins/$uuid.png";
        if ((!file_exists($current_file)) || filemtime($current_file) > $time_stamp) {
            if (isset($texture_arr->textures->SKIN)) { // user did not set skin
                $skin_urls[$uuid] = $texture_arr->textures->SKIN->url;
                // echo $texture_arr->textures->SKIN->url . "<br>\n";
            } else {
                XMPP_ERROR_trace("$uuid does not have a skin: $raw_texture");
                $no_skin[] = $uuid;
            }
        }
    }

    $S = unc_serial_curl($skin_urls);
    foreach ($S as $uuid => $s) {
        $skin_file = "$UMC_PATH_MC/server/bin/data/full_skins/$uuid.png";
        $head_file = "$UMC_PATH_MC/server/bin/data/user_icons/$uuid.png";
        if ($s['response']['content_type'] !== 'image/png' && $s['response']['http_code'] !== 200) {
            $failed_users[] = array(
                'uuid' => $uuid,
                'url' => $s['response']['url'],
                'reason' => 'Could not download image',
            );
            continue;
        }
        $written = file_put_contents($skin_file, $s['content']);
        if (!$written) {
            $failed_users[] = array(
                'uuid' => $uuid,
                'url' => $s['response']['url'],
                'reason' => "Could not save file to $skin_file",
            );
            continue;
        }
        
        // convert to head icon, resize to 20x20
        $command = "convert -crop '8x8+8+8' -scale 20 \"$skin_file\" \"$head_file\"";
        exec($command);
    }

    // process users w/o skin
    foreach ($no_skin as $uuid) {
        $head_file = "$UMC_PATH_MC/server/bin/data/user_icons/$uuid.png";
        if (!file_exists($steve_head)) {
            XMPP_ERROR_trigger("Steve head icon not available");
        } else {
            $check = copy($steve_head, $head_file);
            if (!$check || !file_exists($head_file)) {
                XMPP_ERROR_trigger("Could not create steve head for file $head_file");
            } else {
                XMPP_ERROR_trace("used steve head for $head_file");
            }
        }        
    }

    if (count($failed_users) > 0) {
        XMPP_ERROR_trace("failed users:", $failed_users);
        XMPP_ERROR_trigger("Users failed to get icon, see error report for details");
    }
}

function umc_user_get_icon_url($uuid_requested, $update = false) {
    global $UMC_DOMAIN, $UMC_PATH_MC;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    if (strstr($uuid_requested, ' ')) {
        return '';
    }
    // make sure it's a uuid
    $uuid = umc_uuid_getone($uuid_requested, 'uuid');

    $path = "$UMC_PATH_MC/server/bin/data/user_icons/";
    if (!file_exists($path . $uuid . ".png") && $update) {
        // this tries to download the latest version, otherwise falls back to steve icon
        // umc_update_usericons($uuid);
        umc_usericon_get($uuid);
    } else if (!file_exists($path . $uuid . ".png") && !$update) {
        return false;
    }
    $url = "$UMC_DOMAIN/websend/user_icons/$uuid.png";
    return $url;
}
