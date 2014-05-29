<?php
/**
 * @copyright Copyright (c) 2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * Cache generation job
 */

class Site_Job_ExportPage extends Erfurt_Worker_Job_Abstract
{

    protected $urlBase  = "";
    protected $uri      = "";

    protected function callbackRelativeLink( $match ){
        $path   = Erfurt_Uri::getPathTo( $this->uri, $match[3] );
        return $match[1].$match[2].$path.$match[4];
    }

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

        if (!$helper->testCache($uri)){
            // FIXME maybe we shouldn't always regenerate there, and just use cached version if available
            $cache = $helper->makeCache($uri);
        }
        $cache  = $helper->loadCache($uri);        

        $this->urlBase  = $workload->urlBase;
        $this->uri      = $workload->resourceUri;
        $pattern        = "/(href=|src=)(\"|')(".str_replace("/", "\/", $workload->urlBase ).".+)(\"|')/U";
        $cache['body']  = preg_replace_callback($pattern, array($this, 'callbackRelativeLink'), $cache['body']);
        $pattern        = "/()(')(".str_replace("/", "\/", $workload->urlBase ).".+)(')/U";
        $cache['body']  = preg_replace_callback($pattern, array($this, 'callbackRelativeLink'), $cache['body']);

        if (!is_dir($workload->targetPath)) {
            mkdir($workload->targetPath, 0755, TRUE);
        }

        $parts      = explode("/", $workload->resourceUri);
        $fileName   = array_pop($parts);

        file_put_contents($workload->targetPath.$fileName.'.html', $cache['body'] );

        $this->logSuccess(sprintf('%s %d %s', $workload->msg, $cache['code'], $uri));
    }
}
