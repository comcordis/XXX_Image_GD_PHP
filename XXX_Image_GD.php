<?php

// TODO abstract scale and crop calculations

class XXX_Image_GD
{
	const CLASS_NAME = 'XXX_Image_GD';
	
	protected $originalType = IMAGETYPE_JPEG;
	
	protected $resource;
	
	protected $error = false;
	
	public function __construct ($file)
	{
		$resource = $this->makeResourceFromFile($file);
		
		if (!$resource)
		{
			$this->resource = imagecreate(120, 120);
			
			/*
			$this->error = array
			(
				'description' => 'Invalid file: "' . $file . '"',
				'invoker' => array(self::CLASS_NAME, '__construct')
			);*/
		}
		else
		{
			$this->resource = $resource;
		}
	}
	
	public static function generateCaptcha ($captcha = '')
	{
		$fontPath = XXX_Path_Local::extendPath(XXX_Path_Local::composeOtherProjectDeploymentSourcePathPrefix('PHP_XXX_Image_GD'), array('fonts'));
		
		$fonts = array('arial.ttf', 'verdana.ttf', 'times.ttf');
		
		$image = imagecreate(70, 35);
		$backgroundColor = imagecolorallocate($image, 255, 255, 255);
				
		$leftSpacer = 10;
		
		for($i = 0; $i < 3; ++$i)
		{     
			$character = $captcha[$i];
			
			// Colors
			$characterColor = imagecolorallocate($image, rand(51, 204) + rand(-51, 51), rand(51, 204) + rand(-51, 51), rand(51, 204) + rand(-51, 51));
			
			$glow = rand(204, 255);
			$glowColor = imagecolorallocate($image, $glow, $glow, $glow);
		
			$shade = rand(0, 51);
			$shadeColor = imagecolorallocate($image, $shade, $shade, $shade);
			
			// Size
			$characterSize = rand(18, 22);
			$glowSize = $characterSize + rand(-2, 2);
			$shadeSize = $characterSize + rand(-2, 2);
			
			// Angle
			$characterAngle = rand(-25, 25);
			$glowAngle = $characterAngle + rand(-2, 2);
			$shadeAngle = $characterAngle + rand(-2, 2);
		
			// Top
			$characterTop = 25;
			$glowTop = $characterTop + rand(-2, 2);
			$shadeTop = $characterTop + rand(-2, 2);
			
			$characterLeft = $leftSpacer;
			$glowLeft = $characterLeft + rand(-2, 2);
			$shadeLeft = $characterLeft + rand(-2, 2);
		
			// Font
			$characterFont = $fontPath . $fonts[array_rand($fonts)];
			$glowFont = $fontPath . $fonts[array_rand($fonts)];
			$shadeFont = $fontPath . $fonts[array_rand($fonts)];
			
			if (rand(0, 1))
			{
				// Shade
				imagettftext($image, $shadeSize, $shadeAngle, $shadeLeft, $shadeTop, $shadeColor, $shadeFont, $character); 
				// Glow
				imagettftext($image, $glowSize, $glowAngle, $glowLeft, $glowTop, $glowColor, $glowFont, $character);
			}
			else
			{
				// Glow
				imagettftext($image, $glowSize, $glowAngle, $glowLeft, $glowTop, $glowColor, $glowFont, $character);
				// Shade
				imagettftext($image, $shadeSize, $shadeAngle, $shadeLeft, $shadeTop, $shadeColor, $shadeFont, $character); 
			}
			
			// Character
			imagettftext($image, $characterSize, $characterAngle, $characterLeft, $characterTop, $characterColor, $characterFont, $character); 
			
			// Left
			$leftSpacer += 18;
		}
		
		// Output
		XXX_HTTPServer_Client_Output::sendHeader('Content-type: image/png');
		imagepng($image);
		
		imagedestroy($image);
	}
	
	public function pixel ($red, $green, $blue)
	{
		return array
		(
			($red > 255) ? 255 : (($red < 0) ? 0 : (int) $red),
			($green > 255) ? 255 : (($green < 0) ? 0 : (int) $green),
			($blue > 255) ? 255 : (($blue < 0) ? 0 : (int) $blue)
		);
	}
	
	
	////////////////////////////////////////////////////////////////////////////////////////////////////
	// Alpha blending:
	//
	// imagealphablending
	//
	// true: The result pixel will be an opaque pixel based on a calculation of the source pixel alpha and the destination pixel
	// false: The resulting pixel will be a transparent pixel copied from the source pixel
	//
	// ----------
	//
	// Color transparent:
	// 
	// imagecolortransparent
	//
	// Define a color as transparent
	//
	// ----------
	//
	// Save alpha
	//
	// imagesavealpha
	//
	// Attempt to save full alpha channel information (as opposed to single-color transparency) when saving PNG images
	// (You have to unset alphablending to use it)
	//
	////////////////////////////////////////////////////////////////////////////////////////////////////
	
	public function scale ($desiredWidth = 100, $desiredHeight = 100, $type = 'maximum', $enlargeSmallerOriginal = false, $saveAlpha = false)
	{
		$originalWidth = imagesx($this->resource);
		$originalHeight = imagesy($this->resource);
		
		$tempDimensions = XXX_Calculate::getScaledRectangleSize($originalWidth, $originalHeight, $desiredWidth, $desiredHeight, $type, $enlargeSmallerOriginal);
	
		$tempWidth = $tempDimensions['width'];
		$tempHeight = $tempDimensions['height'];
		
		$tempResource = imagecreatetruecolor($tempWidth, $tempHeight);
		
		$this->preserveTransparency($this->resource, $tempResource);
		
		imagecopyresampled($tempResource, $this->resource, 0, 0, 0, 0, $tempWidth, $tempHeight, $originalWidth, $originalHeight);
		
		$this->resource = $tempResource;
	}
	
	public function resize ($desiredWidth = 100, $desiredHeight = 100, $saveAlpha = false)
	{
		$originalWidth = imagesx($this->resource);
		$originalHeight = imagesy($this->resource);
		
		$tempResource = imagecreatetruecolor($desiredWidth, $desiredHeight);
		
		$this->preserveTransparency($this->resource, $tempResource);
		
		imagecopyresampled($tempResource, $this->resource, 0, 0, 0, 0, $desiredWidth, $desiredHeight, $originalWidth, $originalHeight);
		
		$this->resource = $tempResource;
	}
	
