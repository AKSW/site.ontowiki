<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * OntoWiki Wrap view helper
 *
 * Uses output from the "content" template in the "template" one,
 * but only if there is output from the "content" template.
 * Otherwise, does not use "template" and produces nothing.
 *
 * @category OntoWiki
 * @package  OntoWiki_extensions_components_site
 */
class Site_View_Helper_Wrap extends Zend_View_Helper_Abstract implements Site_View_Helper_MarkupInterface
{
    public function wrap($options)
    {
        $resourceUri = $this->view->resourceUri;
        $content = $this->view->partial($options['content'], array('resourceUri' => $resourceUri));
        if (!trim($content)) return '';
        return $this->view->partial($options['template'], array('content' => $content, 'resourceUri' => $resourceUri));
    }
}
