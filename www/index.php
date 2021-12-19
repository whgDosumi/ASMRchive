<!DOCTYPE html>
<html>

<head>
    <meta name="viewport" content="width=1000">
    <title>ASMRchive - Channels</title>
    <link rel="stylesheet" href="ASMRchive.css">
</head>

<body>
    <?php
    function string_contains($string, $contains) {
        if (strpos($string, $contains) === false) {
            return false;
        } else {
            return true;
        }
    }
    class Channel
    {
        public $alias;
        public $name;
        public $path;
        public $link;
        public $count;
        public $status;

        public function __construct($alias, $name)
        {
            $this->alias = $alias;
            $this->name = $name;
            $this->path = "ASMR/" . $name . "/";
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
            $temp = file_get_contents('channels/' . $this->name . ".channel");
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
    $channel_folders = scandir("ASMR/");
    $channels = array();
    foreach ($channel_folders as $folder) {
        if (strpos($folder, 'DS_Store') === false) {
            if (strpos($folder, '.') === false) {
                array_push($channels, $folder);
            }
        }
    }
    unset($folder);
    unset($channel_folders);
    $chans = array();
    foreach ($channels as $chan) {
        $path = "ASMR/" . $chan . "/name.txt";
        $f = fopen($path, 'r');
        $name = fread($f, filesize($path));
        array_push($chans, new Channel($name, $chan));
    }
    ?>
    <div id="main">
        <a href="/ASMRchive.php">
            <img src="images/ASMRchive.png" alt="logo" class="top_logo">
        </a>
        <table>
            <thead>
                <th colspan="2">Channel</th>
                <th>Status</th>
                <th>Count</th>
            </thead>
            <tbody>
                <?php
                $zeros = [];
                foreach ($chans as $chan) {
                    if ($chan->count > 0) {
                        $chan->display_row();
                    } else {
                        array_push($zeros, $chan);
                    }
                }
                ?>
                <th colspan="4" class="splitter">No Entries &#128546;</th>
                <?php
                foreach ($zeros as $chan) {
                    $chan->display_row();
                }
                ?>
            </tbody>
        </table>
    </div>
</body>