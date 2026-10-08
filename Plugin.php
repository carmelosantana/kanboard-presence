<?php
namespace Kanboard\Plugin\Presence;

use Kanboard\Core\Plugin\Base;

class Plugin extends Base
{
    public function initialize(): void
    {
        $this->route->addRoute('presence/beat', 'HeartbeatController', 'beat', 'Presence');
        $this->api->getMiddlewareHandler()->withMiddleware(new Api\PresenceApiMiddleware($this->container));
    }

    // app.bootstrap: the web session is open here; on API requests the user isn't authenticated yet (middleware handles those).
    public function onStartup(): void
    {
        (new Model\WebRecorder($this->container))->record();
    }

    public function getPluginName(): string        { return 'Presence'; }
    public function getPluginDescription(): string { return t('See which project members are online, away or offline.'); }
    public function getPluginAuthor(): string      { return 'Carmelo Santana'; }
    public function getPluginVersion(): string     { return '1.0.0'; }
    public function getPluginHomepage(): string    { return 'https://github.com/carmelosantana/kanboard-presence'; }
    public function getCompatibleVersion(): string { return '>=1.2.47'; }
}
