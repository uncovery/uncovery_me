<?php
// create maps for singe lots

function umc_assemble_maps() {
    global $UMC_SETTING, $UMC_PATH_MC;
    // create lots
    $worlds = $UMC_SETTING['world_data'];
    // $worlds = array('empire'    => array('lot_size' => 128, 'lot_number' => 32));
    // $worlds = array('kingdom'   => array('lot_size' => 272, 'lot_number' => 24));
    // $worlds = array('draftlands' => array('lot_size' => 272, 'lot_number' => 16));
    // $worlds = array('skyblock'  => array('lot_size' => 128, 'lot_number' => 20));
    // $worlds = array('aether2' => array('lot_size' => 192, 'lot_number' => 16, 'prefix' => 'aet'));
    $maxmin = array(
        'empire' => array('min_1' => -5, 'min_2' => -5, 'max_1' => 4, 'max_2' => 4),
        'flatlands' => array('min_1' => -3, 'min_2' => -3, 'max_1' => 4, 'max_2' => 4),
        'aether' => array('min_1' => -4, 'min_2' => -4, 'max_1' => 3, 'max_2' => 3),
        'kingdom' => array('min_1' => -7, 'min_2' => -7, 'max_1' => 7, 'max_2' => 7),
        'draftlands' => array('min_1' => -7, 'min_2' => -7, 'max_1' => 7, 'max_2' => 7),
        'skyblock' => array('min_1' => -3, 'min_2' => -3, 'max_1' => 4, 'max_2' => 4),
        'empire_new' => array('min_1' => -5, 'min_2' => -5, 'max_1' => 4, 'max_2' => 4),
        'city' => array('min_1' => -2, 'min_2' => -4, 'max_1' => 3, 'max_2' => 1),
        'darklands' => array('min_1' => -5, 'min_2' => -5, 'max_1' => 5, 'max_2' => 5),
    );

    foreach ($worlds as $world => $dim) {
        mysql_connect('localhost', 'minecraft', '9sd6ncC9vEcTD55Z');
        // make chunk files
        // clean up data files
        $folder = $UMC_SETTING['path']['bukkit'] . "/$world/region";
        $mapper_folder = $UMC_SETTING['path']['server'] . '/togos_map';
        $destination = $UMC_SETTING['path']['server'] .  "/maps";
        echo "$world: ";
        // create chunk maps   /// -biome-map $mapper_folder/biome-colors.txt -color-map $mapper_folder/block-colors.txt
        $command = "java -jar $mapper_folder/TMCMR.jar $folder -create-big-image -region-limit-rect {$maxmin[$world]['min_1']} {$maxmin[$world]['min_2']} {$maxmin[$world]['max_1']} {$maxmin[$world]['max_2']} -o $destination/$world/png";
        exec($command);
        echo "chunk maps rendered";

        // compress map to new map
        $command1 = "convert $destination/$world/png/big.png -quality 60% $destination/{$world}_large.jpg";
        exec($command1);
        echo ", map compressed";

        $file_1 = "$destination/{$world}_large.jpg";
        $file_2 = "$destination/{$world}.jpg";
        $size = $UMC_SETTING['world_img_dim'][$world]['max_coord'] * 2;
        $border = $UMC_SETTING['world_img_dim'][$world]['chunkborder'];
        $command2 = "convert -crop '{$size}x{$size}+{$border}+{$border}' $file_1 $file_2";
        exec($command2);
        echo ", cropped map to border size {$size}x{$size}+{$border}+{$border}";
        //umc_assemble_tmc_map($world);
        //echo ", Single file map assembled";
        // create lot maps
        umc_disassemble_map($world);
        echo ", Lot maps cut";

        umc_heatmap($world);
        echo ", heat map rendered\n";
    }

    /*
    $large_worlds = array('darklands');
    foreach ($large_worlds as $world) {
        // clean up data files
        $folder = "$UMC_PATH_MC/server/bukkit/$world/region";
        if ($world == 'nether') {
            $folder = "$UMC_PATH_MC/server/bukkit/nether/DIM-1/region";
        }
        echo "$world: ";
        // create chunk maps
        $command = "java -jar $UMC_PATH_MC/server/togos_map/TMCMR.jar  -region-limit-rect -3 -3 3 3 $folder -o $UMC_PATH_MC/server/maps/$world/png";
        exec($command);
    }
    */

}

