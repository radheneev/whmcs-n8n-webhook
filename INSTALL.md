# **Installation Guide – WHMCS N8N Webhook Module**

1. Upload module to WHMCS

Upload the module folder "N8NWebhook" to your WHMCS installation:

/your-whmcs-root/modules/notifications/N8NWebhook/

Final structure should be:

modules/
└── notifications/
    └── N8NWebhook/
        ├── N8NWebhook.php
        ├── whmcs.json
        └── logo.png

Note:
This repository contains documentation/support files.
The actual module folder (N8NWebhook) must be placed inside /modules/notifications/.

------------------------------------------------------------

2. Set file permissions

On your WHMCS server:

cd /your-whmcs-root/modules/notifications/
chmod 755 N8NWebhook
chmod 644 N8NWebhook/*

------------------------------------------------------------

3. Enable provider in WHMCS

1) Login to WHMCS Admin
2) Go to Setup → Notifications
3) Find "N8N Webhook" in the providers list
4) Click Configure
5) Enter your N8N webhook URL and any auth details
6) Click Save Changes

------------------------------------------------------------

4. Create notification rules

1) In Setup → Notifications, click "Create New Notification Rule"
2) Select an event, for example:
   - Ticket → New Ticket
   - Invoice → Created
   - Order → Accepted
3) Choose "N8N Webhook" as the notification provider
4) Save the rule and test it

------------------------------------------------------------

5. Testing

- Create a test ticket / order / invoice
- Confirm that the webhook is received by N8N
- In N8N, use "Webhook Trigger" as the entry point, then add your workflow logic

------------------------------------------------------------

6. Troubleshooting

Issue: No webhook received in N8N
Check: Webhook URL, WHMCS server outbound connectivity, firewall rules

Issue: SSL / certificate errors
Check: Try disabling SSL verification (testing only)

Issue: No logs in WHMCS
Check: Enable "Debug mode" in the provider configuration

------------------------------------------------------------

7. Upgrading

To upgrade the module:

1) Take a backup of the existing "N8NWebhook" folder from /modules/notifications/
2) Replace it with the new version of "N8NWebhook"
3) Keep your WHMCS configuration as-is (settings are stored in DB)
4) Test with a sample event after upgrade


