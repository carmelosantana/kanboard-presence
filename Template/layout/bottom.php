<?php if ($this->presence->active()): ?>
<?php $cfg = $this->presence->beatConfig(isset($project['id']) ? (int) $project['id'] : null) ?>
<div id="presence-mount" hidden
     data-beat-url="<?= $this->text->e($cfg['url']) ?>"
     data-csrf="<?= $this->text->e($cfg['csrf']) ?>"
     data-project-id="<?= $this->text->e((string) $cfg['project_id']) ?>"></div>
<?php endif ?>
