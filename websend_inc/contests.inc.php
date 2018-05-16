<?php

global $UMC_SETTING, $WS_INIT;

$WS_INIT['contest'] = array(
    'disabled' => true,
    'events' => array(
        'PlayerJoinEvent' => 'umc_contest_login_list',
    ),
    'default' => array(
        'help' => array(
            'title' => 'Building Contests',
            'short' => 'Allows you to reserve a location for a contest.',
            'long' => 'You need to reserve a spot to participate in a contest. To do so, list the available contests, join one and have a lot assigned to build.;'
                . 'You can then warp to it and start building.;',
            ),
    ),
    'list' => array(
        'help' => array(
            'short' => 'List all current contests and their ID;',
            'args' => '',
            'long' => 'All contests that are available will be visible and you can then join one.;',
        ),
        'function' => 'umc_contests_list',
    ),
    'info' => array(
        'help' => array(
            'short' => 'See more information on one contest;',
            'args' => '<ID>',
            'long' => 'You will see the description, deadline and dimentsions of the contest. Get the ID from {green}/contest list;',
        ),
        'function' => 'umc_contests_info',
    ),
    'join' => array(
        'help' => array(
            'short' => 'Join a contest;',
            'args' => '<ID>',
            'long' => 'This will assign a lot for you where you can build your contest entry.;',
        ),
        'function' => 'umc_contests_join',
    ),
    'warp' => array(
        'help' => array(
            'short' => 'Teleports you to a contest entry;',
            'args' => '<contest id> <entry no.>',
            'long' => 'Use the contest ID from /contest list and contest entry from /contest info. ',
        ),
        'function' => 'umc_contests_warp',
    ),
    'abandon' => array(
        'help' => array(
            'short' => 'Abandons one of your contest entries;',
            'args' => '<contest id> <entry no.>',
            'long' => 'Use the contest ID from /contest list and contest entry from /contest info. '
                . 'Your contest entry will not be counted for for rating. You cannot undo this! You cannot get another lot!',
        ),
        'function' => 'umc_contests_abandon',
    ),
    'refund' => array(
        'help' => array(
            'title' => 'Refund Items',  // give it a friendly title
            'short' => 'Refunding items from closed contests',  // a short description
            'long' => "Only Owners can execute these.", // a long add-on to the short  description
            ),
        'security' => array(
            'level' => 'Owner',
         ),
        'function' => 'umc_contests_refund_all',
    ),
);

function umc_wsplg_contest() {
    global $UMC_USER, $WS_INIT;
    $player = $UMC_USER['username'];
    $args = $UMC_USER['args'];

    $level = umc_get_userlevel($player);
    if ($level == 'Guest') {
        umc_error("You need to be Settler to use that command.;");
        die;
    }

    // we need to determine in the plugin if another command is actually needed.
    // here we determine that the command is needed and display help if it is not given.
    if (!isset($args[1])) {
        umc_show_help($args);
    }
    $function = $WS_INIT[$args['0']][$args['1']]['function'];
    if (function_exists($function)) {
        $function();
    } else if (($args[1] == 'delete') && ($level == 'Owner')){
        umc_contests_delete();
    } else if (($args[1] == 'inventory') && ($level == 'Owner')){
        umc_contests_make_inventory();
    } else if (($args[1] == 'refund') && ($level == 'Owner')){
        umc_contests_refund_all();
    } else if (($args[1] == 'close') && ($level == 'Owner')){
        umc_contests_close();
    } else {
        umc_show_help($args);
    }
}

function umc_contest_login_list() {
    $sql = "SELECT title, status, id, deadline from minecraft_srvr.contest_contests WHERE status NOT LIKE 'closed' ORDER by status ASC;";
    $rst = mysql_query($sql);
    if (mysql_num_rows($rst) > 0) {
        umc_pretty_bar("blue", "-", " Current Contests: ", 50, true);
        umc_echo("{grey}ID   Status  Title (deadline)", true);
        while ($row = mysql_fetch_array($rst)) {
            $title = $row['title'];
            $id= $row['id'];
            $status = $row['status'];
            $deadline = '';
            if ($status == 'active') {
                $deadline = " ({$row['deadline']})";
            }
            umc_echo("{gold}$id   {green}$status   {red}$title$deadline", true);
        }
        umc_echo("Enter active contests with {green}/contest{white}! Vote on the website!", true);
        umc_footer(true);
    }
}

