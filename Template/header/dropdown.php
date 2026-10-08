<?php if ($this->user->getId() && $this->presence->enabled()): ?>
<li>
    <?= $this->url->icon('circle', $this->presence->hidden() ? t('Appear online') : t('Appear offline'),
        'SettingsController', 'toggle', ['plugin' => 'Presence', 'csrf_token' => $this->app->getToken()->getReusableCSRFToken()]) ?>
</li>
<?php endif ?>
