<?php

/* Configuration Section */

global $prefix;
$prefix = "contest_"; //Set a prefix for your tables here
 
$contest_blacklist = array(); //people that cannot enter contests
$voting_blacklist = array(); //people that cannot vote

$can_vote = array(
    'Owner' => 10,
    'ElderDonatorPlus' => 1, 'ElderDonator' => 1, 'Elder' => 1,
    'MasterDonatorPlus' => 0.65, 'MasterDonator' => 0.65, 'Master' => 0.65,
    'Designer' => 0.55, 'DesignerDonator' => 0.55, 'DesignerDonatorPlus' => 0.55,
    'ArchitectDonatorPlus' => 0.45, 'ArchitectDonator' => 0.45, 'Architect' => 0.45,
    'CitizenDonatorPlus' => 0.3, 'CitizenDonator' => 0.3, 'Citizen' => 0.3,
    'SettlerDonatorPlus' => 0.2, 'SettlerDonator' => 0.2, 'Settler' => 0.2,
    'Guest' => 0,
);


/* End config */

remove_filter( 'the_content', 'wpautop' );

// var_dump($GLOBALS);

// umc_reset_tables(); // WARNING: THIS CLEARS ALL CONTEST DATA!!
//umc_debug(); // print_r arrays with all contest, entry, and voting data

include_once('/home/minecraft/server/bin/users.php');

global $UMC_USER;
if(!$UMC_USER) {
    die("You need to log in!");
} else {
    $username = $UMC_USER['username'];
    $userlevel = $UMC_USER['userlevel'];
}

// var_dump( $GLOBALS[info][groups][0]);
// var_dump($GLOBALS);
/*
echo "isadmin : " . umc_is_admin();
echo "<br>can vote: " . umc_can_vote("senocular");
echo "<Br>can enter : " . umc_can_enter_contests();
 */


function umc_contest_index() {
    $out = "<div id=\"contests\"><div id=\"contest_menu\">
        Show contests: <a href=\"?action=list_contests&amp;type=current\">Open</a> 
                     | <a href=\"?action=list_contests&amp;type=voting\">Now Voting</a>
                     | <a href=\"?action=list_contests&amp;type=closed\">Closed</a>
                     | <a href=\"?action=list_contests&amp;type=all\">All</a>
    <div id='holder'>";

    //$_GET = sanitize($_GET);
    //$_POST = sanitize($_POST);

    if (isset($_POST['action'])) {
        $action = $_POST['action'];
    } else if (isset($_GET['action'])) {
        $action = $_GET['action'];
    } else {
        $action = $_POST['func'];
    }
    
    $errors = array();
    switch($action) {
        case "create_contest";
            umc_create_contest(
                $_POST["title"], $_POST["desc"], $_POST["max_entries"], $_POST["deadline"],
                $_POST["type"], $_POST["x"], $_POST["y"], $_POST["z"]);
            $out .= umc_get_formatted_contests("active");
            break;
        case "end_contest":
            umc_end_contest(intval($_GET["id"]));
            $out .= umc_get_formatted_contests("active");
            break;
        case "vote_contest":
            umc_vote_contest(intval($_GET["id"]));
            $out .= umc_get_formatted_contests("active");
            break;
        case "resume_contest":
            umc_resume_contest(intval($_GET["id"]));
            $out .= umc_get_formatted_contests("active");
            break;
        case "delete_contest":
            umc_delete_contest(intval($_GET["id"]));
            $out .= umc_get_formatted_contests("active");
            break;
        case "change_deadline":
            umc_change_deadline($_POST["deadline"],$_POST["contest"]);
            $out .= umc_get_formatted_contests("active");
            break;
        case "show_contest":
            $out .= umc_get_formatted_entries(intval($_POST["id"]));
            break;
        case "list_contests":
            $out .= umc_get_formatted_contests($_GET["type"]);
            break;
        case "enter_contest":
            //var_dump($_POST);
            
            umc_validate_and_enter(
               intval($_POST["contest"]), $_POST["title"], $GLOBALS[username], implode("|", $GLOBALS[info][groups]), $_POST["desc"],
                $_POST["lot"]);
            $out .= umc_get_formatted_entries(intval($_POST["contest"]));
            break;
        case "delete_entry":
            umc_delete_entry(intval($_GET["id"]), intval($_GET["contest"]));
            $out .= umc_get_formatted_entries(intval($_GET["contest"]));
            break;
        case "show_entry":
            $out .= umc_get_formatted_entry(intval($_POST["id"]));
            break;
        case "vote":
            umc_vote(intval($_POST[id]), $GLOBALS[username], $_POST[cat], floatval($_POST[val]));
            $out .= umc_get_formatted_entry(intval($_POST["id"]));
            break;
        default:
            $out .= umc_get_formatted_contests($_POST["mode"]);
            break;
    }

    return $out."</div></div>";
}
 
