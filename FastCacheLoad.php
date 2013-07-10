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
 * <Files "index.php">
 *     php_value auto_prepend_file 'extensions/site/FastCacheLoad.php'
 * </Files>
 *
 * @category OntoWiki
 * @author Sebastian Tramp <tramp@informatik.uni-leipzig.de>
 */

/*
 * only mysql supported
 */
function GetCachedContent_Database_ZendDB($cacheId, $config)
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

/*
 * fetch virtuoso based cache
 */
function GetCachedContent_Database_Virtuoso($cacheId, $config)
{
    $virtUser = $config['store.virtuoso.username'];
    $virtPass = $config['store.virtuoso.password'];
    $virtDsn  = $config['store.virtuoso.dsn'];

    $connection = @odbc_connect($virtDsn, $virtUser, $virtPass);
    if ($connection == false) {
        exit('Could not connect to virtuoso');
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
            $serialized .= (string) $segment;
        }
        return unserialize($serialized);
    }
}

function GetCachedContent_File($cacheId, $config)
{
    $prefix     = $config['cache.frontend.cache_id_prefix'];
    $path       = $config['cache.backend.file.cache_dir'];
    $fileName   = $path."zend_cache---".$prefix.$cacheId;
    if(file_exists($fileName)){
        return unserialize(file_get_contents($fileName));
    }
}

function GetCachedContent_Memcached($cacheId, $config)
{
    $host   = 'localhost';
    $port   = 11211;
    $prefix = $config['cache.frontend.cache_id_prefix'];
    if( isset( $config['cache.backend.memcached.servers.0.host'] ) )
        $host = $config['cache.backend.memcached.servers.0.host'];
    if( isset( $config['cache.backend.memcached.servers.0.port'] ) )
        $port = $config['cache.backend.memcached.servers.0.port'];

    if( !class_exists( 'Memcache' ) )
        exit( 'Missing memcache client extension' );

    $memcache = new Memcache;
    $memcache->addServer($host, $port);
    $result   = $memcache->get($prefix.$cacheId);
    if ($result){
        $content = unserialize($result[0]);
        if (is_string($content)){
            return $content;
        }
    }
}

function GetCacheContent ($id)
{
    $default = parse_ini_file('application/config/default.ini');
    $config  = array_merge( $default, parse_ini_file('config.ini') );

    $content = null;
    switch ($config['cache.backend.type']) {
        case 'file':
            $content = GetCachedContent_File($id, $config);
            break;
        case 'memcached':
            $content = GetCachedContent_Memcached($id, $config);
            break;
        case 'database':
            switch ($config['store.backend']) {
                case 'zenddb':
                    $content = GetCachedContent_Database_ZendDB($id, $config);
                    break;
                case 'virtuoso':
                    $content = GetCachedContent_Database_Virtuoso($id, $config);
                    break;
                default:
                    // nothing to do
            }
            break;
        case 'apc':
        case 'sqlite':
        default:
            // nothing to do
    }
    return $content;
}

// prepare the cacheid and fetch the cache content
if (preg_match('/\.html$/', $_SERVER['REQUEST_URI'])) {
    $siteModuleObjectCacheIdSource = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $siteModuleObjectCacheId       = 'site_' . md5($siteModuleObjectCacheIdSource);
    $content = GetCacheContent($siteModuleObjectCacheId);
    // Cache hit: send response and exit
    if ($content != null) {
        header("Content-length: ".strlen($content));
        echo $content;
        exit;
    }
}
