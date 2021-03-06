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

class Trellis_AmazonCdn_Helper_Logger
{
    /**
     * @var bool
     */
    protected $_debugMode = false;

    /**
     * Class constructor
     *
     * @param bool $debugMode
     */
    public function __construct($debugMode = false)
    {
        $this->_debugMode = (bool)$debugMode;
    }

    /**
     * Log message to log (debug mode only)
     *
     * @param string $message
     * @param int $level
     */
    public function log($message, $level = Zend_Log::DEBUG)
    {
        if ($this->_debugMode) {
            Mage::log($message, $level, 'trellis_amazoncdn.log');
        } else {
            switch ($level) {
                case Zend_Log::ERR:
                case Zend_Log::CRIT:
                case Zend_Log::ALERT:
                case Zend_Log::EMERG:
                    Mage::log($message, $level, 'trellis_amazoncdn.log');
                    break;
            }
        }
    }
}
