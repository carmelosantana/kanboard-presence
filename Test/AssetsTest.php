<?php
require_once 'tests/units/Base.php';
use KanboardTests\units\Base;

class AssetsTest extends Base
{
    public function testCssStylesAllThreeStatesAndReducedMotion(): void
    {
        $css = file_get_contents(__DIR__.'/../Assets/css/presence.css');
        foreach (['[data-presence="online"]', '[data-presence="away"]', '[data-presence="offline"]', '.presence-bot', 'prefers-reduced-motion'] as $needle) {
            $this->assertStringContainsString($needle, $css);
        }
    }

    public function testPlateNotTheInnerAvatarOwnsTheHoverTitle(): void
    {
        $this->assertStringContainsString('.presence-plate > * { pointer-events: none; }', file_get_contents(__DIR__.'/../Assets/css/presence.css'));
    }

    public function testJsDispatchesContractEvent(): void
    {
        $this->assertStringContainsString("'presence:update'", file_get_contents(__DIR__.'/../Assets/js/presence.js'));
    }
}
