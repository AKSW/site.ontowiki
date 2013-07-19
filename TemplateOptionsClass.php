<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * An utility class for templateOptions template helper.
 *
 * @category   OntoWiki
 * @package    OntoWiki_extensions_components_site
 */
class TemplateOptionsClass
{
    const DATASET_SPECIFIC_OPTION  = 0;
    const CLASS_SPECIFIC_OPTION    = 1;
    const RESOURCE_SPECIFIC_OPTION = 2;

    /**
     * Template options
     * @var array
     */
    protected $_options = array();

    public function __construct($resourceUri)
    {
        $owApp = OntoWiki::getInstance();
        $store = $owApp->erfurt->getStore();
        $model = $owApp->selectedModel;
        $query = new Erfurt_Sparql_Query2();

        $resource = new Erfurt_Sparql_Query2_IriRef($resourceUri);

        $query->addProjectionVar($key   = new Erfurt_Sparql_Query2_Var('key'));
        $query->addProjectionVar($value = new Erfurt_Sparql_Query2_Var('value'));
        $query->addProjectionVar($class = new Erfurt_Sparql_Query2_Var('class'));
        $query->addProjectionVar($dataset = new Erfurt_Sparql_Query2_Var('dataset'));

        $union = new Erfurt_Sparql_Query2_GroupOrUnionGraphPattern();

        // resource options
        $group = new Erfurt_Sparql_Query2_GroupGraphPattern();
        $group->addTriple($resource, $key, $value);
        $union->addElement($group);

        // class options
        $group = new Erfurt_Sparql_Query2_GroupGraphPattern();
        $group->addTriple($resource, EF_RDF_TYPE, $class);
        $group->addTriple($class, $key, $value);
        $union->addElement($group);

        // dataset options
        $group = new Erfurt_Sparql_Query2_GroupGraphPattern();
        $group->addTriple($dataset, EF_RDF_TYPE, new Erfurt_Sparql_Query2_IriRef(EF_OWL_ONTOLOGY));
        $group->addTriple($dataset, $key, $value);
        $union->addElement($group);

        $query->addElement($union);

        $query->addTriple($key, EF_RDF_TYPE, new Erfurt_Sparql_Query2_IriRef('http://ns.ontowiki.net/SysOnt/Site/TemplateOption'));

        $results = $model->sparqlQuery($query);
        foreach ($results as $result) {
            if (!is_null($result[$dataset->getName()])) {
                $priority = static::DATASET_SPECIFIC_OPTION;
            } elseif (!is_null($result[$class->getName()])) {
                $priority = static::CLASS_SPECIFIC_OPTION;
            } else {
                $priority = static::RESOURCE_SPECIFIC_OPTION;
            }

            $this->_options[$result[$key->getName()]][$priority][] = $result[$value->getName()];
        }

        foreach (array_keys($this->_options) as $key) {
            krsort($this->_options[$key]);
        }
    }

    public function getArray($key)
    {
        $key = "http://schema.eccenca.com/StarPages/$key";

        if (isset($this->_options[$key])) {
            if ($this->_options[$key]) {
                return reset($this->_options[$key]);
            }
        }

        return array();
    }

    public function getValue($key, $default = null)
    {
        if ($array = $this->getArray($key)) {
            return reset($array);
        }

        return $default;
    }

    /**
     * Same as getValue(), with comma separated values exploded to an array
     */
    public function getValueAsArray($key, $default = '')
    {
        $value = $this->getValue($key, $default);
        return $value ? preg_split('/\s*,\s*/', $value) : array();
    }
}
