# Changelog

All notable changes to UniFileManager for Nova are documented here.

The project follows [Semantic Versioning](https://semver.org/).

## Unreleased

### Added

- Initial Nova adapter package scaffold.
- Config bridge for `unifilemanager/core`.
- File Manager API routes and controller actions for listing, uploading, creating folders, renaming, moving, and deleting.
- Nova Tool and UniFilePicker Field skeletons.
- Added `UniFilePicker::storageArea()` so Nova fields can target custom server-defined storage areas.

### Changed

- Require `unifilemanager/core` v0.1.4 or later for the latest object-store listing fixes.
