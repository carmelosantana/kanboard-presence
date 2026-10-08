<?php
namespace Kanboard\Plugin\Presence\Model;

use Kanboard\Core\Base;

// Admin switch (app config) and per-user "Appear offline" (user metadata). ConfigModel::get only
// falls back on '' so a stored '0' reads back as '0'.
class PresenceSettings extends Base
{
    const CONFIG_ENABLED = 'presence_enabled';
    const META_HIDDEN = 'presence_hidden';

    public function isEnabled(): bool
    {
        return $this->configModel->get(self::CONFIG_ENABLED, '1') === '1';
    }

    public function setEnabled(bool $on): void
    {
        $this->configModel->save([self::CONFIG_ENABLED => $on ? '1' : '0']);
    }

    public function isHidden(int $userId): bool
    {
        return $this->userMetadataModel->get($userId, self::META_HIDDEN, '0') === '1';
    }

    public function setHidden(int $userId, bool $hidden): void
    {
        $this->userMetadataModel->save($userId, [self::META_HIDDEN => $hidden ? '1' : '0']);
    }
}
