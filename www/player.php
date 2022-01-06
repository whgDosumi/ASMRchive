<?php

header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

function replace_timestamps($text) {
    $regex = "/(?:([0-5]?[0-9]):)?([0-5]?[0-9]):([0-5][0-9])/";
    preg_match_all($regex, $text, $matches);
    $replacements = [];
    foreach($matches[0] as $match) {
        $times = explode(':',$match);
        if (count($times) === 2) {
            $seconds = ((int)$times[0] * 60) + $times[1];
        }
        if (count($times) === 3) {
            $seconds = ((int)$times[0] * 3600) + ((int)$times[1] * 60) + ((int)$times[2]);
        }
        $replacements[$match] = '<span style="cursor: pointer;text-decoration: underline;color:blue;" onclick="set_time(' . strval($seconds) . ')">' . $match . '</span>';
    }
    foreach($matches[0] as $match) {
        $text = str_replace($match, $replacements[$match], $text);
    }
    return($text);
}
function slugify($text, string $divider = '-')
{
  $text = preg_replace('~[^\pL\d]+~u', $divider, $text);
  $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
  $text = preg_replace('~[^-\w]+~', '', $text);
  $text = trim($text, $divider);
  $text = preg_replace('~-+~', $divider, $text);
  $text = strtolower($text);
  if (empty($text)) {
    return 'n-a';
  }
  return $text;
}



function test_input($data) {
  $data = trim($data);
  $data = stripslashes($data);
  $data = htmlspecialchars($data);
  return $data;
}

class Comment {
    public $path;
    public $text;
    public $user_name;
    public $date;
    public $display_text;
    public function __construct($path) {
        $this->path = $path;
        $arr = json_decode(file_get_contents($path), true);
        $this->text = $arr["text"];
        $this->display_text = replace_timestamps($this->text);
        $this->user_name = $arr["user_name"];
        $this->date = $arr["date"];
    }

    public function save() {
        $arr = array("user_name"=>$this->user_name, "text"=>$this->text, "date"=>$this->date);
        file_put_contents($this->path,json_encode($arr));
    }

    public function delete() {
        if (!is_dir("comments_bak")) {
            mkdir("comments_bak");
        }
        rename($this->path, "comments_bak/" . slugify($this->date) . ".json");
    }

    public function display_comment() {
        echo '
        <div class="comment">
            <form action="' . htmlspecialchars($_SERVER["PHP_SELF"]) . '" method="post">
                <input type="submit" value="Delete" class="delete_button" onclick="return confirm(\'Are you sure you want to delete comment by ' . $this->user_name . '?\');">
                <p class="comment_name">' . $this->user_name . '<span style="font-size: 15px;">&nbsp;&nbsp;at&nbsp;' . $this->date . '</span></p>
                <p class="comment_text">' . nl2br($this->display_text) . '</p>
                <input type="hidden" name="timestamp" class="timestamp">
                <input type="hidden" name="delete" value=' . $this->path . '>
            </form>
        </div>';
    }

}

class New_Comment extends Comment {
    public function __construct($path, $user_name, $text) { #in case we want to define a new one without a file
        $this->text = $text;
        $this->user_name = $user_name;
        $this->path = $path;
        $this->date = date('Y/m/d H:i:s', time());
        $this->display_text = replace_timestamps($this->text);
    }
}

function load_comments() {
    $comment_list = array();
    if (is_dir("comments")) {
        $scan = scandir("comments");
        foreach($scan as $file) {
            if ($file != "." and $file != ".."){
                $comment_list[$file] = new Comment("comments/" . $file);
            }
        }
    }
    return($comment_list);
}
$comment_list = load_comments();


