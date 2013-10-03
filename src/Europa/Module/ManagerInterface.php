<?php

namespace Europa\Module;
use ArrayAccess;
use Countable;
use IteratorAggregate;

interface ManagerInterface extends Countable, IteratorAggregate
{
  public function install();

  public function uninstall();

  public function bootstrap();

  public function add(ModuleInterface $module);

  public function get($name);

  public function has($name);
}