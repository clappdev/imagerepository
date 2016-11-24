<?php
namespace Clapp\ImageRepository;

use JsonSerializable;

class ImageThumbnail implements JsonSerializable{
    /**
     * the $key that was used to retrieve this image from the ImageRepository
     */
    public $key = null;
    /**
     * the path that this image has inside the $cacheDisk of the ImageRepository
     */
    public $cachePath = null;
    /**
     * the width of the image of $cachePath
     */
    public $width = null;
    /**
     * the height of the image of $cachePath
     */
    public $height = null;
    /**
     * is this image a placeholder?
     */
    public $isPlaceholder = false;

    public function __construct($key, $cachePath, $width, $height, $isPlaceholder = false){
        $this->key = $key;
        $this->cachePath = $cachePath;
        $this->width = $width;
        $this->height = $height;
        $this->isPlaceholder = $isPlaceholder;
    }

    public function getSize(){
        return [
            'width' => $this->width,
            'height' => $this->height,
        ];
    }

    public function getPermalink(){
        return $this->cachePath;
    }

    public function toArray(){
        return [
            'thumbnail_uri' => $this->getPermalink(),
            'thumbnail_size' => $this->getSize(),
            'is_placeholder' => $this->isPlaceholder,
        ];
    }

    public function jsonSerialize(){
        return $this->toArray();
    }
}