//umc_reset_tables();
//umc_debug();
 
function umc_is_admin() {
    global $info;
    return (array_search('Owner', $info['groups']) > -1);
}
function umc_can_vote($creator) {
    global $can_vote;

    $vote_rank = false;
    foreach ($GLOBALS[info][groups] as $group) {
        if (array_key_exists($group, $can_vote)) {
            $vote_rank = true;
        }
    }

    $check = (array_search($GLOBALS[username], $GLOBALS[voting_blacklist]) == false)
        && ($vote_rank) && !($creator == $GLOBALS[username]);
    return $check;
}

function umc_vote_weight() {
    global $can_vote;

    foreach ($GLOBALS[info][groups] as $group) {
        if (array_key_exists($group, $can_vote)) {
            return $can_vote[$group];
        }
    }
}

function umc_can_enter_contests() {
    $out = array_search($GLOBALS[username], $GLOBALS[contest_blacklist]) == false;
    return true;
}

function umc_i_am($username) {
        return $username == $GLOBALS[username];
}
 
//sanitize input
function sanitize($input) {
    $arr = array();
    foreach($input as $key => $var) {
        $type = gettype($var);
        switch($type) {
            case "string":
                $var = mysql_real_escape_string(htmlentities($var));
                break;
            case "array":
                $var = sanitize($var);
                break;
            case "object":
                $var = sanitize($var);
                break;
            default:
                $var = $var;
        }
        $arr[$key] = $var;
    }
    return $arr;
}

function umc_getjs() {
    if($_GET[func] == "entry") {
        if(count(get_entry_info(intval($_GET[id]))) > 0) {
            $js = "umc_show_entry('".$_GET[id]."');\n";
        } else {
            $js = "umc_list_contests(\"active\");\n"
                . "$(\"input\").placeholder();\n"
                . "$(\"textarea\").placeholder();\n"
                . "alert('Invalid entry!');\n";
        }
    } else {
       // $js = "umc_list_contests(\"active\");\n";
       // $js .= "$(\"input\").placeholder();\n";
       // $js .= "$(\"textarea\").placeholder();\n";
    }
    //return $js;
}
 
function umc_simple_validate($val, $type = "string", $min = 1, $max = 30) {
    switch($type) {
        case "string":
            $len = strlen($val);
            return $len >= $min && $len <= $max;
        case "number":
            return $val >= $min && $val <= $max;
    }
}
 
function umc_validate_and_enter($id, $entry_title, $name, $level, $description, $lot) {
    global $username;
    //echo "<div class='error'>";
    if($id > 0) {
        if(umc_can_enter($id, $username)) {
            if (umc_simple_validate($entry_title, 'string')) {
                // echo wp_filter_comment();
                return umc_enter_contest($id, $entry_title, $username, $level,  $description, $lot);
            } else if (!umc_simple_validate($entry_title, "string")) {
                umc_throw_error("You must enter a title of no more than 30 characters.");
                echo "You must enter a title of no more than 30 characters.";
                return false;
            }
        } else {
            umc_throw_error("Couldn't enter contest. Possible reasons: 1. Not allowed. 2. Already entered maximum submissions. 3. Contest is no longer open for submissions.");
            echo "Couldn't enter contest. Possible reasons: 1. Not allowed. 2. Already entered maximum submissions. 3. Contest is no longer open for sumissions.";
            return false;
        }
    } else {
        umc_throw_error("Invalid contest.");
        echo "Invalid contest.";
        return false;
    }
    // echo "</div>";
}
 
function umc_can_enter($id, $username = "") {
    if($username == "") {
        $username = $GLOBALS['username'];
    }
    $contest_info = umc_get_contest_info($id);
    /* var_dump($contest_info);
    echo "canentercontests:".umc_can_enter_contests();
    echo "<Br>numentries:".umc_num_entries($id, $username);
    echo "<br>maxentries:". $contest_info[max_entries];
    echo "<Br>isactive:" . $contest_info[active]; */
    //return umc_can_enter_contests($username) && (umc_num_entries($id, $username) < $contest_info[max_entries]) && $contest_info[active];
    return umc_can_enter_contests() && (umc_num_entries($id, $username) < $contest_info[max_entries]) && ($contest_info[status] = 'open');
}
 
