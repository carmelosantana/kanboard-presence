<?php
require_once 'tests/units/Base.php';
use KanboardTests\units\Base;
use Kanboard\Plugin\Presence\Model\PresenceModel;
use Kanboard\Plugin\Presence\Model\PresenceSettings;

class PresenceTouchTest extends Base
{
    private PresenceModel $m;

    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__.'/../Schema/Sqlite.php';
        \Kanboard\Plugin\Presence\Schema\version_1($this->container['db']->getConnection());
        $this->m = new PresenceModel($this->container);
    }

    private function row(int $uid): ?array
    {
        return $this->container['db']->table('presence')->eq('user_id', $uid)->findOne() ?: null;
    }

    public function testFirstTouchInsertsRow(): void
    {
        $this->assertTrue($this->m->touch(1, 'web', false, 1000));
        $r = $this->row(1);
        $this->assertSame(1000, (int) $r['last_seen']);
        $this->assertSame(0, (int) $r['last_beat']);
        $this->assertSame('web', $r['source']);
    }

    public function testWebWriteThrottledAt30s(): void
    {
        $this->m->touch(1, 'web', false, 1000);
        $this->assertFalse($this->m->touch(1, 'web', false, 1029));
        $this->assertTrue($this->m->touch(1, 'web', false, 1031));
        $this->assertSame(1031, (int) $this->row(1)['last_seen']);
    }

    public function testApiWriteThrottledAt60s(): void
    {
        $this->m->touch(1, 'api', false, 1000);
        $this->assertFalse($this->m->touch(1, 'api', false, 1059));
        $this->assertTrue($this->m->touch(1, 'api', false, 1061));
        $this->assertSame('api', $this->row(1)['source']);
    }

    public function testBeatSetsLastBeatEvenRightAfterAWebWrite(): void
    {
        $this->m->touch(1, 'web', false, 1000);
        $this->assertTrue($this->m->touch(1, 'web', true, 1005));
        $this->assertSame(1005, (int) $this->row(1)['last_beat']);
        $this->assertFalse($this->m->touch(1, 'web', true, 1020));
        $this->assertTrue($this->m->touch(1, 'web', true, 1036));
    }

    public function testFirstBeatInsertsWithLastBeat(): void
    {
        $this->assertSame(2, (new \Kanboard\Model\UserModel($this->container))->create(['username' => 'u2']));
        $this->assertTrue($this->m->touch(2, 'web', true, 500));
        $this->assertSame(500, (int) $this->row(2)['last_beat']);
    }

    public function testHiddenUserIsNotWritten(): void
    {
        (new PresenceSettings($this->container))->setHidden(1, true);
        $this->assertFalse($this->m->touch(1, 'web', true, 1000));
        $this->assertNull($this->row(1));
    }

    public function testDisabledInstanceWritesNothing(): void
    {
        (new PresenceSettings($this->container))->setEnabled(false);
        $this->container['memoryCache']->flush();
        $this->assertFalse($this->m->touch(1, 'api', false, 1000));
        $this->assertNull($this->row(1));
    }

    public function testNonPositiveUserIsIgnored(): void
    {
        $this->assertFalse($this->m->touch(0, 'api', false, 1000));
        $this->assertSame(0, $this->container['db']->table('presence')->count());
    }

    public function testSqlFailureIsLoggedNotThrown(): void
    {
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with($this->stringStartsWith('Presence: '));
        $this->container['logger'] = $logger;
        $this->m = new PresenceModel($this->container);
        $this->container['db']->getConnection()->exec('DROP TABLE presence');

        $this->assertFalse($this->m->touch(1, 'web', false, 1000));
    }
}
