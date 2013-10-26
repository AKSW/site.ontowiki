<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * OntoWiki Literals view helper
 *
 * returns all values of a specific property, annotated and concatenated
 *
 * @category OntoWiki
 * @package  OntoWiki_extensions_components_site
 */
class Site_View_Helper_Literals extends Zend_View_Helper_Abstract implements Site_View_Helper_MarkupInterface
{
    public function literals($options = array())
    {
        $options['array'] = true;
        if (!isset($options['plain'])) $options['plain'] = false;

        return implode('', $this->view->literal($options));
    }
}
