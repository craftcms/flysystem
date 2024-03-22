# Release Notes

## 1.0.2 - 2024-03-22

- Fixed a bug where CDN invalidations would not be triggered when an asset was renamed or replaced. ([#14](https://github.com/craftcms/flysystem/pull/14))

## 1.0.1 - 2024-01-17

- Fixed a bug where `craft\flysystem\base\FlysystemFs::directoryExists()` was calling `fileExists()` on the Flysystem adapter, rather than `directoryExists()`. ([#11](https://github.com/craftcms/flysystem/issues/11))

## 1.0.0 - 2022-05-03

- Initial release.
