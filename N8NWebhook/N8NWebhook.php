<?php
/**
 * N8NWebhook - Notification Provider for WHMCS
 *
 * Path:
 *   modules/notifications/N8NWebhook/N8NWebhook.php
 *
 * Sends WHMCS Notification Rule events to an n8n webhook.
 */

namespace WHMCS\Module\Notification\N8NWebhook;

use WHMCS\Module\Contracts\NotificationModuleInterface;
use WHMCS\Notification\Contracts\NotificationInterface;

class N8NWebhook implements NotificationModuleInterface
{
    /** @var string */
    protected $displayName = 'n8n Webhook';

    /** @var string */
    protected $logoFileName = 'logo.png';

    /**
     * Provider (global) settings.
     *
     * @return array
     */
    public function settings()
    {
        return [
            'default_webhook_url' => [
                'FriendlyName' => 'Default Webhook URL',
                'Type'         => 'text',
                'Size'         => '80',
                'Description'  => 'Default n8n webhook URL used when a rule does not override it.',
                'Placeholder'  => 'https://flow.example.com/webhook/whmcs',
            ],
            'auth_mode' => [
                'FriendlyName' => 'Authentication',
                'Type'         => 'dropdown',
                'Options'      => [
                    'none'   => 'None',
                    'bearer' => 'Bearer Token (Authorization: Bearer ...)',
                    'header' => 'Custom Header (X-API-Key, etc.)',
                ],
                'Default'      => 'none',
                'Description'  => 'Choose how to authenticate requests (if needed).',
            ],
            'auth_token' => [
                'FriendlyName' => 'Auth Token / Key',
                'Type'         => 'password',
                'Size'         => '80',
                'Description'  => 'Used as Bearer token when Authentication = Bearer, or as header value when Authentication = Custom Header.',
            ],
            'header_name' => [
                'FriendlyName' => 'Custom Header Name',
                'Type'         => 'text',
                'Size'         => '50',
                'Default'      => 'X-API-Key',
                'Description'  => 'Used only when Authentication = Custom Header.',
            ],
            'timeout_seconds' => [
                'FriendlyName' => 'HTTP Timeout (seconds)',
                'Type'         => 'text',
                'Size'         => '5',
                'Default'      => '15',
                'Description'  => 'How long WHMCS should wait for the webhook response.',
            ],
            'verify_ssl' => [
                'FriendlyName' => 'Verify SSL Certificate',
                'Type'         => 'dropdown',
                'Options'      => [
                    'yes' => 'Verify (recommended)',
                    'no'  => 'Ignore certificate errors (testing / self-signed)',
                ],
                'Default'      => 'yes',
                'Description'  => 'Controls SSL certificate verification for webhook calls.',
            ],
            'debug_log' => [
                'FriendlyName' => 'Debug Log to Activity Log',
                'Type'         => 'dropdown',
                'Options'      => [
                    'no'  => 'Disabled',
                    'yes' => 'Enabled',
                ],
                'Default'      => 'no',
                'Description'  => 'If enabled, logs webhook attempts to the WHMCS Activity Log (can be noisy).',
            ],
        ];
    }

    /**
     * Provider active?
     *
     * @return bool
     */
    public function isActive()
    {
        return true;
    }

    /**
     * Short class name.
     *
     * @return string
     */
    public function getName()
    {
        return (new \ReflectionClass($this))->getShortName();
    }

    /**
     * Display name for UI.
     *
     * @return string
     */
    public function getDisplayName()
    {
        return $this->displayName;
    }

    /**
     * Logo path (relative to WHMCS URL).
     *
     * @return string
     */
    public function getLogoPath()
    {
        return '/modules/notifications/N8NWebhook/' . $this->logoFileName;
    }

    /**
     * MUST match interface: testConnection($settings)
     *
     * @param mixed $settings
     * @return bool
     * @throws \Exception
     */
    public function testConnection($settings)
    {
        if (!is_array($settings)) {
            $settings = [];
        }

        $url = trim((string)($settings['default_webhook_url'] ?? ''));
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \Exception('Please provide a valid Default Webhook URL.');
        }

        $payload = [
            'meta' => [
                'provider'  => 'N8NWebhook',
                'type'      => 'testConnection',
                'timestamp' => gmdate('c'),
            ],
            'message' => 'WHMCS testConnection: if you see this in n8n, the provider is working.',
        ];

