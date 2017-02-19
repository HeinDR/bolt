<?php

namespace Bolt\Extension;

use Bolt\Asset\AssetInterface;
use Bolt\Asset\File\FileAssetInterface;
use Bolt\Asset\Snippet\SnippetAssetInterface;
use Bolt\Asset\Widget\WidgetAssetInterface;
use Bolt\Filesystem\Handler\DirectoryInterface;
use Pimple as Container;
use Silex\Application;

/**
 * Asset loading trait for an extension.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
trait AssetTrait
{
    /** @var AssetInterface[] */
    private $assets = [];
    /** @var bool */
    private $loadedAssets = false;

    /**
     * Returns a list of assets to register. Assets can be a file, snippet, or widget.
     *
     * @return AssetInterface[]
     */
    protected function registerAssets()
    {
        return [];
    }

    /**
     * Call this in register method.
     *
     * @internal
     */
    final protected function extendAssetServices()
    {
        /** @var Application $app */
        $app = $this->getContainer();

        $app['asset.queue.file'] = $app->share(
            $app->extend(
                'asset.queue.file',
                function ($queue) {
                    $this->loadAssets();

                    foreach ($this->assets as $asset) {
                        if ($asset instanceof FileAssetInterface) {
                            $queue->add($asset);
                        }
                    }

                    return $queue;
                }
            )
        );

        $app['asset.queue.snippet'] = $app->share(
            $app->extend(
                'asset.queue.snippet',
                function ($queue) {
                    $this->loadAssets();

                    foreach ($this->assets as $asset) {
                        if ($asset instanceof SnippetAssetInterface) {
                            $queue->add($asset);
                        }
                    }

                    return $queue;
                }
            )
        );

        $app['asset.queue.widget'] = $app->share(
            $app->extend(
                'asset.queue.widget',
                function ($queue) {
                    $this->loadAssets();

                    foreach ($this->assets as $asset) {
                        if ($asset instanceof WidgetAssetInterface) {
                            $queue->add($asset);
                        }
                    }

                    return $queue;
                }
            )
        );
    }

    /**
     * Merges assets returned from registerAssets() to our list.
     */
    private function loadAssets()
    {
        if ($this->loadedAssets) {
            return;
        }

        foreach ((array) $this->registerAssets() as $asset) {
            if (!$asset instanceof AssetInterface) {
                throw new \InvalidArgumentException(sprintf(
                    '%s::registerAssets() should return a list of Bolt\Asset\AssetInterface objects. Got: %s',
                    get_called_class(),
                    is_object($asset) ? get_class($asset) : gettype($asset)
                ));
            }

            $this->addAsset($asset);
        }

        $this->loadedAssets = true;
    }

    /**
     * Add an file, snippet or widget asset to the render queue.
     *
     * @param AssetInterface $asset
     */
    private function addAsset(AssetInterface $asset)
    {
        if ($asset instanceof FileAssetInterface) {
            $this->normalizeAsset($asset);
        }
        $this->assets[] = $asset;
    }
    /**
     * Add jQuery to the output.
     *
     * @deprecated Deprecated since 3.0, to be removed in 4.0.
     */
    protected function addJquery()
    {
        @trigger_error(sprintf('%s::%s is deprecated and will be removed in version 4.0.', get_called_class(), __FUNCTION__), E_USER_DEPRECATED);

        $this->getContainer()['config']->set('general/add_jquery', true);
    }

    /**
     * Don't add jQuery to the output.
     *
     * @deprecated Deprecated since 3.0, to be removed in 4.0.
     */
    protected function disableJquery()
    {
        @trigger_error(sprintf('%s::%s is deprecated and will be removed in version 4.0.', get_called_class(), __FUNCTION__), E_USER_DEPRECATED);

        $this->getContainer()['config']->set('general/add_jquery', false);
    }

    /**
     * Normalizes the path and package name of the asset file.
     *
     * @param FileAssetInterface $asset
     */
    private function normalizeAsset(FileAssetInterface $asset)
    {
        $path = $asset->getPath();
        if ($path === null) {
            throw new \RuntimeException('Extension file assets must have a path set.');
        }

        if ($asset->getPackageName()) {
            return;
        }

        if ($this->isAbsoluteUrl($path)) {
            // Set asset to a package, since there is no default.
            // It doesn't matter which since it is absolute.
            $asset->setPackageName('extensions');

            return;
        }

        $file = $this->getWebDirectory()->getFile($asset->getPath());
        if ($file->exists()) {
            $asset->setPackageName('extensions')->setPath($file->getPath());

            return;
        }

        $app = $this->getContainer();

        $themeFile = $app['filesystem']->getFile(sprintf('theme://%s', $path));
        if ($themeFile->exists()) {
            $asset->setPackageName('theme')->setPath($path);

            return;
        }

        $message = sprintf(
            "Couldn't add file asset '%s': File does not exist in either %s or %s directories.",
            $path,
            $this->getWebDirectory()->getFullPath(),
            $themeFile->getFullPath()
        );
        $app['logger.system']->error($message, ['event' => 'extensions']);
    }

    /**
     * @see \Symfony\Component\Asset\Package::isAbsoluteUrl
     *
     * @param string $url
     *
     * @return bool
     */
    private function isAbsoluteUrl($url)
    {
        return false !== strpos($url, '://') || '//' === substr($url, 0, 2);
    }

    /** @return Container */
    abstract protected function getContainer();

    /** @return string */
    abstract protected function getName();

    /** @return DirectoryInterface */
    abstract protected function getBaseDirectory();

    /** @return DirectoryInterface */
    abstract protected function getWebDirectory();
}
