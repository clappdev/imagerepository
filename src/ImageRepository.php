<?php
namespace Clapp\ImageRepository;

use Illuminate\Contracts\Filesystem\Filesystem;
use Storage;
use InvalidArgumentException;
use Intervention\Image\ImageManager;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Intervention\Image\Exception\NotReadableException;
use League\Flysystem\Adapter\Local as LocalAdapter;
use Intervention\Image\Image;
use Closure;

/**
 * képeket tárol el a storageDisk-en és thumbnaileket generál belőlük a cacheDisk-re
 */
class ImageRepository{

    protected $storagePrefix = null;
    protected $storageDisk = null;
    protected $cacheDisk = null;
    protected $imageManager = null;

    /**
     * @param string $storagePrefix egy path prefix, amivel a storageDisken és a cacheDisken belül prefixelve lesz minden berakott kép url-e (pl. "user/profile-pictures/")
     * @param \Illuminate\Contracts\Filesystem\Filesystem $storageDisk   az eredeti képfájlok tárolási helye (nem kell publicnak lennie
     * @param \Illuminate\Contracts\Filesystem\Filesystem $cacheDisk     a thumbnailek tárolási helye (publicnak kell lennie)
     */
    public function __construct($storagePrefix = "", Filesystem $storageDisk = null, Filesystem $cacheDisk = null, ImageManager $imageManager = null){
        if (!empty($storagePrefix)){
            if (!ends_with($storagePrefix, "/")){
                $storagePrefix .= "/";
            }
        }
        $this->storagePrefix = $storagePrefix;

        if ($storageDisk == null){
            $storageDisk = $this->getDefaultStorageDisk();
        }
        if ($cacheDisk == null){
            $cacheDisk = $this->getDefaultCacheDisk();
        }
        if ($imageManager == null){
            $imageManager = $this->getDefaultImageManager();
        }

        $this->setStorageDisk($storageDisk);
        $this->setCacheDisk($cacheDisk);
        $this->setImageManager($imageManager);
    }

    protected function getDefaultStorageDisk(){
        if (class_exists("Storage")){
            return Storage::disk();
        }else{
            throw new InvalidArgumentException('missing $storageDisk');
        }
    }

    protected function getDefaultCacheDisk(){
        if (class_exists("Storage")){
            return Storage::disk();
        }else{
            throw new InvalidArgumentException('missing $cacheDisk');
        }
    }

    protected function getDefaultImageManager(){
        return new ImageManager(array('driver' => 'gd'));
    }

    public function setImageManager(ImageManager $imageManager){
        $this->imageManager = $imageManager;
    }

    public function getImageManager(){
        return $this->imageManager;
    }

    public function setStorageDisk(Filesystem $disk){
        $this->storageDisk = $disk;
    }
    public function getStorageDisk(){
        return $this->storageDisk;
    }
    public function setCacheDisk(Filesystem $disk){
        $this->cacheDisk = $disk;
    }
    public function getCacheDisk(){
        return $this->cacheDisk;
    }
    protected function guessFileExtension($image){
        if ($image instanceof Image){
            switch($image->mime()){
                case 'image/png':
                    return 'png';
                break;
                case 'image/gif':
                    return 'png';
                break;
                case 'image/jpeg':
                case 'image/pjpeg':
                default:
                    return 'jpg';
                break;
            }
        }else {
            return pathinfo($image, PATHINFO_EXTENSION);
        }
    }
    protected function getEncodeImageFormat($image){
        if ($image instanceof Image){
            switch($image->mime()){
                case 'image/png':
                    return 'png';
                break;
                case 'image/gif':
                    return 'png';
                break;
                case 'image/jpeg':
                case 'image/pjpeg':
                default:
                    return 'jpg';
                break;
            }
        }else {
            switch (pathinfo($image, PATHINFO_EXTENSION)){
                case 'png':
                case 'gif':
                    return 'png';
                break;
                case 'jpg':
                case 'jpeg':
                default:
                    return 'jpg';
                break;
            }
        }
    }
    /**
     * save an image
     * @param  binary|string|Image $imageContents the image
     * @return string key to retrieve this image from the storage
     */
    public function put($imageContents, $options = []){
        if (empty($imageContents)){
            throw new InvalidArgumentException('missing image file');
        }
        $img = $this->imageManager->make($imageContents);

        $extension = $this->guessFileExtension($img);
        $encodingFormat = $this->getEncodeImageFormat($img);

        $filename = sha1(str_random() . '_' . time()) . '.' . $extension;
        $filepath = $this->convertFilenameToFilePath($filename);

        $this->storageDisk->put($filepath, (string)$img->encode($encodingFormat));

        return $filename;
    }
    protected function defaultTransformId($width, $height){
        return  '_' . $width . 'x' . $height;
    }
    protected function defaultTransform(Image $image, $width, $height){
        return $image->fit($width, $height);
    }

