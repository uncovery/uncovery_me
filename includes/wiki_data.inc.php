<?php

function umc_item_icon_html($item_name) {
    global $UMC_DOMAIN, $UMC_SETTING, $ITEM_SPRITES, $UMC_PATH_MC;
    $version = $UMC_SETTING['mc_version'];
    $assets_path = "$UMC_PATH_MC/mc_assets/minecraft-assets/data/$version";

    if (file_exists($assets_path . "blocks/$item_name.png")) {
        $icon_url = "$UMC_DOMAIN/websend/mc-assets/$version/blocks/$item_name.png";
        $html = "<img src=\"$icon_url\">";
    } else if (file_exists($assets_path . "items/$item_name.png")) {
        $icon_url = "$UMC_DOMAIN/websend/mc-assets/$version/items/$item_name.png";
        $html = "<img src=\"$icon_url\">";
    } else if (file_exists("$UMC_PATH_MC/server/bin/data/item_icons/$item_name.png")) {
        $icon_url = $UMC_DOMAIN . "/websend/item_icons/$item_name.png";
        $html = "<img src=\"$icon_url\" width=\"30\" height=\"30\">";
    } else if (isset($ITEM_SPRITES[$item_name])) {
        $html = "<span class=\"item_sprite item_{$item_name}\"> </span>";
    } else {
        XMPP_ERROR_trace("$item_name icon not found" );
        $html = "<span> </span> ?" ;
    }
    return $html;
}

/**
 * get icons for blocks, those are individual icons
 *
 * @global type $UMC_DATA
 * @global type $UMC_PATH_MC
 * @return type
 */
function umc_block_icons_get_wiki($update = true) {
    global $UMC_DATA, $UMC_PATH_MC;

    // STEP 1: get the whole website data, only for blocks, those are individual icons
    $url = 'https://minecraft.gamepedia.com/index.php?title=Java_Edition_data_values/Blocks';

    $url_data = unc_serial_curl($url, 0, 50, '/home/includes/unc_serial_curl/google.crt'); // ,0,50, $UMC_CONFIG['ssl_cert']

    if ($url_data[0]['content'] == '') {
        echo "No content found!";
        return;
    }

    $matches = false;
    // $regex = '/src="(?\'full_url\'.*.png\?version=.*)".*\n.*\n.*<code>(?\'item_name\'.*)<\/code>/mU';
    $regex = '/src="(?\'full_url\'.*.png)\/revision\/.*".*\n.*\n.*<code>(?\'item_name\'.*)<\/code>/mU';
    preg_match_all($regex, $url_data[0]['content'], $matches, PREG_SET_ORDER, 0);

    // now get all valid item_name URLS and write them into an array

    echo "found " . count($matches) . " block Matches!\n";

    $icon_urls = array();
    foreach ($matches as $match) {
        $item_name = str_replace(" ",  "", strtolower($match['item_name']));
        if (isset($UMC_DATA[$item_name])) {
            $icon_urls[$item_name] = $match['full_url'];
        } else {
            echo "could not match $item_name\n";
        }
    }


    echo "found " . count($icon_urls) . " valid block names!\n";

    // now pass all the URLS to serial_curl
    $failed_icons = array();
    $S = unc_serial_curl($icon_urls, 0, 50, '/home/includes/unc_serial_curl/google.crt');
    foreach ($S as $item_name => $s) {
        $icon_file = "$UMC_PATH_MC/server/bin/data/item_icons/$item_name.png";
        if (strstr($s['content'], "access denied")) {
            $failed_icons[] = array(
                'item_name' => $item_name,
                'url' => $s['response']['url'],
                'reason' => "Access denied to remote file!",
            );
        } else {
            if (
                ($update && (!file_exists($icon_file) || filesize($icon_file) == 0)
                ||
                !$update)
                ) {
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

    }
    if (count($failed_icons) > 0) {
        XMPP_ERROR_trace("failed users:", $failed_icons);
        $counter = count($failed_icons);
        XMPP_ERROR_trigger("$counter item icons failed to get icon!");
    }
}

function umc_item_icons_get_wiki() {
    global $UMC_PATH_MC, $UMC_DATA;

    // STEP 1: get the whole website data
    $page_url = 'https://minecraft.gamepedia.com';
    $url = $page_url . '/Java_Edition_data_values/Items';
    $url_data = unc_serial_curl($url, 0, 50, '/home/includes/unc_serial_curl/google.crt'); // ,0,50, $UMC_CONFIG['ssl_cert']

    $matches = false;
    $regex = '/item-sprite" style="background-image:url\((?\'image_url\'.*)\);background-position:(?\'xpos\'-.*)px (?\'ypos\'-.*)px".*\n.*\n.*<code>(?\'item_name\'.*)<\/code>/mU';
    preg_match_all($regex, $url_data[0]['content'], $matches, PREG_SET_ORDER, 0);

    $item_sprite_css = '.item_sprite {display: inline-block; background-size: 512px; background-image: url(/admin/img/ItemCSS.png); background-repeat: no-repeat; width:32px; height:32px;}';

    $icon_positions = array();
    $count = 0;
    $image_downloaded = false;
    foreach ($matches as $match) {
        if (!$image_downloaded) {
            // let's get the sprite image once
            $sprite_data = unc_serial_curl($page_url . $match['image_url'], 0, 50, '/home/includes/unc_serial_curl/google.crt');
            $target_icon_file = "$UMC_PATH_MC/server/bin/data/images/ItemCSS.png";
            file_put_contents($target_icon_file, $sprite_data[0]['content']);
            $image_downloaded = true;
        }

        $item_name = str_replace(" ",  "", strtolower($match['item_name']));
        $x_pos = $match['xpos'] * 2; // we scale the background and the icons so that they match teh block icon size
        $y_pos = $match['ypos'] * 2;
        if (isset($UMC_DATA[$item_name])) {
            $icon_positions[$item_name] = "{$x_pos}px {$y_pos}px";
            $item_sprite_css .= "\n.item_$item_name {background-position:{$x_pos}px {$y_pos}px;}";
            $count++;
        }
    }
    echo "found $count item matches!";

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
/**
 *
 */

// icons: https://gamepedia.cursecdn.com/minecraft_gamepedia/4/42/AchievementSprite.png

function umc_wiki_achievements() {
    // this returns a JSON array of the page
    $url = "https://minecraft.gamepedia.com/api.php?action=query&titles=Achievement&export=true&exportnowrap=true";
    $url_data = unc_serial_curl($url, 0, 50, '/home/includes/unc_serial_curl/google.crt');


    $xml = simplexml_load_string($url_data[0]['content'], "SimpleXMLElement", LIBXML_NOCDATA);

    // convert to array
    $json = json_encode($xml->page->revision->text);
    $array = json_decode($json,TRUE);

    $data = $array[0];



}