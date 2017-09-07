<?php

abstract class TestCase extends PHPUnit_Framework_TestCase{
    public function getDummyImage($format = "jpg"){
        return file_get_contents(__DIR__ . '/cat.'.$format);
    }
}
