<?php
/** Shared start of every admin page: sign-in and the page's permission. Needs $adminPage. */
use Blog\Domain\Admin;

$identity = require_identity();
if (!Admin::can($identity, $adminPage)) {
    http_response_code(403);
    blog_layout_start(['title' => ps_t('Ingen åtkomst')]);
    echo '<div class="ps-empty blog-missing"><i class="bx bx-lock-alt"></i><h1 class="ps-heading">' . ps_h('Ingen åtkomst') . '</h1><p>' . ps_h('Den här sidan är bara för administratörer.') . '</p><a class="ps-btn" href="/"><i class="bx bx-left-arrow-alt"></i>' . ps_h('Till startsidan') . '</a></div>';
    blog_layout_end();
    exit;
}
