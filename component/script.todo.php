<?php
/*
 * @package todo
 * @copyright Copyright (c)2012 Nicholas K. Dionysopoulos / AkeebaBackup.com
 * @license GNU General Public License version 3 or later
 */
defined('_JEXEC') or die();

jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.file');

class Com_TodoInstallerScript
{
	/** @var string The component's name */
	protected $_akeeba_extension = 'com_todo';
	
	/**
	 * Joomla! pre-flight event
	 * 
	 * @param string $type Installation type (install, update, discover_install)
	 * @param JInstaller $parent Parent object
	 */
	public function preflight($type, $parent)
	{
		// Bugfix for "Can not build admin menus"
		if(in_array($type, array('install','discover_install'))) {
			$this->_bugfixDBFunctionReturnedNoError();
		} else {
			$this->_bugfixCantBuildAdminMenus();
		}
		
		// Only allow to install on Joomla! 2.5.0 or later
		if(!version_compare(JVERSION, '2.5.0', 'ge')) {
			echo "<h1>This component is only compatible with Joomla! 2.5</h1>";
			return false;
		}
		
		return true;
	}
	
	/**
	 * Runs after install, update or discover_update
	 * @param string $type install, update or discover_update
	 * @param JInstaller $parent 
	 */
	function postflight( $type, $parent )
	{
		// Install FOF if required
		$fofStatus = $this->_installFOF($parent);
		
		// Kill update site
		$this->_killUpdateSite();
	}
	
	/**
	 * Joomla! 1.6+ bugfix for "DB function returned no error"
	 */
	private function _bugfixDBFunctionReturnedNoError()
	{
		$db = JFactory::getDbo();
			
		// Fix broken #__assets records
		$query = $db->getQuery(true);
		$query->select('id')
			->from('#__assets')
			->where($db->qn('name').' = '.$db->q($this->_akeeba_extension));
		$db->setQuery($query);
		$ids = $db->loadColumn();
		if(!empty($ids)) foreach($ids as $id) {
			$query = $db->getQuery(true);
			$query->delete('#__assets')
				->where($db->qn('id').' = '.$db->q($id));
			$db->setQuery($query);
			$db->query();
		}

		// Fix broken #__extensions records
		$query = $db->getQuery(true);
		$query->select('extension_id')
			->from('#__extensions')
			->where($db->qn('element').' = '.$db->q($this->_akeeba_extension));
		$db->setQuery($query);
		$ids = $db->loadColumn();
		if(!empty($ids)) foreach($ids as $id) {
			$query = $db->getQuery(true);
			$query->delete('#__extensions')
				->where($db->qn('extension_id').' = '.$db->q($id));
			$db->setQuery($query);
			$db->query();
		}

		// Fix broken #__menu records
		$query = $db->getQuery(true);
		$query->select('id')
			->from('#__menu')
			->where($db->qn('type').' = '.$db->q('component'))
			->where($db->qn('menutype').' = '.$db->q('main'))
			->where($db->qn('link').' LIKE '.$db->q('index.php?option='.$this->_akeeba_extension));
		$db->setQuery($query);
		$ids = $db->loadColumn();
		if(!empty($ids)) foreach($ids as $id) {
			$query = $db->getQuery(true);
			$query->delete('#__menu')
				->where($db->qn('id').' = '.$db->q($id));
			$db->setQuery($query);
			$db->query();
		}
	}
	
