<?php if ($this->presence->enabled() && (int) $user['id'] === (int) $this->user->getId()): ?>
<li <?= $this->app->checkMenuSelection('PresenceSettingsController', 'show', 'Presence') ?>>
    <?= $this->url->link(t('Presence'), 'PresenceSettingsController', 'show', ['plugin' => 'Presence', 'user_id' => $user['id']]) ?>
</li>
<?php endif ?>
