<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2011, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * OntoWiki Date view helper
 *
 * Renders a data string according to a format string
 *
 * @category OntoWiki
 * @package  OntoWiki_extensions_components_site
 */
class Site_View_Helper_Date extends Zend_View_Helper_Abstract
{
    public function date($dateString, $formatString = null)
    {
        $translate = OntoWiki::getInstance()->translate;
        if ($formatString == null) {
            $formatString = (string)$translate->_('DATE_FORMAT');
        }
        if (is_object($dateString) && $dateString instanceof Erfurt_Rdf_Literal) {
            if ($dateString->getDatatype() == 'http://www.w3.org/2001/XMLSchema#gYear') {
                $formatString = (string)$translate->_('DATE_FORMAT_YEAR');
            } else if ($dateString->getDatatype() == 'http://www.w3.org/2001/XMLSchema#gYearMonth') {
                $formatString = (string)$translate->_('DATE_FORMAT_YEARMONTH');
            } else if ($dateString->getDatatype() == 'http://www.w3.org/2001/XMLSchema#gMonthDay') {
                $formatString = (string)$translate->_('DATE_FORMAT_MONTHDAY');
            }

            $dateString = $dateString->getLabel();
        }
        $date = new Zend_Date();
        $date->setOptions(array('format_type' => 'php'));
        $date->setDate($dateString, "yy-mm-j");
        $date->setLocale($translate->getLocale());
        return $date->toString($formatString);
    }
}
