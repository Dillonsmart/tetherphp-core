# Releasing tetherphp-core

> Read this when tagging a release, when a change would break consumers, or when deciding what version number a change deserves.

`dillonsmart/tetherphp-core` is published to Packagist from this repository's git tags. There is no build step and no
split — a tag is the release.

## Before tagging

1. `composer test` passes.
2. Verify the package resolves **as an installed dependency**, not just in a linked checkout. Path-resolution bugs are
   invisible when the package is symlinked. Against a skeleton checkout:
   ```bash
   php -r 'require "vendor/autoload.php";
     echo core_dir(), "\n", project_root(), "\n";
     var_dump(file_exists(core_dir()."/Stubs/Action.txt"));'
   ```
   `core_dir()` must land inside `vendor/dillonsmart/tetherphp-core/src/framework` and the stub must exist.
3. `php tether help` in the skeleton lists every built-in command — a command that fails `class_exists()` or the
   `Command` subclass check vanishes silently rather than erroring.

## Tagging

```bash
git tag vX.Y.Z
git push origin main --tags
```

Packagist picks the tag up from the repository hook.

## Never move a published tag

Packagist reads a version **once**. Force-updating a tag to point at a different commit does not make Packagist
re-read it — the old tree stays published under that version number, and anyone who already resolved it has it in
their lock file.

This has happened: `v0.3.0` was tagged on the wrong commit, force-moved to the right one, and Packagist went on
serving the original tree regardless.

So if a tag goes out wrong, **burn the version and release the next patch**. Do not move it. The bad version stays
on Packagist, which means consumers must skip past it — constrain to `^0.3.1`, not `^0.3`, because `^0.3` would
happily resolve the broken `0.3.0`. Delete the bad version from the Packagist UI if you can.

## Versioning while pre-1.0

Composer treats `^0.Y.Z` as locked to the `0.Y` series, so **the minor number is the breaking-change signal**: `^0.3`
will not accept `0.4.0`. Bump the minor for anything a consumer can trip over:

- moving or renaming anything under `src/` that changes the PSR-4 path of a public class
- changing the autoload roots in `composer.json`
- changing what a path helper resolves to
- changing a stub's placeholders, or the namespace generated code is emitted into
- raising the PHP requirement

Patch releases are for changes no consumer can observe structurally.

## Coordinating with the skeleton

The skeleton declares a constraint on this package in its `composer.json`, and `composer create-project
dillonsmart/tetherphp` resolves it fresh from Packagist. So:

**A breaking release here is not complete until the skeleton's constraint is bumped and pushed.** Between the two, a
fresh `create-project` either resolves an old core against a new skeleton or fails to resolve at all.

Order of operations:

1. Tag and push core.
2. Bump `dillonsmart/tetherphp-core` in the skeleton's `composer.json`.
3. Verify a clean resolve in a scratch directory, with no local path overlay in play:
   ```bash
   composer create-project dillonsmart/tetherphp /tmp/tether-check
   ```
4. Tag the skeleton.

If the skeleton's constraint names a version that is not yet tagged here, `create-project` is broken for everyone —
that is the failure mode to check for first when someone reports a fresh install not working.

## Keeping this guide current

If the release process changes — a build step appears, versioning moves to 1.x and the major becomes the breaking
signal, or the skeleton's coordination changes — update this file in the same commit.
