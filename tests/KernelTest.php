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

	public function test_get_service_config()
	{
		$object = new Kernel();
		$this->assertInstanceOf( 'DenDev\Plpconfig\Config', $object->get_service( 'config' ) );
	}
}

