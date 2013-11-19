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
        // FIXME is it ok to change selectedModel/selectedResource and site helper stuff here?
        $store = OntoWiki::getInstance()->erfurt->getStore();
        $model = $store->getModel($workload->modelUri);
        OntoWiki::getInstance()->selectedModel = $model;
        OntoWiki::getInstance()->selectedResource = new OntoWiki_Resource($workload->resourceUri, $model);

        $siteHelper = OntoWiki::getInstance()->extensionManager->getComponentHelper('site');
        $siteHelper->setSite($workload->site);
        $siteHelper->setUrlBase($workload->urlBase);
        $cache = $siteHelper->makeCache($workload->uri);
        $this->logSuccess(sprintf('makePageCache: %d %s', $cache['code'], $workload->uri));
    }
}
