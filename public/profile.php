<?php
// A member's page: who they are and what they have written.
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/views/layout.php';

use Blog\Domain\Posts;

$identity = require_identity();
$profileId = (int) ($_GET['user_id'] ?? 0);
$stmt = wocDb()->prepare('SELECT user_id, username, firstname, lastname, access_level, image_source FROM `user` WHERE user_id = ?');
$stmt->bind_param('i', $profileId);
$stmt->execute();
$person = $stmt->get_result()->fetch_assoc();
if (!$person) {
    http_response_code(404);
    blog_layout_start(['title' => ps_t('Personen finns inte'), 'active' => 'inlagg']);
    echo '<div class="ps-empty blog-missing"><i class="bx bx-user-x"></i><h1 class="ps-heading">' . ps_h('Personen finns inte') . '</h1><a class="ps-btn" href="/blogfeed"><i class="bx bx-left-arrow-alt"></i>' . ps_h('Till inläggen') . '</a></div>';
    blog_layout_end();
    exit;
}
$posts = Posts::byAuthor($profileId, $identity->id());
$likes = array_sum(array_map(fn ($post) => (int) $post['likes'], $posts));
$first = trim((string) $person['firstname']) ?: (string) $person['username'];
$own = $profileId === $identity->id();

blog_layout_start(['title' => member_name($person), 'active' => $own ? 'mina' : 'inlagg']);
?>
<section class="profile-head">
    <?= avatar($person, 'xl') ?>
    <div class="profile-head__body">
        <h1 class="ps-title profile-head__name"><?= e(member_name($person)) ?></h1>
        <div class="ps-inline profile-head__meta"><span class="ps-muted">@<?= e($person['username']) ?></span><?= platform_role_badge((int) $person['user_id'], (int) $person['access_level']) ?></div>
        <div class="profile-head__stats">
            <span><strong><?= count($posts) ?></strong> <?= ps_h('inlägg') ?></span>
            <span><strong><?= $likes ?></strong> gillningar</span>
        </div>
    </div>
    <?php if ($own): ?><a class="ps-btn profile-head__edit" href="<?= e(core_account_url()) ?>"><i class="bx bx-id-card"></i><span><?= ps_h('Redigera profil') ?></span></a><?php endif; ?>
</section>

<section class="blog-section">
    <div class="blog-section__head"><h2 class="blog-section__title"><?= e($first) ?><?= ps_h('s inlägg') ?></h2></div>
    <?php if ($posts): ?>
        <div class="post-list"><?php foreach ($posts as $post) { include dirname(__DIR__) . '/app/views/partials/post-card.php'; } ?></div>
    <?php else: ?>
        <div class="ps-empty"><i class="bx bx-collection"></i><div><?= e($first) ?> <?= ps_h('har inte skrivit något ännu.') ?></div></div>
    <?php endif; ?>
</section>
<?php blog_layout_end();
