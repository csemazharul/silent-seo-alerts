<?php

namespace SEOChangeMonitor\Providers;

if (!defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Deps\BitApps\WPKit\Hooks\Hooks;
use SEOChangeMonitor\Deps\BitApps\WPKit\Http\RequestType;
use SEOChangeMonitor\Deps\BitApps\WPKit\Http\Router\Router;
use SEOChangeMonitor\Plugin;

class HookProvider
{
    private $_pluginBackend;

    public function __construct()
    {
        $this->_pluginBackend = Config::get('BASEDIR') . DIRECTORY_SEPARATOR;
        $this->loadAppAjaxHooks();
        Hooks::addAction('rest_api_init', [$this, 'loadAppApiHooks']);
    }

    public function loadAppApiHooks()
    {
        if (
            is_readable($this->_pluginBackend . 'hooks' . DIRECTORY_SEPARATOR . 'api.php')
            && RequestType::is(RequestType::API)
        ) {
            $router = new Router(RequestType::API, Config::REST_NAMESPACE, 'v1');
            $router->setMiddlewares(Plugin::instance()->middlewares());
            include $this->_pluginBackend . 'hooks' . DIRECTORY_SEPARATOR . 'api.php';
            $router->register();
        }
    }

    protected function loadAppAjaxHooks()
    {
        if (
            RequestType::is(RequestType::AJAX)
            && is_readable($this->_pluginBackend . 'hooks' . DIRECTORY_SEPARATOR . 'ajax.php')
        ) {
            $router = new Router(RequestType::AJAX, Config::VAR_PREFIX, '');
            $router->setMiddlewares(Plugin::instance()->middlewares());
            include $this->_pluginBackend . 'hooks' . DIRECTORY_SEPARATOR . 'ajax.php';
            $router->register();
        }
    }
}