function umc_contests_list() {
    $sql = "SELECT id, title, type, status FROM minecraft_srvr.contest_contests WHERE status = 'active' OR status = 'voting'";
    $rst = mysql_query($sql);

    umc_pretty_bar("darkblue", "-", "{darkcyan} All open or voting contests ");
    while ($row = mysql_fetch_array($rst, MYSQL_ASSOC)) {
        $pre_title = 'Survival: ';
        $status = $row['status'];
        if ($row['type'] == 'creative') {
            $pre_title = 'Creative: ';
        }
        umc_echo('ID ' . $row['id'] . ": $pre_title" . $row['title'] . " ($status)");
    }
    umc_footer();
}

function umc_contests_info() {
    global $UMC_USER;
    $args = $UMC_USER['args'];
    $id = $args[2];
    if (!is_numeric($id)) {
        umc_show_help($args);
        return;
    }
    $sql = "SELECT * FROM minecraft_srvr.contest_contests WHERE id = $id;";
    $rst = mysql_query($sql);

    $row = mysql_fetch_array($rst, MYSQL_ASSOC);
    $pre_title = 'Survival: ';
    if ($row['type'] == 'creative') {
       $pre_title = 'Creative: ';
    }
    umc_pretty_bar("darkblue", "-", "{darkcyan} $pre_title" . $row['title']. " ");
    umc_echo('{gold}Description: {white}' . $row['description']);
    umc_echo('{gold}ID: {white}' . $row['id'] . ' {gold}Deadline: {white}' . $row['deadline']
        . " {gold}Width: {white}{$row['x']} {gold}Depth: {white}{$row['z']} {gold}Height: {white}{$row['y']};");

    // find out if the user can have additional contest entries in this contest
    $sql = "SELECT * FROM minecraft_worldguard.world LEFT JOIN region ON world.id=region.world_id
        LEFT JOIN minecraft_worldguard.region_cuboid ON region.id=region_cuboid.region_id
        LEFT JOIN minecraft_worldguard.region_players ON region_cuboid.region_id=region_players.region_id
        LEFT JOIN minecraft_worldguard.user ON region_players.user_id=user.id
        LEFT JOIN minecraft_srvr.UUID ON user.uuid=UUID.username
        WHERE region.id LIKE 'con_". $id ."%' AND Owner=1 AND user.name <> '_abandoned_'
        ORDER BY max_z, max_x";

    $rst = mysql_query($sql);
    $count = mysql_num_rows($rst);

    umc_pretty_bar("darkblue", "-", "{darkcyan} $count Entries: ");
    $abandon_id = umc_get_worldguard_id('user', '_abandoned_');
    while ($row = mysql_fetch_array($rst, MYSQL_ASSOC)) {
        $abandon_sql = "SELECT * FROM minecraft_worldguard.region_players WHERE region_id = '{$row['region_id']}' AND Owner=1 AND user_id=$abandon_id;";
        $abandon_rst = mysql_query($abandon_sql);
        if (mysql_num_rows($abandon_rst) == 0) {
            umc_echo("{gold}Lot: {white}" . $row['region_id']. " {gold}User: {white}" . $row['username']);
        } else {
            umc_echo("{gold}Lot: {white}" . $row['region_id']. " {gold}User: {white}" . $row['username']. " (ABANDONED)");
        }
    }
    umc_footer();
}

