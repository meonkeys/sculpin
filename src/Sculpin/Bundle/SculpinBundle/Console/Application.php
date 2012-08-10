<?php

/*
 * This file is a part of Sculpin.
 *
 * (c) Dragonfly Development Inc.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sculpin\Bundle\SculpinBundle\Console;

use Composer\Autoload\ClassLoader;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Application
 *
 * @author Beau Simensen <beau@dflydev.com>
 */
class Application extends BaseApplication
{
    const DEFAULT_ROOT_DIR = '.';

    /**
     * Constructor.
     *
     * @param KernelInterface $kernel      A KernelInterface instance
     * @param ClassLoader     $classLoader Class Loader
     */
    public function __construct(KernelInterface $kernel, ClassLoader $classLoader)
    {
        $this->classLoader = $classLoader;
        $this->kernel = $kernel;
        $obj = new \ReflectionClass($this->classLoader);
        $this->kernel->setInternalVendorRoot($this->internalVendorRoot = dirname(dirname($obj->getFileName())));

        parent::__construct('Sculpin', Kernel::VERSION.' - '.$kernel->getName().'/'.$kernel->getEnvironment().($kernel->isDebug() ? '/debug' : ''));

        $this->getDefinition()->addOption(new InputOption('--root-dir', null, InputOption::VALUE_REQUIRED, 'The root directory.', self::DEFAULT_ROOT_DIR));
        $this->getDefinition()->addOption(new InputOption('--env', '-e', InputOption::VALUE_REQUIRED, 'The Environment name.', $kernel->getEnvironment()));
        $this->getDefinition()->addOption(new InputOption('--no-debug', null, InputOption::VALUE_NONE, 'Switches off debug mode.'));
    }

    /**
     * {@inheritdoc}
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $rawRootDir = $input->getParameterOption('--root-dir') ?: self::DEFAULT_ROOT_DIR;

        if (!file_exists($rawRootDir)) {
            throw new \RuntimeException(sprintf('Specified root dir "%s" does not exist', $rawRootDir));
        }

        $rootDir = realpath($rawRootDir);

        if ($autoloadNamespacesFile = realpath($rootDir.'/vendor/composer/autoload_namespaces.php')) {
            if ($this->internalVendorRoot != dirname(dirname($autoloadNamespacesFile))) {
                // We have an autoload file that is *not* the same as the
                // autoload that bootstrapped this application.
                $map = require $autoloadNamespacesFile;
                foreach ($map as $namespace => $path) {
                    $this->classLoader->add($namespace, $path);
                }
            }
        }

        // TODO: Move this to SculpinComposerBundle
        //if (strpos($this->internalVendorRoot, 'phar://')==0 || false===strpos($this->internalVendorRoot, $rootDir)) {
            // If our vendor root does not contain our project root then we
            // can assume that we should enable the internally installed
            // repository.
            //$this->internallyInstalledRepositoryEnabled = true;
        //}

        $this->registerCommands();

        parent::doRun($input, $output);
    }

    /**
     * Get Kernel
     *
     * @return Kernel
     */
    public function getKernel()
    {
        return $this->kernel;
    }

    protected function registerCommands()
    {
        $this->kernel->boot();

        foreach ($this->kernel->getBundles() as $bundle) {
            if ($bundle instanceof BundleInterface) {
                $bundle->registerCommands($this);
            }
        }
    }
}