	public static function calculateCropPosition ($originalWidth, $originalHeight, $desiredWidth = 100, $desiredHeight = 100, $horizontalAlignmentPercentage = 50, $verticalAlignmentPercentage = 50)
	{		
		if (!XXX_Type::isInteger($verticalAlignmentPercentage) || $verticalAlignmentPercentage < 0 || $verticalAlignmentPercentage > 100)
		{
			$verticalAlignmentPercentage = 50;
		}
		if (!XXX_Type::isInteger($horizontalAlignmentPercentage) || $horizontalAlignmentPercentage < 0 || $horizontalAlignmentPercentage > 100)
		{
			$horizontalAlignmentPercentage = 50;
		}
		
		$wideEnough = ($originalWidth >= $desiredWidth);
		$highEnough = ($originalHeight >= $desiredHeight);
		
		// Cropping can only be done if the original area at least overlaps the cropping area...
		if ($wideEnough && $highEnough)
		{		
			$x = XXX_Number::floor((($originalWidth - $desiredWidth) / 100) * $horizontalAlignmentPercentage);
			$y = XXX_Number::floor((($originalHeight - $desiredHeight) / 100) * $verticalAlignmentPercentage);
			
			$result = array
			(
				'x' => $x,
				'y' => $y
			);
		}
		else
		{
			$result = false;
			$this->error = array
			(
				'description' => 'The image is too small to crop, the cropping dimensions exceed the image dimensions',
				'invoker' => array(self::CLASS_NAME, 'calculateCropPosition')
			);
		}
		
		return $result;
	}
	
	public function crop ($desiredWidth = 0, $desiredHeight = 0, $horizontalAlignmentPercentage = 50, $verticalAlignmentPercentage = 50, $saveAlpha = false)
	{		
		$originalWidth = imagesx($this->resource);
		$originalHeight = imagesy($this->resource);
		
		$wideEnough = ($originalWidth >= $desiredWidth);
		$highEnough = ($originalHeight >= $desiredHeight);
		
		if (!($wideEnough && $highEnough))
		{
			$result = false;
			$this->error = array
			(
				'description' => 'The image is too small to crop, the cropping dimensions exceed the image dimensions',
				'invoker' => array(self::CLASS_NAME, 'crop')
			);
		}
		else
		{
			$tempPosition = $this->calculateCropPosition($originalWidth, $originalHeight, $desiredWidth, $desiredHeight, $horizontalAlignmentPercentage, $verticalAlignmentPercentage);
			
			$tempX = $tempPosition['x'];
			$tempY = $tempPosition['y'];
			
			//echo '|' . $originalWidth . 'x' . $originalHeight . '|' . $horizontalAlignmentPercentage . ', ' . $verticalAlignmentPercentage . '|' . $tempX . ', ' . $tempY . '|';
			
			$tempResource = imagecreatetruecolor($desiredWidth, $desiredHeight);
			
			$this->preserveTransparency($this->resource, $tempResource);
			
			imagecopyresampled($tempResource, $this->resource, 0, 0, $tempX, $tempY, $desiredWidth, $desiredHeight, $desiredWidth, $desiredHeight);
			
			$this->resource = $tempResource;
		}
	}
	
	public function cropByPixels ($desiredX = 0, $desiredY = 0, $desiredWidth = 0, $desiredHeight = 0, $saveAlpha = false)
	{
		$originalWidth = imagesx($this->resource);
		$originalHeight = imagesy($this->resource);
		
		$wideEnough = ($originalWidth >= $desiredWidth);
		$highEnough = ($originalHeight >= $desiredHeight);
		
		if (!($wideEnough && $highEnough))
		{
			$result = false;
			$this->error = array
			(
				'description' => 'The image is too small to crop, the cropping dimensions exceed the image dimensions',
				'invoker' => array(self::CLASS_NAME, 'crop')
			);
		}
		else
		{
			$tempResource = imagecreatetruecolor($desiredWidth, $desiredHeight);
			
			$this->preserveTransparency($this->resource, $tempResource);
			
			imagecopyresampled($tempResource, $this->resource, 0, 0, $desiredX, $desiredY, $desiredWidth, $desiredHeight, $desiredWidth, $desiredHeight);
			
			$this->resource = $tempResource;
		}
	}
	
	public function overlap ($overlappingImage, $alpha = 100, $horizontalAlignmentPercentage = 100, $verticalAlignmentPercentage = 100)
	{
		if (!XXX_Type::isInteger($alpha) || $alpha < 0 || $alpha > 100)
		{
			$alpha = 100;
		}
		if (!XXX_Type::isInteger($verticalAlignmentPercentage) || $verticalAlignmentPercentage < 0 || $verticalAlignmentPercentage > 100)
		{
			$verticalAlignmentPercentage = 50;
		}
		if (!XXX_Type::isInteger($horizontalAlignmentPercentage) || $horizontalAlignmentPercentage < 0 || $horizontalAlignmentPercentage > 100)
		{
			$horizontalAlignmentPercentage = 50;
		}
		
		$originalWidth = imagesx($this->resource);
		$originalHeight = imagesy($this->resource);
		
		$overlappingImageResource = $overlappingImage->getResource();
		
		$this->preserveTransparency($overlappingImageResource, $this->resource);
		
		$overlappingImageWidth = $overlappingImage->getWidth();
		$overlappingImageHeight = $overlappingImage->getHeight();
		
		$tempX = XXX_Number::floor((($originalWidth - $overlappingImageWidth) / 100) * $horizontalAlignmentPercentage);
		$tempY = XXX_Number::floor((($originalHeight - $overlappingImageHeight) / 100) * $verticalAlignmentPercentage);
		
		imagecopymerge($this->resource, $overlappingImageResource, $tempX, $tempY, 0, 0, $overlappingImageWidth, $overlappingImageHeight, $alpha);
	}
	
