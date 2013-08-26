<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2006-2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * OntoWiki Literal view helper
 *
 * returns the content of a specific property of a given resource as an RDFa
 * annotated tag with (optional) given css classes and other parameters
 * this helper is usable as {{literal ...}} markup in combination with
 * ExecuteHelperMarkup
 *
 * @category OntoWiki
 * @package  OntoWiki_extensions_components_site
 */
class Site_View_Helper_Literal extends Zend_View_Helper_Abstract implements Site_View_Helper_MarkupInterface
{
    /*
     * current view, injected with setView from Zend
     */
    public $view;

    public $contentProperties = array(
        'http://ns.ontowiki.net/SysOnt/Site/content',
        'http://purl.org/rss/1.0/modules/content/encoded',
        'http://rdfs.org/sioc/ns#content',
        'http://purl.org/dc/terms/description',
        'http://www.w3.org/2000/01/rdf-schema#comment',
    );

    // http://www.whatwg.org/html/microdata.html#values
    public $microdataPropertyValue = array(
        'meta'   => array('attr' => 'content', 'type' => 'string'),
        'audio'  => array('attr' => 'src',     'type' => 'URI'),
        'embed'  => array('attr' => 'src',     'type' => 'URI'),
        'iframe' => array('attr' => 'src',     'type' => 'URI'),
        'img'    => array('attr' => 'src',     'type' => 'URI'),
        'source' => array('attr' => 'src',     'type' => 'URI'),
        'track'  => array('attr' => 'src',     'type' => 'URI'),
        'video'  => array('attr' => 'src',     'type' => 'URI'),
        'a'      => array('attr' => 'href',    'type' => 'URI'),
        'area'   => array('attr' => 'href',    'type' => 'URI'),
        'link'   => array('attr' => 'href',    'type' => 'URI'),
        'object' => array('attr' => 'data',    'type' => 'string'),
        'data'   => array('attr' => 'value',   'type' => 'string'),
        'meter'  => array('attr' => 'value',   'type' => 'string'),
        'time'   => array('attr' => 'value',   'type' => 'FIXME'),
    );

    /*
     * the main tah method, mentioned parameters are:
     * - uri      - which resource the literal is from (empty means selected * Resource)
     * - property - qname/uri of property to use
     * - class    - css class
     * - tag      - the used tag, e.g. span
     * - prefix   - string at the beginning
     * - suffix   - string at the end
     * - iprefix  - string between tag and content at the beginning
     * - isuffix  - string betwee content and tag at the end
     * - plain    - outputs the literal only (no html)
     * - array    - returns an array of the values (not suitable for template markup)
     * - label    - content override
     * - labels   - content overrides for specified values
     * - value    - static value to use instead of querying the database
     */
    public function literal($options = array())
    {
        $model       = OntoWiki::getInstance()->selectedModel;
        $titleHelper = new OntoWiki_Model_TitleHelper($model);

        // check for options and assign local vars or default values
        $array   = (isset($options['array']))   ? $options['array']   : false;

        if (!isset($options['value'])) {
            $description = $this->_getDescription($model, $options);
            $property    = $this->_selectMainProperty($model, $description, $options);
            $objects     = $property ? $description[$property] : array();
        } else {
            $property    = $options['property'];
            $objects     = array(array('value' => $options['value']));
        }

        // filter and render the (first) literal value of the main property
        // TODO: striptags and tidying as extension
        if ($objects) {
            if ($array) {
                $return = array();
                foreach ($objects as $object) {
                    $return[] = $this->_getContent($object, $property, $options);
                }
                return $return;
            } else {
                //search for language tag
                unset($object);
                foreach ($objects as $literalNumber => $literal) {
                    $currentLanguage = OntoWiki::getInstance()->getConfig()->languages->locale;
                    if (isset($literal['lang']) && $currentLanguage == $literal['lang']) {
                        $object = $objects[$literalNumber];
                        break;
                    }
                }
                if (!isset($object)) {
                    $object = $objects[0];
                }

                return $this->_getContent($object, $property, $options);
            }
        } else {
            if ($array) {
                return array();
            } else {
                return '';
            }
        }

    }

    /*
     * view setter (dev zone article: http://devzone.zend.com/article/3412)
     */
    public function setView(Zend_View_Interface $view)
    {
        $this->view = $view;
        $this->resourceUri  = (string)$view->resourceUri;
    }