	/**
	 * Joomla! 1.6+ bugfix for "Can not build admin menus"
	 */
	private function _bugfixCantBuildAdminMenus()
	{
		$db = JFactory::getDbo();
		
		// If there are multiple #__extensions record, keep one of them
		$query = $db->getQuery(true);
		$query->select('extension_id')
			->from('#__extensions')
			->where($db->qn('element').' = '.$db->q($this->_akeeba_extension));
		$db->setQuery($query);
		$ids = $db->loadColumn();
		if(count($ids) > 1) {
			asort($ids);
			$extension_id = array_shift($ids); // Keep the oldest id
			
			foreach($ids as $id) {
				$query = $db->getQuery(true);
				$query->delete('#__extensions')
					->where($db->qn('extension_id').' = '.$db->q($id));
				$db->setQuery($query);
				$db->query();
			}
		}
		
		// If there are multiple assets records, delete all except the oldest one
		$query = $db->getQuery(true);
		$query->select('id')
			->from('#__assets')
			->where($db->qn('name').' = '.$db->q($this->_akeeba_extension));
		$db->setQuery($query);
		$ids = $db->loadObjectList();
		if(count($ids) > 1) {
			asort($ids);
			$asset_id = array_shift($ids); // Keep the oldest id
			
			foreach($ids as $id) {
				$query = $db->getQuery(true);
				$query->delete('#__assets')
					->where($db->qn('id').' = '.$db->q($id));
				$db->setQuery($query);
				$db->query();
			}
		}

		// Remove #__menu records for good measure!
		$query = $db->getQuery(true);
		$query->select('id')
			->from('#__menu')
			->where($db->qn('type').' = '.$db->q('component'))
			->where($db->qn('menutype').' = '.$db->q('main'))
			->where($db->qn('link').' LIKE '.$db->q('index.php?option='.$this->_akeeba_extension));
		$db->setQuery($query);
		$ids1 = $db->loadColumn();
		if(empty($ids1)) $ids1 = array();
		$query = $db->getQuery(true);
		$query->select('id')
			->from('#__menu')
			->where($db->qn('type').' = '.$db->q('component'))
			->where($db->qn('menutype').' = '.$db->q('main'))
			->where($db->qn('link').' LIKE '.$db->q('index.php?option='.$this->_akeeba_extension.'&%'));
		$db->setQuery($query);
		$ids2 = $db->loadColumn();
		if(empty($ids2)) $ids2 = array();
		$ids = array_merge($ids1, $ids2);
		if(!empty($ids)) foreach($ids as $id) {
			$query = $db->getQuery(true);
			$query->delete('#__menu')
				->where($db->qn('id').' = '.$db->q($id));
			$db->setQuery($query);
			$db->query();
		}
	}


