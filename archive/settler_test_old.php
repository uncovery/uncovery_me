<?php

/*
global $settler_questions, $UMC_DOMAIN;
$settler_questions = array(
    1 => array(
        'text'=>'How do you know what lot you are on?',
        'true'=> 2,
        'answers'=>array(
            '0'=>'There is a sign on each lot',
            '1'=>'I cannot know',
            '2'=>"I right-click somewhere with a wooden sword or find my head on the <a href=\"$UMC_DOMAIN/admin/index.php?function=create_map&world=empire\">2D map</a>."
        ),
    ),
    2 => array(
        'text'=>'What can you do if you do not like your empire lot?',
        'true'=> 1,
        'answers'=>array('0'=>'I change to a different one','1'=>'I cannot do anything','2'=>'I beg the admin to change it')
    ),
    3 => array(
        'text'=>"Which color is the thin line around the unoccupied lots on the <a href=\"$UMC_DOMAIN/admin/index.php?function=create_map&world=empire\">2D map</a>?",
        'true'=> 1,
        'answers'=>array('0'=>'Red', '1'=>'White', '2'=>'Black')
    ),
    4 => array(
        'text'=>'How do you get a wooden sword?',
        'true'=> 1,
        'answers'=>array('0'=>'Don\'t need one, got a Diamond Sword', '1'=>'There is a kit for it', '2'=>'I beg the admin')
    ),
    5 => array(
        'text'=>'How big is a protected lot in the empire?',
        'true'=> 0,
        'answers'=>array('0'=>'128x128 blocks', '1'=>'200x200 blocks', '2'=>'50x50 blocks')
    ),
    6 => array(
        'text'=>'In the empire, if want to walk from spawn (emp_q17) to emp_a1, which direction do you go?',
        'true'=> 2,
        'answers'=>array('0'=>'North-East', '1'=>'North-West', '2'=>'South-West')
    ),
    7 => array(
        'text'=>"What does the area emp_d20 in the empire world look like on the <a href=\"$UMC_DOMAIN/admin/index.php?function=create_map&world=empire\">2D map</a>?",
        'true'=> 0,
        'answers'=>array('0'=>'Water', '1'=>'Mountains', '2'=>'Snow', '3'=>'Desert')
    ),
    8 => array(
        'text'=>'How do you know where West is?',
        'true'=> 0,
        'answers'=>array('0'=>'Where the clouds & sun go', '1'=>'I do not care', '2'=>'I ask someone')
    ),
    9 => array(
        'text'=>'What do you need to do to become Architect?',
        'true'=> 1,
        'answers'=>array('0'=>'I beg an Admin', '1'=>'I build cool stuff and hope it happens', '2'=>'I take the exam.')
    ),
    10=> array(
        'text'=>'What do we ban 2 people a week for?',
        'true'=> 0,
        'answers'=>array('0'=>'Xray', '1'=>'Minimaps', '2'=>'Optifine')
    ),
    11=> array(
        'text'=>'What do you do if you have questions?',
        'true'=> 2,
        'answers'=>array(
            '0'=>'I ask in chat and get banned for being a <a href="http://slash7.com/2006/12/22/vampires/">help vampire</a>',
            '1'=>'I look on the website, and if I cannot find it, I ask in chat',
            '2'=>'I look on the website, and if I cannot find it, I ask in chat where to find the answer on the website',
        ),
    ),
    12 => array(
        'text'=>'What is the best way to get to your lot?',
        'true'=> 1,
        'answers'=>array(
            '0'=>'I try to walk there after the test as a mortal settler and die 50 times',
            '1'=>'I walk there now, before finishing this test, as an (almost) immortal guest',
            '2'=>'I beg the admin to teleport me there',
        )
    ),
);


function umc_settler_test(){
    global $UMC_USER, $settler_questions, $UMC_DOMAIN, $UMC_PATH_MC;
    die("This is out of order. Go <a href=\"http://uncovery.me/server-access/buildingrights/\">here</a>.");
    $water_lots = array('emp_m21', 'emp_l20', 'emp_l21', 'emp_o16', 'emp_n16', 'emp_o17');

    if (!$UMC_USER) {
        return "<strong>You need to be <a href=\"$UMC_DOMAIN/wp-login.php\">logged in</a></strong> to see this!\n";
    } else {
        $username = $UMC_USER['username'];
        $userlevel = $UMC_USER['userlevel'];
    }
    umc_log('settler_test', 'start', "$username started the settler test");

        // user submitted form, process it
    $out = "<div style=\"float: left; background: url('$UMC_DOMAIN/wp-content/uploads/2012/04/sprites-1.png') "
        . 'no-repeat -130px 0px; width: 32px; height: 32px; margin-right: 8px;"></div><strong style="font-size: 120%;">'
        . 'Step 6: Pick a survival or creative lot.</strong>'
        . "<br><br><form action=\"$UMC_DOMAIN/server-access/private-area-allocation-map/settler-application-result/\" method=\"post\">\n";

    $filepath = "$UMC_PATH_MC/server/bukkit/plugins/Essentials/userdata/" . strtolower($username) . '.yml';
    if (!file_exists($filepath)) {
        return "You need to logon to the server once before you can take this test!";
    }

    $empire_lots = umc_get_available_lots('empire');
    asort($empire_lots);
    $i = 0;
    $out .="<strong>10 sample free Empire lots close to spawn:</strong><br>\n";
    foreach ($empire_lots as $lot => $distance) {
        if (!in_array($lot, $water_lots)) {
            $tile = umc_user_get_lot_tile($lot);
            $dist = round(($distance)/128);
            $out .="<span style=\"text-align:center;float:left; margin:10px\">$tile<br><strong>$lot:</strong> ~$dist min walk</span>";
            $i++;
            if ($i > 9) {
                $selected[$lot] = ' selected="selected"';
                break;
            }
        }
    }
    $out .="<div style=\"clear:both;\"></div>";
    $out .= "<ul><li>Empire world lots are survival mode and have normal landscapes. They start with \"emp_\". </li>"
        . "<li>Flatlands world lots are creative mode (unlimited resources) and flat. They start with \"flat_\".</li>"
        . "<li><strong>Pick a lot: </strong><select id=\"lot_drop\" name=\"lot\">"
        . "<option value=\"false\" selected=\"selected\">Empire lots: (walk time)</option>\n";
    ksort($empire_lots);
    foreach ($empire_lots as $lot => $distance) {;
        $dist = round(($distance)/128);
        $sel_str = '';
        if (isset($selected[$lot])) {
            $sel_str = $selected[$lot];
        }
        $out .="<option value=\"$lot\"$sel_str>$lot ($dist min)</option>\n";
    }
    $flatlands_lots = umc_get_available_lots('flatlands');
    $out .= "<option value=\"false\">Flatland lots:</option>\n";
    foreach ($flatlands_lots as $lot => $distance) {
        $out .="<option value=\"$lot\">$lot</option>\n";
    }
    $out .="</select>\n</li></ul>";
    // find close-by empire lots


    $out .= "<div style=\"float: left; background: url('$UMC_DOMAIN/wp-content/uploads/2012/04/sprites-1.png') "
        . 'no-repeat -130px 0px; width: 32px; height: 32px; margin-right: 8px;"></div><strong style="font-size: 120%;">'
        . 'Step 7: Answer these questions</strong>'
        . '<br><br><p>You need to fill out this questionnaire. This is here to make sure that you read the instructions. So, don’t come and ask in-game how to find your lot. '
        . 'Do not ask what the answers to the questions are. Once you fill it out correctly, you will get a confirmation on the bottom of the page. '
        . 'You will be instantly Settler and receive access to your lot.</p>';

    foreach ($settler_questions as $q_index => $item) {
        $question = $item['text'];
        $answers = $item['answers'];
        $out .= "<p>$question<ul style=\"list-style-type:none; padding:0px;\">\n";
        foreach ($answers as $a_index => $text){
            $out .= "<li style=\"margin:0px;padding:0px;\"><input type=\"radio\" name=\"$q_index\" value=\"$a_index\" /> $text</li>\n";
        }
        $out .= "</ul></p>\n";
    }
     if ($userlevel != 'Guest' && $username != 'uncovery') {
        return $out . "<br>You are already above Guest level ($userlevel). Please go to the <a href=\"$UMC_DOMAIN/lot-manager/\">lot mananger page</a> instead.";
    }
    $out .= "<p class=\"submit\"><input type=\"submit\" name=\"wp-submit\" id=\"wp-submit\" class=\"button-primary\" "
        . "value=\"Apply\" tabindex=\"100\" /></p><br /></form>\n\n";
    echo $out;

}

function umc_settler_quiz_result() {
    global $UMC_USER, $settler_questions, $UMC_DOMAIN;
    echo "<h2>We are working on a new process for the lots. If you get an empty screen from here on, "
        . "<a href=\"$UMC_DOMAIN/support/\">please post a help ticket</a>. "
        . "We are working hard to resolve all probems but need your help reporting them.<br>";
    if (!$UMC_USER) {
        return "<strong>You need to be <a href=\"$UMC_DOMAIN/wp-login.php\">logged in</a></strong> to see this!\n";
    } else {
        $username = $UMC_USER['username'];
        $userlevel = $UMC_USER['userlevel'];
        $email = $UMC_USER['email'];
    }
    if ($userlevel != 'Guest' && $username != 'uncovery') {
        return "You are already above Guest level. Please go to the <a href=\"$UMC_DOMAIN/lot-manager/\">lot mananger page</a> instead.";
    }

    $sani_post  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
    if (isset($sani_post['lot'])) {
        // echo "{$old_users[$tmp]['lastlogin']}<br>";

        echo "Checking Questions...";
        // check the questions
        foreach ($settler_questions as $q_index => $item) {
            if (!isset($sani_post[$q_index])) {
                return "<h2>You did not answer all questions. Please click <a href=\"$UMC_DOMAIN/server-access/private-area-allocation-map/\">here</a> and try again!";
            }
            if ($sani_post[$q_index] != $item['true']) {
                return "<h2>You answered some or all of the questions wrong! </h2>Please click <a href=\"$UMC_DOMAIN/server-access/private-area-allocation-map/\">here</a> and try again!";
            }
        }
        echo "All questions answered correctly!<br>";

        // assign the lot
        $lot = $sani_post['lot'];
        if ($lot == 'false') {
            return "You have not picked a valid lot from the list. Please click <a href=\"$UMC_DOMAIN/server-access/private-area-allocation-map/\">here</a> and try again!";
        }

        if ($userlevel == 'Guest' || $userlevel == '') {
            echo "Promoting you to Settler Status...OK<Br>";
            // umc_exec_command("pex promote " . $UMC_USER['uuid']);
            //echo "Reloading permissions...OK<br>";
            //umc_exec_command('pex reload');
            umc_exec_command("ch qm u Congrats $username for becoming Settler!");
            umc_log('settler_test', 'promotion', "$username was promoted to settler");
            $headers = 'From:' . $email . "\r\n" .
                'Reply-To:' . $email . "\r\n" .
                'X-Mailer: PHP/' . phpversion();
            $subject = "[Uncovery Minecraft] Settler applicaton";
            $mailtext = "The user: $username (email: $email) was promoted to Settler and got lot $lot.\n\n";
            $check = mail('minecraft@uncovery.me', $subject, $mailtext, $headers);
            if (!$check) {
                XMPP_ERROR_trigger("The settler promotion email could not be sent!");
            }
        }
        $check = umc_lot_manager_lot_eligibility_check_and_assign($lot);
        if ($check['result'] != true) {
            return $check['text'];
        }
        // send email to admin
        echo "Congratulations! You are now Settler and own lot $lot! Go to the <a href=\"$UMC_DOMAIN/lot-manager/\">Lot manager page now</a>!";
    } else {
        umc_log('settler_test', 'failed', "$username failed the settler test");
        return "<br>You failed the application";
    }
}
*/
