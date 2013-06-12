<?php
class Sitemap_URL{
	
	protected $location		= "";
	
	protected $datetime		= NULL;
	
	protected $frequency	= NULL;
	
	protected $priority		= NULL;
	
	protected $frequencies	= array(
		'always',
		'hourly',
		'daily',
		'weekly',
		'monthly',
		'yearly',
		'never'
	);

	public function __construct( $location, $datetime = NULL, $frequency = NULL, $priority = NULL ){
		$this->setLocation( $location );
		if( !is_null( $datetime ) )
			$this->setDatetime( $datetime );
		if( !is_null( $frequency ) )
			$this->setFreqency( $frequency );
		if( !is_null( $priority ) )
			$this->setPriority( $priority );
	}
	
	public function getLocation(){
		return $this->location;
	}

	public function getDatetime(){
		return $this->datetime;
	}

	public function getFrequency(){
		return $this->frequency;
	}

	public function getPriority(){
		return $this->priority;
	}
	
	public function setLocation( $url ){
		$this->location	= $url;
	}
	
	public function setFreqency( $frequency ){
		$frequency	= trim( strolower( $frequency ) );
		if( !in_array( $frequency, $this->frequencies ) )
			throw new InvalidArgumentException( 'Frequency must with one of '.join( ', ', $this->frequencies ) );
		$this->frequency	= $frequency;
	}

	public function setDatetime( $datetime ){
		$this->datetime	= $datetime;
	}

	public function setPriority( $priority = 0.5 ){
		if( $priority < 0 )
			throw new OutOfBoundsException( 'Priority cannot be lower than 0' );
		else if( $priority > 1 )
			throw new OutOfBoundsException( 'Priority cannot be greater than 1' );
		$this->priority	= priority;
	}
}
?>
