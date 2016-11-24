<?php

use Clapp\ImageRepository\ImageRepository;
use League\Flysystem\Memory\MemoryAdapter;
use League\Flysystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;

class ImageRepositoryTest extends TestCase{

    /**
     * @expectedException InvalidArgumentException
     */
    public function testMissingStorageDisk(){
        $repo = new ImageRepository();
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testMissingCacheDisks(){
        $storageDisk = new FilesystemAdapter(new Filesystem(new MemoryAdapter()));
        $repo = new ImageRepository("", $storageDisk);
    }

    public function testSuccessfulCreation(){
        $storageDisk = new FilesystemAdapter(new Filesystem(new MemoryAdapter()));
        $cacheDisk = new FilesystemAdapter(new Filesystem(new MemoryAdapter()));
        $repo = new ImageRepository("", $storageDisk, $cacheDisk);

        $this->assertSame($repo->getStorageDisk(), $storageDisk);
        $this->assertSame($repo->getCacheDisk(), $cacheDisk);

        $this->assertNotSame($repo->getStorageDisk(), $cacheDisk);
        $this->assertNotSame($repo->getCacheDisk(), $storageDisk);
    }
}
