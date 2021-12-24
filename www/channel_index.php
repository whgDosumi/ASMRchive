<?php

header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

function comparator($object1, $object2)
{
    return $object1->upload_date < $object2->upload_date;
}

class Channel
{
    public $alias;
    public $name;
    public $path;
    public $link;
    public $count;

    public function __construct($alias, $name)
    {
        $this->alias = $alias;
        $this->name = $name;
        $this->path = "ASMR/" . $name . "/";
        $this->link = $this->path . "index.php";
        $this->comment_count = 0;
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
    }

    public function display_row()
    {
        echo '<tr onclick="window.location=' . "'" . $this->path . "index.php'" . '"><td><img class="pfp" src=' . $this->path . 'pfp.png></td>
            <td class="channel">' . $this->alias . '</td>
            <td class="count">' . $this->count . '</td></tr>';
    }
}
class Video
{
    public $thumbnail;
    public $path;
    public $title;
    public $upload_date;
    public $pretty_date;
    public $asmr_file;
    public $asmr_runtime;
    public $comment_count;
    public function __construct($path)
    {
        $this->path = $path;
        if (is_file($path . '/asmr.webp')) {
            $this->thumbnail = $path . '/asmr.webp';
        } elseif (is_file($path . '/asmr.jpg')) {
            $this->thumbnail = $path . '/asmr.jpg';
        } else {
            $this->thumbnail = '/images/default_thumbnail.png';
        }

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
        if (is_file($path . "/asmr.webm")) {
            $this->asmr_file = $path . "/asmr.webm";
        } elseif (is_file($path . 'asmr.aac')) {
            $this->asmr_file = $path . "/asmr.aac";
        } else {
            $this->asmr_file = $path . "/asmr.m4a";
        }
        $this->asmr_runtime = file_get_contents($path . "/runtime.txt");
    }
    public function display_row()
    {
        echo '<tr onclick="document.location = \'' . $this->path . '/player.php\';"><td><img class="thumb" alt="No Thumbnail :(" src=' . $this->thumbnail . '></td>
        <td><p class="title">' . $this->title . '</td>
        <td class="date">' . $this->pretty_date . '</td>
        <td class="date">' . $this->asmr_runtime . '</td>
        <td class="count">' . $this->comment_count . '</td>
        </tr>';
    }
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
