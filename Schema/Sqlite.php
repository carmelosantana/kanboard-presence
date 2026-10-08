<?php
namespace Kanboard\Plugin\Presence\Schema;

const VERSION = 1;

function version_1($pdo)
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS presence (
        "user_id" INTEGER PRIMARY KEY,
        "last_seen" INTEGER NOT NULL,
        "last_beat" INTEGER NOT NULL DEFAULT 0,
        "source" VARCHAR(8) NOT NULL,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )');
}
