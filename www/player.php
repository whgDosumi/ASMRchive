<?php

header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

include "/var/www/html/library.php";

# Comment object class
class Comment {
    public $path;
    public $text;
    public $user_name;
    public $date;
    public $display_text;

    # For constructing a comment that isn't already saved to disk,
    # see below class New_Comment
    # $path is path to comment file.
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

# Extension of above comment class that is used when a new one is created
# Used for defining a comment without having it already saved to disk
class New_Comment extends Comment {
    public function __construct($path, $user_name, $text) { #in case we want to define a new one without a file
        $this->text = $text;
        $this->user_name = $user_name;
        $this->path = $path;
        $this->date = date('Y/m/d H:i:s', time());
        $this->display_text = replace_timestamps($this->text);
    }
}

# function reads all comments in comments dir,
# spawns new comment objects, and returns list of them. 
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
        umask(0);
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
        umask();
    }
    if (isset($_POST['timestamp'])) {
        $start_timestamp = $_POST['timestamp'];
    }
}
$comment_list = load_comments();
#sorts the comments by date entered.
function cmp($a, $b) {
    return strcmp($a->date, $b->date);
}
usort($comment_list, "cmp");

$me = new Video(".")
?>



<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate"/>
    <meta http-equiv="Pragma" content="no-cache"/>
    <meta http-equiv="Expires" content="0"/>
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
                <?php
                    foreach($me->asmr_formats as &$value) {
                        echo "<source src=\"" . $value . "\">";
                    }
                ?>
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
            <p id="prev_tstp"></p>
            <img src="/images/boost.png" id="boostbutton"></img>
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
        function amplifyMedia(mediaElem, multiplier) {
        var context = new (window.AudioContext || window.webkitAudioContext),
            result = {
                context: context,
                source: context.createMediaElementSource(mediaElem),
                gain: context.createGain(),
                media: mediaElem,
                amplify: function(multiplier) { result.gain.gain.value = multiplier; },
                getAmpLevel: function() { return result.gain.gain.value; }
            };
        result.source.connect(result.gain);
        result.gain.connect(context.destination);
        result.amplify(multiplier);
        return result;
        }

        // source: https://www.w3schools.com/js/js_cookies.asp
        function set_cookie(cname, cvalue, exdays) {
            const d = new Date();
            d.setTime(d.getTime() + (exdays*24*60*60*1000));
            let expires = "expires="+ d.toUTCString();
            document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
        }
        function get_cookie(cname) {
            let name = cname + "=";
            let decodedCookie = decodeURIComponent(document.cookie);
            let ca = decodedCookie.split(';');
            for(let i = 0; i <ca.length; i++) {
                let c = ca[i];
                while (c.charAt(0) == ' ') {
                c = c.substring(1);
                }
                if (c.indexOf(name) == 0) {
                return c.substring(name.length, c.length);
                }
            }
            return "";
        }

        function iOS() { 
            return [
                'iPad Simulator',
                'iPhone Simulator',
                'iPod Simulator',
                'iPad',
                'iPhone',
                'iPod'
            ].includes(navigator.platform)
            || (navigator.userAgent.includes("Mac") && "ontouchend" in document)
        }
        function url_exists(url)
        {
            var http = new XMLHttpRequest();
            http.open('HEAD', url, false);
            http.send();
            return http.status!=404;
        }
        if (!url_exists("asmr.mp3")) {
            if (iOS()) {
                alert("Conversion not complete, if this doesn't work wait 15 minutes and try again.")
            }
        }
        var audio = document.getElementById("asmr");
        audio.currentTime = <?php echo $start_timestamp; ?>;
        button = document.getElementById("play");

        
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

        function set_time(seconds) {
            audio.currentTime = seconds;
            if (audio.paused) {
                play_pause();
            }
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

        let prev_tstp = get_cookie(window.location.pathname + "-prev_tstp");
        if (prev_tstp != "") {
            let doctstp = document.getElementById("prev_tstp");
            doctstp.innerHTML = "<span id=\"doctstpspan\">Previous Time: " + seconds_to_hms(prev_tstp) + "</span>";
            document.getElementById("doctstpspan").addEventListener("click", go_to_cookie);
        }

        function go_to_cookie() {
            set_time(parseInt(prev_tstp));
        }
        
        update_button();
        
        function update_cookie() {
            ts = String(audio.currentTime);
            // prevent short sends from overwriting your last spot
            if (audio.currentTime > 10) { 
                set_cookie(String(window.location.pathname) + "-prev_tstp", ts, 14);
            }
        }

        var cookie_int = setInterval(update_cookie, 1000);

        boostbutton = document.getElementById("boostbutton");
        button_state = false;
        result = false;
        boostbutton.addEventListener("click", function() {
            if (result == false) {
                result = amplifyMedia(audio, 1);
            }
            if (button_state) {
                result.amplify(1);
                button_state = false;
                boostbutton.style["background-color"] = "azure";
            } else {
                result.amplify(3);
                button_state = true;
                boostbutton.style["background-color"] = "rgb(109, 168, 109)";
            }
        })

        audio.addEventListener("ended", function() {
            clearInterval(cookie_int);
            set_cookie(String(window.location.pathname) + "-prev_tstp", "", 0);
        })

        setInterval(update_timestamp, 10);
        if ( window.history.replaceState ) {
            window.history.replaceState( null, null, window.location.href );
        }
    </script>
</body>
