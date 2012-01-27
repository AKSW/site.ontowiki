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

// @todo: fetch from config.ini
$ini = parse_ini_file('config.ini');

$host = 'localhost';;
$user = $ini['store.zenddb.username'];
$pass = $ini['store.zenddb.password'];
$database = $ini['store.zenddb.dbname'];

$link = mysql_connect($host, $user, $pass);
if (!$link) {
    die('Could not connect: ' . mysql_error());
}
$selectedDatabase = mysql_select_db($database, $link);
if (!$selectedDatabase) {
    die ('Can\'t use foo : ' . mysql_error());
}

$siteModuleObjectCacheIdSource = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$siteModuleObjectCacheId = 'site_' . md5($siteModuleObjectCacheIdSource);

$query = 'SELECT content FROM `ef_cache` WHERE id = "'.$siteModuleObjectCacheId.'"';
// Perform Query
$result = mysql_query($query);

// Check result
if ($result && (mysql_num_rows($result) == 1)) {
    $result = mysql_fetch_row($result);
    $content = unserialize($result[0]);
    echo $content;
    //die(microtime(true) - REQUEST_START);
    die();
}

