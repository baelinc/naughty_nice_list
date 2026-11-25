<?php
// Adds "Santa's Naughty & Nice List" to FPP's Content Setup menu.
$santaPlugin = basename(__DIR__);
?>
<li><a href="plugin.php?plugin=<?php echo urlencode($santaPlugin); ?>&page=santa-list.php">Santa's Naughty &amp; Nice List</a></li>
