<?php

class XenForo_BbCode_Formatter_Wysiwyg extends XenForo_BbCode_Formatter_Base
{
	protected $_undisplayableTags = array('quote', 'plain', 'media', 'attach', 'user', 'code', 'php', 'html');

	protected $_tagReplacements = array(
		'u' => array(
			'replace' => array('<u>', '</u>'),
		),
		's' => array(
			'replace' => array('<strike>', '</strike>'),
		),
		'font' => array(
			'replace' => array('<font face="%s">', '</font>')
		)
	);

	protected $_imageTemplate = '<img src="%1$s" class="bbCodeImage wysiwygImage" alt="" unselectable="on" />';

	protected $_smilieTemplate = '<img src="%1$s" class="mceSmilie" alt="%2$s" unselectable="on" />';

	protected $_smilieSpriteTemplate = '<img src="styles/default/xenforo/clear.png" class="mceSmilieSprite mceSmilie%1$d" alt="%2$s" unselectable="on" unselectable="on" />';

	public function getTags()
	{
		if ($this->_tags !== null)
		{
			return $this->_tags;
		}

		$tags = parent::getTags();

		foreach ($tags AS $tagName => &$tag)
		{
			if (in_array($tagName, $this->_undisplayableTags))
			{
				$tag['callback'] = array($this, 'renderTagUndisplayable');
				unset($tag['trimLeadingLinesAfter'], $tag['stopLineBreakConversion']);
			}
			else if (isset($this->_tagReplacements[$tagName]))
			{
				$tag = array_merge($tag, $this->_tagReplacements[$tagName]);
				if (isset($this->_tagReplacements['replace']))
				{
					unset($tag['callback']);
				}
			}
		}

		return $tags;
	}

	protected $_tagFilterMatched;
	protected $_blockTags = '<p|<div|<blockquote|<ul|<ol';
	protected $_blockTagsEnd;

	public function filterFinalOutput($output)
	{
		$debug = false;

		$blockTags = $this->_blockTags;
		$blockTagsEnd = strtr($blockTags, array(
			'<' => '</',
			'|' => '>|'
		)) . '>';
		$this->_blockTagsEnd = $blockTagsEnd;

		if ($debug) { echo '<hr /><b>Original:</b><br />'. nl2br(htmlspecialchars($output)); }

		// process first to last - inner tags will have lower numbers and
		// we need to keep the same nesting order
		for ($i = 1; $i <= $this->_tagCounter; $i++)
		{
			$this->_tagFilterMatched = false;
			$output = preg_replace_callback(
				'#<(xf' . $i . ':((?>[a-z0-9-]+?)))([^>]*)(?<!/)>((?:(?>[^<]*?)|<)*)</\\1>#siU',
				array($this, '_replaceTagFilter'),
				$output
			);
			if (!$this->_tagFilterMatched)
			{
				$output = strtr($output, array(
					'<xf' . $i . ':' => '<',
					'</xf' . $i . ':' => '</'
				));
			}

			//if ($debug) { echo '<hr />'. nl2br(htmlspecialchars($output)); }
		}
		$this->_tagCounter = 0;

		if ($debug) { echo '<hr /><b>Pre-break:</b><br />'. nl2br(htmlspecialchars($output)); }

		$output = preg_replace('#\s*<break-start />(?>\s*)(?!' . $blockTags . '|' . $blockTagsEnd . '|$)#i', ($debug ? "\n" : '') . "<p>", $output);
		$output = preg_replace('#\s*<break-start />#i', '', $output);
		$output = preg_replace('#(' . $blockTagsEnd . ')\s*<break />#i', "\\1", $output);
		$output = preg_replace('#<break />\s*(' . $blockTags . ')#i', "</p>" . ($debug ? "\n" : '') . "\\1", $output);
		$output = preg_replace('#<break />\s*#i', "</p>" . ($debug ? "\n" : '') . "<p>", $output);

		if ($debug) { echo '<hr /><b>Post-break:</b><br />'. nl2br(htmlspecialchars($output)); }

		$output = trim($output);
		if (!preg_match('#^(' . $blockTags . ')#i', $output))
		{
			$output = '<p>' . $output;
		}
		if (!preg_match('#(' . $blockTagsEnd . ')$#i', $output))
		{
			$output .= '</p>';
		}

		$output = preg_replace_callback('#(<p[^>]*>)(.*)(</p>)#siU',
			array($this, '_replaceEmptyContent'), $output
		);
		$output = str_replace('<empty-content />', '', $output); // just in case

		if ($debug) { echo '<hr /><b>Final:</b><br />'. nl2br(htmlspecialchars($output)); }

		return $output;
	}

	protected function _replaceTagFilter(array $match)
	{
		$this->_tagFilterMatched = true;

		$open = "<$match[2]$match[3]>";
		$close = "</$match[2]>";

		$inner = $match[4];
		$inner = preg_replace('#(<break />\s*)(<break />|$)#i', '\\1<empty-content />\\2', $inner);

		if (preg_match('#^(' . $this->_blockTags . ')#i', $open))
		{
			$inner = preg_replace('#<break-start />(?>\s*)(?!' . $this->_blockTags . '|' . $this->_blockTagsEnd . '|$)#i', "\\0$open", $inner);
			$inner = preg_replace('#<break />(?>\s*)(?!' . $this->_blockTags . ')#i', "$close\\0$open", $inner);
		}
		else
		{
			$inner = preg_replace('#<break />\s*((' . $this->_blockTags . ')[^>]*>)?#i', "$close\\0$open", $inner);
		}

		return $open . $inner . $close;
	}