function umc_disassemble_map($world = 'empire') {
    global $UMC_SETTING, $UMC_PATH_MC;
    $dim = $UMC_SETTING['world_data'][$world];
    $source = "$UMC_PATH_MC/server/maps";

    // get lot list with coordinates
    if ($world == 'city') {
        return;
    }


    $sql = "SELECT region_id as lot, min_x, min_z, max_x, max_z FROM minecraft_worldguard.region_cuboid "
        . "LEFT JOIN minecraft_worldguard.world ON world_id = id WHERE name='$world';";
    $rst = mysql_query($sql);
    if (!$rst) {
        $error = mysql_errno($rst) . ": " . mysql_error($rst). "\n";
        XMPP_ERROR_trigger("disassemble_map failed: $error $sql");
    }
    if (mysql_num_rows($rst) == 0) {
        return;
    }

    if ($world == 'kingdom' || $world == 'draftlands') {
        $map = $UMC_SETTING['world_img_dim'][$world];
        $source = "$UMC_PATH_MC/server/maps";
        while ($row = mysql_fetch_array($rst, MYSQL_ASSOC)) {
            $lot = $row['lot'];
            if ($lot == "__global__") {
                continue;
            }
            $x1 = conv_x($row['min_x'], $map);
            $x2 = conv_x($row['max_x'], $map);
            $z1 = conv_z($row['min_z'], $map);
            $z2 = conv_z($row['max_z'], $map);

            $base_x = min($x1, $x2);
            $base_z = min($z1, $z2);
            $size_x = max($x1, $x2) - $base_x;
            $size_z = max($z1, $z2) - $base_z;

            $command = "convert -crop '{$size_x}x{$size_z}+{$base_x}+{$base_z}' \"$source/$world.jpg\" \"$source/lots/$world/$lot.png\"";
            // echo $command . "\n";
            exec($command);
        }
    } else {
        $lot_size = $dim['lot_size'];
        $world_lots = $dim['lot_number'];
        if (!isset($dim['lot_number'])) {
            XMPP_ERROR_trigger("Error disassembling world $world map");
        }
        $command = "convert \"$source/$world.jpg\" +repage -crop $lot_size". "x". "$lot_size +repage \"$source/lots/$world/$world.png\" ";
        // echo $command . "\n";
        exec($command);

        // rename the files
        $lot_array = array();
        // this creates an array of the line number a letter would have (A=1, B=2 etc)
        for ($i=1; $i<=$world_lots; $i++) {
            if ($i <= 26) {
                $letter = chr(96 + $i);
            } else {
                $letter = 'a'. chr(70 + $i);
            }
            $lot_array[$letter] = $i;
        }
        // var_dump($lot_array);
        while ($row = mysql_fetch_array($rst, MYSQL_ASSOC)) {
            $lot = $row['lot'];
            if ($lot == "__global__") {
                continue;
            }
            $lot_str = explode("_", $lot);
            // this is needed because of the "city_spawn" lot
            if ($lot_str[1] == 'spawn') {
                continue;
            }
            $lot_coords = array();
            preg_match('#^([a-zA-Z]*)(\d*)#', $lot_str[1], $lot_coords);
            if (!isset($lot_coords[1]) || !isset($lot_coords[2])) {
                XMPP_ERROR_send_msg("Disassemble $world failed: " . var_export($lot_coords, true));
            }
            $lot_letter = $lot_coords[1];
            $lot_number = $lot_coords[2];
            // this is the final line number
            if (!isset($lot_array[$lot_letter])) { // some lots do not fit the pattern (arena etc)
                continue;
            }
            $row_no = $world_lots - $lot_array[$lot_letter];
            $index = ($row_no * $world_lots)+ $lot_number - 1;
            // echo "$lot: $lot_letter -> {$lot_array[$lot_letter]} of $world_lots => $row_no + $lot_number = $index <br>";
            // rename files
            // echo "renaming {$world}-$index.png -> $lot.png\n";
            if (file_exists("$source/lots/$world/{$world}-$index.png")) {
                rename("$source/lots/$world/{$world}-$index.png", "$source/lots/$world/$lot.png");
            } else {
                echo "File $source/lots/$world/{$world}-$index.png not found \n";
            }
        }
    }
}

#-- Return associative array of region data for a world
#-- Note that regions with no Owners or members -will- still be listed.
#-- Format:
#--  Array(
#--     [region_name] => Array (
#--           [Owners]   => Array (
#--                ... Owners here ...
#--            )
#--           [members]  => Array (
#--                ... members here ...
#--            )
#--     )
#-- )
function umc_region_data($world_name) {
    $world_id = umc_get_worldguard_id('world', $world_name);
    if ($world_id === null) {
        XMPP_ERROR_trigger("Tried to find ID for World $world_name and failed (umc_region_data)");
        return false;
    }
    $reg_sql = "SELECT region.id, region.world_id, min_x, min_y, min_z, max_x, max_y, max_z, version, mint_version 
        FROM minecraft_worldguard.region 
        LEFT JOIN minecraft_worldguard.region_cuboid ON region.id = region_cuboid.region_id AND region.world_id = region_cuboid.world_id 
        LEFT JOIN minecraft_srvr.lot_version ON id=lot 
        WHERE region.world_id = $world_id AND region_cuboid.world_id=$world_id;";
    //echo $reg_sql;
    $reg_rst = mysql_query($reg_sql);
    $region_list = array();
    while ($reg_row = mysql_fetch_array($reg_rst, MYSQL_ASSOC)) {
        $region_id = $reg_row['id'];
        $region_list[$region_id]['min'] = array('x' => $reg_row['min_x'], 'y' => $reg_row['min_y'], 'z' => $reg_row['min_z']);
        $region_list[$region_id]['max'] = array('x' => $reg_row['max_x'], 'y' => $reg_row['max_y'], 'z' => $reg_row['max_z']);
        $region_list[$region_id]['version'] = $reg_row['version'];
        $region_list[$region_id]['mint_version'] = $reg_row['mint_version'];

        $sql = "SELECT region.id AS region_id, UUID.username AS user_name, user.uuid as uuid,
            region_players.Owner AS player_Owner, region_groups.Owner AS group_Owner, `group`.`name` AS group_name
            FROM minecraft_worldguard.region
            LEFT JOIN minecraft_worldguard.region_players ON region.id = region_players.region_id AND region.world_id = region_players.world_id
            LEFT JOIN minecraft_worldguard.region_groups ON region.id = region_groups.region_id AND region.world_id = region_groups.world_id
            LEFT JOIN minecraft_worldguard.`group` ON `group`.id = region_groups.group_id
            LEFT JOIN minecraft_worldguard.user ON user.id = region_players.user_id
            LEFT JOIN minecraft_srvr.UUID ON user.uuid = UUID.UUID
            WHERE region.world_id = $world_id
            AND region.id= '$region_id';";
        // echo $sql;
        $rst = mysql_query($sql);
        $region_list[$region_id]['owners'] = false;
        $region_list[$region_id]['members'] = false;
        while ($row = mysql_fetch_array($rst, MYSQL_ASSOC)) {
            $player = $row['user_name'];
            $uuid = $row['uuid'];
            $group = $row['group_name'];
            $player_OwnerFlag = $row['player_Owner'];
            $group_OwnerFlag = $row['group_Owner'];

            /*if(!isset($region_list[$region_id])) {
                $region_list[$region_id] = array(
                    'owners' => array('players' => array(), 'groups' => array()),
                    'members' => array('players' => array(), 'groups' => array()),
                    'min' => array('x' => $row['min_x'], 'y' => $row['min_y'], 'z' => $row['min_z']),
                    'max' => array('x' => $row['max_x'], 'y' => $row['max_y'], 'z' => $row['max_z']),
                );
            }*/
            if (strlen($group) > 0) {
                if ($group_OwnerFlag == 1) {
                    $region_list[$region_id]['owners'][] = "group:$group";
                } else {
                    $region_list[$region_id]['members'][] = "group:$group";
                }
            }
            if (strlen($player) > 0) {
                if ($player_OwnerFlag == 1) {
                    $region_list[$region_id]['owners'][$uuid] = $player;
                } else {
                    $region_list[$region_id]['members'][$uuid] = $player;
                }
            }
        }
    }
    return $region_list;
}

