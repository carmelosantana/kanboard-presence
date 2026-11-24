<?php
namespace Kanboard\Plugin\Presence;

use Kanboard\Core\Plugin\Base;

class Plugin extends Base
{
    public function initialize(): void
    {
        $this->api->getMiddlewareHandler()->withMiddleware(new Api\PresenceApiMiddleware($this->container));

        $this->helper->register('presence', '\Kanboard\Plugin\Presence\Helper\PresenceHelper');

        $this->route->addRoute('presence/beat', 'HeartbeatController', 'beat', 'Presence');
        $this->route->addRoute('presence/settings', 'PresenceSettingsController', 'show', 'Presence');
        $this->route->addRoute('presence/settings/save', 'PresenceSettingsController', 'save', 'Presence');
        $this->route->addRoute('presence/toggle', 'PresenceSettingsController', 'toggle', 'Presence');

        $container = $this->container;
        $this->hook->on('template:project:header:after', [
            'template' => 'Presence:project/strip',
            'callable' => function (array $project = []) use ($container) {
                $helper = $container['helper']->presence;
                if (! $helper->active() || empty($project['id'])) {
                    return ['members' => null];
                }
                $uid = $container['userSession']->getId();
                return ['members' => (new Model\PresenceModel($container))->lineup((int) $project['id'], $uid), 'project_id' => (int) $project['id']];
            },
        ]);
        $this->hook->on('template:layout:bottom', ['template' => 'Presence:layout/bottom']);
        $this->hook->on('template:user:sidebar:actions', ['template' => 'Presence:user/sidebar']);
        $this->hook->on('template:header:dropdown', ['template' => 'Presence:header/dropdown']);
        $this->hook->on('template:config:application', ['template' => 'Presence:config/application']);
        $this->hook->on('template:layout:css', ['template' => 'plugins/Presence/Assets/css/presence.css']);
        $this->hook->on('template:layout:js', ['template' => 'plugins/Presence/Assets/js/presence.js']);
    }

    // app.bootstrap: the web session is open here; on API requests the user isn't authenticated yet (middleware handles those).
    public function onStartup(): void
    {
        (new Model\WebRecorder($this->container))->record();
    }

    public function getPluginName(): string        { return 'Presence'; }
    public function getPluginDescription(): string { return t('See which project members are online, away or offline.'); }
    public function getPluginAuthor(): string      { return 'Carmelo Santana'; }
    public function getPluginVersion(): string     { return '1.0.2'; }
    public function getPluginHomepage(): string    { return 'https://github.com/carmelosantana/kanboard-presence'; }
    public function getCompatibleVersion(): string { return '>=1.2.47'; }
}
