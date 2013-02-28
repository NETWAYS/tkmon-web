<?php

namespace NETWAYS\Tests\IO;

class FileObjectTest extends \PHPUnit_Framework_TestCase
{
    public function testChmod1()
    {
        $testFile = '/tmp/tkmon-test-chmod.txt';

        umask(0022);

        $fo = new \NETWAYS\IO\FileObject($testFile, 'w');

        $fo->fwrite('TEST123');
        $fo->fflush();

        $p1 = fileperms($testFile);
        $this->assertEquals(33188, $p1);

        $fo->chmod(0607);

        clearstatcache(); // WÜRGH!

        $p2 = fileperms($testFile);

        $this->assertEquals(33159, $p2);

        unlink($testFile);
    }
}
