<?php

namespace WHMCS\Module\Notification\N8NWebhook;

use WHMCS\Database\Capsule;
use WHMCS\Module\Contracts\NotificationModuleInterface;
use WHMCS\Notification\Contracts\NotificationInterface;

class N8NWebhook implements NotificationModuleInterface
{
    protected $displayName = 'n8n Webhook';
    protected $logoFileName = 'logo.png';

    /**
     * Provider-level settings (Setup > Notifications > Providers)
     */
    public function settings()
    {
        return [
            'default_webhook_url' => [
                'FriendlyName' => 'Default Webhook URL',
                'Type'         => 'text',
                'Size'         => '100',
                'Description'  => 'Used when a rule does not override the webhook URL.',
                'Placeholder'  => 'https://flow.example.com/webhook/xxxxxxxx',
            ],
            'auth_mode' => [
                'FriendlyName' => 'Authentication',
                'Type'         => 'dropdown',
                'Options'      => [
                    'none'   => 'None',
                    'bearer' => 'Bearer Token',
                    'header' => 'Custom Header',
                ],
                'Default'      => 'none',
                'Description'  => 'Optional authentication for the webhook endpoint.',
            ],
            'auth_token' => [
                'FriendlyName' => 'Token / Key',
                'Type'         => 'password',
                'Description'  => 'Used either as Bearer token or header value (see Authentication).',
            ],
            'header_name' => [
                'FriendlyName' => 'Custom Header Name',
                'Type'         => 'text',
                'Size'         => '40',
                'Default'      => 'X-API-Key',
                'Description'  => 'Used only when Authentication = Custom Header.',
            ],
            'debug_log' => [
                'FriendlyName' => 'Debug Log',
                'Type'         => 'dropdown',
                'Options'      => [
                    'no'  => 'No',
                    'yes' => 'Yes',
                ],
                'Default'      => 'no',
                'Description'  => 'If Yes, log HTTP status codes for webhook calls to the Activity Log.',
            ],
        ];
    }

    public function isActive()
    {
        return true;
    }

    public function getName()
    {
        return (new \ReflectionClass($this))->getShortName();
    }

    public function getDisplayName()
    {
        return $this->displayName;
    }

    public function getLogoPath()
    {
        return '/modules/notifications/N8NWebhook/' . $this->logoFileName;
    }

    /**
     * Test connection from WHMCS UI.
     * Signature MUST be testConnection($settings) – no typehint.
     */
    public function testConnection($settings)
    {
        if (!is_array($settings)) {
            $settings = [];
        }

        $url = trim((string)($settings['default_webhook_url'] ?? ''));
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \Exception('Enter a valid Default Webhook URL in provider settings.');
        }

        $payload = [
            'notification' => [
                'title'   => 'WHMCS n8n Test',
                'message' => 'This is a testConnection call from the N8NWebhook provider.',
                'url'     => $this->getSystemUrl(),
            ],
            'meta' => [
                'timestamp'  => gmdate('c'),
                'category'   => 'generic',
                'source'     => 'whmcs',
                'operation_code'  => 'test',
                'operation_label' => 'Test Connection',
            ],
        ];

        $result = $this->httpPost($url, $payload, $settings);

        if ($result['http_code'] >= 200 && $result['http_code'] < 300) {
            return true;
        }

