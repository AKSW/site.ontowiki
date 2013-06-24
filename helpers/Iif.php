<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2006-2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * OntoWiki Iif view helper
 *
 * returns either specified content depending on a boolean value
 * of a specific property of a given resource as an RDFa
 * annotated tag with (optional) given css classes and other parameters
 *
 * @category OntoWiki
 * @package  OntoWiki_extensions_components_site
 */
class Site_View_Helper_Iif extends Site_View_Helper_Literal implements Site_View_Helper_MarkupInterface
{
    public $contentProperties = array(
    );

    /*
     * the main tah method, mentioned parameters are:
     * - uri          - which resource the literal is from (empty means selected * Resource)
     * - property     - qname/uri of property to use
     * - class        - css class
     * - tag          - the used tag, e.g. span
     * - prefix       - string at the beginning
     * - suffix       - string at the end
     * - trueContent  - content to be used when property is true
     * - falseContent - content to be used when property is false
     */
    public function iif($options = array())
    {
        $model       = OntoWiki::getInstance()->selectedModel;

        // check for options and assign local vars or default values
        $class        = (isset($options['class']))        ? $options['class']        : '';
        $tag          = (isset($options['tag']))          ? $options['tag']          : 'span';
        $prefix       = (isset($options['prefix']))       ? $options['prefix']       : '';
        $suffix       = (isset($options['suffix']))       ? $options['suffix']       : '';
        $trueContent  = (isset($options['trueContent']))  ? $options['trueContent']  : 'true';
        $falseContent = (isset($options['falseContent'])) ? $options['falseContent'] : 'false';

        $description  = $this->_getDescription($model, $options);
        $mainProperty = $this->_selectMainProperty($model, $description, $options);

        // render the (first) boolean value of the main property
        if ($mainProperty) {
            // TODO: check datatype?
            $firstLiteral = $description[$mainProperty][0];
            $content      = $firstLiteral['value'];
            $text         = $content === 'true' ? $trueContent : $falseContent;

            $curie = $this->view->curie($mainProperty);
            return "$prefix<$tag class='$class' property='$curie' content='$content'>$text</$tag>$suffix";
        } else {
            return '';
        }

    }
}
