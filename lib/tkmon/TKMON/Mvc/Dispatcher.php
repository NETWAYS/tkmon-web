<?php
/**
 * This file is part of TKMON
 *
 * TKMON is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * TKMON is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with TKMON.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Marius Hein <marius.hein@netways.de>
 * @copyright 2012-2015 NETWAYS GmbH <info@netways.de>
 */

namespace TKMON\Mvc;

/**
 * Dispatcher to handle the (web) request
 * @package TKMON\Mvc
 * @author Marius Hein <marius.hein@netways.de>
 */
class Dispatcher
{

    const ACTION_PREFIX = 'action';

    const SECURITY_PREFIX = 'security';

    const TEMPLATE_PREFX = 'template';

    /**
     * DI container
     * @var null|Pimple
     */
    private $container = null;

    /**
     * Current class
     * @var string
     */
    private $class = '';

    /**
     * Current action
     * @var string
     */
    private $action = '';

    /**
     * Current URI
     * @var string
     */
    private $uri = '';

    /**
     * Creates a new dispatcher
     * @param \Pimple $container
     */
    public function __construct(\Pimple $container)
    {
        $this->container = $container;
        $this->initializeData();
    }

    /**
     * Setter for action name
     * @param string $action
     */
    public function setAction($action)
    {
        $this->action = $action;
    }

    /**
     * Getter for action
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * Setter for class
     * @param string $class
     */
    public function setClass($class)
    {
        $this->class = $class;
    }

    /**
     * Getter for class
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * Setter for the container
     * @param \Pimple $container
     */
    public function setContainer(\Pimple $container)
    {
        $this->container = $container;
    }

    /**
     * Getter for container
     * @return \Pimple
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Setter for URI
     * @param string $uri
     */
    public function setUri($uri)
    {
        $this->uri = $uri;
    }

    /**
     * Getter for URI
     * @return string
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * Method decides which URI model is currently working and
     * returns the url
     * @return string
     */
    private function determineUri()
    {
        $params = $this->container['params'];
        $config = $this->container['config'];

        if ($config->get('web.rewrite', false) === true) {
            $uri = '/'. preg_replace(
                '@^'. preg_quote($config['web.path'], '@'). '@',
                '',
                $params->getParameter('REQUEST_URI', null, 'header')
            );

            // We do not need CGI params here
            $uri = preg_replace('@\?[^$]+$@', '', $uri);

            return $uri;
        } else {
            if ($params->hasParameter('path')) {
                return $params->getParameter('path');
            } elseif ($params->hasParameter('PATH_INFO', 'header')) {
                return $params->getParameter('PATH_INFO', null, 'header');
            }
        }
    }

    /**
     * Fills the object with db (action, class) from URL
     */
    private function initializeData()
    {
        $this->uri = $this->determineUri();

        if ($this->uri == '/' || !$this->uri) {
            $parts = array('Index', 'Index');
        } else {
            $parts = explode('/', $this->uri);
            array_shift($parts);
        }

        $this->action = array_pop($parts);
        $this->class = $this->container['config']->get('mvc.action.namespace') . '\\' . implode('\\', $parts);
    }

    /**
     * Calls our action in class and setup template to display
     * @return string
     */
    public function dispatchRequest()
    {
        try {
            $reflectionClass = $this->getActionReflection($this->class);

            /** @var $object \TKMON\Action\Base */
            $object = $reflectionClass->newInstance();

            /*
             * External configuration
             */
            $object->setContainer($this->container);

            /*
             * Object is ready, want to initialize?
             */
            $object->init();

            /**
             * If this is a public action
             */
            if ($this->passUserSecurityTest($this->action, $reflectionClass, $object) === false) {
                throw new \TKMON\Exception\DispatcherException(
                    'Action needs authenticated user: '. $this->action,
                    \TKMON\Exception\DispatcherException::TYPE_UNAUTHORIZED
                );
            }

            $content = null;

            if (($data = $this->getSimpleTemplate($this->action, $reflectionClass, $object))) {
                $content = $data;
            } else {
                $reflectionMethod = $this->getActionMethod($reflectionClass, $this->action);
                $content = $reflectionMethod->invoke($object, $this->container['params']->getArrayObject('request'));
            }

            $templateParams = $object->getTemplateParams();

            if (is_object($content) && $content instanceof \TKMON\Mvc\Output\DataInterface) {
                if ($this->isAjaxRequest()) {
                    if ($content instanceof  \TKMON\Mvc\Output\Json) {
                        header('Content-type: application/json; charset=utf-8', true);
                    }
                    return $content->toString();
                } else {
                    return $this->renderTemplate($content->toString(), $templateParams);
                }
            }

            throw new \TKMON\Exception\DispatcherException(
                'Output is not type of DataInterface',
                \TKMON\Exception\DispatcherException::TYPE_OUTPUT
            );
        } catch (\TKMON\Exception\DispatcherException $e) {

            /**
             * Try to catch known exceptions
             */
            if (!$this->isAjaxRequest()) {
                if ($e->getCode() === \TKMON\Exception\DispatcherException::TYPE_UNAUTHORIZED) {
                    $response = new \TKMON\Mvc\Output\TwigTemplate($this->container['template']);
                    $response->setTemplateName('views/Common/SessionExpired.twig');
                    $response['referer'] = $this->uri;
                    return $this->renderTemplate($response->toString());
                }
            }

            if ($this->isAjaxRequest()) {
                $response = new \TKMON\Mvc\Output\JsonResponse();
                $response->setSuccess(false);
                $response->addException($e);
                return $response->toString();
            } else {
                $response = new \TKMON\Mvc\Output\TwigTemplate($this->container['template']);
                $response->setTemplateName('views/exception.twig');
                $response['exception'] = $e;
                $response['type'] = get_class($e);
                $response['text'] = (string)$e;
                return $this->renderTemplate($response->toString());
            }
        }
    }

