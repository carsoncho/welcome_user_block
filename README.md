CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Installation
 * Configuration
 * Potential improvements

INTRODUCTION
------------
 
 Provides a custom Drupal block that displays a welcome message to authenticated users.

INSTALLATION
------------

# Either download the .zip file from the repository and extract it into your modules directory or if using composer add the following under the `repositories` to your composer.json file.

```json
{
  "type": "vcs",
  "url": "https://github.com/carsoncho/welcome_user_block"
}
```

Then run `composer require carsoncho/welcome_user_block`

# Once installed enabled the module as normal.

CONFIGURATION
-------------

 * After installation you can login as admin and there will be a new block to manage called "Authenticated User Welcome Message". Configure a optional message that will be displayed for all authenticated users as well as the date-time format for the user's last logged in date.

POTENTIAL IMPROVEMENTS
----------------------
# Need to implement translations in the template
# Allow the other parts of the message to be managed by a content author, e.g. "Hello {{ username }}". Would require implementing tokens.