//contest functions
function umc_vote($entry_id_or_title, $name, $cat, $val) {
    global $prefix;
    $sql = array();
    if (is_string($entry_id_or_title)) {
        $ids = umc_get_entries_by_title($entry_id_or_title);
    } else {
        $ids = array($entry_id_or_title);
    }
    foreach($ids as $id) {
        $entry_info = umc_get_entry_info($id);
        if (!umc_can_vote($entry_info[user])) {
            die("You don't have permission to do that.");
        }
        $sql[] = "DELETE FROM ".$prefix."votes WHERE entry='".$id."' AND user='".$name."' AND category='".$cat."'";
        $sql[] = "INSERT INTO ".$prefix."votes (entry, contest, user, category, value, weight) VALUES ('".$id."', '".$entry_info[contest]."', '".$name."', '".$cat."', '".$val."', '".umc_vote_weight()."')";
    }
    mysql_select_db('minecraft_srvr');
    foreach($sql as $statement) {
        $rst = mysql_query($statement);
    }
    $id = mysql_insert_id();
    mysql_select_db('minecraft');
    return $id;
}

function umc_enter_contest($id_or_title, $entry_title, $name, $level, $description, $lot) {
    global $prefix;
    $sql = array();
    if (is_string($id_or_title)) {
        $ids = umc_get_contests_by_title($id_or_title);
    } else {
        $ids = array($id_or_title);
    }
    
    $description = nl2br(strip_tags($description));
    foreach($ids as $id) {
        $sql[] = "INSERT INTO minecraft_srvr.$prefix entries (title, contest, user, level, description, lot)
                VALUES ('$entry_title', '$id', '$name', '$level', '$description', '$lot')";
    }
    // var_dump($sql);
    foreach($sql as $statement) {
        $rst = mysql_query($statement);
    }
    return mysql_insert_id();
}
 
function umc_create_contest($title, $desc, $max_entries, $deadline, $type, $x, $y, $z, $voting_categories="Looks|Realism|Fun|Usability|Innovation") {
    if(!umc_is_admin()) {
        die("You don't have permission to do that.");
    }
    echo "Creating contest entry...<br>";
    global $prefix;
    $sql = "INSERT INTO minecraft_srvr.".$prefix."contests (title, description, max_entries, voting_categories, deadline, status, type, x, y, z)
		VALUES ('$title', '$desc', '$max_entries', '$voting_categories', '$deadline', 'active', '$type', $x, $y, $z)";
    $rst = mysql_query($sql);
    if (!$rst) {
        echo mysql_error();
        echo "<br>" . $sql;
        var_dump($_POST);
    } else {
        echo "Contest entry inserted!";
        return mysql_insert_id();
    }
}
 
function umc_end_contest($id_or_title) {
    if(!umc_is_admin()) die("You don't have permission to do that.");
    global $prefix;
    $sql = array();
    if (is_string($id_or_title)) {
        $ids = umc_get_contests_by_title($id_or_title);
    } else {
        $ids = array($id_or_title);
    }
    foreach ($ids as $id) {
        $sql[] = "UPDATE minecraft_srvr.".$prefix."contests SET status='closed' WHERE id='".$id."'";
    }
    foreach ($sql as $statement) {
        $rst = mysql_query($statement);
    }
    return mysql_insert_id();
}

function umc_vote_contest($id_or_title) {
    if(!umc_is_admin()) die("You don't have permission to do that.");
    global $prefix;
    $sql = array();
    if(is_string($id_or_title)) $ids = umc_get_contests_by_title($id_or_title);
    else $ids = array($id_or_title);
    foreach($ids as $id) {
        $sql[] = "UPDATE minecraft_srvr.".$prefix."contests SET status='voting' WHERE id='".$id."'";
    }
    foreach($sql as $statement) {
        $rst = mysql_query($statement);
    }
    return mysql_insert_id();
}

 
function umc_resume_contest($id_or_title) {
    if(!umc_is_admin()) die("You don't have permission to do that.");
    global $prefix;
    $sql = array();
    if(is_string($id_or_title)) $ids = umc_get_contests_by_title($id_or_title);
    else $ids = array($id_or_title);
    foreach($ids as $id) {
        $sql[] = "UPDATE minecraft_srvr.".$prefix."contests SET status='active' WHERE id='".$id."'";
    }
    foreach($sql as $statement) {
        $rst = mysql_query($statement);
    }
}      
 
function umc_delete_contest($id_or_title) {
    if (!umc_is_admin()) {
        die("You don't have permission to do that.");
    }
    echo "starting delete";
    global $prefix;
    $sql = array();
    if (is_string($id_or_title)) { 
        $ids = umc_get_contests_by_title($id_or_title);
    } else {
        $ids = array($id_or_title);
    }
    foreach($ids as $id) {
        $sql[] = "DELETE FROM minecraft_srvr.".$prefix."contests WHERE id='".$id."'";
        $sql[] = "DELETE FROM minecraft_srvr.".$prefix."entries WHERE contest='".$id."'";
        $sql[] = "DELETE FROM minecraft_srvr.".$prefix."votes WHERE contest='".$id."'";
    }
    foreach($sql as $statement) {
        $rst = mysql_query($statement);
    }

}

