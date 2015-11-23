<?php
namespace DenDev\Plpkernel;
use DenDev\Plpkernel\KernelInterface;
use Pimple\Container;


/**
 * Centralise les services.
 *
 * Sert de catalogue de service.
 * assur le log, config, error en appel direct
 */
class Kernel implements KernelInterface
{
    /**
     * constructor.
     *
     * @param string $config_dir chemin vers le repertoire ou se trouve les fichier de config, 
     * false pour utiliser la config par defaut pour tout ( kernel et service )
     */
    public function __construct( $config_dir = false )
    {
        $this->_container = new Container();
        $this->_available_services = array();
        $this->_set_base_services( $config_dir );
    }

    /**
     * Init la config par defaut pour le kernel exclusivement
     *
     * @return array tableau associatif de coniguration
     */
    public function get_default_configs()
    {
        $root_path = str_replace('src', '', dirname(__FILE__));

        $root_url = '';
        if(! empty($_SERVER['HTTPS']) && ! empty($_SERVER['HTTP_HOST']) && ! empty($_SERVER['REQUEST_URI']) ) {
            $protocol = ( $_SERVER['HTTPS'] && $_SERVER['HTTPS'] !== 'off' ) ? 'https://' : 'http://';
            $tmp = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
            $root_url = substr($tmp, 0, strpos($tmp, 'src/'));
        }
        return array( 
            'root_path' => $root_path,
            'log_path' => $root_path . 'logs/',
            'config_path' => $root_path . 'configs/',
            'assets_path' => $root_path . 'assets/',
            'js_path' => $root_path . 'assets/js/',
            'js_url' => $root_url . 'assets/js/',
            'img_path' => $root_path . 'assets/img/',
            'css_path' => $root_path . 'assets/css/',
        );
    }

    /**
     * Permet l'ajout d'un service.
     * 
     * @param string $service_name nom du service en lower slugifier ( mon_service ok Mon Service ko )
     * @param string $class_path chemin vers la class du service ( DenDev\Plpconfig\Config.php ok )
     * @param bool $instanciate par defaut a false, true pour recuperer un service instancier
     *
     * @return bool|object true ou false si le serice a etait ajouter, l'object service si instancier
     */
    public function add_service( $service_name, $class_path, $instanciate = false, $factory = false )
    {
        if( ! array_key_exists( $service_name, $this->_available_services ) )
        {
            if( file_exists( $class_path ) )
            {
                $this->_available_services[$service_name];
                if( $instanciate && ! $factory)
                {
                    $service = $this->_instanciate_singleton_service( $service_name );
                }
                else if( $instanciate && $factory)
                {
                    $service = $this->_instanciate_factory_service( $service_name );
                }
            }
            else
            {
                // class_path not found
            }   
        }
        else
        {
            // service existe deja 
        }

    }

    /**
     * Permet d'obtenir la reference vers un service.
     *
     * Si le service n'existe pas il est creer a condition que son slug soit unique
     *
     * @param string $service_name nom du service en lower slugifier ( mon_service ok Mon Service ko )
     *
     * @return object le service 
     */
    public function get_service( $service_name )
    {
        $service = false;
        if( array_key_exists( $service_name, $this->_available_services ) )
        {
            if( $this->_container->offsetExists( $service_name ) )
            {
                // get
                $service = $this->_container[$service_name];
            }
            else
            {
                // add
                $class_name = $this->_available_services[$service_name];
                $config_file_with_ext = ( $config_file_with_ext ) ? $config_file_with_ext : $service_name . '.php';
                $config_path = $this->get_config_value( 'service_config_path' ) . $config_file_with_ext;
                $function = function( $context ) use( $class_name, $config_path ) {
                    $service = new $class_name( $context, $config_path );
                    return $service->get_service_instance();
                };
                $this->_container[$service_name] = $function;
                $service = $this->_container[$service_name];
            }
        }
        else
        {
            // not found
            //throw new \Exception( "Erreur service not found $service_name " );
            $service = false;
        }
        return $service;
    }

    // -
    private function _set_base_services( $config_dir ) // because at the begining no config, error, logger exist 
    {
        // config
        $this->_available_services['config'] = '\DenDev\Plpconfig\Config';
        $krl = $this; // because config can not be set without config...
        $this->_container['config'] = function( $context ) use( $krl, $config_dir ) {
            $service = new \DenDev\Plpconfig\Config( $krl, array( 'config_dir' => $config_dir ) );
            return $service->get_service_instance();
        };

        // log
        $this->_available_services['logger'] = 'DenDev\Plplogger\Logger';
        $service = $this->_instanciate_singleton_service( 'logger' );

        // error
        $this->_available_services['error'] = 'DenDev\Plperror\Error';
        $service = $this->_instanciate_singleton_service( 'error' );
    }

    /**
     * Instancie un service et le stock dans le Container
     *
     * Met a jour la configuration et rend l'object dispo
     *
     * @param string $service_name le nom du service_name
     * @param array $args un tableau associatif des arguments a donner au service 
     *
     * @return void
     */
    private function _instanciate_singleton_service( $service_name, $args = null )
    {
        $class_path = $this->_available_services[$service_name];
        $krl = $this;

        $function = function( $context ) use( $class_path, $krl, $args, $service_name ) {
            // create service
            $service = new $class_path( $krl, $args );
            // update config with default 
            $this->_config->merge_with_default( $service_name, $service->get_default_configs() );
            // end
            return $service->get_service_instance();
        };

        $this->_container[$service_name] = $function;
    }

    private function _instanciate_factory_service( $service_name )
    {
    }
}
