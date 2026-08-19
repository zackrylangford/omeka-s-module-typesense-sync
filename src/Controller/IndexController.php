<?php
namespace TypesenseSync\Controller;

use Laminas\Http\Client;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use TypesenseSync\Module;

class IndexController extends AbstractActionController
{
    /** Namespace for the trigger-sync CSRF token. */
    private const CSRF_NAME = 'typesensesync_csrf';

    public function indexAction()
    {
        $settings = $this->settings();
        $endpoint = (string) $settings->get(Module::SETTING_ENDPOINT, '');
        $hasKey = trim((string) $settings->get(Module::SETTING_API_KEY, '')) !== '';

        // The key itself is deliberately not passed to the template. The page
        // only needs to know whether one is set.
        return new ViewModel([
            'endpoint' => $endpoint,
            'hasKey' => $hasKey,
            'configured' => $endpoint !== '',
            'lastSync' => (string) $settings->get(Module::SETTING_LAST_SYNC, ''),
            'csrfToken' => $this->csrfToken(),
        ]);
    }

    public function triggerAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute('admin/typesense-sync');
        }

        // A POST-only check is not an authorisation check: without a token, any
        // page anywhere could make an administrator's browser fire this.
        if (!$this->csrfValid((string) $this->params()->fromPost('csrf', ''))) {
            $this->messenger()->addError('Invalid or expired form token. Please try again.');
            return $this->redirect()->toRoute('admin/typesense-sync');
        }

        $settings = $this->settings();
        $endpoint = trim((string) $settings->get(Module::SETTING_ENDPOINT, ''));
        $apiKey = trim((string) $settings->get(Module::SETTING_API_KEY, ''));

        if ($endpoint === '') {
            $this->messenger()->addError(
                'Set the sync endpoint in Modules → Typesense Sync → Configure first.'
            );
            return $this->redirect()->toRoute('admin/typesense-sync');
        }

        try {
            $client = new Client();
            $client->setUri($endpoint);
            $client->setMethod('POST');
            $client->setOptions(['timeout' => 120]);
            $client->getRequest()->getHeaders()->addHeaderLine('Content-Type', 'application/json');
            if ($apiKey !== '') {
                $client->getRequest()->getHeaders()->addHeaderLine('x-api-key', $apiKey);
            }

            $response = $client->send();

            if ($response->isSuccess()) {
                $this->messenger()->addSuccess($this->summarise($response->getBody()));
                $settings->set(Module::SETTING_LAST_SYNC, date('Y-m-d H:i:s'));
            } else {
                $this->messenger()->addError(sprintf(
                    'The sync endpoint returned HTTP %d.',
                    $response->getStatusCode()
                ));
            }
        } catch (\Exception $e) {
            $this->messenger()->addError('Could not reach the sync endpoint: ' . $e->getMessage());
        }

        return $this->redirect()->toRoute('admin/typesense-sync');
    }

    /**
     * Turn the endpoint's response into a sentence, if it is the shape we know.
     *
     * The body is never echoed. It comes from a URL an administrator typed, so
     * it is not trusted content to put in a flash message.
     */
    private function summarise(string $body): string
    {
        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['collections'], $data['items'])) {
            return 'Sync completed.';
        }

        return sprintf(
            'Sync completed. %d collection(s) and %d item(s) in %.2f seconds.',
            (int) $data['collections'],
            (int) $data['items'],
            (float) ($data['duration_seconds'] ?? 0)
        );
    }

    private function csrfToken(): string
    {
        return (new \Laminas\Validator\Csrf(['name' => self::CSRF_NAME, 'timeout' => 7200]))
            ->getHash();
    }

    private function csrfValid(string $token): bool
    {
        return (new \Laminas\Validator\Csrf(['name' => self::CSRF_NAME, 'timeout' => 7200]))
            ->isValid($token);
    }
}
