\# Installation Guide



\## Quick Install



\### 1. Download

Download the latest release from GitHub



\### 2. Upload Files

\# Upload N8NWebhook folder to your WHMCS

/path/to/whmcs/modules/notifications/N8NWebhook/



\### 3. Set Permissions

chmod 755 /path/to/whmcs/modules/notifications/N8NWebhook

chmod 644 /path/to/whmcs/modules/notifications/N8NWebhook/\*



\### 4. Configure in WHMCS

1\. Login to WHMCS Admin

2\. Go to \*\*Setup → Notifications\*\*

3\. Find \*\*N8N Webhook\*\* → Click \*\*Configure\*\*

4\. Enter webhook URL

5\. Click \*\*Save Changes\*\*



\### 5. Create Notification Rule

1\. Click \*\*Create New Notification Rule\*\*

2\. Choose event (e.g., Ticket → New Ticket)

3\. Select \*\*N8N Webhook\*\* as provider

4\. Click \*\*Create\*\*



\### 6. Test

Create a test ticket and check N8N receives the webhook!



\## File Structure

```

modules/notifications/N8NWebhook/

├── N8NWebhook.php

├── logo.png

└── whmcs.json

```



\*\*Webhooks not working:\*\*

\- Enable Debug Mode in module settings

\- Check Activity Log for errors



\## Support



See \[README.md](README.md) for full documentation





\## \*\*How to Use These Files:\*\*



\### \*\*Method 1: Create Files Directly\*\*



1\. \*\*Create a folder:\*\* `whmcs-n8n-webhook`



2\. \*\*Create README.md:\*\*

&nbsp;  - Open text editor

&nbsp;  - Copy the README.md content above

&nbsp;  - Save as `README.md`



3\. \*\*Create LICENSE:\*\*

&nbsp;  - Copy the LICENSE content above

&nbsp;  - Save as `LICENSE` (no extension)



4\. \*\*Create .gitignore:\*\*

&nbsp;  - Copy the .gitignore content above

&nbsp;  - Save as `.gitignore`



5\. \*\*Create INSTALL.md:\*\*

&nbsp;  - Copy the INSTALL.md content above

&nbsp;  - Save as `INSTALL.md`



6\. \*\*Add your N8NWebhook folder:\*\*

```

&nbsp;  whmcs-n8n-webhook/

&nbsp;  ├── N8NWebhook/

&nbsp;  │   ├── N8NWebhook.php

&nbsp;  │   ├── logo.png

&nbsp;  │   └── whmcs.json

&nbsp;  ├── README.md

&nbsp;  ├── LICENSE

&nbsp;  ├── .gitignore

&nbsp;  └── INSTALL.md