function umc_heatmap($world) {
    return;
    include_once('/home/minecraft/server/bin/includes/tempest/Tempest.php');
    global $UMC_SETTING, $UMC_PATH_MC;
    if ($world == 'empire_new') {
        return;
    }
    // echo "Heatmap $world: ";
    $file = "$UMC_PATH_MC/server/maps/$world.jpg";
    $sql = "SELECT * FROM minecraft_srvr.lag_location WHERE world='$world';";
    $rst = mysql_query($sql);
    if (!$rst) {
        $error = mysql_errno() . ": " . mysql_error(). "\n";
        XMPP_ERROR_trigger("umc_heatmap failed: $error $sql");
    }
    $data = array();
    $map = $UMC_SETTING['world_img_dim'][$world];
    if (mysql_num_rows($rst) > 0) {
        while ($row = mysql_fetch_array($rst, MYSQL_ASSOC)) {
            $x = conv_x($row['x_coord'], $map);
            $z = conv_z($row['z_coord'], $map);
            $data[] = array($x, $z);
            // get max coordinates
        }
        $heatmap = new Tempest(array(
            'input_file' => "$UMC_PATH_MC/server/maps/$world.jpg",
            'output_file' => "$UMC_PATH_MC/server/maps/$world" . '_heatmap.jpg',
            'coordinates' => $data //array( array(0,10), array(2,14), array(2,14) ),
        ));
        $heatmap->set_image_lib('imagick');
        $heatmap->render();
    }
}

function umc_map_menu($worlds, $current_world, $freeswitch) {
    global $UMC_DOMAIN;
    $freevalue = 'false';
    if ($freeswitch) {
        $freevalue = 'true';
    }
    $this_uc_map = ucwords($current_world);
    $menu = "\n<!-- Menu -->\n<strong>Uncovery $this_uc_map map</strong>\n <button type='button' onclick='find_spawn()'>Find Spawn</button>\n"
        . " <button type='button' onclick='toggleLotDisplay()'>Display mode</button>\n"
        . " Choose world:\n <form action=\"$UMC_DOMAIN/admin/\" method=\"get\" style=\"display:inline;\">\n    <div style=\"display:inline;\">"
        . "        <input type='hidden' name='freeonly' value='$freevalue'>\n"
        . "        <input type='hidden' name='function' value='create_map'>\n"
        . "        <select name=\"world\" style=\"display:inline;\" onchange='this.form.submit()'>\n";
    foreach ($worlds as $worldname) {
        $uc_worldname = ucwords($worldname);
        $selected = '';
        if ($worldname == $current_world) {
            $selected = ' selected';
        }
        $menu .= "            <option value=\"$worldname\"$selected>$uc_worldname</option>\n";
    }
    $menu .= "        </select>\n    </div></form>\n "
          . "<a id='link_3d_maps' href=\"$UMC_DOMAIN/dynmap/#\">3D Maps</a>\n ";

    /* heatmap is disabled
    if ($lag) {
        $menu .= "<a href=\"$UMC_DOMAIN/admin/index.php?function=create_map&amp;world=$world\">Normal Map</a>\n | ";
    } else {
        $menu .= "<a href=\"$UMC_DOMAIN/admin/index.php?function=create_map&amp;world=$world&amp;lag=true\">Heatmap</a>\n | ";
    }
    */
    
    $menu .= umc_read_markers_file('scrollto', $current_world);
    
    return $menu;
}