$start_timestamp = 0;
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['delete'])) {
        foreach($comment_list as $comment) {
            if ($comment->path == $_POST['delete']) {
                $comment->delete();
            }
        }
    }
    else if (isset($_POST['user_name']) and isset($_POST['text']) ){
        $user_name = test_input($_POST["user_name"]);
        $text = test_input($_POST["text"]);
        
        if (!is_dir("comments")) {
            mkdir("comments");
        }
        $scan = scandir("./comments");
        $i = 1;
        $path = "comments/comment_" . strval($i) . ".json";
        while (is_file($path)) {
            $i = $i + 1;
            $path = "./comments/comment_" . strval($i) . ".json";
        }
        $new_comment = new New_Comment($path, $user_name, $text);
        $new_comment->save();
    }
    if (isset($_POST['timestamp'])) {
        $start_timestamp = $_POST['timestamp'];
    }
}

#sorts the comments by date entered.
function cmp($a, $b) {
    return strcmp($a->date, $b->date);
}
usort($comment_list, "cmp");



class Video
{
    public $thumbnail;
    public $path;
    public $title;
    public $upload_date;
    public $pretty_date;
    public $asmr_file;
    public $description;
    public function __construct($path)
    {
        $this->path = $path;
        if (is_file($path . '/asmr.webp')) {
            $this->thumbnail = $path . '/asmr.webp';
        } elseif (is_file($path . '/asmr.jpg')) {
            $this->thumbnail = $path . '/asmr.jpg';
        } elseif (is_file($path . '/asmr.jpeg')) {
            $this->thumbnail = $path . '/asmr.jpeg';
        } else {
            $this->thumbnail = '/images/default_thumbnail.png';
        }

        $doc = fopen($path . '/title.txt', 'r');
        $this->title = fread($doc, filesize($path . '/title.txt'));

        $doc = fopen($path . '/upload_date.txt', 'r');
        $this->upload_date = fread($doc, filesize($path . '/upload_date.txt'));
        $this->pretty_date = date('m-d-Y', strtotime($this->upload_date));
        if (is_file($path . "/asmr.webm")) {
            $this->asmr_file = $path . "/asmr.webm";
        } elseif (is_file($path . '/asmr.aac')) {
            $this->asmr_file = $path . "/asmr.aac";
        } elseif (is_file($path . "/asmr.mp3")) {
            $this->asmr_file = $path . "/asmr.mp3";
        } else {
            $this->asmr_file = $path . "/asmr.m4a";
        }
        $this->description = file_get_contents("./asmr.description");

    }
    public function display_row()
    {
        echo '<tr><td><a href="' . $this->thumbnail . '"><img class="thumb" src=' . $this->thumbnail . '></a></td>
        <td><p class="title">' . $this->title . '</td>
        <td class="date"><p>' . $this->pretty_date . '</td>
        <td><a href="' . $this->path . '/player.php"><img src="/images/playbutton.png" class="playbutton"></a></td>
        </tr>';
    }
}
$me = new Video(".")
?>



<!DOCTYPE html>
<html>

<head>
    <meta name="viewport" content="width=1000">
    <title>ASMRchive - Player</title>
    <link rel="stylesheet" href="/player.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>