	public function overlapByPixels ($overlappingImage, $alpha = 100, $desiredX = 0, $desiredY = 0)
	{
		if (!XXX_Type::isInteger($alpha) || $alpha < 0 || $alpha > 100)
		{
			$alpha = 100;
		}
		
		if (!XXX_Type::isInteger($desiredX))
		{
			$desiredX = 0;
		}
		if (!XXX_Type::isInteger($desiredY))
		{
			$desiredY = 0;
		}
		
		$originalWidth = imagesx($this->resource);
		$originalHeight = imagesy($this->resource);
		
		$overlappingImageResource = $overlappingImage->getResource();
		
		$this->preserveTransparency($overlappingImageResource, $this->resource);
		
		$overlappingImageWidth = $overlappingImage->getWidth();
		$overlappingImageHeight = $overlappingImage->getHeight();
		
		imagecopymerge($this->resource, $overlappingImageResource, $desiredX, $desiredY, 0, 0, $overlappingImageWidth, $overlappingImageHeight, $alpha);
	}
	
	public function watermark ($watermarkImage, $horizontalAlignmentPercentage = 100, $verticalAlignmentPercentage = 100)
	{
		if (!XXX_Type::isInteger($verticalAlignmentPercentage) || $verticalAlignmentPercentage < 0 || $verticalAlignmentPercentage > 100)
		{
			$verticalAlignmentPercentage = 50;
		}
		if (!XXX_Type::isInteger($horizontalAlignmentPercentage) || $horizontalAlignmentPercentage < 0 || $horizontalAlignmentPercentage > 100)
		{
			$horizontalAlignmentPercentage = 50;
		}
		
		$originalWidth = imagesx($this->resource);
		$originalHeight = imagesy($this->resource);
		
		$watermarkImageResource = $watermarkImage->getResource();
		
		$watermarkImageWidth = $watermarkImage->getWidth();
		$watermarkImageHeight = $watermarkImage->getHeight();
		
		$tempX = XXX_Number::floor((($originalWidth - $watermarkImageWidth) / 100) * $horizontalAlignmentPercentage);
		$tempY = XXX_Number::floor((($originalHeight - $watermarkImageHeight) / 100) * $verticalAlignmentPercentage);
		
		imagecopyresampled($this->resource, $watermarkImageResource, $tempX, $tempY, 0, 0, $watermarkImageWidth, $watermarkImageHeight, $watermarkImageWidth, $watermarkImageHeight);
	}
		
	// $rotation = 1 (90), 2 (180), 3 (270)
	public function rotate ($rotation = 1)
	{
		switch ($rotation)
		{
			case 3:
				$degrees = 270;
				break;
			case 2:
				$degrees = 180;
				break;
			case 1:
			default:
				$degrees = 90;
				break;
		}
		
		$this->resource = imagerotate($this->resource, $degrees, 0);
	}
	
	// $degrees = 0 - 359
	public function rotateFree ($degrees = 180)
	{
		if (!XXX_Type::isInteger($degrees) || $degrees < 0 || $degrees > 359)
		{
			$degrees = 180;
		}
		
		$this->resource = imagerotate($this->resource, $degrees, 0);
	}
	
	public function processImageEditorEditingInformation ($imageEditorEditingInformation = '')
	{
		$imageEditorEditingInformation = XXX_String_JSON::decode($imageEditorEditingInformation);
		
		// 1. Rotate
		$rotation = XXX_Type::makeInteger($imageEditorEditingInformation['image']['rotation']);
		
		switch ($rotation)
		{
			case 0:
				break;
			case 1:
				$this->rotate(3);
				break;
			case 2:
				$this->rotate(2);
				break;
			case 3:
				$this->rotate(1);
				break;
		}
		
		// 2. Flip
		$flip = XXX_Type::makeInteger($imageEditorEditingInformation['image']['flip']);
		
		switch ($flip)
		{
			case 0:
				break;
			case 1:
				$this->manipulate('mirror', 'horizontal');
				break;
			case 2:
				$this->manipulate('mirror', 'vertical');
				break;
			case 3:
				$this->manipulate('mirror', 'diagonal');
				break;
		}
		
		// 3. Resize
		$imageWidth = XXX_Type::makeInteger($imageEditorEditingInformation['image']['width']);
		$imageHeight = XXX_Type::makeInteger($imageEditorEditingInformation['image']['height']);
		
		$this->resize($imageWidth, $imageHeight);
		
		// 4. Crop
		$croppingAreaX = XXX_Type::makeInteger($imageEditorEditingInformation['croppingArea']['x']);
		$croppingAreaY = XXX_Type::makeInteger($imageEditorEditingInformation['croppingArea']['y']);
		
		$croppingAreaWidth = XXX_Type::makeInteger($imageEditorEditingInformation['croppingArea']['width']);
		$croppingAreaHeight = XXX_Type::makeInteger($imageEditorEditingInformation['croppingArea']['height']);
				
		$this->cropByPixels($croppingAreaX, $croppingAreaY, $croppingAreaWidth, $croppingAreaHeight);
		
	}
	
	//////////////////////////////////////////////////
	
