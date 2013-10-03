<?php

namespace Europa\Module;

interface ModuleInterface
{
  public function install();

  public function uninstall();

  public function installed();

  public function bootstrap(callable $container);

  public function ns();

  public function name();

  public function version();

  public function path();

  public function config();

  public function dependencies();
}