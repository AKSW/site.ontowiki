<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2006-2014, {@link http://aksw.org AKSW}
 * @license   http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * Tests the behavior of Literal helper.
 */
class LiteralHelperTest extends Erfurt_TestCase
{
    protected $_view = null;
    //protected $_modelUri = 'http://example.org/test/';

    public function setUp()
    {
        $owApp = OntoWiki::getInstance();
        $cm = $owApp->extensionManager;

        $this->_view = new OntoWiki_View();
        $this->_view->resourceUri = 'http://model.org/model#i1';

        $name = 'site';

        // set component specific helper path
        if ($hp = $cm->getComponentHelperPath($name)) {
            $this->_view->addHelperPath($hp, ucfirst($name) . '_View_Helper_');
        }

        parent::setUp();
    }

    public function testLiteralHelper()
    {
        // no markup
        $val = $this->_view->literal(array(
            'property' => 'http://model.org/prop',
            'value' => '[value]',
            'tag' => 'meta',
        ));
        $this->assertEquals($val, '<meta property="http://model.org/prop" content="[value]"/>');

        $val = $this->_view->literal(array(
            'property' => 'http://model.org/prop',
            'value' => array('value' => '[value]', 'type' => 'uri'),
            'tag' => 'link',
        ));
        $this->assertEquals($val, '<link rel="http://model.org/prop" href="[value]"/>');

        // RDFa
        $val = $this->_view->literal(array(
            'markup' => 'RDFa',
            'property' => 'http://model.org/prop',
            'value' => '[value]',
            'tag' => 'meta',
        ));
        $this->assertEquals($val, '<meta property="http://model.org/prop" content="[value]"/>');

        $val = $this->_view->literal(array(
            'markup' => 'RDFa',
            'property' => 'http://model.org/prop',
            'value' => array('value' => '[value]', 'type' => 'uri'),
            'tag' => 'link',
        ));
        $this->assertEquals($val, '<link rel="http://model.org/prop" href="[value]"/>');

        // microdata
        $val = $this->_view->literal(array(
            'markup' => 'microdata',
            'property' => 'http://model.org/prop',
            'value' => '[value]',
            'tag' => 'meta',
        ));
        $this->assertEquals($val, '<meta itemprop="http://model.org/prop" content="[value]"/>');

        $val = $this->_view->literal(array(
            'markup' => 'microdata',
            'property' => 'http://model.org/prop',
            'value' => array('value' => '[value]', 'type' => 'uri'),
            'tag' => 'link',
        ));
        $this->assertEquals($val, '<link itemprop="http://model.org/prop" href="[value]"/>');
    }
}
