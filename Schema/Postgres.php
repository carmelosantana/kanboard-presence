<?php
namespace Kanboard\Plugin\Presence\Schema;

const VERSION = 1;

function version_1($pdo)
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS presence (
        user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
        last_seen BIGINT NOT NULL,
        last_beat BIGINT NOT NULL DEFAULT 0,
        source VARCHAR(8) NOT NULL
    )');
}