// loads lot information, displays current map and overlays it with lot information
function umc_create_map() {
    global $UMC_SETTING, $UMC_DOMAIN, $UMC_PATH_MC, $UMC_ENV;
    $timer = array();
    $UMC_ENV = '2Dmap';

    $file = $UMC_SETTING['map_css_file'];
    $css = "\n" . '<style type="text/css">' . file_get_contents($file) . "\n";
    $worlds = array('city', 'empire', 'aether', 'flatlands', 'kingdom', 'draftlands', 'skyblock', 'empire_new');
    $longterm = $UMC_SETTING['longterm'];

    $s_post  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
    $s_get = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);

    if (isset($s_get['world'])) {
        $world = $s_get['world'];
        if (!in_array($world, $worlds)) {
            exit;
        }
    } else if (isset($s_post['world'])) {
        $world = $s_post['world'];
        if (!in_array($world, $worlds)) {
            exit;
        }
    } else {
        $world = 'empire';
    }
    // get donators
    $donators = umc_users_donators();

    $track_player_icon = '';
    $find_lot = false;
    $settler_test = false;
    $track_player = false;
    // part of the settler test
    if (isset($s_post['settler_test'])) {
        $settler_test = true;
    } else if (isset($s_post['track_player'])) {
        $player = $s_post['track_player'];
        $loc = umc_read_markers_file('array', $world);
        // something is wrong: player is not online or in the wrong world
        if (!isset($loc[$player])) {
            //umc_error_longmsg("Could not find player $player on the 2D map for the settler test! (track_player)\n" . umc_ws_vardump($loc));
            return "You need to be login on the server and be in the $world world to do this. Please go back to the previous page and try again.";
        } else { // player is in the right world.
            $track_player = true;
            $track_player_icon = "&identify_user=$player";
        }
    } else if (isset($s_post['guide_lot'])) {
        $player = $s_post['guide_lot'];
        $loc = umc_read_markers_file('array', $world);
        if (!isset($loc[$player])) {
            XMPP_ERROR_trigger("Could not find player $player on the 2D map for the settler test! (guide_lot)\n" . umc_ws_vardump($loc));
            return "You need to be login on the server and be in the $world world to do this. Please go back to the previous page and try again.";
        }  else { // player is in the right world.
            $find_lot = true;
            $track_player_icon = "&track_user=$player";
        }
        $player_z = floor($loc[$player]['top']) + 20;
        $player_x = floor($loc[$player]['left']) + 10;
        $player_lot = $s_post['lot'];
    } else {
        $player_x = 0;
        $player_z = 0;
        $lot_x = 0;
        $lot_z = 0;
    }

    $lag = false;
    if (isset($s_get['lag'])) {
        $lag = true;
    }

    $freeonly = false;
    if (
        (isset($s_get['freeonly']) && $s_get['freeonly'] == 'true') 
        || 
        (isset($s_post['freeonly']) && $s_post['freeonly'] == 'true')) 
        {
        $freeonly = true;
    }

    $menu = '';
    if ($settler_test) {
        $menu .= "<form action=\"$UMC_DOMAIN/server-access/buildingrights/\" method=\"post\">\n"
            . "<input type=\"hidden\" name=\"step\" value=\"4\">\n<input type=\"hidden\" name=\"world\" value=\"$world\">\n";
    } else if ($track_player) {
        $player_lot = $s_post['lot'];
        $menu .= "<form action=\"$UMC_DOMAIN/server-access/buildingrights/\" method=\"post\">\n"
            . "<input type=\"hidden\" name=\"step\" value=\"7\">\n<input type=\"hidden\" name=\"world\" value=\"$world\"><input type=\"hidden\" name=\"lot\" value=\"$player_lot\">\n";
    } else if ($find_lot) {
            $menu .= "<form action=\"$UMC_DOMAIN/server-access/buildingrights/\" method=\"post\">\n"
            . "<input type=\"hidden\" name=\"step\" value=\"9\">\n<input type=\"hidden\" name=\"world\" value=\"$world\"><input type=\"hidden\" name=\"lot\" value=\"$player_lot\">\n";
    }

    $menu .= "<div id=\"menu_2d_map\">\n";

    if (!$settler_test && !$track_player && !$find_lot) {
        // create the top menu
        $menu .= umc_map_menu($worlds, $world, $freeonly);
    } else if ($settler_test){
        $menu .= "Pick a lot that looks nice to you. Closer to spawn is more convenient. Then click here: "
            . "<input id=\"settler_test_next\" type=\"submit\" name=\"Next\" value=\"Next\">\n";
    } else if ($find_lot) {
        $menu .= "Follow the red line from your icon to your lot $player_lot and then press "
            . "<input type=\"submit\" name=\"Next\" value=\"Next\">\n";
    } else {
        $menu .= "Find your user head on the map and click on the button next to it!";
    }
    $menu .= "</div>\n";

    $new_choices = array();
    if ($world == 'empire_new') {
        $rights = umc_region_data('empire');
        // this is for the empire_new move only:
        // var_dump($new_choices);
    } else  {
        $rights = umc_region_data($world);
    }
    $timer['after_region_data'] = XMPP_ERROR_ptime();

    $map = $UMC_SETTING['world_img_dim'][$world];

    $image = "$UMC_PATH_MC/server/maps/" . $world . ".jpg";
    $size = array(0,0);
    if (file_exists($image)) {
        $size = getimagesize($image);
    }
    $map_width = $size[0];
    $map_height = $size[1];

    if ($lag) {
        $heatworld = $world;
        $world .= '_heatmap';
    }
    $html = '';
    if ($find_lot) {
        $html = "<canvas id=\"lot_pointer\" style=\"position: absolute; top: 30px; left: 10px; z-index: 99;\" width=\"$map_width\" height=\"$map_height\"></canvas>\n";
    }

    $html .= '<div id="outer_box">' . "\n"
        . '    <img src="/map/'. $world . '.jpg" id="image_box" alt="map">' . "\n";
    if ($track_player) {
        $html .= umc_read_markers_file('identify_user', $world, $player);
    } else if ($find_lot) {
        $html .= umc_read_markers_file('track_user', $world, $player);
    } else if ($lag) {
        $html .= umc_read_markers_file('html', $heatworld);
    } else {
        $html .= umc_read_markers_file('html', $world);
    }

    //$repl_arr = array(',','-');
    $kingdom = '';
    if ($world == 'kingdom' || $world == 'draftlands') {
        $kingdom = 'center';
    }

    if (isset($UMC_SETTING['world_data'][$world]['spawn'])) {
        $spawn_lot = strtoupper($UMC_SETTING['world_data'][$world]['spawn']);
    } else {
        $spawn_lot = '';
    }
    // $old_users =
    $timer['before_owner_age'] = XMPP_ERROR_ptime();

    $all_lastlogins = umc_users_active_lastlogin_and_level();
    // what date was 1 month ago?
    $now_datetime = umc_datetime();
    $now_datetime->modify('-1 month');
    $one_months_ago = $now_datetime->format('Y-m-d H:i:s');
    $now_datetime->modify('-1 month');
    // what date was 2 months ago?
    $two_months_ago = $now_datetime->format('Y-m-d H:i:s');

    $timer['after_owner_age'] = XMPP_ERROR_ptime();
    $banned_users = umc_banned_users();
    $timer['after_banned_users'] = XMPP_ERROR_ptime();
    $css_lot_sizes = array();
    //$user_str = "<div class=\"user\">";
    //$css_lot_types = array();
    // var_dump($old_users);
    foreach ($rights as $lot => $opt) {
        $class = '';
        if (substr($lot,0,2) == '__') {
            continue;
        }
        // we need to switch for the proper coordinates
        // we need the 1 coordinate to be the top left one

        // Lot A1
        // min: {z: 1152.0, y: 0.0, x: -1280.0}
        // max: {z: 1279.0, y: 128.0, x: -1153.0}

        // take the larger X (west)
        $min = $opt['min'];
        $max = $opt['max'];
        if ($min['z'] < $max['z']) {
            $z1 = $min['z'];
            $z2 = $max['z'];
        } else {
            $z1 = $max['z'];
            $z2 = $min['z'];
        }
        // get the smaller z (north)
        if ($min['x'] < $max['x']) {
            $x1 = $min['x'];
            $x2 = $max['x'];
        } else {
            $x1 = $max['x'];
            $x2 = $min['x'];
        }
        $coord_1 = $x1;
        $coord_2 = $z1;

        $chunk = "Region ". floor($x1 / 512) . "/" . floor($z1 / 512) . ", Chunks ". floor($x1 / 16) . "/" . floor($z1 / 16);

        $x1 = conv_x($x1, $map);
        $x2 = conv_x($x2, $map);
        $z1 = conv_z($z1, $map);
        $z2 = conv_z($z2, $map);

        if ($find_lot && $player_lot == $lot) {
            $lot_x = $x1 + 60;
            $lot_z = $z1 + 60;
        }

        $lowercase_lot = $lot;
        $lot = strtoupper($lot);
        $lot_str = $lot;
        $width = $x2 - $x1 - 3;
        $height = $z2 - $z1 - 3;
        //$css .='#'. $lot . ' {width:'. $width. 'px; height:'. $height . 'px; top:' . $z1 . 'px; left:'. $x1 . 'px;}'. "\n";
        $css_lot_location =' style="top:' . $z1 . 'px; left:'. $x1 . 'px;"';
        $css_lot_sizes["size{$width}_{$height}"] = '{width:'. $width. 'px; height:'. $height . 'px;}';
        $size_class = " size{$width}_{$height}";

        $user_string = '';
        $owner_string = '';
        // $box_color = '';
        $coord_str = "            <span class=\"coords\">$coord_1/$coord_2, $chunk</span>\n";
            //. '<span class="coords bottomleft">'.$x1.'/'.$z2.'</span>'
            //. '<span class="coords topright">'.$x2.'/'.$z1.'</span>'
            //. '<span class="coords bottomright">'.$x2.'/'.$z2.'</span>';
        if ($opt['owners']) {
            $owner_uuid = key($opt['owners']);
            $owner_username = $opt['owners'][$owner_uuid];

            // donation level
            $donation_level = false;
            if (isset($donators[$owner_uuid])) {
                $donation_level = $donators[$owner_uuid];
            }

            // find out who can keep their lot longer than 1 months
            $retain_lot = false;

            // kick out banned users
            if (isset($owner_uuid, $banned_users)) {
                $class = ' redout';
                $lastlogin_str = "Banned!";
            }

            $owner_lastlogin = $all_lastlogins[$owner_uuid]['lastlogin'];
            if (!isset($all_lastlogins[$owner_uuid]['userlevel'])) {
                XMPP_ERROR_trigger("$owner_username has no userlevel for the map!");
            }
            $lastlogin_str = $owner_lastlogin;
            $ownergroup = $all_lastlogins[$owner_uuid]['userlevel'];
            // who should be able to be away for 2 months?
            if (in_array($ownergroup, $longterm)) {
                $retain_lot = true;
            }
            // if we show only free lots, use different class
            $border = '';
            if (!$freeonly) {
                $border = 'border';
                // $box_color = ' background: rgba(0, 255, 255, 0.2);';
            }

            if ($retain_lot && ($owner_lastlogin < $two_months_ago) && $donation_level < 2) { // too late
                $class .= ' red' . $border;
            } else if ($retain_lot && ($owner_lastlogin < $one_months_ago) && $donation_level < 2) { // still yellow
                $class .= ' yellow' . $border;
            } else if (!$retain_lot && ($owner_lastlogin < $two_months_ago) && ($world == 'aether') && $donation_level < 1) {
                $class .= ' red' . $border;
            } else if (!$retain_lot && ($owner_lastlogin < $one_months_ago) && $donation_level < 1) {
                $class .= ' red'  . $border;
            } else {
                if (isset($new_choices[$lowercase_lot]) && !in_array($new_choices[$lowercase_lot]['choice'], array('keep', 'reset'))) {
                    $class .= ' whiteborder';
                } else {
                    $class .= ' black' . $border;
                }
            }
            if (substr($lot, 0, 3) === 'CON') {
                $owner_string .= "$owner_username";
                $lot_str = substr($lot_str, 4);
                $class .= " small";
            } else {
                $owner_string .= "$owner_username ($lastlogin_str)";
                $lot_str = $lot;
            }
        } else {
            $class .= ' whiteborder';
        }
        if ($opt['members']) {
            $members = array_unique($opt['members']);
            foreach ($members as $user) {
                $user_string .= "$user ";
            }
        }
//        if ($opt['members']) {
//            $members = array_unique($opt['members']);
//            foreach ($members as $user) {
//                $user_string .= "$user ";
//            }
//        }

        if ($opt['owners']) {
            $owner_string = "            <div class=\"Owner\">$owner_string</div>\n";
            if ($opt['members']) {
                $user_string = "            <span class=\"user\">$user_string</span>\n";
            }
        }
        $onclick = '';
        if ($settler_test && !$opt['owners']) {
            $onclick = " onclick=\"select_lot('radio_$lot', '$lot')\"";
        }

        $html .= "    <div id=\"$lot\" class=\"outerframe$class$size_class\"$css_lot_location$onclick>\n";

        if ($settler_test && !$opt['owners']) {
            $html .= "        <input id=\"radio_$lot\" class=\"settler_test\" type=\"radio\" name=\"lot\" value=\"$lot\"><label for=\"radio_$lot\">$lot_str</label>\n";
        } else {
            $html .= "        <div class=\"innertext $kingdom\">$lot_str<br>\n$owner_string$user_string$coord_str        </div>\n";
        }

        $html .= "    </div>\n";
    }
    $timer['after_regions_display'] = XMPP_ERROR_ptime();
    if ($settler_test || $track_player || $find_lot) {
        $html .= '</form>';
    }

    foreach ($css_lot_sizes as $class => $css_string) {
        $css .= ".$class $css_string\n";
    }
    if ($lag) {
        $world = $heatworld;
    }

    $css .= "</style>\n";

    $header = '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
    <head>
        <title>Uncovery Minecraft 2D Map: '. $world . '</title>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <script type="text/javascript" src="/admin/js/jquery-1.11.1.min.js"></script>
        <script type="text/javascript" src="/admin/js/jquery-ui.min.js"></script>
        <script type="text/javascript">
            function find_spawn() {
                window.scrollTo(($(document).width()-$(window).width())/2 + 60,($(document).height()-$(window).height())/2 - 60);
                doBlink("'. $spawn_lot . '");
            }
            function doBlink(element_id) {
                toggleBlink(element_id);
                setTimeout(function() {toggleBlink(element_id);}, 2000);
            }
            function toggleBlink(element_id) {
                $("#" + element_id).toggleClass("blink_me");
            }
            function toggleLotDisplay() {
                $(".blackborder").toggleClass("black");
                $(".redborder").toggleClass("red");
                $(".yellowborder").toggleClass("yellow");
            }
            function find_user(left, top, element_id) {
                window.scrollTo(left - ($(window).width() / 2), top - ($(window).height() / 2))
                $("#" + element_id).effect("shake");
            }
            function select_lot(lot_radio, lot_name) {
                $("#" + lot_radio).prop("checked", true);
                $("#settler_test_next").prop("value", "Chose Lot " + lot_name);
            }
            var markers_url = "' . $UMC_DOMAIN. '/admin/index.php?function=display_markers&world=' . $world . $track_player_icon . '";
            var markers_menu_url = "' . $UMC_DOMAIN. '/admin/index.php?function=display_markers&format=scrollto&world=' . $world . '";
            function update_positions() {
                $.ajax({
                    url: markers_menu_url,
                    success: function(data) {
                        $("#scroll_to_icons").remove();
                        $(data).insertAfter( "#link_3d_maps" );
                        // $("#link_3d_maps").html($("#link_3d_maps").html() + data);
                    }
                });
                $.ajax({
                    url: markers_url,
                    success: function(data) {
                        $("#marker_list").remove();
                        $("#outer_box").html(data + $("#outer_box").html());
                        setTimeout("update_positions()", 4000);'. "\n";
if ($find_lot) {
    $header .= '                draw_line();
                    }
                });

            }
            function draw_line() {
                var player_left = parseInt($("#'.$player.'_marker").css("left"), 10) + 10;
                var player_top = parseInt($("#'.$player.'_marker").css("top"), 10) + 10;
                var c = document.getElementById("lot_pointer");
                var ctx = c.getContext("2d");
                ctx.setTransform(1, 0, 0, 1, 0, 0);
                ctx.beginPath();
                ctx.clearRect(0, 0, '.$map_width.', '.$map_height.');
                ctx.strokeStyle="red";
                ctx.moveTo(player_left, player_top);
                ctx.lineTo('. $lot_x . ','. $lot_z . ');
                ctx.stroke();
            }
';
} else {
    $header .= '                        }
                    });
                }