function umc_change_deadline($deadline, $contest) {
    global $prefix;

    $sql = "UPDATE minecraft_srvr.".$prefix."contests SET deadline='".$deadline."' WHERE id='".$contest."'";
    mysql_query($sql);
}

function umc_delete_entry($id_or_title, $contest) {
    global $prefix;
    $sql = array();
    if (is_numeric($id_or_title)) {
        $ids = array($id_or_title);
    } else {
        $ids = umc_get_entry($id_or_title, $contest);
    }
   
    foreach($ids as $id) {
        $entry_info = umc_get_entry_info($id);
        if (!(umc_is_admin() || umc_i_am($entry_info[user]))) {
            die("You don't have permission to do that.");
        }
        $sql[] = "DELETE FROM minecraft_srvr.".$prefix."entries WHERE id='".$id."'";
        $sql[] = "DELETE FROM minecraft_srvr.".$prefix."votes WHERE entry='".$id."'";
    }
    foreach($sql as $statement) {
        $rst = mysql_query($statement);
    }
}
 
function umc_get_contests_by_title($title) {
        global $prefix;
        $sql = "SELECT id FROM minecraft_srvr.".$prefix."contests WHERE title='".$title."'";
        $ids = array();

        $rst = mysql_query($sql);
        while ($row = mysql_fetch_array($rst, MYSQL_ASSOC)) {
            $ids[] = $row['id'];
        }
        if (count($ids) > 0) {        
            return $ids;
        } else {
            umc_throw_error("Can't find contest.");
            return -1;
        }
}
 
function umc_get_entry($entry_title, $contest_id_or_title) {
    global $prefix;
    if (is_string($contest_id_or_title)) {
        $ids = umc_get_contests_by_title($contest_id_or_title);
    } else {
        $ids = array($contest_id_or_title);
    }
    $contest_id = $ids[0];
    if($contest_id > -1) {
        $sql = "SELECT id FROM minecraft_srvr.".$prefix."entries WHERE title='".$title."' AND contest='".$contest_id."'";
        $rst = mysql_query($sql);
        while ($row = mysql_fetch_array($rst, MYSQL_ASSOC)) {
            $ids[] = $row['id'];
        }
        if (count($ids) >0) {
            return intval($ids[0]);
        } else {
            umc_throw_error("Building not found.");
            return -1;
        }
    } else {
        return -1;
    }
}
 
function umc_num_entries($id_or_title, $name) {
    global $prefix;
    if (is_string($id_or_title)) {
        $ids = umc_get_contests_by_title($id_or_title);
    } else {
        $ids = array($id_or_title);
    }
    $contest_id = $ids[0];
    if($contest_id > -1) {
        $sql = "SELECT id FROM minecraft_srvr.".$prefix."entries WHERE user='".$name."' AND contest='".$contest_id."'";
        $rst = mysql_query($sql);
        return mysql_num_rows($rst);
    } else {
        return 1;
    }
}
 
function umc_get_entries_by_title($entry_title) {
    global $prefix;
    $sql = "SELECT id FROM minecraft_srvr.".$prefix."entries WHERE title='".$entry_title."'";
    $ids = array();
    $rst = mysql_query($sql);
    while ($row = mysql_fetch_array($rst, MYSQL_ASSOC)) {
        $ids[] = $row['id'];
    }
    return $ids;
}
 
//formatted output functions
 
function umc_get_stars($id, $votes, $cat, $can_edit=false) {
    global $username;
    $input_id = str_replace(" ", "_", strtolower($cat))."_".$id;
    $total_score = 0.0;
    $total_weight = 0.0;
    $uservote = 0;
    $usercount = 0;
    foreach ($votes as $vote) {
        $total_score += $vote[value] * $vote[weight];
        $total_weight += $vote[weight];
        if ($vote[user] == $username) {
            $uservote += $vote[value];
            $usercount ++;
        }
    }
    if($total_weight > 0) {
        $score = $total_score / $total_weight;
    } else {
        $score = 0;
    }
    if ($can_edit) {
        $ro = 'false';
        $click_callback = "function(val) { alert(val); }";
    } else {
        $ro = 'true';
        $click_callback = "function() { return false; }";
    }
    if ($usercount > 0) {
        $uservote = "You voted " . $uservote / $usercount . " stars ";
    } else if ($usercount == 0 || !$can_edit) {
        $uservote = '';        
    } else {
        $uservote = "You did not vote yet<br>";
        $uservote = '';
    }
    $html = '<div id="'.$input_id.'">';

    $html .= '</div>' . $uservote;
    $js = umc_get_star($input_id, $id, $score, 24, $cat, $ro) . "(Avg. ". round($score, 2) . " stars)<br>";
    return $html . $js;
}

