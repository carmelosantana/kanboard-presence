<?php
require_once 'tests/units/Base.php';
use KanboardTests\units\Base;
use Kanboard\Plugin\Presence\Model\PresenceSettings;

class PresenceSettingsTest extends Base
{
    public function testEnabledByDefaultAndToggleable(): void
    {
        $s = new PresenceSettings($this->container);
        $this->assertTrue($s->isEnabled());
        $s->setEnabled(false);
        $this->container['memoryCache']->flush();
        $this->assertFalse($s->isEnabled());
    }

    public function testHiddenIsPerUser(): void
    {
        $s = new PresenceSettings($this->container);
        $this->assertFalse($s->isHidden(1));
        $s->setHidden(1, true);
        $this->assertTrue($s->isHidden(1));
        $s->setHidden(1, false);
        $this->assertFalse($s->isHidden(1));
    }
}
