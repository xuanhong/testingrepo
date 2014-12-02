<?php

/**
 * Helper to do common string operations, such as word wrap and censoring.
 *
 * @package XenForo_Helper
 */
class XenForo_Helper_String
{
	/**
	 * Censor cache for default words/censor string option.
	 *
	 * @var array|null Null when not set up
	 */
	protected static $_censorCache = null;

	/**
	 * Internal value of html encoded variable for auto-linking. Only used
	 * to pass to callback.
	 *
	 * @var boolean
	 */
	protected static $_alptHtmlEncoded = true;

	/**
	 * Private constructor. Use statically.
	 */
	private function __construct() {}

	/**
	 * Adds spaces to the given string after the given amount of
	 * contiguous, non-whitespace characters.
	 *
	 * @param string $string
	 * @param integer $breakLength Number of characters before break; if null, use option
	 *
	 * @return string
	 */
	public static function wordWrapString($string, $breakLength)
	{
		$breakLength = intval($breakLength);
		if ($breakLength < 1 || $breakLength > strlen($string)) // strlen isn't completely accurate, but this is an optimization
		{
			return $string;
		}

		return preg_replace('#[^\s]{' . $breakLength . '}(?=[^\s])#u', '$0  ', $string);
	}

	/**
	 * Helper to allow templates to pass the censor string to self::censorString()
	 *
	 * @param string $string
	 * @param string $censorString
	 *
	 * @return string
	 */
	public static function censorStringTemplateHelper($string, $censorString = null)
	{
		return self::censorString($string, null, $censorString);
	}

	/**
	 * Censors the given string.
	 *
	 * @param string $string
	 * @param array|null $words Words to censor. Null to use option value.
	 * @param string|null $censorString String to censor each character with. Null to use option value.
	 *
	 * @return string
	 */
	public static function censorString($string, array $words = null, $censorString = null)
	{
		$allowCache = ($words === null && $censorString === null); // ok to use cache for default
		$censorCache = ($allowCache ? self::$_censorCache : null);

		if ($censorCache === null)
		{
			if ($words === null)
			{
				$words = XenForo_Application::get('options')->censorWords;
			}

			if (!$words)
			{
				if ($allowCache)
				{
					self::$_censorCache = array();
				}

				return $string;
			}

			if ($censorString === null)
			{
				$censorString = XenForo_Application::get('options')->censorCharacter;
			}

			$censorCache = self::buildCensorArray($words, $censorString);

			if ($allowCache)
			{
				self::$_censorCache = $censorCache;
			}
		}

		if (!empty($censorCache['exact']))
		{
			$string = preg_replace(array_keys($censorCache['exact']), $censorCache['exact'], $string);
		}
		if (!empty($censorCache['any']))
		{
			$string = str_ireplace(array_keys($censorCache['any']), $censorCache['any'], $string);
		}

		return $string;
	}

	/**
	 * Builds the censorship array.
	 *
	 * @param array $words List of words (from option format)
	 * @param string $censorString String to replace each character with if no replacement map
	 *
	 * @return array Possible keys: exact, any with key-value search/replace pairs
	 */
	public static function buildCensorArray(array $words, $censorString)
	{
		$censorCache = array();

		if (!empty($words['exact']))
		{
			$exact = array();
			foreach ($words['exact'] AS $word => $replace)
			{
				$search = '#(?<=\W|^)(' . preg_quote($word, '#') . ')(?=\W|$)#iu';
				if (is_int($replace))
				{
					$exact[$search] = str_repeat($censorString, $replace);
				}
				else
				{
					$exact[$search] = $replace;
				}
			}

			$censorCache['exact'] = $exact;
		}

		if (!empty($words['any']))
		{
			$any = array();
			foreach ($words['any'] AS $word => $replace)
			{
				if (is_int($replace))
				{
					$any[$word] = str_repeat($censorString, $replace);
				}
				else
				{
					$any[$word] = $replace;
				}
			}

			$censorCache['any'] = $any;
		}

		return $censorCache;
	}

