<?php

/**
 * Trellis_AmazonCdn
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0), a
 * copy of which is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @package    Trellis_AmazonCdn
 * @author     Zach Loubier <zach@growwithtrellis.com>
 * @copyright  Copyright (c) 2014 Trellis, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class Trellis_AmazonCdn_Model_Varien_Gd2 extends Varien_Image_Adapter_Gd2 {
  /**
   * @var bool
   */
  protected $_fileInCache = false;

  /**
   * Get helper
   *
   * @return Trellis_AmazonCdn_Helper_Data
   */
  protected function _getHelper() {
    return Mage::helper('trellis_amazoncdn');
  }

  /**
   * Download file from remote (called before all real actions on file like rotate, crop, etc.)
   *
   * @throws InvalidArgumentException
   */
  protected function _downloadAndOpenRemoteFile() {
    if (!$this->_getHelper()->getCdnAdapter()->downloadFile($this->_fileName)) {
      $message = sprintf('File "%s" not found both on local filesystem and on CDN', $this->_fileName);
      throw new InvalidArgumentException($message);
    }

    // hack to prevent infinite loop in getMimeType()
    $this->_imageHandler = true;
    parent::open($this->_fileName);
  }

  /**
   * Download file from cdn and store it local temp dir
   *
   * @param string $filename
   *
   * @throws InvalidArgumentException
   */
  public function open($filename) {
    $this->_fileName = $filename;
    $cachedData = $this->_getHelper()->getCacheFacade()->get($filename);
    if ($cachedData && is_array($cachedData)) {
      $this->_fileInCache = true;
      $this->_fileType = $cachedData['image_type'];
      $this->_fileMimeType = image_type_to_mime_type($this->_fileType);
      $this->_imageSrcWidth = $cachedData['image_width'];
      $this->_imageSrcHeight = $cachedData['image_height'];
    }
  }

  /**
   * Hijack the normal GD2 save method to add CDN hooks. Fail back to parent method as appropriate.
   *
   * @param string $destination
   * @param string $newName
   *
   * @throws Exception
   */
  public function save($destination = null, $newName = null) {
    if (!$this->_imageHandler) {
      $this->_downloadAndOpenRemoteFile();
    }

    $temp = tempnam(sys_get_temp_dir(), 'cds');

    if ($this->_fileType == IMAGETYPE_PNG && $this->checkAlpha($this->_fileName)) {
      imagesavealpha($this->_imageHandler, true);
    }

    Varien_Image_Adapter_Gd2::save($temp);

    // compress images?
    $compression = $this->_getHelper()->getCompression();
    if ($compression > 0) {
      switch ($this->_fileType) {
        case IMAGETYPE_JPEG:
          $convert = round((9 - $compression) * (100 / 8)); //convert to imagejpeg's scale
          call_user_func('imagejpeg', $this->_imageHandler, $temp, $convert);
          break;
        case IMAGETYPE_PNG:
          $convert = round(($compression - 1) * (9 / 8)); //convert to imagepng's scale
          call_user_func('imagepng', $this->_imageHandler, $temp, $convert);
          break;
      }
    }

    $filename = (!isset($destination)) ? $this->_fileName : $destination;
    if (isset($destination) && isset($newName)) {
      $filename = $destination . "/" . $filename;
    } elseif (isset($destination) && !isset($newName)) {
      $info = pathinfo($destination);
      $filename = $destination;
      $destination = $info['dirname'];
    } elseif (!isset($destination) && isset($newName)) {
      $filename = $this->_fileSrcPath . "/" . $newName;
    } else {
      $filename = $this->_fileSrcPath . $this->_fileSrcName;
    }

    if ($this->_getHelper()->getCdnAdapter()->save($filename, $temp)) {
      @unlink($temp);
    } else {
      if (!is_writable($destination)) {
        try {
          $io = new Varien_Io_File();
          $io->mkdir($destination);
        } catch (Exception $e) {
          throw new Exception("Unable to write file into directory '{$destination}'. Access forbidden.");
        }
      }
      @rename($temp, $filename);
      @chmod($filename, 0644);
    }
  }

  /**
   * Retrieve Original Image Width
   *
   * @return int|null
   */
  public function getOriginalWidth() {
    if ($this->_fileInCache) {
      return $this->_imageSrcWidth;
    }

    if (!$this->_imageHandler) {
      $this->_downloadAndOpenRemoteFile();
    }

    return parent::getOriginalWidth();
  }

  /**
   * Retrieve Original Image Height
   *
   * @return int|null
   */
  public function getOriginalHeight() {
    if ($this->_fileInCache) {
      return $this->_imageSrcHeight;
    }

    if (!$this->_imageHandler) {
      $this->_downloadAndOpenRemoteFile();
    }

    return parent::getOriginalHeight();
  }

  /**
   * Get image mime type
   *
   * @return string
   */
  public function getMimeType() {
    if ($this->_fileInCache) {
      return $this->_fileMimeType;
    }

    if (!$this->_imageHandler) {
      $this->_downloadAndOpenRemoteFile();
    }

    return parent::getMimeType();
  }

  public function crop($top = 0, $left = 0, $right = 0, $bottom = 0) {
    $this->_downloadAndOpenRemoteFile();
    parent::crop($top, $left, $right, $bottom);
  }

  public function display() {
    $this->_downloadAndOpenRemoteFile();
    parent::display();
  }

  public function resize($frameWidth = null, $frameHeight = null) {
    $this->_downloadAndOpenRemoteFile();
    parent::resize($frameWidth, $frameHeight);
  }

  public function rotate($angle) {
    $this->_downloadAndOpenRemoteFile();
    parent::rotate($angle);
  }

  public function watermark(
    $watermarkImage, $positionX = 0, $positionY = 0, $watermarkImageOpacity = 30,
    $repeat = false
  ) {
    $this->_downloadAndOpenRemoteFile();
    parent::watermark($watermarkImage, $positionX, $positionY, $watermarkImageOpacity, $repeat);
  }

  public function  __destruct() {
    if ($this->_imageHandler) {
      parent::__destruct();
    }
  }
}
