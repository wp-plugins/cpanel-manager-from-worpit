=== cPanel Manager (from Worpit) ===
Contributors: paultgoodchild, dlgoodchild
Donate link: http://worpit.com/
Tags: cPanel, manage
Requires at least: 3.2.0
Tested up to: 3.4
Stable tag: 1.1

== Description ==

The cPanel Manager plugin from [Worpit](http://worpit.com/?src=cpm_readme "Worpit: Manage Muliple WordPress Sites Better") 
offers you the ability to connect to your Web Hosting cPanel account.

Currently you can:

*	View a list of all MySQL database and their attached users.
*	Download any MySQL database in your cPanel account directly from this plugin.
*	Add a new MySQL database and a new MySQL user to that database.
*	Add a new MySQL user.
*	Delete MySQL databases from your cPanel account IN BULK - take GREAT CARE.
*	Delete MySQL users from your cPanel account IN BULK - take GREAT CARE.
*	View a list of all FTP users.
*	View a list of all Parked Domains.
*	View a list of all Addon Domains.
*	View a list of all Cron Jobs.

With it (in time) you will be able to perform many convenient functions from within your WordPress site that
you would otherwise need to log into your cPanel to do.

Currently, with the initial release it will list your databases, your database users, yours parked and addon domains
and also your crons.

== Frequently Asked Questions ==

= Is it secure to have my cPanel login credentials in my WordPress? =

Normally, no. But with version 1.1 of the plugin, if you have the 'mcrypt' PHP library available on your web hosting
your cPanel credentials will always be encrypted before being stored in your WordPress database.

= What if I don't have the 'mcrypt' PHP extensions? =

You will have a permanent notice on the plugin's admin pages telling you of this. The plugin will function as normal
but your details will not be encrypted.

= What is the Security Access Key? =

This is basically an encryption salt/password. We use this to encrypt and decrypt your cPanel username and password.

= What if I forget the Security Access Key? =

Simply click the orange 'Reset' button on the plugin's security page. This will delete the current security access key,
the stored cpanel username and the stored cpanel password.

You will then need to supply a new Security Access Key (as you would have at the beginning) before adding any new cPanel information. 

= What is the CONFIRM box all about? =

As you can imagine with great power comes great responsibility. This plugin lets you delete databases and users in bulk.

So, before I can let you do that, you must type in the word CONFIRM exactly as it is, in capital letters, each time you want
to perform a task. If you don't, the task will fail.

This is a small protection against accidental clicks etc. If you accidently delete all your databases and
you want to blame someone, you know that it could only have been done by typing CONFIRM and submitting the task. There's NO
way around this.

= Can I undo my delete of databases and users? =

No!

Use the MySQL database delete and MySQL user delete functionality with GREAT CARE. You are wholly responsible for any mess you create. 

= Where is all the documentation? =

The cPanel Manager is very easy to use right now because there isn't much functionality.

But, documentation is coming. I wanted to get this work out to the public first, [get feedback](http://worpit.com/help-support/?src=wporg "Worpit: Manage Muliple WordPress Sites Better")
and then move on from there.

= Do you make any other plugins? =

Yes, we created the only [Twitter Bootstrap WordPress](http://worpit.com/wordpress-twitter-bootstrap-css-plugin-home/ "Twitter Bootstrap WordPress Plugin")
plugin with over 20,000 downloads so far.

We also created the [Manage Multiple WordPress Site Better Tool: Worpit](http://worpit.com/?src=wporg) for people with multiple WordPress sites to manage.

== Changelog ==

= 1.1 =

* ADDED: Encryption mechanism of sensitive cPanel data through use of a Security Access Key. REQUIRES: PHP mcrypt library extension to be loaded.
* ADDED: Permanent warning message if you don't have the mcrypt library extension loaded and your data isn't encrypted.
* CHANGED: Regardless of whether you can encrypt your data or not, cPanel username and password are serialized before being stored to WP DB.

= 1.0 =

* First Release.

== Upgrade Notice ==

= 1.1 =

* ADDED: Encryption mechanism of sensitive cPanel data through use of a Security Access Key. REQUIRES: PHP mcrypt library extension to be loaded.

= 1.0 =

* First Release.
