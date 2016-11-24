<?php
namespace Clapp\ImageRepository;

use Illuminate\Contracts\Filesystem\Filesystem;
use Storage;
use InvalidArgumentException;
use Intervention\Image\Facades\Image;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use League\Flysystem\Adapter\Local as LocalAdapter;

/**
 * képeket tárol el a storageDisk-en és thumbnaileket generál belőlük a cacheDisk-re
 */
class ImageRepository{

    protected $storagePrefix = null;
    protected $storageDisk = null;
    protected $cacheDisk = null;

    /**
     * @param string $storagePrefix egy path prefix, amivel a storageDisken és a cacheDisken belül prefixelve lesz minden berakott kép url-e (pl. "user/profile-pictures/")
     * @param \Illuminate\Contracts\Filesystem\Filesystem $storageDisk   az eredeti képfájlok tárolási helye (nem kell publicnak lennie
     * @param \Illuminate\Contracts\Filesystem\Filesystem $cacheDisk     a thumbnailek tárolási helye (publicnak kell lennie)
     */
    public function __construct($storagePrefix = "", $storageDisk = null, $cacheDisk = null){
        if (!empty($storagePrefix)){
            if (!ends_with($storagePrefix, "/")){
                $storagePrefix .= "/";
            }
        }
        $this->storagePrefix = $storagePrefix;

        if ($storageDisk == null){
            if (class_exists("Storage")){
                $storageDisk = Storage::disk();
            }else{
                throw new InvalidArgumentException('missing $storageDisk');
            }
        }
        if ($cacheDisk == null){
            if (class_exists("Storage")){
                $cacheDisk = Storage::disk();
            }else{
                throw new InvalidArgumentException('missing $cacheDisk');
            }
        }

        $this->setStorageDisk($storageDisk);
        $this->setCacheDisk($cacheDisk);
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
    /**
     * save an image
     * @param  binary|string|Image $imageContents the image
     * @return string key to retrieve this image from the storage
     */
    public function put($imageContents){
        if (empty($imageContents)){
            throw new InvalidArgumentException('missing image file');
        }
        $img = Image::make($imageContents);

        $filename = sha1(str_random() . '_' . time()) . '.jpg';
        $filepath = $this->convertFilenameToFilePath($filename);

        $this->storageDisk->put($filepath, (string)$img->encode('jpg'));

        return $filename;
    }
    /**
     * get a thumbnail to a previously saved image file
     * @param  string  $filename the key returned by put()
     * @param  integer $width    fit the image into this width (default: 500)
     * @param  integer $height   fit the image into this height (default: 500)
     * @return string path to the generated thumbnail file - can be dropped directly into laravel's asset() function
     */
    public function get($filename, $width = 500, $height = 500){

        $sourceFilePath = $this->convertFilenameToFilePath($filename);
        $targetFilePath = $this->convertFilenameToFilePath($filename . '_' . $width . 'x' . $height .'.jpg');

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
                    $targetFilePath = $this->convertFilenameToFilePath($filename . '_' . $width . 'x' . $height .'.jpg');
                }else {
                    throw $e;
                }
            }
            if(!$this->cacheDisk->has($targetFilePath)){
                $img = (string) Image::make($imageContents)->fit($width, $height)->encode('jpg');
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
