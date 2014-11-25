<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

require_once 'MarkupInterface.php';

/**
 * A helper class for the site component.
 *
 * @category   OntoWiki
 * @package    OntoWiki_extensions_components_site
 */
class SiteHelper extends OntoWiki_Component_Helper
{
    /**
     * Name of per-site config file
     */
    const SITE_CONFIG_FILENAME = 'config.ini';

    /**
     * The main template filename
     */
    const MAIN_TEMPLATE_NAME = 'layout.phtml';

    /**
     * The model URI of the selected model or the uri which is given
     * by the m parameter
     *
     * @var string|null
     */
    private $_modelUri = null;

    /**
     * The selected model or the model which is given
     * by the m parameter
     */
    private $_model = null;

    /**
     * The resource URI of the requested resource or the uri which is given
     * by the r parameter
     *
     * @var string|null
     */
    private $_resourceUri = null;

    /**
     * relative Path to the extension template folder
     */
    private $_relativeTemplatePath = 'templates';

    /**
     * Current site (if in use)
     * @var string|null
     */
    protected $_site = null;

    /**
     * Site config for the current site.
     * @var array
     */
    protected $_siteConfig = null;

    /**
     * Current pseudo file extension.
     * @var string
     */
    protected $_currentSuffix = '';

    /*
     * used schema URIs
     */
    const TEMPLATE_PROP_CLASS       = 'http://ns.ontowiki.net/SysOnt/Site/classTemplate';
    const TEMPLATE_PROP_RESOURCE    = 'http://ns.ontowiki.net/SysOnt/Site/template';
    const TYPE_PROP                 = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type';
    const SUBCLASS_PROP             = 'http://www.w3.org/2000/01/rdf-schema#subClassOf';

    public function init()
    {
        $this->_relativeTemplatePath = $this->_owApp->extensionManager->getExtensionConfig('site')->templates;
    }

    public function onAnnounceWorker($event)
    {
        $event->registry->registerJob(
            'makePageCache',
            'extensions/site/jobs/MakePageCache.php',
            'Site_Job_MakePageCache'
        );
        $event->registry->registerJob(
            'makeSiteCache',
            'extensions/site/jobs/MakeSiteCache.php',
            'Site_Job_MakeSiteCache'
        );
        $event->registry->registerJob(
            'exportPage',
            'extensions/site/jobs/ExportPage.php',
            'Site_Job_ExportPage'
        );
        $event->registry->registerJob(
            'exportSite',
            'extensions/site/jobs/ExportSite.php',
            'Site_Job_ExportSite'
        );
    }

    public function onPostBootstrap($event)
    {
        $router     = $event->bootstrap->getResource('Router');
        $request    = Zend_Controller_Front::getInstance()->getRequest();
        $controller = $request->getControllerName();
        $action     = $request->getActionName();

        if ($router->hasRoute('empty')) {
            $emptyRoute = $router->getRoute('empty');
            $defaults   = $emptyRoute->getDefaults();

            $defaultController = $defaults['controller'];
            $defaultAction     = $defaults['action'];

            // are we currently following the empty route?
            if ($controller === $defaultController && $action === $defaultAction) {
                /* TODO: this should not be the default site but the site which
                   matches the model of the selected resource */
                $siteConfig = $this->getSiteConfig();

                if (isset($siteConfig['index'])) {
                    // TODO: detect accept header
                    $indexResource = $siteConfig['index'] . $this->getCurrentSuffix();
                    $requestUri    = $this->_config->urlBase
                                   . ltrim($request->getRequestUri(), '/');

                    // redirect if request URI does not match index resource
                    if ($requestUri !== $indexResource) {
                        // response not ready yet, do it the PHP way
                        header('Location: ' . $indexResource, true, 303);
                        exit;
                    }
                }
            }

            $emptyRoute = new Zend_Controller_Router_Route(
                '',
                array(
                    'controller' => 'site',
                    'action'     => $this->_privateConfig->defaultSite
                )
            );
            $router->addRoute('empty', $emptyRoute);
        }
    }

