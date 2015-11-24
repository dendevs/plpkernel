<?php
namespace DenDev\Plpkernel\Test;
use DenDev\Plpkernel\Kernel;


class KernelTest extends \PHPUnit_Framework_TestCase 
{
	public function setUp()
	{
		$this->_config_dir = sys_get_temp_dir() . '/test/';
		@mkdir( $this->_config_dir );
		$content_test1 = "<?php return array( 'test1' => 'valeur test1 fichier test1', 'test2' => 'valeur test2 fichier test1', 'test3' => 'valeur test3 fichier test1' ); ";

		file_put_contents( $this->_config_dir . 'test1.php', $content_test1 );
	}

	public function test_instanciate()
	{
		$object = new Kernel();
		$this->assertInstanceOf( "DenDev\Plpkernel\Kernel", $object );
	}

	public function test_get_service_config()
	{
		$object = new Kernel( $this->_config_dir );
		$service = $object->get_service( 'config' );
		$this->assertInstanceOf( 'DenDev\Plpconfig\Config', $service );
		$this->assertEquals( 'valeur test1 fichier test1', $service->get_value( 'test1.test1' ) );
	}

	public function tearDown()
	{
		@unlink( $this->_config_dir . 'test1.php' );
		@rmdir( $this->_config_dir );
	}

}

