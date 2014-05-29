<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2011, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * OntoWiki Querylist view helper
 *
 * this helper executes a SPARQL query, renders each row with a given template
 * and outputs the resulting string
 *
 * @category OntoWiki
 * @package  OntoWiki_extensions_components_site
 */
class Site_View_Helper_Querylist extends Zend_View_Helper_Abstract
{
    /**
     * current view, injected with setView from Zend
     */
    public $view;

    /**
     * The TitleHelper object
     */
    private $_titleHelper = null;

    public function querylist($query, $template, $templateOptions = array(), $options = array())
    {
        $owapp      = OntoWiki::getInstance();
        $store      = $owapp->erfurt->getStore();
        $model      = $owapp->selectedModel;

        $prefix     = (isset($options['prefix']))       ? $options['prefix']    : '';
        $suffix     = (isset($options['suffix']))       ? $options['suffix']    : '';
        $delimiter  = (isset($options['delimiter']))    ? $options['delimiter'] : '';
        $property   = (isset($options['property']))     ? $options['property']  : '';

        if ($this->_titleHelper == null) {
            $this->_titleHelper = new OntoWiki_Model_TitleHelper($model);
        }

        if ($property !== '') {
            // construct query to retrieve specified property of the current resource
            $query  = new Erfurt_Sparql_Query2();

            $resourceUriVar = new Erfurt_Sparql_Query2_Var('resourceUri');
            $query->addProjectionVar($resourceUriVar);

            $query->addTriple(
                new Erfurt_Sparql_Query2_IriRef($this->view->resourceUri),
                new Erfurt_Sparql_Query2_IriRef($property),
                $resourceUriVar
            );
        }

        try {
            $result = $model->sparqlQuery($query);
        } catch (Exception $e) {
            // executions failed (return nothing)
            return $e->getMessage();
        }

        // pre-fill the title helper
        foreach ($result as $row) {
            foreach ($row as $value) {
                if (Erfurt_Uri::check($value)) {
                    $this->_titleHelper->addResource($value);
                }
            }
        }

        /*
         * If the ordering doesn't work using the 'ORDER BY' clause you should add ASC() around the
         * variable which should by sorted. E.g. "… } ORDER BY ASC(?start) ASC(?end)"
         */
        if (!stristr($query, 'ORDER BY')) {
            // sort results by resource title
            usort($result, array('Site_View_Helper_Querylist', '_cmpTitles'));
        }

        $return  = '';
        $count   = count($result);
        $current = 0;
        $odd     = true;
        foreach ($result as $row) {
            // shift status vars
            $current++;
            $odd = !$odd;

            // prepare a first / last hint for the template
            $listhint = ($current == 1) ? 'first' : null;
            $listhint = ($current == $count) ? 'last' : $listhint;
            $row['listhint'] = $listhint;

            // prepare other template vars
            $row['oddclass'] = $odd ? 'odd' : 'even';
            $row['rowcount'] = $count;
            $row['current']  = $current;
            if (isset($row['resourceUri'])) {
                if (!Erfurt_Uri::check($row['resourceUri'])) {
                    $row['title']    = $row['resourceUri'];
                } else {
                    $row['title']    = $this->_titleHelper->getTitle($row['resourceUri']);
                }
            }

            $row             = array_merge($row, $templateOptions);

            // render the template
            $return         .= $prefix . $this->view->partial($template, $row) . $suffix;
            if ($current < $count) {
                $return     .= $delimiter;
            }
        }

        return $return;
    }

    /*
     * view setter (dev zone article: http://devzone.zend.com/article/3412)
     */
    public function setView(Zend_View_Interface $view)
    {
        $this->view = $view;
    }

    /**
     * This is the sorting function used to sort the result set.
     * It compares the titles of the resources in $a and $b as returned by the TitleHelper
     *
     * @param $a the first row to compare
     * @param $b the second row to compare
     * @return int as needed by usort
     */
    private function _cmpTitles ($a, $b, $orderBy = 'resourceUri')
    {
        $titleA = '';
        $titleB = '';

        if (isset($a[$orderBy])) {
            if (!Erfurt_Uri::check($a[$orderBy])) {
                $titleA    = $a[$orderBy];
            } else {
                $titleA    = $this->_titleHelper->getTitle($a[$orderBy]);
            }
        }

        if (isset($b[$orderBy])) {
            if (!Erfurt_Uri::check($b[$orderBy])) {
                $titleB    = $b[$orderBy];
            } else {
                $titleB    = $this->_titleHelper->getTitle($b[$orderBy]);
            }
        }

        return strcasecmp($titleA, $titleB);
    }
}
