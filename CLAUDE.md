# CLAUDE.md

This file provides guidance for Claude Code when working in this repository.

Project overview and usage live in [README.md](README.md) /
[README.ja.md](README.ja.md) — keep the two in sync.

## Release procedure

- Tags are `vX.Y.Z` (e.g. `v1.1.0`), annotated + signed (`git tag -s -m`),
  and use semver: features → minor, fix-only → patch. Packagist picks new
  tags up automatically via the GitHub integration — no manual submission.
- **A pushed release tag is immutable** — the package is consumed via
  Packagist, where moving or deleting a published tag is forbidden. Never
  retag; if a released tag is wrong, cut a new higher version.
