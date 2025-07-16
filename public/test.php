<?php
echo '<style>body { background-color: black; color: white; font-family: monospace; }</style>';

$ffmpegPath = '/home/azsystems-motors/htdocs/motors.azsystems.tech/public/ffmpeg-7.0.2-amd64-static/ffmpeg';
$dir = 'reels';
$items = scandir($dir);

// Filter video files (MP4 only)
$videos = array_filter($items, function($item) use ($dir) {
    return !in_array($item, ['.', '..']) && is_file("$dir/$item") && pathinfo($item, PATHINFO_EXTENSION) === 'mp4';
});

$videos = array_values($videos);

if (!empty($videos)) {
    $firstVideo = $videos[0];
    $firstVideoPath = realpath("$dir/" . $firstVideo);
    echo "First video path: $firstVideoPath<br><br>";

    // Output thumbnail file path
    $thumbnailPath = "$dir/thumbnail.jpg";

    // Build ffmpeg command to extract 1 frame at 1 second
    $cmd = escapeshellcmd($ffmpegPath) . " -ss 00:00:03 -i " . escapeshellarg($firstVideoPath) . " -frames:v 1 -q:v 2 " . escapeshellarg($thumbnailPath) . " 2>&1";

    exec($cmd, $output, $returnVar);

    if ($returnVar === 0 && file_exists($thumbnailPath)) {
        echo "Thumbnail created successfully:<br>";
        echo "<img src='$thumbnailPath' style='max-width:300px; border:1px solid #fff;'><br>";
    } else {
        echo "Failed to create thumbnail.<br>";
        echo "<pre>" . implode("\n", $output) . "</pre>";
    }
} else {
    echo "No video files found in '$dir'.";
}
?>
