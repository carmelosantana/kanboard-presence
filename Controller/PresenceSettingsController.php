<?php
namespace Kanboard\Plugin\Presence\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Plugin\Presence\Model\PresenceSettings;

// The viewer manages only their own flag; user_id is used for layout but writes target the session user.
class PresenceSettingsController extends BaseController
{
    public function show()
    {
        $user = $this->getUser();
        $uid = (int) $this->userSession->getId();
        // The page edits the viewer's own flag, so another user's profile (admins can open any) goes to your own.
        if ((int) $user['id'] !== $uid) {
            $this->response->redirect($this->helper->url->to('PresenceSettingsController', 'show', ['plugin' => 'Presence', 'user_id' => $uid]));
            return;
        }
        $this->response->html($this->helper->layout->user('Presence:user/settings', [
            'user' => $user,
            'hidden' => (new PresenceSettings($this->container))->isHidden($uid),
            'title' => t('Presence'),
        ]));
    }

    public function save()
    {
        $this->checkCSRFForm();
        $values = $this->request->getValues();
        (new PresenceSettings($this->container))->setHidden($this->userSession->getId(), ($values['presence_hidden'] ?? '0') === '1');
        $this->flash->success(t('Presence settings saved.'));
        $this->response->redirect($this->helper->url->to('PresenceSettingsController', 'show', ['plugin' => 'Presence', 'user_id' => $this->userSession->getId()]));
    }

    public function toggle()
    {
        $this->checkReusableGETCSRFParam();
        $s = new PresenceSettings($this->container);
        $uid = $this->userSession->getId();
        $s->setHidden($uid, ! $s->isHidden($uid));
        $this->response->redirect($this->sameOriginReferer() ?: $this->helper->url->to('DashboardController', 'show'));
    }

    // Accept the referer only when it is a path on this site or an absolute URL on the request host (ports ignored).
    private function sameOriginReferer(): string
    {
        $ref = (string) $this->request->getServerVariable('HTTP_REFERER');
        if ($ref === '') {
            return '';
        }
        $parts = parse_url($ref);
        if ($parts === false) {
            return '';
        }
        $host = $parts['host'] ?? '';
        if ($host === '') {
            // Relative: a plain path only (no scheme such as javascript:, no //host or /\host).
            return empty($parts['scheme']) && preg_match('#^/(?![/\\\\])#', $ref) ? $ref : '';
        }
        $strip = fn (string $h) => strtolower(preg_replace('/:\d+$/', '', $h));
        return $strip($host) === $strip((string) $this->request->getServerVariable('HTTP_HOST')) ? $ref : '';
    }
}
