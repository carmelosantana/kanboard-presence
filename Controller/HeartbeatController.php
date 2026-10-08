<?php
namespace Kanboard\Plugin\Presence\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Plugin\Presence\Model\PresenceModel;
use Kanboard\Plugin\Presence\Model\PresenceSettings;

// POST presence/beat — one call per visible tab per minute: records the heartbeat and returns the lineup.
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
        $model->touch($userId, PresenceModel::SOURCE_WEB, true);

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
