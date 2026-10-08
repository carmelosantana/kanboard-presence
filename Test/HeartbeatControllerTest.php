<?php
require_once 'tests/units/Base.php';
use KanboardTests\units\Base;
use Kanboard\Model\ProjectModel;
use Kanboard\Model\ProjectUserRoleModel;
use Kanboard\Model\UserModel;
use Kanboard\Core\Security\Role;
use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Plugin\Presence\Controller\HeartbeatController;
use Kanboard\Plugin\Presence\Model\PresenceSettings;

class HeartbeatControllerTest extends Base
{
    private int $pid;
    private int $foreign;

    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__.'/../Schema/Sqlite.php';
        \Kanboard\Plugin\Presence\Schema\version_1($this->container['db']->getConnection());
        // The controller uses the real AgentDetector; when Agents is installed beside us, give it its table (as Kanboard's migrations would).
        $agentsSchema = __DIR__.'/../../Agents/Schema/Sqlite.php';
        if (is_file($agentsSchema)) {
            require_once $agentsSchema;
            $pdo = $this->container['db']->getConnection();
            for ($v = 1; $v <= \Kanboard\Plugin\Agents\Schema\VERSION; $v++) {
                ('\\Kanboard\\Plugin\\Agents\\Schema\\version_'.$v)($pdo);
            }
        }
        (new UserModel($this->container))->create(['username' => 'amy', 'name' => 'Amy', 'role' => Role::APP_USER]); // 2
        $this->pid = (new ProjectModel($this->container))->create(['name' => 'Mine']);
        $this->foreign = (new ProjectModel($this->container))->create(['name' => 'Theirs']);
        (new ProjectUserRoleModel($this->container))->addUser($this->pid, 2, Role::PROJECT_MEMBER);
        $this->container['userSession']->initialize($this->container['userModel']->getById(2));
    }

    // Same pattern as Agents/Test/AgentControllerTest: mock Response, capture what json() was given.
    private function beat(array $params): array
    {
        $post = $params + ['csrf_token' => $this->container['token']->getReusableCSRFToken()];
        $get = isset($params['project_id']) ? ['project_id' => $params['project_id']] : ['_' => '1'];
        $this->container['request'] = new \Kanboard\Core\Http\Request($this->container, ['REQUEST_METHOD' => 'POST'], $get, $post, [], []);
        $captured = [];
        $resp = $this->createMock(\Kanboard\Core\Http\Response::class);
        $resp->method('json')->willReturnCallback(function (array $d) use (&$captured) { $captured = $d; });
        $this->container['response'] = $resp;
        (new HeartbeatController($this->container))->beat();
        return $captured;
    }

    public function testBeatWritesLastBeatAndReturnsLineup(): void
    {
        $out = $this->beat(['project_id' => $this->pid]);
        $this->assertTrue($out['enabled']);
        $this->assertSame($this->pid, $out['project_id']);
        $this->assertSame(2, $out['members'][0]['user_id']);
        $this->assertSame('online', $out['members'][0]['state']);
        $this->assertTrue($out['members'][0]['is_self']);
        $this->assertStringContainsString('avatar', $out['members'][0]['avatar_html']);
        $this->assertGreaterThan(0, (int) $this->container['db']->table('presence')->eq('user_id', 2)->findOneColumn('last_beat'));
    }

    public function testBeatWithoutProjectReturnsNoMembers(): void
    {
        $out = $this->beat([]);
        $this->assertNull($out['project_id']);
        $this->assertSame([], $out['members']);
    }

    public function testBeatForForeignProjectIsForbidden(): void
    {
        $this->expectException(AccessForbiddenException::class);
        $this->beat(['project_id' => $this->foreign]);
    }

    public function testBeatWithBadCsrfIsForbidden(): void
    {
        $this->expectException(AccessForbiddenException::class);
        $this->beat(['csrf_token' => 'nope']);
    }

    public function testBeatWhenDisabledReturnsEmpty(): void
    {
        (new PresenceSettings($this->container))->setEnabled(false);
        $this->container['memoryCache']->flush();
        $out = $this->beat(['project_id' => $this->pid]);
        $this->assertFalse($out['enabled']);
        $this->assertSame([], $out['members']);
        $this->assertSame(0, $this->container['db']->table('presence')->count());
    }
}