<body>
    <a onclick="go_to_parent();"><div id="backbutton"><img id="backimage" src="/images/back.png"></div></a>
    <div id="player">
        <img src="<?php echo $me->thumbnail ?>" id="thumbnail">
        <p class="title"><?php echo $me->title; ?></p>
        <p class="description"><?php echo $me->description;?></p>
        <div class="controls">
            <audio id="asmr" controls autoplay onplay="play_update()" onpause="pause_update()">
                <source src="<?php echo $me->asmr_file; ?>" type="audio/mpeg">
                <source src="<?php echo $me->asmr_file; ?>" type="audio/ogg">
                <source src="<?php echo $me->asmr_file; ?>" type="audio/wav">
                <p>Your browser does not support the audio tag.</p>
            </audio>
            <div class="button" onclick="skipBack(60)">
                <p> -1m </p>
            </div>
            <div class="button" onclick="skipBack(30)">
                <p> -30s </p>
            </div>
            <div class="button" onclick="skipBack(10)">
                <p> -10s </p>
            </div>
            <div class="separator">
            </div>
            <div class="button" onclick="skipForward(10)">
                <p> +10s </p>
            </div>
            <div class="button" onclick="skipForward(30)">
                <p> +30s </p>
            </div>
            <div class="button" onclick="skipForward(60)">
                <p> +1m </p>
            </div>
            <div class="playbutton" onclick="play_pause()">
                <img id="play" src="/images/pause.png">
            </div>
        </div>
    </div>
    <div id="comments">
        <?php
            foreach(array_reverse($comment_list) as $comment) {
                $comment->display_comment();
            }
        ?>
        <div id="comment_form">
            <h4 style="padding: 0;margin:0;">New Comment:</h4>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method="post" id="comment_form">
                Name:<br> <input id="name_box" type="text" name="user_name" maxlength="40" required><input type="submit" value="Post" id="post_button"><br>
                Message: <br><textarea id="message_box" type="text" name="text" required></textarea>
                <p onclick="append_timestamp()" id="comment_timestamp">Current time: 00:00</p>
                <div class="button" style="user-select: none;touch-action: manipulation;float: right;width: 10%; height: auto;vertical-align: center;font-size: 25px;" onclick="skipForward(1)">
                    <p> +1s </p>
                </div>
                <div class="button" style="user-select: none;touch-action: manipulation;float: right;width: 10%; height: auto;vertical-align: center;font-size: 25px;" onclick="skipBack(1)">
                    <p> -1s </p>
                </div>
                <input type="hidden" name="timestamp" class="timestamp">
            </form>
        </div>
    </div>
    <script>
        var audio = document.getElementById("asmr");
        audio.currentTime = <?php echo $start_timestamp; ?>;
        button = document.getElementById("play");

        function set_time(seconds) {
            audio.currentTime = seconds;
        }
        function update_timestamp() {
            var timestamps = document.getElementsByClassName("timestamp");
            for (element of timestamps) {
                element.value = document.getElementById("asmr").currentTime;
            }
        }
        function pause_update() {
            document.getElementById("play").src = "/images/play.png";
            console.log("paused");
        }
        function play_update() {
            document.getElementById("play").src = "/images/pause.png";
            console.log("play");
        }
        function skipForward(time) {
            audio.currentTime += time;
        }
        function skipBack(time) {
            audio.currentTime -= time;
        }
        function play_audio() {
            document.getElementById("play").src = "/images/pause.png";
            document.getElementById("asmr").play();
        }
        function update_button() {
            if (audio.paused) {
                button.src = "/images/play.png";
            } else {
                button.src = "/images/pause.png";
            }
        }
        function play_pause() {
            if (audio.paused) {
                button.src = "/images/pause.png";
                audio.play();
            } else {
                button.src = "/images/play.png";
                audio.pause();
            }
        }
        function go_to_parent() {
            var loc = window.location.href;
            var i = loc.lastIndexOf("/");
            loc = loc.slice(0,i);
            var i = loc.lastIndexOf("/");
            loc = loc.slice(0,i);
            loc = loc + "/index.php";
            window.location = loc;
        }

        function seconds_to_hms(seconds) {
            var date = new Date(null);
            date.setSeconds(seconds);

            var spt = date.toISOString().substr(11, 8).split(":");
            if (spt[0] == "00") {
                return (spt[1] + ":" + spt[2]);
            }
            return (date.toISOString().substr(11, 8));
        }

        function update_ts_comment() {
            document.getElementById("comment_timestamp").innerHTML = 'Current time: ' + seconds_to_hms(document.getElementById("asmr").currentTime);
        }
        setInterval(update_ts_comment, 100);

        function append_timestamp() {
            var timestamp = seconds_to_hms(document.getElementById("asmr").currentTime)
            document.getElementById("message_box").value = document.getElementById("message_box").value + timestamp;
        }

        update_button();
        
        setInterval(update_timestamp, 10);
        if ( window.history.replaceState ) {
            window.history.replaceState( null, null, window.location.href );
        }
    </script>
</body>
