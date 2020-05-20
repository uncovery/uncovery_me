<?php
/*
 * This file is part of Uncovery Minecraft.
 * Copyright (C) 2015 uncovery.me
 *
 * Uncovery Minecraft is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of  MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/*
 * This is a simple trivia game
 */

global $UMC_SETTING, $WS_INIT;

$WS_INIT['trivia'] = array(  // the name of the plugin
    'default' => array(
        'help' => array(
            'title' => 'Trivia Quiz',  // give it a friendly title
            'short' => 'Run a public Trivia quiz.',  // a short description
            'long' => "User can answer for a small pay and get the jackpot if they win", // a long add-on to the short  description
            ),
    ),
    'new' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'For Quizmaster: Run a new trivia quiz',
            'long' => "Chose number of questions and price of answers!",
            'args' => '<questions> <price>',
        ),
        'function' => 'umc_trivia_new',
    ),
    'find' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'For Quizmaster: Shows a new quiz question',
            'long' => "The master can use this to see a new quiz question",

        ),
        'function' => 'umc_trivia_find_question',
    ),
    'ask' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'For Quizmaster: Ask a found question to users',
            'long' => "The master can use this to ask the last question he found with /trivia find",
        ),
        'function' => 'umc_trivia_ask_users',
    ),
    'check' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'For Quizmaster: get all current answers',
            'long' => "Displays all current answers to pick a correct one",
        ),
        'function' => 'umc_trivia_check',
    ),
    'solve' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'For Quizmaster: get all current answers',
            'long' => "Picks one answer as the correct one and gives out the prize",
        ),
        'function' => 'umc_trivia_solve',
    ),
    'skip' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'For Quizmaster: moves to the next question',
            'long' => "In case there are no or no correct answers, this moves to the next question.",
        ),
        'function' => 'umc_trivia_skip',
    ),
    'answer' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'For players: Answer a trivia question',
            'long' => "Answer a question asked by the quizmaster",
            'args' => '<answer>',
        ),
        'function' => 'umc_trivia_answer',
    ),
    'disabled' => false,
    'events' => false,
);

function umc_trivia_new() {
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $args = $UMC_USER['args'];

    if ($player != 'uncovery') {
        // umc_error('under maintenance');
    }

    if (isset($args[2]) && is_numeric($args[2]) && $args[2] > 1 && $args[2] <= 20) {
        $questions = $args[2];
    } else {
        umc_error("{red}You need to specify the amount of questions (2-20) to be asked before someone wins. ");
    }

    if (isset($args[3]) && is_numeric($args[3]) && $args[3] >= 10 && $args[3] <= 100) {
        $price = $args[3];
    } else {
        umc_error("{red}You need to specify the price users pay for each answer (10-100 Uncs).");
    }

    // check if there is a quiz running
    $quiz_arr = umc_trivia_get_current_quiz();
    umc_header("Trivia Quiz");
    if (!$quiz_arr) {
        umc_echo("Starting a new quiz with $questions questions and $price Uncs per question!");
    } else {
        $master = $quiz_arr['master'];
        if ($player != $master) {
            if (!in_array($master, $UMC_USER['online_players'])) {
                umc_echo("$master has been running a quiz, but he is not online anymore, so closing it now...");
                umc_trivia_close_quiz($quiz_arr);
            } else {
                umc_error("$master is running a quiz already, it has to stop first!");
            }
        } else if ($player == $master) {
            umc_error("A quiz is already running, use /trivia find");
        }
    }

    // create the quiz
    $sql = "INSERT INTO minecraft_quiz.quizzes (`master`, `start`, `questions`, `price`) VALUES ('$player', NOW(),'$questions','$price');";
    umc_mysql_execute_query($sql);
    $id = umc_mysql_insert_id();
    // announce quiz and send first question
    umc_footer();

    umc_mod_broadcast("&3[Trivia]&f &4Trivia Quiz No $id started: &4$questions questions, $price Uncs&f per given answer&4");
    umc_mod_broadcast("&3[Trivia]&f Quizmaster today is &4$player&f!");
    umc_mod_broadcast("&3[Trivia]&f There is a new trivia quiz starting, do &2/ch join o&f to see it!");
    umc_trivia_find_question();
    umc_log('trivia', 'start', "$player started a trivia with $questions questions for $price uncs");
}

