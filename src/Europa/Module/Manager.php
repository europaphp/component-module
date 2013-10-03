<?php

namespace Europa\Module;
use ArrayIterator;
use Europa\Version;
use ReflectionExtension;

class Manager implements ManagerInterface
{
  const VALID_NAME_REGEX = '/[a-z][a-z0-9\-]*\/[a-z][a-z0-9\-]*/';

  private $bootstrapped = [];

  private $container;

  private $modules = [];

  public function __construct(callable $container)
  {
    $this->container = $container;
  }

  public function install()
  {
    foreach ($this->modules as $module) {
      $module->install();
    }

    return $this;
  }

  public function uninstall()
  {
    foreach ($this->modules as $module) {
      $module->uninstall();
    }

    return $this;
  }

  public function bootstrap()
  {
    foreach ($this->modules as $module) {
      $this->validate($module);
      $this->bootstrapDependencies($module);

      if (!in_array($module->name(), $this->bootstrapped)) {
        $module->bootstrap($this->container);
        $this->bootstrapped[] = $module->name();
      }
    }

    return $this;
  }

  public function add(ModuleInterface $module)
  {
    $name = $module->name();

    if (isset($this->modules[$name])) {
      throw new Exception\DuplicateModuleName(['name' => $name]);
    }

    if (!preg_match(self::VALID_NAME_REGEX, $name)) {
      throw new Exception\InvalidModuleName(['name' => $name]);
    }

    $this->modules[$name] = $module;

    return $this;
  }

  public function get($name)
  {
    if (isset($this->modules[$name])) {
      return $this->modules[$name];
    }

    throw new Exception\ModuleNotFoundException(['name' => $name]);
  }

  public function has($name)
  {
    return isset($this->modules[$name]);
  }

  public function count()
  {
    return count($this->modules);
  }

  public function getIterator()
  {
    return new ArrayIterator($this->modules);
  }

  private function validate(ModuleInterface $module)
  {
    foreach ($module->dependencies() as $name => $version) {
      if (!$this->has($name)) {
        throw new Exception\ModuleDependencyRequred([
          'name' => $name,
          'dependant' => $module->name()
        ]);
      }

      $version = new Version\SemVer($version);

      if (!$version->is($this->get($name)->version())) {
        throw new Exception\ModuleVersionRequired([
          'name' => $name,
          'version' => $this->get($name)->version(),
          'requiredVersion' => $version,
          'dependant' => $module->name()
        ]);
      }
    }
  }

  private function bootstrapDependencies(ModuleInterface $module)
  {
    foreach ($module->dependencies() as $name => $version) {
      if (!in_array($name, $this->bootstrapped)) {
        $this->get($name)->bootstrap($this->container);
        $this->bootstrapped[] = $name;
      }
    }
  }
}