	/**
	 * Returns a string that is snipped to $maxLength characters at the nearest space,
	 * Appends an elipsis if the string is snipped.
	 *
	 * @param string
	 * @param integer Max length of returned string, excluding elipsis
	 * @param integer Offset from string start - will add leading elipsis
	 * @param string Elipses string (default: '...')
	 *
	 * @return string
	 */
	public static function wholeWordTrim($string, $maxLength, $offset = 0, $elipses = '...')
	{
		//TODO: this may need a handler for language independence and some form of error correction for bbcode

		if ($offset)
		{
			$string = preg_replace('/^\S*\s+/s', '', utf8_substr($string, $offset));
		}

		$strLength = utf8_strlen($string);

		if ($maxLength > 0 && $strLength > $maxLength)
		{
			$string = utf8_substr($string, 0, $maxLength);
			$string = strrev(preg_replace('/^\S*\s+/s', '', strrev($string))) . $elipses;
		}

		if ($offset)
		{
			$string = $elipses . $string;
		}

		return $string;
	}

	/**
	 * Automatically links URLs/email in the given string of BB code. Output will be original
	 * BB code input with [url] or [email] tags inserted where ncessary.
	 *
	 * @param string $string BB code string
	 * @param boolean $autoEmbedIfEnabled Do auto embed of media if enabled in options
	 *
	 * @return string
	 */
	public static function autoLinkBbCode($string, $autoEmbedIfEnabled = true)
	{
		$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_BbCode_AutoLink', false);
		$formatter->setAutoEmbed($autoEmbedIfEnabled);
		$parser = XenForo_BbCode_Parser::create($formatter);
		return $parser->render($string);
	}

	/**
	 * Auto-links URLs in plain text. This text should generally already be HTML
	 * escaped, because it can't be done after the linking.
	 *
	 * @param string $string
	 * @param boolean $htmlEncoded Denotes whether the text is already encoded; if false, the URL will be encoded before being put into the link
	 *
	 * @return string Text with links added
	 */
	public static function autoLinkPlainText($string, $htmlEncoded = true)
	{
		self::$_alptHtmlEncoded = $htmlEncoded;

		return preg_replace_callback(
			'#(?<=[^a-z0-9@-]|^)(https?://|ftp://|www\.)[^\s"]+#i',
			array('self', '_autoLinkPlainTextCallback'),
			$string
		);
	}

	/**
	 * Internal handler for auto-linking regex.
	 *
	 * @param array $match
	 *
	 * @return string
	 */
	protected static function _autoLinkPlainTextCallback(array $match)
	{
		$link = self::prepareAutoLinkedUrl($match[0]);

		if (!self::$_alptHtmlEncoded)
		{
			$link['url'] = htmlspecialchars($link['url']);
		}

		list($class, $target) = self::getLinkClassTarget($link['url']);
		$class = $class ? " class=\"$class\"" : '';
		$target = $target ? " target=\"$target\"" : '';

		return '<a href="' . $link['url'] . "\" rel=\"nofollow\"$class$target>" . $link['linkText'] . '</a>' . $link['suffixText'];
	}

	/**
	 * Gets the class and target to apply to a specified link URL.

	 * @param string $url
	 *
	 * @return array [class, target, type (internal/external)]
	 */
	public static function getLinkClassTarget($url)
	{
		$target = '_blank';
		$class = 'externalLink';
		$type = 'external';

		$urlInfo = @parse_url($url);
		if ($urlInfo)
		{
			if (empty($urlInfo['host']))
			{
				$isInternal = true;
			}
			else
			{
				$host = $urlInfo['host'] . (!empty($urlInfo['port']) ? ":$urlInfo[port]" : '');
				$isInternal = ($host == XenForo_Application::$host);
			}

			if ($isInternal)
			{
				$target = '';
				$class = 'internalLink';
				$type = 'internal';
			}
		}

		return array($class, $target, $type);
	}

	/**
	 * Given a text that appears to be a URL, extracts the components from it,
	 * possibly moving characters after the link or adding http://.
	 *
	 * @param string $url URL that may have trailing characters or missing scheme
	 *
	 * @return array Keys: url, linkText, suffixText
	 */
	public static function prepareAutoLinkedUrl($url)
	{
		$linkText = $url;
		$suffixText = '';

		if (strpos($url, '://') === false)
		{
			$url = 'http://' . $url;
		}

		do
		{
			$matchedTrailer = false;
			$lastChar = substr($url, -1);
			switch ($lastChar)
			{
				case ')':
					if (substr_count($url, ')') == substr_count($url, '('))
					{
						break;
					}
					// break missing intentionally

				case '.':
				case ',':
				case '!':
				case ':':
				case "'":
					$suffixText = $lastChar . $suffixText;
					$url = substr($url, 0, -1);
					$linkText = substr($linkText, 0, -1);

					$matchedTrailer = true;
					break;
			}
		}
		while ($matchedTrailer);

		return array(
			'url' => $url,
			'linkText' => $linkText,
			'suffixText' => $suffixText
		);
	}

