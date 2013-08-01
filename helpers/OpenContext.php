<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * OntoWiki OpenContext view helper
 *
 * returns the opening component of context
 * to which the contained markup will apply
 *
 * @category OntoWiki
 * @package  OntoWiki_extensions_components_site
 */
class Site_View_Helper_OpenContext extends Zend_View_Helper_Abstract implements Site_View_Helper_MarkupInterface
{
    private static $_itemref = array();

    /**
     * Opens a resource context in data markup.
     *
     * Options (all are optional):
     * - resource - resource URI (by default, the current resource in template)
     * - type     - resource type (by default, rdf:type value for the specified resource)
     * - rel      - relation to the resource
     * - rev      - reverse relation (from the resource)
     * - itemref  - microdata's itemref as an array of IDs
     * - tag      - HTML tag to create context with
     * - id       - HTML id attribute
     * - class    - HTML class attribute
     * - markup   - markup format ("RDFa", "microdata", false)
     *
     * The following options may be specified in template options:
     * rel, rev, itemref, tag, id, class
     */
    public function openContext($options = array())
    {
        $model    = OntoWiki::getInstance()->selectedModel;

        $tmplOpt  = $this->view->templateOptions();
        $markup   = $tmplOpt->getValue('http://ns.ontowiki.net/SysOnt/Site/dataMarkupFormat', 'RDFa');

        $markup   = isset($options['markup'])   ? $options['markup']   : $markup;
        $resource = isset($options['resource']) ? $options['resource'] : $this->view->resourceUri;

        $html     = array();
        $attr     = '';
        $iprefix  = '';
        $type     = null;
        $rel      = null;
        $rev      = null;
        $itemref  = null;

        // can generate new namespaces which weren't included in html element
        //$resource = $this->view->curie($resource);

        if (isset($options['type'])) {
            $type = $options['type'];
        } else {
            $query = new Erfurt_Sparql_Query2();
            $query->addProjectionVar($type = new Erfurt_Sparql_Query2_Var('type'));
            $query->addTriple(new Erfurt_Sparql_Query2_IriRef($resource),
                              new Erfurt_Sparql_Query2_IriRef(EF_RDF_TYPE),
                              $type);
            if ($types = $model->sparqlQuery($query)) {
                $type = $types[0]['type'];
            }
        }

        if (isset($options['rel'])) {
            $rel = $options['rel'];
        } elseif (isset($this->view->rel)) {
            $rel = $this->view->rel;
        }

        if (isset($options['rev'])) {
            $rev = $options['rev'];
        } elseif (isset($this->view->rev)) {
            $rev = $this->view->rev;
        }

        if (isset($options['itemref'])) {
            $itemref = $options['itemref'];
        } elseif (isset($this->view->itemref)) {
            $itemref = $this->view->itemref;
        }

        foreach (array('id', 'class') as $name) {
            if (isset($options[$name])) {
                $html[$name] = $options[$name];
            } elseif (isset($this->view->$name)) {
                $html[$name] = $this->view->$name;
            }
        }

        switch ($markup) {
            case 'RDFa':
                $attr .= " resource='$resource'";
                if ($type !== null) $attr .= " typeof='$type'";
                if ($rel  !== null) $attr .= " rel='$rel'";
                if ($rev  !== null) $attr .= " rev='$rev'";
            break;
            case 'microdata':
                if (!isset($html['id']) or !in_array($html['id'], static::$_itemref)) {
                    $attr .= " itemscope='itemscope'";
                    /* "The itemid attribute must not be specified on elements
                        that do not have both an itemscope attribute
                        and an itemtype attribute specified" */
                    if ($type !== null) $attr   .= " itemid='$resource' itemtype='$type'";
                    if ($rel  !== null) $attr   .= " itemprop='$rel'";
                    //if ($rev  !== null) $iprefix = "<link itemprop='$rev' href='#TODO'/>$iprefix";
                    if ($itemref) {
                        $itemrefValue = implode(' ', $itemref);
                        $attr .= " itemref='$itemrefValue'";

                        /* remember which elements are associated using itemref
                           so context markup can be disabled for them later */
                        static::$_itemref += $itemref;
                    }
                }
            break;
        }

        foreach ($html as $name => $value) {
            $attr .= " $name='$value'";
        }

        if (!isset($options['tag'])) {
            if (!isset($this->view->tag)) {
                $this->view->tag = 'span';
            }
            $tag = $this->view->tag;
        } else {
            $tag = $options['tag'];
        }

        // stack information for closecontext
        if (!isset($this->view->dataMarkupContextStack)) {
            $this->view->dataMarkupContextStack = array();
        }
        array_push($this->view->dataMarkupContextStack, array('tag' => $tag));

        return "<$tag$attr>$iprefix";
    }
}