	public function manipulate ($operation, $parameter = false)
	{
		$width = imagesx($this->resource);
		$height = imagesy($this->resource);
		
		$tempResource = imagecreatetruecolor($width, $height);
				
		switch ($operation)
		{
			// 'horizontal' | 'vertical' | 'diagonal' (default)
			case 'mirror':
				if ($parameter == 'horizontal')
				{
					for ($x = 0; $x < $width; ++$x)
					{
						for ($y = 0; $y < $height; ++$y)
						{							
							$rgb = imagecolorat($this->resource, ($width - 1) - $x, $y);							
							imagesetpixel($tempResource, $x, $y, imagecolorallocate($this->resource, ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF));
						}
					}
				}
				elseif ($parameter == 'vertical')
				{
					for ($x = 0; $x < $width; ++$x)
					{
						for ($y = 0; $y < $height; ++$y)
						{
							$rgb = imagecolorat($this->resource, $x, ($height - 1) - $y);
							imagesetpixel($tempResource, $x, $y, imagecolorallocate($this->resource, ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF));
						}
					}
				}
				// Diagonal
				else
				{
					for ($x = 0; $x < $width; ++$x)
					{
						for ($y = 0; $y < $height; ++$y)
						{
							$rgb = imagecolorat($this->resource, ($width - 1) - $x, ($height - 1) - $y);
							imagesetpixel($tempResource, $x, $y, imagecolorallocate($this->resource, ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF));
						}
					}
				}
				break;
			// 0 = none/dark, 1 = normal, 1 > more contrast
			case 'contrast':
				$averageLuminance = $this->getAverageLuminance();
				for ($x = 0; $x < $width; ++$x)
				{
					for ($y = 0; $y < $height; ++$y)
					{
						$rgb = imagecolorat($this->resource, $x, $y);
						
						$pixel = $this->pixel
						(
							(($rgb >> 16) & 0xFF) * $parameter + (1 - $parameter) * $averageLuminance,
							(($rgb >> 8) & 0xFF) * $parameter + (1 - $parameter) * $averageLuminance,
							($rgb & 0xFF) * $parameter + (1 - $parameter) * $averageLuminance
						);
						
						imagesetpixel($tempResource, $x, $y, imagecolorallocate($this->resource, $pixel[0], $pixel[1], $pixel[2]));
					}	
				}
				break;
			// 0 = none/dark, 1 = normal, 1 > more brightness
			case 'brightness':
				for ($x = 0; $x < $width; ++$x)
				{
					for ($y = 0; $y < $height; ++$y)
					{						
						$rgb = imagecolorat($this->resource, $x, $y);
						
						$pixel = $this->pixel
						(
							(($rgb >> 16) & 0xFF) * $parameter,
							(($rgb >> 8) & 0xFF) * $parameter,
							($rgb & 0xFF) * $parameter
						);
						imagesetpixel($tempResource, $x, $y, imagecolorallocate($this->resource, $pixel[0], $pixel[1], $pixel[2]));
					}
				}
				break;
			// 0 = none/white, 1 = normal, 1 > more dark
			case 'gamma':
				for ($x = 0; $x < $width; ++$x)
				{
					for ($y = 0; $y < $height; ++$y)
					{						
						$rgb = imagecolorat($this->resource, $x, $y);
						
						$pixel = $this->pixel
						(
							XXX_Number::power((($rgb >> 16) & 0xFF) / 255, $parameter) * 255,
							XXX_Number::power((($rgb >> 8) & 0xFF) / 255, $parameter) * 255,
							XXX_Number::power(($rgb & 0xFF) / 255, $parameter) * 255
						);
						
						imagesetpixel($tempResource, $x, $y, imagecolorallocate($this->resource, $pixel[0], $pixel[1], $pixel[2]));
					}
				}
				break;
			// 'rgb' | 'gbr' | 'brg' etc.
			case 'swapColors':
				for ($x = 0; $x < $width; ++$x)
				{
					for ($y = 0; $y < $height; ++$y)
					{
						$rgb = imagecolorat($this->resource, $x, $y);
						
						$red = $tempRed = ($rgb >> 16) & 0xFF;
						$green = $tempGreen = ($rgb >> 8) & 0xFF;
						$blue = $tempBlue = $rgb & 0xFF;
						
						$firstChannel = XXX_String::getPart($parameter, 0, 1);
						$secondChannel = XXX_String::getPart($parameter, 1, 1);
						$thirdChannel = XXX_String::getPart($parameter, 2, 1);
						
						switch ($firstChannel)
						{
							case 'g':
								$red = $tempGreen;
								break;
							case 'b':
								$red = $tempBlue;
								break;
						}
						
						switch ($secondChannel)
						{
							case 'r':
								$green = $tempRed;
								break;
							case 'b':
								$green = $tempBlue;
								break;
						}
						
						switch ($thirdChannel)
						{
							case 'r':
								$blue = $tempRed;
								break;
							case 'g':
								$blue = $tempGreen;
								break;
						}
															
						imagesetpixel($tempResource, $x, $y, imagecolorallocate($this->resource, $red, $green, $blue));
					}
				}
				break;
			// 'r' | 'g' | 'b' | 'rb' | 'br' | 'rg' | 'gr' | 'bg' | 'gb'
			case 'maximizeColor':
				$channelValue = 255;
			// 'r' | 'g' | 'b' | 'rb' | 'br' | 'rg' | 'gr' | 'bg' | 'gb'
			case 'removeColor':
				if (!XXX_Type::isValue($channelValue))
				{
					$channelValue = 0;
				}
				for ($x = 0; $x < $width; ++$x)
				{
					for ($y = 0; $y < $height; ++$y)
					{
						$rgb = imagecolorat($this->resource, $x, $y);
						
						$red = ($rgb >> 16) & 0xFF;
						$green = ($rgb >> 8) & 0xFF;
						$blue = $rgb & 0xFF;
						
						switch ($parameter)
						{
							case 'r':
								$red = $channelValue;
								break;
							case 'g':
								$green = $channelValue;
								break;
							case 'b':
								$blue = $channelValue;
								break;			
							case 'rb':
							case 'br':
								$red = $blue = $channelValue;
								break;
							case 'rg':
							case 'gr':
								$red = $green = $channelValue;
								break;			
							case 'bg':
							case 'gb':
								$green = $blue = $channelValue;
								break;
						}
						
						imagesetpixel($tempResource, $x, $y, imagecolorallocate($this->resource, $red, $green, $blue));
					}
				}
				break;
			// array(red 0 - 1, green 0 - 1, blue 0 - 1)
			case 'selectiveColor':
				list($red, $green, $blue) = $parameter;
				$red = XXX_Number::lowest(1, $red);
				$green = XXX_Number::lowest(1, $green);
				$blue = XXX_Number::lowest(1, $blue);
				
				for ($x = 0; $x < $width; ++$x)
				{
					for ($y = 0; $y < $height; ++$y)
					{						
						$rgb = imagecolorat($this->resource, $x, $y);
						
						imagesetpixel($tempResource, $x, $y, imagecolorallocate
						(
							$this->resource,
							round($red * (($rgb >> 16) & 0xFF)),
							round($green * (($rgb >> 8) & 0xFF)),
							round($blue * ($rgb & 0xFF))
						));
					}
				}
				break;
			// pixels spacing
			case 'dotGrid':
				for ($x = 0; $x < $width; ++$x)
				{
					for ($y = 0; $y < $height; ++$y)
					{	
						$rgb = imagecolorat($this->resource, $x, $y);
						
						if ($x % $parameter == 0 && $y % $parameter == 0)
						{
							$rgb = 0;
						}
						
						imagesetpixel($tempResource, $x, $y, imagecolorallocate
						(
							$this->resource,
							(($rgb >> 16) & 0xFF),
							(($rgb >> 8) & 0xFF),
							($rgb & 0xFF)
						));
					}
				}
				break;
			case 'negate':
				for ($x = 0; $x < $width; ++$x)
				{
					for ($y = 0; $y < $height; ++$y)
					{	
						$rgb = imagecolorat($this->resource, $x, $y);
						
						imagesetpixel($tempResource, $x, $y, imagecolorallocate
						(
							$this->resource,
							255 - (($rgb >> 8) & 0xFF),
							255 - (($rgb >> 16) & 0xFF),
							255 - ($rgb & 0xFF)
						));
					}
				}
				break;
			case 'greyscale':
				for ($x = 0; $x < $width; ++$x)
				{
					for ($y = 0; $y < $height; ++$y)
					{
						$rgb = imagecolorat($this->resource, $x, $y);
						$pixelAverage = XXX_Number::round(((($rgb >> 16) & 0xFF) + (($rgb >> 8) & 0xFF) + ($rgb & 0xFF)) / 3);
						imagesetpixel($tempResource, $x, $y, imagecolorallocate($this->resource, $pixelAverage, $pixelAverage, $pixelAverage));
					}
				}
				break;
			case 'desaturate':
				for ($x = 0; $x < $width; ++$x)
				{
					for ($y = 0; $y < $height; ++$y)
					{
						$rgb = imagecolorat($this->resource, $x, $y);
						$yiqY = XXX_Number::round(((($rgb >> 16) & 0xFF) * 0.299) + ((($rgb >> 8) & 0xFF) * 0.587) + (($rgb & 0xFF) * 0.114));
						imagesetpixel($tempResource, $x, $y, imagecolorallocate($this->resource, $yiqY, $yiqY, $yiqY));						
					}
				}
				break;
			// 0 (white) - 1 (black) 
			case 'blackAndWhite':
				for ($x = 0; $x < $width; ++$x)
				{
					for ($y = 0; $y < $height; ++$y)
					{						
						$rgb = imagecolorat($this->resource, $x, $y);
						
						$red = (($rgb >> 16) & 0xFF);
						$green = (($rgb >> 8) & 0xFF);
						$blue = ($rgb & 0xFF);						
						
						$pixelTotal = ($red + $green + $blue);
		
						if ($pixelTotal  > (765 * $parameter))
						{
							$red = $green = $blue = 255;
						}
						else
						{
							$red = $green = $blue = 0;
						}
						
						imagesetpixel($tempResource, $x, $y, imagecolorallocate($this->resource, $red, $green, $blue));
					}
				}
				break;
			case 'sepia':
				for ($x = 0; $x < $width; ++$x)
				{
					for ($y = 0; $y < $height; ++$y)
					{						
						$rgb = imagecolorat($this->resource, $x, $y);
						
						$red = (($rgb >> 16) & 0xFF);
						$green = (($rgb >> 8) & 0xFF);
						$blue = ($rgb & 0xFF);
						
						imagesetpixel($tempResource, $x, $y, imagecolorallocate
						(
							$this->resource, 
							XXX_Number::round(($red * 0.393 + $green * 0.769 + $blue * 0.189) / 1.351),
							XXX_Number::round(($red * 0.349 + $green * 0.686 + $blue * 0.168) / 1.203),
							XXX_Number::round(($red * 0.272 + $green * 0.534 + $blue * 0.131) / 2.140)
						));
					}
				}
				break;
			// 0 (Normal) - 2.55 (Almost white)
			case 'clipping':
				$parameter = XXX_Number::round(100 * $parameter);
				for ($x = 0; $x < $width; ++$x)
				{
					for ($y = 0; $y < $height; ++$y)
					{						
						$rgb = imagecolorat($this->resource, $x, $y);
						
						$red = (($rgb >> 16) & 0xFF);
						$green = (($rgb >> 8) & 0xFF);
						$blue = ($rgb & 0xFF);
						
						if ($red > 255 - $parameter)
						{
							$red = 255;
						}
						elseif ($red < $parameter)
						{
							$red = 0;
						}
						
						if ($green > 255 - $parameter)
						{
							$green = 255;
						}
						elseif ($green < $parameter)
						{
							$green = 0;
						}
						
						if ($blue > 255 - $parameter)
						{
							$blue = 255;
						}
						elseif ($blue < $parameter)
						{
							$blue = 0;
						}
						
						imagesetpixel($tempResource, $x, $y, imagecolorallocate($this->resource, $red, $green, $blue));
					}
				}
				break;
			// 0 (Normal) - 5.2 (Full noise)
			case 'noise':
				$parameter  = XXX_Number::round(100 * $parameter);
				for ($x = 0; $x < $width; ++$x)
				{
					for ($y = 0; $y < $height; ++$y)
					{
						$rgb = imagecolorat($this->resource, $x, $y);
						
						$random = mt_rand(-$parameter, $parameter);
						$pixel = $this->pixel
						(
							(($rgb >> 16) & 0xFF) + $random,
							(($rgb >> 8) & 0xFF) + $random,
							($rgb & 0xFF) + $random
						);
						
						imagesetpixel($tempResource, $x, $y, imagecolorallocate($this->resource, $pixel[0], $pixel[1], $pixel[2]));
					}
				}
				break;
			// 0 (Few) - 1 (Lots)
			case 'saltAndPepper':
				$black = XXX_Number::round(100 - (98 * $parameter));
				$white = $black - 2;
				
				for ($x = 0; $x < $width; ++$x)
				{
					for ($y = 0; $y < $height; ++$y)
					{
						$rgb = imagecolorat($this->resource, $x, $y);
						$pixel = $this->pixel
						(
							(($rgb >> 16) & 0xFF),
							(($rgb >> 8) & 0xFF),
							($rgb & 0xFF)
						);
				
						$random = mt_rand(0, ceil(100 - 98 * $parameter));
				
						$channel = false;
				
						if ($random == $black)
						{
							$channel = 0;
						}
						if ($random == $white)
						{
							$channel = 255;
						}
						
						if (XXX_Type::isInteger($channel))
						{
							$pixel = $this->pixel
							(
								$channel,
								$channel,
								$channel
							);
						}
						
						imagesetpixel($tempResource, $x, $y, imagecolorallocate($this->resource, $pixel[0], $pixel[1], $pixel[2]));
					}
				}
				break;
			// 0 (Normal) - 100 (Very blurred) > 
			case 'convolutionBlur':
				$tempResource =& $this->resource;
				$matrix = array
				(
					array(1, 2, 1),
					array(2, 4, 2),
					array(1, 2, 1)
				);
				
				$divisor = 16;
				
				for ($i = 0; $i < $parameter; ++$i)
				{
					imageconvolution($tempResource, $matrix, $divisor, 0);				
				}		
				break;
			case 'filterGreyscale':
				$tempResource =& $this->resource;
				imagefilter($tempResource, IMG_FILTER_GRAYSCALE);
				break;
			case 'filterNegate':
				$tempResource =& $this->resource;
				imagefilter($tempResource, IMG_FILTER_NEGATE);
				break;
			// none
			case 'filterEdgeDetect':
				$tempResource =& $this->resource;
				imagefilter($tempResource, IMG_FILTER_EDGEDETECT);
				break;
			// none
			case 'filterEmboss':
				$tempResource =& $this->resource;
				imagefilter($tempResource, IMG_FILTER_EMBOSS);
				break;			
			// -255 - 0: darken, 0 - 255: lighten
			case 'filterBrightness':
				$tempResource =& $this->resource;
				imagefilter($tempResource, IMG_FILTER_BRIGHTNESS, $parameter);
				break;
			// < 0: the more - the higher the contrast, 0 - 100 the higher, the less contrast
			case 'filterContrast':
				$tempResource =& $this->resource;
				imagefilter($tempResource, IMG_FILTER_CONTRAST, $parameter);
				break;
			case 'filterGaussianBlur':
				$tempResource =& $this->resource;
				imagefilter($tempResource, IMG_FILTER_GAUSSIAN_BLUR);
				break;
			case 'filterSelectiveBlur':
				$tempResource =& $this->resource;
				imagefilter($tempResource, IMG_FILTER_SELECTIVE_BLUR);
			// 0 = smooth, 25 = normal
			case 'filterSmooth':
				$tempResource =& $this->resource;
				imagefilter($tempResource, IMG_FILTER_SMOOTH, $parameter);
				break;
			case 'filterMeanRemoval':
				$tempResource =& $this->resource;
				imagefilter($tempResource, IMG_FILTER_MEAN_REMOVAL, 20);
				break;
			// array(red 0 - 255, green 0 - 255, blue 0 - 255)
			case 'filterColorize':
				list($red, $green, $blue) = $parameter;
				$tempResource =& $this->resource;
				imagefilter($tempResource, IMG_FILTER_COLORIZE, $red, $green, $blue);
				break;
			// 0 = normal, 10 (Very blurry) >
			case 'blur':		
				$radius = XXX_Number::round(XXX_Number::highest(0, XXX_Number::lowest($parameter, 50)) * 2);
				if (!$radius) {
					return false;
				}
				// Gaussian blur matrix:
				//    1    2    1
				//    2    4    2
				//    1    2    1
	
				// Move copies of the image around one pixel at the time and merge them with weight
				// according to the matrix. The same matrix is simply repeated for higher radii.
				for ($i = 0; $i < $radius; $i++)
				{
					imagecopy($tempResource, $this->resource, 0, 0, 1, 1, $width - 1, $height - 1); // up left
					imagecopymerge($tempResource, $this->resource, 1, 1, 0, 0, $width, $height, 50.00000); // down right
					imagecopymerge($tempResource, $this->resource, 0, 1, 1, 0, $width - 1, $height, 33.33333); // down left
					imagecopymerge($tempResource, $this->resource, 1, 0, 0, 1, $width, $height - 1, 25.00000); // up right
					imagecopymerge($tempResource, $this->resource, 0, 0, 1, 0, $width - 1, $height, 33.33333); // left
					imagecopymerge($tempResource, $this->resource, 1, 0, 0, 0, $width, $height, 25.00000); // right
					imagecopymerge($tempResource, $this->resource, 0, 0, 0, 1, $width, $height - 1, 20.00000); // up
					imagecopymerge($tempResource, $this->resource, 0, 1, 0, 0, $width, $height, 16.666667); // down
					imagecopymerge($tempResource, $this->resource, 0, 0, 0, 0, $width, $height, 50.000000); // center
					imagecopy($this->resource, $tempResource, 0, 0, 0, 0, $width, $height);
				}
		}
		
		$this->resource = $tempResource;
	}
		