	/**
	 * Installs the FOF framework only if the currently installed version on the
	 * user's site is out of date or if there is no version already installed.
	 * 
	 * @param JInstaller $parent
	 * @return array
	 */
	private function _installFOF($parent)
	{
		$src = $parent->getParent()->getPath('source');
		
		// Install the FOF framework
		jimport('joomla.filesystem.folder');
		jimport('joomla.filesystem.file');
		jimport('joomla.utilities.date');
		$source = $src.'/fof';
		if(!defined('JPATH_LIBRARIES')) {
			$target = JPATH_ROOT.'/libraries/fof';
		} else {
			$target = JPATH_LIBRARIES.'/fof';
		}
		$haveToInstallFOF = false;
		if(!JFolder::exists($target)) {
			$haveToInstallFOF = true;
		} else {
			$fofVersion = array();
			if(JFile::exists($target.'/version.txt')) {
				$rawData = JFile::read($target.'/version.txt');
				$info = explode("\n", $rawData);
				$fofVersion['installed'] = array(
					'version'	=> trim($info[0]),
					'date'		=> new JDate(trim($info[1]))
				);
			} else {
				$fofVersion['installed'] = array(
					'version'	=> '0.0',
					'date'		=> new JDate('2011-01-01')
				);
			}
			$rawData = JFile::read($source.'/version.txt');
			$info = explode("\n", $rawData);
			$fofVersion['package'] = array(
				'version'	=> trim($info[0]),
				'date'		=> new JDate(trim($info[1]))
			);

			$haveToInstallFOF = $fofVersion['package']['date']->toUNIX() > $fofVersion['installed']['date']->toUNIX();
		}

		$installedFOF = false;
		if($haveToInstallFOF) {
			$versionSource = 'package';
			$installer = new JInstaller;
			$installedFOF = $installer->install($source);
		} else {
			$versionSource = 'installed';
		}
		
		if(!isset($fofVersion)) {
			$fofVersion = array();
			if(JFile::exists($target.'/version.txt')) {
				$rawData = JFile::read($target.'/version.txt');
				$info = explode("\n", $rawData);
				$fofVersion['installed'] = array(
					'version'	=> trim($info[0]),
					'date'		=> new JDate(trim($info[1]))
				);
			} else {
				$fofVersion['installed'] = array(
					'version'	=> '0.0',
					'date'		=> new JDate('2011-01-01')
				);
			}
			$rawData = JFile::read($source.'/version.txt');
			$info = explode("\n", $rawData);
			$fofVersion['package'] = array(
				'version'	=> trim($info[0]),
				'date'		=> new JDate(trim($info[1]))
			);
			$versionSource = 'installed';
		}
		
		if(!($fofVersion[$versionSource]['date'] instanceof JDate)) {
			$fofVersion[$versionSource]['date'] = new JDate();
		}
		
		return array(
			'required'	=> $haveToInstallFOF,
			'installed'	=> $installedFOF,
			'version'	=> $fofVersion[$versionSource]['version'],
			'date'		=> $fofVersion[$versionSource]['date']->format('%Y-%m-%d'),
		);
	}
	
	
	/**
	 * Remove the update site specification from Joomla! – we no longer support
	 * that misbehaving crap, thank you very much...
	 */
	private function _killUpdateSite()
	{
		// Get some info on all the stuff we've gotta delete
		$db = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select(array(
				$db->qn('s').'.'.$db->qn('update_site_id'),
				$db->qn('e').'.'.$db->qn('extension_id'),
				$db->qn('e').'.'.$db->qn('element'),
				$db->qn('s').'.'.$db->qn('location'),
			))
			->from($db->qn('#__update_sites').' AS '.$db->qn('s'))
			->join('INNER',$db->qn('#__update_sites_extensions').' AS '.$db->qn('se').' ON('.
				$db->qn('se').'.'.$db->qn('update_site_id').' = '.
				$db->qn('s').'.'.$db->qn('update_site_id')
				.')')
			->join('INNER',$db->qn('#__extensions').' AS '.$db->qn('e').' ON('.
				$db->qn('e').'.'.$db->qn('extension_id').' = '.
				$db->qn('se').'.'.$db->qn('extension_id')
				.')')
			->where($db->qn('s').'.'.$db->qn('type').' = '.$db->q('extension'))
			->where($db->qn('e').'.'.$db->qn('type').' = '.$db->q('component'))
			->where($db->qn('e').'.'.$db->qn('element').' = '.$db->q($this->_akeeba_extension))
		;
		$db->setQuery($query);
		$oResult = $db->loadObject();
		
		// If no record is found, do nothing. We've already killed the monster!
		if(is_null($oResult)) return;
		
		// Delete the #__update_sites record
		$query = $db->getQuery(true)
			->delete($db->qn('#__update_sites'))
			->where($db->qn('update_site_id').' = '.$db->q($oResult->update_site_id));
		$db->setQuery($query);
		try {
			$db->query();
		} catch (Exception $exc) {
			// If the query fails, don't sweat about it
		}

		// Delete the #__update_sites_extensions record
		$query = $db->getQuery(true)
			->delete($db->qn('#__update_sites_extensions'))
			->where($db->qn('update_site_id').' = '.$db->q($oResult->update_site_id));
		$db->setQuery($query);
		try {
			$db->query();
		} catch (Exception $exc) {
			// If the query fails, don't sweat about it
		}
		
		// Delete the #__updates records
		$query = $db->getQuery(true)
			->delete($db->qn('#__updates'))
			->where($db->qn('update_site_id').' = '.$db->q($oResult->update_site_id));
		$db->setQuery($query);
		try {
			$db->query();
		} catch (Exception $exc) {
			// If the query fails, don't sweat about it
		}
	}
}