function umc_contests_abandon(){
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $args = $UMC_USER['args'];

    $id = $args[2];
    if (!isset($args[2])){
        umc_show_help($args);
        return;
    } else if (!is_numeric($id)) {
        umc_error("You have to enter a numeric contest ID. See /contest list");
    }
    $num = $args[3];
    if (!isset($args[3])){
        umc_show_help($args);
        return;
    } else if (!is_numeric($num)) {
        umc_error("You have to enter a numeric contest entry. See /contest info $id");
    }

    $lot = "con_" . $id . "_". $num;


    //check if the user abandoned already
    $abandon_id = umc_get_worldguard_id('user', '_abandoned_');
    $abandon_sql = "SELECT * FROM minecraft_worldguard.region_players WHERE region_id = '$lot' AND Owner=1 AND user_id=$abandon_id;";
    $abandon_rst = mysql_query($abandon_sql);
    if  (mysql_num_rows($abandon_rst) > 0) {
        umc_error("You abandoned the entry $lot already!");
    }

    // make sure the user actually owns this enrty
    $user_id = umc_get_worldguard_id('user', strtolower($player));
    // find out if the user can have additional contest entries in this contest
    $sql = "SELECT * FROM minecraft_worldguard.world LEFT JOIN region ON world.id=region.world_id
        LEFT JOIN minecraft_worldguard.region_cuboid ON region.id=region_cuboid.region_id
        LEFT JOIN minecraft_worldguard.region_players ON region_cuboid.region_id=region_players.region_id
        LEFT JOIN minecraft_worldguard.user ON region_players.user_id=user.id
        WHERE region.id LIKE '$lot' AND Owner=1 AND user.id=$user_id";
    $rst = mysql_query($sql);
    if (mysql_num_rows($rst) != 1) {
        umc_error("You do not own the lot $lot in world $world!");
    } else {
        $row = mysql_fetch_array($rst, MYSQL_ASSOC);
    }

    $world_id = $row['world_id'];
    $world = $row['world.name'];

    $ins_user_sql = "INSERT INTO minecraft_worldguard.region_players (region_id, world_id, user_id, Owner)
        VALUES ('$lot', $world_id, $abandon_id, 1);";
    $inc_user_rst = mysql_query($ins_user_sql);
    umc_ws_cmd("region load -w $world", 'asConsole');
    umc_echo("You have succcessfully abandoned the lot $lot!");
}

function umc_contests_close() {
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $args = $UMC_USER['args'];
    if ($player != 'uncovery' && $player != 'console') {
        umc_error("Nice try, $player. Think I am stupid? Want to get banned?");
    }
    $id = $args[2];
    if (!isset($args[2])){
        umc_show_help($args);
        return;
    } else if (!is_numeric($id)) {
        umc_error("You have to enter a numeric contest ID ($id). See /contest list");
    }

    $sql = "SELECT * FROM minecraft_worldguard.region WHERE id LIKE 'con_$id%';";
    $rst = mysql_query($sql);
    $i = 0;
    while ($region = mysql_fetch_array($rst, MYSQL_ASSOC)) {
        $lot = $region['id'];
        $world_id = $region['world_id'];
        $flagname = 'build';
        $flag = 'deny';
        $ins_sql = "INSERT INTO minecraft_worldguard.region_flag (region_id, world_id, flag, value) VALUES
            ('$lot', $world_id, '$flagname', '$flag');";
        umc_echo($ins_sql);
        $ins_rst = mysql_query($ins_sql);
        $i = $i + mysql_affected_rows();
    }
    umc_echo("$i lots in the contest $id have been closed!!");
}

function umc_contests_delete() {
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $args = $UMC_USER['args'];
    if ($player != 'uncovery' && $player != 'console') {
        umc_error("Nice try, $player. Think I am stupid? Want to get banned?");
    }

    $id = $args[2];
    if (!isset($args[2])){
        umc_show_help($args);
        return;
    } else if (!is_numeric($id)) {
        umc_error("You have to enter a numeric contest ID ($id). See /contest list");
    }

    $sql = "DELETE FROM minecraft_worldguard.region WHERE id LIKE 'con_$id%';";
    $rst = mysql_query($sql);
    $count = mysql_affected_rows();

    umc_echo("$count lots in the contest $id have been removed!");
}


