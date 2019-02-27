<?php

namespace ZerosDev;

use Exception;

class Captcha
{
	protected $id = null;
	protected $bufferData = null;
	protected $error = null;
	protected $lastSessionKey = '';
	protected $width = 170;
	protected $height = 50;
	protected $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
	protected $captchaLength = 6;

	/**
	*	
	*	Initializing captcha
	*	
	*	@param Length of Captcha $length
	*	@param Captcha Character list $chars
	*	@return void
	*
	**/

	public function __construct()
	{
		$this->font = dirname(__DIR__).'/data/fonts/Arimo-Bold.ttf';

		/** Check if font is exists **/
		if( !file_exists($this->font) || !is_file($this->font) ) {
			$this->error = 'Font file is not found!';
		}

		/** Start session if not started **/
		if( session_status() !== PHP_SESSION_ACTIVE )
		{
			if( !headers_sent() )
			{
				session_start();
			}
			else
			{
				$this->error = 'Session can not be started';
			}
		}
	}

	public function chars($chars)
	{
		$this->chars = $chars;
		return $this;
	}

	public function length($length)
	{
		$this->captchaLength = $length;
		return $this;
	}

	public function size($width, $height)
	{
		$this->width = intval($width);
		$this->height = intval($height);
		return $this;
	}

	/**
	*	
	*	Generating captcha
	*	
	*	@return boolean
	*
	**/

	public function generate()
	{
		try
		{
			if( $this->isError() ) {
				throw new Exception($this->error());
			}

			$image = imagecreatetruecolor($this->width, $this->height);
			$background_color = imagecolorallocate($image, 255, 255, 255);  
			imagefilledrectangle($image, 0, 0, $this->width, $this->height, $background_color);
			$line_color = imagecolorallocate($image, 64,64,64);

			for($i=0; $i<10; $i++)
			{
				imageline($image, 0, rand()%$this->height, $this->width, rand()%$this->height, $line_color);
			}

			$pixel_color = imagecolorallocate($image, 0, 0, 255);

			for($i=0; $i<1000; $i++)
			{
				imagesetpixel($image, rand()%$this->width, rand()%$this->height, $pixel_color);
			}

			$len = strlen($this->chars);
			$text_color = imagecolorallocate($image, 0,0,0);
			$shadow_color = $grey = imagecolorallocate($image, 128, 128, 128);
			$word = '';

			for($i=0; $i < $this->captchaLength; $i++)
			{
				$angle = mt_rand(-4, 4);
				$r = $i > 0 ? mt_rand(5, 12) : 0;
				$sizeStart = (($this->height/2)-5);
				$sizeEnd = (($this->height/2)+5);
				$font_size = mt_rand($sizeStart, $sizeEnd);
				$letter = $this->chars[mt_rand(0, $len-1)];
				imagettftext($image, $font_size, ($angle*$i), 18+($i*25)-$r, 35, $shadow_color, $this->font, $letter);
				imagettftext($image, $font_size, ($angle*$i), 18+($i*25)-$r, 35, $text_color, $this->font, $letter);
				$word .= $letter;
			}

			$word = str_replace(' ', '', $word);

			ob_start();
			imagepng($image);
			imagedestroy($image);

			$this->bufferData = ob_get_clean();
			$this->id = uniqid().time();
			$this->lastSessionKey = '_'.$this->id;
			$cd = isset($_SESSION['captcha']) ? json_decode($_SESSION['captcha'], true) : [];

			if( count($cd) >= 10 )
			{
				$_SESSION['captcha'] = '{}';
				$cd = [];
			}

			$cd[$this->lastSessionKey] = $word;
			$sessionValue = json_encode($cd);
			$_SESSION['captcha'] = $sessionValue;

			return $this;
		}
		catch(Exception $e)
		{
			$this->error = $e->getMessage();

			return $this;
		}
	}

	/**
	*	
	*	Get captcha image
	*	
	*	@return string of generated base64 image
	*
	**/

	public function getImage()
	{
		if( $this->isError() ) {
			return null;
		}

		return 'data:image/png;base64, ' . base64_encode($this->bufferData);
	}

	/**
	*	
	*	Get captcha id
	*	
	*	@return string of captcha generation id
	*
	**/

	public function getId()
	{
		if( $this->isError() ) {
			return null;
		}

		return $this->id;
	}

	/**
	*	
	*	Validating captcha
	*	
	*	@param Captcha ID $id
	*	@param Captcha Code $captcha
	*	@return boolean
	*
	**/

	public function validate($id, $captcha)
	{
		if( $this->isError() ) {
			return false;
		}

		$cd = isset($_SESSION['captcha']) ? $_SESSION['captcha'] : null;
		if( !empty($cd) )
		{
			$list = json_decode($cd, true);
			$key = '_'.$id;
			if( isset($list[$key]) )
			{
				$result = hash_equals($list[$key], $captcha) ? true : false;
				
				if( $result )
				{
				    unset($list[$key]);
				    $sessionValue = json_encode($list);
			        $_SESSION['captcha'] = $sessionValue;
				}
				
				return $result;
			}
		}

		return false;
	}

	/**
	*	
	*	Check if there is an error occured
	*	
	*	@return boolean
	*
	**/

	public function isError()
	{
		return !empty($this->error) ? true : false;
	}

	/**
	*	
	*	Get error message
	*	
	*	@return string of error message
	*
	**/

	public function error()
	{
		if( !$this->isError() ) {
			return '';
		}

		return $this->error;
	}
}