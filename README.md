# Typecho-CommentGun
A plugin for typecho to notify readers of new comments by sending emails via Mailgun.

This plugin is modified from [CommentToMail](https://github.com/byends/CommentToMail).
The original author of CommentToMail is [Byends Upd.](http://www.byends.com).
The original original author is [DEFE](http://defe.me).

## Modifications
1. Changed sending method from Sendmail/SMTP to Mailgun API.
2. Removed async sending - Mailgun API is too fast to cause lag.
3. Removed logging and caching - these causes unnecessary disk operations, which may cause lag on some systems.

## Installation
Just drop the *CommentGun* folder into *usr/plugins/* folder under your Typecho installation, and enter your API key and domain into the control panel.

## Credits
[Mailgun](http://mailgun.com)