function umc_contests_refund_all() {
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $args = $UMC_USER['args'];
    $max = 1;
    $contest_id = $args[2];
    if (!isset($args[2])){
        umc_show_help($args);
        return;
    } else if (!is_numeric($contest_id)) {
        umc_error("You have to enter a numeric contest ID. See /contest list");
    }

    $which = $args[3];
    if (!isset($args[3])){
        umc_show_help($args);
        return;
    } else if (!in_array($which, array('good', 'bad'))) {
        umc_error("You have to enter good or bad.");
    } else {
        $reset = $args[3];
    }

    umc_echo("Refunding all $which entries of Contest $contest_id");

    $lot_name = "con_" . $contest_id . "_%";
    $sql = "SELECT * FROM minecraft_worldguard.region_cuboid LEFT JOIN minecraft_worldguard.world ON region_cuboid.world_id=world.id WHERE region_id LIKE '$lot_name';";
    $rst = mysql_query($sql);

    $loop_count = 0;
    while ($entry = mysql_fetch_array($rst, MYSQL_ASSOC)) {
        $lot_name = $entry['region_id'];
        $contest_sql = "SELECT * FROM minecraft_srvr.contest_entries WHERE lot='$lot_name';";
        $contest_rst = mysql_query($contest_sql);
        $count = mysql_num_rows($contest_rst);
        if ($count == 0 && $which == 'bad') {
            umc_contests_refund($contest_id, $lot_name, true);
            $loop_count++;
        } else if ($count > 0 && $which == 'good') {
            umc_contests_refund($contest_id, $lot_name, false);
            $loop_count++;
        }
        if ($loop_count >= $max) {
            umc_echo("$max entries processed!");
            return;
        }
    }
}

/**
 * returns false if lot does not exist
 * returns array of Owners if lot is occupied
 * returns empty array if lot unoccupied
 * @param type $world_id ID or name
 * @param type $lot name
 * @return boolean
 */
function umc_region_check_Owners($world_id, $lot) {
    if (!is_numeric($world_id)) {
        $world_id = umc_get_worldguard_id('world', $world_id);
    }

    if (umc_check_lot_exists($world_id, $lot)) {
        $sql = "SELECT user.name as user_name
            FROM minecraft_worldguard.region
            LEFT JOIN minecraft_worldguard.region_players ON region.id = region_players.region_id
            LEFT JOIN minecraft_worldguard.user ON user.id = region_players.user_id
            WHERE region.world_id = $world_id AND region.id = '$lot' AND Owner=1;";
        // echo $sql;
        $rst = mysql_query($sql);
        $owners = array();
        if (mysql_num_rows($rst) > 0) {
            while($row = mysql_fetch_array($rst, MYSQL_ASSOC)) {
                $owners[] = strtolower($row['user_name']);
            }
        }
        return $owners;
    } else {
        return false;
    }
}



/**
 *
 * @global type $UMC_USER
 * @param type $contest_id contest number to choose from
 * @param type $lot_name contest entry to refund
 * @param type $reset if true, the lot will be reset
 */

