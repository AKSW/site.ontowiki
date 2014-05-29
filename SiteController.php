<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * The main controller class for the site component. This class
 * provides an action to render a given resource
 *
 * @category   OntoWiki
 * @package    OntoWiki_extensions_components_site
 */
class SiteController extends OntoWiki_Controller_Component
{
    /**
     * The selected model or the model which is given
     * by the m parameter
     */
    private $_model = null;

    /**
     * The site id which is part of the request URI as well as the template structure
     *
     * @var string|null
     */
    private $_site = null;

    public function deletecacheAction()
    {
        if (!$this->_erfurt->getAc()->isActionAllowed('CacheManagement')) {
            throw new Erfurt_Ac_Exception("Action 'CacheManagement' not allowed.");
        }

        $this->getComponentHelper()->removeCache($this->_request->getPost('uri'));
        $redirect = new OntoWiki_Url(array('controller' => 'resource', 'action' => 'properties'), array());
        $redirect->r = $this->_request->getPost('r');
        $this->_redirect($redirect);
    }

    public function exportAllResources($targetPath, $model)
    {
        $ontowiki   = OntoWiki::getInstance();
        $ontowiki->callJob("exportSite", array(
            'urlBase'       => $model,
            'targetPath'    => $targetPath,
            'msg'           => '',
        ));
    }

    public function exportResource($targetPath, $model, $resourceUri)
    {
        $ontowiki   = OntoWiki::getInstance();
        $ontowiki->callJob("exportPage", array(
            'targetPath'    => $targetPath,
            'urlBase'       => $model,
            'resourceUri'   => $resourceUri,
            'msg'           => '',
        ));
    }

    public function init()
    {
        parent::init();
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout()->disableLayout();
    }

    public function makesitecacheAction()
    {
        if (!$this->_erfurt->getAc()->isActionAllowed('CacheManagement')) {
            throw new Erfurt_Ac_Exception("Action 'CacheManagement' not allowed.");
        }

        $helper = $this->getComponentHelper();
        $siteConfig = $helper->getSiteConfig();

        OntoWiki::getInstance()->callJob('makeSiteCache', array(
            'urlBase' => $helper->getUrlBase(),
        ));

        $redirect = new OntoWiki_Url(array('controller' => 'resource', 'action' => 'properties'), array());
        $redirect->r = $this->_request->getPost('r');
        $this->_redirect($redirect);
    }

    public function regeneratecacheAction()
    {
        if (!$this->_erfurt->getAc()->isActionAllowed('CacheManagement')) {
            throw new Erfurt_Ac_Exception("Action 'CacheManagement' not allowed.");
        }

        $helper = $this->getComponentHelper();
        $this->_model = $helper->loadModel();

        OntoWiki::getInstance()->callJob('makePageCache', array(
            'resourceUri' => $this->_request->getPost('resourceUri'),
            'urlBase' => $helper->getUrlBase(),
            'msg' => '(requested in OntoWiki)',
        ));

        $redirect = new OntoWiki_Url(array('controller' => 'resource', 'action' => 'properties'), array());
        $redirect->r = $this->_request->getPost('r');
        $this->_redirect($redirect);
    }

    /**
     *  Prints a simple robots.txt containing nothing but a sitemap rule.
     *  @access     public
     *  @return     void
     *  @todo       Create zend route from ./robots.txt to site/robots
     *  @todo       Change URL to .../sitemap.xml after zend route has been created
     */
    public function robotsAction()
    {
        header("Content-Type: text/plain");
        print("Sitemap: ".$this->_config->urlBase."site/sitemap");
        exit;
    }

