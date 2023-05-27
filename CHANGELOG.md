# Release Notes

## Unreleased

- Fixed a bug where `craft\flysystem\base\FlysystemFs::directoryExists()` was calling `fileExists()` on the Flysystem adapter, rather than `directoryExists()`. ([#11](https://github.com/craftcms/flysystem/issues/11))

## 1.0.0 - 2022-05-03

- Initial release.
