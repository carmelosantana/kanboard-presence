<div class="page-header"><h2><?= t('Presence') ?></h2></div>
<form method="post" action="<?= $this->url->href('PresenceSettingsController', 'save', ['plugin' => 'Presence']) ?>">
    <?= $this->form->csrf() ?>
    <input type="hidden" name="presence_hidden" value="0">
    <?= $this->form->checkbox('presence_hidden', t('Appear offline — teammates always see you as offline and nothing is recorded'), 1, $hidden) ?>
    <div class="form-actions"><button type="submit" class="btn btn-blue"><?= t('Save') ?></button></div>
</form>
