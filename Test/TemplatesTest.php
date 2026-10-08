<?php
require_once 'tests/units/Base.php';
use KanboardTests\units\Base;
use Kanboard\Model\ProjectModel;
use Kanboard\Model\ProjectUserRoleModel;
use Kanboard\Core\Security\Role;
use Kanboard\Plugin\Presence\Plugin;
use Kanboard\Plugin\Presence\Model\PresenceSettings;

class TemplatesTest extends Base
{
    private int $pid;

    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__.'/../Schema/Sqlite.php';
        \Kanboard\Plugin\Presence\Schema\version_1($this->container['db']->getConnection());
        // Hermetic: if Agents is autoloadable but unmigrated, the detector logs and degrades to no bots.
        $this->container['logger'] = $this->createMock(\Psr\Log\LoggerInterface::class);
        // tests/units/Base.php doesn't register ApiProvider; initialize() needs $this->api.
        if (! isset($this->container['api'])) {
            $this->container->register(new \Kanboard\ServiceProvider\ApiProvider());
        }
        (new Plugin($this->container))->initialize();
        $this->pid = (new ProjectModel($this->container))->create(['name' => 'P']);
        (new ProjectUserRoleModel($this->container))->addUser($this->pid, 1, Role::PROJECT_MANAGER);
        $this->container['userSession']->initialize($this->container['userModel']->getById(1));
    }

    private function hook(string $name, array $vars = []): string
    {
        return $this->container['helper']->hook->render($name, $vars);
    }

    public function testStripCarriesContractAttributes(): void
    {
        $html = $this->hook('template:project:header:after', ['project' => ['id' => $this->pid]]);
        $this->assertStringContainsString('class="presence-strip"', $html);
        $this->assertStringContainsString('data-user-id="1"', $html);
        $this->assertMatchesRegularExpression('/data-presence="(online|away|offline)"/', $html);
        $this->assertStringContainsString('data-bot="0"', $html);
        $this->assertStringContainsString('data-self="1"', $html);
    }

    public function testMountRendersOnLayoutBottomWithoutInlineScript(): void
    {
        $html = $this->hook('template:layout:bottom');
        $this->assertStringContainsString('id="presence-mount"', $html);
        $this->assertStringContainsString('data-beat-url=', $html);
        $this->assertStringContainsString('data-csrf=', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function testNothingRendersWhenDisabled(): void
    {
        (new PresenceSettings($this->container))->setEnabled(false);
        $this->container['memoryCache']->flush();
        $this->assertSame('', trim($this->hook('template:layout:bottom')));
        $this->assertSame('', trim($this->hook('template:project:header:after', ['project' => ['id' => $this->pid]])));
        $this->assertSame('', trim($this->hook('template:header:dropdown')));
        $this->assertSame('', trim($this->hook('template:user:sidebar:actions', ['user' => ['id' => 1]])));
    }

    public function testNothingRendersForHiddenViewer(): void
    {
        (new PresenceSettings($this->container))->setHidden(1, true);
        $this->assertSame('', trim($this->hook('template:layout:bottom')));
    }

    public function testAvatarMenuShowsToggleLabelForCurrentState(): void
    {
        $this->assertStringContainsString('Appear offline', $this->hook('template:header:dropdown'));
        (new PresenceSettings($this->container))->setHidden(1, true);
        $this->assertStringContainsString('Appear online', $this->hook('template:header:dropdown'));
    }

    public function testAdminCheckboxPostsZeroWhenUnchecked(): void
    {
        $html = $this->hook('template:config:application', ['values' => [], 'errors' => []]);
        $this->assertMatchesRegularExpression('/type="hidden" name="presence_enabled" value="0"/', $html);
        // FormHelper::checkbox emits class="" between name and value.
        $this->assertMatchesRegularExpression('/type="checkbox" name="presence_enabled"[^>]* value="1"/', $html);
    }

    // Mock Response; Request falls back to superglobals for empty arrays, so every array passed is non-empty.
    private function controller(array $server, array $get, array $post, ?callable $onRedirect = null): \Kanboard\Plugin\Presence\Controller\SettingsController
    {
        unset($this->container['request'], $this->container['response']); // core factories freeze once read (e.g. by the url helper)
        $this->container['request'] = new \Kanboard\Core\Http\Request($this->container, $server, $get, $post, [], []);
        $resp = $this->createMock(\Kanboard\Core\Http\Response::class);
        if ($onRedirect) {
            $resp->method('redirect')->willReturnCallback($onRedirect);
        }
        $this->container['response'] = $resp;
        return new \Kanboard\Plugin\Presence\Controller\SettingsController($this->container);
    }

    private function toggleWithReferer(string $referer): string
    {
        $to = '';
        $get = ['csrf_token' => $this->container['token']->getReusableCSRFToken(), 'user_id' => 99];
        $server = ['HTTP_REFERER' => $referer, 'HTTP_HOST' => 'kanboard.test:8080', 'REQUEST_METHOD' => 'GET'];
        $this->controller($server, $get, ['_' => '1'], function ($url) use (&$to) { $to = $url; })->toggle();
        return $to;
    }

    public function testToggleFlipsOnlyTheSessionUser(): void
    {
        $this->toggleWithReferer('/x');
        $s = new PresenceSettings($this->container);
        $this->assertTrue($s->isHidden(1));
        $this->assertFalse($s->isHidden(99));
    }

    public function testToggleReturnsToSameHostRefererOnly(): void
    {
        $this->assertSame('http://kanboard.test/board/1', $this->toggleWithReferer('http://kanboard.test/board/1'));
        $this->assertSame('/x', $this->toggleWithReferer('/x'));
        foreach (['https://evil.example/x', '//evil.example/x', '/\\evil.example/x', 'javascript:alert(1)'] as $bad) {
            $to = $this->toggleWithReferer($bad);
            $this->assertStringContainsString('controller=DashboardController', $to, $bad);
            $this->assertStringNotContainsString('evil', $to, $bad);
        }
    }

    public function testShowForAnotherUserRedirectsToOwnPage(): void
    {
        $other = $this->container['userModel']->create(['username' => 'bob', 'name' => 'Bob']);
        $to = null;
        $this->controller(['REQUEST_METHOD' => 'GET'], ['user_id' => $other], ['_' => '1'], function ($url) use (&$to) { $to = $url; })->show();
        $this->assertNotNull($to);
        $this->assertStringContainsString('user_id=1', $to);
        $this->assertStringNotContainsString('user_id='.$other, $to);
    }

    public function testSaveSetsAndClearsTheSessionUsersFlag(): void
    {
        $s = new PresenceSettings($this->container);
        $csrf = $this->container['token']->getCSRFToken();
        $this->controller(['REQUEST_METHOD' => 'POST'], ['_' => '1'], ['csrf_token' => $csrf, 'presence_hidden' => '1'])->save();
        $this->assertTrue($s->isHidden(1));
        $this->controller(['REQUEST_METHOD' => 'POST'], ['_' => '1'], ['csrf_token' => $csrf, 'presence_hidden' => '0'])->save();
        $this->assertFalse($s->isHidden(1));
    }

    public function testProfileSidebarItemOnlyOnOwnProfile(): void
    {
        $this->assertStringContainsString('Presence', $this->hook('template:user:sidebar:actions', ['user' => ['id' => 1]]));
        $this->assertSame('', trim($this->hook('template:user:sidebar:actions', ['user' => ['id' => 2]])));
    }
}
