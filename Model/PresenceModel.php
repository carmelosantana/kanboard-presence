<?php
namespace Kanboard\Plugin\Presence\Model;

use Kanboard\Core\Base;
use PicoDb\SQLException;

// One row per user, updated in place. Throttles are conditional UPDATEs (portable across
// SQLite/MySQL/Postgres); first sight INSERTs; a racing duplicate
// comes back as insert() === false. Genuine SQL errors are logged, not thrown.
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

        try {
            $stmt = $this->db->execute($sql, $params);
            if ($stmt !== false && $stmt->rowCount() > 0) {
                return true;
            }
            if ($this->db->table(self::TABLE)->eq('user_id', $userId)->exists()) {
                return false; // throttled
            }

            // A racing first write is a duplicate key, which PicoDb reports as insert() === false.
            return (bool) $this->db->table(self::TABLE)->insert([
                'user_id' => $userId,
                'last_seen' => $now,
                'last_beat' => $beat ? $now : 0,
                'source' => $source,
            ]);
        } catch (SQLException $e) {
            // Real SQL failure (missing table, lost connection): log it, never break the page.
            $this->logger->error('Presence: '.$e->getMessage());
            return false;
        }
    }
}
