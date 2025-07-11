<!DOCTYPE html>
<html>

<head>
    <meta name="viewport" content="width=1000">
    <title>ASMRchive - Channels</title>
    <link rel="stylesheet" href="ASMRchive.css">
    <link rel="apple-touch-icon" href="/ASMRchive/apple_touch_icon.png">
    <link rel="icon" type="image/x-icon" href="./favicon.ico">
</head>

<body>
    <?php
    # Import tools from library.php
    include "library.php";

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
    <a href="./admintools.php"><div id="adminbutton"><img id="adminimage" src="./images/Ayame.png"></div></a>
    <div id="main">
        <a href="./index.php">
            <img src="./images/ASMRchive.png" alt="logo" class="top_logo">
        </a>
        <table <?php if(count($chans) <= 0) { echo "hidden"; } ?> >
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
                if (count($zeros) > 0) {
                    echo "<th colspan=\"4\" class=\"splitter\">No Entries &#128546;</th>";
                    foreach ($zeros as $chan) {
                        $chan->display_row();
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</body>