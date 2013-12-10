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
    const RESOURCE_SPECIFIC_OPTION = 1;
    const CLASS_SPECIFIC_OPTION    = 2;
    const DATASET_SPECIFIC_OPTION  = 3;
    const PRIORITY_LAST            = 4;

    const SITE_TEMPLATE_OPTION     = 'http://ns.ontowiki.net/SysOnt/Site/TemplateOption';
    const SITE_FETCH_OPTIONS_FROM  = 'http://ns.ontowiki.net/SysOnt/Site/fetchOptionsFrom';

    /**
     * Template options
     * @var array
     */
    protected $_options = array();

    /**
     * Mapping of local names to template option keys
     * @var array
     */
    protected $_optionLocalNames = array();

    public function __construct($resourceUri)
    {
        $owApp     = OntoWiki::getInstance();
        $store     = $owApp->erfurt->getStore();
        $model     = $owApp->selectedModel;
        $resource  = new Erfurt_Sparql_Query2_IriRef($resourceUri);
        $ontology  = new Erfurt_Sparql_Query2_IriRef(EF_OWL_ONTOLOGY);
        $fetchFrom = new Erfurt_Sparql_Query2_IriRef(static::SITE_FETCH_OPTIONS_FROM);

        // fetch options from this resource
        $query  = new Erfurt_Sparql_Query2();
        list($key, $value, $class, $dataset) = $this->_queryAddVars($query, array('key', 'value', 'class', 'dataset'));

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

        $query->addTriple($key, EF_RDF_TYPE, new Erfurt_Sparql_Query2_IriRef(static::SITE_TEMPLATE_OPTION));

        $results = $model->sparqlQuery($query);
        foreach ($results as $result) {
            $arrayKey = $result[$key->getName()];
            $this->_options[$arrayKey][$this->_getPriority($result)][] = $result[$value->getName()];

            $templateOption = new Erfurt_Rdf_Resource($arrayKey, $model);
            $arrayKeyLocalName = $templateOption->getLocalName();
            $this->_optionLocalNames[$arrayKeyLocalName][] = $arrayKey;
        }

        // fetch options from linked resources
        $query  = new Erfurt_Sparql_Query2();
        list($other, $class, $dataset) = $this->_queryAddVars($query, array('other', 'class', 'dataset'));

        $union = new Erfurt_Sparql_Query2_GroupOrUnionGraphPattern();

        // resource links
        $group = new Erfurt_Sparql_Query2_GroupGraphPattern();
        $group->addTriple($resource, $fetchFrom, $other);
        $union->addElement($group);

        // class links
        $group = new Erfurt_Sparql_Query2_GroupGraphPattern();
        $group->addTriple($resource, EF_RDF_TYPE, $class);
        $group->addTriple($class, $fetchFrom, $other);
        $union->addElement($group);

        // dataset links
        $group = new Erfurt_Sparql_Query2_GroupGraphPattern();
        $group->addTriple($dataset, EF_RDF_TYPE, $ontology);
        $group->addTriple($dataset, $fetchFrom, $other);
        $union->addElement($group);

        $query->addElement($union);

        $results = $model->sparqlQuery($query);
        foreach ($results as $result) {
            $options = new TemplateOptionsClass($result[$other->getName()]);
            foreach ($options->_options as $arrayKey => $priorities) {
                foreach ($priorities as $priority => $values) {
                    $this->_options[$arrayKey][(string)($this->_getPriority($result) + 0.1*$priority)] = $values;
                }
            }
            foreach ($options->_optionLocalNames as $arrayKeyLocalName => $keys) {
                foreach ($keys as $key) {
                    if (!isset($this->_optionLocalNames[$arrayKeyLocalName])
                        || !in_array($key, $this->_optionLocalNames[$arrayKeyLocalName])) {
                        $this->_optionLocalNames[$arrayKeyLocalName][] = $key;
                    }
                }
            }
        }

        foreach (array_keys($this->_options) as $key) {
            ksort($this->_options[$key]);
        }
    }

    private function _queryAddVars($query, $vars)
    {
        return array_map(function($name) use($query) {
            $query->addProjectionVar($var = new Erfurt_Sparql_Query2_Var($name));
            return $var;
        }, $vars);
    }

    private function _getPriority($result)
    {
        if (!is_null($result['dataset'])) {
            return static::DATASET_SPECIFIC_OPTION;
        } elseif (!is_null($result['class'])) {
            return static::CLASS_SPECIFIC_OPTION;
        } else {
            return static::RESOURCE_SPECIFIC_OPTION;
        }
    }

    /**
     * Returns an array with the template options in sub arrays sorted by priority
     * @param $key the key of the template option, either as URI or as localName
     * @return 2d-array with the template options
     */
    public function getArray($key)
    {
        if (!isset($this->_options[$key])) {
            if (isset($this->_optionLocalNames[$key])) {
                $keys = $this->_optionLocalNames[$key];
            } else {
                return array();
            }
        } else {
            $keys = array($key);
        }

        $options = array();
        foreach($keys as $optionKey) {
            if ($this->_options[$optionKey]) {
                $options = array_merge($options, $this->_options[$optionKey]);
            }
        }

        return $options;
    }

    /**
     * Returns the template options value with the highest priority
     * @param $key the key of the template option, either as URI or as localName
     * @param $default (optional) the optional default value if the template option is not set
     * @return the template options value
     */
    public function getValue($key, $default = null)
    {
        if ($array = $this->getArray($key)) {
            $values = reset($array);
            return reset($values);
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

    public function dump()
    {
        return print_r(array(
            'options'          => $this->_options,
            'optionLocalNames' => $this->_optionLocalNames,
        ), true);
    }
}