function umc_contests_refund($contest_id, $lot_name, $reset) {
    global $UMC_USER, $UMC_PATH_MC;
    $player = $UMC_USER['username'];
    $args = $UMC_USER['args'];

    umc_echo("Processing Contest lot $lot_name:");

    $entry_id = explode("_", $lot_name);
    $entry_id = $entry[2];

    $sql = "SELECT * FROM minecraft_worldguard.region_cuboid LEFT JOIN minecraft_worldguard.world ON region_cuboid.world_id=world.id WHERE region_id = '$lot_name';";
    $rst = mysql_query($sql);
    $lot = mysql_fetch_array($rst, MYSQL_ASSOC);
    $world_id = $lot['world_id'];
    $world_name = $lot['name'];
    $owners = umc_region_check_Owners($world_id, $lot_name);
    $owner = $owners[0];

    $server_path = "$UMC_PATH_MC/server";
    $data_path = $server_path . '/minecount/output/' . $lot_name . '.json';
    // read data
    if(!file_exists($data_path)) {
        umc_error("Could not find file $data_path");
    }

    $data = file_get_contents($data_path);
    $data = json_decode($data);

    $convert_array = array(
        2=> 3, 26=> 355, 55 => 331, 59 => 295, 60 => 3, 62 => 61, 63 => 323, 64 => 324, 75 => 76, 68 => 323, 71 => 330,
        74 => 73, 78 => 332, 83 => 338, 93 => 356, 94 => 356, 104 => 361, 105 => 362,
        115 => 372, 117 => 379, 118 => 380, 124 => 123, 25 => 126, 132 => 287,
    );

    umc_echo("Beginning refund of items in area $lot_name to owner $owner");
    foreach ($data as $block => $amount) {
        $block_data = explode(".", $block);
        // get block data from JSON
        $block = $block_data[0];
        $data = 0;
        if (isset($block_data[1])) {
            $data = $block_data[1];
        }
        // convert to local function blockdata
        $id = $block;
        $damage = $data;
        // process
        // umc_echo("incoming: $amount of $name (ID: $id:$damage) ($block)");
        switch ($id) {
            case 0: // invalid blocks, convert to 0 amount
            case 8:
            case 9:
            case 10:
            case 11:
            case 18:// we skip grass, but it should be fixed!
            case 19:
            case 30:
            case 31:
            case 34:
            case 51:
            case 52:
            case 90:
            case 92:
            case 95:
            case 97:
            case 119:
            case 120:
            case 122:
            case 137:
            case 401: // fireworks
                // umc_echo("Skipped $amount of block $name");
                $amount = 0;
                break;
            case 18: // leaves have data 4:
                //$damage = 4;
                break;
            case 29:
            case 33:
                $damage = 7;
                break;
            case 26:
            case 71:
            case 64:
                if ($damage > 7) { // skip bed 2nd half
                    continue;
                }
            //reset data value (rotation etc)
            case 53:
            case 25: // noteblocks needs to be tested!
            case 65:
            case 67:
            case 108:
            case 109:
            case 114:
            case 128:
            case 134:
            case 135:
            case 136:
                $damage = 0;
                break;
            case 127:
                $id = 351;
                $damage = 3;
                break;
            case 43:
                $id = 44;
                $amount = $amount * 2;
                break;
        }
        if ($amount > 0) {
            if (isset($convert_array[$id])) {
                $id_new = $convert_array[$id];
                // umc_echo("Converted $id to $id_new");
                $id = $id_new;
            }
            $data_new = umc_goods_get_text($id, $damage);
            if (!$data_new) {
                umc_error("Could not find data for $block:$data");
            }
            $name = $data_new['item_name'];
            $full = $data_new['full'];
            $damage = $data_new['type'];
            umc_echo("Giving: $amount of $full");
            umc_deposit_give_item($owner, $name, $damage, '', $amount, 'Contest '. $lot_name . " refunds");
        } else {
            // umc_echo("Amount 0 for $amount of $name (ID: $id:$damage");
        }
    }

    umc_echo ("Resetting non-submitted entry $lot_name");
    $sql = "DELETE FROM `minecraft_worldguard`.`region` WHERE `region`.`id` = '$lot_name' AND `region`.`world_id` = $world_id";
    $rst = mysql_query($sql);
    echo mysql_error();

    umc_ws_cmd("region select $lot_name", 'asPlayer');
    umc_ws_cmd("region load -w $world_name", 'asPlayer');
    // umc_ws_cmd("ws contest warp $contest_id $entry_id", 'asPlayer');
}