    /**
     *  Renders and prints sitemap XML.
     *  For gzip compression add paramter "compression" with compression method "bzip" or "gzip" as value.
     *  Appending a name paramter with a file name will name your download file if you request via browser.
     *  @access     public
     *  @return     void
     *  @todo       Create zend route from ./sitemap.xml to site/sitemap
     *  @todo       Create zend route from ./sitemap.xml.gz to site/sitemap/compression/gzip/name/sitemap.xml.gz
     *  @todo       Create zend route from ./sitemap.xml.bz2 to site/sitemap/compression/bzip/name/sitemap.xml.bz2
     *  @todo       add support for sitemap index
     */
    public function sitemapAction()
    {
        $compression    = $this->getParam( 'compression' );
        $siteConfig     = $this->getComponentHelper()->getSiteConfig();

        $pathGenerator	= __DIR__.'/libraries/SitemapGenerator/classes/';
        require_once ($pathGenerator.'Sitemap.php');
        require_once ($pathGenerator.'Sitemap/URL.php');
        require_once ($pathGenerator.'XML/Builder.php');
        require_once ($pathGenerator.'XML/Node.php');

        // Here we start the object cache id
        $sitemapObjectCacheIdSource = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $sitemapObjectCacheId = 'sitemap_' . md5($sitemapObjectCacheIdSource);

        // try to load the cached value
        $erfurtObjectCache  = OntoWiki::getInstance()->erfurt->getCache();
        $erfurtQueryCache   = OntoWiki::getInstance()->erfurt->getQueryCache();
        $sitemapXml         = $erfurtObjectCache->load($sitemapObjectCacheId);
        if ($sitemapXml === false) {
            $erfurtQueryCache->startTransaction($sitemapObjectCacheId);
            $extension  = "";
            if (!empty($siteConfig['sitemap_url_ext'])) {
                $extension  = $siteConfig['sitemap_url_ext'];
            }
            $results    = $this->getComponentHelper()->getAllSitemapUris();
            $sitemap    = new Sitemap();
            foreach ($results as $result) {
                $url    = new Sitemap_URL ($result['resourceUri'].$extension);
                if (isset($result['modified']) && strlen($result['modified']))
                    $url->setDatetime ($result['modified']);
                $sitemap->addUrl ($url);
            }
            $sitemapXml	= $sitemap->render();
            // save the page body as an object value for the object cache
            $erfurtObjectCache->save($sitemapXml, $sitemapObjectCacheId);
            // close the object cache transaction
            $erfurtQueryCache->endTransaction($sitemapObjectCacheId);
        }
        $contentType    = "application/xml";
        // compression has been requested
        if(strlen(trim($compression))){
            switch(strtolower($compression)){
                case 'bzip':
                    $sitemapXml     = bzcompress($sitemapXml);
                    $contentType    = "application/x-bzip";
                    header('Content-Encoding: bzip2');
                    break;
                case 'gzip':
                    $sitemapXml     = gzencode($sitemapXml);
                    $contentType    = "application/x-gzip";
                    header('Content-Encoding: gzip');
                    break;
            }
        }
        header("Content-Length: ".strlen($sitemapXml));
        header("Content-Type: ".$contentType);
        print($sitemapXml);
        exit;
    }

    /*
     * to allow multiple template sets, every action is mapped to a template directory
     */
    public function __call($method, $args)
    {
        $action = $this->_request->getActionName();
        $router = $this->_owApp->getBootstrap()->getResource('Router');

        if ($router->hasRoute('empty')) {
            $emptyRoute    = $router->getRoute('empty');
            $defaults      = $emptyRoute->getDefaults();
            $defaultAction = $defaults['action'];
        }

        if (empty($action) || (isset($defaultAction) && $action === $defaultAction) || $action === 'index') {
            // use default site for empty or default action (index)
            $this->_site = $this->_privateConfig->defaultSite;
        } else {
            // use action as site otherwise
            $this->_site  = $action;
        }

        $this->getComponentHelper()->setSite($this->_site);

        $cache = $this->getComponentHelper()->loadCache();
        if (!$cache) {
            $cache = $this->getComponentHelper()->makeCache();
        }

        // set the page content
        $this->_response->setHttpResponseCode($cache['code']);
        foreach ($cache['headers'] as $header => $content) {
            $this->_response->setHeader($header, $content);
        }
        $this->_response->setBody($cache['body']);
    }

}
