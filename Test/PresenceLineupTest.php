<?php
require_once 'tests/units/Base.php';
use KanboardTests\units\Base;
use Kanboard\Model\ProjectModel;
use Kanboard\Model\UserModel;
use Kanboard\Model\GroupModel;
use Kanboard\Model\GroupMemberModel;
use Kanboard\Model\ProjectUserRoleModel;
use Kanboard\Model\ProjectGroupRoleModel;
use Kanboard\Core\Security\Role;
use Kanboard\Plugin\Presence\Model\AgentDetector;
use Kanboard\Plugin\Presence\Model\PresenceModel;
use Kanboard\Plugin\Presence\Model\PresenceSettings;

class FakeAgentDetector extends AgentDetector
{
    public array $ids = [];
    public int $calls = 0;
    public function agentIds(array $userIds): array { $this->calls++; return array_values(array_intersect($userIds, $this->ids)); }
}

// Stands in for an Agents plugin that is autoloadable but whose schema never ran.
class UnmigratedAgentTable extends \Kanboard\Core\Base
{
    public function agentIds(array $userIds): array { return $this->db->table('agents_missing')->findAllByColumn('agent_user_id'); }
}

class PresenceLineupTest extends Base
{
    private PresenceModel $m;
    private int $pid;
    const NOW = 100000;

    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__.'/../Schema/Sqlite.php';
        \Kanboard\Plugin\Presence\Schema\version_1($this->container['db']->getConnection());
        $this->m = new PresenceModel($this->container);
        $users = new UserModel($this->container);
        $users->create(['username' => 'zoe', 'name' => 'Zoe']);       // 2
        $users->create(['username' => 'amy', 'name' => 'Amy']);       // 3
        $users->create(['username' => 'bot', 'name' => 'Bot']);       // 4
        $users->create(['username' => 'gina', 'name' => 'Gina']);     // 5 (via group)
        $users->create(['username' => 'outsider', 'name' => 'Out']);  // 6 (not a member)
        $this->pid = (new ProjectModel($this->container))->create(['name' => 'P']);
        $roles = new ProjectUserRoleModel($this->container);
        foreach ([2, 3, 4] as $uid) { $roles->addUser($this->pid, $uid, Role::PROJECT_MEMBER); }
        $gid = (new GroupModel($this->container))->create('crew');
        (new GroupMemberModel($this->container))->addUser($gid, 5);
        (new ProjectGroupRoleModel($this->container))->addGroup($this->pid, $gid, Role::PROJECT_MEMBER);
    }

    private function put(int $uid, int $seenAgo, int $beatAgo = -1, string $src = 'web'): void
    {
        $this->container['db']->table('presence')->insert([
            'user_id' => $uid, 'last_seen' => self::NOW - $seenAgo,
            'last_beat' => $beatAgo < 0 ? 0 : self::NOW - $beatAgo, 'source' => $src,
        ]);
    }

    private function byId(array $lineup): array
    {
        $out = [];
        foreach ($lineup as $m) { $out[$m['user_id']] = $m; }
        return $out;
    }

    public function testStateThresholds(): void
    {
        $n = self::NOW;
        $this->assertSame('online', $this->m->state(['last_seen' => $n, 'last_beat' => $n - 120], false, false, $n));
        $this->assertSame('away', $this->m->state(['last_seen' => $n - 900, 'last_beat' => $n - 121], false, false, $n));
        $this->assertSame('offline', $this->m->state(['last_seen' => $n - 901, 'last_beat' => 0], false, false, $n));
        $this->assertSame('offline', $this->m->state(null, false, false, $n));
        $this->assertSame('online', $this->m->state(['last_seen' => $n - 300, 'last_beat' => 0], true, false, $n));
        $this->assertSame('offline', $this->m->state(['last_seen' => $n - 301, 'last_beat' => 0], true, false, $n));
    }

    public function testLineupIncludesDirectAndGroupMembersOnly(): void
    {
        $ids = array_column($this->m->lineup($this->pid, 2, self::NOW, new FakeAgentDetector($this->container)), 'user_id');
        sort($ids);
        $this->assertSame([2, 3, 4, 5], $ids);
    }

    public function testOrderingAndFlags(): void
    {
        $this->put(2, 10, 10);     // Zoe online
        $this->put(3, 10, 10);     // Amy online
        $this->put(5, 600);        // Gina away
        $this->put(4, 60, -1, 'api');
        $det = new FakeAgentDetector($this->container);
        $det->ids = [4];

        $l = $this->m->lineup($this->pid, 3, self::NOW, $det);
        $this->assertSame([3, 4, 2, 5], array_column($l, 'user_id')); // online: Amy, Bot, Zoe; then away: Gina
        $by = $this->byId($l);
        $this->assertTrue($by[3]['is_self']);
        $this->assertFalse($by[2]['is_self']);
        $this->assertTrue($by[4]['is_bot']);
        $this->assertSame('online', $by[4]['state']);
        $this->assertSame('away', $by[5]['state']);
        $this->assertSame(1, $det->calls);
    }

    public function testNamesSortCaseInsensitivelyBeyondAscii(): void
    {
        $users = new UserModel($this->container);
        $eva = $users->create(['username' => 'eva', 'name' => 'Éva']);
        $edo = $users->create(['username' => 'edo', 'name' => 'édouard']);
        $roles = new ProjectUserRoleModel($this->container);
        $roles->addUser($this->pid, $eva, Role::PROJECT_MEMBER);
        $roles->addUser($this->pid, $edo, Role::PROJECT_MEMBER);
        $ids = array_column($this->m->lineup($this->pid, 2, self::NOW, new FakeAgentDetector($this->container)), 'user_id');
        $this->assertLessThan(array_search($eva, $ids, true), array_search($edo, $ids, true), 'édouard sorts before Éva');
    }

    public function testHiddenUserReadsOfflineEvenWithFreshRow(): void
    {
        $this->put(2, 1, 1);
        (new PresenceSettings($this->container))->setHidden(2, true);
        $by = $this->byId($this->m->lineup($this->pid, 3, self::NOW, new FakeAgentDetector($this->container)));
        $this->assertSame('offline', $by[2]['state']);
    }

    public function testInactiveUserIsExcluded(): void
    {
        $this->put(4, 1, -1, 'api');
        (new UserModel($this->container))->disable(4);
        $ids = array_column($this->m->lineup($this->pid, 2, self::NOW, new FakeAgentDetector($this->container)), 'user_id');
        $this->assertNotContains(4, $ids);
    }

    public function testDetectorWithoutAgentsPluginReturnsEmpty(): void
    {
        $this->assertSame([], (new AgentDetector($this->container, 'No\\Such\\AgentTable'))->agentIds([1, 2]));
    }

    public function testDetectorDegradesWhenAgentsTableMissing(): void
    {
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with($this->stringContains('Presence: agent detection failed'));
        $this->container['logger'] = $logger;
        $this->assertSame([], (new AgentDetector($this->container, UnmigratedAgentTable::class))->agentIds([1, 2]));
    }
}