        throw new \Exception('Webhook Test Failed: HTTP ' . $result['http_code']);
    }

    /**
     * Rule-level settings (per Notification Rule)
     */
    public function notificationSettings()
    {
        return [
            'webhook_url' => [
                'FriendlyName' => 'Webhook URL Override',
                'Type'         => 'text',
                'Size'         => '100',
                'Description'  =>
                    'Optional. If empty, the Default Webhook URL from provider settings is used.',
            ],
            'message_template' => [
                'FriendlyName' => 'Message Template (optional)',
                'Type'         => 'textarea',
                'Rows'         => '3',
                'Description'  =>
                    'Optional. Placeholders: {title}, {message}, {url}. Leave blank to use WHMCS message.',
            ],
        ];
    }

    /**
     * Dynamic fields – not used, return empty array.
     * Signature MUST be getDynamicField($fieldName, $settings) – no typehint on $settings.
     */
    public function getDynamicField($fieldName, $settings)
    {
        return [];
    }

    /**
     * Core sendNotification implementation.
     */
    public function sendNotification(
        NotificationInterface $notification,
        $moduleSettings,
        $ruleSettings
    ) {
        if (!is_array($moduleSettings)) {
            $moduleSettings = [];
        }
        if (!is_array($ruleSettings)) {
            $ruleSettings = [];
        }

        // 1) Webhook URL (rule override > default)
        $url = trim((string)($ruleSettings['webhook_url'] ?? ''));
        if ($url === '') {
            $url = trim((string)($moduleSettings['default_webhook_url'] ?? ''));
        }
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \Exception('Webhook URL missing or invalid (rule override or default).');
        }

        // 2) Basic WHMCS notification data
        $title   = (string)$notification->getTitle();
        $message = (string)$notification->getMessage();
        $link    = (string)$notification->getUrl();

        // 3) Detect category / operation
        $opInfo = $this->detectOperation($message, $link);
        $category       = $opInfo['category'];
        $operationCode  = $opInfo['code'];
        $operationLabel = $opInfo['label'];

        // 4) Optional message template
        $tpl = trim((string)($ruleSettings['message_template'] ?? ''));
        if ($tpl !== '') {
            $message = strtr($tpl, [
                '{title}'   => $title,
                '{message}' => $message,
                '{url}'     => $link,
            ]);
        }

        // 5) Base payload
        $payload = [
            'notification' => [
                'title'           => $title,
                'message'         => $message,
                'url'             => $link,
                'subject'         => null,
                'body'            => $message,
                'operation_code'  => $operationCode,
                'operation_label' => $operationLabel,
            ],
            'meta' => [
                'timestamp'       => gmdate('c'),
                'system_url'      => $this->getSystemUrl(),
                'category'        => $category,
                'operation_code'  => $operationCode,
                'operation_label' => $operationLabel,
                'source'          => 'whmcs',
            ],
        ];

        // 6) Try to pull IDs from attributes
        $ticketId = null;
        $orderId  = null;
        $clientId = null;

        $attributes = $notification->getAttributes();
        if (is_array($attributes)) {
            foreach ($attributes as $attr) {
                $label = strtolower((string)$attr->getLabel());
                $value = $attr->getValue();

                if ($ticketId === null && strpos($label, 'ticket') !== false && is_numeric($value)) {
                    $ticketId = (int)$value;
                }
                if ($orderId === null && strpos($label, 'order') !== false && is_numeric($value)) {
                    $orderId = (int)$value;
                }
                if ($clientId === null && strpos($label, 'client') !== false && is_numeric($value)) {
                    $clientId = (int)$value;
                }
            }
        }

        // Fallback: detect ticket ID in URL if not already known
        if ($ticketId === null && $link !== '') {
            if (preg_match('/supporttickets\.php.*[?&]id=([0-9]+)/i', $link, $m)) {
                $ticketId = (int)$m[1];
            }
        }

        // 7) Attach ticket data
        if ($ticketId !== null) {
            $ticketData = $this->getTicketData($ticketId);
            if (!empty($ticketData)) {
                $ticketData['operation_code']  = $operationCode;
                $ticketData['operation_label'] = $operationLabel;

                $payload['ticket'] = $ticketData;

                if ($clientId === null && isset($ticketData['client_id'])) {
                    $clientId = $ticketData['client_id'];
                }

                $payload['notification']['subject'] =
                    $ticketData['subject'] ?? $title;

                $payload['notification']['body'] =
                    $ticketData['last_reply_message']
                    ?? $ticketData['initial_message']
                    ?? $message;
            }
        }

        // 8) Attach order data
        if ($orderId !== null) {
            $orderData = $this->getOrderData($orderId);
            if (!empty($orderData)) {
                $payload['order'] = $orderData;

                if ($clientId === null && isset($orderData['client_id'])) {
                    $clientId = $orderData['client_id'];
                }
            }
        }

        // 9) Attach client profile (with Company Name + custom fields)
        if ($clientId !== null) {
            $clientData = $this->getClientData($clientId);
            if (!empty($clientData)) {
                $payload['client'] = $clientData;
            }

            $ownerAdmin = $this->getAccountManagerAdmin($clientId);
            $payload['account_manager_admin'] = $ownerAdmin ?: null;
        }

        // 10) Send to n8n
        $result = $this->httpPost($url, $payload, $moduleSettings);

        if (($moduleSettings['debug_log'] ?? 'no') === 'yes' && function_exists('logActivity')) {
            logActivity(sprintf('N8NWebhook: HTTP %s to %s', $result['http_code'], $url));
        }

        if ($result['http_code'] < 200 || $result['http_code'] >= 300) {
            throw new \Exception('Webhook Failed: HTTP ' . $result['http_code']);
        }
    }

    /**
     * Detect category & operation from message + URL.
     * Category: ticket, invoice, order, service, generic.
     */
    private function detectOperation($message, $url)
    {
        $msg = strtolower((string)$message);
        $u   = strtolower((string)$url);

        $category = 'generic';

        if (strpos($u, 'supporttickets.php') !== false) {
            $category = 'ticket';
        } elseif (strpos($u, 'viewinvoice.php') !== false) {
            $category = 'invoice';
        } elseif (strpos($u, 'orders.php') !== false) {
            $category = 'order';
        } elseif (strpos($u, 'clientarea.php') !== false && strpos($msg, 'service') !== false) {
            $category = 'service';
        }

        $code  = null;
        $label = null;

        if ($category === 'ticket') {
            // New ticket
            if (
                strpos($msg, 'new support ticket') !== false
                || strpos($msg, 'new ticket') !== false
            ) {
                $code  = 'ticket_new';
                $label = 'New Ticket';

            // Client reply
            } elseif (
                strpos($msg, 'customer reply') !== false
                || strpos($msg, 'client reply') !== false
                || strpos($msg, 'reply has been received from the client') !== false
                || strpos($msg, 'reply has been posted by the client') !== false
                || strpos($msg, 'new reply has been posted by a customer') !== false
                || strpos($msg, 'posted by a customer') !== false
                || strpos($msg, 'posted by the customer') !== false
            ) {
                $code  = 'ticket_reply_client';
                $label = 'New Customer Reply';

            // Staff reply
            } elseif (
                strpos($msg, 'staff member') !== false
                || strpos($msg, 'admin reply') !== false
                || strpos($msg, 'new reply has been posted by a staff member') !== false
                || strpos($msg, 'posted by a staff member') !== false
                || strpos($msg, 'reply has been posted by an admin') !== false
            ) {
                $code  = 'ticket_reply_staff';
                $label = 'New Staff Reply';

            // Department change
            } elseif (
                strpos($msg, 'department has been changed') !== false
                || strpos($msg, 'department change') !== false
            ) {
                $code  = 'ticket_department_change';
                $label = 'Department Change';

            // Priority change
            } elseif (
                strpos($msg, 'priority has been changed') !== false
                || strpos($msg, 'priority change') !== false
            ) {
                $code  = 'ticket_priority_change';
                $label = 'Priority Change';

            // Status change
            } elseif (
                strpos($msg, 'status has been changed') !== false
                || strpos($msg, 'status change') !== false
            ) {
                $code  = 'ticket_status_change';
                $label = 'Status Change';

            // Ticket assigned
            } elseif (
                strpos($msg, 'has been assigned') !== false
                || strpos($msg, 'ticket assigned') !== false
            ) {
                $code  = 'ticket_assigned';
                $label = 'Ticket Assigned';

            // Ticket closed
            } elseif (
                strpos($msg, 'has been closed') !== false
                || strpos($msg, 'ticket has been closed') !== false
                || strpos($msg, 'ticket closed') !== false
            ) {
                $code  = 'ticket_closed';
                $label = 'Ticket Closed';

            } else {
                $code  = 'ticket_generic';
                $label = 'Ticket Event';
            }

        } elseif ($category === 'invoice') {
            if (
                strpos($msg, 'payment received') !== false
                || strpos($msg, 'invoice payment confirmation') !== false
                || strpos($msg, 'has been paid') !== false
            ) {
                $code  = 'invoice_paid';
                $label = 'Invoice Paid';
            } elseif (
                strpos($msg, 'invoice created') !== false
                || strpos($msg, 'new invoice') !== false
            ) {
                $code  = 'invoice_created';
                $label = 'Invoice Created';
            } elseif (
                strpos($msg, 'refunded') !== false
                || strpos($msg, 'refund') !== false
            ) {
                $code  = 'invoice_refunded';
                $label = 'Invoice Refunded';
            } elseif (
                strpos($msg, 'cancelled') !== false
                || strpos($msg, 'canceled') !== false
            ) {
                $code  = 'invoice_cancelled';
                $label = 'Invoice Cancelled';
            } else {
                $code  = 'invoice_generic';
                $label = 'Invoice Event';
            }

        } elseif ($category === 'order') {
            if (
                strpos($msg, 'new order') !== false
                || strpos($msg, 'order placed') !== false
            ) {
                $code  = 'order_new';
                $label = 'New Order';
            } elseif (
                strpos($msg, 'order accepted') !== false
                || strpos($msg, 'order active') !== false
            ) {
                $code  = 'order_accepted';
                $label = 'Order Accepted';
            } elseif (
                strpos($msg, 'order cancelled') !== false
                || strpos($msg, 'order canceled') !== false
            ) {
                $code  = 'order_cancelled';
                $label = 'Order Cancelled';
            } else {
                $code  = 'order_generic';
                $label = 'Order Event';
            }

        } elseif ($category === 'service') {
            if (strpos($msg, 'suspended') !== false) {
                $code  = 'service_suspended';
                $label = 'Service Suspended';
            } elseif (strpos($msg, 'unsuspended') !== false) {
                $code  = 'service_unsuspended';
                $label = 'Service Unsuspended';
            } elseif (
                strpos($msg, 'terminated') !== false
                || strpos($msg, 'cancelled') !== false
                || strpos($msg, 'canceled') !== false
            ) {
                $code  = 'service_terminated';
                $label = 'Service Terminated';
            } elseif (
                strpos($msg, 'created') !== false
                || strpos($msg, 'new service') !== false
            ) {
                $code  = 'service_created';
                $label = 'New Service';
            } else {
                $code  = 'service_generic';
                $label = 'Service Event';
            }

        } else {
            $code  = 'generic_event';
            $label = 'Generic Event';
        }

        return [
            'category' => $category,
            'code'     => $code,
            'label'    => $label,
        ];
    }

    /**
     * Ticket data with last reply info.
     */
    private function getTicketData($ticketId)
    {
        $ticket = Capsule::table('tbltickets')
            ->where('id', (int)$ticketId)
            ->first();

        if (!$ticket) {
            return null;
        }

        $data = [
            'id'              => (int)$ticket->id,
            'tid'             => $ticket->tid ?? null,
            'subject'         => $ticket->title ?? null,
            'status'          => $ticket->status ?? null,
            'priority'        => $ticket->urgency ?? null,
            'date'            => $ticket->date ?? null,
            'lastreply'       => $ticket->lastreply ?? null,
            'department_id'   => isset($ticket->did) ? (int)$ticket->did : null,
            'client_id'       => isset($ticket->userid) ? (int)$ticket->userid : null,
            'initial_message' => $ticket->message ?? null,
        ];

        if (!empty($ticket->did)) {
            $dept = Capsule::table('tblticketdepartments')
                ->where('id', (int)$ticket->did)
                ->value('name');
            $data['department'] = $dept ?: null;
        } else {
            $data['department'] = null;
        }

        $reply = Capsule::table('tblticketreplies')
            ->where('tid', (int)$ticketId)
            ->orderBy('id', 'desc')
            ->first();

        if ($reply) {
            $type = !empty($reply->admin) ? 'staff' : 'client';

            $data['last_reply_type']     = $type;
            $data['last_reply_message']  = $reply->message ?? null;
            $data['last_reply_date']     = $reply->date ?? null;
            $data['last_reply_by_name']  = $reply->admin ?: ($reply->name ?? null);
            $data['last_reply_by_email'] = $reply->email ?? null;
        } else {
            $data['last_reply_type']     = null;
            $data['last_reply_message']  = null;
            $data['last_reply_date']     = null;
            $data['last_reply_by_name']  = null;
            $data['last_reply_by_email'] = null;
        }

        return $this->nullifyEmptyStrings($data);
    }

    /**
     * Order data (basic).
     */
    private function getOrderData($orderId)
    {
        $order = Capsule::table('tblorders')
            ->where('id', (int)$orderId)
            ->first();

        if (!$order) {
            return null;
        }

        $data = [
            'id'         => (int)$order->id,
            'ordernum'   => $order->ordernum ?? null,
            'status'     => $order->status ?? null,
            'client_id'  => isset($order->userid) ? (int)$order->userid : null,
            'date'       => $order->date ?? null,
            'amount'     => $order->amount ?? null,
            'invoice_id' => isset($order->invoiceid) ? (int)$order->invoiceid : null,
        ];

        return $this->nullifyEmptyStrings($data);
    }

    /**
     * Client profile, including Company Name and Sales/Account Manager custom fields.
     */
    private function getClientData($clientId)
    {
        $client = Capsule::table('tblclients')
            ->where('id', (int)$clientId)
            ->first();

        if (!$client) {
            return null;
        }

        $data = [
            'id'           => (int)$client->id,
            'firstname'    => $client->firstname ?? null,
            'lastname'     => $client->lastname ?? null,
            'name'         => trim(($client->firstname ?? '') . ' ' . ($client->lastname ?? '')) ?: null,
            'companyname'  => $client->companyname ?? null,
            'email'        => $client->email ?? null,
            'phone'        => $client->phonenumber ?? null,
            'country'      => $client->country ?? null,
            'state'        => $client->state ?? null,
        ];

        $customFields = $this->getClientCustomFieldValues(
            (int)$clientId,
            ['Sales Manager', 'Account Manager']
        );

        $data['sales_manager']   = $customFields['Sales Manager']   ?? null;
        $data['account_manager'] = $customFields['Account Manager'] ?? null;

        return $this->nullifyEmptyStrings($data);
    }

    /**
     * Optional account-manager admin (tblclients.owner → tbladmins.id) – safe.
     */
    private function getAccountManagerAdmin($clientId)
    {
        try {
            // Capsule::schema() exists on modern WHMCS; guard anyway
            if (!method_exists(Capsule::class, 'schema')) {
                return null;
            }

            $schema = Capsule::schema();
            if (!$schema->hasColumn('tblclients', 'owner')) {
                return null;
            }

            $ownerId = Capsule::table('tblclients')
                ->where('id', (int)$clientId)
                ->value('owner');

            if (!$ownerId) {
                return null;
            }

            $admin = Capsule::table('tbladmins')
                ->where('id', (int)$ownerId)
                ->first();

            if (!$admin) {
                return null;
            }

            $data = [
                'id'        => (int)$admin->id,
                'firstname' => $admin->firstname ?? null,
                'lastname'  => $admin->lastname ?? null,
                'name'      => trim(($admin->firstname ?? '') . ' ' . ($admin->lastname ?? '')) ?: null,
                'email'     => $admin->email ?? null,
            ];

            return $this->nullifyEmptyStrings($data);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Fetch selected client custom fields by name.
     */
    private function getClientCustomFieldValues($clientId, array $fieldNames)
    {
        if (empty($fieldNames)) {
            return [];
        }

        $fields = Capsule::table('tblcustomfields')
            ->where('type', 'client')
            ->whereIn('fieldname', $fieldNames)
            ->get(['id', 'fieldname']);

        if ($fields->isEmpty()) {
            $out = [];
            foreach ($fieldNames as $fn) {
                $out[$fn] = null;
            }
            return $out;
        }

        $fieldIds = [];
        $nameById = [];
        foreach ($fields as $f) {
            $fid = (int)$f->id;
            $fieldIds[] = $fid;
            $nameById[$fid] = $f->fieldname;
        }

        $values = Capsule::table('tblcustomfieldsvalues')
            ->where('relid', (int)$clientId)
            ->whereIn('fieldid', $fieldIds)
            ->get(['fieldid', 'value']);

        $result = [];
        foreach ($values as $v) {
            $fid  = (int)$v->fieldid;
            $name = $nameById[$fid] ?? null;
            if ($name !== null) {
                $val = trim((string)$v->value);
                $result[$name] = $val === '' ? null : $val;
            }
        }

        foreach ($fieldNames as $fn) {
            if (!array_key_exists($fn, $result)) {
                $result[$fn] = null;
            }
        }

        return $result;
    }

    /**
     * Simple JSON POST helper.
     */
    private function httpPost($url, array $data, array $moduleSettings)
    {
        $headers = ['Content-Type: application/json'];

        $mode  = (string)($moduleSettings['auth_mode'] ?? 'none');
        $token = trim((string)($moduleSettings['auth_token'] ?? ''));
        $hName = trim((string)($moduleSettings['header_name'] ?? 'X-API-Key'));

        if ($mode === 'bearer' && $token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        } elseif ($mode === 'header' && $token !== '' && $hName !== '') {
            $headers[] = $hName . ': ' . $token;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_TIMEOUT        => 20,
        ]);

        $body   = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return [
            'http_code' => $status,
            'body'      => $body,
        ];
    }

    /**
     * WHMCS SystemURL helper.
     */
    private function getSystemUrl()
    {
        try {
            if (class_exists('\WHMCS\Config\Setting')) {
                $url = \WHMCS\Config\Setting::getValue('SystemURL');
                $url = trim((string)$url);
                return $url !== '' ? $url : null;
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    /**
     * Convert empty strings to null for cleaner JSON.
     */
    private function nullifyEmptyStrings(array $data)
    {
        foreach ($data as $k => $v) {
            if (is_string($v)) {
                $trim = trim($v);
                $data[$k] = $trim === '' ? null : $trim;
            }
        }
        return $data;
    }
}
