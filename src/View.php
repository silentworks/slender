<?php
namespace Slender;

use \Slim\Collection;
use \Slim\Interfaces\ViewInterface;
use Symfony\Component\Finder\Finder;

/**
 * View
 *
 * This class is responsible for fetching and rendering a template with
 * a given set of data. Although the `\Slim\View` class is itself
 * capable of rendering PHP templates, it is highly recommended that you
 * subclass `\Slim\View` for use with popular PHP templating libraries
 * such as Twig, Smarty, or Mustache.
 *
 * If you do choose to create a subclass of `\Slim\View`, the subclass
 * MUST override the `render` method with this exact signature:
 *
 *     public render(string $template);
 *
 * The `render` method MUST return the rendered output for the template
 * identified by the `$template` argument. The `$template` argument will
 * contain the template file pathname *relative to* the templates base
 * directory for the current view instance.
 *
 * The `Slim-Views` repository contains pre-made custom views for
 * Twig and Smarty, two of the most popular PHP templating libraries.
 *
 * See: https://github.com/codeguy/Slim-Views
 *
 * Also, `\Slim\View` extends `\Slim\Container` so
 * that you may use the convenient `\Slim\Container` interface just
 * as you do with other Slim application data sets (e.g. HTTP headers,
 * HTTP cookies, etc.)
 *
 * @package Slim
 * @author  Josh Lockhart
 * @since   1.0.0
 */
class View extends Collection implements ViewInterface
{

    protected $templateDirs = array();
    protected $pathCache = array();
    protected $fileExtension = 'php';

    /**
     * Constructor
     *
     * @param  array $items Initialize set with these items
     * @api
     */
    public function __construct(array $items = array())
    {
        parent::__construct($items);
    }

    /**
     * Display template
     *
     * This method echoes the rendered template to the current output buffer
     *
     * @param  string $template Pathname of template file relative to templates directory
     * @param array   $data
     * @api
     */
    public function display($template, array $data = array())
    {
        echo $this->fetch($template, $data);
    }

    /**
     * Fetch template
     *
     * This method returns the rendered template. This is useful if you need to capture
     * a rendered template into a variable for further processing.
     *
     * @var    string $template Pathname of template file relative to templates directory
     * @param array   $data
     * @return string           The rendered template
     * @api
     */
    public function fetch($template, array $data = array())
    {
        return $this->render($template, $data);
    }

    /**
     * Render template
     *
     * This method will render the specified template file using the current application view.
     * Although this method will work perfectly fine, it is recommended that you create your
     * own custom view class that implements \Slim\ViewInterface instead of using this default
     * view class. This default implementation is largely intended as an example.
     *
     * @var    string $template Pathname of template file relative to templates directory
     * @param array   $data
     * @throws \RuntimeException If resolved template pathname is not a valid file
     * @return string                      The rendered template
     */
    protected function render($template, array $data = array())
    {
        // Resolve and verify template file
        $templatePathname = $this->resolveTemplate($template);
        if (!is_file($templatePathname)) {
            throw new \RuntimeException("Cannot render template `$templatePathname` because the template does not exist. Make sure your view's template directory is correct.");
        }

        // Render template with view variables into a temporary output buffer
        $this->replace($data);
        extract($this->all());
        ob_start();
        require $templatePathname;

        // Return temporary output buffer content, destroy output buffer
        return ob_get_clean();
    }


    /**
     * Resolves a template name to a file in $templateDirs
     *
     * @param $template
     */
    protected function resolveTemplate($template)
    {
        if(!$this->pathCache[$template]){

            foreach(array_reverse($this->templateDirs) as $dir){
                $path = $dir . DIRECTORY_SEPARATOR . $template .'.'. $this->getFileExtension();
                if(is_readable($path)){
                    $this->pathCache[$template] = $path;
                    break;
                }
            }

        }
        return $this->pathCache[$template];
    }

    /**
     * @param array $templateDirs
     */
    public function setTemplateDirs($templateDirs)
    {
        $this->templateDirs = $templateDirs;
    }

    /**
     * @return array
     */
    public function getTemplateDirs()
    {
        return $this->templateDirs;
    }

    /**
     * @param string $fileExtension
     */
    public function setFileExtension($fileExtension)
    {
        $this->fileExtension = $fileExtension;
    }

    /**
     * @return string
     */
    public function getFileExtension()
    {
        return $this->fileExtension;
    }


}
