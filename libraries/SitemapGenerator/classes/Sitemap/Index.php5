<?php
class Sitemap_Index{

	protected $sitemaps		= array();
	protected $maxUrls		= 50000;
	
	public function __construct( $maxUrls = 50000 ){
		$this->maxUrls	= $maxUrls;
	}
	
	public function addSitemap( Sitemap $sitemap ){
		if( !$sitemap->getUrl() )
			throw new Exception( 'Sitemaps needs to have an URL to be indexable' );
		if( $this->maxUrls && count( $sitemap ) > $this->maxUrls )
			throw new OutOfBoundsException( 'Sitemap has more than '.$this->maxUrls.' URLs and needs to be spitted' );
		$this->sitemaps[]	= $sitemap;
	}
	
	public function getSitemaps(){
		return $this->sitemaps;
	}

	public function render(){
		$tree	= new XML_Node( 'sitemapindex' );
		$tree->setAttribute( 'xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9' );
		foreach( $this->sitemaps as $sitemap ){
			$node	= new XML_Node( 'sitemap');
			$node->addChild( new XML_Node( 'loc', $sitemap->getUrl() ) );
			if( $sitemap->getDatetime() ){
				$node->addChild( new XML_Node( 'lastmod', $sitemap->getDatetime() ) );
			}
			$tree->addChild( $node );
		}
		$builder	= new XML_Builder();
		return $builder->build( $tree );
	}

	public function save( $fileName ){
		$xml	= $this->render();
		file_put_contents( $fileName, $xml );
	}

	public function setMaxUrls( $number ){
		if( $number < 0 || $number > 50000 )
			throw new OutOfBoundsException( 'URL limit must at least 0 and atmost 50000' );
	}
}
?>
