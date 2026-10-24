<?php
// Albums are edited on the album page now.
$album = (int) ($_GET['gallery_id'] ?? 0);
header('Location: ' . ($album > 0 ? "/view_gallery?gallery_id=$album" : '/gallery'), true, 302);
exit;