    public function onCreateMenu($event) {
        $request    = Zend_Controller_Front::getInstance()->getRequest();
        $controller = $request->getControllerName();
        $action     = $request->getActionName();

        if ($controller === 'resource' && $action === 'properties') {
            $site = $this->getSiteConfig();
            $resourceUri = $this->_owApp->selectedResource;
            if (!empty($resourceUri) && $resourceUri != (string)$this->_owApp->selectedModel) {
                if (strpos($resourceUri, $site['model']) === 0) {
                    $resourceUri .= '.html';
                } else {
                    $resourceUri = new OntoWiki_Url(array('controller' => 'site', 'action' => $site['id']), array('r'));
                    $resourceUri = (string)$resourceUri; // build URL while site is set
                }
            }

            $toolbar = OntoWiki_Toolbar::getInstance();
            $toolbar->prependButton(OntoWiki_Toolbar::SEPARATOR)
                    ->prependButton(
                        OntoWiki_Toolbar::SUBMIT,
                        array(
                            'name' => 'Back to Site',
                            'url' => $resourceUri
                        )
                    );
        }
    }

    public function onShouldLinkedDataRedirect($event)
    {
        if ($event->type) {
            // Supported type?
            $requestUri = $event->request->getServer('REQUEST_URI');
            $requestUriPath = parse_url($requestUri, PHP_URL_PATH);
            $parts = explode('.', $requestUriPath);
            if ($parts[count($parts)-1] != $event->type) {
                header('Location: ' . $event->uri . '.' . $event->type, true, 302);
                exit;
            }
        } else {
            return false;
        }

        if ($event->type === 'html') {
            $event->request->setControllerName('site');
            $event->request->setActionName($this->_privateConfig->defaultSite);

            if ($event->flag) {
                $this->_currentSuffix = '.html';
            }
        } else {
            // export
            $event->request->setControllerName('resource');
            $event->request->setActionName('export');
            $event->request->setParam('f', $event->type);
            $event->request->setParam('r', $event->uri);
        }

        $event->request->setDispatched(false);
        return false;
    }

    public function getSiteConfig()
    {
        if (null === $this->_siteConfig) {
            $this->_siteConfig = array();
            $site = $this->_privateConfig->defaultSite;
            $typesfolder = $this->_privateConfig->subfolderTypes;
            $relativeTemplatePath = OntoWiki::getInstance()->extensionManager->getExtensionConfig('site')->templates;
            // load the site config
            $configFilePath = sprintf('%s/%s/%s/%s', $this->getComponentRoot(), $relativeTemplatePath, $site, self::SITE_CONFIG_FILENAME);
            if (is_readable($configFilePath)) {
                if ($config = parse_ini_file($configFilePath, true)) {
                    $this->_siteConfig = $config;
                }
            }

            // add the site id to the config in order to allow correct URIs
            $this->_siteConfig['id'] = $site;
            
            // subfolder for resource type templates
            if (!isset($this->_siteConfig['subfolderTypes'])) {
                $this->_siteConfig['subfolderTypes'] = $typesfolder;
            }
        }

        return $this->_siteConfig;
    }

    public function getCurrentSuffix()
    {
        return $this->_currentSuffix;
    }

    public static function skosNavigationAsArray($titleHelper)
    {
        $store = OntoWiki::getInstance()->erfurt->getStore();
        $model = OntoWiki::getInstance()->selectedModel;

        $query = '
            PREFIX skos: <http://www.w3.org/2004/02/skos/core#>
            PREFIX sysont: <http://ns.ontowiki.net/SysOnt/>
            SELECT ?topConcept ?altLabel
            FROM <' . (string)$model . '>
            WHERE {
                ?cs a skos:ConceptScheme .
                ?topConcept skos:topConceptOf ?cs
                OPTIONAL {
                    ?topConcept sysont:order ?order
                }
                OPTIONAL {
                    ?topConcept skos:altLabel ?altLabel
                }
            }
            ORDER BY ASC(?order)
            ';

        if ($result = $store->sparqlQuery($query)) {
            $tree = array();
            foreach ($result as $row) {
                $topConcept = new stdClass;
                $topConcept->uri = $row['topConcept'];

                $titleHelper->addResource($topConcept->uri);

                if ($row['altLabel'] != null) {
                    $topConcept->altLabel = $row['altLabel'];
                }

                $subQuery = '
                    PREFIX skos: <http://www.w3.org/2004/02/skos/core#>
                    PREFIX sysont: <http://ns.ontowiki.net/SysOnt/>
                    SELECT ?subConcept ?altLabel
                    FROM <' . (string)$model . '>
                    WHERE {
                        ?subConcept skos:broader <' . $topConcept->uri . '>
                        OPTIONAL {
                            ?subConcept sysont:order ?order
                        }
                        OPTIONAL {
                            ?subConcept skos:altLabel ?altLabel
                        }
                    }
                    ORDER BY ASC(?order)
                    ';
                if ($subConceptsResult = $store->sparqlQuery($subQuery)) {
                    $subconcepts = array();
                    foreach ($subConceptsResult as $subConceptRow) {
                        $subConcept = new stdClass;
                        $subConcept->uri = $subConceptRow['subConcept'];
                        $subConcept->subconcepts = array();

                        if ($subConceptRow['altLabel'] != null) {
                            $subConcept->altLabel = $subConceptRow['altLabel'];
                        }

                        $titleHelper->addResource($subConcept->uri);
                        $subconcepts[$subConcept->uri] = $subConcept;
                    }
                    $topConcept->subconcepts = $subconcepts;
                } else {
                    $topConcept->subconcepts = array();
                }

                $tree[$topConcept->uri] = $topConcept;
            }

            return $tree;
        }

        return array();
    }

