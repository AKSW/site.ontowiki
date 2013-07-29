<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2011, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * OntoWiki NavigationList view helper
 *
 * returns an ol/ul list of a given rdf:seq resource
 *
 * @todo render substructure
 * @category OntoWiki
 * @package  OntoWiki_extensions_components_site
 */
class Site_View_Helper_NavigationList extends Zend_View_Helper_Abstract implements Site_View_Helper_MarkupInterface
{
    /*
     * the uri of the special title property
     */
    protected $_menuLabel = 'http://ns.ontowiki.net/SysOnt/Site/menuLabel';

    /*
     * current view, injected with setView from Zend
     */
    public $view;

    /*
     * The resource URI which is used for the SPARQL query
     */
    private $_navResource;

    /*
     * The resource URI which is used as a relation from the active resource to the navResource
     */
    private $_navProperty;

    /*
     * the used list tag (ol/ul)
     */
    private $_listTag = 'ul';

    /*
     * the used list tag for sub lists (ol/ul)
     * this value is set to the value of $listTag if not specified
     */
    private $_sublistTag = '';

    /*
     * css class value for the list item
     */
    private $_listClass = '';

    /*
     * css class value for the list item of a sublist
     */
    private $_sublistClass = '';

    /*
     * css class value for active li item
     */
    private $_activeItemClass = 'active';

    /*
     * the currently active resource
     */
    private $_activeUrl = '';

    /*
     * a string which is prepended to the list
     */
    private $_prefix = '';

    /*
     * a string which is appended to the list
     */
    private $_suffix = '';

    /*
     * the nav tag css class
     */
    private $_navClass = '';

    /*
     * id value for the nav item
     */
    private $_navId = '';

    /*
     * surround sub navigation heading tag
     */
    private $_subheadTag = 'strong';

    /*
     * surround sub navigation heading class
     */
    private $_subheadClass = 'headline';

    /*
     * skip link to navigation resource
     */
    private $_subheadSkipLink = true;

    /*
     * main call method, takes an URI and an options array.
     * possible options array key:
     * - navResource     - the navigation resource URI
     * - navProperty    - the link to the navigation resource
     * - listTag         - the used html tag (ol, ul)
     * - listClass       - the used css class for the list
     * - activeItemClass - the used css class for the active item
     * - activeUrl       - the active item
     * - prefix          - a prefix string outside of the list
     * - suffix          - a suffix string outside of the list
     * - titleProperty   - an additional VIP title property
     * - navClass        - the navigation tag css class
     * - navId           - the navigation tag id attribute value
     *
     */
    public function navigationList($options = array())
    {
        $owapp       = OntoWiki::getInstance();
        $store       = $owapp->erfurt->getStore();
        $titleHelper = new OntoWiki_Model_TitleHelper($owapp->selectedModel);

        if (!isset($options['navResource']) || !$options['navResource']) {
            if (isset($options['navProperty'])) {
                $this->_navProperty = $options['navProperty'];
                $resource          = new OntoWiki_Resource($this->resourceUri, $this->model);
                $description       = $resource->getDescription();
                $description       = $description[(string)$resource];
                if (isset($description[$this->_navProperty])) {
                    $this->_navResource = $description[$this->_navProperty][0]['value'];
                } else {
                    return '';
                }
            } else {
                return '';
            }
        } else {
            $this->_navResource = $options['navResource'];
        }

        // overwrite standard options with given ones, if given as option
        $this->_listTag         = (isset($options['listTag'])) ? $options['listTag'] : $this->_listTag;
        $this->_listClass       = (isset($options['listClass'])) ? $options['listClass'] : $this->_listClass;
        $this->_activeItemClass = (isset($options['activeItemClass'])) ? $options['activeItemClass'] : $this->_activeItemClass;
        $this->_activeUrl       = (isset($options['activeUrl'])) ? $options['activeUrl'] : $this->_activeUrl;
        $this->_prefix          = (isset($options['prefix'])) ? $options['prefix'] : $this->_prefix;
        $this->_suffix          = (isset($options['suffix'])) ? $options['suffix'] : $this->_suffix;
        $this->_navClass        = (isset($options['navClass'])) ? $options['navClass'] : $this->_navClass;
        $this->_navId           = (isset($options['navId'])) ? $options['navId'] : $this->_navId;
        $this->_subheadTag      = (isset($options['subheadTag'])) ? $options['subheadTag'] : $this->_subheadTag;
        $this->_subheadClass    = (isset($options['subheadClass'])) ? $options['subheadClass'] : $this->_subheadClass;
        $this->_subheadSkipLink = (isset($options['subheadSkipLink'])) ? $options['subheadSkipLink'] : $this->_subheadSkipLink;
        $this->_sublistTag      = (isset($options['sublistTag'])) ? $options['sublistTag'] : $this->_listTag; // takes the list tag for default
        $this->_sublistClass    = (isset($options['sublistClass'])) ? $options['sublistClass'] : $this->_sublistClass;

        if (isset($options['titleProperty'])) {
            $titleHelper->prependTitleProperty($options['titleProperty']);
        } else {
            $titleHelper->prependTitleProperty($this->_menuLabel);
        }

        $navigation = $this->_getMenu($this->_navResource, $store, $titleHelper);
        $navigation = $this->_setTitles($navigation, $titleHelper);

        return $this->render($navigation);
    }

    /*
     * view setter (dev zone article: http://devzone.zend.com/article/3412)
     */
    public function setView(Zend_View_Interface $view)
    {
        $this->view = $view;
        $this->model       = $view->model;
        if (isset($view->templateData)) {
            $this->templateData = $view->templateData;
        }
        $this->resourceUri = (string)$view->resourceUri;
    }

