<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2011, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * OntoWiki Exhibit view helper
 *
 * prints exhibit header script
 *
 * @category OntoWiki
 * @package  OntoWiki_extensions_components_site
 */
class Site_View_Helper_HeadScriptExhibit extends Zend_View_Helper_Abstract
{
    /*
     * current view, injected with setView from Zend
     */
    public $view;

    private $_defaultPropertyInfo = array(
        array ('uri' => 'http://lod2.eu/schema/exhibitData')
    );

    private $_exhibitScript = 'http://api.simile-widgets.org/exhibit/3.0.0/exhibit-api.js';

    /*
     * Parameter propertyInfo:
     *   an array of arrays which has uri and mod key
     *     uri: property uri to use
     *     sprintf : a format string to transform literal values into a data URI
     *  OR
     *   simply a property string
     *  OR
     *   nothing, to use the default
     *
     */
    public function headScriptExhibit($propertyInfo = null)
    {
        if ($propertyInfo == null) {
            // set default info
            $propertyInfo = $this->_defaultPropertyInfo;
        } else if (is_string($propertyInfo)) {
            // transform string to array
            $propertyInfo = array(
                array('uri' => $propertyInfo)
            );
        } else if (is_array($propertyInfo) && (isset($propertyInfo['uri']))) {
            // transform single level array to multi-hierarchy form
            $propertyInfo = array($propertyInfo);
        }

        // if we have something completly different as option
        if (!is_array($propertyInfo)) {
            return;
        }

        $description = new Erfurt_Rdf_MemoryModel($this->view->description);
        $resourceUri = $this->view->resourceUri;
        $propertyUri = null;
        $literalMod  = null;

        // the main loop to search for a valid property value
        // first hit is taken and every other value is not used
        foreach ($propertyInfo as $info) {
            if (isset($info['uri'])) {
                $propertyUri = $info['uri'];
                $literalMod = isset($info['sprintf']) ? $info['sprintf'] : null;

                // check for exhibit data URI and integrate this as well as exhibit
                if ($description->hasSP($resourceUri, $propertyUri)) {
                    // we've found something, so we can add the exhibit script
                    echo '    <script src="'.$this->_exhibitScript.'" type="text/javascript"></script>' . PHP_EOL;
                    $value = $description->getValue($resourceUri, $propertyUri);
                    if ($literalMod != null) {
                        $value = sprintf($literalMod, $value);
                    }

                    // output the data script and return
                    $type   = "application/jsonp";
                    $rel    = "exhibit/data";
                    echo '    <link href="'.$value.'" type="'.$type.'" rel="'.$rel.'" ex:jsonp-callback="cb" />';
                    echo PHP_EOL;
                    return;
                }
            }
        }
    }

    /*
     * view setter (dev zone article: http://devzone.zend.com/article/3412)
     */
    public function setView(Zend_View_Interface $view)
    {
        $this->view = $view;
    }

}
