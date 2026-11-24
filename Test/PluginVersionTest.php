<?php
require_once 'tests/units/Base.php';

use KanboardTests\units\Base;
use Kanboard\Plugin\Presence\Plugin;

// Tag == plugin.json == getPluginVersion(): drift across the three is the most common release defect.
class PluginVersionTest extends Base
{
    public function testVersionIsAligned(): void
    {
        $json = json_decode(file_get_contents(__DIR__.'/../plugin.json'), true);
        $this->assertSame('1.0.2', $json['version']);
        $this->assertSame($json['version'], (new Plugin($this->container))->getPluginVersion());
    }

    // ModMenu's normalizeDeps reads {plugin, min_version, reason} with a BARE version;
    // any other shape (e.g. {name, version: ">=x"}) is silently dropped.
    public function testRecommendsUsesModMenuDependencyShape(): void
    {
        $json = json_decode(file_get_contents(__DIR__.'/../plugin.json'), true);
        $this->assertSame([], $json['requires']);
        $this->assertSame(['Agents'], array_column($json['recommends'], 'plugin'));
        $this->assertSame('0.3.2', $json['recommends'][0]['min_version']);
        $this->assertNotSame('', $json['recommends'][0]['reason'] ?? '');
        $this->assertArrayNotHasKey('name', $json['recommends'][0]);
        $this->assertArrayNotHasKey('version', $json['recommends'][0]);
    }
}
