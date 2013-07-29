<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2006-2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * OntoWiki Table view helper
 *
 * returns the content of a specific property of a given resource as an RDFa
 * annotated tag with (optional) given css classes and other parameters
 * this helper is usable as {{literal ...}} markup in combination with
 * ExecuteHelperMarkup
 *
 * @category OntoWiki
 * @package  OntoWiki_extensions_components_site
 */
class Site_View_Helper_Table extends Zend_View_Helper_Abstract implements Site_View_Helper_MarkupInterface
{
    /*
     * current view, injected with setView from Zend
     */
    public $view;

    public $contentProperties = array(
        'http://ns.ontowiki.net/SysOnt/Site/tableContent',
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
     */
    public function table($options = array())
    {
        $model       = OntoWiki::getInstance()->selectedModel;
        $titleHelper = new OntoWiki_Model_TitleHelper($model);

        // check for options and assign local vars or default values
        $class   = (isset($options['class']))   ? $options['class']   : '';
        $id      = (isset($options['id']))      ? $options['id']      : '';
        $tag     = (isset($options['tag']))     ? $options['tag']     : 'span';
        $prefix  = (isset($options['prefix']))  ? $options['prefix']  : '';
        $suffix  = (isset($options['suffix']))  ? $options['suffix']  : '';
        $iprefix = (isset($options['iprefix'])) ? $options['iprefix'] : '';
        $isuffix = (isset($options['isuffix'])) ? $options['isuffix'] : '';

        // choose, which uri to use: option over helper default over view value
        $uri = (isset($this->resourceUri))           ? $this->resourceUri : null;
        $uri = (isset($options['selectedResource'])) ? (string)$options['selectedResource'] : $uri;
        $uri = (isset($options['uri']))              ? (string)$options['uri'] : $uri;
        $uri = Erfurt_Uri::getFromQnameOrUri($uri, $model);

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

        // create description from resource URI
        $resource     = new OntoWiki_Resource($uri, $model);
        $description  = $resource->getDescription();
        $description  = $description[$uri];

        // get the table size
        $tableRows = 0; // the URI of the main content property
        $tableColumns = 0; // the URI of the main content property
        foreach ($contentProperties as $contentProperty) {
            if (isset($description[$contentProperty . 'Rows'])) {
                $tableRows = (int)$description[$contentProperty . 'Rows'][0]['value'];
            }
            if (isset($description[$contentProperty . 'Columns'])) {
                $tableColumns = (int)$description[$contentProperty . 'Columns'][0]['value'];
            }
            if (null != $tableRows && null != $tableColumns) {
                $mainProperty = $contentProperty;
                break;
            }
        }
        $content = '';
        // filter and render the table
        if (0 < $tableRows && 0 < $tableColumns) {
            $content .= '<table id=' . $id . '><thead>';
            for ($row = 1; $row <= $tableRows; $row++) {
                $content .= '<tr id="R' . $row . '">';
                for ($column = 1; $column <= $tableColumns; $column++) {
                    $content .= (1 == $row ? '<th' : '<td') . ' id="R' . $row . 'C' . $column . '">';
                    $currentMainProperty = $mainProperty . 'R' . $row . 'C' . $column;
                    unset($firstLiteral);
                    // search for language tag
                    foreach ($description[$currentMainProperty] as $literalNumber => $literal) {
                        $currentLanguage = OntoWiki::getInstance()->getConfig()->languages->locale;
                        if (isset($literal['lang']) && $currentLanguage == $literal['lang']) {
                            $firstLiteral = $description[$currentMainProperty][$literalNumber];
                            break;
                        }
                    }
                    if (!isset($firstLiteral)) {
                        $firstLiteral = $description[$currentMainProperty][0];
                    }
                    $cellContent = $firstLiteral['value'];

                    // execute the helper markup on the content (after the extensions)
                    $cellContent = $this->view->executeHelperMarkup($cellContent);

                    // filter by using available extensions
                    if (isset($firstLiteral['datatype'])) {
                        $datatype = $firstLiteral['datatype'];
                        $cellContent = $this->view->displayLiteralPropertyValue(
                            $cellContent,
                            $currentMainProperty,
                            $datatype
                        );
                    } else {
                        $cellContent = $this->view->displayLiteralPropertyValue(
                            $cellContent,
                            $currentMainProperty
                        );
                    }

                    $curie          = $this->view->curie($currentMainProperty);
                    $cellContent    = $iprefix . $cellContent . $isuffix;
                    $content        .= "$prefix<$tag class='$class' property='$curie'>$cellContent</$tag>$suffix";
                    $content        .= 1 == $row ? '</th>' : '</td>';
                }
                $content .= '</tr>';
                $content .= (1 == $row ? '</thead><tbody>' : '');
            }
            $content .= '</tbody></table>';
        }
        return $content;

    }

    /*
     * view setter (dev zone article: http://devzone.zend.com/article/3412)
     */
    public function setView(Zend_View_Interface $view)
    {
        $this->view = $view;
        $this->resourceUri  = (string)$view->resourceUri;
    }

}
