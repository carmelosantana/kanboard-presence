<?php
namespace Kanboard\Plugin\Presence\Helper;

use Kanboard\Core\Base;
use Kanboard\Plugin\Presence\Model\PresenceSettings;

class PresenceHelper extends Base
{
    public function active(): bool
    {
        $s = new PresenceSettings($this->container);
        return $this->userSession->isLogged() && $s->isEnabled() && ! $s->isHidden($this->userSession->getId());
    }

    public function hidden(): bool
    {
        return (new PresenceSettings($this->container))->isHidden($this->userSession->getId());
    }

    public function beatConfig(?int $projectId): array
    {
        return [
            'url' => $this->helper->url->to('HeartbeatController', 'beat', ['plugin' => 'Presence']),
            'csrf' => $this->token->getReusableCSRFToken(),
            'project_id' => $projectId ?: '',
        ];
    }
}
