<?php
require_once '../classes/Sitemap.php5';
require_once '../classes/Sitemap/URL.php5';
require_once '../classes/Sitemap/Index.php5';
require_once '../classes/XML/Node.php5';
require_once '../classes/XML/Builder.php5';

try{
	$sitemap	= new Sitemap();
	$sitemap->add( 'http://localhost/dev/0.8/SitemapGenerator/test1', '2012-12-24' );
	$sitemap->setUrl( 'http://localhost/dev/0.8/SitemapGenerator/get.php5?part=0' );
	#$sitemap->save( 'sitemap.xml.bz', 1, 10, 'bz' );
	#$sitemap->save( 'sitemap.xml.gz', 1, 10, 'gz' );

	$compression	= NULL;'gz';
	$maxMegabytes	= 0.001;
	$maxUrls		= 0;
	
	print( '<xmp>'.$sitemap->render( $maxUrls, $maxMegabytes, $compression ) ); 
	die;
	$index		= new Sitemap_Index();
	$index->addSitemap( $sitemap );

	print( '<xmp>'.$index->render() );

}
catch( Exception $e ){
	UI_HTML_Exception_Page::display( $e );
}
?>