function umc_trivia_find_question() {
    // get the current question number
    $quiz_arr = umc_trivia_get_current_quiz();
    if (!$quiz_arr) {
        umc_error("You need to start a quiz before you can ask a question!");
    } else if ($quiz_arr['status'] == 'asked') {
        umc_error("You have an un-answered question open. Close that question first please with {green}/trivia solve{white}");
    }
    $quiz_id = $quiz_arr['id'];

    $question_arr = umc_trivia_pick_question($quiz_id);
    umc_header("Trivia No. $quiz_id: Question proposal");
    umc_echo("{yellow}Question {red}No {$question_arr['question_no']}{yellow}:{white} {$question_arr['question']}");
    umc_echo("Type {green}/trivia ask{white} to ask this or {red}/trivia find{white} to get a different one");
    umc_footer();
}

function umc_trivia_ask_users() {
    global $UMC_USER;
    $player = $UMC_USER['username'];

    $quiz_arr = umc_trivia_get_current_quiz();
    $master = $quiz_arr['master'];
    if (!$quiz_arr) {
        umc_error("There is no active quiz to ask questions for. Please start one first.");
    } else if ($player != $master) {
        umc_error("Someone is running a quiz already, it has to stop first!");
    }
    // get the current question
    $quiz_id = $quiz_arr['id'];
    $price = $quiz_arr['price'];
    if (!$quiz_arr['question_no']) {
        umc_error("You have to find a suitable question with {green}/trivia find{white} first!");
    }
    $question = $quiz_arr['question'];
    $question_id = $quiz_arr['question_id'];

    $upd_sql = "UPDATE minecraft_quiz.quiz_questions SET status='asked'
        WHERE quiz_id=$quiz_id AND status='preparing';";
    umc_mysql_execute_query($upd_sql);

    $question_no = $quiz_arr['question_no'];

    // update counter
    $question_sql = "UPDATE minecraft_quiz.catalogue SET skipped=skipped+1 WHERE question_id = $question_id";
    umc_mysql_execute_query($question_sql);

    umc_mod_broadcast("&3[Trivia]&f Quiz No $quiz_id Question $question_no:&4");
    umc_mod_broadcast("&3[Trivia]&f Question: $question&4");
    umc_mod_broadcast("&3[Trivia]&f Answer for $price Uncs with &3/trivia answer <text>&4");
    umc_log('trivia', 'ask', "$player asked trivia $quiz_id question id {$quiz_arr['question_id']}");
}

function umc_trivia_answer() {
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $args = $UMC_USER['args'];

    $quiz_arr = umc_trivia_get_current_quiz();
    $quiz_id = $quiz_arr['id'];
    $question_no = $quiz_arr['question_no'];
    $master = $quiz_arr['master'];
    $balance = umc_money_check($player);
    $price = $quiz_arr['price'];
    if (!$quiz_arr) {
        umc_error("There is no active quiz to answer questions for. Please start one first.");
    } else if ($player == $master) {
        umc_error("You cannot answer your own question!");
    } else if (!$quiz_arr['question_no']) {
        umc_error("There is no question asked for the current trivia yet. Please wait!");
    } else if ($quiz_arr['status'] != 'asked') {
        umc_error("The last question was closed already. Please wait for the next one to be asked!");
    } else if (!isset($args[2])) {
        umc_error("You have to provide an answer!");
    } else if (!$balance || ($balance < $price)) {
        umc_error("Answering costs $price Uncs, but you do not have that much money!");
    } else if (count($quiz_arr['users']) > 0  && in_array($player, $quiz_arr['users'])) {
        umc_error("You have already answered that question!");
    }
    $answer_raw = '';
    for ($i=2; $i<count($args); $i++) {
        $answer_raw .= " " . $args[$i];
    }
    $answer = trim($answer_raw);
    umc_header("Trivia Quiz No.$quiz_id Question No.$question_no");
    umc_echo("You answer: $answer");
    umc_echo("Thanks for answering! Your account was debited $price Uncs!");
    umc_footer(true);
    // register answer
    umc_money($player, false, $price);
    $answer_str = umc_mysql_real_escape_string($answer);
    $sql = "INSERT INTO minecraft_quiz.quiz_answers (quiz_id, question_id, answer_text, username, time, result)
        VALUES ({$quiz_arr['id']}, {$quiz_arr['question_id']}, $answer_str, '$player', NOW(), 'wrong');";
    umc_mysql_execute_query($sql);

    // message the quizmaster
    umc_exec_command("----------------- New Trivia Answer -----------------", 'toPlayer', $master);
    umc_exec_command("\"$answer\"", 'toPlayer', $master);
    umc_exec_command("To display all current answers, use /trivia check", 'toPlayer', $master);
    umc_exec_command("--------------------------------------------------", 'toPlayer', $master);
    umc_log('trivia', 'answer', "$player answered trivia $quiz_id question id {$quiz_arr['question_id']}");
}

