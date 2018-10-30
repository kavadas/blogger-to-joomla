# Blogger to Joomla
Simple Joomla CLI application for importing Blogger to content to Joomla

## About

This is a simple Joomla CLI application created to migrate akamatra.com from Blogger to Joomla. The application will generate Joomla articles from XML exported by Blogger. The only hardcoded part is the categories and the user mappings. As a bonus the application will also generate an XML file for importing Blogger comments to DISQUS.

### Prerequisites

This requires that you have shell access to your server. This is a CLI script and you have to execute it using the command line.

### Installing

Upload the file BloggerToJoomla.php to your target Joomla installation folder under the /cli folder. Assuming that your Joomla installation is located under /var/www/yoursite the file should be located at /var/www/yoursite/cli/BloggerToJoomla.php

### Running

First make sure that you have a backup of your site. The application will create content to your site. Assuming that your site is located under /var/www/yoursite:
```
cd /var/www/yoursite/cli
php BloggerToJoomla.php
```

## Support

I do not plan to provide any support for this. It was written during the migration process for a client and I just wanted to share it. You are always free to fork it or submit your pull requests.

## License

This project is licensed under the GNU General Public License - see the [LICENSE](LICENSE) file for details