	//////////////////////////////////////////////////
	
	protected function getPixelRgb ($x, $y)
	{
		$rgb = imagecolorat($this->resource, $x, $y);
		
		return array
		(
			($rgb >> 16) & 0xFF,
			($rgb >> 8) & 0xFF,
			$rgb & 0xFF
		);
	}
	
	protected function getAverageLuminance ()
    {
       	$luminanceTotal = 0;

        $width = imagesx($this->resource);
        $height = imagesy($this->resource);
		
        for ($x = 0; $x < $width; ++$x)
		{
            for ($y = 0; $y < $height; ++$y)
			{			   
			    $rgb = imagecolorat($this->resource, $x, $y);
				
				$luminanceTotal += XXX_Number::round(((($rgb >> 16) & 0xFF) * 0.299) + ((($rgb >> 8) & 0xFF) * 0.587) + (($rgb & 0xFF) * 0.114));
            }
        }

        $pixelTotal = ($width * $height);
		
		return ($luminanceTotal / $pixelTotal);
    }
		
	//////////////////////////////////////////////////
	
	public static function getExifData ($file)
	{
		$file = XXX_Path_Local::normalizePath($file);
		
		$result = array();
		
		if (function_exists('exif_read_data'))
		{
			$result = exif_read_data($file, 0, true, false);
		}
		
		return $result;
	}
	
