<?php

abstract class TestCase extends PHPUnit_Framework_TestCase{
    public function getDummyImage(){
        return file_get_contents(__DIR__ . '/cat.jpg');
    }
}