function umc_trivia_check(){
    global $UMC_USER;
    $player = $UMC_USER['username'];
    // $args = $UMC_USER['args'];

    $quiz_arr = umc_trivia_get_current_quiz();
    $master = $quiz_arr['master'];
    if (!$quiz_arr) {
        umc_error("There is no active quiz to get answers for. Please start one first.");
    } else if ($player != $master) {
        umc_error("$master is running a quiz already, it has to stop first!");
    } else if ($quiz_arr['status'] != 'asked') {
        umc_error("You need to ask the next question before you can get the answers.");
    } else if (!isset($quiz_arr['answers'])) {
        umc_error("Nobody has answered this question so far");
    }
    umc_header("Trivia Answers Status", true);
    umc_echo("{green}Question:{white} " . $quiz_arr['question']);
    umc_echo("{green}Correct Answer:{white} " . $quiz_arr['answer']);
    umc_footer(true);
    foreach ($quiz_arr['answers'] as $id => $answer) {
        umc_echo("{green}$id:{white} " . trim($answer));
    }
    umc_footer(true);
    umc_echo("Pick a correct answer with {green}/trivia solve <No>{white}");
    umc_echo("In case there are 2 correct answers, pick the one higher up.");
    umc_echo("Use {green}/trivia skip{white} in case you did not get any good answers");
    umc_footer(true);
}

function umc_trivia_skip() {
    global $UMC_USER;
    $player = $UMC_USER['username'];

    $quiz_arr = umc_trivia_get_current_quiz();
    $master = $quiz_arr['master'];
    $answer = $quiz_arr['answer'];
    $quiz_id = $quiz_arr['id'];
    $question_no = $quiz_arr['question_no'];
    if (!$quiz_arr) {
        umc_error("There is no active quiz to get answers for. Please start one first.");
    } else if ($player != $master) {
        umc_error("Someone is running a quiz already, it has to stop first!");
    } else if ($quiz_arr['status'] != 'asked') {
        umc_error("You need to ask the next question before you deal with the answers.");
    }

    // set all questions wrong
    $id = $quiz_arr['id'];
    $update_sql = "UPDATE minecraft_quiz.quiz_answers SET result='wrong' WHERE quiz_id=$id and question_id={$quiz_arr['question_id']};";
    umc_mysql_execute_query($update_sql);

    $close_sql = "UPDATE minecraft_quiz.quiz_questions SET status='solved' WHERE quiz_id={$quiz_arr['id']} AND question_id={$quiz_arr['question_id']};";
    umc_mysql_execute_query($close_sql);
    umc_mod_broadcast("&3[Trivia]&f &4 Quiz No $quiz_id Question $question_no&4");
    umc_mod_broadcast("&3[Trivia]&f &2No correct answers, this question has been skipped!&4");
    umc_mod_broadcast("&3[Trivia]&f &2Correct answer would have been $answer&4");
    if ($quiz_arr['question_no'] >= $quiz_arr['questions']) {
        umc_trivia_close_quiz();
    } else {
        umc_mod_broadcast("&3[Trivia]&f Please stand by for the next question to be picked and annunced.");
        umc_trivia_find_question();
    }
}

