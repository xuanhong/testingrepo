<?php

class XfAddOns_Sitemap_ViewPublic_Robots extends XenForo_ViewPublic_Base
{
	
	/**
	 * The response type is set to raw, we will then render the template that drives the content
	 * @return XenForo_Template_Abstract
	 */
	public function renderRaw() 
	{
		// get the template and set sensible defaults. We need to do this because we don't always come from the FrontController
		$template = $this->createTemplateObject($this->_templateName, $this->_params);
		$visitor = XenForo_Visitor::getInstance();
		$options = XenForo_Application::get('options');
		
		$styleId = $visitor->get('style_id');
		$styleId = $styleId ? $styleId : $options->defaultStyleId;
		$template->setStyleId($styleId);
		
		$languageId = $visitor->get('language_id');
		$languageId = $languageId ? $languageId : $options->defaultLanguageId;
		$template->setLanguageId($languageId);
		
		// render the template
		$output = $template->render();
		$output = $this->collapseWhiteSpace($output);
		return $output;
	}
	
	/**
	 * Collapse the whitespace so the robots.txt looks a little more tidy. This is completely optional as far as
	 * the robots are concerned
	 * @param The robots.txt contents $output
	 * @return string
	 */
	protected function collapseWhiteSpace($output)
	{
		// we will collapse all additional line breaks
		$output = preg_replace("/[\n][\n]+/", "\n", trim($output));
		
		// but will actually add some for a few tokens
		$output = preg_replace("/(User-agent|Sitemap)/", "\r\n$1", trim($output));

		return trim($output);
	}
	
	
}

