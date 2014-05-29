<?php
/**
 * @copyright Copyright (c) 2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * Cache generation job
 */

class Site_Job_MakePageCache extends Erfurt_Worker_Job_Abstract
{
    public function run($workload)
    {
        $helper = OntoWiki::getInstance()->extensionManager->getComponentHelper('site');
        $siteConfig = $helper->getSiteConfig();

        // FIXME is it ok to change selectedModel/selectedResource and site helper stuff here?
        $store = OntoWiki::getInstance()->erfurt->getStore();
        $model = $store->getModel($siteConfig['model']);
        OntoWiki::getInstance()->selectedModel = $model;
        OntoWiki::getInstance()->selectedResource = new OntoWiki_Resource($workload->resourceUri, $model);

        $helper->setSite($siteConfig['id']);
        $helper->setUrlBase($workload->urlBase);

        // FIXME, actual uri logic is in onShouldLinkedDataRedirect & onBuildUrl, needs refactoring to be accessible
        $uri = preg_replace('~^https?://(.*)$~', '$1.html', $workload->resourceUri);

        // FIXME maybe we shouldn't always regenerate there, and just use cached version if available
        $cache = $helper->makeCache($uri);

        $this->logSuccess(sprintf('%s %d %s', $workload->msg, $cache['code'], $uri));
    }
}
