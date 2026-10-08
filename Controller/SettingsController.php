<?php
namespace Kanboard\Plugin\Presence\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Plugin\Presence\Model\PresenceSettings;

// The viewer manages only their own flag; user_id is used for layout but writes target the session user.
class SettingsController extends BaseController
{
    public function show()
    {
        $user = $this->getUser();
        $this->response->html($this->helper->layout->user('Presence:user/settings', [
            'user' => $user,
            'hidden' => (new PresenceSettings($this->container))->isHidden($this->userSession->getId()),
            'title' => t('Presence'),
        ]));
    }

    public function save()
    {
        $this->checkCSRFForm();
        $values = $this->request->getValues();
        (new PresenceSettings($this->container))->setHidden($this->userSession->getId(), ($values['presence_hidden'] ?? '0') === '1');
        $this->flash->success(t('Presence settings saved.'));
        $this->response->redirect($this->helper->url->to('SettingsController', 'show', ['plugin' => 'Presence', 'user_id' => $this->userSession->getId()]));
    }

    public function toggle()
    {
        $this->checkReusableGETCSRFParam();
        $s = new PresenceSettings($this->container);
        $uid = $this->userSession->getId();
        $s->setHidden($uid, ! $s->isHidden($uid));
        $this->response->redirect($this->request->getServerVariable('HTTP_REFERER') ?: $this->helper->url->to('DashboardController', 'show'));
    }
}