function umc_contests_warp(){
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $args = $UMC_USER['args'];

    $id = $args[2];
    if (!isset($args[2])){
        umc_show_help($args);
        return;
    } else if (!is_numeric($id)) {
        umc_error("You have to enter a numeric contest ID. See /contest list");
    }
    $num = $args[3];
    if (!isset($args[3])){
        umc_show_help($args);
        return;
    } else if (!is_numeric($num)) {
        umc_error("You have to enter a numeric contest entry. See /contest info $id");
    }

    // find out if the contest is creative or survival
    $sql = "SELECT * FROM minecraft_srvr.contest_contests WHERE id = $id;";
    $rst = mysql_query($sql);
    $contest = mysql_fetch_array($rst, MYSQL_ASSOC);

    $world = 'aether';
    if ($contest['type'] == 'creative') {
       $world = 'flatlands';
    }

    if ($UMC_USER['world'] != $world) {
        umc_error("You have to be in the $world world to do this!");
    }

    $sql = "SELECT * FROM minecraft_worldguard. world LEFT JOIN minecraft_worldguard.region ON world.id=region.world_id
        LEFT JOIN minecraft_worldguard.region_cuboid ON region.id=region_cuboid.region_id
        WHERE world.name='$world' AND region.id LIKE 'con_" . $id . "_" . $num . "' ";

    $rst = mysql_query($sql);
    $count = mysql_num_rows($rst);

    if ($count == 0){
        umc_error("There is no contest entry with id $id and entry $num!");
    }

    $lot = mysql_fetch_array($rst, MYSQL_ASSOC);

    $c_x = $lot['min_x'] + ($contest[x]/ 2);
    $c_z = $lot['max_z'] + 2;
    $c_y = $lot['min_y'] + 2;

    $cmd = "tppos $player $c_x $c_y $c_z 0 0 aether";
    umc_ws_cmd($cmd, 'asConsole');
    umc_pretty_bar("darkblue", "-", "{darkcyan} Visiting contest $id ");
    umc_echo("You are looking now at contest $id entry $num!");
    umc_footer();
}