    /*
     * render a html snippet from the navigation structure array
     */
    private function render($navigation = array())
    {
        $return = '';
        foreach ($navigation as $item) {
            // prepare item values
            $url   = $item['url'];
            $label = $item['label'];

            // item tag start (depends on activeness)
            if ($url == $this->_activeUrl) {
                $return .= '<li class="'.$this->_activeItemClass.'">';
            } else {
                $return .= '<li>';
            }

            if ($item['hasSubMenu']) {
                $return .= '<' . $this->_subheadTag . ' class="' . $this->_subheadClass . '">';
                if ($this->_subheadSkipLink) {
                    $return .= $label;
                } else {
                    $return .= '<a href="'.$url.'">'.$label.'</a>';
                }
                $return .= '</' . $this->_subheadTag . '>';
                $return .= $this->_renderSubMenu($item['subMenu']);
            } else {
                $return .= '<a href="'.$url.'">'.$label.'</a>';
            }

            // item tag end
            $return .= '</li>' . PHP_EOL;
        }

        // prepare the class attribute of the list
        if ($this->_listClass != '') {
            $class = ' class="'. $this->_listClass .'" ';
        } else {
            $class = '';
        }

        // surround the list items with ul or ol tag
        $return  = '<' . $this->_listTag . $class . '>' . PHP_EOL . $return;
        $return .= '</' . $this->_listTag . '>' . PHP_EOL;

        // surround the list with prefix/suffix
        $return = $this->_prefix . $return . $this->_suffix;

        // prepare class and id attribute/value strings for the nav-tag
        $class = ($this->_navClass != '') ? ' class="'.$this->_navClass.'"' : '';
        $id    = ($this->_navId != '')    ? ' id="'.$this->_navId.'"'       : '';

        // surround the list with the nav-tag
        $return = '<nav'. $class . $id .'>' . $return . '</nav>' . PHP_EOL;
        return $return;
    }

    private function _renderSubMenu ($navigation)
    {
        $return = '';
        foreach ($navigation as $item) {
            // prepare item values
            $url   = $item['url'];
            $label = $item['label'];

            // item tag start (depends on activeness)
            if ($url == $this->_activeUrl) {
                $return .= '<li class="'.$this->_activeItemClass.'">';
            } else {
                $return .= '<li>';
            }

            if ($item['hasSubMenu']) {
                $return .= '<' . $this->_subheadTag . ' class="' . $this->_subheadClass . '">';
                if ($this->_subheadSkipLink) {
                    $return .= $label;
                } else {
                    $return .= '<a href="'.$url.'">'.$label.'</a>';
                }
                $return .= '</' . $this->_subheadTag . '>';
                $return .= $this->_renderSubMenu($item['subMenu']);
            } else {
                $return .= '<a href="'.$url.'">'.$label.'</a>';
            }

            // item tag end
            $return .= '</li>' . PHP_EOL;
        }

        $return  = '<' . $this->_sublistTag . ' class="' . $this->_sublistClass . '">' . PHP_EOL . $return;
        $return .= '</' . $this->_sublistTag . '>' . PHP_EOL;

        return $return;
    }

    private function _getMenu ($navResource, $store, $titleHelper = null)
    {
        $query = '
            PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
            SELECT ?item ?prop
            WHERE {
               <'. $navResource .'> ?prop ?item.
               ?prop a rdfs:ContainerMembershipProperty.
            }
        ';

        try {
            $result = $store->sparqlQuery($query);
        } catch (Exception $e) {
            throw new OntoWiki_Exception('Problem while getting menu entries.', $e);
        }

        // array of urls and labels which represent the navigation menu
        $navigation = array();

        // round one: fill navigation array with urls as well as fill the titleHelper
        foreach ($result as $row) {
            // works only for URIs ...
            if (Erfurt_Uri::check($row['item'])) {
                // prepare variables
                $url      = $row['item'];
                $property = $row['prop'];

                // fill the titleHelper
                if ($titleHelper !== null) {
                    $titleHelper->addResource($url);
                }

                // split property and use numeric last part for navigation order.
                // example property: http://www.w3.org/2000/01/rdf-schema#_1
                $pieces = explode('_', $property);
                if (isset($pieces[1]) && is_numeric($pieces[1])) {
                    // file the navigation array
                    $navigation[$pieces[1]] = array(
                        'url' => $url,
                        'label' => $pieces[1]
                    );

                    $subMenu = $this->_getMenu($url, $store, $titleHelper);

                    if (count($subMenu) > 0) {
                        $navigation[$pieces[1]]['hasSubMenu'] = true;
                        $navigation[$pieces[1]]['subMenu'] = $subMenu;
                    } else {
                        $navigation[$pieces[1]]['hasSubMenu'] = false;
                    }
                }
            }
        }

        // round three: sort navigation according to the index
        if (count($navigation) > 1) {
            ksort($navigation);
        }

        return $navigation;
    }

    private function _setTitles ($navigation, $titleHelper)
    {
        // round two: fill navigation array with labels from the titleHelper
        foreach ($navigation as $key => $value) {
            $label = $titleHelper->getTitle($value['url']);
            $navigation[$key]['label'] = $label;
            if ($navigation[$key]['hasSubMenu']) {
                $navigation[$key]['subMenu'] = $this->_setTitles(
                    $navigation[$key]['subMenu'], $titleHelper
                );
            }
        }

        return $navigation;
    }
}