function umc_trivia_solve() {
    global $UMC_USER;
    $player = $UMC_USER['username'];
    $args = $UMC_USER['args'];

    $quiz_arr = umc_trivia_get_current_quiz();
    $quiz_id = $quiz_arr['id'];
    $question_no = $quiz_arr['question_no'];
    $master = $quiz_arr['master'];
    if (!$quiz_arr) {
        umc_error("There is no active quiz to get answers for. Please start one first.");
    } else if ($player != $master) {
        umc_error("Someone is running a quiz already, it has to stop first!");
    } else if ($quiz_arr['status'] != 'asked') {
        umc_error("You need to ask the next question now!");
    } else if (!isset($args[2], $quiz_arr['answers'][$args[2]])) {
        umc_error("You need to enter an existing answer id. Use /trivia check!");
    }
    $answer_id = $args[2];

    $update_sql = "UPDATE minecraft_quiz.quiz_answers SET result='right' WHERE answer_id=$answer_id;";
    umc_mysql_execute_query($update_sql);
    $close_sql = "UPDATE minecraft_quiz.quiz_questions SET status='solved' WHERE quiz_id={$quiz_arr['id']} AND question_id={$quiz_arr['question_id']};";
    //umc_echo($close_aql);
    umc_mysql_execute_query($close_sql);

    // get answer user
    $sql = "SELECT * FROM minecraft_quiz.quiz_answers wHERE answer_id=$answer_id;";
    $D = umc_mysql_fetch_all($sql);
    $row = $D[0];
    $username = $row['username'];
    $answer = $row['answer_text'];
    umc_mod_broadcast("&3[Trivia]&f Quiz No $quiz_id Question $question_no");
    umc_mod_broadcast("&3[Trivia]&f The correct answer was: {$quiz_arr['answer']}&4");
    umc_mod_broadcast("&3[Trivia]&f User &6$username&f answered correctly with &2$answer&4");

    if ($quiz_arr['question_no'] >= $quiz_arr['questions']) {
        umc_trivia_close_quiz();
    } else {
        umc_mod_broadcast("&3[Trivia]&f Please stand by for the next question to be picked and annunced.");
        umc_trivia_find_question();
    }
}

function umc_trivia_get_current_quiz() {
        // check if there is a quiz running
    $sql = "SELECT * FROM minecraft_quiz.quizzes WHERE end IS NULL LIMIT 1;";
    $D = umc_mysql_fetch_all($sql);
    if (count($D) == 1) {
        // check if master is still online
        $row = $D[0];
        $quiz_arr = array(
            'id' => $row['quiz_id'],
            'master' => $row['master'],
            'questions' => $row['questions'],
            'price' => $row['price'],
            'users' => array(),
            'status' => false,
        );
        // get current question
        $question_sql = "SELECT quiz_questions.question_id, question, status, question_no, answer FROM minecraft_quiz.quiz_questions
            LEFT JOIN minecraft_quiz.catalogue ON quiz_questions.question_id=catalogue.question_id
            WHERE quiz_id={$row['quiz_id']} ORDER BY question_no DESC LIMIT 1";
        $Q = umc_mysql_fetch_all($question_sql);
        if (count($Q) > 0) {
            $question_row = $Q[0];
            $quiz_arr['question_no'] = $question_row['question_no'];
            $quiz_arr['status'] = $question_row['status'];
            $quiz_arr['question'] = $question_row['question'];
            $quiz_arr['answer'] = $question_row['answer'];
            $quiz_arr['question_id'] = $question_row['question_id'];
        } else {
            $quiz_arr['question_no'] = false;
        }

        // get current answers only if there was a question
        if ($quiz_arr['question_no']) {
            $answers_sql = "SELECT * FROM minecraft_quiz.quiz_answers
                    WHERE quiz_id={$quiz_arr['id']} AND question_id={$quiz_arr['question_id']} ORDER BY answer_id;";
            $A = umc_mysql_fetch_all($answers_sql);
            if (count($A) > 0) {
                foreach ($A as $answers_row) {
                    $id = $answers_row['answer_id'];
                    $quiz_arr['answers'][$id] = $answers_row['answer_text'];
                    $quiz_arr['users'][] = $answers_row['username'];
                }
            }
        }
        // check if master is still online
        return $quiz_arr;
        // check age of quiz
    } else {
        return false;
    }
}

