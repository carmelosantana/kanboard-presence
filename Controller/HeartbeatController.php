<?php
namespace Kanboard\Plugin\Presence\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Plugin\Presence\Model\PresenceModel;
use Kanboard\Plugin\Presence\Model\PresenceSettings;

// POST presence/beat — one call per visible tab per minute: records the heartbeat and returns the lineup.
// With idle=1 (no input for 30 min, every 5 min) it records activity only, so the user reads away.
class HeartbeatController extends BaseController
{
    public function beat()
    {
        $this->checkReusableCSRFParam();
        $projectId = $this->request->getIntegerParam('project_id');
        $userId = $this->userSession->getId();

        if ($projectId > 0 && ! $this->projectPermissionModel->isUserAllowed($projectId, $userId)) {
            throw new AccessForbiddenException();
        }
        if (! (new PresenceSettings($this->container))->isEnabled()) {
            return $this->response->json(['enabled' => false, 'project_id' => $projectId ?: null, 'members' => []]);
        }

        $model = new PresenceModel($this->container);
        // An idle tab (no input for 30 min) still counts as activity but is no heartbeat, so it reads away.
        $model->touch($userId, PresenceModel::SOURCE_WEB, $this->request->getIntegerParam('idle') !== 1);

        $members = [];
        if ($projectId > 0) {
            foreach ($model->lineup($projectId, $userId) as $m) {
                $members[] = [
                    'user_id' => $m['user_id'],
                    'name' => $m['name'],
                    'avatar_html' => $this->helper->avatar->small($m['user_id'], $m['username'], $m['name'], $m['email'], $m['avatar_path']),
                    'state' => $m['state'],
                    'is_bot' => $m['is_bot'],
                    'is_self' => $m['is_self'],
                ];
            }
        }
        $this->response->json(['enabled' => true, 'project_id' => $projectId ?: null, 'members' => $members]);
    }
}