	/**
	 * Strips BB code from a string
	 *
	 * @param string $string
	 * @param boolean $stripQuote If true, contents from within quote tags are stripped
	 *
	 * @return string
	 */
	public static function bbCodeStrip($string, $stripQuote = false)
	{
		/*$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_BbCode_Strip', false);
		$formatter->setMaxQuoteDepth(0);

		$parser = XenForo_BbCode_Parser::create($formatter);
		return $parser->render($string);*/

		if ($stripQuote)
		{
			$parts = preg_split('#(\[quote[^\]]*\]|\[/quote\])#', $string, -1, PREG_SPLIT_DELIM_CAPTURE);
			$string = '';
			$quoteLevel = 0;
			foreach ($parts AS $i => $part)
			{
				if ($i % 2 == 0)
				{
					// always text, only include if not inside quotes
					if ($quoteLevel == 0)
					{
						$string .= rtrim($part) . "\n";
					}
				}
				else
				{
					// quote start/end
					if ($part[1] == '/')
					{
						// close tag, down a level if open
						if ($quoteLevel)
						{
							$quoteLevel--;
						}
					}
					else
					{
						// up a level
						$quoteLevel++;
					}
				}
			}
		}

		// replaces unviewable tags with a text representation
		$string = preg_replace('#\[(attach|media|img)[^\]]*\].*\[/\\1\]#siU', '[\\1]', $string);

		while ($string != ($newString = preg_replace('#\[([a-z0-9]+)(=[^\]]*)?\](.*)\[/\1\]#siU', '\3', $string)))
		{
			$string = $newString;
		}

		$string = str_replace('[*]', '', $string);

		return trim($string);
	}

	/**
	 * Creates a snippet of $length maximum characters around a search term.
	 * If the term is not found, the snippet is taken from the string start.
	 *
	 * @param $string
	 * @param $maxLength
	 * @param $term
	 *
	 * @return string
	 */
	public static function wholeWordTrimAroundSearchTerm($string, $maxLength, $term)
	{
		$stringLength = utf8_strlen($string);

		if ($stringLength > $maxLength)
		{
			$term = strval($term);

			if ($term !== '')
			{
				// TODO: slightly more intelligent search term matching, breaking up multiple words etc.
				$termPosition = utf8_strpos(utf8_strtolower($string), utf8_strtolower($term));
			}
			else
			{
				$termPosition = false;
			}

			if ($termPosition !== false)
			{
				// add term length to term start position
				$startPos = $termPosition + utf8_strlen($term);

				// count back half the max characters
				$startPos -= $maxLength / 2;

				// don't overflow the beginning
				$startPos = max(0, $startPos);

				// don't overflow the end
				$startPos = min($startPos, $stringLength - $maxLength);
			}
			else
			{
				$startPos = 0;
			}

			$string = self::wholeWordTrim($string, $maxLength, $startPos);
		}

		return $string;
	}

	/**
	 * Returns an HTML string with instances a search term highlighted with <em class="$emClass">...</em>
	 *
	 * @param string Haystack
	 * @param string Needle
	 * @param string Class with which to style the wrapping <em>
	 *
	 * @return string HTML
	 */
	public static function highlightSearchTerm($string, $term, $emClass = 'highlight')
	{
		$term = strval($term);
		if ($term !== '')
		{
			return preg_replace('/(' . preg_quote($term, '/') . ')/si', '<em class="' . $emClass . '">\1</em>', htmlspecialchars($string));
		}

		return htmlspecialchars($string);
	}

	/**
	 * Strips out quoted text from the specified string, allowing for quoted text
	 * up to a specified depth.
	 *
	 * @param string $string
	 * @param integer $allowedDepth -1 for unlimited depth
	 * @param boolean $censorResults
	 *
	 * @return string Quotes stripped
	 */
	public static function stripQuotes($string, $allowedDepth = -1, $censorResults = true)
	{
		if ($allowedDepth == -1)
		{
			return $string;
		}
		else
		{
			$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_BbCode_Strip', false);
			$formatter->setMaxQuoteDepth($allowedDepth);
			$formatter->setCensoring($censorResults);

			$parser = XenForo_BbCode_Parser::create($formatter);
			return $parser->render($string);
		}
	}
}