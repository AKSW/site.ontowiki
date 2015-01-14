<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2011, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * OntoWiki URL view helper
 *
 * This helper takes a URI and returns a URL taking into account the route for the current request.
 *
 * @category OntoWiki
 * @package  OntoWiki_extensions_components_site
 */
class Site_View_Helper_Url extends Zend_View_Helper_Abstract
{
    /*
     * view setter (dev zone article: http://devzone.zend.com/article/3412)
     */
    public function setView(Zend_View_Interface $view)
    {
        $this->view         = $view;
        if (isset($view->model)) {
            $this->_model       = $view->model;
        }
        if (isset($view->templateData)) {
            $this->templateData = $view->templateData;
        }
    }

    public function url($options, $additionalParams = array())
    {
        if (is_string($options)) {
            /*
             * compatibility mode: signature is:
             * public function url($uri, $additionalParams = array())
             * TODO log a warning
             */
            $uri = $options;
            $options =array(
                'uri' => $uri,
                'additionalParams' => $additionalParams
            );
        }

        $uri        = (isset($options['uri']))          ? $options['uri']           : '';
        $route      = (isset($options['route']))        ? $options['route']         : 'properties';
        $controller = (isset($options['c']))            ? $options['c']             : null;
        $controller = (isset($options['controller']))   ? $options['controller']    : $controller;
        $action     = (isset($options['a']))            ? $options['a']             : null;
        $action     = (isset($options['action']))       ? $options['action']        : $action;
        $stayOnSite = (isset($options['stayOnSite']))   ? $options['stayOnSite']    : true;
        $additionalParams   = (isset($options['additionalParams']))     ? $options['additionalParams']  : $additionalParams;
        $contractNamespace  = (isset($options['contractNamespace']))    ? $options['contractNamespace'] : true;

        $urlOptions = array();

        $export = '';
        if ($controller !== null && $action !== null) {
            $urlOptions['controller'] = $controller;
            $urlOptions['action'] = $action;
        } else if ($stayOnSite) {
            // better get configured site model
            $graph = (string)OntoWiki::getInstance()->selectedModel;
            if (false !== strpos($uri, $graph)) {
                // LD-capable
                if ((string) $uri[strlen($uri)-1] == '/') {
                    // Slash should not get a suffix
                    $url = (string) $uri;
                } else {
                    // $this->getCurrentSuffix();
                    $url = $uri . '.html';
                }
                return $url;
            } else {
                $urlOptions['controller'] = 'site';
                $urlOptions['action']     = $this->view->siteId;
            }
        } else {
            $urlOptions['route'] = $route;
        }

        $url = new OntoWiki_Url($urlOptions, array('r'));

        $url->setParam('r', $uri, $contractNamespace);
                    // set the controller and action according to the site to let the site handle
                    // the URL

        foreach ($additionalParams as $name => $value) {
            $url->setParam($name, $value, true);
        }

        $url .= $export;

        return (string)$url;
    }
}
