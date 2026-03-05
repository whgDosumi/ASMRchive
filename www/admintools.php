<!DOCTYPE html>
<html>

<head>
    <meta name="viewport" content="width=1000">
    <title>ASMRchive - Administration Tools</title>
    <link rel="stylesheet" href="admintools.css">
    <link rel="icon" type="image/x-icon" href="./favicon.ico">
    <script src="sort.js"></script>
</head>

<body>
    <?php
    include "/var/www/html/library.php";
    include "/var/www/html/auth.php";
    require_login();

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['logout'])) {
        session_destroy();
        header("Location: login.php");
        exit();
    }
    
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
    $user_management_message = "";
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // User Management: Create User
        if (isset($_POST['create_user']) && isset($_POST['new_username']) && isset($_POST['new_password'])) {
            if (is_owner()) {
                $new_user = trim($_POST['new_username']);
                $new_pass = $_POST['new_password'];
                if (strlen($new_user) < 3 || strlen($new_pass) < 6) {
                    $user_management_message = "Username > 2 chars, Password > 5 chars.";
                } else {
                    if (create_user($new_user, $new_pass)) {
                        $user_management_message = "User '{$new_user}' created successfully.";
                    } else {
                        $user_management_message = "User '{$new_user}' already exists.";
                    }
                }
            } else {
                $user_management_message = "Permission denied.";
            }
        }

        // User Management: Delete User
        if (isset($_POST['delete_user']) && isset($_POST['target_user'])) {
            if (is_owner()) {
                $target = $_POST['target_user'];
                $users = get_users();
                // Prevent deleting the last user or the currently logged-in user
                if (count($users) <= 1) {
                    $user_management_message = "Cannot delete the last remaining user.";
                } else if ($target === $_SESSION['username']) {
                    $user_management_message = "Cannot delete your own account while logged in.";
                } else if (isset($users[$target]['is_owner']) && $users[$target]['is_owner'] === true) {
                    $user_management_message = "Cannot delete the owner account.";
                } else {
                    if (delete_user($target)) {
                        $user_management_message = "User '{$target}' deleted.";
                    } else {
                        $user_management_message = "Failed to delete user.";
                    }
                }
            } else {
                $user_management_message = "Permission denied.";
            }
        }

        // leaving room here for other POST functions

        // force scan
        if (isset($_POST['force-scan'])) {
            $flag_file = "/var/ASMRchive/.appdata/flags/scan_flag.txt";
            write_file($flag_file, null, 0664);
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
        if (isset($_POST['send']) and isset($_POST['channel_name']) and isset($_POST['channel_input'])) {
            $channel_id_input = trim($_POST['channel_input']);
            $channel_id = "";
            $channel_name = trim($_POST['channel_name']);

            // Validate channel ID or URL
            if (preg_match('/^UC[\w-]{22}$/', $channel_id_input)) {
                $channel_id = $channel_id_input;
            } else {
                // It's not a direct ID, validate as URL
                if (filter_var($channel_id_input, FILTER_VALIDATE_URL)) {
                    $parsed_url = parse_url($channel_id_input);
                    $host = $parsed_url['host'] ?? '';
                    if (preg_match('/^(www\.|m\.)?youtube\.com$/i', $host) || preg_match('/^youtu\.be$/i', $host)) {
                        $fetched_id = getChannelId($channel_id_input);
                        if ($fetched_id) {
                            $channel_id = $fetched_id;
                        } else {
                            echo "<script type='text/javascript'>alert('Could not find a Channel ID at the provided URL.');</script>";
                        }
                    } else {
                        echo "<script type='text/javascript'>alert('Invalid YouTube URL domain.');</script>";
                    }
                } else {
                    echo "<script type='text/javascript'>alert('Invalid Channel ID or URL format.');</script>";
                }
            }

            if ($channel_id !== "") {
                if (strlen($channel_id) == 24 and strlen($channel_name) <= 24 and strlen($channel_name) >= 1) { // check for expected string lengths
                    $filecontent = $channel_name . "\n" . $channel_id . "\nnew\n0";
                    $exists = false;
                    foreach($chans as $chan) {
                        if ($chan->channel_id == $channel_id) {
                            $exists = true;
                        }
                    }
                    $result = false;
                    if (!$exists){
                        $filename = "/var/ASMRchive/.appdata/channels/" . slugify($channel_name) . ".channel";
                        if (! file_exists($filename)){
                            $result = write_file($filename, $filecontent, 0644);
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
                    if ( ! (strlen($channel_id) == 24) ) {
                        echo "<script type='text/javascript'>alert('Channel ID should be 24 characters.');</script>";
                    } else {
                        echo "<script type='text/javascript'>alert('Verify the channel name is between 1 and 24 characters.');</script>";
                    }
                }
            }
        }

        // Update yt-dlp
        if (isset($_POST["dlp_update"])) {
            // Flag for yt-dlp to be updated
            write_file("/var/ASMRchive/.appdata/flags/update_dlp_flag.txt", null, 0664);
            echo "<script type='text/javascript'>alert('Container will update yt-dlp. Reload in a few minutes to verify the update was completed.');</script>";
        }
        // Check yt-dlp
        if (isset($_POST["dlp_check"])) {
            write_file("/var/ASMRchive/.appdata/flags/check_dlp_flag.txt", null, 0664);
            echo "<script type='text/javascript'>alert('Will refresh yt-dlp info, please wait a moment and refresh to see the update.');</script>";
        }

        // Upload Cookie
        if (isset($_POST['cookie_upload']) && isset($_POST['cookie_content']) && isset($_POST['cookie_ttl'])) {
            $cookie_content = $_POST['cookie_content'];
            $cookie_ttl = intval($_POST['cookie_ttl']);
            $allowed_ttls = [15, 30, 60, 120];

            if (!in_array($cookie_ttl, $allowed_ttls)) {
                echo "<script type='text/javascript'>alert('Invalid TTL selected.');</script>";
            } else {
                // Validate Netscape cookie format
                $cookie_content = str_replace("\r\n", "\n", $cookie_content);
                $cookie_content = str_replace("\r", "\n", $cookie_content);
                $lines = explode("\n", trim($cookie_content));
                $valid = true;
                $data_lines = 0;
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === "" || str_starts_with($line, "#")) {
                        continue;
                    }
                    $fields = explode("\t", $line);
                    if (count($fields) !== 7) {
                        $valid = false;
                        break;
                    }
                    $data_lines++;
                }
                if (!$valid || $data_lines === 0) {
                    echo "<script type='text/javascript'>alert('Invalid Netscape cookie format. Each data line must have 7 tab-separated fields.');</script>";
                } else if (!is_dir("/var/ASMRchive/.appdata/cookies/")) {
                    echo "<script type='text/javascript'>alert('Cookies directory does not exist.');</script>";
                } else {
                    $expiry = date("Ymd-Hi", strtotime("+{$cookie_ttl} minutes"));
                    $suffix = bin2hex(random_bytes(2));
                    $filename = "cookie-{$expiry}-{$suffix}.txt";
                    $filepath = "/var/ASMRchive/.appdata/cookies/{$filename}";
                    $result = file_put_contents($filepath, $cookie_content);
                    if ($result !== false) {
                        chmod($filepath, 0200);
                        echo "<script type='text/javascript'>
                            var expiry = new Date(Date.now() + {$cookie_ttl} * 60000);
                            var timeStr = expiry.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
                            alert('Cookie uploaded. Expires at ' + timeStr + '.');
                        </script>";
                    } else {
                        echo "<script type='text/javascript'>alert('Failed to save cookie file.');</script>";
                    }
                }
            }
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
                
                if (create_dir($asmrDirectory, 0775)) {
                    if (move_uploaded_file($_FILES["upload_file"]["tmp_name"], $asmrFile)) {
                        chmod($asmrFile, 0664);
                        // copy thumbnail if applicable
                        if ($thumbnailOk) {
                            $thumbDest = $asmrDirectory . "asmr." . $thubmnailFileType;
                            move_uploaded_file($_FILES["upload_thumbnail"]["tmp_name"], $thumbDest);
                            chmod($thumbDest, 0664);
                        }

                        // copy of player.php
                        $playerDest = $asmrDirectory . "player.php";
                        copy('/var/www/html/player.php', $playerDest);
                        chmod($playerDest, 0664);

                        // Use provided title, else filename for asmr title
                        if (isset($_POST['upload_title'])) {
                            write_file($asmrDirectory . 'title.txt', $_POST['upload_title'], 0664);
                        } else {
                            write_file($asmrDirectory . 'title.txt', $_FILES["upload_file"]["name"], 0664);
                        }
                        
                        // use provided description if it exists
                        if (isset($_POST['upload_description'])) {
                            write_file($asmrDirectory . 'asmr.description', $_POST['upload_description'], 0664);
                        }
                        
                        // Get duration from ffmpeg
                        $asmrDuration = system("ffmpeg -i '" . $asmrFile . "' 2>&1 | grep -Eo '[[:digit:]]{2}:[[:digit:]]{2}:[[:digit:]]{2}'");
                        write_file($asmrDirectory . 'runtime.txt', $asmrDuration, 0664);

                        if (!empty($_POST['upload_date'])) {
                            write_file($asmrDirectory . 'upload_date.txt', str_replace('-', '', $_POST['upload_date']), 0664);
                        }

                        // sneakily update the channel's count if everything succeeded
                        $targetChannel->count++;
                        $targetChannel->last_updated = time();
                        $targetChannel->save_appdata();

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
        }
    }


    ?>
    <a href="index.php">
        <div id="backbutton"><img id="backimage" src="images/back.png"></div>
    </a>
    <a href="https://github.com/whgDosumi/ASMRchive">
        <p id="version"><?php echo $asmrchive_version; ?> <br> <span id="builddate"><?php echo $build_date; ?></span></p>
    </a>
    <div style="position: fixed; top: 58px; right: 0; display: flex; flex-direction: column; align-items: flex-end;">
        <form method="post" id="logout_form" style="margin-bottom: 8px;">
            <input type="submit" name="logout" value="Logout" style="background-color: darkseagreen; border: none; font-weight: bold; cursor: pointer; border-top-left-radius: 5px; border-bottom-left-radius: 5px; padding: 15px 20px; font-size: 18px; width: 100%;">
        </form>
        <a href="change_password.php" style="text-decoration: none;">
            <div style="background-color: darkseagreen; border: none; font-weight: bold; cursor: pointer; border-top-left-radius: 5px; border-bottom-left-radius: 5px; padding: 15px 20px; font-size: 18px; color: black; text-align: center;">
                Change Password
            </div>
        </a>
    </div>
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
                                        echo "<option value='" . htmlspecialchars($item->dir_name, ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($item->alias, ENT_QUOTES, 'UTF-8') . "</option>";
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


        <form method="post" enctype="multipart/form-data">
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
                        <td class="upload_table_cell" title="Enter a 24-character YouTube Channel ID or a valid YouTube Channel URL (e.g., https://www.youtube.com/@username)"> Channel ID or URL<span style="color:red;">*</span> </td>
                        <td class="upload_table_cell">
                            <input type="text" name="channel_input" id="channel_input" title="Enter a 24-character YouTube Channel ID or a valid YouTube Channel URL (e.g., https://www.youtube.com/@username)">
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

        <form method="post" enctype="multipart/form-data">
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
                                            echo "<option value='" . htmlspecialchars($item->dir_name, ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($item->alias, ENT_QUOTES, 'UTF-8') . "</option>";
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
        <form method="post">
            <table>
                <thead>
                    <th colspan="2">Upload Cookie</th>
                </thead>
                <tbody>
                    <tr>
                        <td class="upload_table_cell" title="Netscape HTTP cookie format, tab-separated"> Cookie<span style="color:red;">*</span> </td>
                        <td class="upload_table_cell">
                            <textarea name="cookie_content" id="cookie_content" required></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td class="upload_table_cell"> Time to Live<span style="color:red;">*</span> </td>
                        <td class="upload_table_cell">
                            <select name="cookie_ttl" id="cookie_ttl" required>
                                <option value="15">15 minutes</option>
                                <option value="30" selected>30 minutes</option>
                                <option value="60">1 hour</option>
                                <option value="120">2 hours</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td class="upload_table_error_cell"></td>
                        <td class="upload_table_cell">
                            <input type="submit" name="cookie_upload" value="Send" class="submit_button">
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
                    <td class="upload_table_cell"><input type="submit" name="dlp_check" value="Check" id="dlp_check" class="submit_button"> </td>
                    <?php
                        if (!$dlp_info["up_to_date"]) {
                            echo "<td class=\"upload_table_cell\"><input type=\"submit\" name=\"dlp_update\" value=\"Update\" id=\"dlp_update\" class=\"submit_button\"> </td>";
                        }
                    ?>
                    </tr>
                </tbody>
            </table>
        </form>
        <?php if (is_owner()): ?>
        <form method="post" enctype="multipart/form-data">
            <table>
                <thead>
                    <th colspan="2">User Management</th>
                </thead>
                <tbody>
                    <tr>
                        <td class="upload_table_cell"> New Username </td>
                        <td class="upload_table_cell">
                            <input type="text" name="new_username" id="new_username" style="font-size: 30px; margin-left: 0px; position: relative; bottom: 10px;">
                        </td>
                    </tr>
                    <tr>
                        <td class="upload_table_cell"> New Password </td>
                        <td class="upload_table_cell">
                            <input type="password" name="new_password" id="new_password" style="font-size: 30px; margin-left: 0px; position: relative; bottom: 10px;">
                        </td>
                    </tr>
                    <tr>
                        <td class="upload_table_error_cell">
                            <?=htmlspecialchars($user_management_message, ENT_QUOTES, 'UTF-8')?>
                        </td>
                        <td class="upload_table_cell"><input type="submit" name="create_user" value="Create User" class="submit_button"> </td>
                    </tr>
                    <tr>
                        <td colspan="2" class="upload_table_cell" style="text-align: center; border-bottom: none;">
                            <strong>Current Users:</strong><br>
                            <?php
                            $all_users = get_users();
                            foreach ($all_users as $u => $data) {
                                echo htmlspecialchars($u, ENT_QUOTES, 'UTF-8') . " ";
                                $is_owner = isset($data['is_owner']) && $data['is_owner'] === true;
                                if ($is_owner) {
                                    echo "<span style='font-size: 15px; color: #003300; font-weight: bold;'>[owner]</span> ";
                                }
                                if ($u !== $_SESSION['username'] && !$is_owner) {
                                    echo "<button type='submit' name='delete_user' value='1' onclick=\"document.getElementById('target_user').value='$u';\" style='margin-left: 10px; cursor: pointer; color: red;'>Delete</button>";
                                } else if ($u === $_SESSION['username']) {
                                    echo "<span style='font-size: 15px; color: #444;'> (you)</span>";
                                }
                                echo "<br>";
                            }
                            ?>
                            <input type="hidden" name="target_user" id="target_user" value="">
                        </td>
                    </tr>
                </tbody>
            </table>
        </form>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <input type="submit" name="force-scan" value="ASMR Scan Now" id="force-scan">
        </form>
        <br>
        <table>
            <thead>
                <th colspan="2" class="sortable" onclick="sortTable(this)" data-sort-col="1">Channel</th>
                <th class="sortable" onclick="sortTable(this)" data-sort-col="2">Status</th>
                <th class="sortable" onclick="sortTable(this)" data-sort-col="3">Count</th>
                <th class="sortable" onclick="sortTable(this)" data-sort-col="4">Updated</th>
                <th>Members</th>
            </thead>
            <tbody>
                <?php
                $zeros = [];
                foreach ($chans as $chan) {
                    if ($chan->count > 0) {
                        $chan->display_row($show_members = true, $show_status = true);
                    } else {
                        array_push($zeros, $chan);
                    }
                }
                ?>
                <th colspan="6" class="splitter">No Entries &#128546;</th>
                <?php
                foreach ($zeros as $chan) {
                    $chan->display_row($show_members = true, $show_status = true);
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