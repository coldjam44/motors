<?php
$path = '.';
$dirs = array_filter(scandir($path), function($item) use ($path) {
    return is_dir($path . DIRECTORY_SEPARATOR . $item) && $item !== '.' && $item !== '..';
});

echo '<ul>';
foreach ($dirs as $dir) {
    echo '<li>' . htmlspecialchars($dir) . '</li>';
}
echo '</ul>';
?>
