<?php

/**
 * Class used to generate the sitemap contents for members
 */
class XfAddOns_Sitemap_Sitemap_Member extends XfAddOns_Sitemap_Sitemap_BasePagination
{

	/**
	 * Generate the members part of the sitemap
	 */
	public function generate()
	{
		$ret = array();
		XfAddOns_Sitemap_Logger::debug('Generating members...');
		while (!$this->isFinished)
		{
			XfAddOns_Sitemap_Logger::debug('-- Starting at ' . $this->lastId . ' and generating ' . $this->maxUrls .' urls...');
	
			$this->generateStep($this->maxUrls);
			if (!$this->isEmpty)
			{
				$ret[] = $this->save($this->getSitemapName('members'));
			}
		}
		return $ret;
	}	
	
	/**
	 * Append the information about the members to the sitemap
	 */
	protected function generateStep($totalMembers)
	{
		$this->initialize();

		$db = XenForo_Application::getDb();
		$sql = "
			SELECT *
			FROM xf_user user
			INNER JOIN xf_user_privacy user_privacy ON user.user_id = user_privacy.user_id 
			WHERE user.user_id > ? AND
				user.user_state = 'valid' AND
				user.is_banned = 0
			ORDER BY user.user_id
			";
		$st = new Zend_Db_Statement_Mysqli($db, $sql);
		$st->execute( array( $this->lastId ) );

		while ($data = $st->fetch())
		{
			if (!$this->canView($data))
			{
				continue;
			}			
			
			$url = XenForo_Link::buildPublicLink('canonical:members', $data);
			$this->addUrl($url, $data['register_date']);

			// We may have to break if we reached the limit of members to include in a single file
			$this->lastId = $data['user_id'];
			$totalMembers--;
			if ($totalMembers <= 0)
			{
				break;
			}
		}

		// if we still have data, that means that we did not finish fetching the information
		$this->isFinished = !$st->fetch();
		$st->closeCursor();
	}
	
	/**
	 * Return whether the member profile is public, we won't include in the sitemap profiles for members that have their
	 * privacy set to 'private'
	 * 
	 * @param array $data		The data for the members
	 */
	protected function canView($data)
	{
		return $data['allow_view_profile'] == 'everyone';
	}


}

