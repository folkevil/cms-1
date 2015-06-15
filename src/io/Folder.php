<?php
/**
 * @link      http://buildwithcraft.com/
 * @copyright Copyright (c) 2015 Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license
 */

namespace craft\app\io;

use craft\app\helpers\IOHelper;

/**
 * Class Folder
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  3.0
 */
class Folder extends BaseIO
{
    // Properties
    // =========================================================================

    /**
     * @var
     */
    private $_size;

    /**
     * @var bool
     */
    private $_isEmpty;

    // Public Methods
    // =========================================================================

    /**
     * @param $path
     *
     * @return Folder
     */
    public function __construct($path)
    {
        clearstatcache();
        $this->path = $path;
    }

    /**
     * @return mixed
     */
    public function getSize()
    {
        if (!$this->_size) {
            $this->_size = IOHelper::getFolderSize($this->getRealPath());
        }

        return $this->_size;
    }

    /**
     * @return boolean
     */
    public function isEmpty()
    {
        if (!$this->_isEmpty) {
            $this->_isEmpty = IOHelper::isFolderEmpty($this->getRealPath());
        }

        return $this->_isEmpty;
    }

    /**
     * @param $recursive
     * @param $filter
     *
     * @return mixed
     */
    public function getContents($recursive, $filter)
    {
        return IOHelper::getFolderContents($this->getRealPath(), $recursive,
            $filter);
    }

    /**
     * @param $destination
     *
     * @return boolean
     */
    public function copy($destination)
    {
        if (!IOHelper::copyFolder($this->getRealPath(), $destination)) {
            return false;
        }

        return true;
    }

    /**
     * @param boolean $suppressErrors
     *
     * @return boolean
     */
    public function clear($suppressErrors = false)
    {
        if (!IOHelper::clearFolder($this->getRealPath(), $suppressErrors)) {
            return false;
        }

        return true;
    }

    /**
     * @return boolean
     */
    public function delete()
    {
        if (!IOHelper::deleteFolder($this->getRealPath())) {
            return false;
        }

        return true;
    }
}