        $result = $this->httpPost($url, $payload, $settings, ['X-WHMCS-Test' => '1'], 'json');

        if ($result['http_code'] < 200 || $result['http_code'] >= 300) {
            $body = $this->truncate((string)$result['body'], 500);
            throw new \Exception("Webhook test failed. HTTP {$result['http_code']}. Response: {$body}");
        }

        return true;
    }

    /**
     * Per-rule settings.
     *
     * @return array
     */
    public function notificationSettings()
    {
        return [
            'webhook_url' => [
                'FriendlyName' => 'Webhook URL Override',
                'Type'         => 'text',
                'Size'         => '80',
                'Description'  => 'Optional. Overrides Default Webhook URL for this rule.',
                'Placeholder'  => 'https://flow.example.com/webhook/whmcs-orders',
            ],
            'route_key' => [
                'FriendlyName' => 'Route Key (Optional)',
                'Type'         => 'text',
                'Size'         => '40',
                'Description'  => 'Optional routing key. Sent as X-Route-Key header and payload.meta.route_key.',
                'Placeholder'  => 'orders / invoices / tickets',
            ],
            'send_format' => [
                'FriendlyName' => 'Send Format',
                'Type'         => 'dropdown',
                'Options'      => [
                    'json' => 'JSON (recommended)',
                    'form' => 'Form-URL-Encoded',
                ],
                'Default'      => 'json',
                'Description'  => 'How the payload is sent to the webhook.',
            ],
            'include_attributes' => [
                'FriendlyName' => 'Include Attributes',
                'Type'         => 'yesno',
                'Default'      => 'yes',
                'Description'  => 'Include notification attributes (label/value/url/style/icon).',
            ],
            'message_template' => [
                'FriendlyName' => 'Message Template (Optional)',
                'Type'         => 'textarea',
                'Rows'         => '3',
                'Description'  => 'Placeholders: {title}, {message}, {url}. Leave blank to use WHMCS message.',
                'Placeholder'  => '{title} - {message}',
            ],
        ];
    }

    /**
     * Dynamic fields not used.
     *
     * @param string $fieldName
     * @param mixed  $settings
     * @return array
     */
    public function getDynamicField($fieldName, $settings)
    {
        return [];
    }

    /**
     * sendNotification(NotificationInterface $notification, $moduleSettings, $notificationSettings)
     *
     * @param NotificationInterface $notification
     * @param mixed                 $moduleSettings
     * @param mixed                 $notificationSettings
     * @return void
     * @throws \Exception
     */
    public function sendNotification(
        NotificationInterface $notification,
        $moduleSettings,
        $notificationSettings
    ) {
        if (!is_array($moduleSettings)) {
            $moduleSettings = [];
        }
        if (!is_array($notificationSettings)) {
            $notificationSettings = [];
        }

        $defaultUrl  = trim((string)($moduleSettings['default_webhook_url'] ?? ''));
        $overrideUrl = trim((string)($notificationSettings['webhook_url'] ?? ''));
        $url = $overrideUrl !== '' ? $overrideUrl : $defaultUrl;

        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \Exception(
                'Webhook URL is missing or invalid. Set Default Webhook URL or per-rule override.'
            );
        }

        $routeKey = trim((string)($notificationSettings['route_key'] ?? ''));

        $title   = (string)$notification->getTitle();
        $message = (string)$notification->getMessage();
        $link    = (string)$notification->getUrl();

        $tpl = trim((string)($notificationSettings['message_template'] ?? ''));
        if ($tpl !== '') {
            $message = strtr($tpl, [
                '{title}'   => $title,
                '{message}' => $message,
                '{url}'     => $link,
            ]);
        }

        $includeAttributes = $this->asBool($notificationSettings['include_attributes'] ?? 'yes');
        $attributes = [];

        if ($includeAttributes) {
            $rawAttrs = $notification->getAttributes();
            if (is_array($rawAttrs)) {
                foreach ($rawAttrs as $attr) {
                    $attributes[] = [
                        'label' => method_exists($attr, 'getLabel') ? $attr->getLabel() : null,
                        'value' => method_exists($attr, 'getValue') ? $attr->getValue() : null,
                        'url'   => method_exists($attr, 'getUrl') ? $attr->getUrl() : null,
                        'style' => method_exists($attr, 'getStyle') ? $attr->getStyle() : null,
                        'icon'  => method_exists($attr, 'getIcon') ? $attr->getIcon() : null,
                    ];
                }
            }
        }

        $payload = [
            'meta' => [
                'provider'   => 'N8NWebhook',
                'timestamp'  => gmdate('c'),
                'route_key'  => $routeKey !== '' ? $routeKey : null,
                'system_url' => defined('WHMCS_URL') ? WHMCS_URL : null,
            ],
            'notification' => [
                'title'      => $title,
                'message'    => $message,
                'url'        => $link,
                'attributes' => $attributes,
            ],
        ];

        if (method_exists($notification, 'getEvent')) {
            $payload['meta']['event'] = $notification->getEvent();
        }
        if (method_exists($notification, 'getType')) {
            $payload['meta']['type'] = $notification->getType();
        }

        $headers = [];
        if ($routeKey !== '') {
            $headers['X-Route-Key'] = $routeKey;
        }

        $sendFormat = (string)($notificationSettings['send_format'] ?? 'json');
        $result = $this->httpPost($url, $payload, $moduleSettings, $headers, $sendFormat);

        $debug = $this->asBool($moduleSettings['debug_log'] ?? 'no');
        if ($debug && function_exists('logActivity')) {
            $excerpt = $this->truncate((string)$result['body'], 400);
            logActivity(sprintf(
                'N8NWebhook: sent notification. HTTP %s to %s. Response: %s',
                $result['http_code'],
                $url,
                $excerpt
            ));
        }

        if ($result['http_code'] < 200 || $result['http_code'] >= 300) {
            $body = $this->truncate((string)$result['body'], 500);
            throw new \Exception("Webhook delivery failed. HTTP {$result['http_code']}. Response: {$body}");
        }
    }

    /**
     * HTTP POST helper using cURL.
     */
    protected function httpPost(
        $url,
        array $payload,
        array $moduleSettings,
        array $extraHeaders = [],
        $format = 'json'
    ) {
        if (!function_exists('curl_init')) {
            throw new \Exception('PHP cURL extension is required for N8NWebhook provider.');
        }

        $timeout = (int)($moduleSettings['timeout_seconds'] ?? 15);
        if ($timeout <= 0) {
            $timeout = 15;
        }

        $verifySsl = (string)($moduleSettings['verify_ssl'] ?? 'yes');
        $verifySslBool = ($verifySsl === 'yes');

        $headers = [
            'User-Agent: WHMCS-N8NWebhook/1.0',
        ];

        foreach ($this->buildAuthHeaders($moduleSettings) as $h) {
            $headers[] = $h;
        }

        foreach ($extraHeaders as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $headers[] = $k . ': ' . $v;
        }

        if ($format === 'form') {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $postFields = http_build_query($payload);
        } else {
            $headers[] = 'Content-Type: application/json';
            $postFields = json_encode($payload);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(10, $timeout));
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySslBool ? 1 : 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifySslBool ? 2 : 0);

        $body = curl_exec($ch);
        if ($body === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \Exception('Webhook request error: ' . $err);
        }

        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'http_code' => $httpCode,
            'body'      => (string)$body,
            'err'       => '',
        ];
    }

    /**
     * Build auth headers based on provider settings.
     *
     * @param array $moduleSettings
     * @return string[]
     */
    protected function buildAuthHeaders(array $moduleSettings)
    {
        $mode   = (string)($moduleSettings['auth_mode'] ?? 'none');
        $token  = trim((string)($moduleSettings['auth_token'] ?? ''));
        $header = trim((string)($moduleSettings['header_name'] ?? 'X-API-Key'));

        if ($mode === 'bearer' && $token !== '') {
            return ['Authorization: Bearer ' . $token];
        }

        if ($mode === 'header' && $header !== '' && $token !== '') {
            return [$header . ': ' . $token];
        }

        return [];
    }

    /**
     * Truncate strings for logging.
     */
    protected function truncate($s, $max)
    {
        $s = (string)$s;
        if (strlen($s) <= $max) {
            return $s;
        }
        return substr($s, 0, $max) . '...';
    }

    /**
     * Convert WHMCS-style yes/no values to bool.
     */
    protected function asBool($value)
    {
        if (is_bool($value)) {
            return $value;
        }
        $v = strtolower(trim((string)$value));
        return in_array($v, ['1', 'true', 'yes', 'y', 'on'], true);
    }
}
