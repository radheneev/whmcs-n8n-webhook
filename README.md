## FILE: README.md

### WHMCS → N8N Webhook Notification Module

This repository contains documentation and support files for the
WHMCS N8N Webhook Notification Provider.

The module lets you send WHMCS notifications (tickets, invoices, orders, etc.)
directly to N8N via a webhook.

### MODULE LOCATION IN WHMCS
------------------------
Place the module folder named "N8NWebhook" inside:
```
/your-whmcs-root/modules/notifications/N8NWebhook/
```
```
Example structure:

whmcs-n8n-webhook/
├── N8NWebhook/        (WHMCS module folder - code)
├── README.md
├── INSTALL.md
├── LICENSE
└── .gitignore
```

### WHAT THIS MODULE DOES
---------------------
- Sends WHMCS notifications to N8N using HTTP POST (JSON)
- Supports configuration of:
  - Webhook URL
  - Authentication (optional)
  - Timeouts
  - SSL verification
  - Debug logging in WHMCS

### BASIC FLOW
----------
1) WHMCS event occurs (e.g., ticket created)
2) Notification rule triggers the "N8N Webhook" provider
3) WHMCS sends a JSON payload to your N8N webhook URL
4) N8N receives the payload and continues the workflow

### REQUIREMENTS
------------
- WHMCS 7.7+ (recommended: 8.x)
- PHP 7.4+ or PHP 8.x
- Working N8N instance with Webhook Trigger

### LICENSE
-------
This project is licensed under the MIT License.
See LICENSE for details.
