<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2013, {@link http://aksw.org AKSW}
 * @license   http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * OntoWiki module - cache viewer
 *
 * Displays status of cache for selected resource
 *
 * @category   OntoWiki
 * @package    Extensions_Site
 */
class CacheviewerModule extends OntoWiki_Module
{
    public function init()
    {
    }

    public function shouldShow()
    {
        return $this->_erfurt->getAc()->isActionAllowed('CacheManagement');
    }

    public function getTitle()
    {
        return 'Site Page Cache';
    }

    public function getContents()
    {
        $helper = $this->_owApp->extensionManager->getComponentHelper('site');

        $uri = $this->_owApp->selectedResource;
        if (!empty($uri) && $uri != (string)$this->_owApp->selectedModel) {
            $uri .= '.html';
        }
        $uri = preg_replace('~^http://~', '', $uri);

        $this->view->r = $this->_owApp->selectedResource;
        $this->view->uri = $uri;
        $this->view->resourceUri = (string)$this->_owApp->selectedResource;
        $this->view->test = $helper->testCache($uri);

        return $this->render('templates/cacheviewer');
    }
}
