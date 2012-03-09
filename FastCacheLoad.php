<?php
/**
 * @copyright Copyright (c) 2012, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * Site Extension Fast Cache Loader
 *
 * this files parses the config ini generates the cache id and directly
 * looks for the object cache and outputs it. put this to your htacess:
 *
 * php_value auto_prepend_file 'extensions/site/FastCacheLoad.php'
 *
 *
 * @category OntoWiki
 * @author Sebastian Tramp <tramp@informatik.uni-leipzig.de>
 */

function GetCachedContent_ZendDB($cacheId, $config)
{
    $dbuser = $config['store.zenddb.username'];
    $dbpass = $config['store.zenddb.password'];
    $dbname = $config['store.zenddb.dbname'];
    $dbhost = 'localhost';

    $link = mysql_connect($dbhost, $dbuser, $dbpass);
    if (!$link) {
        exit('Could not connect: ' . mysql_error());
    }
    $selectedDatabase = mysql_select_db($dbname, $link);
    if (!$selectedDatabase) {
        exit('Can\'t use foo : ' . mysql_error());
    }

    $query = 'SELECT content FROM `ef_cache` WHERE id = "' . $cacheId . '"';
    // Perform Query
    $result = mysql_query($query);

    // Check result
    if ($result && (mysql_num_rows($result) == 1)) {
        $result  = mysql_fetch_row($result);
        $content = unserialize($result[0]);

        return $content;
    }
}

function GetCachedContent_Virtuoso($cacheId, $config)
{
    $virtUser = $config['store.virtuoso.username'];
    $virtPass = $config['store.virtuoso.password'];
    $virtDsn  = $config['store.virtuoso.dsn'];

    $connection = @odbc_connect($virtDsn, $virtUser, $virtPass);
    if ($connection == false) {
        // Could not connect
        return;
    }

    $query = "SELECT content
        FROM DB.DBA.ef_cache
        WHERE id = '$cacheId'";

    $resultId = odbc_exec($connection, $query);
    if ($resultId == false) {
        // Erroneous query
        return;
    }

    if (odbc_num_rows($resultId) == 1) {
        $serialized = '';
        while ($segment = odbc_result($resultId, 1)) {
            $serialized .= (string)$segment;
        }
        return unserialize($serialized);
    }
}

function GetCacheContent ($id)
{
    $config  = parse_ini_file('config.ini');
    $backend = $config['store.backend'];

    $content = null;
    switch ($backend) {
    case 'zenddb':
        $content = GetCachedContent_ZendDB($id, $config);
        break;
    case 'virtuoso':
        $content = GetCachedContent_Virtuoso($id, $config);
        break;
    default:
        // nothing to do
    }
    return $content;
}

// prepare the cacheid and fetch the cache content
$siteModuleObjectCacheIdSource = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$siteModuleObjectCacheId       = 'site_' . md5($siteModuleObjectCacheIdSource);
$content = GetCacheContent($siteModuleObjectCacheId);

// Cache hit: send response and exit
if ($content) {
    echo $content;
    exit;
}