    /**
     * Test the header if we are an ajax request or not
     * @return bool
     */
    private function isAjaxRequest()
    {
        $testAjax = $this->container['params']->getParameter('HTTP_X_REQUESTED_WITH', false, 'header');
        return ($testAjax && strtolower($testAjax) === 'xmlhttprequest')
            ? true : false;
    }

    /**
     * Renders the template
     * @param $content The inner part of the template
     * @param array $templateParams Assing values to outer template
     * @return string
     */
    private function renderTemplate($content, array $templateParams = null)
    {
        $template = $this->container['template']->loadTemplate($this->container['config']->get('template.file'));

        $default = array(
            'content' => $content,
            'user' => $this->container['user'],
            'config' => $this->container['config'],
            'navigation' => $this->container['navigation']
        );

        if ($templateParams === null) {
            $templateParams = $default;
        } else {
            $templateParams = $templateParams + $default;
        }

        return $template->render($templateParams);
    }

    /**
     * Return the action method as ReflectionMethod
     * @param \ReflectionClass $class
     * @param string $actionName
     * @return \ReflectionMethod
     * @throws \TKMON\Exception\DispatcherException
     */
    private function getActionMethod(\ReflectionClass $class, $actionName)
    {
        $methodName = self::ACTION_PREFIX . $actionName;
        if ($class->hasMethod($methodName)) {
            return $class->getMethod($methodName);
        }

        throw new \TKMON\Exception\DispatcherException(
            'Method not found: ' . $methodName,
            \TKMON\Exception\DispatcherException::TYPE_METHOD
        );
    }

    /**
     * Returns the class as ReflectionClass object
     * @param $className
     * @throws \TKMON\Exception\DispatcherException
     * @return \ReflectionClass
     */
    private function getActionReflection($className)
    {

        if (class_exists($className)) {
            $reflection = new \ReflectionClass($className);

            if ($reflection->getParentClass()->getName() === 'TKMON\Action\Base') {
                return $reflection;
            }

            throw new \TKMON\Exception\DispatcherException(
                'Parent class is not "TKMON\Action\Base"',
                \TKMON\Exception\DispatcherException::TYPE_PARENT
            );
        }

        throw new \TKMON\Exception\DispatcherException(
            'Could not load class from URI: ' . $className,
            \TKMON\Exception\DispatcherException::TYPE_NOTFOUND
        );
    }

    /**
     * Tests for user access to action
     * @param string $action
     * @param \ReflectionClass $reflection
     * @param \TKMON\Action\Base $object
     * @return bool
     */
    private function passUserSecurityTest($action, \ReflectionClass $reflection, \TKMON\Action\Base $object)
    {
        $methodName = self::SECURITY_PREFIX. $action;
        $test = true; // Default, user needs to be authenticated

        if ($reflection->hasMethod($methodName)) {
            $method = $reflection->getMethod($methodName);
            $test = $method->invoke($object);
        }

        $user = $this->container['user'];

        if ($test === true && $user->getAuthenticated() !== true) {
            return false;
        }

        return true;
    }

    /**
     * Reflection test to display simple template only
     *
     * @param string $action
     * @param \ReflectionClass $reflection
     * @param \TKMON\Action\Base $object
     * @return \TKMON\Mvc\Output\TwigTemplate
     * @throws \TKMON\Exception\DispatcherException
     */
    private function getSimpleTemplate($action, \ReflectionClass $reflection, \TKMON\Action\Base $object)
    {
        $methodName = self::TEMPLATE_PREFX. $action;

        if ($reflection->hasMethod($methodName)) {
            $method = $reflection->getMethod($methodName);
            $templateName = $method->invoke($object);

            if ($templateName && is_string($templateName)) {
                $template = new \TKMON\Mvc\Output\TwigTemplate($this->container['template']);
                $template->setTemplateName($templateName);
                $template['user'] = $this->container['user'];
                return $template;
            }

            throw new \TKMON\Exception\DispatcherException(
                "Template from action '$action' is not configured",
                \TKMON\Exception\DispatcherException::TYPE_MISC
            );
        }
    }
}
