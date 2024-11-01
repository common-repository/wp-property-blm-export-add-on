=== WP-Property - BLM Export ===
Contributors: BIOSTALL
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=N68UHATHAEDLN&lc=GB&item_name=BIOSTALL&no_note=0&cn=Add%20special%20instructions%20to%20the%20seller%3a&no_shipping=1&currency_code=GBP&bn=PP%2dDonationsBF%3abtn_donateCC_LG%2egif%3aNonHosted
Tags: property management, real estate, properties, property, property portal, property portal blm, real estate cms, wp-property blm, wp-property, blm export, blm feed, portal feed, rightmove, rightmove blm, estate agent
Requires at least: 3.0
Tested up to: 3.6
Stable tag: 1.0.0

== Description ==

An add-on for the WP-Property WordPress plugin that allows you to send a BLM feed to property portals automatically at regular intervals. Contains the following features:

* Automatic scheduling of feed creation; either twice a day, once a day, or every other day.
* Test mode and live mode to allow you to test the feed first before sending it.
* FTP upload - Feeds are automatically uploaded via FTP to the portals.
* Compression. Specify if you want to send all the files separately, or compress them into a single zip for quicker processing.
* Support for multiple branch codes per portal in the event you are dealing with a multi-office agency.
* Incremental feeds. The plugin only includes the media that is new or has recently changed to massively reduce sending times and filesize. Can be overwritten if required
* Archiving. Store files for a configurable amount of days to act as a temporary backup. These are then automatically cleaned up at regular intervals to prevent disk space being taken up.
* Manually run feeds to assist with testing
* View statistics for the past 30 days including a list of the properties sent in each feed, and a list of errors if any occur to aid with debugging why a feed or property may not have sent.
* Emailed reports. Get a report emailed to one or more recipients containing a log of what happened when the feeds ran. You can choose to receive these reports every time the feed runs, or only when errors occur.
* Advanced field mapping. Easy to use interface allowing you to select which fields setup in WP-Property relate to the fields in the BLM
* Extra resources for developers including links to a BLM validator, a sample BLM and BLM specification.
* Help tooltips throughout explaining what each field means.

**Screencast**

[youtube http://www.youtube.com/watch?feature=player_embedded&v=wMZCgkrfsE0]

**Requires**

* PHP ZipArchive extension
* [WP-Property plugin](http://wordpress.org/plugins/wp-property/)

== Installation ==

1. Ensure you have the WP-Property plugin installed and setup
2. Place this plugin's file into your plugins directory and activate the 'WP-Property - Portal Feeds BLM Export' plugin through the 'Plugins' menu in WordPress.
3. Under the 'Settings' menu there will now be a 'BLM Export' options panel where you can configure the panel.

== Screenshots ==

1. Export and Sending Options
2. Portals and Branch Codes
3. Field Mapping
4. Archiving
5. Statistics
6. Actions and Tools

== Changelog ==

= 1.0.0 =
* 2013-07-15 - First commit

== Upcoming features ==

* Aim to include floorplans, brochures, EPC's and virtual tours. Because there is no definitive way to add these in WP-Property it's difficult to know how people will be storing these.
* Make field mapping more intelligent by pre-selecting fields based on their names
* Allow user to choose which size of image they include in the feed