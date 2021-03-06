<?php

namespace Drupal\cypress;


use Symfony\Component\Filesystem\Filesystem;

/**
 * Manages the Cypress runtime directory.
 *
 * Responsible for directory creation, config file updates and collection of
 * test requirements across different test suites.
 *
 * @package Drupal\cypress
 */
class CypressRuntime implements CypressRuntimeInterface {

  /**
   * A filesystem component for various operations.
   *
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  protected $fileSystem;

  /**
   * The root directory where config files and tests should be collected.
   * @var string
   */
  protected $cypressRoot;

  /**
   * List of paths to support files.
   *
   * @var string[]
   */
  protected $support = [];

  /**
   * List of paths to plugin files.
   *
   * @var string[]
   */
  protected $plugins = [];

  /**
   * List of npm dependencies and versions.
   *
   * @var string[]
   */
  protected $dependencies = [];

  /**
   * A npm project manager to install test suite dependencies.
   *
   * @var \Drupal\cypress\NpmProjectManagerInterface
   */
  protected $npmProjectManager;

  /**
   * Generate the filesystem instance.
   *
   * @return \Symfony\Component\Filesystem\Filesystem
   */
  protected function getFileSystem() {
    return new Filesystem();
  }

  /**
   * CypressRuntime constructor.
   *
   * @param string $cypressRoot
   *   The absolute path the Cypress runtime should reside in.
   */
  public function __construct($cypressRoot, NpmProjectManagerInterface $npmProjectManager) {
    $this->cypressRoot = $cypressRoot;
    $this->npmProjectManager = $npmProjectManager;
    $this->fileSystem = $this->getFileSystem();
  }

  /**
   * {@inheritDoc}
   */
  public function initiate(CypressOptions $options) {
    if (!$this->fileSystem->exists($this->cypressRoot)) {
      $this->fileSystem->mkdir($this->cypressRoot);
    }

    if ($this->fileSystem->exists($this->cypressRoot . '/integration')) {
      $this->fileSystem->remove($this->cypressRoot . '/integration');
    }

    if ($this->fileSystem->exists($this->cypressRoot . '/suites')) {
      $this->fileSystem->remove($this->cypressRoot . '/suites');
    }

    if ($this->fileSystem->exists($this->cypressRoot . '/support')) {
      $this->fileSystem->remove($this->cypressRoot . '/support');
    }

    if ($this->fileSystem->exists($this->cypressRoot . '/plugins')) {
      $this->fileSystem->remove($this->cypressRoot . '/plugins');
    }

    $this->fileSystem->mkdir($this->cypressRoot . '/integration');
    $this->fileSystem->mkdir($this->cypressRoot . '/integration/common');
    $this->fileSystem->mkdir($this->cypressRoot . '/suites');

    $this->fileSystem->dumpFile($this->cypressRoot . '/cypress.json', $options->getCypressJson());
    $this->fileSystem->dumpFile($this->cypressRoot . '/plugins.js', $this->generatePluginsJs());
    $this->fileSystem->dumpFile($this->cypressRoot . '/support.js', $this->generateSupportJs());
  }

  /**
   * {@inheritDoc}
   */
  public function addSuite($name, $path) {
    if (!$this->fileSystem->exists($path)) {
      return FALSE;
    }

    if ($this->fileSystem->exists($path)) {
      $this->fileSystem->symlink(
        $path,
        $this->cypressRoot . '/suites/' . $name
      );
    }

    if ($this->fileSystem->exists($path . '/integration')) {
      $this->fileSystem->symlink(
        $path . '/integration',
        $this->cypressRoot . '/integration/' . $name
      );
    }

    if ($this->fileSystem->exists($path . '/steps')) {
      $this->fileSystem->symlink(
        $path . '/steps',
        $this->cypressRoot . '/integration/common/' . $name
      );
    }

    if ($this->fileSystem->exists($path . '/support/index.js')) {
      $this->support[] = $name;
      $this->fileSystem->mirror($path . '/support', $this->cypressRoot . '/support/' . $name);
      $this->fileSystem->dumpFile($this->cypressRoot . '/support.js', $this->generateSupportJs());
    }

    if ($this->fileSystem->exists($path . '/plugins/index.js')) {
      $this->plugins[] = $name;
      $this->fileSystem->mirror($path . '/plugins', $this->cypressRoot . '/plugins/' . $name);
      $this->fileSystem->dumpFile($this->cypressRoot . '/plugins.js', $this->generatePluginsJs());
    }

    if ($this->fileSystem->exists($path . '/package.json')) {
      $this->npmProjectManager->merge($path . '/package.json');
    }
  }

  /**
   * Generate a javascript file that imports plugins.
   *
   * @return string
   *   The content for a local plugins/index.js.
   */
  private function generatePluginsJs() {
    $plugins = array_map(
      function ($file) {
        return "  require('./plugins/{$file}/index.js')(on, config);";
      },
      $this->plugins
    );
    array_unshift($plugins,
      '// Automatically generated by the Cypress module for Drupal.',
      'module.exports = (on, config) => {'
    );
    $plugins[] = '};';
    return implode("\n", $plugins);
  }

  /**
   * Generate a javascript file that imports `index` from given folders.
   *
   * @return string
   *   The content for a local index.js.
   */
  protected function generateSupportJs() {
    $index = array_map(function ($name) {
      return "require('./support/$name/index.js');";
    }, $this->support);
    array_unshift(
      $index,
      '// Automatically generated by the Cypress module for Drupal.'
    );
    return implode("\n", $index);
  }
}
