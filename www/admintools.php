<!DOCTYPE html>
<html>

<head>
    <meta name="viewport" content="width=1000">
    <title>ASMRchive - Administration Tools</title>
    <link rel="stylesheet" href="admintools.css">
</head>

<body>
    <?php
    include "/var/www/html/library.php";
    
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
        } else {
            exit();
        }
    }


    ?>
    <a href="/index.php"><div id="backbutton"><img id="backimage" src="/images/back.png"></div></a>
    <div id="main">
        <a href="/index.php">
            <img src="images/ASMRchive.png" alt="logo" class="top_logo">
        </a>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method="post" enctype="multipart/form-data">
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
                        <td class="upload_table_cell"><input type="submit" name="send" value="Send" id="upload_button"></td>
                    </tr>
                </tbody>
            </table>
        </form>

        <br>
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

<script>
if ( window.history.replaceState ) {
  window.history.replaceState( null, null, window.location.href );
}
</script>