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

    // http://www.w3.org/TR/html4/index/elements.html
    public static $htmlForbiddenEndTags = array(
        'area',
        'base',
        'basefont',
        'br',
        'col',
        'frame',
        'hr',
        'img',
        'input',
        'isindex',
        'link',
        'meta',
        'param',
    );

    public static $alwaysRemoveNoAttrTags = array(
        'link',
        'meta',
    );

    public static $htmlPropertyValue = array(
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
        'data'   => array('attr' => 'value',   'type' => 'string'),
        'meter'  => array('attr' => 'value',   'type' => 'string'),
        'time'   => array('attr' => 'datetime','type' => 'string'),
    );

    // http://www.whatwg.org/html/microdata.html#values
    public static $microdataPropertyValue = array(
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
        'time'   => array('attr' => 'datetime','type' => 'string'),
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
            $object      = $options['value'];
            if (!is_array($object)) {
                $object = array('value' => $object);
            }
            $objects     = array($object);
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
        $resource     = $model->getResource($uri);
        $description  = $resource->getDescription();
        if (isset($description[$uri])) {
            $description  = $description[$uri];
        } else {
            $description = array();
        }

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
        $label   = (isset($options['label']))   ? $options['label']   : null;
        $labels  = (isset($options['labels']))  ? $options['labels']  : array();

        $tmplOpt = $this->view->templateOptions();
        $markup  = (isset($options['markup']))  ? $options['markup']  : $tmplOpt->getValue('http://ns.ontowiki.net/SysOnt/Site/dataMarkupFormat', 'RDFa');

        $attrs   = array();
        $value   = null;

        if (!in_array($tag, static::$htmlForbiddenEndTags)) {
            $shortTag = '';
            $endTag   = "</$tag>";
        } else {
            $shortTag = '/';
            $endTag   = '';
        }

        $content = $object['value'];
        $alt     = '';

        if ($label !== null) {
            $value   = $object['value'];
            $content = $label;
            $alt     = $content;
        }

        if (isset($labels[$content])) {
            $value   = $object['value'];
            $content = $labels[$content];
            $alt     = $content;
        }

        if ($plain) {
            return $content;
        } else {
            //$property = $this->view->curie($property);

            if ($endTag === '') {
                $value   = $object['value'];
                $content = '';

                if ($iprefix !== '' || $isuffix !== '') {
                    throw new OntoWiki_Exception('Attempting to use literal helper\'s iprefix and isuffix with '.$tag.' tag.');
                }
            }

            $isUri = isset($object['type']) && $object['type'] === 'uri';

            switch ($markup) {
                case 'RDFa':
                    switch ($tag) {
                        case 'link':
                            $relation = 'rel';
                        break; default:
                            $relation = 'property';
                    }

                    $attrs[$relation] = $property;

                    if ($isUri) {
                        switch ($tag) {
                            case 'img':
                                $target = 'src';
                            break; case 'link':
                                $target = 'href';
                            break; default:
                                $target = 'resource';
                        }

                        $attrs[$target] = $object['value'];
                        $value = null;
                    }

                    if ($value !== null) $attrs['content'] = $value;
                break;
                case 'microdata':
                    $attrs['itemprop'] = $property;

                    // microdata does not have one general property for machine-readable value
                    $valueInfo = null;
                    if (isset(static::$microdataPropertyValue[$tag])) {
                        $valueInfo = static::$microdataPropertyValue[$tag];
                    }

                    if ($valueInfo && $value === null) {
                        // microdata uses element's textContent only "otherwise";
                        // for all defined elements an attribute must be used
                        $value = $object['value'];
                    }

                    if ($value !== null) {
                        if (
                            $valueInfo !== null // element may have machine-readable values
                            && ($isUri xor $valueInfo['type'] !== 'URI') // element's value type matches
                        ) {
                            $attrs[$valueInfo['attr']] = $value;
                        } else {
                            // microdata does not allow this element with this type of property (URI/non-URI),
                            // supply additional element just to put machine-readable value into it
                            $attr = '';
                            foreach ($attrs as $name => $value) {
                                $attr .= sprintf(' %s="%s"', $name, $value);
                            }

                            if (!$isUri) {
                                /* non-empty <data/> is reasonable to use here,
                                   but there are many issues in microdata parsers with it */
                                /*
                                $prefix .= '<data'.$attr.' value="'.$value.'">';
                                $suffix  = '</data>'.$suffix;
                                */
                                $prefix .= '<meta'.$attr.' content="'.$value.'"/>';
                            } else {
                                $prefix .= '<link'.$attr.' href="'.$value.'"/>';
                            }
                            $attrs = array();
                        }
                    }
                break;
                case 'NONE':
                break;
                default:
                    throw new OntoWiki_Exception('Unknown data markup format specified.');
            }

            // add attributes expected in HTML regardless of additional markup (media, links, HTML data)
            if (isset(static::$htmlPropertyValue[$tag])) {
                $valueInfo = static::$htmlPropertyValue[$tag];
                if ($isUri xor $valueInfo['type'] !== 'URI') {
                    if (!isset($attrs[$valueInfo['attr']])) {
                        $attrs[$valueInfo['attr']] = $object['value'];
                    }
                }
            }

            if ($class !== '') {
                $attrs['class'] = $class;
            }

            // execute the helper markup on the content (after the extensions)
            $content = $this->view->executeHelperMarkup($content);

            // filter by using available extensions
            if (isset($object['datatype'])) {
                $datatype = $object['datatype'];
                $content = $this->view->displayLiteralPropertyValue($content, $property, $datatype);
            } elseif (isset($object['lang'])) {
                $attrs['xml:lang'] = $object['lang'];
                $attrs['lang']     = $object['lang'];
                $content = $this->view->displayLiteralPropertyValue($content, $property);
            } else {
                $content = $this->view->displayLiteralPropertyValue($content, $property);
            }

            if ($tag === 'img') {
                $attrs['alt'] = $alt;
            }

            $html = sprintf('%s%s%s', $iprefix, $content, $isuffix);

            $attr = '';
            foreach ($attrs as $name => $value) {
                $attr .= sprintf(' %s="%s"', $name, $value);
            }
            // don't generate empty elements, if tag is not specified explicitly
            if ($attr !== '' || (isset($options['tag']) && !in_array($tag, static::$alwaysRemoveNoAttrTags))) {
                $html = sprintf('<%s%s%s>%s%s', $tag, $attr, $shortTag, $html, $endTag);
            }

            return sprintf('%s%s%s', $prefix, $html, $suffix);
        }
    }

}
