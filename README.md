\# WHMCS N8N Webhook Notification Module



Send WHMCS notifications to N8N workflows with automatic data enrichment.



\## Features



\- Full UI configuration - no coding required

\- Automatic data enrichment from WHMCS database

\- Support for all WHMCS events (Tickets, Invoices, Orders, Services, Domains)

\- Multiple authentication methods (Bearer, Basic Auth, API Key)

\- Custom webhook URLs per notification

\- Custom HTTP headers support

\- SSL verification

\- Debug logging



\## Installation



1\. Download the latest release

2\. Upload `N8NWebhook` folder to `/modules/notifications/`

3\. Go to \*\*Setup → Notifications\*\* in WHMCS Admin

4\. Click \*\*Configure\*\* on N8N Webhook

5\. Enter your N8N webhook URL

6\. Click \*\*Save Changes\*\*



\## Configuration



\### Global Settings



\- \*\*Default Webhook URL\*\*: Your N8N webhook endpoint

\- \*\*Authentication Type\*\*: None, Bearer, Basic Auth, or API Key

\- \*\*Auth Token\*\*: Your authentication credentials

\- \*\*Request Timeout\*\*: Response wait time (default: 10 seconds)

\- \*\*Verify SSL\*\*: Enable/disable SSL verification

\- \*\*Debug Mode\*\*: Log all webhook activity



\### Per-Notification Settings



\- \*\*Custom Webhook URL\*\*: Override default URL for specific events

\- \*\*Include Client Details\*\*: Send full client information

\- \*\*Include Raw Event Data\*\*: Include all WHMCS hook parameters

\- \*\*Custom Headers\*\*: Additional HTTP headers

\- \*\*Custom Message Template\*\*: Customize message using `{title}`, `{url}`, `{message}`



\## Usage



1\. Go to \*\*Setup → Notifications\*\*

2\. Click \*\*Create New Notification Rule\*\*

3\. Select event (e.g., Ticket → New Ticket)

4\. Choose \*\*N8N Webhook\*\* as provider

5\. Configure settings

6\. Click \*\*Create\*\*



\## Example Payload

```json

{

&nbsp; "event": "#ABC-123456 - Server Issue",

&nbsp; "url": "https://your-whmcs.com/admin/supporttickets.php?action=view\&id=123",

&nbsp; "message": "A new support ticket has been opened.",

&nbsp; "timestamp": "2025-12-26T20:23:41+00:00",

&nbsp; "ticket": {

&nbsp;   "id": 123,

&nbsp;   "ticket\_number": "ABC-123456",

&nbsp;   "subject": "Server Issue",

&nbsp;   "department": "Support",

&nbsp;   "status": "Open",

&nbsp;   "priority": "High"

&nbsp; },

&nbsp; "client": {

&nbsp;   "id": 92,

&nbsp;   "name": "John Doe",

&nbsp;   "email": "john@example.com",

&nbsp;   "company": "Example Corp"

&nbsp; }

}

```



\## Supported Events



\*\*Tickets\*\*: New Ticket, Reply, Status Change, Priority Change, Closed  

\*\*Invoices\*\*: Created, Paid, Cancelled, Refunded  

\*\*Orders\*\*: Placed, Paid, Accepted  

\*\*Services\*\*: Created, Suspended, Terminated  

\*\*Domains\*\*: Registered, Renewed, Transferred  

\*\*Clients\*\*: Signup, Login, Profile Updated



\## Requirements



\- WHMCS 7.7+

\- PHP 7.4+

\- cURL extension

\- N8N instance with webhook configured



\## Troubleshooting



\*\*Webhooks not firing:\*\*

1\. Enable Debug Mode

2\. Check Activity Log for "N8N Webhook" entries

3\. Verify notification rule is enabled (green toggle)



\*\*Connection errors:\*\*

\- Verify webhook URL is correct

\- Check firewall allows WHMCS to reach N8N

\- Test with SSL verification disabled



\## Security



\- All credentials stored in WHMCS database

\- SSL verification enabled by default

\- No hardcoded sensitive data

\- Configurable authentication



\## License



MIT License - See LICENSE file



\## Author



Radhe Dhakad



\## Changelog



\### v1.0.0 (2025-12-26)

\- Initial release

\- Full UI configuration

\- Automatic data enrichment

\- Multi-authentication support

