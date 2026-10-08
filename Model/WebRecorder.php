<?php
namespace Kanboard\Plugin\Presence\Model;

use Kanboard\Core\Base;

// Runs on every web request (app.bootstrap). The session timestamp keeps most requests off the DB.
class WebRecorder extends Base
{
    const SESSION_KEY = 'presence_last_write';

    public function record(?int $now = null): bool
    {
        $now = $now ?? time();
        // Queue workers (Core\Queue\JobHandler) log the job author in, flush the session and fire
        // app.bootstrap under CLI; without a REQUEST_METHOD this is not a real web request.
        if ($this->request->getServerVariable('REQUEST_METHOD') === '') {
            return false;
        }
        if (! $this->userSession->isLogged()) {
            return false;
        }
        $last = (int) (session_get(self::SESSION_KEY) ?? 0);
        if ($last > $now - PresenceModel::WEB_THROTTLE) {
            return false;
        }
        session_set(self::SESSION_KEY, $now);
        return (new PresenceModel($this->container))->touch($this->userSession->getId(), PresenceModel::SOURCE_WEB, false, $now);
    }
}