';
}

    if (!$settler_test) {
        $header .= '$(document).ready(function() {update_positions();});';
    }
    $header .= "\n</script>\n";

    $out =  $header . $css . "</head>\n<body>\n" .  $menu . $html . "</div>\n</body>\n</html>\n";
    echo $out;
    $timer['final'] = XMPP_ERROR_ptime();
    // var_dump($timer);
}

// this is called with URL
// http://uncovery.me/admin/index.php?function=display_markers&world=world
// or
// http://uncovery.me/admin/index.php?function=display_markers&world=empire&track_player=uncovery
//
// and returns ONLY the userpositions HTML/CSS with images for the selected map
// used for dynamic map location updates
function umc_display_markers() {
    global $UMC_ENV;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $UMC_ENV = 'markers';

    if (isset($_GET['world'])) {
        $world = $_GET['world'];
    } else {
        $world = 'city';
    }
    if (isset($_GET['track_user'])) {
        $user = $_GET['track_user'];
        return umc_read_markers_file('track_user', $world, $user);
    } else if (isset($_GET['identify_user'])) {
        $user = $_GET['identify_user'];
        return umc_read_markers_file('identify_user', $world, $user);
    } else if (isset($_GET['format'])) {
        $format = $_GET['format'];
    } else {
        $format = 'html';
    }

    $out = umc_read_markers_file($format, $world);
    return $out;
}

