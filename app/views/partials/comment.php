<?php
/**
 * One comment. Needs $comment (a Comments row) and $canDelete.
 * Signed-in members link to their profile; guests show the name they gave.
 */
$isMember = (int) $comment['user_id'] > 0 && !empty($comment['username']);
$level = (int) ($comment['access_level'] ?? 0);
$name = $isMember ? member_name($comment) : (string) $comment['user_name'];
?>
<li class="comment" id="comment-<?= (int) $comment['id'] ?>">
    <?php if ($isMember): ?>
        <a class="comment__avatar" href="/profile?user_id=<?= (int) $comment['user_id'] ?>" tabindex="-1" aria-hidden="true"><?= avatar($comment, 'sm') ?></a>
    <?php else: ?>
        <span class="comment__avatar" aria-hidden="true"><?= avatar(['firstname' => $name], 'sm', 'comment__guest') ?></span>
    <?php endif; ?>
    <div class="comment__body">
        <div class="comment__head">
            <?php if ($isMember): ?>
                <a class="comment__name" href="/profile?user_id=<?= (int) $comment['user_id'] ?>"><?= e($name) ?></a>
            <?php else: ?>
                <span class="comment__name"><?= e($name) ?></span><span class="ps-badge comment__role"><?= ps_h('Gäst') ?></span>
            <?php endif; ?>
            <?php if ($isMember): ?><?= platform_role_badge((int) $comment['user_id'], $level) ?><?php endif; ?>
            <?php if ($canDelete): ?>
                <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm ps-btn--icon comment__delete" data-comment-delete="<?= (int) $comment['id'] ?>" aria-label="<?= ps_h('Radera kommentaren') ?>" title="<?= ps_h('Radera') ?>"><i class="bx bx-trash"></i></button>
            <?php endif; ?>
        </div>
        <p class="comment__text"><?= e_links($comment['comment']) ?></p>
    </div>
</li>