	public static function getInformation ($file)
	{
		$result = false;
		
		$file = XXX_Path_Local::normalizePath($file);
		
		if (XXX_FileSystem_Local::doesFileExist($file, false))
		{
			$imageInfo = getimagesize($file);
					
			$width = $imageInfo[0];
			$height = $imageInfo[1];
			$imageType = $imageInfo[2];
			$mimeType = $imageInfo['mime'];
			
			$validImageTypes = array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG);
			
			if (XXX_Array::hasValue($validImageTypes, $imageType) && $width > 0 && $height > 0)
			{
				$animated = false;
				
				if ($imageType == IMAGETYPE_GIF)
				{
					$animated = isGifAnimated($file);
				}
				
				$type = 'jpg';
				
				switch ($imageType)
				{
					case IMAGETYPE_JPEG:
						$type = 'jpg';
						break;
					case IMAGETYPE_GIF:
						$type = 'gif';
						break;
					case IMAGETYPE_PNG:
						$type = 'png';
						break;
				}
				
				$result = array
				(
				 	'type' => $type,
					'width' => $width,
					'height' => $height,
					'mimeType' => $mimeType,
					'animated' => $animated
				);
			}
		}
		
		return $result;
	}	
	
	protected function makeResourceFromFile ($file)
	{
		$result = false;
		
		$file = XXX_Path_Local::normalizePath($file);
				
		if (@get_resource_type($file) == 'gd')
		{
			$result = $file;
		}
		else if (XXX_FileSystem_Local::doesFileExist($file))
		{
			$imageInfo = getimagesize($file);
					
			$width = $imageInfo[0];
			$height = $imageInfo[1];
			$imageType = $imageInfo[2];
			
			$validImageTypes = array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG);
			
			if (XXX_Array::hasValue($validImageTypes, $imageType) && $width > 0 && $height > 0)
			{					
				$this->originalType = $imageType;
				
				switch ($imageType)
				{
					case IMAGETYPE_GIF:
						if (!isGifAnimated($file))
						{
							$result = imagecreatefromgif($file);
							$this->preserveTransparency($result, $result);
						}
						break;
					case IMAGETYPE_JPEG:
						$result = imagecreatefromjpeg($file);
						break;
					case IMAGETYPE_PNG:
						$result = imagecreatefrompng($file);
						$this->preserveTransparency($result, $result);
						break;
				}
			}
		}
		
		return $result;
	}
	
	protected function preserveTransparency ($sourceResource, $destinationResource)
	{		
		$sourceType = $this->originalType;
		
		// Transparency only available for GIFs & PNGs
		if ($sourceType == IMAGETYPE_GIF)
		{
			$transparentIndex = imagecolortransparent($sourceResource);
			if ($transparentIndex >= 0)
			{
				imagepalettecopy($sourceResource, $destinationResource);
				imagefill($destinationResource, 0, 0, $transparentIndex);
				imagecolortransparent($destinationResource, $transparentIndex);
				imagetruecolortopalette($destinationResource, true, 256);
			}
		}
		else if (($sourceType == IMAGETYPE_GIF) || ($sourceType == IMAGETYPE_PNG))
		{
			$transparentIndex = imagecolortransparent($sourceResource);
		
			// If we have a specific transparent color
			if ($transparentIndex >= 0)
			{
				// Get the original image's transparent color's RGB values
				$transparentColor = imagecolorsforindex($sourceResource, $transparentIndex);
		
				// Allocate the same color in the new image resource
				$transparentIndex = imagecolorallocate($destinationResource, $transparentColor['red'], $transparentColor['green'], $transparentColor['blue']);
		
				// Completely fill the background of the new image with allocated color.
				imagefill($destinationResource, 0, 0, $transparentIndex);
				
				// Set the background color for new image to transparent
				imagecolortransparent($destinationResource, $transparentIndex);		
			}
			// Always make a transparent background color for PNGs that don't have one allocated already
			else if ($sourceType == IMAGETYPE_PNG)
			{
				// Turn off transparency blending (temporarily)
				imagealphablending($destinationResource, false);
				
				// Create a new transparent color for image
				$transparentColor = imagecolorallocatealpha($destinationResource, 0, 0, 0, 127);
				
				// Completely fill the background of the new image with allocated color.
				imagefill($destinationResource, 0, 0, $transparentColor);
				
				// Restore transparency blending
				imagesavealpha($destinationResource, true);
			}
		}
	}
	
	//////////////////////////////////////////////////
	
	public function saveAsJpg ($file, $quality = 80)
	{
		$file = XXX_Path_Local::normalizePath($file);
		
		return imagejpeg($this->resource, $file, $quality);
	}
	
	public function saveAsGif ($file)
	{
		$file = XXX_Path_Local::normalizePath($file);
		
		return imagegif($this->resource, $file);
	}
	
	public function saveAsPng ($file)
	{
		$file = XXX_Path_Local::normalizePath($file);
		
		return imagepng($this->resource, $file);
	}
	
	//////////////////////////////////////////////////
	
	public function showJpg ($quality = 80)
	{
		XXX_HTTPServer_Client_Output::sendHeader('Content-type: image/jpeg');
		imagejpeg($this->resource, null, $quality);
	}
	
	public function showGif ()
	{
		XXX_HTTPServer_Client_Output::sendHeader('Content-type: image/gif');
		imagegif($this->resource);
	}
	
	public function showPng ()
	{
		XXX_HTTPServer_Client_Output::sendHeader('Content-type: image/png');
		imagepng($this->resource);
	}
	
	//////////////////////////////////////////////////
	
	public function getResource ()
	{
		return $this->resource;
	}
	
	public function getWidth ()
	{
		return imagesx($this->resource);
	}
	
	public function getHeight ()
	{
		return imagesy($this->resource);
	}
	
	//////////////////////////////////////////////////
	
	public function __destruct ()
	{
		$this->destroy();
	}
	
	public function destroy ()
	{
		if (is_resource($this->resource))
		{
			@imagedestroy($this->resource);
		}
	}
	
	//////////////////////////////////////////////////
	
	public function isOK ()
	{
		$result = true;
		
		if ($this->error)
		{
			$result = false;
		}
		
		return $result;
	}
	
	public function getError ()
	{
		$result = false;
		
		if ($this->error)
		{
			$result = $this->error;
			unset($this->error);
		}
		
		return $result;
	}
}