    protected function _getDescription($model, $options)
    {
        // choose, which uri to use: option over helper default over view value
        $uri = (isset($this->resourceUri))           ? $this->resourceUri : null;
        $uri = (isset($options['selectedResource'])) ? (string)$options['selectedResource'] : $uri;
        $uri = (isset($options['uri']))              ? (string)$options['uri'] : $uri;
        $uri = Erfurt_Uri::getFromQnameOrUri($uri, $model);

        // create description from resource URI
        $resource     = new OntoWiki_Resource($uri, $model);
        $description  = $resource->getDescription();
        $description  = $description[$uri];

        return $description;
    }

    protected function _selectMainProperty($model, $description, $options)
    {
        // choose, which properties to use (todo: allow multple properties)
        $contentProperties = (isset($options['property'])) ? array( $options['property']) : null;
        $contentProperties = (!$contentProperties) ? $this->contentProperties : $contentProperties;

        foreach ($contentProperties as $key => $value) {
            try {
                $validatedValue = Erfurt_Uri::getFromQnameOrUri($value, $model);
                $contentProperties[$key] = $validatedValue;
            } catch (Exception $e) {
                unset($contentProperties[$key]);
            }
        }

        // select the main property from existing ones
        $mainProperty = null; // the URI of the main content property
        foreach ($contentProperties as $contentProperty) {
            if (isset($description[$contentProperty])) {
                $mainProperty = $contentProperty;
                break;
            }
        }

        return $mainProperty;
    }

    protected function _getContent($object, $property, $options)
    {
        $class   = (isset($options['class']))   ? $options['class']   : '';
        $tag     = (isset($options['tag']))     ? $options['tag']     : 'span';
        $prefix  = (isset($options['prefix']))  ? $options['prefix']  : '';
        $suffix  = (isset($options['suffix']))  ? $options['suffix']  : '';
        $iprefix = (isset($options['iprefix'])) ? $options['iprefix'] : '';
        $isuffix = (isset($options['isuffix'])) ? $options['isuffix'] : '';
        // array used to return plain values by default
        $plain   = (isset($options['plain']))   ? $options['plain']   : isset($options['array']);
        $label   = (isset($options['label']))   ? $options['label']   : '';
        $labels  = (isset($options['labels']))  ? $options['labels']  : array();

        $tmplOpt = $this->view->templateOptions();
        $markup  = $tmplOpt->getValue('http://ns.ontowiki.net/SysOnt/Site/dataMarkupFormat', 'RDFa');

        $attr    = '';
        $value   = null;

        $content = $object['value'];

        if ($label !== '') {
            $value = $object['value'];
            $content = $label;
        }

        if (isset($labels[$content])) {
            $value = $object['value'];
            $content = $labels[$content];
        }

        if ($plain) {
            return $content;
        } else {
            //$property = $this->view->curie($property);

            $isUri = isset($object['type']) && $object['type'] === 'uri';

            switch ($markup) {
                case 'RDFa':
                    $attr .= ' property="'.$property.'"';

                    if ($isUri) {
                        $attr .= ' resource="'.$object['value'].'"';
                        $value = null;
                    }

                    if ($value !== null) $attr .= ' content="'.$value.'"';
                break;
                case 'microdata':
                    $attr .= ' itemprop="'.$property.'"';

                    if ($value !== null) {
                        // microdata does not have one general property for machine-readable value
                        $valueInfo = null;
                        if (isset($this->microdataPropertyValue[$tag])) {
                            $valueInfo = $this->microdataPropertyValue[$tag];
                        }

                        if ($valueInfo !== null and ($isUri xor $valueInfo['type'] !== 'URI')) {
                            $attr .= ' '.$valueInfo['attr'].'="'.$value.'"';
                        } else {
                            if (!$isUri) {
                                $prefix .= '<data'.$attr.' value="'.$value.'">';
                                $suffix  = '</data>'.$suffix;
                            } else {
                                $prefix .= '<link'.$attr.' href="'.$value.'"/>';
                            }
                            $attr = '';
                        }
                    }
                break;
            }

            if ($class !== '') {
                $attr .= ' class="'.$class.'"';
            }

            // execute the helper markup on the content (after the extensions)
            $content = $this->view->executeHelperMarkup($content);

            // filter by using available extensions
            if (isset($object['datatype'])) {
                $datatype = $object['datatype'];
                $content = $this->view->displayLiteralPropertyValue($content, $property, $datatype);
            } elseif (isset($object['lang'])) {
                $attr   .= ' xml:lang="'.$object['lang'].'"';
                $attr   .= ' lang="'.$object['lang'].'"';
                $content = $this->view->displayLiteralPropertyValue($content, $property);
            } else {
                $content = $this->view->displayLiteralPropertyValue($content, $property);
            }

            return "$prefix<$tag$attr>$iprefix$content$isuffix</$tag>$suffix";
        }
    }

}