function umc_trivia_call_question() {
    $luck = mt_rand(1, 7156);
    $question_sql = "SELECT * FROM minecraft_quiz.catalogue
        WHERE asked=0 AND category NOT IN ('Hard','History','Business')
	LIMIT $luck, 1;";
    $D = umc_mysql_fetch_all($question_sql);
    if (count($D) == 0) {
        $D = umc_trivia_call_question();
    }
    return $D;
}

/*
 * Select a random question from the catalogue
 */
function umc_trivia_pick_question($quiz_id) {
    // Get a new question

    $Q = umc_trivia_call_question();
    $question_row = $Q[0];
    $question_arr = array(
        'id' => $question_row['question_id'],
        'question' => $question_row['question'],
        'category' => $question_row['category'],
    );

    // find the latest question in this quiz
    $sql = "SELECT question_no, status, question_id FROM minecraft_quiz.quiz_questions
        WHERE quiz_id=$quiz_id
	ORDER BY question_no DESC
	LIMIT 1";
    $D = umc_mysql_fetch_all($sql);
    $num_rows = count($D);

    if ($num_rows == 1) {
        $row = $D[0];
        $status = $row['status'];
        $question_id = $row['question_id'];
        $next_question_no = $row['question_no'] + 1;
        $question_no = $row['question_no'];
    } else {
        $next_question_no = 1;
    }

    // insert new question if first or the last one is done
    if ($num_rows == 0 || ($status == 'solved')) {
        // insert new question
        $ins_sql = "INSERT INTO minecraft_quiz.quiz_questions (`question_id`, `question_no`, `quiz_id`, `status`)
            VALUES ({$question_arr['id']}, $next_question_no, $quiz_id, 'preparing');";
        umc_mysql_execute_query($ins_sql);
    } else {
        // update existing question
        $upd_sql = "UPDATE minecraft_quiz.quiz_questions SET question_id={$question_arr['id']}
            WHERE quiz_id=$quiz_id AND question_no=$question_no;";
        umc_mysql_execute_query($upd_sql);
        // mark question as skipped
        $question_sql = "UPDATE minecraft_quiz.catalogue SET skipped=skipped+1 WHERE question_id=$question_id";
        umc_mysql_execute_query($question_sql);
    }
    $question_arr['question_no'] = $next_question_no;
    return $question_arr;
}

