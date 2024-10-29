=== WordPress BasePress Migration Tools ===
Contributors: codesavory
Donate link: https://codesavory.com
Tags: basepress,migration,export,import,transfer,documentation,documents,docs,knowledgebase,knowledge base
Requires at least: 4.5
Tested up to: 5.7
Stable tag: 1.0.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Migrate all BasePress Knowledge Base content with ease between sites. Including KBs, sections, articles and settings.

== Description ==
If you are using BasePress Knowledge Base in your site, chances are that you may need to move all of its content to a new site.
BasePress Migration Tools is the easiest way to do that. It generates an export file with all the Knowledge Bases, sections, articles and settings data.
You can then import the content using the same Migration tools in the destination site.

⚠️ _**It is strongly recommended to create a backup of both source and destination sites before exporting and importing content.**_

#### **What it does**
The BasePress Migration tools generates a xml file to import all knowledge base data to a new site.
It will also update the links to any media files and internal links with the new site domain.
Apart from export/import the Migration tools can also be used to remove all BasePress data from a site.

#### What it doesn't do
The BasePress Migration tools doesn't transfer any of the attached media files used in the KBs, sections and articles.
You would need to manually transfer them via FTP from your source site to the destination site respecting the URL structure.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/plugin-name` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress.
1. A new menu item called Migration tools will be added under the BasePress menu.

== Frequently Asked Questions ==

= Should I create a backup of the sites before using this plugin? =

Yes it is strongly recommended to create a backup of both sites in case something goes wrong during the migration.

= Should BasePress be installed while using the Migration tools? =

Yes BasePress must be installed in both source and destination sites.

== Changelog ==

= 1.0.0 =
* initial release