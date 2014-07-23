<?php

/**
 * Dewdrop
 *
 * @link      https://github.com/DeltaSystems/dewdrop
 * @copyright Delta Systems (http://deltasys.com)
 * @license   https://github.com/DeltaSystems/dewdrop/LICENSE
 */

namespace Dewdrop\Admin\Page;

use Dewdrop\Admin\Component\ComponentAbstract;
use Dewdrop\Admin\ResponseHelper\Standard as ResponseHelper;
use Dewdrop\Pimple;
use Dewdrop\Request;

/**
 * This is the base page controller class for admin component's in Dewdrop.
 *
 * The stock page controller implements a basic three step execution process:
 *
 * <ul>
 *     <li>init(): Create any resources shared by both process() and render()</li>
 *     <li>
 *         process(): Perform and form processing or data manipulation.  Optionally,
 *         short-circuit further execution and bypass rendering by redirecting,
 *         aborting, etc.
 *     </li>
 *     <li>
 *         render(): Assign values to your view.  You can either directly render
 *         your view script in this method (or generate output in any other way),
 *         or, if no output is generated by render(), Dewdrop will attempt to
 *         render your default view script automatically.
 *     </li>
 * </ul>
 *
 * Sub-classes, such as EditAbstract, can be created that alter this basic page
 * controller flow of execution.  EditAbstract, for example, will only call
 * process() if the request is a POST.
 */
abstract class PageAbstract
{
    /**
     * The component the page is part of
     *
     * @var ComponentAbstract
     */
    protected $component;

    /**
     * A view object available for rendering.  Generally, your page should not
     * be rendering any output directly, but instead passing information from
     * models to the view and then rendering the view.
     *
     * @var \Dewdrop\View\View
     */
    protected $view;

    /**
     * An object representing the current HTTP request.  The is primarily
     * around to make it easier to test your pages by injecting POST and GET
     * data into the request object.
     *
     * @var \Dewdrop\Request
     */
    protected $request;

    /**
     * Create a new page with a reference to its component and the file in which
     * it is defined.
     *
     * Also, by default, the page will be configured to look for view scripts
     * in the view-scripts sub-folder of its component.
     *
     * @param ComponentAbstract $component
     * @param Request $request
     * @param string $viewScriptPath
     */
    public function __construct(ComponentAbstract $component, Request $request, $viewScriptPath = null)
    {
        $this->component   = $component;
        $this->view        = Pimple::getResource('view');
        $this->request     = ($request ?: $this->application['dewdrop-request']);

        if (null === $viewScriptPath) {
            $viewScriptPath = $this->component->getPath() . '/view-scripts';
        }

        $this->view
            ->setScriptPath($viewScriptPath)
            ->helper('AdminUrl')
                ->setPage($this);
    }

    /**
     * Create any resources that need to be accessible both for processing
     * and rendering.
     */
    public function init()
    {
    }

    /**
     * Whether this page's process method should be called.  By default,
     * process() is always called, but sub-classes like EditAbstract can alter
     * that logic to make other common patterns easier to support.
     *
     * @return boolean
     */
    public function shouldProcess()
    {
        return true;
    }

    /**
     * Perform any processing or data manipulation needed before render.
     *
     * A response helper object will be passed to this method to allow you to
     * easily add success messages or redirects.  This helper should be used
     * to handle these kinds of actions so that you can easily test your
     * page's code.
     *
     * @param ResponseHelper $response
     */
    public function process($response)
    {

    }

    /**
     * Assign variables to your page's view and render the output.
     */
    public function render()
    {

    }

    /**
     * You can call renderView() directly from your render() method.  Or, if
     * your render method produces no output itself, the component will call
     * this method itself to automatically render your view script.
     *
     * @return string
     */
    public function renderView()
    {
        return $this->view->render($this->inflectViewScriptName());
    }

    /**
     * As the component this page belongs to for a URL matching the provided
     * page and query string parameters.  This method should always be used for
     * generating URLs in your components so that it will play nicely with
     * various WP integration points like submenus.
     *
     * @param string $page
     * @param array $params
     * @return string
     */
    public function url($page, array $params = array())
    {
        return $this->component->url($page, $params);
    }

    /**
     * Create a response helper object for this page.
     *
     * If your page would benefit from an alternative response helper with
     * additional methods relevant to your use case, you can override this
     * method and the helper will be injected into the page's process()
     * method rather than the standard helper created in PageAbstract.
     *
     * @param callable $redirector
     * @return \Dewdrop\Admin\ResponseHelper\Standard
     */
    public function createResponseHelper($redirector)
    {
        return new ResponseHelper($this, $redirector);
    }

    /**
     * Get a reference to this page's view object.
     *
     * @return \Dewdrop\View\View
     */
    public function getView()
    {
        return $this->view;
    }

    /**
     * Determine the view script name for this page by inflecting the page
     * class name to all lower case with the words separated by hyphens.
     * For example, the following class name:
     *
     * <pre>Admin\MyComponent\Index</pre>
     *
     * Would become:
     *
     * <pre>index.phtml</pre>
     *
     * @return string
     */
    private function inflectViewScriptName()
    {
        $className = get_class($this);
        $pageName  = substr($className, strrpos($className, '\\') + 1);
        $words     = preg_split('/(?=[A-Z])/', $pageName);
        $fileName  = implode('-', array_slice($words, 1));

        return strtolower($fileName . '.phtml');
    }
}