    /**
     * get a thumbnail to a previously saved image file
     * @param  string  $filename the key returned by put()
     * @param  integer $width    fit the image into this width (default: 500)
     * @param  integer $height   fit the image into this height (default: 500)
     * @param  Closure $width    use this function to apply custom transformations to the image
     * @param  Closure $height   use this function to generate a unique string for the custom transformation - the same transformation should have the same unique string
     * @return string path to the generated thumbnail file - can be dropped directly into laravel's asset() function
     */
    public function get($filename, $width = 500, $height = 500){

        if ($width instanceof Closure){
            $transform = $width;
            if (!$height instanceof Closure){
                throw new InvalidArgumentException("missing transformId function");
            }
            $transformId = $height;
        }else {
            $self = $this;
            $transform = function(Image $image) use ($width, $height, $self){
                return $self->defaultTransform($image, $width, $height);
            };
            $transformId = function() use ($width, $height, $self){
                return $self->defaultTransformId($width, $height);
            };
        }

        $extension = $this->guessFileExtension($filename);
        $encodingFormat = $this->getEncodeImageFormat($filename);

        $sourceFilePath = $this->convertFilenameToFilePath($filename);
        $targetFilePath = $this->convertFilenameToFilePath($filename . $transformId() . '.'.$extension);

        if(!$this->cacheDisk->has($targetFilePath))
        {
            try {
                /**
                 * képet kikeressük a storageDisk-ből
                 */
                $imageContents = $this->storageDisk->get($sourceFilePath);
            }catch(FileNotFoundException $e){

                /**
                 * megnézzük, hogy hátha egy abszolút url-ünk van (pl. egy placeholder képhez)
                 */
                if (!empty($filename) && file_exists($filename)){
                    $imageContents = file_get_contents($filename);
                    $filename = basename($filename);
                    $targetFilePath = $this->convertFilenameToFilePath($filename . $transformId() .'.'.$extension);
                }else {
                    throw new ImageMissingOrInvalidException("", 0, $e);
                }
            }
            if(!$this->cacheDisk->has($targetFilePath)){
                try {
                    $instance = $this->imageManager->make($imageContents);
                    $instance = $transform($instance);
                    $img = (string) $instance->encode($encodingFormat);
                }catch(NotReadableException $e){
                    throw new ImageMissingOrInvalidException("", 0, $e);
                }
                $this->cacheDisk->put($targetFilePath, $img);
            }
        }
        /**
         * hogy egyenes be lehessen dobni az asset() függvénybe a path-t:
         *
         * "profile-pictures/pl/ac/placeholder.png_500x500.jpg" -> "cache/profile-pictures/pl/ac/placeholder.png_500x500.jpg"
         */
        $adapter = $this->cacheDisk->getDriver()->getAdapter();
        if ($adapter instanceof LocalAdapter){
            $pathPrefix = $this->cacheDisk->getDriver()->getAdapter()->getPathPrefix();
            if (starts_with($pathPrefix, public_path())){
                $publicPathPrefix = str_replace(public_path()."/", "", $pathPrefix);
                $targetFilePath = $publicPathPrefix . $targetFilePath;
            }

        }

        return $targetFilePath;
    }

    /**
     * $filename összes méretű változatának kiszedése a cacheDisk-ből
     */
    public function remove($filename){
        throw new \Exception("uninmplemented");
    }

    /**
     * teljes cacheDisk ürítése
     */
    public function flush(){
        throw new \Exception("uninmplemented");
    }

    protected function convertFilenameToFilePath($filename){
        return $this->storagePrefix . substr($filename, 0, 2) . '/' . substr($filename, 2, 2) . '/' . $filename;
    }

}