function umc_contests_join() {
    global $UMC_USER;
    $args = $UMC_USER['args'];
    $player = $UMC_USER['username'];

    $debug = true;

    if (!isset($args[2])) {
        umc_show_help($args);
        return;
    }

    $id = $args[2];

    umc_pretty_bar("darkblue", "-", "{darkcyan} Joining contest $id ");

    if (!is_numeric($id)) {
        umc_error("You have to enter a numeric contest ID ($id). See /contest list");
    }

    // find out if the contest is creative or survival
    $sql = "SELECT * FROM minecraft_srvr.contest_contests WHERE id = $id;";
    $rst = mysql_query($sql);
    $contest = mysql_fetch_array($rst, MYSQL_ASSOC);
    $status = $contest['status'];
    if ($status !== 'active') {
        umc_error("Contest number $id is not active. Please chose a different contest!");
    }


    $min = array(
        'aether' => array('x' => -1532, 'z' => -1532, 'y' => 11, 'parent' => 'aet_p1'),
        'flatlands' => array('x' => 1028, 'z' =>  1028, 'y' => 64, 'parent' => 'flat_b19'),
    );
    $max = array(
        'aether' => array('x' => -1157, 'z' => -1157, 'y' => 255),
        'flatlands' => array('x' => 1275, 'z' =>  1275, 'y' => 255),
    );
    $gap = 4;

    $type = 'survival';
    $world = 'aether';
    if ($contest['type'] == 'creative') {
       $type = 'creative';
       $world = 'flatlands';
    }
    $parent = $min[$world]['parent'];

    $min_coords = $min[$world];
    $min_x = $min_coords['x'];
    $min_z = $min_coords['z'];
    $min_y = $min_coords['y'];
    if ($debug) {umc_echo("Min coords are $min_x/$min_y/$min_z");}

    $max_coords = $max[$world];
    $max_x = $max_coords['x'];
    $max_z = $max_coords['z'];
    $max_y = $max_coords['y'];
    if ($debug) {umc_echo("MAx coords are $max_x/$max_y/$max_z");}

    $user_id = umc_get_worldguard_id('user', strtolower($player));
    $world_id = umc_get_worldguard_id('world', strtolower($world));

    // find out if the user can have additional contest entries in this contest
    $sql = "SELECT * FROM minecraft_worldguard.world LEFT JOIN minecraft_worldguard.region ON world.id=region.world_id
        LEFT JOIN minecraft_worldguard.region_cuboid ON region.id=region_cuboid.region_id
        LEFT JOIN minecraft_worldguard.region_players ON region_cuboid.region_id=region_players.region_id
        WHERE world.name='$world' AND region.id LIKE 'con_". $id ."%' AND user_id=$user_id AND Owner=1
        ORDER BY max_z, max_x";
    $rst = mysql_query($sql);
    $count = mysql_num_rows($rst);
    if ($count >= $contest['max_entries'] && $player != 'uncovery' ) {
        umc_error("You have reached the max number of entries for this contest!;");
    }

    // find out if a contest lot already exists
    $sql = "SELECT * FROM minecraft_worldguard.world LEFT JOIN minecraft_worldguard.region ON world.id=region.world_id
        LEFT JOIN minecraft_worldguard.region_cuboid ON region.id=region_cuboid.region_id
        WHERE world.name='$world' AND region.id LIKE 'con%' ORDER BY max_z, max_x";
    $rst = mysql_query($sql);
    $count = mysql_num_rows($rst);
    if ($debug) {umc_echo("$count entries already entered!");}

    $lotnumber = $count + 1;

    $lot = 'con_' . $id . '_' . $lotnumber;
    if ($debug) {umc_echo("Trying to create entry $lot");}

    // how many lots can I fit into the space across?
    $fullwidth = $max_x - $min_x;
    if ($debug) {umc_echo("Lot width is $fullwidth");}

    $single_width = $contest['x'] + $gap;
    if ($debug) {umc_echo("One lot (with gap) is $single_width wide");}
    $width_lots = floor($fullwidth / $single_width);
    if ($debug) {umc_echo("Fitting $width_lots per line");}

    $fulldepth = $max_z - $min_z;
    $single_depth = $contest['z'] + $gap;
    if ($debug) {umc_echo("One lot (with gap) is $single_depth deep");}
    $depth_lots = floor($fulldepth / $single_depth);
    if ($debug) {umc_echo("Fitting $depth_lots per row");}

    $full_lines = floor($count / $width_lots);
    if ($debug) {umc_echo("$full_lines lines already full");}

    $lastline_lots = $count - ($full_lines * $width_lots);
    if ($debug) {umc_echo("Last line has $lastline_lots lots");}

    $start_x = $lastline_lots * ($contest['x'] + $gap) + $min_coords['x'];
    $start_z = $full_lines * ($contest['z'] + $gap) + $min_coords['z'];
    $start_y = $min_coords['y'];
    if ($debug) {umc_echo("Starting coords are {$start_x}/{$start_y}/{$start_z}");}

    $end_x = $start_x + $contest['x'] - 1;
    $end_z = $start_z + $contest['z'] - 1;
    $end_y = $min_coords['y'] + $contest['y'] - 1;
    if ($debug) {umc_echo("End coords are {$end_x}/{$end_y}/{$end_z}");}

    if (($end_x > $max_x) || ($end_z > $max_z)) {
        umc_error('There is no more space for additional contest entries!;');
    }

    umc_echo("New lot {gold}$lot{white} in {gold}$world{white} was created at ");
    umc_echo("coordinates {gold}$start_x/$start_y/$start_z{white} - {gold}$end_x/$end_y/$end_z{white};");
    umc_echo("Use {gold}/contest warp $id $lotnumber{white} to get there.");

    // create insert SQL 	id 	world_id 	type 	priority 	parent
    $ins_region_sql = "INSERT INTO region (id, world_id, type, priority, parent)
        VALUES ('$lot', $world_id, 'cuboid', 0, '$parent');";
    $ins_region_rst = mysql_query($ins_region_sql);
    // insert cuboid region_id 	world_id 	min_x 	min_y 	min_z 	max_x 	max_y 	max_z
    $ins_cuboid_sql = "INSERT INTO region_cuboid (region_id, world_id, min_x, min_y, min_z, max_x, max_y, max_z)
        VALUES ('$lot', $world_id, $start_x, $start_y, $start_z, $end_x, $end_y, $end_z);";
    $ins_cuboid_rst = mysql_query($ins_cuboid_sql);
    // add user to lot as Owner  region_id 	world_id 	user_id 	Owner
    $ins_user_sql = "INSERT INTO region_players (region_id, world_id, user_id, Owner)
        VALUES ('$lot', $world_id, $user_id, 1);";
    $inc_user_rst = mysql_query($ins_user_sql);
    umc_ws_cmd("region load -w $world", 'asConsole');

    umc_footer();
}

?>