function umc_get_star($input_id, $id, $score, $size, $cat, $ro) {
    $js = "
    <script type=\"text/javascript\" language=\"javascript\">\n
    $('#".$input_id."').raty({
        readOnly: ".$ro.",
        half: true,
        start: ".$score.",
        path: '/contests/img/',
        size: 24,
        click: function(val) { alert('This will submit a vote of ' + val + ' for the category \"".$cat
            ."\". You may change your vote later, but only one vote will be considered.'); umc_vote(".$id.", '".$cat."', val);  },
        hintList: ['needs work', 'average', 'decent', 'good', 'super']
    });\n
    </script>";
    return $js;

}

function umc_get_formatted_entry($id = false) {
    global $username;
    $entry_html = "";

    if (isset($_GET['type'])) {
        $id = $_GET['type'];
    }
    $id = intval($id);

    $entry = umc_get_entry_info($id);
    $contest = umc_get_contest_info($entry[contest]);
    $votes = umc_get_votes_info($id);
    $categories = array_filter(explode("|", $contest[voting_categories]));
    if ($contest['statys'] == 'voting' && count($votes) == 0 && umc_can_vote($entry[user])) {
        $entry_html .= '<div id="no_votes">This entry hasn\'t received any votes yet! Why don\'t you be the first to vote?</div>';
    }
    $rating_html = "";
    if ($contest['status'] == 'voting') {
        foreach ($categories as $cat) {
            $cat_votes = umc_filter_votes($votes, $cat);
            $rating_html .= $cat.':'.umc_get_stars($id, $cat_votes, $cat, umc_can_vote($entry[user]));
        }
    } else {
        $rating_html .= umc_get_stars($id, umc_get_votes_info($id), "all");
    }
    $icon_url = umc_user_get_icon_url($entry['user']);
    $entry_html .= '<hr><div id="single_entry"><div class="single_entry_title">' . stripslashes($entry[title])
        . "</div><br/>by <img width=\"32\" src=\"$icon_url\"/>{$entry[user]} (". umc_get_userlevel($entry[user]) . ')</br>for <a href="?action=show_contest&amp;type=' . $contest['id'] . '">' . $contest['title'] . '</a><br />'
        . '<hr><p>' . stripslashes($entry[description]) . '</p>'
        . '<strong>Lot:</strong> '.$entry[lot].'<br>'
        . '<strong>Screenshots:</strong><div id="screenshot_gallery">';
    $screenshots = explode("|", $entry[screenshots]);
    $i = 1;
    foreach ($screenshots as $url) {
        if(strlen($url) >= 5) {
            $entry_html .= '<a href="'.$url.'" rel="prettyPhoto[ss_gal]"><img class="screenshot" src="'.$url.'" height="60" alt="Screenshot #'.$i.'" /></a>' . "\n";
        }
        $i++;
    }
    $entry_html .= '</div>'
        . '<div id="average_rating" style="width:100%;">'
        . $rating_html
        . '</div>'
        . '</div>';
    return $entry_html; // ."<br />can enter: ".umc_can_enter($id)." id: ".$id;
}

function umc_get_formatted_contests($mode = "active"){
    global $prefix;

    if ($_GET['action'] == 'list_contests') {
        $mode = $_GET['type'];
    }

    switch($mode) {
        case "closed":
            $sql = "SELECT * FROM minecraft_srvr.".$prefix."contests WHERE status='closed';";
            $contest_header = "Past Contests:";
            break;
        case "all":
            $sql = "SELECT * FROM minecraft_srvr.".$prefix."contests";
            $contest_header = "All Contests:";
            break;
        case "voting":
            $sql = "SELECT * FROM minecraft_srvr.".$prefix."contests WHERE status='voting';";
            $contest_header = "All Contests:";
            break;
        default:
            $sql = "SELECT * FROM minecraft_srvr.".$prefix."contests WHERE status='active'";
            $contest_header = "Current Contests:";
    }
    $rst = mysql_query($sql);
    $ret = '<div id="contest_header" class="header">'.$contest_header.'</div>';
    if (mysql_num_rows($rst) == 0) {
        $ret .= "No entries found!";
    }

    while ($row = mysql_fetch_array($rst, MYSQL_ASSOC)) {
        $pre_title = 'Survival: ';
        if ($row['type'] == 'creative') {
            $pre_title = 'Creative: ';
        }
        $admin_html = '<div class="admin_opts">Admin options: ';
        $admin_html .= '<a href="?action=delete_contest&amp;id='.$row['id'].'" class="delete_contest">Delete</a>';
        if ($row['status'] == 'voting') {
            $admin_html .= ' | <a href="?action=end_contest&amp;id='.$row['id'].'" class="toggle_contest">End</a> ';
        } else if ($row['status'] == 'active') {
            $admin_html .= ' | <a href="?action=vote_contest&amp;id='.$row['id'].'" class="toggle_contest">Open voting</a> '
                . " | <form method='post' style='display:inline;'>"
                . "<input name='deadline' type='text' size='12'>"
                . "<input type='hidden' name='contest' value='{$row['id']}'>"
                . "<input type='hidden' name='action' value='change_deadline'>"
                . "<input type='submit' value='Change Deadline'>"
                . "</form>";
        } else {
            $admin_html .= ' | <a href="?action=resume_contest&amp;id='.$row['id'].'" class="toggle_contest">Resume</a> ';
        }
        $admin_html .= "</div><hr>";
        
        $ret .= '<div class="contest">'. "\n". '<div class="contest_title"><a href="?action=show_contest&amp;type='
            . $row['id'].'">'.$pre_title.$row['title'].'</a></div>'.  "\n";
        $deadline_text = "";
        if ($row['deadline'] != "" && $row['deadline'] != '0000-00-00' && $row['status'] == 'active') {
            $deadline_text = "<strong>Deadline:</strong> {$row['deadline']}\n";
        }
        $ret .= '<hr>'.stripslashes($row['description'])
            . "<hr><strong>ID:</strong>" . $row['id']
            . " $deadline_text <strong>Width:</strong>" . $row['x']. ' <strong>Length:</strong>' . $row['z']
            . ' <strong>Height:</strong>' . $row['y']. '</div>';
        if (umc_is_admin()) {
            $ret .= $admin_html;
        }
        //$ret .= '</div>';
    }
    
    // create new contest
    $admin_html2 = '<div id="new_contest_form">Create a new contest:<br />'
        . '<form method="post">'
        . '<input type="text" name="title" placeholder="Title"><br />'
        . '<textarea name="desc" placeholder="Description"></textarea><br />'
        . 'Maximum Entries: <input type="text" name="max_entries" value="1"> '
	    . 'Type: Survival <input type="radio" name="type" value="survival"> Creative <input name="type" type="radio" value="creative"><br />'
        . 'Dimensions: Width <input type="text" name="x" size="3" value="20"> '
        . 'Depth <input type="text" name="z" size="3" value="20"> '
        . 'Height <input type="text" name="y" size="3" value="125"><br />'
	    . 'Submission Deadline: <input type="text" name="deadline" size="12" placeholder="yyyy-mm-dd"/><br />'
        . '<input type="hidden" name="action" value="create_contest">'
        . '<input type="submit" value="Create Contest"></form>'
        . '</div>';
    if (umc_is_admin()) {
        $ret .= $admin_html2;
    }

    return $ret;
}
 
function umc_get_formatted_entries($contest_id = false, $new_entry_id = 0) {
    $id = intval($contest_id);
    global $prefix, $UMC_DOMAIN;

    if (isset($_GET['type'])) {
        $id = intval($_GET['type']);
    }

    // get contest title
    $sql = "SELECT * FROM ".$prefix."contests WHERE id=$id;";
    mysql_select_db('minecraft_srvr');
    $rst = mysql_query($sql);
    $row = mysql_fetch_array($rst, MYSQL_ASSOC);
    $pre_title = 'Survival: ';
    if ($row['type'] == 'creative') {
        $pre_title = 'Creative: ';
    }
    
    $ret = '<div class="contest"><div class="contest_title">'.$pre_title.$row['title'].'</div>';
    $deadline_text = "";
    if ($row['deadline'] != "" && $row['deadline'] != '0000-00-00' && $row['status'] == 'active') {
        $deadline_text = "<strong>Deadline:</strong> {$row['deadline']}";
    }
    $ret .= '<hr>'.stripslashes($row['description'])
        . "<hr><strong>ID:</strong>" . $row['id']
        . " $deadline_text <strong>Width:</strong>" . $row['x']. ' <strong>Length:</strong>' . $row['z']
        . ' <strong>Height:</strong>' . $row['y']. '</div>';

    // all entries
    $sql = "SELECT * FROM ".$prefix."entries WHERE contest='".$id."'";
    $rst = mysql_query($sql);
    if(mysql_num_rows($rst) > 0) {
        $ret .= '<div id="entries_header" class="header">Entries:</div>';
    } else {
        $ret .= '<div id="entries_header" class="header">This contest has no entries.</div>';
    }
    while ($row = mysql_fetch_array($rst, MYSQL_ASSOC)) {
        $admin_html = '<div class="opts">'
            . '<a href="?action=delete_entry&amp;contest='.$id.'&amp;id='.$row['id'].'" class="delete_entry">Delete</a>'
            . '</div>';
        if ($row['id'] == intval($new_entry_id)) {
            $ret .= '<div class="contest_entry new_entry">';
        } else {
            $ret .= '<div class="contest_entry">';
        }
        $icon_url = umc_user_get_icon_url($row['user']);
        $ret .= '<div class="entry_title"><a href="?action=show_entry&amp;type='.$row['id'].'">' . stripslashes($row['title'])
            .'</a></div><div class="creator">by <img width=\"16\" src="'.$icon_url.'"/>&nbsp;<strong>'.$row['user'].'</strong> ('. umc_get_userlevel($row['user']) . ')</div>'
            . umc_get_stars($row['id'], umc_get_votes_info($row['id']), "all");
        if (umc_is_admin() || umc_i_am($row->user)) {
            $ret .= $admin_html;
        }
        $ret .= '</div>';
    }
    
    //create new contest entry
    
    // find entry by user
    
    $user_arr = umc_is_online();
    
    if ($user_arr['online'] == false) {
        $ret .= "To enter your contest entry, please <a href=\"$UMC_DOMAIN/wp-admin/profile.php\">logged in</a>!";
        return $ret;
    }
    
    $username = $user_arr['username'];
    $lower_username = strtolower($username);
    mysql_select_db('minecraft_worldguard');

    // find out if the user can have additional contest entries in this contest
    $sql = "SELECT * FROM world LEFT JOIN region ON world.id=region.world_id
        LEFT JOIN region_cuboid ON region.id=region_cuboid.region_id
        LEFT JOIN region_players ON region_cuboid.region_id=region_players.region_id
        LEFT JOIN user ON region_players.user_id=user.id
        WHERE region.id LIKE 'con_$id%' AND Owner=1 AND user.name = '$lower_username'
        ORDER BY max_z, max_x";

    $rst = mysql_query($sql);
    $count = mysql_num_rows($rst);

    if ($count == 0) {
        $ret .= "To create a contest entry, please type <strong>/contest</strong> in-game!";
        return $ret;
    }
    
    $entries = array();
    while ($row = mysql_fetch_array($rst, MYSQL_ASSOC)) {
        $entries[] = $row['region_id'];
    }
    
    mysql_select_db('minecraft_srvr');    

    foreach ($entries as $entry) {
        $sql = "SELECT * FROM contest_entries WHERE contest = $id AND user = '$lower_username' AND lot='$entry';";
        // echo $sql;
        $rst = mysql_query($sql);
        $count = mysql_num_rows($rst);
        if ($count == 1) {
            // entry was already submitted
        } else {
            // entry has to be submitted still
            $entry_html .= "<div id=\"new_entry_form\">Submit your entry from lot $entry: "
                . '<form method="post">'
                . '<input type="hidden" name="contest" id="contest_id" value="'.$id.'">'
                . '<input type="hidden" name="lot" id="lot" value="'.$entry.'">'
                . '<input type="text" name="title" id="new_entry_title" placeholder="Title" /><br />'
                . '<textarea id="new_entry_desc" name="desc" placeholder="Description"></textarea><br />'
                . '<input type="hidden" name="action" value="enter_contest">'
                . '<hr/><input type="submit" id="enter_contest" value="Enter Contest" >'
                . '</form>'
                . '</div>';
        }
    }
    

    //if(umc_can_enter($id)) {
        $ret .= $entry_html;
    //}
    return $ret; // ."<br />can enter: ".umc_can_enter($id)." id: ".$id;
}
function umc_filter_votes($votes, $crit, $mode = "category") {
    $arr = array();
    switch($mode) {
        case "category":
            foreach ($votes as $vote) {
                if ($vote[category] == $crit) {
                    $arr[] = $vote;
                }
            }
        case "user":
            foreach ($votes as $vote) {
                if ($vote[user] == $crit) {
                    $arr[] = $vote;
                }
            }
    }
    return $arr;
}
 
//general functions
 
function umc_get_contest_info($id) {
    global $prefix;
    mysql_select_db('minecraft_srvr');
    $sql = "SELECT * FROM ".$prefix."contests WHERE id=".intval($id);
    $rst = mysql_query($sql);
    $row = mysql_fetch_array($rst, MYSQL_ASSOC);
    return $row;
}
 
function umc_get_entry_info($id) {
    global $prefix;
    mysql_select_db('minecraft_srvr');
    $sql = "SELECT * FROM ".$prefix."entries WHERE id=".intval($id);
    $rst = mysql_query($sql);
    $row = mysql_fetch_array($rst, MYSQL_ASSOC);
    return $row;
}
 
function umc_get_votes_info($criteria, $mode = "entry") {
    global $prefix;
    switch($mode) {
        case "entry":
            $sql = "SELECT * FROM ".$prefix."votes WHERE entry=".intval($criteria);
            break;
        case "user":
            $sql = "SELECT * FROM ".$prefix."votes WHERE user=".intval($criteria);
            break;
        case "contest":
            $sql = "SELECT * FROM ".$prefix."votes WHERE contest=".intval($criteria);
            break;
    }
    mysql_select_db('minecraft_srvr');
    $rst = mysql_query($sql);
    $rows = array();
    while ($row = mysql_fetch_array($rst, MYSQL_ASSOC)) {
        $rows[] = $row;
    }
    return $rows;
}
 
function umc_get_field($table, $field, $sel_field, $sel_val) {
    global $prefix;
    $sql = "SELECT ".$field." FROM ".$prefix.$table." WHERE ".$sel_field."=".$sel_val;
    mysql_select_db('minecraft_srvr');
    $rst = mysql_query($sql);
    $row = mysql_fetch_array($rst, MYSQL_ASSOC);
    return $row[$field];
}
 
 
function umc_reset_tables() {
    global $prefix;
    $sql = array();
    $sql[] = "DROP TABLE IF EXISTS ".$prefix."contests";
    $sql[] = "DROP TABLE IF EXISTS ".$prefix."entries";
    $sql[] = "DROP TABLE IF EXISTS ".$prefix."votes";
    $sql[] = "CREATE TABLE ".$prefix."contests (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(50),
        description VARCHAR(1000),
        max_entries INT NOT NULL,
        voting_categories VARCHAR(1000),
        deadline DATE,
        active BOOL
        )";
    $sql[] = "CREATE TABLE ".$prefix."entries (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(50),
        contest INT NOT NULL,
        world varchar(20),
        user VARCHAR(20),
        description VARCHAR(1000),
        x INT NOT NULL,
        y INT NOT NULL,
        z INT NOT NULL,
        screenshots VARCHAR(1000)
        )";
    $sql[] = "CREATE TABLE ".$prefix."votes (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user VARCHAR(20),
        `rank` VARCHAR(50),
        contest INT NOT NULL,
        entry INT NOT NULL,
        category VARCHAR(20),
        value FLOAT,
        weight FLOAT
        )";
    mysql_select_db('minecraft_srvr');
    $rst = mysql_query($sql);
    foreach($sql as $statement) {
    // echo $statement;
        $rst = mysql_query($sql);
    }
    echo "Database Initialized.<br /><br />";
}
 
