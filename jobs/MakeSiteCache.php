<?php
/**
 * @copyright Copyright (c) 2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * Cache generation job
 */

class Site_Job_MakeSiteCache extends Erfurt_Worker_Job_Abstract
{
    public function run($workload)
    {
        $helper = OntoWiki::getInstance()->extensionManager->getComponentHelper('site');
        $siteConfig = $helper->getSiteConfig();

        // FIXME is it ok to change selectedModel/selectedResource and site helper stuff here?
        $store = OntoWiki::getInstance()->erfurt->getStore();
        $model = $store->getModel($siteConfig['model']);
        OntoWiki::getInstance()->selectedModel = $model;

        $helper->setUrlBase($workload->urlBase);

        $uris = $helper->getAllURIs();
        $count = count($uris);
        $i = 0;
        foreach ($uris as $uri) {
            $i++;
            OntoWiki::getInstance()->callJob('makePageCache', array(
                'resourceUri'   => $uri,
                'urlBase'       => $helper->getUrlBase(),
                'msg'           => sprintf('(%d/%d)', $i, $count),
            ));
        }

        $this->logSuccess(sprintf('%s resources', $count));
    }
}
