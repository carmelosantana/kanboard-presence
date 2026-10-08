<?php
namespace Kanboard\Plugin\Presence\Model;

use Kanboard\Core\Base;

// Agents is recommended, not required: without it nobody is a bot. Never read the agents table directly.
class AgentDetector extends Base
{
    private string $class;

    public function __construct($container, string $class = 'Kanboard\\Plugin\\Agents\\Model\\AgentTable')
    {
        parent::__construct($container);
        $this->class = $class;
    }

    public function agentIds(array $userIds): array
    {
        if ($userIds === [] || ! class_exists($this->class) || ! method_exists($this->class, 'agentIds')) {
            return [];
        }
        // Kanboard autoloads every plugin folder but only migrates compatible plugins, so the class can exist without its table.
        try {
            return (new $this->class($this->container))->agentIds($userIds);
        } catch (\PicoDb\SQLException $e) {
            $this->logger->error('Presence: agent detection failed: '.$e->getMessage());
            return [];
        }
    }
}
