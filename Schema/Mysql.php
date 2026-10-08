<?php
namespace Kanboard\Plugin\Presence\Schema;

const VERSION = 1;

function version_1($pdo)
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS presence (
        user_id INT NOT NULL,
        last_seen BIGINT NOT NULL,
        last_beat BIGINT NOT NULL DEFAULT 0,
        source VARCHAR(8) NOT NULL,
        PRIMARY KEY(user_id),
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB CHARSET=utf8');
}
