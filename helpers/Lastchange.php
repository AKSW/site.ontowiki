<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2011, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * OntoWiki Lastchange view helper
 *
 * returns metadata of the last change of a resource
 *
 * @category OntoWiki
 * @package  OntoWiki_extensions_components_site
 */
class Site_View_Helper_Lastchange extends Zend_View_Helper_Abstract
{
    public function lastchange($uri)
    {
        // TODO: fill this value with the erfurt versioning api
        $versioning = Erfurt_App::getInstance()->getVersioning();
        $history = $versioning->getLastModifiedForResource($uri, (string)OntoWiki::getInstance()->selectedModel);

        if (empty($history)) {
            return array(
                'resourceUri'   => $uri,
                'resourceTitle' => '',
                'timeStamp'     => '',
                'timeIso8601'   => '',
                'timeDuration'  => '',
                'userTitle'     => '',
                'userUri'       => '',
                'userHref'      => ''
            );
        }

        $th = new OntoWiki_Model_TitleHelper(OntoWiki::getInstance()->selectedModel);
        $th->addResource($history['useruri']);
        $th->addResource($uri);
        $return     = array();
        $userUrl    = new OntoWiki_Url(array('route' => 'properties'));
        $userUrl->setParam('r', $history['useruri']);
        $return['resourceUri'] = $uri;
        $return['resourceTitle'] = $th->getTitle($uri);
        $return['timeStamp'] = $history['tstamp']; //unix timestamp
        $return['timeIso8601'] = date('c', $history['tstamp']); // ISO 8601 format

        try {
            $return['timeDuration'] = OntoWiki_Utils::dateDifference($history['tstamp'], null, 3); // x days ago
        } catch (Exception $e) {
            $return['timeDuration'] = '';
        }

        $return['userTitle'] = $th->getTitle($history['useruri']);
        $return['userUri'] = $history['useruri'];
        $return['userHref'] = $userUrl; //use URI helper

        return $return;
    }
}
