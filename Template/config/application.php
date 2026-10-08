<fieldset>
    <legend><?= t('Presence') ?></legend>
    <input type="hidden" name="presence_enabled" value="0">
    <?= $this->form->checkbox('presence_enabled', t('Show who is online in projects'), 1,
        ($values['presence_enabled'] ?? $this->app->config('presence_enabled', '1')) == 1) ?>
</fieldset>
