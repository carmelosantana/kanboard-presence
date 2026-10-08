<?php if (! empty($members)): ?>
<div class="presence-strip" data-project-id="<?= (int) $project_id ?>" aria-label="<?= t('Project members online status') ?>">
    <?php foreach ($members as $m): ?>
        <span class="presence-plate"
              data-user-id="<?= (int) $m['user_id'] ?>"
              data-presence="<?= $this->text->e($m['state']) ?>"
              data-bot="<?= $m['is_bot'] ? 1 : 0 ?>"
              data-self="<?= $m['is_self'] ? 1 : 0 ?>"
              title="<?= $this->text->e($m['name'].' — '.t($m['state'])) ?>">
            <?= $this->avatar->small($m['user_id'], $m['username'], $m['name'], $m['email'], $m['avatar_path']) ?>
            <?php if ($m['is_bot']): ?><span class="presence-bot" aria-label="<?= t('Agent') ?>"><?= t('BOT') ?></span><?php endif ?>
        </span>
    <?php endforeach ?>
</div>
<?php endif ?>
