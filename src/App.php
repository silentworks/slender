<?php
namespace Slender;

use Slender\Core\ModuleLoader\ModuleLoader;
use Slender\Core\ModuleResolver\DirectoryResolver;
use Slender\Core\ModuleResolver\NamespaceResolver;
use Slender\Core\ModuleResolver\ResolverStack;
use Slender\Core\ConfigParser;
use Slender\Interfaces\ConfigFileFinderInterface;
use Slender\Interfaces\ConfigFileParserInterface;
use Slender\Interfaces\ModuleLoaderInterface;
use Slender\Interfaces\FactoryInterface;
use Slender\Interfaces\ModuleResolverInterface;

class App extends \Slim\App
{

    function __construct(array $userSettings = array())
    {
        // Do the normal Slim construction
        parent::__construct($userSettings);

        // Register our core services
        $this->registerCoreServices();

        /**
         * Load up config defaults
         *
         * This is a hardcoded config file within Slender core that sets
         * up sane default values for config
         *
         * @var ConfigFileParserInterface $parser
         */
        $parser = $this['config-parser'];
        $defaults = $parser->parseFile(__DIR__ . '/slender.yml');
        $userSettings = array_merge_recursive($defaults, $userSettings);
        $this['settings']->setArray($userSettings);


        /**
         * Load any application config files
         *
         * @var ConfigFileFinderInterface $loader
         */
        $loader = $this['config-finder'];
        $userSettings = array();
        foreach($loader->findFiles() as $path){
            if(is_readable($path)){
                $parsedFile = $parser->parseFile($path);
                if($parsedFile !== false){
                    $userSettings = array_merge_recursive($userSettings, $parsedFile);
                }
            } else {
                echo "Invalid path $path\n";
            }
        }
        $this['settings']->setArray($userSettings);


        /**
         * Load modules
         */
        foreach($this['settings']['modules'] as $module){
            $this['module-loader']->loadModule($module);
        }

        /**
         * Register Services & Factories
         */
        foreach($this['settings']['services'] as $service => $class){
            $this->registerService($service,$class);
        }


        /**
         * Call module invokables now everything is ready
         */

    }

    public function registerService($service,$class)
    {
        $this[$service] = $this->share(function($app) use ($class){
            $inst = new $class;
            if($inst instanceof FactoryInterface){
                return $inst->create($app) ;
            } else {
                return $inst;
            }
        });
    }


    /**
     * Registers core services to the IoC Container
     */
    protected function registerCoreServices()
    {
        /**
         * The configParser is used to translate various file
         * formats into PHP arrays
         *
         * @var ConfigFileParserInterface
         * @return \Slender\Core\ConfigParser\Stack
         */
        $this['config-parser'] = $this->share(function(){
                return new ConfigParser\Stack(array(
                    'yml' => new ConfigParser\YAML,
                    'php' => new ConfigParser\PHP,
                    'json' => new ConfigParser\JSON,
                ));
            });


        /**
         * ConfigFinder is responsible for finding, loading and merging
         * configuration files
         *
         * @var ConfigFileFinderInterface
         * @return ConfigFileFinderInterface
         */
        $this['config-finder'] = $this->share(function($app) {
                $configLoader = new \Slender\Core\ConfigFinder\ConfigFinder(
                    $this['settings']['config']['autoload'],
                    $this['settings']['config']['files']
                );
                return $configLoader;
            });


        /**
         * ModuleResolver is used for tracking down a module's path
         * from it's name
         *
         * @var ModuleResolverInterface
         * @return \Slender\Core\ModuleResolver\ResolverStack
         */
        $this['module-resolver'] = $this->share(function($app){
                $stack = new ResolverStack(new NamespaceResolver);
                $stack->setConfigParser($app['config-parser']);
                foreach($this['settings']['modulePaths'] as $path){
                    if(is_readable($path)){
                        $stack->prependResolver(new DirectoryResolver($path));
                    }
                }

                return $stack;
            });

        /**
         * ModuleLoader is used to load modules & their dependencies,
         * registering services & routes etc along the way
         *
         * @var ModuleLoaderInterface
         * @param \Slender\App $app
         * @return ModuleLoaderInterface
         */
        $this['module-loader'] = $this->share(function($app){
                 $loader = new ModuleLoader();
                 $loader->setResolver($app['module-resolver']);
                 $loader->setConfig($app['settings']);
                 return $loader;
            });

    }



}