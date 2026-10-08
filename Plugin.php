<?php
namespace Kanboard\Plugin\Presence;

use Kanboard\Core\Plugin\Base;

class Plugin extends Base
{
    public function initialize(): void
    {
    }

    public function getPluginName(): string        { return 'Presence'; }
    public function getPluginDescription(): string { return t('See which project members are online, away or offline.'); }
    public function getPluginAuthor(): string      { return 'Carmelo Santana'; }
    public function getPluginVersion(): string     { return '1.0.0'; }
    public function getPluginHomepage(): string    { return 'https://github.com/carmelosantana/kanboard-presence'; }
    public function getCompatibleVersion(): string { return '>=1.2.47'; }
}
