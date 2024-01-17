# Release Notes

## 1.0.1 - 2024-01-17

- Added support for Craft 5
- Added config arguments to `craft\flysystem\base\FlysystemFs::renameFile()` and `craft\flysystem\base\FlysystemFs::copyFile()`. ([#12](https://github.com/craftcms/flysystem/pull/9))
- Fixed a bug where `craft\flysystem\base\FlysystemFs::directoryExists()` was calling `fileExists()` on the Flysystem adapter, rather than `directoryExists()`. ([#11](https://github.com/craftcms/flysystem/issues/11))

## 1.0.0 - 2022-05-03

- Initial release.
