<?php
require_once 'tests/units/Base.php';
use KanboardTests\units\Base;

class SchemaTest extends Base
{
    public function testSchemaCreatesPresenceTable(): void
    {
        require_once __DIR__.'/../Schema/Sqlite.php';
        \Kanboard\Plugin\Presence\Schema\version_1($this->container['db']->getConnection());
        $this->container['db']->table('presence')->insert(['user_id' => 1, 'last_seen' => 10, 'source' => 'web']);
        $row = $this->container['db']->table('presence')->eq('user_id', 1)->findOne();
        $this->assertSame(0, (int) $row['last_beat']);
    }

    public function testAllThreeDialectsDeclareVersion1(): void
    {
        foreach (['Sqlite', 'Mysql', 'Postgres'] as $d) {
            $src = file_get_contents(__DIR__."/../Schema/$d.php");
            $this->assertStringContainsString('const VERSION = 1;', $src, $d);
            $this->assertStringContainsString('function version_1', $src, $d);
            $this->assertStringContainsString('presence', $src, $d);
        }
    }
}
