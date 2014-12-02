<?php

/**
 * Base class for generating SiteMap information. Holds common logic like generating the root node
 * and common functions shared between the generators
 */
abstract class XfAddOns_Sitemap_Sitemap_Base
{

	/**
	 * The DOM document
	 * @var DomDocument
	 */
	protected $dom;

	/**
	 * Root node. Maps to the sitemapindex portion
	 * @var DomElement
	 */
	protected $root;

	/**
	 * The name of the root tag, usually sitemapindex or urlset
	 * @var string		A string with the url for the root tag
	 */
	private $rootName;

	/**
	 * A reference to the anonymous visitor, used for validating permissions
	 * @var XenForo_Visitor
	 */
	protected $defaultVisitor;

	/**
	 * A variable that will track if the xml file is empty. This must be set to true on initialize() and to false every time
	 * we add a URL
	 * @var boolean
	 */
	public $isEmpty = true;
	
	/**
	 * The maximum number of URLs that may be included in a single sitemap file
	 * @var int
	 */
	protected $maxUrls = -1;

	/**
	 * Directory that will store the sitemaps
	 */
	protected $sitemapDir;	

	/**
	 * Constructor. Initializes the document
	 * @param $rootName		The name for the root tag of the XML file, usually sitemapindex or urlset
	 */
	public function __construct($rootName = 'urlset')
	{
		$this->rootName = $rootName;
		$this->defaultVisitor = XenForo_Visitor::setup(0);
		
		$options = XenForo_Application::getOptions();
		$this->maxUrls = $options->xenforo_sitemap_max_urls;
		$this->sitemapDir = $options->xenforo_sitemap_directory;
	}

	/**
	 * Initialize the Document and the Root node for the XML document. Call this whenever you want to
	 * generate the contents of the document or you need to wipe all generated information to start fresh
	 */
	protected function initialize()
	{
		$this->dom = new DomDocument('1.0', 'UTF-8');
		$this->dom->formatOutput = true;		// may disable this

		$this->root = $this->dom->createElement($this->rootName);
		$this->root->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
		$this->dom->appendChild($this->root);
		$this->isEmpty = true;
	}

	/**
	 * Save the generated file. This will attempt to create the file with the correct encoding,
	 * and to compress the file into a gzip format
	 * @param $fileName		The name of the file in which the contents will be saved
	 * @return		The real name that was used to save the file
	 */
	public function save($fileName)
	{
		$this->dom->save($fileName);
		if (function_exists('gzopen'))
		{
			$fd = fopen($fileName, 'r');
			$out = gzopen($fileName . '.gz', 'w');
			while (($data = fgets($fd, 4096)) !== false)
			{
				gzwrite($out, $data);
			}
			gzclose($out);
			fclose($fd);
			unlink($fileName);
			return $fileName . '.gz';
		}
		return $fileName;
	}

	/**
	 * Adds a new XML node, initializing the tag and contents. This method is used to,
	 * for an existing node, a new tag with the contents as-is. All the contents will be
	 * included as a textNode
	 *
	 * @param DOMElement $node		The node to which we want to add the content
	 * @param String 	$tagName	The name of the tag that should be added
	 * @param String 	$contents	The contents for the tag. They wil be added as-is
	 */
	protected function addNode(&$node, $tagName, $contents)
	{
		$resultNode = $this->dom->createElement($tagName);
		$resultText = $this->dom->createTextNode($contents);
		$resultNode->appendChild($resultText);
		$node->appendChild($resultNode);
	}

	/**
	 * This method can be called to add a new URL to the sitemap. We will need
	 * 	- The URL
	 * 	- The last time the forum had a post
	 *
	 * @param string $url		The url that we want to add
	 */
	protected function addUrl($loc, $lastPostDate)
	{
		$url = $this->dom->createElement('url');
		$this->root->appendChild($url);

		$this->addNode($url, 'loc', $loc);
		$this->addNode($url, 'lastmod', gmdate('Y-m-d', $lastPostDate));
		$this->isEmpty = false;
	}
	
	/**
	 * This method will generate a unique sitemap name, add it to the local list of sitemaps that are being generated
	 * and return the name
	 * @return string
	 */
	protected function getSitemapName($type)
	{
		// figure out an incremental index depending on the sitemap type
		static $typeDict = array();
		if (!isset($typeDict[$type]))
		{
			$typeDict[$type] = 0;
		}
		$idx = ++$typeDict[$type];
	
		// generate the name
		$name = $this->sitemapDir . '/sitemap.' . $type . '.' . ($idx) . '.xml';
		return $name;
	}	


}