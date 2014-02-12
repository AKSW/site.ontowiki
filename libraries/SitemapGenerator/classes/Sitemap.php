<?php
class Sitemap implements Countable{

	protected $url			= NULL;
	protected $urls			= array();
	protected $datetime		= NULL;
	protected $compressions	= array( 'bz', 'gz' );
	
	public function add( $location, $datetime = NULL, $frequency = NULL, $priority = NULL ){
		$this->addUrl( new Sitemap_URL( $location, $datetime, $frequency, $priority ) );
	}

	public function addUrl( Sitemap_URL $url ){
		foreach( $this->urls as $entry )
			if( $entry->getLocation() === $url->getLocation() )
				return FALSE;
		$this->urls[]	= $url;
		if( $url->getDatetime() ){
			if( !$this->datetime ){
				$this->datetime	= $url->getDatetime();
			}
			else{
				$timestamp	= strtotime( $url->getDatetime() );
				if( $timestamp > strtotime( $this->datetime ) ){
					$this->datetime	= $url->getDatetime();
				}
			}
		}
		return TRUE;
	}

	public function count(){
		return count( $this->urls );
	}
	
	public function getDatetime(){
		return $this->datetime;
	}

	public function getUrl(){
		return $this->url;
	}

	public function getUrls(){
		return $this->urls;
	}

	public function render( $maxUrls = NULL, $maxMegabytes = NULL, $compression = NULL ){
		if( $maxUrls && !count( $this ) > $maxUrls )
			throw new OutOfBoundsException( 'Sitemap has more than '.$maxUrls.' URLs and needs to be spitted' );

		$tree	= new XML_Node( 'urlset' );
		$tree->setAttribute( 'xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9' );
		foreach( $this->urls as $url ){
			$node	= new XML_Node( 'url' );
			$node->addChild( new XML_Node( 'loc', $url->getLocation() ) );
			if( ( $datetime = $url->getDatetime() ) ){
                $datetime   = date( 'c', strtotime( $datetime ) );
				$node->addChild( new XML_Node( 'lastmod', $datetime ) );
            }
			$tree->addChild( $node );
		}
		$builder	= new XML_Builder();
		$xml		= $builder->build( $tree );
		if( $compression ){
			if( !in_array( $compression, $this->compressions ) )
				throw new OutOfRangeException( 'Available compression types are '.join( ", ", $this->compressions ) );
			switch( $compression ){
				case 'bz':
					$xml	= bzcompress( $xml );
					break;
				case 'gz':
					$xml	= gzencode( $xml );
					break;
			}
		}
		if( $maxMegabytes && strlen( $xml ) > $maxMegabytes * 1024 * 1024 )
			throw new OutOfBoundsException( 'Rendered sitemap is to large (max: '.$maxMegabytes.' MB)' );
		return $xml;
	}

	public function save( $fileName, $maxUrls = NULL, $maxMegabytes = NULL, $compression = NULL ){
		$xml	= $this->render( $maxUrls, $maxMegabytes, $compression );
		file_put_contents( $fileName, $xml );
	}

	public function setDatetime( $datetime ){
		$this->datetime	= $datetime;
	}
	
	public function setUrl( $url ){
		$this->url	= $url;
	}
}
?>
