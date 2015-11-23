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
        return array();
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
        if( array_key_exists( $id_service, $this->_available_services ) )
        {
            if( $this->_container->offsetExists( $id_service ) )
            {
                // get
                $service = $this->_container[$id_service];
            }
            else
            {
                // add
                $class_name = $this->_available_services[$id_service];
                $config_file_with_ext = ( $config_file_with_ext ) ? $config_file_with_ext : $id_service . '.php';
                $config_path = $this->get_config_value( 'service_config_path' ) . $config_file_with_ext;
                $function = function( $context ) use( $class_name, $config_path ) {
                    $service = new $class_name( $context, $config_path );
                    return $service->get_service_instance();
                };
                $this->_container[$id_service] = $function;
                $service = $this->_container[$id_service];
            }
        }
        else
        {
            // not found
            //throw new \Exception( "Erreur service not found $id_service " );
            $service = false;
        }
        return $service;
    }

    /**
     * Merge la config general du kernel avec la config par defaut du service.
     *
     * La config kernel a la priorite sur celle du service.
     * Le but est de pouvoir customiser le service via le kernel
     *
     * @param array $default_service_configs tableau associatif config valeur
     *
     * @return array la config merger
     */
    public function merge_configs( $default_service_configs )
    {
        if( ( bool ) $default_service_configs ) // better than is_array
        {
            $this->_config = array_merge( $default_service_configs, $this->_config );
        }


        return $this->_config;
    }

    /**
     * Recupere la valeur d'un option de configuration.
     *
     * @param string config_name nom de l'option
     * @param mixed $default_value valeur si l'option n'existe pas, false par default
     *
     * @return mixed|false la valeur ou false si rien trouver
     */
    public function get_config_value( $config_name, $default_value = false )
    {
        $value = false;
        if( array_key_exists( $config_name, $this->_config ) )
        {
            $value = $this->_config[$config_name];
        }
        else if( ! $default_value )
        {
            $value = $default_value;
        }

        return $value;
    }

    /**
     * Ecriture de log basique.
     *
     * Le logs est ecrit dans /tmp par defaut. 
     * Si la config log_path existe alors l'ecriture ce fait dans ce repertoire nom_plugin.
     *
     * @param string $log_name nom fichier ( sans ext ) 
     * @param string $level niveau du message ( info, debug, ... )
     * @param string $message message a logger
     * @param array $context informations supplementaires
     *
     * @return bool true si ecriture ok
     */
    public function log( $service_name, $log_name, $level, $message, $context = array() )
    {
        $ok = false;

        $tmp_log_path = $this->get_config_value( 'log_path' );
        $log_path = ( $this->get_config_value( 'log_path' ) ) ? $this->get_config_value( 'log_path' ) . '/' . $service_name . '/' : sys_get_temp_dir() . '/' . $service_name . '/';

        if( ! file_exists( $log_path ) )
        {
            mkdir( $log_path, 0755 );
        }

        $log_path .= $log_name . ".log";

        // avoid big file
        $append = false;
        if( file_exists( $log_path ) && filesize( $log_path ) >= 1024 )
        {
            //    unlink( $log_path );
            $append = FILE_APPEND;
        }

        // write
        $context_string = ( (bool) $context ) ? print_r( $context, true ) : '';
        $formated_message = $level . ': ' . $message . ' ( ' . $context_string . ' )';
        $ok = file_put_contents( $log_path, $formated_message, $append );

        return ( $ok === false ) ? false : true;
    }

    /**
     * Gere les erreurs fatal ou non.
     *
     * Si l'erreur est fatal log l'erreur et declenche un object Exception
     * Sinon log l'erreur.
     *
     * @param string $service_name le nom du service_name
     * @param string $message le message
     * @param int $code le code erreur
     * @param array $context infos supp sur le context de l'erreur
     * @param bool $fatal declenche ou non une exception
     *
     * @return bool true si l'ecriture dans le log est ok ( et erreur non fatal )
     */
    public function error( $service_name, $message, $code, $context = false, $fatal = false )
    {
        $ok = false;
        // log
        $level = ( $fatal ) ? 'alert' : 'error'; 

        $context_string = ( (bool) $context ) ? print_r( $context, true ) : '';
        $formated_message = $level . ': ' . $message . ' ( ' . $context_string . ' )';

        $ok = $this->log( $service_name, 'error', $level, $formated_message, $context_string );

        // error
        if( $fatal )
        {
            throw new \Exception( $formated_message );
        }

        return $ok;
    }


    protected function _set_default_config()
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

    // -
    private function _set_base_services( $config_dir ) // because at the begining no config, error, logger exist 
    {
        // config
        $this->_available_services['config'] = 'DenDev\Plpconfig\Config.php';
        $service = $this->_instanciate_singleton_service( 'config' );

        // log
        $this->_available_services['logger'] = 'DenDev\Plplogger\Logger.php';
        $service = $this->_instanciate_singleton_service( 'logger' );

        // error
        $this->_available_services['error'] = 'DenDev\Plperror\Error.php';
        $service = $this->_instanciate_singleton_service( 'error' );
    }

    private function _instanciate_singleton_service( $service_name, $args = null )
    {
        $class_path = $this->_available_services[$service_name];
        $krl = $this;

        $service = new $class_path( $krl, $args );
        $function = function( $context ) use( $class_path, $krl, $args ) {
            $service = new $class_path( $krl, $args );
            return $service->get_service_instance();
        };

        $this->_container[$service_name] = $function;
    }

    private function _instanciate_factory_service( $service_name )
    {
    }
}
