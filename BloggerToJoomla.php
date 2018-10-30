<?php
const _JEXEC = 1;

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);

if (file_exists(dirname(__DIR__) . '/defines.php'))
{
	require_once dirname(__DIR__) . '/defines.php';
}

if (!defined('_JDEFINES'))
{
	define('JPATH_BASE', dirname(__DIR__));
	require_once JPATH_BASE . '/includes/defines.php';
}
define('JPATH_COMPONENT_ADMINISTRATOR', JPATH_ADMINISTRATOR . '/components/com_content');
require_once JPATH_LIBRARIES . '/import.legacy.php';
require_once JPATH_LIBRARIES . '/cms.php';
require_once JPATH_CONFIGURATION . '/configuration.php';
$config = new JConfig;
define('JDEBUG', $config->debug);

use Joomla\Registry\Registry;

class BloggerToJoomla extends JApplicationCli
{
	public function doExecute()
	{
		$_SERVER['HTTP_HOST'] = 'domain.com';
		JFactory::getApplication('administrator');
		JTable::addIncludePath(JPATH_SITE . '/administrator/components/com_tags/tables');

		$db = JFactory::getDbo();
		$params = JComponentHelper::getParams('com_content');

		if ($params->get('save_history'))
		{
			$this->out('Article revisions are enabled. Please disable them at the component settings and run the migration again.');
			exit;
		}

		JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_content/tables');
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');

		$filter = JFilterInput::getInstance(array('br', 'div', 'ol', 'ul', 'li', 'p', 'img', 'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'u'), array('href', 'src', 'alt', 'rel', 'target', 'class', 'id'));
		require_once JPATH_ADMINISTRATOR . '/components/com_content/models/article.php';
		$articleModel = new ContentModelArticle(array('table_path' => JPATH_ADMINISTRATOR . '/components/com_content/tables'));

		$options = new Registry();
		$options->set('timeout', 15);
		$http = JHttpFactory::getHttp($options);

		$feed = new DOMDocument();
		$feed->loadXML(file_get_contents('blogger.xml'));

		$finder = new DomXPath($feed);
		$finder->registerNamespace('thr', 'http://purl.org/syndication/thread/1.0');
		$commentsXML = '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dsq="http://www.disqus.com/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:wp="http://wordpress.org/export/1.0/"><channel>';

		$rows = $feed->getElementsByTagName('entry');

		foreach ($rows as $row)
		{
			$data = array();

			$terms = array();
			$categories = $row->getElementsByTagName('category');
			$isPost = false;

			foreach ($categories as $category)
			{
				if ($category->hasAttribute('scheme') && $category->getAttribute('scheme') == 'http://www.blogger.com/atom/ns#')
				{
					$terms[] = $category->getAttribute('term');
				}

				if ($category->hasAttribute('term') && $category->getAttribute('term') == 'http://schemas.google.com/blogger/2008/kind#post')
				{
					$isPost = true;
				}
			}

			if (!$isPost)
			{
				continue;
			}

			$data['state'] = 1;
			$draft = $row->getElementsByTagNameNS('http://purl.org/atom/app#', 'draft');

			if ($draft->length > 0 && $draft->item(0)->nodeValue == 'yes')
			{
				$data['state'] = 0;

				continue;
			}

			$data['title'] = $row->getElementsByTagName('title')->item(0)->nodeValue;

			if (!$data['title'])
			{
				continue;
			}

			$created = JFactory::getDate($row->getElementsByTagName('published')->item(0)->nodeValue);
			$data['created'] = $created->toSql();
			$data['publish_up'] = $data['created'];
			$data['fulltext'] = $row->getElementsByTagName('content')->item(0)->nodeValue;

			if ($data['fulltext'])
			{
				libxml_use_internal_errors(true);
				$dom = new DOMDocument('1.0', 'UTF-8');
				$dom->substituteEntities = false;
				$html = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="charset=utf-8" /></head><body>' . $data['fulltext'] . '</body></html>';

				if ($dom->loadHTML($html))
				{
					$images = $dom->getElementsByTagName('img');

					if ($images->length > 0)
					{
						for ($i = $images->length; --$i >= 0;)
						{
							$image = $images->item($i);
							$src = $image->getAttribute('src');
							$alt = $image->getAttribute('alt');
							$filename = basename(urldecode($src));
							$filename = strtolower($filename);
							$filename = str_replace('+', '-', $filename);
							$filename = JFile::makeSafe($filename);

							try
							{
								$response = $http->get($src);
							}
							catch (\Exception $e)
							{
								$this->out('Failed downloading image ' . $src . '. Skiped. ');

								continue;
							}

							if ($response && $response->code == 200)
							{
								$buffer = $response->body;
								$path = 'images/' . $created->format('Y') . '/' . $created->format('m') . '/' . $filename;

								JFile::write(JPATH_SITE . '/' . $path, $buffer);
								$image->setAttribute('src', $path);

								if ($i == 0)
								{
									$articleImages = new stdClass;
									$articleImages->image_intro = $path;
									$articleImages->image_intro_alt = $alt ? $alt : $data['title'];
									$articleImages->image_intro_caption = '';
									$data['images'] = json_encode($articleImages);
								}
							}
							else
							{
								$this->out('Failed downloading image ' . $src);

								continue;
							}

							if ($image->parentNode && $image->parentNode->tagName == 'a' && $image->parentNode->hasAttribute('imageanchor'))
							{
								$image->parentNode->removeAttribute('imageanchor');
								$image->parentNode->setAttribute('class', 'modal');

								$src = $image->parentNode->getAttribute('href');
								$filename = basename(urldecode($src));
								$filename = strtolower($filename);
								$filename = str_replace('+', '-', $filename);
								$filename = JFile::makeSafe($filename);

								try
								{
									$response = $http->get($src);
								}
								catch (\Exception $e)
								{
									$this->out('Failed downloading image ' . $src . '. Skiped. ');

									continue;
								}

								if ($response && $response->code == 200)
								{
									$buffer = $response->body;
									$path = 'images/' . $created->format('Y') . '/' . $created->format('m') . '/' . $filename;

									JFile::write(JPATH_SITE . '/' . $path, $buffer);
									$image->parentNode->setAttribute('href', $path);

									if ($i == 0)
									{
										$parent = $image->parentNode;
										$parent->parentNode->removeChild($parent);
									}
								}
								else
								{
									$this->out('Failed downloading image ' . $src);

									continue;
								}
							}

							if ($i == 0)
							{
								$image->parentNode->removeChild($image);
							}
						}
					}

					$body = $dom->getElementsByTagName('body')->item(0);
					$output = new DOMDocument();

					foreach ($body->childNodes as $child)
					{
						$output->appendChild($output->importNode($child, true));
					}
					$data['fulltext'] = $output->saveHTML();
					$data['fulltext'] = $filter->clean($data['fulltext'], 'HTML');
					$data['fulltext'] = str_replace('<div', '<p', $data['fulltext']);
					$data['fulltext'] = str_replace('</div>', '</p>', $data['fulltext']);
				}
			}

			$links = $row->getElementsByTagName('link');

			foreach ($links as $link)
			{
				if ($link->hasAttribute('rel') && $link->getAttribute('rel') == 'alternate')
				{
					$href = $link->getAttribute('href');
					$path = parse_url($href, PHP_URL_PATH);
					$parts = explode('.', $path);
					$parts2 = explode('/', $parts[0]);
					$data['alias'] = $parts2[count($parts2) - 1];
				}
			}

			$this->setArticleTaxonomy($data, $terms);
			$this->setArticleAuthor($data);
			$data['language'] = '*';

			$query = $db->getQuery(true);
			$query->select('id');
			$query->from('#__content');
			$query->where($db->qn('alias') . ' = ' . $db->q($data['alias']));
			$db->setQuery($query);
			$aliasExists = $db->loadResult();

			if ($aliasExists)
			{
				$data['alias'] .= '-' . uniqid();
			}

			$articleTable = JTable::getInstance('Content', 'JTable');

			if (!$articleTable->save($data))
			{
				$this->out('Error migrating post ' . $data['title'] . ': ' . $articleTable->getError());

				continue;
			}

			try
			{
				$query = $db->getQuery(true);
				$query->select('MAX(id)');
				$query->from('#__content');
				$db->setQuery($query);
				$id = $db->loadResult();

				$query = $db->getQuery(true);
				$query->update('#__content');
				$query->set('modified = ' . $db->q(JFactory::getDate($row->getElementsByTagName('updated')->item(0)->nodeValue)->toSql()));
				$query->where('id = ' . $id);
				$db->setQuery($query);
				$db->execute();

				$tagIds = array();

				foreach ($data['terms'] as $tag)
				{
					$query = $db->getQuery(true);
					$query->select('id');
					$query->from('#__tags');
					$query->where('title = ' . $db->q($tag));
					$db->setQuery($query);
					$tagId = $db->loadResult();

					if (!$tagId)
					{
						JModelLegacy::addIncludePath(JPATH_SITE . '/administrator/components/com_tags/models');
						$tagModel = JModelLegacy::getInstance('Tag', 'TagsModel');
						$tagsData = array('title' => $tag, 'published' => 1, 'language' => '*', 'parent_id' => 1);

						try
						{
							$res = $tagModel->save($tagsData);
						}
						catch (\Exception $e)
						{
							$this->out('Error creating tag ' . $tag . ': ' . $e->getMessage());
							exit;
						}

						$query = $db->getQuery(true);
						$query->select('id');
						$query->from('#__tags');
						$query->where('title = ' . $db->q($tag));
						$db->setQuery($query);
						$tagId = $db->loadResult();
					}

					$tagIds[] = $tagId;
				}

				if (count($tagIds))
				{
					$input = array('id' => $id, 'catid' => $data['catid'], 'tags' => $tagIds);
					$articleModel->save($input);
				}
			}
			catch (Exception $e)
			{
				$this->out('Error migrating post ' . $data['title'] . ': ' . $e->getMessage());
				exit;
			}

			$this->out('Migrated post ' . $data['title']);

			$bloggerId = $row->getElementsByTagName('id')->item(0)->nodeValue;
			$refs = $finder->query('//thr:in-reply-to[@ref="' . $bloggerId . '"]');
			$numOfComments = $refs->length;

			if ($numOfComments > 0)
			{
				$commentsXML .= '<item><title>' . htmlspecialchars($articleTable->title) . '</title><link>' . JUri::root(false) . '/' . $created->format('Y') . '/' . $created->format('m') . '/' . $articleTable->alias . '.html</link><content:encoded><![CDATA[' . $articleTable->fulltext . ']]></content:encoded><dsq:thread_identifier>joomla-article-' . $articleTable->id . '</dsq:thread_identifier><wp:post_date_gmt>' . $created->toSql() . '</wp:post_date_gmt><wp:comment_status>open</wp:comment_status>';

				for ($i = 0; $i < $numOfComments; $i++)
				{
					$ref = $refs[$i];
					$comment = $ref->parentNode;
					$commentsXML .= '<wp:comment><wp:comment_id></wp:comment_id><wp:comment_author>' . $comment->getElementsByTagName('name')->item(0)->nodeValue . '</wp:comment_author><wp:comment_author_email>' . $comment->getElementsByTagName('email')->item(0)->nodeValue . '</wp:comment_author_email><wp:comment_author_url>' . @$comment->getElementsByTagName('uri')->item(0)->nodeValue . '</wp:comment_author_url><wp:comment_author_IP></wp:comment_author_IP><wp:comment_date_gmt>' . JFactory::getDate($comment->getElementsByTagName('published')->item(0)->nodeValue)->toSql() . '</wp:comment_date_gmt><wp:comment_content><![CDATA[' . $comment->getElementsByTagName('content')->item(0)->nodeValue . ']]></wp:comment_content><wp:comment_approved>1</wp:comment_approved><wp:comment_parent></wp:comment_parent></wp:comment>';
				}

				$commentsXML .= '</item>';
			}
		}

		$commentsXML .= '</channel></rss>';

		file_put_contents(JPATH_SITE . '/cli/comments.xml', $commentsXML);

		$this->out('Peak memory used : ' . memory_get_peak_usage(true) . ' bytes');
		$this->out('Completed');
	}

	private function setArticleTaxonomy(&$data, $terms)
	{
		$data['catid'] = 0;
		$data['terms'] = array();
		$categories = array(8 => 'craft', 9 => 'food', 10 => 'travel', 11 => 'ideas', 12 => 'decor', 13 => 'parenting', 14 => 'books', 15 => 'ecolife');

		foreach ($terms as $term)
		{
			if (in_array($term, $categories))
			{
				$data['catid'] = array_search($term, $categories);
			}
			else
			{
				$data['terms'][] = $term;
			}
		}

		if (!$data['catid'])
		{
			$data['catid'] = 2;
		}
	}

	private function setArticleAuthor(&$data)
	{
		$data['created_by'] = 307;
	}
}

$application = JApplicationCli::getInstance('BloggerToJoomla');
$application->execute();
