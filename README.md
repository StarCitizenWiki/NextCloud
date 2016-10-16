# NextCloud

Extension to automatically add Users to a NextCloud instance.  
The extension will create a new user on a given NextCloud instance after the User confirmed their Mail-Address.  
The Extension will send the newly created Credentials to the users Mail-Address.  
You can optionally set a predefined group and quota the user will be added to.  
Please make sure that your server can send mails and that the webserver can write into the extensions log directory.

## Features

Adds Users to a NextCloud instance after they successfully confirmed their mail address

## Using this Extension
Extract the Extension into a directory called _NextCloud_ into your extensions directory.  
Add `wfLoadExtension('NextCloud');` to your `LocalSettings.php`.  
Visit `Special:Version` and make sure that the extension is loaded.

## Config
    $wgNextCloudUrl = "";
    $wgNextCloudAdminUser = "";
    $wgNextCloudAdminPassword = "";
    $wgNextCloudUserGroup = "";
    $wgNextCloudUserQuota = "256M";
    $wgNextCloudWikiName = "";
    $wgNextCloudContactMail = "";
    $wgNextCloudDebug = false;

### Description
#### Required Parameters
**$wgNextCloudUrl**  
The Url to the NextCloud Site with trailing slash.  
Example: _https://cloud.example.com/_  

**$wgNextCloudAdminUser**  
Name of the Admin Account  
Example: UserAdmin

**$wgNextCloudAdminPassword**  
Plain Text password of the Admin Account  
Example: Password123

**$wgNextCloudContactMail**  
Mail Adress to which a User can reply to, will be set in the footer in outgoing mails  
Example: cloud@example.com

#### Optional Parameters
**$wgNextCloudUserGroup**  
The Group the user will be added to, has to be created beforehand  
Example: Example Group

**$wgNextCloudUserQuota**  
The Quote the user will be having, defaults zo 256M  
Example: 256M

**$wgNextCloudWikiName**
The Name of the Wiki, _$wgSiteName_ will be used if this value is empty  
Example: Example Wiki

**$wgNextCloudDebug**  
If debugging is set to true the extension will log into a file called _error.log_ in the log directory. Please make sure that the webserver can write into this directory!  
Example: true

Developed by [FoXFTW](https://star-citizen.wiki/Benutzer:FoXFTW) &copy; 2016
