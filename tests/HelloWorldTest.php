<?php

use PHPUnit\Framework\TestCase;

class HelloWorldTest extends TestCase
{
    public function testHelloWorld()
    {
        $helloWorld = new HelloWorld();
        $this->assertEquals("Hello, World!", $helloWorld->getMessage());
    }
}

?>