	protected function _replaceEmptyContent(array $match)
	{
		$emptyParaText = (XenForo_Visitor::isBrowsingWith('ie') ? '&nbsp;' : '<br />');

		if (strlen(trim($match[2])) == 0)
		{
			// paragraph is actually empty
			$output = $emptyParaText;
		}
		else
		{
			$test = strip_tags($match[2], '<empty-content><img><br><hr>');
			if (trim($test) == '<empty-content />')
			{
				$output = str_replace('<empty-content />', $emptyParaText, $match[2]);
			}
			else
			{
				// we had a break
				$output = str_replace('<empty-content />', '', $match[2]);
			}
		}

		return $match[1] . $output . $match[3];
	}

	public function filterString($string, array $rendererStates)
	{
		if (empty($rendererStates['stopSmilies']))
		{
			$string = $this->replaceSmiliesInText($string, 'htmlspecialchars');
		}
		else
		{
			$string = htmlspecialchars($string);
		}

		$string = str_replace("\t", '    ', $string);
		
		// doing this twice handles situations with 3 spaces
		$string = str_replace('  ', '&nbsp; ', $string);
		$string = str_replace('  ', '&nbsp; ', $string);

		if (empty($rendererStates['stopLineBreakConversion']))
		{
			if (!empty($rendererStates['inList']))
			{
				$string = nl2br($string);
			}
			else
			{
				$string = preg_replace('/\r\n|\n|\r/', "<break />\n", $string);
			}
		}

		return $string;
	}

	public function renderTagUrl(array $tag, array $rendererStates)
	{
		$rendererStates['shortenUrl'] = false;
		return parent::renderTagUrl($tag, $rendererStates);
	}

	public function renderTagSize(array $tag, array $rendererStates)
	{
		$text = $this->renderSubTree($tag['children'], $rendererStates);
		if (trim($text) === '')
		{
			return $text;
		}

		$size = $this->getTextSize($tag['option']);
		if ($size)
		{
			$fontSize = false;
			switch ($size)
			{
				case 'xx-small':
				case '9px':
					$fontSize = 1; break;

				case 'x-small':
				case '10px':
					$fontSize = 2; break;

				case 'small':
				case '12px':
					$fontSize = 3; break;

				case 'medium':
				case '15px':
				case '100%':
					$fontSize = 4; break;

				case 'large':
				case '18px':
					$fontSize = 5; break;

				case 'x-large':
				case '22px':
					$fontSize = 6; break;

				case 'xx-large':
				case '26px':
					$fontSize = 7; break;
			}

			if ($fontSize)
			{
				return $this->_wrapInHtml('<font size="' . htmlspecialchars($fontSize) . '">', '</font>', $text);
			}
			else
			{
				return $this->_wrapInHtml('<span style="font-size: ' . htmlspecialchars($size) . '">', '</span>', $text);
			}
		}
		else
		{
			return $text;
		}
	}

	public function renderTagAlign(array $tag, array $rendererStates)
	{
		$text = $this->renderSubTree($tag['children'], $rendererStates);

		switch (strtolower($tag['tag']))
		{
			case 'left':
			case 'center':
			case 'right':
				return $this->_wrapInHtml('<p style="text-align: ' . $tag['tag'] . '">', '</p>', $text) . "<break-start />\n";

			default:
				return $this->_wrapInHtml('<p>', '</p>', $text) . "<break-start />\n";
		}
	}

	public function renderTagList(array $tag, array $rendererStates)
	{
		$wasInList = !empty($rendererStates['inList']);
		$rendererStates['inList'] = true;

		$output = parent::renderTagList($tag, $rendererStates);
		$output = preg_replace('#\s*<break-start />\s*#i', "\n", $output);
		if (!$wasInList)
		{
			$output = "<break-start />\n$output<break-start />\n";
		}

		return $output;
	}

	public function renderTagIndent(array $tag, array $rendererStates)
	{
		$wasInIndent = !empty($rendererStates['inIndent']);
		$rendererStates['inIndent'] = true;

		$text = $this->renderSubTree($tag['children'], $rendererStates);
		if (trim($text) === '')
		{
			$text = '<br />';
		}

		if (isset($tag['option']))
		{
			$amount = intval($tag['option']);
			if ($amount > 10)
			{
				$amount = 10;
			}
		}
		else
		{
			$amount = 1;
		}

		$prepend = '';
		$append = '';
		for ($i = 1; $i <= $amount; $i++)
		{
			$prepend .= '<blockquote>';
			$append = '</blockquote>' . $append;
		}

		if (strpos($text, '<blockquote') === false)
		{
			$text = '<p>' . $text . '</p>';
		}

		$output = $prepend . $text . $append;
		if (!$wasInIndent)
		{
			$output .= "<break-start />\n";
		}

		return $output;
	}

	protected function _renderListOutput($listType, array $elements)
	{
		$output = "<$listType>";
		foreach ($elements AS $element)
		{
			$output .= "\n<li>$element</li>";
		}
		$output .= "\n</$listType>";

		return $output;
	}


	public function renderTagUndisplayable(array $tag, array $rendererStates)
	{
		return $this->renderInvalidTag($tag, $rendererStates);
	}

	protected $_tagCounter = 0;

	protected function _wrapInHtml($prepend, $append, $text, $option = null)
	{
		$this->_tagCounter++;

		$replace = array(
			'</' => '</xf' . $this->_tagCounter . ':',
			'<' => '<xf' . $this->_tagCounter . ':'
		);
		$prepend = strtr($prepend, $replace);
		$append = strtr($append, $replace);

		if ($option === null)
		{
			return $prepend . $text . $append;
		}
		else
		{
			return sprintf($prepend, $option) . $text . sprintf($append, $option);
		}
	}
}