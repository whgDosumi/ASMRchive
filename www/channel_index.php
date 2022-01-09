<?php

header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

include "/var/www/html/library.php";

function comparator($object1, $object2)
{
    return $object1->upload_date < $object2->upload_date;
}

$cwd = getcwd();
$name = explode('/', getcwd());
$name = $name[count($name) - 1];
$f = fopen("./name.txt", 'r');
$alias = fread($f, filesize("./name.txt"));
$me = new Channel($alias, $name);
?>
<!DOCTYPE html>
<html>

<head>
    <meta name="viewport" content="width=1000">
    <title>ASMRchive - <?php echo $me->alias?></title>
    <link rel="stylesheet" href="/channel.css">
</head>

<body>
    <a href="/index.php"><div id="backbutton"><img id="backimage" src="/images/back.png"></div></a>
    <div id="main">
        <a href="/index.php">
            <img src="/images/ASMRchive.png" alt="logo" class="top_logo">
        </a>
        <br>
        <div class="titlecard">
            <img src="./pfp.png" class="pfp">
            <h1 class="cardtext"><?php echo $me->alias ?></h1>
        </div>
        <br>
        <table>
            <thead>
                <th>Image</th>
                <th>Title</th>
                <th>Date</th>
                <th>Time</th>
                <th>Com</th>
            </thead>
            <tbody>
                <?php
                $scan = scandir("./");
                $asmr = [];
                foreach ($scan as $file) {
                    if (is_dir($file)) {
                        if ($file != "." and $file != "..") {
                            array_push($asmr, new Video($file));
                        }
                    }
                }
                usort($asmr, "comparator");
                foreach ($asmr as $video) {
                    $video->display_row();
                }
                ?>
            </tbody>
        </table>
    </div>
</body>
