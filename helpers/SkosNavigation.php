<?php

class Site_View_Helper_SkosNavigation extends OntoWiki_Component_Helper
{
    protected $_store;
    protected $_selectedModel;
    protected $_titleHelper;
    protected $_view;

    public function __construct()
    {
        $this->_store = OntoWiki::getInstance()->erfurt->getStore();
        $this->_selectedModel = OntoWiki::getInstance()->selectedModel;
        // We use our own title helper and ignore the injected one.
        $this->_titleHelper = new OntoWiki_Model_TitleHelper($this->_selectedModel);
    }

    public function skosNavigation($titleHelper)
    {
        // $this->_titleHelper = $titleHelper;
        return ($this->_renderNavigation($this->_skosNavigationAsArray()));
    }

    public function setView(Zend_View_Interface $view)
    {
        $this->_view = $view;
    }

    protected function _renderNavigation($navigationTree, $depth = 0)
    {
        $navigationTree = (array)$navigationTree;
        if (count($navigationTree) == 0) {
            return '';
        }
        $rendered = '<ul class="depth_' . $depth . '">' . PHP_EOL;
        foreach ($navigationTree as $navigationItem) {
            $url = isset($navigationItem->page) 
                 ? $navigationItem->page 
                 : $this->_view->url($navigationItem->uri);

            $label = $this->_titleHelper->getTitle($navigationItem->uri);

            // var_dump($this->_titleHelper->getResources());

            $rendered .= sprintf('<li><a href="%s">%s</a>%s</li>%s', 
                                 $url,
                                 $label,
                                 $this->_renderNavigation($navigationItem->subConcepts, $depth + 1),
                                 PHP_EOL);
        }
        $rendered .= '</ul>' . PHP_EOL;

        return $rendered;
    }

    protected function _skosNavigationAsArray()
    {
        $query = '
            PREFIX skos: <http://www.w3.org/2004/02/skos/core#>
            PREFIX sysont: <http://ns.ontowiki.net/SysOnt/>
            SELECT ?topConcept 
            FROM <' . (string)$this->_selectedModel . '>
            WHERE {
                ?cs a skos:ConceptScheme .
                ?topConcept skos:topConceptOf ?cs
                OPTIONAL {
                    ?topConcept sysont:order ?order
                }
            }
            ORDER BY ASC(?order)
            ';

        if ($result = $this->_store->sparqlQuery($query)) {
            $tree = array();
            foreach ($result as $row) {
                $topConcept = new stdClass;
                $topConcept->uri = $row['topConcept'];

                $this->_titleHelper->addResource($topConcept->uri);

                $subQuery = '
                    PREFIX skos: <http://www.w3.org/2004/02/skos/core#>
                    PREFIX sysont: <http://ns.ontowiki.net/SysOnt/>
                    PREFIX foaf: <http://xmlns.com/foaf/0.1/>
                    SELECT ?subConcept ?page
                    FROM <' . (string)$this->_selectedModel . '>
                    WHERE {
                        ?subConcept skos:broader <' . $topConcept->uri . '>
                        OPTIONAL {
                            ?subConcept sysont:order ?order .
                        }
                        OPTIONAL {
                          ?subConcept foaf:page ?page .
                        }
                    }
                    ORDER BY ASC(?order)
                    ';
                if ($subConceptsResult = $this->_store->sparqlQuery($subQuery)) {
                    $subConcepts = array();
                    foreach ($subConceptsResult as $subConceptRow) {
                        $subConcept = new stdClass;
                        $subConcept->uri = $subConceptRow['subConcept'];
                        $subConcept->subConcepts = array();

                        if ($subConceptRow['page'] != null) {
                            $subConcept->page = $subConceptRow['page'];
                        }
                        
                        $this->_titleHelper->addResource($subConcept->uri);                        
                        $subConcepts[$subConcept->uri] = $subConcept;
                    }
                    $topConcept->subConcepts = $subConcepts;
                } else {
                    $topConcept->subConcepts = array();
                }

                $tree[$topConcept->uri] = $topConcept;
            }

            return $tree;
        }

        return array();
    }
}
