<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

include_once('extensions/site/TemplateOptionsClass.php');

/**
 * OntoWiki template options helper
 *
 * returns an object which can be queried for specific template options
 *
 * @category OntoWiki
 * @package  OntoWiki_extensions_components_site
 */
class Site_View_Helper_TemplateOptions extends Zend_View_Helper_Abstract
{
    /*
     * current view, injected with setView from Zend
     */
    public $view;

    public function templateOptions($resourceUri = null)
    {
        if (is_null($resourceUri)) {
            $resourceUri = $this->view->resourceUri;
        }

        return new TemplateOptionsClass($resourceUri);
    }

    /*
     * view setter (dev zone article: http://devzone.zend.com/article/3412)
     */
    public function setView(Zend_View_Interface $view)
    {
        $this->view = $view;
    }
}
