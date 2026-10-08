<li <?= $this->app->checkMenuSelection('SettingsController', 'show', 'Presence') ?>>
    <?= $this->url->link(t('Presence'), 'SettingsController', 'show', ['plugin' => 'Presence', 'user_id' => $user['id']]) ?>
</li>
