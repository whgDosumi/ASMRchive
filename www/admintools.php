<!DOCTYPE html>
<html>

<head>
    <meta name="viewport" content="width=1000">
    <title>ASMRchive - Administration Tools</title>
    <link rel="stylesheet" href="admintools.css">
    <link rel="icon" type="image/x-icon" href="./favicon.ico">
</head>

<body>
    <?php
    include "/var/www/html/library.php";
    // Get current version
    $asmrchive_version = "Unknown Version";
    if (file_exists("version.txt")) {
        $lines = explode(PHP_EOL, file_get_contents("version.txt"));
        $asmrchive_version = $lines[0];
        $build_date = $lines[1];
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
        $chans[$chan] = new Channel($name, $chan);
    }

    $error_message = "";
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // leaving room here for other POST functions

        // force scan
        if (isset($_POST['force-scan'])) {
            touch("/var/ASMRchive/.appdata/flags/scan_flag.txt");
            echo "<script type='text/javascript'>alert('Forcing ASMR Scan.');</script>"; 
        }
        // Request Video

        if (isset($_POST['send']) and isset($_POST['upload_channel']) and isset($_POST['video_url'])) {
            // Validate provided url
            if (validate_yt_video($_POST['video_url'])) {
                $targetChannel = $chans[$_POST["upload_channel"]];
                $targetChannel->append_video($_POST['video_url']);
                $targetChannel->save_appdata();
                echo "<script type='text/javascript'>alert('Video added to download queue. Please wait ~15 minutes for it to arrive.');</script>"; 
            } else {
                echo "<script type='text/javascript'>alert('Invalid youtube video URL');</script>"; 
            }
        }

        // New Channel
        if (isset($_POST['send']) and isset($_POST['channel_name']) and isset($_POST['channel_id'])) {
            if (strlen($_POST['channel_id']) == 24 and strlen($_POST['channel_name']) <= 24 and strlen($_POST['channel_name']) >= 1) { // check for expected string lengths
                $filecontent = $_POST['channel_name'] . "\n" . $_POST['channel_id'] . "\nnew";
                $exists = false;
                foreach($chans as $chan) {
                    if ($chan->channel_id == $_POST['channel_id']) {
                        $exists = true;
                    }
                }
                $result = false;
                if (!$exists){
                    $filename = "/var/ASMRchive/.appdata/channels/" . slugify($_POST['channel_name']) . ".channel";
                    if (! file_exists($filename)){
                        $result = file_put_contents($filename, $filecontent);
                        if ($result !== false) {
                            echo "<script type='text/javascript'>alert('Channel added, please wait a few minutes for the channel to download.');</script>"; 
                        } else {
                            echo "<script type='text/javascript'>alert('Something went wrong, contact a system administrator to review the logs.');</script>"; 
                        }
                    } else {
                        echo "<script type='text/javascript'>alert('A channel with that name already exists!');</script>";     
                    }
                } else {
                    echo "<script type='text/javascript'>alert('A channel with that channel ID already exists!');</script>"; 
                }
            } else {
                if ( ! (strlen($_POST['channel_id']) == 24) ) {
                    echo "<script type='text/javascript'>alert('Channel ID should be 24 characters.');</script>";
                } else {
                    echo "<script type='text/javascript'>alert('Verify the channel name is between 1 and 24 characters.');</script>";
                }
            }
        }

        // Update yt-dlp
        if (isset($_POST["dlp_update"])) {
            // Flag for yt-dlp to be updated
            touch("/var/ASMRchive/.appdata/flags/update_dlp_flag.txt");
            echo "<script type='text/javascript'>alert('Container will update yt-dlp. Reload in a few minutes to verify the update was completed.');</script>";
        }
        // Check yt-dlp
        if (isset($_POST["dlp_check"])) {
            touch("/var/ASMRchive/.appdata/flags/check_dlp_flag.txt");
            echo "<script type='text/javascript'>alert('Will refresh yt-dlp info, please wait a moment and refresh to see the update.');</script>";
        }

        // Upload ASMR
        if (isset($_POST['send']) and isset($_FILES['upload_file']) and isset($_POST['upload_channel'])) {
            $uploadOk = 1;
            $thumbnailOk = 0;
            $asmrFileType = strtolower(pathinfo(basename($_FILES["upload_file"]["name"]),PATHINFO_EXTENSION));
            error_log("error code: " . $_FILES["upload_file"]["error"]);
            global $SUPPORTED_FORMATS;
            // naive file type check
            if ( ! in_array(("." . $asmrFileType), $SUPPORTED_FORMATS)) {
                $first = True;
                $error_message .= "Only ";
                foreach ($SUPPORTED_FORMATS as &$format) {
                    if ($first) {
                        $first = False;
                        $error_message .= $format;
                    } elseif ($format === end($SUPPORTED_FORMATS)) {
                        $error_message .= " or " . $format;
                    } else {
                        $error_message .= ", " . $format;
                    }
                }
                $error_message .= " audio files allowed\n";
                $uploadOk = 0;
            }
            // File size check
            if ($_FILES["upload_file"]["size"] < 1024) {
                error_log("file size: " . $_FILES["upload_file"]["size"]);
                $error_message .= "Filesize too small (< 1KB)\n";
                $uploadOk = 0;
            }
            elseif ($_FILES["upload_file"]["size"] > 1073741824) {
                error_log("file size: " . $_FILES["upload_file"]["size"]);
                $error_message .= "Filesize too large (< 1GB)\n";
                $uploadOk = 0;
            }
            // File name length check
            if (mb_strlen($_FILES["upload_file"]["name"]) > 225) {
                $error_message .= "Filename too large (> 225)\n";
                $uploadOk = 0;
            }
            // thumbnail check
            if (isset($_FILES['upload_thumbnail'])) {
                $thumbnailOk = 1;
                if (mb_strlen($_FILES["upload_thumbnail"]["name"]) > 225) {
                    $error_message .= "Thumbnail name too large (> 225)\n";
                    $thumbnailOk = 0;
                }
                else {
                    $thubmnailFileType = strtolower(pathinfo(basename($_FILES["upload_thumbnail"]["name"]),PATHINFO_EXTENSION));
                }
            }
            if ($uploadOk == 0) {
                echo "<script type='text/javascript'>alert('Destruction');</script>";
            } else {
                // Create random new directory
                $targetChannel = $chans[$_POST["upload_channel"]];
                $asmrDirectory = $targetChannel->path . generateRandomString() . "/";
                $asmrFile =  $asmrDirectory . "asmr." . $asmrFileType;
                umask(0); # spicy
                if (!is_dir($asmrDirectory)) {
                    mkdir($asmrDirectory);
                    if (move_uploaded_file($_FILES["upload_file"]["tmp_name"], $asmrFile)) {
                        // copy thumbnail if applicable
                        if ($thumbnailOk) {
                            move_uploaded_file($_FILES["upload_thumbnail"]["tmp_name"], $asmrDirectory . "asmr." . $thubmnailFileType);
                        }

                        // copy of player.php
                        copy('/var/www/html/player.php', $asmrDirectory . "player.php");

                        // Use provided title, else filename for asmr title
                        if (isset($_POST['upload_title'])) {
                            file_put_contents($asmrDirectory . 'title.txt', $_POST['upload_title']);
                        } else {
                            file_put_contents($asmrDirectory . 'title.txt', $_FILES["upload_file"]["name"]);
                        }
                        
                        // use provided description if it exists
                        if (isset($_POST['upload_description'])) {
                            file_put_contents($asmrDirectory . 'asmr.description', $_POST['upload_description']);
                        }
                        
                        // Get duration from ffmpeg
                        $asmrDuration = system("ffmpeg -i '" . $asmrFile . "' 2>&1 | grep -Eo '[[:digit:]]{2}:[[:digit:]]{2}:[[:digit:]]{2}'");
                        file_put_contents($asmrDirectory . 'runtime.txt', $asmrDuration);

                        if (!empty($_POST['upload_date'])) {
                            file_put_contents($asmrDirectory . 'upload_date.txt', str_replace('-', '', $_POST['upload_date']));
                        }

                        // sneakily update the channel's count if everything succeeded
                        $targetChannel->count++;

                    } else {
                        $error_message = "Error in move_uploaded_file()";
                        echo "<script type='text/javascript'>alert('Destruction');</script>";
                    }

                }
                else {
                    // Here we find out whether php rand() is actually random.
                    echo "<script type='text/javascript'>alert('rand() disaster');</script>";
                }
            }
            umask();
        }
    }


    ?>
    <a href="index.php">
        <div id="backbutton"><img id="backimage" src="images/back.png"></div>
    </a>
    <a href="https://github.com/whgDosumi/ASMRchive">
        <p id="version"><?php echo $asmrchive_version; ?> <br> <span id="builddate"><?php echo $build_date; ?></span></p>
    </a>
    <div id="main">
        <a href="index.php">
            <img src="images/ASMRchive.png" alt="logo" class="top_logo">
        </a>
        <form method="post" enctype="multipart/form-data">
            <table>
                <thead>
                    <th colspan="2">Upload ASMR</th>
                </thead>
                <tbody>
                    <tr>
                        <td class="upload_table_cell"> Channel<span style="color:red;">*</span> </td>
                        <td class="upload_table_cell">
                            <select name="upload_channel" id="upload_channel" required>
                                <option value=""></option>
                                <?php
                                foreach($chans as $item){
                                    echo "<option value='$item->dir_name'>$item->alias</option>";
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td class="upload_table_cell"> Audio File<span style="color:red;">*</span> </td>
                        <td class="upload_table_cell">
                            <input type="file" name="upload_file" id="upload_file" required>
                        </td>
                    </tr>
                    <tr>
                        <td class="upload_table_cell"> Title </td>
                        <td class="upload_table_cell">
                            <input type="text" name="upload_title" id="upload_title">
                        </td>
                    </tr>
                    <tr>
                        <td class="upload_table_cell"> Description </td>
                        <td class="upload_table_cell">
                            <textarea type="text" name="upload_description"></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td class="upload_table_cell"> Upload Date </td>
                        <td class="upload_table_cell">
                            <input type="date" name="upload_date" id="upload_date">
                        </td>
                    </tr>
                    <tr>
                        <td class="upload_table_cell"> Thumbnail </td>
                        <td class="upload_table_cell">
                            <input type="file" name="upload_thumbnail" id="upload_thumbnail">
                        </td>
                    </tr>
                    <tr>
                        <td class="upload_table_error_cell"> <?=$error_message?></td>
                        <td class="upload_table_cell"><input type="submit" name="send" value="Send" id="upload_button" class="submit_button">
                        </td>
                    </tr>
                </tbody>
            </table>
        </form>


        <form action="<?php echo htmlspecialchars($_SERVER[$base_url]);?>" method="post" enctype="multipart/form-data">
            <table>
                <thead>
                    <th colspan="2">Add Channel</th>
                </thead>
                <tbody>
                    <tr>
                        <td class="upload_table_cell"> Channel Name<span style="color:red;">*</span> </td>
                        <td class="upload_table_cell">
                            <input type="text" name="channel_name" id="channel_name">
                        </td>
                    </tr>
                    <tr>
                        <td class="upload_table_cell"> Channel ID<span style="color:red;">*</span> </td>
                        <td class="upload_table_cell">
                            <input type="text" name="channel_id" id="channel_id">
                        </td>
                    </tr>
                    <tr>
                        <td class="upload_table_error_cell"> <?=$error_message?></td>
                        <td class="upload_table_cell"><input type="submit" class="submit_button" name="send" value="Send" id="add_channel_button">
                        </td>
                    </tr>
                </tbody>
            </table>
        </form>

        <form action="<?php echo htmlspecialchars($_SERVER[$base_url]);?>" method="post" enctype="multipart/form-data">
            <table>
                <thead>
                    <th colspan="2">Request Video</th>
                </thead>
                <tbody>
                    <tr>
                        <td class="upload_table_cell"> Channel<span style="color:red;">*</span> </td>
                        <td class="upload_table_cell">
                            <select name="upload_channel" id="upload_channel" required>
                                <option value=""></option>
                                <?php
                                    foreach($chans as $item){
                                        echo "<option value='$item->dir_name'>$item->alias</option>";
                                    }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td class="upload_table_cell"> Video URL<span style="color:red;">*</span> </td>
                        <td class="upload_table_cell">
                            <input type="text" name="video_url" id="video_url" required>
                        </td>
                    </tr>
                    <tr>
                        <td class="upload_table_error_cell">
                            <?=$error_message?>
                        </td>
                        <td class="upload_table_cell"><input class="submit_button" type="submit" name="send" value="Send" id="request_video_button">
                        </td>
                    </tr>
                </tbody>
            </table>
        </form>
        <form method="post" enctype="multipart/form-data">
            <table>
                    <?php
                        $dlp_info = get_dlp_update();
                        // Ensure necessary values are set, otherwise set to defaults
                        $dlp_info["current_version"] = $dlp_info["current_version"] ?? "Unknown";
                        $dlp_info["latest_version"] = $dlp_info["latest_version"] ?? "Unknown";
                        // Default to true because we don't want the button to show. 
                        $dlp_info["up_to_date"] = $dlp_info["up_to_date"] ?? true;
                    ?>
                <thead>
                    <th colspan="2">YT-DLP Version</th>
                </thead>
                <tbody>
                    <tr>
                        <td class="upload_table_cell"> Current: </td>
                        <td class="upload_table_cell"> <?php echo $dlp_info["current_version"]; ?> </td>
                    </tr>
                    <tr>
                        <td class="upload_table_cell"> Latest: </td>
                        <td class="upload_table_cell"> <?php echo $dlp_info["latest_version"]; ?> </td>
                    </tr>
                    <tr>
                    <td class="upload_table_cell\"><input type="submit" name="dlp_check" value="Check" id="dlp_check" class="submit_button"> </td>
                    <?php
                        if (!$dlp_info["up_to_date"]) {
                            echo "<td class=\"upload_table_cell\"><input type=\"submit\" name=\"dlp_update\" value=\"Update\" id=\"dlp_update\" class=\"submit_button\"> </td>";
                        }
                    ?>
                    </tr>
                </tbody>
            </table>
        </form>
        <form method="post" enctype="multipart/form-data">
            <input type="submit" name="force-scan" value="Scan Now" id="force-scan">
        </form>
        <br>
        <table>
            <thead>
                <th colspan="2">Channel</th>
                <th>Status</th>
                <th>Count</th>
                <th>Members</th>
            </thead>
            <tbody>
                <?php
                $zeros = [];
                foreach ($chans as $chan) {
                    if ($chan->count > 0) {
                        $chan->display_row($show_members = true);
                    } else {
                        array_push($zeros, $chan);
                    }
                }
                ?>
                <th colspan="5" class="splitter">No Entries &#128546;</th>
                <?php
                foreach ($zeros as $chan) {
                    $chan->display_row($show_members = true);
                }
                ?>
            </tbody>
        </table>
    </div>
</body>

<script>
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
</script>