function umc_read_data_files($world = 'city', $map = '') {
    global $UMC_PATH_MC;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $path = "$UMC_PATH_MC/server/bukkit/$world/region";
    $html = '';
    $css = '';
    if ($handle = opendir($path)) {
        while (false !== ($file = readdir($handle))) {
            if (substr($file,-4) == '.mcr') {
                $data = explode(".", $file);
                $signa = $signb = '';
                $d2 = $data[1];
                $d1 = $data[2];

                if ($d1 <0) {
                    $signa = 'x';
                }
                if ($d2 <0) {
                    $signb = 'x';
                }
                $box = "file_" . $signa . abs($d1) . "_" . $signb . abs($d2);
                // r.-11.1.mcr
                $x1 = ($d1 * 512) + 512;
                $z1 = ($d2 * 512);
                $shiftx1 = conv_x($x1, $map);
                $shiftz1 = conv_z($z1, $map);
                $css .= '#'. $box . ' {width:512px; height:512px; top:' . $shiftz1 . 'px; left:'. $shiftx1 . 'px;}'. "\n";
                $html .= '    <div id="'. $box. '" class="outerframe" style="border:1px solid blue;">' . "\n"
                    . '       <div class="innertext_files">File: ' . "$file / Coords: $x1/$z1 </div>\n"
                    . "    </div>\n";
            }
        }
    }
    return array('html'=>$html, 'css'=>$css);
}


