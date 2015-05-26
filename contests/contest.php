<?php
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

 
remove_filter( 'the_content', 'wpautop' );
$contest_db = mysql_select_db('minecraft_srvr');

global $UMC_USER, $UMC_PATH_MC;

include_once('users.php');

if(!$UMC_USER) {
    die("You need to be logged in!");
}

function umc_contest() {
    $out = "<div id=\"contests\"><div id=\"contest_menu\">
        Show contests: <a href=\"?action=list_contests&amp;type=current\">Open</a> 
                     | <a href=\"?action=list_contests&amp;type=voting\">Now Voting</a>
                     | <a href=\"?action=list_contests&amp;type=closed\">Closed</a>
                     | <a href=\"?action=list_contests&amp;type=all\">All</a>
    <div id='holder'>";
    
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
    } else if (isset($_GET['action'])) {
        $action = $_GET['action'];
    } else {
        $action = 'list_contests';
    }
     
    
    if (!function_exists('umc_'. $action)) {
        echo "ERROR, function $action not found!";
    } else {
        $function = 'umc_'. $action;
        $out .= $function();
    }
    echo $out;
}

function umc_list_contests() {
    if ($_GET['action'] == 'list_contests') {
        $mode = $_GET['type'];
    }

    switch($mode) {
        case "closed":
            $sql = "SELECT * FROM contest_contests WHERE status='closed';";
            $contest_header = "Past Contests:";
            break;
        case "all":
            $sql = "SELECT * FROM contest_contests";
            $contest_header = "All Contests:";
            break;
        case "voting":
            $sql = "SELECT * FROM contest_contests WHERE status='voting';";
            $contest_header = "All Contests:";
            break;
        default:
            $sql = "SELECT * FROM contest_contests WHERE status='active'";
            $contest_header = "Current Contests:";
    }
    
    mysql_select_db('minecraft_srvr');
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
        $admin_html = '<div class="admin_opts">Admin options: '
            . "<a href=\"?action=delete_contest&amp;id={$row['id']}\" class=\"delete_contest\">Delete</a>";
        if ($row['status'] == 'voting') {
            $admin_html .= " | <a href=\"?action=end_contest&amp;id={$row['id']}\" class=\"toggle_contest\">End</a> ";
        } else if ($row['status'] == 'active') {
            $admin_html .= " | <a href=\"?action=vote_contest&amp;id={$row['id']}\" class=\"toggle_contest\">Open voting</a> "
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
    if (umc_is_admin()) {
            $ret .= '<div id="new_contest_form">Create a new contest:<br />'
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
    }
    return $ret;
}

function umc_create_contest() {
    if (!umc_is_admin()) {
        die("You don't have permission to do that.");
    }
    
    $voting_categories = "Looks|Realism|Fun|Usability|Innovation";
    
    $post_vals = array($title, $desc, $max_entries, $deadline, $type, $x, $y, $z);
    foreach ($post_vals as $val) {
        $$val = $val;
    }

    echo "Creating contest entry...<br>";
    global $prefix;
    $sql = "INSERT INTO ".$prefix."contests (title, description, max_entries, voting_categories, deadline, status, type, x, y, z) "
		. "VALUES ('$title', '$desc', '$max_entries', '$voting_categories', '$deadline', 'active', '$type', $x, $y, $z)";
    mysql_select_db('minecraft_srvr');
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


function umc_is_admin() {
    global $USERINFO;
    return (array_search('Owner', $USERINFO['groups']) > -1);
}

?>
