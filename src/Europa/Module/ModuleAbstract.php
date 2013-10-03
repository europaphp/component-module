<?php

namespace Europa\Module;
use Europa\Config;
use Europa\Filter;
use Europa\Fs;

abstract class ModuleAbstract implements ModuleInterface, AssetAwareInterface, RouteAwareInterface, ViewScriptAwareInterface
{
  const BOOTSTRAPPER = 'Bootstrapper';

  const VERSION = '0.0.0';

  protected $assets = [];

  protected $config = [];

  protected $dependencies = [];

  protected $installPath;

  protected $installPermissions = 0777;

  protected $name;

  protected $namespace;

  protected $path = '../..';

  protected $routes = [];

  protected $viewPaths = [
    ['views', 'php']
  ];

  public function __construct($config = [])
  {
    $this->initInstallPath();
    $this->initNamespace();
    $this->initName();
    $this->initPath();
    $this->initConfig($config);
    $this->initRoutes();
    $this->init();
  }

  public function init()
  {

  }

  public function install()
  {
    foreach ($this->assets() as $asset) {
      $from = $this->formatFromPath($asset);
      $to = $this->formatToPath($asset);
      $path = dirname($to);

      if (!is_dir($path) && !@mkdir($path, $this->installPermissions, true)) {
        throw new \RuntimeException(sprintf('Unable to create install directory "%s".', $path));
      }

      if (!is_file($from)) {
        throw new \UnexpectedValueException(sprintf('Cannot install asset "%s" because it does not exist.', $from));
      }

      if (!@copy($from, $to)) {
        throw new \RuntimeException(sprintf('Cannot copy asset file from "%s" to "%s".', $from, $to));
      }
    }

    return $this;
  }

  public function uninstall($cleanup = true)
  {
    foreach ($this->assets() as $asset) {
      $asset = $this->formatToPath($asset);

      if (is_file($asset) && !@unlink($asset)) {
        throw new \RuntimeException(sprintf('Cannot uninstall asset "%s".', $asset));
      }
    }

    if ($cleanup) {
      foreach ($this->assets() as $asset) {
        $path = explode(DIRECTORY_SEPARATOR, $asset)[0];
        $path = $this->formatToPath($path);

        if (is_dir($path)) {
          $this->removeEmptyDirectories($path);
        }
      }
    }

    return $this;
  }

  public function installed()
  {
    foreach ($this->assets() as $asset) {
      if (!is_file($this->formatToPath($asset))) {
        return false;
      }
    }

    return true;
  }

  public function bootstrap(callable $container)
  {
    $class = $this->namespace . '\\' . static::BOOTSTRAPPER;

    if (class_exists($class)) {
      $class = new $class;

      if (!is_callable($class)) {
        throw new Exception\BootstrapperNotCallable(sprintf(
          'The bootstrapper class "%s" must be callable.',
          get_class($class)
        ));
      }

      $class($this, $container);
    }
  }

  public function ns()
  {
    return $this->namespace;
  }

  public function name()
  {
    return $this->name;
  }

  public function version()
  {
    return static::VERSION;
  }

  public function path()
  {
    return $this->path;
  }

  public function config()
  {
    return $this->config;
  }

  public function dependencies()
  {
    return $this->dependencies;
  }

  public function assets()
  {
    return $this->assets;
  }

  public function routes()
  {
    return $this->routes;
  }

  public function viewPaths()
  {
    return $this->viewPaths;
  }

  private function formatNameToNamespace()
  {
    $filter = new Filter\ClassNameFilter;
    return $filter($this->name);
  }

  private function initInstallPath()
  {
    $this->installPath = (PHP_SAPI === 'cli' ? getcwd() . DIRECTORY_SEPARATOR : '') . dirname($_SERVER['SCRIPT_FILENAME']);
  }

  private function initNamespace()
  {
    if (!$this->namespace) {
      $this->namespace = get_class($this);
    }
  }

  private function initName()
  {
    if (!$this->name) {
      $this->name = $this->namespace;
    }

    $this->name = strtolower($this->name);
    $this->name = str_replace(['\\', '_'], '/', $this->name);
  }

  private function initPath()
  {
    $path = (new \ReflectionClass($this))->getFileName();
    $path = dirname($path);

    if ($this->path) {
      $path .= '/' . $this->path;
    }

    if (!$this->path = realpath($path)) {
      throw new Exception\InvalidPath($this->name, $path);
    }
  }

  private function initConfig($config)
  {
    if (is_string($this->config)) {
      $this->config = $this->path . '/' . $this->config;
    }

    $this->config = new Config\Config($this->config, $config);
  }

  private function initRoutes()
  {
    if (is_string($this->routes)) {
      $this->routes = $this->path . '/' . $this->routes;
    }

    $this->routes = new Config\Config($this->routes);
  }

  private function formatFromPath($from)
  {
    return $this->path() . DIRECTORY_SEPARATOR . $from;
  }

  private function formatTopath($to)
  {
    return $this->installPath . DIRECTORY_SEPARATOR . $to;
  }

  private function removeEmptyDirectories($path)
  {
    $empty = true;

    foreach (glob($path . DIRECTORY_SEPARATOR . '*') as $file) {
       $empty &= is_dir($file) && $this->removeEmptyDirectories($file);
    }

    return $empty && rmdir($path);
  }
}