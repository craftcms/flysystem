<?php

declare(strict_types=1);
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\flysystem\base;

use Craft;
use craft\base\Fs;
use craft\errors\FsException;
use craft\errors\FsObjectNotFoundException;
use craft\models\FsListing;
use Generator;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem as FlysystemFilesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\Visibility;
use Throwable;

/**
 * FlysystemFs provides a base implementation for Flysystem-powered filesystem types.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0.0
 */
abstract class FlysystemFs extends Fs
{
    /**
     * @var bool Whether the Flysystem adapter expects folder names to have trailing slashes
     */
    protected bool $foldersHaveTrailingSlashes = true;

    /**
     * @var FilesystemAdapter The Flysystem adapter
     * @see adapter()
     */
    private FilesystemAdapter $_adapter;

    /**
     * @inheritdoc
     */
    public function getFileList(string $directory = '', bool $recursive = true): Generator
    {
        try {
            $fileList = $this->filesystem()->listContents($directory, $recursive);
        } catch (FilesystemException $exception) {
            throw new FsException('Unable to list files' . (!empty($directory) ? " in $directory" : ''), 0, $exception);
        }

        /** @var StorageAttributes $entry */
        foreach ($fileList as $entry) {
            yield new FsListing([
                'dirname' => pathinfo($entry->path(), PATHINFO_DIRNAME),
                'basename' => pathinfo($entry->path(), PATHINFO_BASENAME),
                'type' => $entry->isDir() ? 'dir' : 'file',
                'dateModified' => $entry->lastModified(),
                'fileSize' => $entry instanceof FileAttributes ? $entry->fileSize() : null,
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function getFileSize(string $uri): int
    {
        try {
            return $this->filesystem()->fileSize($uri);
        } catch (FilesystemException | UnableToRetrieveMetadata $exception) {
            throw new FsException("Unable to get filesize for “{$uri}”", 0, $exception);
        }
    }

    /**
     * @inheritdoc
     * @throws FsException
     */
    public function getDateModified(string $uri): int
    {
        try {
            return $this->filesystem()->lastModified($uri);
        } catch (FilesystemException | UnableToRetrieveMetadata $exception) {
            throw new FsException("Unable to get date modified for “{$uri}”", 0, $exception);
        }
    }

    /**
     * @inheritdoc
     * @throws FsException
     */
    public function write(string $path, string $contents, array $config = []): void
    {
        try {
            $config = $this->addFileMetadataToConfig($config);
            $this->filesystem()->write($path, $contents, $config);
        } catch (FilesystemException | UnableToWriteFile $exception) {
            throw new FsException("Unable to write to “{$path}”", 0, $exception);
        }
    }

    /**
     * @inheritdoc
     * @throws FsException
     */
    public function read(string $path): string
    {
        try {
            $contents = $this->filesystem()->read($path);
        } catch (FilesystemException | UnableToReadFile $exception) {
            throw new FsException($exception->getMessage(), 0, $exception);
        }

        return $contents;
    }

    /**
     * @inheritdoc
     */
    public function writeFileFromStream(string $path, $stream, array $config = []): void
    {
        try {
            $config = $this->addFileMetadataToConfig($config);
            $this->filesystem()->writeStream($path, $stream, $config);
        } catch (FilesystemException | UnableToWriteFile $exception) {
            throw new FsException("Unable to write stream to “{$path}”", 0, $exception);
        }
    }

    /**
     * @inheritdoc
     */
    public function fileExists(string $path): bool
    {
        try {
            return $this->filesystem()->fileExists($path);
        } catch (FilesystemException $exception) {
            throw new FsException("Unable to check if $path exists", 0, $exception);
        }
    }

    /**
     * @inheritdoc
     */
    public function deleteFile(string $path): void
    {
        try {
            $this->filesystem()->delete($path);
        } catch (FilesystemException | UnableToDeleteFile $exception) {
            // Make a note of it, but otherwise - mission accomplished!
            Craft::info($exception->getMessage(), __METHOD__);
        }

        $this->invalidateCdnPath($path);
    }

    /**
     * @inheritdoc
     */
    public function renameFile(string $path, string $newPath): void
    {
        try {
            $this->filesystem()->move($path, $newPath);
        } catch (FilesystemException | UnableToMoveFile $exception) {
            throw new FsException($exception->getMessage(), 0, $exception);
        }

        $this->invalidateCdnPath($path);
    }

    /**
     * @inheritdoc
     */
    public function copyFile(string $path, string $newPath): void
    {
        try {
            $this->filesystem()->copy($path, $newPath);
        } catch (FilesystemException | UnableToCopyFile $exception) {
            throw new FsException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @inheritdoc
     */
    public function getFileStream(string $uriPath)
    {
        try {
            $stream = $this->filesystem()->readStream($uriPath);
        } catch (FilesystemException | UnableToReadFile $exception) {
            throw new FsException($exception->getMessage(), 0, $exception);
        }

        return $stream;
    }

    /**
     * @inheritdoc
     */
    public function directoryExists(string $path): bool
    {
        try {
            // Calling adapter directly instead of filesystem to avoid losing the trailing slash (if any)
            return $this->adapter()->directoryExists(rtrim($path, '/') . ($this->foldersHaveTrailingSlashes ? '/' : ''));
        } catch (FilesystemException $exception) {
            throw new FsException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @inheritdoc
     */
    public function createDirectory(string $path, array $config = []): void
    {
        try {
            $this->filesystem()->createDirectory($path, $config);
        } catch (FilesystemException | UnableToCreateDirectory $exception) {
            throw new FsException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @inheritdoc
     */
    public function deleteDirectory(string $path): void
    {
        try {
            $this->filesystem()->deleteDirectory($path);
        } catch (FilesystemException | UnableToDeleteDirectory $exception) {
            throw new FsException($exception->getMessage(), 0, $exception);
        }

        $this->invalidateCdnPath($path);
    }

    /**
     * @inheritdoc
     */
    public function renameDirectory(string $path, string $newName): void
    {
        // Get the list of dir contents
        $fileList = $this->getFileList($path);
        $directoryList = [$path];

        $parts = explode('/', $path);

        array_pop($parts);
        $parts[] = $newName;

        $newPath = implode('/', $parts);

        $pattern = '/^' . preg_quote($path, '/') . '/';

        $hasFiles = false;

        // Rename every file and build a list of directories
        foreach ($fileList as $listing) {
            if (!$listing->getIsDir()) {
                $objectPath = preg_replace($pattern, $newPath, $listing->getUri());
                $this->renameFile($listing->getUri(), $objectPath);
                $hasFiles = true;
            } else {
                $directoryList[] = $listing->getUri();
            }
        }

        // Rename the actual directory.
        if (!$hasFiles) {
            if (!$this->directoryExists($path)) {
                throw new FsObjectNotFoundException('No folder exists at path: ' . $path);
            }

            $this->deleteDirectory($path);
            $this->createDirectory($newPath);
        }

        // The files are moved, but the directories remain. Delete them.
        foreach ($directoryList as $dir) {
            try {
                $this->deleteDirectory($dir);
            } catch (Throwable $e) {
                // This really varies between filesystem types and whether file directories are virtual or real
                // So just in case, catch the exception, log it and then move on
                Craft::warning($e->getMessage());
                continue;
            }
        }
    }

    /**
     * Creates a Flysystem adapter instance based on the stored settings.
     *
     * @return FilesystemAdapter The Flysystem adapter.
     */
    abstract protected function createAdapter(): FilesystemAdapter;

    /**
     * Returns the Flysystem adapter instance.
     *
     * @return FilesystemAdapter The Flysystem adapter.
     */
    protected function adapter(): FilesystemAdapter
    {
        if (!isset($this->_adapter)) {
            $this->_adapter = $this->createAdapter();
        }
        return $this->_adapter;
    }

    /**
     * Creates a Flysystem filesystem configured with the Flysystem adapter.
     *
     * @param array $config
     * @return FlysystemFilesystem The Flysystem filesystem.
     */
    protected function filesystem(array $config = []): FlysystemFilesystem
    {
        // Constructing a Filesystem is super cheap and we always get the config we want, so no caching.
        return new FlysystemFilesystem($this->adapter(), $config);
    }

    /**
     * Adds file metadata to the config array.
     *
     * @param array $config
     * @return array
     */
    protected function addFileMetadataToConfig(array $config): array
    {
        return array_merge($config, [
            self::CONFIG_VISIBILITY => $this->visibility(),
        ]);
    }

    /**
     * Invalidates a CDN path on the filesystem.
     *
     * @param string $path the path to invalidate
     * @return bool Whether the operation was successful.
     */
    abstract protected function invalidateCdnPath(string $path): bool;

    /**
     * Returns the visibility setting for the filesystem.
     *
     * @return string
     */
    protected function visibility(): string
    {
        return $this->hasUrls ? Visibility::PUBLIC : Visibility::PRIVATE;
    }
}