    public function setSite($site)
    {
        $this->_site = (string)$site;
    }

    public function getAllURIs()
    {
        $this->loadModel();
        $store = OntoWiki::getInstance()->erfurt->getStore();

        // get all classes with template set in some way
        $classes = $this->_model->sparqlQuery('
            SELECT DISTINCT ?resourceUri
            FROM <http://starpages.dev.eccenca.com/>
            WHERE {
                ?resourceUri <' . self::TEMPLATE_PROP_CLASS . '> ?template.
            }
        ');
        $classes = array_map(function($_) { return $_['resourceUri']; }, $classes);

        $closure = $store->getTransitiveClosure($this->_model, self::SUBCLASS_PROP, $classes, true);
        $classes = array_keys($closure);

        // get all resources with templates set directly or with class
        $query  = '
            SELECT DISTINCT ?resourceUri
            FROM <http://starpages.dev.eccenca.com/>
            WHERE {
                {
                    ?resourceUri <' . self::TEMPLATE_PROP_RESOURCE . '> ?template.
                }
                UNION
                {
                    ?resourceUri <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> ?class.
                    FILTER (
                        ?class IN (' . implode(',', array_map(function($_) { return "<$_>"; }, $classes)) . ')
                    )
                }
            }
        ';
        $URIs   = $this->_model->sparqlQuery($query);
        $URIs = array_map(function($_) { return $_['resourceUri']; }, $URIs);
        return $URIs;
    }

    public function getAllSitemapUris(){
        $siteConfig = $this->getSiteConfig();

        // determine resource types
        $types  = array('?type');
        if (!empty($siteConfig['sitemap_types'])) {
            $types  = explode(',', $siteConfig['sitemap_types']);
        }

        foreach ($types as $nr => $type) {
            $types[$nr]    = '{?resourceUri <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> '.$type.'}';
        }

        $query	= '
SELECT DISTINCT ?resourceUri ?modified
FROM <http://schema.org/>
WHERE {
    '.join(' UNION ', $types).' .
    FILTER strStarts(str(?resourceUri), "'.$siteConfig['model'].'") .
    OPTIONAL {
        ?resourceUri <http://purl.org/dc/terms/modified> ?modified .
    }
}
ORDER BY DESC(?modified)';
        $this->loadModel();
        return $this->_model->sparqlQuery($query);
    }
    
    public function getPage($uri = null)
    {
        $this->loadModel();
        $this->_loadResource();

        // prepare view
        $moduleTemplatePath = $this->_componentRoot
                            . $this->_relativeTemplatePath
                            . DIRECTORY_SEPARATOR
                            . $this->_privateConfig->defaultSite
                            . DIRECTORY_SEPARATOR
                            . 'modules';

        $config = OntoWiki::getInstance()->config;

        // TODO merge with Bootstrap::_initView
        $defaultTemplatePath = ONTOWIKI_ROOT
                             . 'application/views/templates';

        $themeTemplatePath   = ONTOWIKI_ROOT
                             . $config->themes->path
                             . $config->themes->default
                             . 'templates';

        $viewOptions = array(
            'use_module_cache' => (bool)$config->cache->modules,
            'cache_path'        => $config->cache->path,
            'lang'              => $config->languages->locale,
        );

        $translate = $GLOBALS['application']->getBootstrap()->getResource('Translate');

        $view = new OntoWiki_View($viewOptions, $translate);
        $view->addScriptPath($defaultTemplatePath)  // default templates
            ->addScriptPath($themeTemplatePath)    // theme templates override default ones
            ->addScriptPath($config->extensions->base)    // extension templates
            ->setEncoding($config->encoding)
            ->setHelperPath(ONTOWIKI_ROOT . 'application/classes/OntoWiki/View/Helper', 'OntoWiki_View_Helper');

        $view->strictVars(defined('_OWDEBUG'));

        // TODO merge with Controller::Base
        $view->themeUrlBase   = $config->themeUrlBase;
        $view->urlBase        = $config->urlBase;
        $view->staticUrlBase  = $config->staticUrlBase;
        $view->libraryUrlBase = $config->staticUrlBase . 'libraries/';

        $cache = array(
            'code'    => 200,
            'headers' => array('Content-Type' => 'text/html; encoding=utf-8'),
        );

        $templatePath = $this->_owApp->extensionManager->getComponentTemplatePath('site');
        $mainTemplate = sprintf('%s/%s', $this->_site, static::MAIN_TEMPLATE_NAME);

        // TODO merge with Controller::Component
        $cm   = $this->_owApp->extensionManager;
        $name = 'site';

        // set component specific template path
        if ($tp = $cm->getComponentTemplatePath($name)) {
            $view->addScriptPath($tp);
        }

        // set component specific helper path
        if ($hp = $cm->getComponentHelperPath($name)) {
            $view->addHelperPath($hp, ucfirst($name) . '_View_Helper_');
        }

        // set component root dir
        $this->_componentRoot = $cm->getExtensionPath()
            . $name
            . '/';

        // set component root url
        $this->_componentUrlBase = $this->_config->staticUrlBase
            . $this->_config->extensions->base
            . $name
            . '/';

        if (!is_readable($templatePath . $mainTemplate)) {
            $cache['code'] = 404;
            $cache['body'] = $view->render('404.phtml');
            return $cache;
        }

        $description = $this->_resource->getDescription();
        if (!empty($description[$this->_resourceUri][EF_RDF_TYPE])) {
            $type = $description[$this->_resourceUri][EF_RDF_TYPE][0]['value'];
            if ($type === 'http://ns.ontowiki.net/SysOnt/Site/MovedResource') {
                if (!empty($description[$this->_resourceUri]['http://ns.ontowiki.net/SysOnt/Site/seeAlso'])) {
                    $cache['code'] = 303;
                    $cache['headers']['Location'] = $description[$this->_resourceUri]['http://ns.ontowiki.net/SysOnt/Site/seeAlso'][0]['value'];
                } else {
                    // FIXME
                    $cache['code'] = 500;
                }
                // TODO use different template?
            }
        }

        // add module template override path
        if (is_readable($moduleTemplatePath)) {
            $scriptPaths = $view->getScriptPaths();
            array_push($scriptPaths, $moduleTemplatePath);
            $view->setScriptPath($scriptPaths);
        }

        // with assignment, direct access is possible ($this->basePath).
        $view->assign($this->_getTemplateData($view));
        // this allows for easy re-assignment of everything
        $view->templateData = $this->_getTemplateData($view);

        // generate the page body
        $cache['body'] = $view->render($mainTemplate);

        return $cache;
    }

    protected function _cacheId($uri)
    {
        if ($uri === null) {
            $uri = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . $this->_config->languages->locale . var_export($_POST, true);
        }
        return 'site_' . md5($uri);
    }

    public function testCache($uri = null)
    {
        $id = $this->_cacheId($uri);

        $erfurtObjectCache = OntoWiki::getInstance()->erfurt->getCache();
        return $erfurtObjectCache->test($id);
    }

    public function removeCache($uri = null)
    {
        $id = $this->_cacheId($uri);

        $erfurtObjectCache = OntoWiki::getInstance()->erfurt->getCache();
        return $erfurtObjectCache->remove($id);
    }

    public function makeCache($uri = null)
    {
        $id = $this->_cacheId($uri);

        $erfurtObjectCache = OntoWiki::getInstance()->erfurt->getCache();
        $erfurtQueryCache  = OntoWiki::getInstance()->erfurt->getQueryCache();

        $erfurtQueryCache->startTransaction($id);
        $erfurtObjectCache->save($cache = $this->getPage($uri), $id);
        $erfurtQueryCache->endTransaction($id);

        return $cache;
    }

    public function loadCache($uri = null)
    {
        $id = $this->_cacheId($uri);

        $erfurtObjectCache = OntoWiki::getInstance()->erfurt->getCache();
        return $erfurtObjectCache->load($id);
    }

    public function setUrlBase($url)
    {
        $this->_urlBase = $url;
    }

    public function getUrlBase()
    {
        if (!isset($this->_urlBase)) {
            return $this->_owApp->getUrlBase();
        }

        return $this->_urlBase;
    }

    private function _getTemplateData($view)
    {
        $namespaces = $this->_model->getNamespacePrefixes();
        // add presets for rdf, rdfs and owl
        $namespaces['rdf'] = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
        $namespaces['rdfs'] = 'http://www.w3.org/2000/01/rdf-schema#';
        $namespaces['owl'] = 'http://www.w3.org/2002/07/owl#';

        // this template data is given to ALL templates (with renderx)
        $templateData           = array(
            'siteId'            => $this->_site,
            'systemConfig'      => $this->_config,
            'siteConfig'        => $this->getSiteConfig(),
            'generator'         => $this->_config->version->label . ' ' . $this->_config->version->number,
            'pingbackUrl'       => $this->getUrlBase() . 'pingback/ping',
            'wikiBaseUrl'       => $this->getUrlBase(),
            'themeUrlBase'      => $view->themeUrlBase,
            'libraryUrlBase'    => $view->libraryUrlBase,
            'basePath'          => sprintf('%s%s/%s', $this->_componentRoot, $this->_relativeTemplatePath, $this->_site),
            'baseUri'           => sprintf('%s%s/%s', $this->_componentUrlBase, $this->_relativeTemplatePath, $this->_site),
            'context'           => 'site.' . $this->_site,
            'namespaces'        => $namespaces,
            'model'             => $this->_model,
            'modelUri'          => $this->_modelUri,
            'title'             => $this->_resource->getTitle(),
            'resourceUri'       => (string) $this->_resourceUri,
            'description'       => $this->_resource->getDescription(),
            'descriptionHelper' => $this->_resource->getDescriptionHelper(),

            'site'              => array(
                                            'index' => 0,
                                            'name' => 'Home'
                                        ),
            'navigation'        => array(),
            'options'           => array(),
        );

        return $templateData;
    }

    public function loadModel()
    {
        $siteConfig = $this->getSiteConfig();

        // m is automatically used and selected
        if ((!isset($this->_request->m)) && (!$this->_owApp->selectedModel)) {
            // TODO: what if no site model configured?
            if (!Erfurt_Uri::check($siteConfig['model'])) {
                $site = $this->_privateConfig->defaultSite;
                $root = $this->getComponentHelper()->getComponentRoot();
                $configFilePath = sprintf('%s%s/%s/%s', $root, $this->_relativeTemplatePath, $site, SiteHelper::SITE_CONFIG_FILENAME);
                throw new OntoWiki_Exception(
                    'No model selected! Please, configure a site model by setting the option '
                    . '"model=..." in "' . $configFilePath . '" or specify parameter m in the URL.'
                );
            } else {
                // setup the model
                $this->_modelUri = $siteConfig['model'];
                $store = OntoWiki::getInstance()->erfurt->getStore();
                $this->_model = $store->getModel($this->_modelUri);
                OntoWiki::getInstance()->selectedModel = $this->_model;
            }
        } else {
            $this->_model = $this->_owApp->selectedModel;
            $this->_modelUri = (string) $this->_owApp->selectedModel;
        }

        return $this->_model;
    }

    protected function _loadResource()
    {
        // r is automatically used and selected, if not then we use the model uri as starting point
        if ((!isset($this->_request->r)) && (!$this->_owApp->selectedResource)) {
            OntoWiki::getInstance()->selectedResource = new OntoWiki_Resource($this->_modelUri, $this->_model);
        }
        $this->_resource = $this->_owApp->selectedResource;
        $this->_resourceUri = (string) $this->_owApp->selectedResource;
    }

}
