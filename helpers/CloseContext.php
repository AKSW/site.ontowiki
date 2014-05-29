<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * OntoWiki CloseContext view helper
 *
 * returns the closing component of context opened with OpenContext helper
 *
 * @category OntoWiki
 * @package  OntoWiki_extensions_components_site
 */
class Site_View_Helper_CloseContext extends Zend_View_Helper_Abstract implements Site_View_Helper_MarkupInterface
{
    /**
     * Closes a resource context in data markup.
     */
    public function closeContext()
    {
        if (!isset($this->view->dataMarkupContextStack)) {
            throw new OntoWiki_Exception('Attempting to close a context when no contexts were opened in this view.');
        }

        $options = array_pop($this->view->dataMarkupContextStack);
        return "</{$options['tag']}>";
    }
}
