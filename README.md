# Airtable Connect - Airtable Web API Client

Airtable Connect is a plugin for WordPress that provides a PHP interface for Airtable's web API. It integrates with OAuth to allow developers to provide secure and managed access to Airtable bases.

## Installation

Download the repo as a zip file and upload to WordPress via Plugins > Add New > Upload Plugin. After installation, activate the plugin. From here, you will need to register a new OAuth integration in Airtable. Once registered, the integration will provide you with a client ID. This client ID will need to be added in the Airtable Connect settings to allow Airtable Connect to connect to Airtable.

### Creating an OAuth Integration in Airtable

To create a new OAuth integration in Airtable, first log in to your Airtable account that has access to the base you want to connect to. Click you profile icon in the top right corner of the screen to open your account menu, then click "Developer Hub" to begin setting up the integration. In the left-hand sidebar of the developer hub, click "OAuth Integrations," then "+ Register new OAuth integration." Give your integration a name (e.g., "Airtable Connect Client"). You will also need to provide the integration with the OAuth redirect URL. This URL will be `https://[your WordPress site root]/airtableconnect/auth`. So if your WordPress website is found at "example.com" then your OAuth redirect URL will be "https://example.com/airtableconnect/auth". When you have entered a name and redirect URL, click "Register integration".

After registering the integration, you will be redirected to the new integration's settings page. You will need to update the integration's settings so that the proper scopes are set, allowing Airtable Connect to read data from Airtable. In the "Scopes" section of the integration's settings page, enable `data.records:read` underneath "Record data and comments." Once you are done, click "Save changes" at the bottom of the page. Finally, copy the Client ID string under the "Developer details" section of the integration's settings page; you will need to add this to the Airtable Connect settings in the WordPress admin dashboard.

### Granting Access to Airtable Connect

After you set up your OAuth integration in Airtable, log in to your WordPress admin dashboard and click "Airtable Connect" in the left-hand sidebar to access the plugin's settings. Take your client ID that you copied from the OAuth integration's settings page and paste it into the "Client ID" field in the Airtable Connect plugin's settings page, then click "Save Changes." Once the page reloads, you will see a "Connect to Airtable" link beneath the "Save Changes" button. Click the link to grant access to the desired base(s) in Airtable. You will be redirected to the Airtable's OAuth server; from here, you can "+ Add a base" to grant Airtable Connect access to the appropriate base in Airtable. When you have added the base(s) you want to access, click "Grant Access." If you are successful, you will be returned to the Airtable Connect settings page in your WordPress dashboard with a message telling you that the connection to Airtable has succeeded.

