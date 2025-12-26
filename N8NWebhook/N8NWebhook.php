<?php
/**
 * N8N Webhook Notification Provider for WHMCS
 * Allows sending WHMCS events to N8N workflows with full UI configuration
 * Automatically enriches notifications with complete ticket, invoice, order, and client data
 */

namespace WHMCS\Module\Notification\N8NWebhook;

use WHMCS\Module\Contracts\NotificationModuleInterface;
use WHMCS\Notification\Contracts\NotificationInterface;
use WHMCS\Database\Capsule;

class N8NWebhook implements NotificationModuleInterface {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Constructor can be empty
    }

    /**
     * Get module name
     */
    public function getName() {
        return 'N8NWebhook';
    }

    /**
     * Get display name (shown in WHMCS UI)
     */
    public function getDisplayName() {
        return 'N8N Webhook';
    }

    /**
     * Get module description
     */
    public function getDescription() {
        return 'Send WHMCS notifications to N8N workflows via webhooks with customizable settings';
    }

    /**
     * Check if module is active
     */
    public function isActive() {
        return true;
    }

    /**
     * Get logo filename
     */
    public function getLogoFileName() {
        return 'logo.png';
    }

    /**
     * Get full logo path
     */
    public function getLogoPath() {
        return 'modules/notifications/' . $this->getName() . '/' . $this->getLogoFileName();
    }

    /**
     * Global module settings - shown when activating the module
     * These are configured ONCE for the entire module
     */
    public function settings() {
        return [
            'default_webhook_url' => [
                'FriendlyName' => 'Default Webhook URL',
                'Type' => 'text',
                'Size' => '50',
                'Description' => 'Your N8N webhook URL (can be overridden per notification)',
                'Placeholder' => 'https://your-n8n.com/webhook/whmcs'
            ],
            'auth_type' => [
                'FriendlyName' => 'Authentication Type',
                'Type' => 'dropdown',
                'Options' => [
                    'none' => 'None',
                    'bearer' => 'Bearer Token',
                    'basic' => 'Basic Auth',
                    'api_key' => 'API Key Header'
                ],
                'Default' => 'none',
            ],
            'auth_token' => [
                'FriendlyName' => 'Auth Token/API Key',
                'Type' => 'password',
                'Size' => '50',
                'Description' => 'Enter Bearer token, API key, or username:password for Basic Auth',
            ],
            'timeout' => [
                'FriendlyName' => 'Request Timeout (seconds)',
                'Type' => 'text',
                'Size' => '5',
                'Default' => '10',
                'Description' => 'How long to wait for N8N response',
            ],
            'verify_ssl' => [
                'FriendlyName' => 'Verify SSL Certificate',
                'Type' => 'yesno',
                'Description' => 'Disable only for testing with self-signed certificates',
                'Default' => 'yes',
            ],
            'debug_mode' => [
                'FriendlyName' => 'Debug Mode',
                'Type' => 'yesno',
                'Description' => 'Log all webhook requests to Activity Log',
                'Default' => 'no',
            ],
        ];
    }

    /**
     * Per-notification settings - shown when creating each notification rule
     * Users can customize these for EACH individual notification
     */
    public function notificationSettings() {
        return [
            'custom_webhook_url' => [
                'FriendlyName' => 'Custom Webhook URL (Optional)',
                'Type' => 'text',
                'Size' => '50',
                'Description' => 'Override default webhook URL for this specific notification',
                'Placeholder' => 'https://your-n8n.com/webhook/specific-workflow'
            ],
            'include_client_data' => [
                'FriendlyName' => 'Include Client Details',
                'Type' => 'yesno',
                'Description' => 'Send client name, email, company in payload',
                'Default' => 'yes',
            ],
            'include_raw_data' => [
                'FriendlyName' => 'Include Raw Event Data',
                'Type' => 'yesno',
                'Description' => 'Include all raw WHMCS hook parameters',
                'Default' => 'no',
            ],
            'custom_headers' => [
                'FriendlyName' => 'Custom Headers',
                'Type' => 'textarea',
                'Rows' => '3',
                'Description' => 'Additional headers (one per line, format: Header-Name: value)',
                'Placeholder' => "X-Custom-Header: value\nX-Team: support"
            ],
            'custom_message' => [
                'FriendlyName' => 'Custom Message Template',
                'Type' => 'textarea',
                'Rows' => '3',
                'Description' => 'Custom message (leave blank for default). Use {title}, {url}, {message}',
                'Placeholder' => '{title} - {message}'
            ],
        ];
    }

    /**
     * Get dynamic field - for dynamic field types in notification settings
     * This is called when a field type is 'dynamic'
     */
    public function getDynamicField($fieldName, $settings) {
        // Return empty array if no dynamic fields are defined
        // You can implement dynamic dropdowns here if needed in the future
        return [];
    }

    /**
     * Test connection - validates settings when user clicks "Test Connection"
     */
    public function testConnection($settings) {
        $webhookUrl = isset($settings['default_webhook_url']) ? trim($settings['default_webhook_url']) : '';
        
        if (empty($webhookUrl)) {
            throw new \Exception('Webhook URL is required');
        }
        
        if (!filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            throw new \Exception('Invalid webhook URL format');
        }

        // Build test payload
        $testPayload = [
            'test' => true,
            'message' => 'WHMCS N8N Webhook connection test',
            'timestamp' => date('c'),
            'source' => 'WHMCS N8N Webhook Module'
        ];

        // Build headers
        $headers = ['Content-Type: application/json'];
        
        // Add authentication if configured
        if (isset($settings['auth_type'])) {
            switch ($settings['auth_type']) {
                case 'bearer':
                    if (!empty($settings['auth_token'])) {
                        $headers[] = 'Authorization: Bearer ' . $settings['auth_token'];
                    }
                    break;
                case 'basic':
                    if (!empty($settings['auth_token'])) {
                        $headers[] = 'Authorization: Basic ' . base64_encode($settings['auth_token']);
                    }
                    break;
                case 'api_key':
                    if (!empty($settings['auth_token'])) {
                        $headers[] = 'X-API-Key: ' . $settings['auth_token'];
                    }
                    break;
            }
        }

        // Send test request
        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testPayload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, isset($settings['verify_ssl']) && $settings['verify_ssl'] === 'on');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new \Exception('Connection failed: ' . $error);
        }
        
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \Exception('N8N returned HTTP ' . $httpCode . '. Response: ' . substr($response, 0, 200));
        }
        
        return ['success' => 'Test successful! N8N responded with HTTP ' . $httpCode];
    }

    /**
     * Send notification - this is called when an event is triggered
     */
    public function sendNotification(NotificationInterface $notification, $moduleSettings, $notificationSettings) {
        
        // Determine which webhook URL to use
        $webhookUrl = !empty($notificationSettings['custom_webhook_url']) 
            ? trim($notificationSettings['custom_webhook_url'])
            : trim($moduleSettings['default_webhook_url']);

        if (empty($webhookUrl)) {
            logActivity('N8N Webhook Error: No webhook URL configured');
            return;
        }

        // Get notification attributes
        $attributes = $notification->getAttributes();
        
        // Build the base payload
        $payload = [
            'event' => $notification->getTitle(),
            'url' => $notification->getUrl(),
            'message' => $notification->getMessage(),
            'timestamp' => date('c'),
        ];

        // ENHANCED: Extract IDs and fetch full data from database
        $enrichedData = $this->enrichNotificationData($notification, $attributes);
        
        if ($enrichedData) {
            $payload = array_merge($payload, $enrichedData);
        }

        // Include raw data if enabled
        if (isset($notificationSettings['include_raw_data']) && $notificationSettings['include_raw_data'] === 'on') {
            $payload['raw_data'] = $attributes;
        }

        // Add custom message if provided
        if (!empty($notificationSettings['custom_message'])) {
            $customMessage = $notificationSettings['custom_message'];
            $customMessage = str_replace('{title}', $notification->getTitle(), $customMessage);
            $customMessage = str_replace('{url}', $notification->getUrl(), $customMessage);
            $customMessage = str_replace('{message}', $notification->getMessage(), $customMessage);
            $payload['custom_message'] = $customMessage;
        }

        // Build headers
        $headers = ['Content-Type: application/json'];
        
        // Add authentication header
        if (isset($moduleSettings['auth_type'])) {
            switch ($moduleSettings['auth_type']) {
                case 'bearer':
                    if (!empty($moduleSettings['auth_token'])) {
                        $headers[] = 'Authorization: Bearer ' . $moduleSettings['auth_token'];
                    }
                    break;
                case 'basic':
                    if (!empty($moduleSettings['auth_token'])) {
                        $headers[] = 'Authorization: Basic ' . base64_encode($moduleSettings['auth_token']);
                    }
                    break;
                case 'api_key':
                    if (!empty($moduleSettings['auth_token'])) {
                        $headers[] = 'X-API-Key: ' . $moduleSettings['auth_token'];
                    }
                    break;
            }
        }

        // Add custom headers
        if (!empty($notificationSettings['custom_headers'])) {
            $customHeaders = explode("\n", $notificationSettings['custom_headers']);
            foreach ($customHeaders as $header) {
                $header = trim($header);
                if (!empty($header) && strpos($header, ':') !== false) {
                    $headers[] = $header;
                }
            }
        }

        // Debug logging
        $debugMode = isset($moduleSettings['debug_mode']) && $moduleSettings['debug_mode'] === 'on';
        if ($debugMode) {
            logActivity('N8N Webhook: Sending to ' . $webhookUrl . ' - Event: ' . $notification->getTitle());
        }

        // Send the request
        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, isset($moduleSettings['timeout']) ? (int)$moduleSettings['timeout'] : 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, isset($moduleSettings['verify_ssl']) && $moduleSettings['verify_ssl'] === 'on');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Log if there's an error or debug mode is on
        if ($error || $httpCode < 200 || $httpCode >= 300) {
            logActivity('N8N Webhook Error: ' . ($error ?: 'HTTP ' . $httpCode) . ' - URL: ' . $webhookUrl . ' - Event: ' . $notification->getTitle());
        } elseif ($debugMode) {
            logActivity('N8N Webhook Success: HTTP ' . $httpCode . ' - Event: ' . $notification->getTitle());
        }
    }

    /**
     * Enrich notification data by fetching from database
     */
    private function enrichNotificationData($notification, $attributes) {
        $enrichedData = [];
        
        try {
            // Extract ticket ID from URL or title
            $ticketId = $this->extractTicketId($notification);
            
            if ($ticketId) {
                $enrichedData = $this->getTicketData($ticketId);
            }
            
            // Extract invoice ID if it's an invoice notification
            $invoiceId = $this->extractInvoiceId($notification);
            if ($invoiceId) {
                $enrichedData = $this->getInvoiceData($invoiceId);
            }
            
            // Extract order ID if it's an order notification
            $orderId = $this->extractOrderId($notification);
            if ($orderId) {
                $enrichedData = $this->getOrderData($orderId);
            }
            
            // Extract service ID if it's a service notification
            $serviceId = $this->extractServiceId($notification);
            if ($serviceId) {
                $enrichedData = $this->getServiceData($serviceId);
            }
            
            // Extract domain ID if it's a domain notification
            $domainId = $this->extractDomainId($notification);
            if ($domainId) {
                $enrichedData = $this->getDomainData($domainId);
            }
            
        } catch (\Exception $e) {
            logActivity('N8N Webhook: Data enrichment error - ' . $e->getMessage());
        }
        
        return $enrichedData;
    }

    /**
     * Extract ticket ID from notification
     */
    private function extractTicketId($notification) {
        $url = $notification->getUrl();
        $title = $notification->getTitle();
        
        // Try to extract from URL: supporttickets.php?action=view&id=123
        if (preg_match('/supporttickets\.php.*[&?]id=(\d+)/', $url, $matches)) {
            return $matches[1];
        }
        
        // Try to extract ticket number from title: #ABC-123456
        if (preg_match('/#([A-Z0-9\-]+)/', $title, $matches)) {
            $ticketNum = $matches[1];
            $ticket = Capsule::table('tbltickets')
                ->where('tid', $ticketNum)
                ->first();
            if ($ticket) {
                return $ticket->id;
            }
        }
        
        return null;
    }

    /**
     * Get full ticket data from database
     */
    private function getTicketData($ticketId) {
        $ticket = Capsule::table('tbltickets')
            ->where('id', $ticketId)
            ->first();
        
        if (!$ticket) {
            return [];
        }
        
        // Get client data
        $client = Capsule::table('tblclients')
            ->where('id', $ticket->userid)
            ->first();
        
        // Get department name
        $department = Capsule::table('tblticketdepartments')
            ->where('id', $ticket->did)
            ->first();
        
        // Get last reply/message
        $lastMessage = Capsule::table('tblticketreplies')
            ->where('tid', $ticketId)
            ->orderBy('id', 'desc')
            ->first();
        
        return [
            'ticket' => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->tid,
                'subject' => $ticket->title,
                'message' => $ticket->message,
                'department' => $department ? $department->name : 'Unknown',
                'department_id' => $ticket->did,
                'status' => $ticket->status,
                'priority' => $ticket->urgency,
                'flag' => $ticket->flag,
                'created' => $ticket->date,
                'last_reply' => $ticket->lastreply,
                'last_message' => $lastMessage ? $lastMessage->message : $ticket->message,
            ],
            'client' => $client ? [
                'id' => $client->id,
                'name' => $client->firstname . ' ' . $client->lastname,
                'email' => $client->email,
                'company' => $client->companyname,
                'phone' => $client->phonenumber,
                'address' => $client->address1,
                'city' => $client->city,
                'state' => $client->state,
                'country' => $client->country,
            ] : null
        ];
    }

    /**
     * Extract invoice ID from notification
     */
    private function extractInvoiceId($notification) {
        $url = $notification->getUrl();
        
        if (preg_match('/invoices\.php.*[&?]id=(\d+)/', $url, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Get invoice data from database
     */
    private function getInvoiceData($invoiceId) {
        $invoice = Capsule::table('tblinvoices')
            ->where('id', $invoiceId)
            ->first();
        
        if (!$invoice) {
            return [];
        }
        
        $client = Capsule::table('tblclients')
            ->where('id', $invoice->userid)
            ->first();
        
        // Get invoice items
        $items = Capsule::table('tblinvoiceitems')
            ->where('invoiceid', $invoiceId)
            ->get();
        
        $invoiceItems = [];
        foreach ($items as $item) {
            $invoiceItems[] = [
                'description' => $item->description,
                'amount' => $item->amount,
                'type' => $item->type,
            ];
        }
        
        return [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoicenum,
                'total' => $invoice->total,
                'subtotal' => $invoice->subtotal,
                'tax' => $invoice->tax,
                'credit' => $invoice->credit,
                'status' => $invoice->status,
                'date' => $invoice->date,
                'due_date' => $invoice->duedate,
                'date_paid' => $invoice->datepaid,
                'items' => $invoiceItems,
            ],
            'client' => $client ? [
                'id' => $client->id,
                'name' => $client->firstname . ' ' . $client->lastname,
                'email' => $client->email,
                'company' => $client->companyname,
            ] : null
        ];
    }

    /**
     * Extract order ID from notification
     */
    private function extractOrderId($notification) {
        $url = $notification->getUrl();
        
        if (preg_match('/orders\.php.*[&?]id=(\d+)/', $url, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Get order data from database
     */
    private function getOrderData($orderId) {
        $order = Capsule::table('tblorders')
            ->where('id', $orderId)
            ->first();
        
        if (!$order) {
            return [];
        }
        
        $client = Capsule::table('tblclients')
            ->where('id', $order->userid)
            ->first();
        
        return [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->ordernum,
                'amount' => $order->amount,
                'status' => $order->status,
                'payment_method' => $order->paymentmethod,
                'invoice_id' => $order->invoiceid,
                'date' => $order->date,
            ],
            'client' => $client ? [
                'id' => $client->id,
                'name' => $client->firstname . ' ' . $client->lastname,
                'email' => $client->email,
                'company' => $client->companyname,
            ] : null
        ];
    }

    /**
     * Extract service ID from notification
     */
    private function extractServiceId($notification) {
        $url = $notification->getUrl();
        
        if (preg_match('/clientsservices\.php.*[&?]id=(\d+)/', $url, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Get service data from database
     */
    private function getServiceData($serviceId) {
        $service = Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->first();
        
        if (!$service) {
            return [];
        }
        
        $client = Capsule::table('tblclients')
            ->where('id', $service->userid)
            ->first();
        
        $product = Capsule::table('tblproducts')
            ->where('id', $service->packageid)
            ->first();
        
        return [
            'service' => [
                'id' => $service->id,
                'product' => $product ? $product->name : 'Unknown',
                'domain' => $service->domain,
                'username' => $service->username,
                'server' => $service->server,
                'status' => $service->domainstatus,
                'payment_method' => $service->paymentmethod,
                'billing_cycle' => $service->billingcycle,
                'amount' => $service->amount,
                'registration_date' => $service->regdate,
                'next_due_date' => $service->nextduedate,
            ],
            'client' => $client ? [
                'id' => $client->id,
                'name' => $client->firstname . ' ' . $client->lastname,
                'email' => $client->email,
                'company' => $client->companyname,
            ] : null
        ];
    }

    /**
     * Extract domain ID from notification
     */
    private function extractDomainId($notification) {
        $url = $notification->getUrl();
        
        if (preg_match('/clientsdomains\.php.*[&?]id=(\d+)/', $url, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Get domain data from database
     */
    private function getDomainData($domainId) {
        $domain = Capsule::table('tbldomains')
            ->where('id', $domainId)
            ->first();
        
        if (!$domain) {
            return [];
        }
        
        $client = Capsule::table('tblclients')
            ->where('id', $domain->userid)
            ->first();
        
        return [
            'domain' => [
                'id' => $domain->id,
                'domain' => $domain->domain,
                'status' => $domain->status,
                'registrar' => $domain->registrar,
                'registration_date' => $domain->registrationdate,
                'expiry_date' => $domain->expirydate,
                'next_due_date' => $domain->nextduedate,
                'registration_period' => $domain->registrationperiod,
            ],
            'client' => $client ? [
                'id' => $client->id,
                'name' => $client->firstname . ' ' . $client->lastname,
                'email' => $client->email,
                'company' => $client->companyname,
            ] : null
        ];
    }
}