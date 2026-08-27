<?php
namespace TypesenseSync;

use Laminas\Mvc\Controller\AbstractController;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Module\AbstractModule;

/**
 * Typesense Sync — trigger a rebuild of the external search index.
 *
 * Omeka is the system of record; a Typesense collection is a derived copy that
 * a search UI queries. This module holds the address of the endpoint that
 * rebuilds that copy, the credential for it, and a button that calls it.
 *
 * **The configuration form is the only place these settings are written.**
 * There used to be a second form on the module's own admin page saving the same
 * keys, which meant two code paths to keep correct and two chances to leak the
 * credential. The admin page is now read-only apart from the trigger button.
 */
class Module extends AbstractModule
{
    public const SETTING_ENDPOINT = 'typesense_sync_endpoint';
    public const SETTING_API_KEY = 'typesense_sync_api_key';
    public const SETTING_LAST_SYNC = 'typesense_last_sync';

    /** Checkbox name used to explicitly clear a stored key. */
    private const CLEAR_API_KEY = 'typesense_sync_clear_api_key';

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    // No onBootstrap ACL grant. This page holds a credential and repoints an
    // outbound request, so it stays on Omeka's default -- Global Administrators
    // only. It previously called $acl->allow(null, ...), and `null` in Laminas
    // ACL means *every* role, so any account down to Researcher could read the
    // key off this page and change where the sync posts to.

    public function getConfigForm(PhpRenderer $renderer)
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $endpoint = (string) $settings->get(self::SETTING_ENDPOINT, '');
        $storedKey = trim((string) $settings->get(self::SETTING_API_KEY, ''));
        $hasKey = $storedKey !== '';

        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $html = '<div class="field">';
        $html .= '<div class="field-meta"><label for="' . self::SETTING_ENDPOINT . '">Sync endpoint URL</label></div>';
        $html .= '<div class="inputs"><input type="text" size="45" name="' . self::SETTING_ENDPOINT . '"'
            . ' id="' . self::SETTING_ENDPOINT . '" value="' . $e($endpoint) . '"'
            . ' placeholder="https://example.execute-api.us-east-1.amazonaws.com/Prod/sync" /></div>';
        $html .= '<p class="explanation">The endpoint that rebuilds the search index. '
            . 'It is sent an empty POST.</p>';
        $html .= '</div>';

        // The stored key is never rendered back into the form. A password input
        // still ships its value in the HTML source, so not sending it at all is
        // the only version that keeps it off the page.
        $html .= '<div class="field">';
        $html .= '<div class="field-meta"><label for="' . self::SETTING_API_KEY . '">API key</label></div>';
        $html .= '<div class="inputs"><input type="password" size="45" name="' . self::SETTING_API_KEY . '"'
            . ' id="' . self::SETTING_API_KEY . '" value="" autocomplete="new-password"'
            . ' placeholder="' . ($hasKey ? 'Set — leave blank to keep' : 'Not set') . '" /></div>';
        // The field is empty after every save, because the stored key is never
        // sent to the browser — so an administrator who has just pasted a key in
        // and saved sees a blank field and reasonably concludes it did not take.
        // The placeholder alone was too quiet to carry that.
        //
        // A fingerprint is the strong signal: it confirms a key is stored, and
        // confirms *which* one, so a rotation can be checked against the value
        // that was generated. A hash prefix rather than the key's own last few
        // characters — those would disclose part of the secret for the same
        // benefit.
        $html .= '<p class="explanation">';
        $html .= $hasKey
            ? '<strong>Stored:</strong> a key is set, fingerprint <code>'
                . substr(hash('sha256', $storedKey), 0, 8) . '</code>. '
                . 'The field above is blank because the key itself is never sent to '
                . 'the browser — that is expected, not a failed save. '
            : '<strong>Stored:</strong> no key. ';
        $html .= 'Sent as the <code>x-api-key</code> header. '
            . 'Leave blank to keep the current key.</p>';
        $html .= '</div>';

        if ($hasKey) {
            $html .= '<div class="field">';
            $html .= '<div class="field-meta"><label for="' . self::CLEAR_API_KEY . '">Remove stored key</label></div>';
            $html .= '<div class="inputs"><input type="checkbox" name="' . self::CLEAR_API_KEY . '"'
                . ' id="' . self::CLEAR_API_KEY . '" value="1" /></div>';
            $html .= '<p class="explanation">Since a blank field means "keep the current key", '
                . 'removing one has to be asked for explicitly.</p>';
            $html .= '</div>';
        }

        return $html;
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $params = $controller->params()->fromPost();

        $settings->set(self::SETTING_ENDPOINT, trim((string) ($params[self::SETTING_ENDPOINT] ?? '')));

        // Blank means "unchanged". It previously meant "", so any submission
        // that did not carry the field silently wiped a working credential.
        $key = trim((string) ($params[self::SETTING_API_KEY] ?? ''));
        if (!empty($params[self::CLEAR_API_KEY])) {
            $settings->set(self::SETTING_API_KEY, '');
        } elseif ($key !== '') {
            $settings->set(self::SETTING_API_KEY, $key);
        }

        return true;
    }
}
