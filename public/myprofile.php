<?php
// The account page lives in PlainShow now: profile, picture, password and two-factor.
header('Location: ' . ps_url('core', '/account'), true, 301);
exit;
