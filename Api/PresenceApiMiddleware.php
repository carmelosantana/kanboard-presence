<?php
namespace Kanboard\Plugin\Presence\Api;

use JsonRPC\MiddlewareInterface;
use Kanboard\Core\Base;
use Kanboard\Plugin\Presence\Model\PresenceModel;

// Appended after core AuthenticationMiddleware, so userSession is set for personal-token users.
// App-token (jsonrpc) calls never initialise a user session and are skipped.
class PresenceApiMiddleware extends Base implements MiddlewareInterface
{
    public function execute($username, $password, $procedureName)
    {
        if ($this->userSession->isLogged()) {
            (new PresenceModel($this->container))->touch($this->userSession->getId(), PresenceModel::SOURCE_API);
        }
    }
}
