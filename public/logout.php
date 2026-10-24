<?php
// Signing out of the blog signs out of PlainShow everywhere.
header('Location: /auth/logout', true, 302);
exit;
