<?php
// Uploading happens on the album page now. Without an album, pick one first.
$album = (int) ($_GET['gallery_id'] ?? 0);
header('Location: ' . ($album > 0 ? "/view_gallery?gallery_id=$album&upload=1" : '/gallery'), true, 302);
exit;