function umc_trivia_close_quiz($quiz_arr = false) {
    global $UMC_USER;
    $player = $UMC_USER['username'];
    if (!$quiz_arr) {
        $quiz_arr = umc_trivia_get_current_quiz();
    }
    $quiz_id = $quiz_arr['id'];

    // get best user and points
    $sql = "SELECT username, count(result) as points FROM minecraft_quiz.quiz_answers WHERE quiz_id=$quiz_id AND result='right' GROUP BY username ORDER BY count(result) DESC;";
    $D = umc_mysql_fetch_all($sql);
    $data = array();

    $prev_points = 0;
    foreach ($D as $row) {
        $username = $row['username'];
        $points = $row['points'];

        if ($points < $prev_points) {
            break;
        }

        $data[] = $username; // array of the winner(s)
        $prev_points = $points;
    }
    $winner_count = count($data);
    if ($winner_count > 0) {
        $winner_str = implode(", ", $data);

        // how many answers have been given?
        $answer_sql = "SELECT count(answer_id) as counter FROM minecraft_quiz.quiz_answers WHERE quiz_id=$quiz_id;";
        $A = umc_mysql_fetch_all($answer_sql);
        $answer_row =  $A[0];
        $answer_count = $answer_row['counter'];

        $prize = ($answer_count * $quiz_arr['price']) / $winner_count;
        umc_mod_broadcast("&3[Trivia]&f We have $winner_count winner(s) who each get $prize!");
        umc_mod_broadcast("&3[Trivia]&f Winner(s): $winner_str");
        umc_money(false, $row['username'], $prize);
        $sql = "UPDATE minecraft_quiz.quizzes SET end=NOW(), winner='$winner_str', points=$prev_points WHERE quiz_id=$quiz_id;";
    } else {
         $sql = "UPDATE minecraft_quiz.quizzes SET end=NOW(), winner='', points=0 WHERE quiz_id=$quiz_id;";
    }
    umc_mysql_execute_query($sql);
    umc_mod_broadcast("&3[Trivia]&f Thanks for participating. Create your own quiz with /trivia new!");
    umc_log('trivia', 'close', "$player closed trivia $quiz_id and gave $prize to $winner_str each");
}

function umc_trivia_webstats() {
    $out = '<table>';

    $quiz_sql = "SELECT * FROM minecraft_quiz.quizzes WHERE end <> '' ORDER BY start DESC;";
    $D = umc_mysql_fetch_all($quiz_sql);

    foreach ($D as $quiz_row) {
        $quiz_id = $quiz_row['quiz_id'];
        $master = $quiz_row['master'];
        $quiz_start = $quiz_row['start'];
        $quiz_end = $quiz_row['end'];
        $winner = $quiz_row['winner'];
        $points = $quiz_row['points'];
        $prize = $quiz_row['points'];

        $out .= "<tr style=\"background-color:#99CCFF;\"><td>Quiz No.$quiz_id, Quizmaster: $master</td><td>Start: $quiz_start</td></tr>";
        $out .= "<tr><td colspan=2>Winner: $winner with $points points won $prize Uncs each</tr>";

        $datetime = umc_datetime($quiz_start);
        $seconds = umc_timer_raw_diff($datetime);
        $days = $seconds / 60 / 60 / 24;
        if ($days > 3) {
            continue;
        }
        $question_sql = "SELECT question_no, question, answer, quiz_questions.question_id FROM minecraft_quiz.quiz_questions
            LEFT JOIN minecraft_quiz.catalogue ON quiz_questions.question_id = catalogue.question_id
            WHERE quiz_id = $quiz_id ORDER BY question_no;";
        $Q = umc_mysql_fetch_all($question_sql);
        foreach ($Q as $question_row) {
            $question_no = $question_row['question_no'];
            $question_id = $question_row['question_id'];
            $question = $question_row['question'];
            $answer = $question_row['answer'];
            $out .= "<tr style=\"font-size:70%; background-color:#99FFCC;\"><td style=\"padding-left:40px\">Q. No.$question_no: $question</td><td>A.: $answer</td></tr>";
            $answer_sql = "SELECT * FROM minecraft_quiz.quiz_answers WHERE quiz_id=$quiz_id AND question_id=$question_id ORDER BY answer_id;";
            $A = umc_mysql_fetch_all($answer_sql);
            $out .= "<tr style=\"font-size:70%;\"><td style=\"padding-left:80px\" colspan=2>";
            foreach ($A as $answer_row) {
                $answer_id = $answer_row['answer_id'];
                $user_answer = $answer_row['answer_text'];
                $username = $answer_row['username'];
                $result = $answer_row['result'];
                $style = "style=\"margin-right:10px;\"";
                if ($result == 'right') {
                    $style = " style=\"color:green; margin-right:10px;\"";
                }
                $out .= "<span $style>$answer_id ($username): $user_answer</span>";

            }
            $out .= "</td></tr>";
        }
    }
    $out .= "</table>";
    return $out;
}