function umc_read_markers_file($format = 'html', $world = 'empire', $user = false) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    /*   [0]=>
      object(stdClass)#1 (5) {
        ["timestamp"]=> string(19) "2011-01-31 14:14:47"
        ["z"]=> float(-319.54668819556)
        ["msg"]=> string(8) "Thegiant"
        ["y"]=> float(50)
        ["x"]=> float(448.69999998808)
    */
    global $UMC_SETTING, $UMC_PATH_MC;
    $file = "$UMC_PATH_MC/server/bin/data/markers.json"; // $UMC_SETTING['markers_file'];
    $text = file_get_contents($file);
    if (!$file ) {
        XMPP_ERROR_trigger("Could not read markers file (umc_read_markers_file)");
    }
    $m = json_decode($text);
    // no users
    if (!$m) {
        return '';
    }
    $out_arr = array();
    $map = $UMC_SETTING['world_img_dim'][$world];

    if (count($m) == 0) {
        return '';
    }
    if ($format == 'scrollto') {
        $out = "<div id=\"scroll_to_icons\">\n";
    } else {
        $out = "<div id=\"marker_list\">\n";
    }

    // check if we find the single user from the marker
    $foundplayer = false;
    // var_dump($format);
    foreach ($m as $marker) {
        $z = $marker->z;
        $x = $marker->x;
        $x_text = round($x);
        $z_text = round($z);
        $top = conv_z($marker->z, $map);// + $map['img_top_offset'];
        $left = conv_x($marker->x, $map);// + $map['img_left_offset'];
        $username = strtolower($marker->msg);
        $playerworld = $marker->world;
        if ($username == 'uncovery') {
            //continue;
        }
        $icon_url = umc_user_get_icon_url($username);
        if ($format == 'identify_user' && $username == $user) {
            $out .= '   <span class="marker" style="z-index:100; top:'.$top.'px; left:'.$left
                ."px;\"><img src=\"$icon_url\" title=\"$username\" alt=\"$username\"> <input type=\"submit\" name=\"track_player\" value=\"That's me!\"></span>\n"
                . "\n";
            $foundplayer = true;
        } else if ($format == 'track_user' && $username == $user) {
            $out .= '   <span class="marker"  id="'.$username.'_marker" style="z-index:100; top:'.$top.'px; left:'.$left
                . "px;\"><img src=\"$icon_url\" title=\"$username\" alt=\"$username\"></span>\n"
                . "\n";
            $foundplayer = true;
        } else if ($format == 'scrollto' && ($marker->world == $world)) {
            $out .= "<img src=\"$icon_url\" title=\"$username\" alt=\"$username\" onclick=\"find_user($left, $top, '{$username}_marker')\">\n";
        } else if ($format == 'html' && ($marker->world == $world)) {
            if ($world == 'hunger'){
                $out .= '<div class="marker" style="position:relative">'
                //. "   <img class=\"marker\" src=\"$icon_url\" title=\"$username\" alt=\"$username\">'
                . $username
                . '</div>'
                . "\n";
            } else {
                $out .= '   <img id="'.$username.'_marker" class="marker" style="z-index:100; top:'.$top.'px; left:'.$left
                    . "px;\" src=\"$icon_url\" title=\"$username (x:$x_text z:$z_text)\" alt=\"$username (x:$x_text z:$z_text)\">\n";
            }
        } else if ($format == 'json') {
            $arr[] = array('name' => $username, 'url' => $icon_url, 'top' => $top, 'left' =>$left);
        } else if ($format == 'all_users') {
            // list all users for website status
            $out .= $username . " ";
        } else if ($format == 'array') {
            $out_arr[$username] = array('name' => $username, 'url' => $icon_url, 'top' => $top, 'left' =>$left, 'world'=> $playerworld, 'x' => $x_text, 'z' => $z_text);
        }
    }

    if (($format == 'identify_user' || $format == 'track_user') && $foundplayer == false) {
        // umc_error_notify("Could not find single player $user in Json Data: \n" . var_export($m, true));
    }
    if ($format == 'json') {
        $out = json_encode($arr);
    } else if ($format == 'array') {
        return $out_arr;
    }
    if ($format == 'scrollto') {
        $out .= "</div>\n";
    } else {
        if ($format == 'html') {
            // no idea why this was here. it would duplicate the output.
            // $out .= umc_read_markers_file('scrollto', $world);
        }
        $out .= "</div>\n";
    }

    return $out;
}

