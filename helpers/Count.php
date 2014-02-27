<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2011, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * OntoWiki query view helper
 *
 * returns a query result list rendered by a specific template
 * this helper is usable as {{query ...}} markup in combination with
 * ExecuteHelperMarkup
 *
 * @category OntoWiki
 * @package  OntoWiki_extensions_components_site
 */
class Site_View_Helper_Count extends Zend_View_Helper_Abstract implements Site_View_Helper_MarkupInterface
{
    /*
     * current view, injected with setView from Zend
     */
    public $view;

    /*
     * the main query method, mentioned parameters are:
     * - where
     * - template
     * - limit
     * - prefix
     * - suffix
     */
    public function count($options = array())
    {
        $this->templateData = $this->view->getHelper('Renderx')->templateData;
        $store              = OntoWiki::getInstance()->erfurt->getStore();
        $model              = OntoWiki::getInstance()->selectedModel;

        // check for options and assign local vars or null
        $select   = (isset($options['select']))   ? $options['select']   : 'count(distinct ?resourceUri) as ?resourceUri';
        $where    = (isset($options['where']))    ? $options['where']    : '?resourceUri a foaf:Project.';
        $limit    = (isset($options['limit']))    ? $options['limit']    : 100;
        $prefix   = (isset($options['prefix']))   ? $options['prefix']   : '';
        $suffix   = (isset($options['suffix']))   ? $options['suffix']   : '';
        $orderby  = (isset($options['orderby']))  ? $options['orderby']  : null;

        // build the query including PREFIX declarations
        $query = '';
        foreach ($model->getNamespaces() as $ns => $usedPrefix) {
            $query .= 'PREFIX ' . $usedPrefix . ': <' . $ns . '>' . PHP_EOL;
        }
        $query .= 'SELECT ' . $select . ' WHERE {' . PHP_EOL;
        $query .= $where . PHP_EOL;
        $query .= 'FILTER (!isBLANK(?resourceUri))' . PHP_EOL;
        $query .= '}' . PHP_EOL;
        if ($orderby !== null) {
            $query .= 'ORDER BY ' . $orderby . PHP_EOL;
        }
        $query .= 'LIMIT ' . $limit . PHP_EOL;

        // prepare the result string
        try {
            $result = $model->sparqlQuery($query);
        } catch (Exception $e) {
            // executions failed (return nothing)
            return $e->getMessage();
        }

        $resultString = '';
        // pre-fill the title helper
        foreach ($result as $row) {
            foreach ($row as $value) {
                $resultString .= $value;
            }
        }

        if ($resultString != '') {
            $resultString = $prefix . $resultString . $suffix;
        }
        return $resultString;
    }

    /*
     * view setter (dev zone article: http://devzone.zend.com/article/3412)
     */
    public function setView(Zend_View_Interface $view)
    {
        $this->view = $view;
    }
}