function imagepalettetotruecolor (& $resource)
{
	if (!imageistruecolor($resource))
	{
		$width = imagesx($resource);
		$height = imagesy($resource);
		$tempResource = imagecreatetruecolor($width, $height);
		imagecopy($tempResource, $resource, 0, 0, 0, 0, $width, $height);
		$resource = $tempResource;
	}
}

function imagecreatefrombmp ($file)
{	
	if(!$sourceFile = fopen($file, 'rb')) //Open the file in "read binary" mode, otherwise return false
	{
		return false;
	}
	// Unpack formats
	// Big Endian = Most important byte first
	// Little Endian = Least important byte first
	// v	unsigned short (always 16 bit, little endian byte order) 
	// V	unsigned long (always 32 bit, little endian byte order)
	// n	unsigned short (always 16 bit, big endian byte order)
	// / 	seperator
	
	// Load the file headers into file
	$file = unpack('vfileType/VfileSize/Vreserved/VbitmapOffset', fread($sourceFile, 14)); 
	
	// Signature needs to be "BM", otherwise return false
	if($file['fileType'] != 19778) 
	{
		return false;
	}
		
	$bmp = unpack('VheaderSize/Vwidth/Vheight/vplanes/vbitsPerPixel/Vcompression/VimageSize/VhorizontalResolution/VverticalResolution/VcolorsUsed/VcolorsImportant', fread($sourceFile, 40)); // Load the bmp headers into bmp
	
	// If no size is specified, calculate it by extracting the headers of the total file size
	if($bmp['imageSize'] == 0) 
	{
		$bmp['imageSize'] = ($file['fileSize'] - $file['bitmapOffset']);
	}
	
	// Exponential expression e.g. pow(2, 8) = 256 as in 2^8
	$bmp['colors'] = pow(2, $bmp['bitsPerPixel']); 
	// 1 Byte = 8 Bits	
	$bmp['bytesPerPixel'] = ($bmp['bitsPerPixel'] / 8); 
	$bmp['lineRemainder'] = ($bmp['width'] * $bmp['bytesPerPixel'] / 4);
	$bmp['lineRemainder'] -= floor($bmp['lineRemainder']);
	$bmp['lineRemainder'] = 4 - (4 * $bmp['lineRemainder']);
	
	if($bmp['lineRemainder'] == 4)
	{
		$bmp['lineRemainder'] = 0;
	}
	
	if($bmp['colors'] < 16777216)
	{
		// Load the palet colors
		$palette = unpack('V'.$bmp['colors'], fread($sourceFile, ($bmp['colors'] * 4))); 
	}
	
	$null = chr(0);
	
	// Get the binary image source	
	$bitmapSource = fread($sourceFile, $bmp['imageSize']); 
	// Create the output image
	$resultImage = imagecreatetruecolor($bmp['width'], $bmp['height']); 
	$pixelPointer = 0;
	// Start at bottom
	$y = ($bmp['height'] - 1); 
	// Walk trough lines bottom to top
	while($y >= 0) 
	{
		// Reset x for each line iteration so it starts at the left
		$x = 0; 
		// Walk trough pixels left to right
		while ($x < $bmp['width']) 
		{
			switch($bmp['bitsPerPixel'])
			{
				// 16.777.216 Colors (2^8)^3,  8 bits = 1 byte so per 8
				case 24: 
					$color = unpack('V', XXX_String::getPart($bitmapSource, $pixelPointer, 3).$null);
				break;
				// 65.535 Colors (2^8)^2
				case 16: 
					$color = unpack('n', XXX_String::getPart($bitmapSource, $pixelPointer, 2));
					$color[1] = $palette[$color[1] + 1];
				break;
				//  256 Colors 2^8
				case 8: 
					$color = unpack('n', $null.XXX_String::getPart($bitmapSource, $pixelPointer, 1));
					$color[1] = $palette[$color[1] + 1];
				break;
				// 16 Colors 2^4
				case 4: 
					$color = unpack('n', $null.XXX_String::getPart($bitmapSource, XXX_Number::floor($pixelPointer), 1));
					$color[1] = (($pixelPointer * 2)%2 == 0) ? ($color[1] >> 4) : ($color[1] & 0x0F); // Hex. 0x0F = 15
					$color[1] = $palette[$color[1] + 1];
				break;
				// Monochrome 2 Colors (Black/White)
				case 1:
					$color = unpack('n', $null.XXX_String::getPart($bitmapSource, XXX_Number::floor($pixelPointer), 1));
					switch(($pixelPointer * 8) % 8)
					{
						case 0:
							$color[1] = $color[1] >> 7;
						break;
						// Hex. 0x40 = 64
						case 1:
							$color[1] = ($color[1] & 0x40) >> 6; 
						break;
						// Hex. 0x20 = 32
						case 2:
							$color[1] = ($color[1] & 0x20) >> 5; 
						break;
						// Hex. 0x10 = 16
						case 3:
							$color[1] = ($color[1] & 0x10) >> 4; 
						break;
						// Hex. 0x8 = 8
						case 4:
							$color[1] = ($color[1] & 0x8) >> 3; 
						break;
						// Hex. 0x4 = 4
						case 5:
							$color[1] = ($color[1] & 0x4) >> 2; 
						break;
						// Hex. 0x2 = 2
						case 6:
							$color[1] = ($color[1] & 0x2) >> 1; 
						break;
						// Hex. 0x1 = 1
						case 7:
							$color[1] = ($color[1] & 0x1);
						break;
					}
					$color[1] = $palette[$color[1] + 1];
				break;
				// If bitsPerPixel isn't an allowed value return false;
				default: 
					return false;
				break;
			}
			
			// Set the color of the pixel
			imagesetpixel($resultImage, $x, $y, $color[1]);
			$x++;
			
			// Move to the next pixel
			$pixelPointer += $bmp['bytesPerPixel']; 
		}
		
		$y--;
		
		// If any lineRemainder, add it to get to the next line properly
		$pixelPointer += $bmp['lineRemainder']; 
	}
	
	fclose($sourceFile);
	
	return $resultImage;
}

function isGifAnimated ($file)
{
	$result = false;
	
	$fileContents = file_get_contents($file);
	$stringPosition = 0;
	$frameCount = 0;
	
	// There is no point in continuing after we find a 2nd frame
	while($frameCount < 2)
	{
		// Hex. x00 = 0, x21 = 33, xF9 = 249, x04 = 4 (0|33|249|4)
		$temporaryStringPosition1 = XXX_String::findFirstPosition($fileContents, "\x00\x21\xF9\x04", $stringPosition); 
		if($temporaryStringPosition1 === false)
		{
			break;
		}
		else
		{
			// Hex. x00 = 0, x2C = 44 (0|44)
			$stringPosition = ($temporaryStringPosition1 + 1);
			$temporaryStringPosition2 = XXX_String::findFirstPosition($fileContents, "\x00\x2C", $stringPosition); 
			if($temporaryStringPosition2 === false)
			{
				break;
			}
			else
			{
				if(($temporaryStringPosition1 + 8) == $temporaryStringPosition2)
				{
					$frameCount++;
				}
				$stringPosition = ($temporaryStringPosition2 + 1);
			}
		}
	}	
	
	if($frameCount > 1)
	{
		$result = true;
	}
	
	return $result;
}

?>