// creates a list of cuboids with coordinates and displays it on the browser
function umc_create_cuboids() {
    //echo "Starting Map generation<br>";
    // are we enlarging an existing map?
    $enlarge = true;
    $maxval = 2176 + (4 * 272); // define future map size here
    $enlarge_by = 4;
    $blocksize = 272;
    $worldname = "draftlands";
    $lot_prefix = 'draft';
    $version = '0'; // lot version

    // editing stop
    $divider = ($maxval / $blocksize) * 2;
    $startchar = 64;
    $startcol = 1;
    $new_regions = array();
    $old_regions = array();

    $world_id = umc_get_worldguard_id('world', strtolower($worldname));

    if ($enlarge) {
        // get existing lots, users, flags
        $region_sql = "SELECT * FROM minecraft_worldguard.region "
                . "LEFT JOIN minecraft_worldguard.region_cuboid ON id=region_id "
                . "WHERE id LIKE '$lot_prefix%';";
        $region_rst = mysql_query($region_sql);
        while ($region_row = mysql_fetch_array($region_rst, MYSQL_ASSOC)) {
            $lot = $region_row['id'];
            $old_regions[$lot]['coords'] = array(
                'min_x' => $region_row['min_x'],
                'min_y' => $region_row['min_y'],
                'min_z' => $region_row['min_z'],
                'max_x' => $region_row['max_x'],
                'max_y' => $region_row['max_y'],
                'max_z' => $region_row['max_z']
            );
            $old_regions[$lot]['users'] = array();
            $user_sql = "SELECT * FROM minecraft_worldguard.region_players WHERE region_id LIKE '$lot';";
            $user_rst = mysql_query($user_sql);
            while ($user_row = mysql_fetch_array($user_rst, MYSQL_ASSOC)) {
                $user_id = $user_row['user_id'];
                $old_regions[$lot]['users'][$user_id] = $user_row['owner'];
            }
            $old_regions[$lot]['flags'] = array();
            $flags_sql = "SELECT * FROM minecraft_worldguard.region_flag WHERE region_id LIKE '$lot';";
            $flags_rst = mysql_query($flags_sql);
            if (mysql_num_rows($flags_rst) > 0){
                while ($flags_row = mysql_fetch_array($flags_rst, MYSQL_ASSOC)) {
                    $flag = $flags_row['flag'];
                    $old_regions[$lot]['flags'][$flag] = $flags_row['value'];
                }
            }
        }
    }

    // start at 0 - 0 on the top left corner
    for ($z=1; $z<=$divider; $z++) { // go from left to right, 30 fields
        if ($z >= 27) {
            $char = 'A'. chr($startchar + ($z - 26));
        } else {
            $char = chr($startchar + $z);
        }
        //echo "iteration z = $z<br>";
        for ($x=0; $x<$divider; $x++) { // go from up to down
            $left_b = $maxval - ($z * $blocksize);
            $up_b = ((-1)* $maxval) + ($x * $blocksize);
            $num = $startcol + $x;
            $up_b2 = $up_b + $blocksize - 1;
            $left_b2 = $left_b + $blocksize - 1;

            $new_lot = strtolower($lot_prefix . '_' .$char.$num);
            $flags = array();
            $users = array();
            $flags_a = array();
            $flags_b = array();
            $flags_c = array();
            $users_a = array();
            $users_b = array();
            $users_c = array();
            $reset = "'reset'";
            if ($enlarge) {
                // get old lot name
                if ($z >= 27) {
                    $old_char = 'A'. chr($startchar + ($z - 26 - $enlarge_by));
                } else {
                    $old_char = chr($startchar + $z - $enlarge_by);
                }
                $old_num = $num - $enlarge_by; // numbers start at 0
                $old_lot = strtolower($lot_prefix . '_' . $old_char . $old_num);
                if (isset($old_regions[$old_lot])) {
                    $flags = $old_regions[$old_lot]['flags'];
                    $users = $old_regions[$old_lot]['users'];
                    $reset = 'NULL';
                    if ($worldname == 'kingdom' || $worldname == 'draftlands') {
                        $flags_a = $old_regions[$old_lot . "_a"]['flags'];
                        $flags_b = $old_regions[$old_lot . "_b"]['flags'];
                        $flags_c = $old_regions[$old_lot . "_c"]['flags'];
                        $users_a = $old_regions[$old_lot . "_a"]['users'];
                        $users_b = $old_regions[$old_lot . "_b"]['users'];
                        $users_c = $old_regions[$old_lot . "_c"]['users'];
                    }
                }
            }
            if ($worldname !== 'kingdom' && $worldname !== 'draftlands') {
                $new_regions[$new_lot] = array(
                    'coords' => array('min_x'=>$up_b, 'min_y'=>0, 'min_z'=>$left_b, 'max_x'=>$up_b2, 'max_y'=>256, 'max_z'=>$left_b2),
                    'flags' => $flags,
                    'users' => $users,
                    'reset' => $reset,
                );
            } else {
                $new_regions[$new_lot] = array(
                    'coords' => array('min_x'=>$up_b, 'min_y'=>0, 'min_z'=>$left_b, 'max_x'=>$up_b2 - 16, 'max_y'=>256, 'max_z'=>$left_b2 - 16),
                    'flags' => $flags,
                    'users' => $users,
                    'reset' => $reset,
                );
                $new_regions[$new_lot. "_a"] = array(
                    'coords' => array('min_x'=>$up_b2 - 15, 'min_y'=>0, 'min_z'=>$left_b, 'max_x'=>$up_b2, 'max_y'=>256, 'max_z'=>$left_b2 - 16),
                    'flags' => $flags_a,
                    'users' => $users_a,
                    'reset' => $reset,
                );
                $new_regions[$new_lot. "_b"] = array(
                    'coords' => array('min_x'=>$up_b2 - 15, 'min_y'=>0, 'min_z'=>$left_b2 - 15, 'max_x'=>$up_b2, 'max_y'=>256, 'max_z'=>$left_b2),
                    'flags' => $flags_b,
                    'users' => $users_b,
                    'reset' => $reset,
                );
                $new_regions[$new_lot. "_c"] = array(
                    'coords' => array('min_x'=>$up_b, 'min_y'=>0, 'min_z'=>$left_b2 - 15, 'max_x'=>$up_b2 - 16, 'max_y'=>256, 'max_z'=>$left_b2),
                    'flags' => $flags_c,
                    'users' => $users_c,
                    'reset' => $reset,
                );
            }
        }
    }

    $region_sql = 'INSERT INTO minecraft_worldguard.`region` (`id`, `world_id`, `type`, `priority`, `parent`) VALUES ';
    $cuboid_sql = 'INSERT INTO minecraft_worldguard.`region_cuboid` (`region_id`, `world_id`, `min_x`, `min_y`, `min_z`, `max_x`, `max_y`, `max_z`) VALUES ';
    $player_sql = 'INSERT INTO minecraft_worldguard.`region_players` (`region_id`, `world_id`, `user_id`, `owner`) VALUES ';
    $flags_sql = 'INSERT INTO minecraft_worldguard.`region_flag` (`world_id`, `region_id`, `flag`, `value`) VALUES ';
    $version_sql = 'INSERT INTO minecraft_srvr.`lot_version`(`lot`, `version`, `choice`, `timestamp`, `mint_version`) VALUES ';
    foreach ($new_regions as $lot => $data) {
        $region_sql .= "\n('$lot',$world_id,'cuboid',0,NULL), ";
        $coords = $data['coords'];
        $cuboid_sql .= "\n('$lot',$world_id,{$coords['min_x']},{$coords['min_y']},{$coords['min_z']},{$coords['max_x']},{$coords['max_y']},{$coords['max_z']}), ";
        $version_sql .= "\n('$lot','$version',{$data['reset']},NOW(),'$version'), ";
        foreach ($data['users'] as $user_id => $owner) {
            $player_sql .= "\n('$lot',$world_id,$user_id,$owner), ";
        }
        foreach ($data['flags'] as $flag => $value) {
            $flags_sql .= "\n($world_id,'$lot','$flag','$value'), ";
        }
    }

    echo "DELETE FROM  minecraft_worldguard.region WHERE id LIKE '{$lot_prefix}_%';\n";
    echo "DELETE FROM  minecraft_srvr.lot_version WHERE lot LIKE '{$lot_prefix}_%';\n";
    echo rtrim($region_sql, ", "). ";\n";
    echo rtrim($cuboid_sql, ", "). ";\n";
    echo rtrim($player_sql, ", "). ";\n";
    echo rtrim($flags_sql, ", "). ";\n";
    echo rtrim($version_sql, ", "). ";\n";
}

?>