# Blogger to Joomla and DISQUS
Simple Joomla CLI application for importing Blogger content to Joomla and DISQUS

## About

This is a quick and dirty Joomla CLI application created to migrate akamatra.com from Blogger to Joomla. The application will generate Joomla articles from the XML file exported by Blogger. The only hardcoded part is the categories and the user mappings. As a bonus the application will also generate an XML file for importing Blogger comments to DISQUS.

### Prerequisites

This requires that you have shell access to your server. This is a CLI script and you have to execute it using the command line.

### Installing

Upload the file BloggerToJoomla.php to your target Joomla installation folder under the /cli folder. Assuming that your Joomla installation is located under /var/www/yoursite the file should be located at /var/www/yoursite/cli/BloggerToJoomla.php. After that upload in the same directory the XML export from Blogger and rename it to "blogger.xml".

### Preparing

This script has some hardcoded parts. Those parts have to do with the categories and users. You will have to create the categories in Joomla manually and then update the setArticleTaxonomy function of the script to match the IDs of those categories. You also have to edit the setArticleAuthor function to return the user ID of the generated articles.

### Running

First make sure that you have a backup of your site. The application will create content to your site. Assuming that your site is located under /var/www/yoursite:
```
cd /var/www/yoursite/cli
php BloggerToJoomla.php
```

### DISQUS Comments

After executing the script you should see a file named "comments.xml" under the /cli directory. You can use this file to import comments into DISQUS. Read the official DISQUS documentation in order to find out the exact procedure. 

## Support

I do not plan to provide any support for this. It was written during the migration process for a client and I just wanted to share it. You are always free to fork it or submit your pull requests.

## License

This project is licensed under the GNU General Public License - see the [LICENSE](LICENSE) file for details


