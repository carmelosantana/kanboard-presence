<?php
namespace Kanboard\Plugin\Presence\Model;

use Kanboard\Core\Base;

// One row per user, updated in place. Throttles are conditional UPDATEs (portable across
// SQLite/MySQL/Postgres); first sight INSERTs and swallows a racing duplicate.
class PresenceModel extends Base
{
    const TABLE = 'presence';
    const ONLINE_WINDOW = 120;
    const AWAY_WINDOW = 900;
    const AGENT_WINDOW = 300;
    const WEB_THROTTLE = 30;
    const API_THROTTLE = 60;
    const BEAT_THROTTLE = 30;
    const SOURCE_WEB = 'web';
    const SOURCE_API = 'api';

    public function touch(int $userId, string $source, bool $beat = false, ?int $now = null): bool
    {
        $now = $now ?? time();
        $settings = new PresenceSettings($this->container);
        if ($userId <= 0 || ! $settings->isEnabled() || $settings->isHidden($userId)) {
            return false;
        }
        $source = $source === self::SOURCE_API ? self::SOURCE_API : self::SOURCE_WEB;

        if ($beat) {
            $sql = 'UPDATE '.self::TABLE.' SET last_seen = ?, last_beat = ?, source = ? WHERE user_id = ? AND last_beat < ?';
            $params = [$now, $now, $source, $userId, $now - self::BEAT_THROTTLE];
        } else {
            $throttle = $source === self::SOURCE_API ? self::API_THROTTLE : self::WEB_THROTTLE;
            $sql = 'UPDATE '.self::TABLE.' SET last_seen = ?, source = ? WHERE user_id = ? AND last_seen < ?';
            $params = [$now, $source, $userId, $now - $throttle];
        }

        $stmt = $this->db->execute($sql, $params);
        if ($stmt !== false && $stmt->rowCount() > 0) {
            return true;
        }
        if ($this->db->table(self::TABLE)->eq('user_id', $userId)->exists()) {
            return false; // throttled
        }

        try {
            return (bool) $this->db->table(self::TABLE)->insert([
                'user_id' => $userId,
                'last_seen' => $now,
                'last_beat' => $beat ? $now : 0,
                'source' => $source,
            ]);
        } catch (\Exception $e) {
            return false; // concurrent first write won the race
        }
    }
}
