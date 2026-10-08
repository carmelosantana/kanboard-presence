<?php
require_once 'tests/units/Base.php';
use KanboardTests\units\Base;
use Kanboard\Model\UserModel;
use Kanboard\Plugin\Presence\Api\PresenceApiMiddleware;
use Kanboard\Plugin\Presence\Model\WebRecorder;

class WriteHooksTest extends Base
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__.'/../Schema/Sqlite.php';
        \Kanboard\Plugin\Presence\Schema\version_1($this->container['db']->getConnection());
        (new UserModel($this->container))->create(['username' => 'agent', 'name' => 'Agent']); // id 2
    }

    private function login(int $uid): void
    {
        $this->container['userSession']->initialize($this->container['userModel']->getById($uid));
    }

    // Request falls back to superglobals for empty arrays, so pass non-empty ones.
    private function setServer(array $server): void
    {
        $this->container['request'] = new \Kanboard\Core\Http\Request($this->container, $server, ['_' => '1'], ['_' => '1'], [], []);
    }

    private function row(int $uid): ?array
    {
        return $this->container['db']->table('presence')->eq('user_id', $uid)->findOne() ?: null;
    }

    public function testWebRecorderSkipsAnonymous(): void
    {
        $this->setServer(['REQUEST_METHOD' => 'GET']);
        $this->assertFalse((new WebRecorder($this->container))->record(1000));
        $this->assertSame(0, $this->container['db']->table('presence')->count());
    }

    public function testWebRecorderWritesAndSessionThrottles(): void
    {
        $this->setServer(['REQUEST_METHOD' => 'GET']);
        $this->login(2);
        $r = new WebRecorder($this->container);
        $this->assertTrue($r->record(1000));
        $this->container['db']->table('presence')->eq('user_id', 2)->remove(); // prove the 2nd call never reaches the DB
        $this->assertFalse($r->record(1010));
        $this->assertNull($this->row(2));
        $this->assertTrue($r->record(1031));
    }

    public function testWebRecorderSkipsNonWebBootstrap(): void
    {
        $this->setServer(['argv' => []]); // queue worker: JobHandler logs the job author in, then fires app.bootstrap under CLI
        $this->login(2);
        $this->assertFalse((new WebRecorder($this->container))->record(1000));
        $this->assertNull($this->row(2));
    }

    public function testApiMiddlewareRecordsPersonalTokenUser(): void
    {
        $this->login(2);
        (new PresenceApiMiddleware($this->container))->execute('agent', 'token', 'getMe');
        $this->assertSame('api', $this->row(2)['source']);
    }

    public function testAppTokenCallWritesNothing(): void
    {
        (new PresenceApiMiddleware($this->container))->execute('jsonrpc', 'apptoken', 'getAllProjects');
        $this->assertSame(0, $this->container['db']->table('presence')->count());
    }

    public function testPluginRegistersMiddlewareAfterCoreAuth(): void
    {
        if (! isset($this->container['api'])) { // tests/units/Base.php does not register ApiProvider
            $this->container->register(new \Kanboard\ServiceProvider\ApiProvider());
        }
        (new \Kanboard\Plugin\Presence\Plugin($this->container))->initialize();
        $handler = $this->container['api']->getMiddlewareHandler();
        $prop = (new \ReflectionClass($handler))->getProperty('middleware');
        $prop->setAccessible(true);
        $list = $prop->getValue($handler);
        $this->assertInstanceOf(\Kanboard\Api\Middleware\AuthenticationMiddleware::class, $list[0]);
        $this->assertInstanceOf(PresenceApiMiddleware::class, end($list));
    }
}
