<?php

/**
 * Override the default posts functionality. We need to add some settings so the group data can be displayed
 * in the postbit
 */
class HDJuegos_WaterMarkAdd_ModelOverride_Attachment extends XFCP_HDJuegos_WaterMarkAdd_ModelOverride_Attachment
{

	/**
	 * Extend the insertUploadedAttachmentData functionality, to add a little watermark. It might
	 * be disabled by permissions (in which case we should not show it)
	 * @return array
	 **/
	
    public $WaterMarkedImage;   
 	
	public function insertUploadedAttachmentData(XenForo_Upload $file, $userId, array $extra = array())
	{
		if ($file->isImage()
			&& XenForo_Image_Abstract::canResize($file->getImageInfoField('width'), $file->getImageInfoField('height'))
		)
		{
			$dimensions = array(
				'width' => $file->getImageInfoField('width'),
				'height' => $file->getImageInfoField('height'),
			);
			
			$tempWaterMarkedFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
			
			$tempThumbFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
			
			if ($tempThumbFile)
			{
				$image = XenForo_Image_Abstract::createFromFile($file->getTempFile(), $file->getImageInfoField('type'));
				if ($image)
				{
					if ($image->thumbnail(XenForo_Application::get('options')->attachmentThumbnailDimensions))
					{
						$image->output($file->getImageInfoField('type'), $tempThumbFile);
					}
					else
					{
						copy($file->getTempFile(), $tempThumbFile); // no resize necessary, use the original
					}

					$dimensions['thumbnail_width'] = $image->getWidth();
					$dimensions['thumbnail_height'] = $image->getHeight();

					unset($image);
				}
			}
			if ($tempWaterMarkedFile)
			{
				$image = $this->createFromFile($file->getTempFile(), $file->getImageInfoField('type'));
				if ($image)
				{
					if ($this->addWatermark($image))
					{
						$this->outputImage($file->getImageInfoField('type'), $tempWaterMarkedFile);
					}
					else
					{
						copy($file->getTempFile(), $tempWaterMarkedFile);  // no resize necessary, use the original
					}

					

					unset($image);
				}
			}
		}
		else
		{
			$tempThumbFile = '';
			$tempWaterMarkedFile = '';
			$dimensions = array();
		}

		try
		{
			$dataDw = XenForo_DataWriter::create('XenForo_DataWriter_AttachmentData');
			$dataDw->bulkSet($extra);
			$dataDw->set('user_id', $userId);
			$dataDw->set('filename', $file->getFileName());
			$dataDw->bulkSet($dimensions);
			if ($tempWaterMarkedFile) {
			$dataDw->setExtraData(XenForo_DataWriter_AttachmentData::DATA_TEMP_FILE, $tempWaterMarkedFile);
			} else {
			$dataDw->setExtraData(XenForo_DataWriter_AttachmentData::DATA_TEMP_FILE, $file->getTempFile());
			}
			if ($tempThumbFile)
			{
				$dataDw->setExtraData(XenForo_DataWriter_AttachmentData::DATA_TEMP_THUMB_FILE, $tempThumbFile);
			}
			$dataDw->save();
		}
		catch (Exception $e)
		{
			if ($tempThumbFile)
			{
				@unlink($tempThumbFile);
			}
			
			if ($tempWaterMarkedFile)
			{
				@unlink($tempWaterMarkedFile);
			}
			
			throw $e;
		}

		if ($tempThumbFile)
		{
			@unlink($tempThumbFile);
		}
		if ($tempWaterMarkedFile)
		{
			@unlink($tempWaterMarkedFile);
		}
		// TODO: add support for "on rollback" behavior

		return $dataDw->get('data_id');
	}

    private function addWatermark($uploadedFile)
	{
		$options = XenForo_Application::get('options');
		$width = imagesx($uploadedFile);
		$height = imagesy($uploadedFile);
		$minWidth = $options->HDJuegos_WaterMarkAdd_MinWidth;
		$minHeight = $options->HDJuegos_WaterMarkAdd_MinHeight;
		if ($width < $minWidth && $height < $minHeight)
		{
			return false;
		}
		
			
		
		
			$watermark = imagecreatefrompng(XenForo_Application::getInstance()->getRootDir() . '/styles/HDJuegos/watermark/' . $options->HDJuegos_WaterMarkAdd_File);
		
			$waterwidth = imagesx($watermark);
		    $waterheight = imagesy($watermark);
			
			$marginRight = $options->HDJuegos_WaterMarkAdd_MarginRight;
			$marginBottom = $options->HDJuegos_WaterMarkAdd_MarginBottom;
			$marginTop = $options->HDJuegos_WaterMarkAdd_MarginTop;
			$marginLeft = $options->HDJuegos_WaterMarkAdd_MarginLeft;
			$startwidth = 0;
			$startheight = 0;
			switch ($options->HDJuegos_WaterMarkAdd_Position) {
				case "top-left":
				$startwidth = $marginLeft; 
				$startheight = $marginTop; 
				break;
				case "top-right":
				$startwidth = $width-$waterwidth-$marginRight; 
				$startheight = $marginTop; 
				break;
				case "bottom-left":
				$startwidth = $marginLeft; 
				$startheight = $height-$waterheight-$marginBottom; 
				break;
				case "bottom-right":
				$startwidth = $width-$waterwidth-$marginRight; 
				$startheight = $height-$waterheight-$marginBottom; 
				break;
				case "center":
				$startwidth = ceil($width/2)-ceil($waterwidth/2); 
				$startheight = ceil($height/2)-ceil($waterheight/2); 
				break;
			}
			imagecopy($uploadedFile, $watermark,  $startwidth, $startheight, 0, 0, $waterwidth, $waterheight);
			
			imagedestroy($watermark);
			
			$this->WaterMarkedImage = $uploadedFile;
			
			return true;
	}
	
	private function outputImage($outputType, $outputFile = null, $quality = 85)
	{
		switch ($outputType)
		{
			case IMAGETYPE_GIF: $success = imagegif($this->WaterMarkedImage, $outputFile); break;
			case IMAGETYPE_JPEG: $success = imagejpeg($this->WaterMarkedImage, $outputFile, $quality); break;
			case IMAGETYPE_PNG:
				// "quality" seems to be misleading, always force 9
				$success = imagepng($this->WaterMarkedImage, $outputFile, 9, PNG_ALL_FILTERS);
				break;

			default:
				throw new XenForo_Exception('Invalid output type given. Expects IMAGETYPE_XXX constant.');
		}

		return $success;
	}
	
	private function createFromFile($fileName, $inputType)
	{
		$invalidType = false;

		try
		{
			switch ($inputType)
			{
				case IMAGETYPE_GIF:
					if (!function_exists('imagecreatefromgif'))
					{
						return false;
					}
					$image = imagecreatefromgif($fileName);
					break;

				case IMAGETYPE_JPEG:
					if (!function_exists('imagecreatefromjpeg'))
					{
						return false;
					}
					$image = imagecreatefromjpeg($fileName);
					break;

				case IMAGETYPE_PNG:
					if (!function_exists('imagecreatefrompng'))
					{
						return false;
					}
					$image = imagecreatefrompng($fileName);
					break;

				default:
					$invalidType = true;
			}
		}
		catch (Exception $e)
		{
			return false;
		}

		if ($invalidType)
		{
			throw new XenForo_Exception('Invalid image type given. Expects IMAGETYPE_XXX constant.');
		}

		return $image;
	}
}