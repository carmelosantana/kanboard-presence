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

    public function state(?array $row, bool $isBot, bool $hidden, int $now): string
    {
        if ($row === null || $hidden) {
            return 'offline';
        }
        if ($isBot) {
            return (int) $row['last_seen'] >= $now - self::AGENT_WINDOW ? 'online' : 'offline';
        }
        if ((int) $row['last_beat'] >= $now - self::ONLINE_WINDOW) {
            return 'online';
        }
        return (int) $row['last_seen'] >= $now - self::AWAY_WINDOW ? 'away' : 'offline';
    }

    public function lineup(int $projectId, int $viewerId, ?int $now = null, ?AgentDetector $agents = null): array
    {
        $now = $now ?? time();
        $ids = array_map('intval', array_keys($this->projectUserRoleModel->getAllUsers($projectId)));
        if ($ids === []) {
            return [];
        }

        $users = $this->db->table('users')
            ->columns('id', 'username', 'name', 'email', 'avatar_path')
            ->in('id', $ids)->eq('is_active', 1)->findAll();
        $activeIds = array_map('intval', array_column($users, 'id'));
        $rows = [];
        foreach ($this->db->table(self::TABLE)->in('user_id', $activeIds ?: [0])->findAll() as $r) {
            $rows[(int) $r['user_id']] = $r;
        }
        $bots = array_flip(($agents ?? new AgentDetector($this->container))->agentIds($activeIds));
        $hidden = array_flip(array_map('intval', $this->db->table('user_has_metadata')
            ->in('user_id', $activeIds ?: [0])->eq('name', PresenceSettings::META_HIDDEN)->eq('value', '1')
            ->findAllByColumn('user_id')));

        $order = ['online' => 0, 'away' => 1, 'offline' => 2];
        $out = [];
        foreach ($users as $u) {
            $id = (int) $u['id'];
            $isBot = isset($bots[$id]);
            $out[] = [
                'user_id' => $id,
                'name' => $u['name'] !== '' && $u['name'] !== null ? $u['name'] : $u['username'],
                'username' => $u['username'],
                'email' => (string) $u['email'],
                'avatar_path' => (string) $u['avatar_path'],
                'state' => $this->state($rows[$id] ?? null, $isBot, isset($hidden[$id]), $now),
                'is_bot' => $isBot,
                'is_self' => $id === $viewerId,
            ];
        }
        usort($out, fn ($a, $b) => [$order[$a['state']], strtolower($a['name'])] <=> [$order[$b['state']], strtolower($b['name'])]);
        return $out;
    }
}
