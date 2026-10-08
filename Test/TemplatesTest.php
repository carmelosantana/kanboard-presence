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

    public function testToggleFlipsOnlyTheSessionUser(): void
    {
        $_GET = ['csrf_token' => $this->container['token']->getReusableCSRFToken(), 'user_id' => 99];
        $this->container['request'] = new \Kanboard\Core\Http\Request($this->container, ['HTTP_REFERER' => '/x', 'REQUEST_METHOD' => 'GET'], $_GET, ['_' => '1'], [], []);
        $this->container['response'] = $this->createMock(\Kanboard\Core\Http\Response::class);
        (new \Kanboard\Plugin\Presence\Controller\SettingsController($this->container))->toggle();
        $s = new PresenceSettings($this->container);
        $this->assertTrue($s->isHidden(1));
        $this->assertFalse($s->isHidden(99));
    }
}