//debug functions
function umc_debug_contests() {
    global $prefix;
    $sql = "SELECT * FROM ".$prefix."contests";
    mysql_select_db('minecraft_srvr');
    $rst = mysql_query($sql);
    $ret = "Contests:<br /><br />";
    while ($row = mysql_fetch_array($rst, MYSQL_ASSOC)) {
        $ret .= print_r($row, true) . "<br /><br />";
    }
    return $ret;
}
 
function umc_debug_entries() {
    global $prefix;
    $sql = "SELECT * FROM ".$prefix."entries";
    mysql_select_db('minecraft_srvr');
    $rst = mysql_query($sql);
    $ret = "Entries:<br /><br />";
    while ($row = mysql_fetch_array($rst, MYSQL_ASSOC)) {
        $ret .= print_r($row, true) . "<br /><br />";
    }
    return $ret;
}
 
function umc_debug_votes() {
    global $prefix;
    $sql = "SELECT * FROM ".$prefix."votes";
    mysql_select_db('minecraft_srvr');
    $rst = mysql_query($sql);
    $ret = "Votes:<br /><br />";
    while ($row = mysql_fetch_array($rst, MYSQL_ASSOC)) {
        $ret .= print_r($row, true) . "<br /><br />";
    }
    return $ret;
}
 
function umc_debug() {
        echo umc_debug_contests();
        echo umc_debug_entries();
        echo umc_debug_votes();
        echo umc_debug_errors();
}
 
function umc_debug_errors() {
        global $errors;
        return "Errors: ".print_r($errors, true);
}
 
function umc_throw_error($error) {
    global $errors;
    $errors[] = $error;
}

function umc_really_strip_slashes($string) {
    return preg_replace("/\\//g",$string);
}

?>
