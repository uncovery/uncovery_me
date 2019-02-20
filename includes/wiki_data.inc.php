<?php

function umc_item_icon_html($item_name) {
    global $UMC_DOMAIN, $UMC_SETTING, $ITEM_SPRITES;
    $version = $UMC_SETTING['mc_version'];
    $assets_blocks_path = $UMC_SETTING['path']['server'] . "/mc_assets/minecraft-assets/data/$version/blocks";

    $wiki_icon_path = $UMC_SETTING['path']['server'] . "/bin/data/item_icons";

    if (file_exists("$wiki_icon_path/$item_name.png")) {
        $url = $UMC_DOMAIN . "/websend/item_icons/$item_name.png";
        $img = "<img src=\"$url\">";
        return $img;
    } else if (isset($ITEM_SPRITES[$item_name])) {
        $img = "<span class=\"item_sprite item_{$item_name}\"> </span>";
    /*} else if (file_exists("$assets_blocks_path/$item_name.png")) {
        $url = $UMC_DOMAIN . "/admin/mc_assets/$version/blocks/$item_name.png";
        $img = "<img src=\"$url\">";
        return $img;*/
    } else {
        $item_name = 'invalid: ' . "$assets_blocks_path/$item_name.png" . "?";
        $img = "<span class=\"item_sprite item_{$item_name}\"> </span> ?" ;
    }
    return $img;
}

function umc_block_icons_get_wiki() {
    global $UMC_CONFIG, $UMC_DATA, $UMC_PATH_MC;

    // STEP 1: get the whole website data
    $url = 'https://minecraft.gamepedia.com/Java_Edition_data_values';

    $url_data = unc_serial_curl($url, 0, 50, '/home/includes/unc_serial_curl/google.crt'); // ,0,50, $UMC_CONFIG['ssl_cert']

    if ($url_data[0]['content'] == '') {
        echo "No content found!";
        return;
    }

    $matches = false;
    $regex = '/src="(?\'full_url\'.*.png\?version=.*)".*\n.*\n.*<code>(?\'item_name\'.*)<\/code>/mU';
    preg_match_all($regex, $url_data[0]['content'], $matches, PREG_SET_ORDER, 0);

    // now get all valid item_name URLS and write them into an array

    echo "found " . count($matches) . " Matches!\n";

    $icon_urls = array();
    foreach ($matches as $match) {
        $item_name = str_replace(" ",  "", strtolower($match['item_name']));
        if (isset($UMC_DATA[$item_name])) {
            $icon_urls[$item_name] = $match['full_url'];
        }
    }

    echo "found " . count($icon_urls) . " valid item names!\n";

    // now pass all the URLS to serial_curl
    $failed_icons = array();
    $S = unc_serial_curl($icon_urls, 0, 50, '/home/includes/unc_serial_curl/google.crt');
    foreach ($S as $item_name => $s) {
        $icon_file = "$UMC_PATH_MC/server/bin/data/item_icons/$item_name.png";
        if (strstr("access denied", $s['content'])) {
            $failed_icons[] = array(
                'item_name' => $item_name,
                'url' => $s['response']['url'],
                'reason' => "Access denied to remote file!",
            );
        } else {
            $written = file_put_contents($icon_file, $s['content']);
            if (!$written) {
                $failed_icons[] = array(
                    'item_name' => $item_name,
                    'url' => $s['response']['url'],
                    'reason' => "Could not save file to $icon_file",
                );
            }
        }

    }
    if (count($failed_icons) > 0) {
        XMPP_ERROR_trace("failed users:", $failed_icons);
        $counter = count($failed_icons);
        XMPP_ERROR_trigger("$counter item icons failed to get icon!");
    }
}

function umc_item_icons_get_wiki() {
    global $UMC_PATH_MC, $UMC_DATA;

    // let's get the image first

    $css_items_url = 'https://gamepedia.cursecdn.com/minecraft_gamepedia/f/f5/ItemCSS.png';
    $image_data = unc_serial_curl($css_items_url, 0, 50, '/home/includes/unc_serial_curl/google.crt');
    $icon_file = "$UMC_PATH_MC/server/bin/data/images/ItemCSS.png";

    file_put_contents($icon_file, $image_data[0]['content']);


    // STEP 1: get the whole website data
    $url = 'https://minecraft.gamepedia.com/Java_Edition_data_values';
    $url_data = unc_serial_curl($url, 0, 50, '/home/includes/unc_serial_curl/google.crt'); // ,0,50, $UMC_CONFIG['ssl_cert']

    $matches = false;
    $regex = '/item-sprite" style="background-image:url\((?\'image_url\'.*)\);background-position:(?\'position\'.*)".*\n.*\n.*<code>(?\'item_name\'.*)<\/code>/mU';
    preg_match_all($regex, $url_data[0]['content'], $matches, PREG_SET_ORDER, 0);

    $item_sprite_css = '.item_sprite {display: inline-block; background-size: 256px; background-image: url(/admin/img/ItemCSS.png); background-repeat: no-repeat; width:16px; height:16px;}';

    $icon_positions = array();
    foreach ($matches as $match) {
        $item_name = str_replace(" ",  "", strtolower($match['item_name']));
        $position = $match['position'];
        if (isset($UMC_DATA[$item_name])) {
            $icon_positions[$item_name] = $position;
            $item_sprite_css .= "\n.item_$item_name {background-position:$position;}";
        }
    }
    ksort($icon_positions);
    umc_array2file($icon_positions, "ITEM_SPRITES", "/home/minecraft/server/bin/assets/item_sprites.inc.php");

    // vertical-align: text-top;

    $css_file = "$UMC_PATH_MC/server/bin/data/item_sprites.css";
    file_put_contents($css_file, $item_sprite_css);
}

function umc_items_get_unavailable() {
    global $UMC_DATA;
    // STEP 1: get the whole website data
    $url = 'https://minecraft.gamepedia.com/Java_Edition_data_values';
    $url_data = unc_serial_curl($url, 0, 50, '/home/includes/unc_serial_curl/google.crt'); // ,0,50, $UMC_CONFIG['ssl_cert']

    $matches = false;
    $regex = '/style="background-color: #D3D3D3;"><code>(?\'item_name\'.*)<\/code>/mU';
    preg_match_all($regex, $url_data[0]['content'], $matches, PREG_SET_ORDER, 0);

    $items = array();
    foreach ($matches as $match) {
        $item_name = strtolower($match['item_name']);
        if (isset($UMC_DATA[$item_name])) {
            $items[] = $item_name;

        }
    }
    ksort($items);
    umc_array2file($items, "ITEM_UNAVAILABLE", "/home/minecraft/server/bin/assets/item_unavailable.inc.php");
}