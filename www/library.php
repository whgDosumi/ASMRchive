<?php
    # Resets umask to default
    # Security feature
    umask();

    header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
    header("Pragma: no-cache"); // HTTP 1.0.
    header("Expires: 0"); // Proxies.

    # Checks if $string contains $contains, returns boolean
    function string_contains($string, $contains) {
        if (strpos($string, $contains) === false) {
            return false;
        } else {
            return true;
        }
    }

    # Removes any strange characters from $data
    # Used by player.php to sanitize comment inputs
    function test_input($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }

    # Sanitizes $text for filesystem name.
    function slugify($text, string $divider = '-') {
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

    #Generates a random string of length $length (defaults 20)
    function generateRandomString($length = 20) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    # Function player.php uses to replace timestamps in its comments with links. 
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
            $temp_match = str_replace(":", "<colonhere>", $match);

            $replace_string = '<span style="cursor: pointer;text-decoration: underline;color:blue;" onclick="set_time(' . strval($seconds) . ')">' . $temp_match . '</span>';

            $pos = strpos($text, $match);
            if ($pos !== false) {
                $text = substr_replace($text, $replace_string, $pos, strlen($match));
            }
        }
        $text = str_replace("<colonhere>", ":", $text);
        return($text);
    }

    # Class for the channel object
    # These are representations of channels in the media dir
    class Channel {
        public $alias;
        public $dir_name;
        public $path;
        public $link;
        public $count;
        public $status;

        # $alias is the channel name as read from channel_dir/name.txt
        # $dir_name is the name of the directory it's contained in.
        public function __construct($alias, $dir_name)
        {
            $this->alias = $alias;
            $this->dir_name = $dir_name;
            $this->path = "ASMR/" . $dir_name . "/";
            $this->link = $this->path . "index.php";
            $temp = scandir($this->path);
            $count = 0;
            foreach ($temp as $vid) {
                if ($vid != ".") {
                    if ($vid != "..") {
                        if (is_dir($this->path . $vid)) {
                            $count = $count + 1;
                        }
                    }
                }
            }
            $this->count = $count;
            $temp = file_get_contents('channels/' . $this->dir_name . ".channel");
            if (string_contains($temp, "archived")){
                $this->status = "Archived";
            } elseif (string_contains($temp, "recording")) {
                $this->status = "Recording";
            } elseif (string_contains($temp, "new")) {
                $this->status = "New";
            } elseif (string_contains($temp, "recorded")) {
                $this->status = "Recorded";
            } elseif (string_contains($temp, "waiting")) {
                $this->status = "Waiting";
            } elseif (string_contains($temp, "errored")) {
                $this->status = "Errored!";
            } elseif (string_contains($temp, "saved")) {
                $this->status = "Saved";
            } else {
                $this->status = "Unknown";
            }
        }

        public function display_row()
        {
            if ($this->count == 0) {
                echo '<tr style="cursor: not-allowed;"';
            } else {
                echo '<tr onclick="window.location=' . "'" . $this->path . "index.php'" . '"';
            }
            echo '><td><img class="pfp" src=' . $this->path . 'pfp.png></td>
            <td class="channel">' . $this->alias . '</td>
            <td class="status">' . $this->status . '</td>
            <td class="count">' . $this->count . '</td></tr>';
        }
    }
    
    # Defines the Video object
    # Representations of videos in the channels
    # Used when a video needs to be an interactable object.
    class Video {
        public $thumbnail;
        public $path;
        public $title;
        public $upload_date;
        public $pretty_date;
        public $asmr_file;
        public $asmr_runtime;
        public $comment_count;
        public $description;
        # $path is the path to the directory of the video
        public function __construct($path) {
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
            $this->description = nl2br(file_get_contents("./asmr.description"));
            $doc = fopen($path . '/title.txt', 'r');
            $this->title = fread($doc, filesize($path . '/title.txt'));
            $this->comment_count = 0;
            if (is_dir($this->path . "/comments")) {
                $scan = scandir($this->path . "/comments");
                $this->comment_count = count($scan)-2;
            }
            $doc = fopen($path . '/upload_date.txt', 'r');
            $this->upload_date = fread($doc, filesize($path . '/upload_date.txt'));
            $this->pretty_date = date('m-d-Y', strtotime($this->upload_date));
            if (is_file($path . '/asmr.flac')) {
                $this->asmr_file = $path . "/asmr.flac";
            } elseif (is_file($path . '/asmr.mp3')) {
                $this->asmr_file = $path . "/asmr.mp3";
            } elseif (is_file($path . '/asmr.opus')) {
                $this->asmr_file = $path . "/asmr.opus";
            } elseif (is_file($path . '/asmr.m4a')) {
                $this->asmr_file = $path . "/asmr.m4a";
            } else {
                $this->asmr_file = $path . "/asmr.webm"; # unsupported but here to cover non-converted files.
            }
            $this->asmr_runtime = file_get_contents($path . "/runtime.txt");
        }
        # Used to display this video as a row in channel_index
        public function display_row() {
            echo '<tr onclick="document.location = \'' . $this->path . '/player.php\';"><td><img class="thumb" alt="No Thumbnail :(" src=' . $this->thumbnail . '></td>
            <td><p class="title">' . $this->title . '</td>
            <td class="date">' . $this->pretty_date . '</td>
            <td class="date">' . $this->asmr_runtime . '</td>
            <td class="count">' . $this->comment_count . '</td>
            </tr>';
        }
    }

?>