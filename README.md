# Notice

This repository is not yet final and parts of it are due to change.
It is not yet recommended for a production environment.

# What is this?

This is a modified version of the plupload_widget, allowing more detailed configuration of where to upload files.
Other than the regular version, this one not only supports uploading multiple files, but also uploading complete folder structures and specifying static or token based subfolders.
The modified code makes use of cookies, so there is also a configurable GDPR (or similar) cookie check which, if enabled, prevents users that have not accepted a specified Cookie Agreement from uploading.
It is mostly configured to our needs at DH-Cologne, but feel free to configure it for your needs.

# Configuration
```
{
	// Enables or disables checking for an agreement cookie (e.g. GDPR cookie)
	"useCookie": false,
	// Interval on how frequent to check whether the agreement has been fulfilled
	// This allows the plupload widget to be initialized after the user agrees without having to reload the page
	// Interval in milliseconds
	"Interval": 500,
	// Configures the needed cookie
	// To find this data you can use your browsers developer console
	// In Chromium based browsers -> check the Application tab for cookies
	"cookie": {
		// The name of the agreed cookie
		// This cookie is preconfigured to match the "EU Cookie Compliance" module
		// https://www.drupal.org/project/eu_cookie_compliance
		"Name": "cookie-agreed",
		// The value the cookie has when the user has agreed
		"AgreeVal": "2",
		// The path of the cookie
		"Path": "/drupal",
		// The HTMLElement ID of the reminder that replaces the plupload widget for the time period
		// where the cookie does not match the agreement value
		"reminderID": "plupload-widget-cookies-not-enabled"
	}
}
```
