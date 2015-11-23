<?php
namespace DenDev\Plpkernel\Test;
use DenDev\Plpkernel\Kernel;


class KernelTest extends \PHPUnit_Framework_TestCase 
{
	public function test_instanciate()
	{
		$object = new Kernel();
		$this->assertInstanceOf( "DenDev\Plpkernel\Kernel", $object );
	}
}

