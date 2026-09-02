# tetherphp-core

The TetherPHP framework package. This repository is the source of truth for framework code; the
`dillonsmart/tetherphp` skeleton application consumes it as a Composer dependency.

Requires PHP >= 8.4 — property hooks, interface property declarations and parenthesis-free `new` are used throughout
and are not optional.

## Skills

Read the relevant skill in `.claude/skills/` before working here:

- **core-framework** — layout, the `project_root()` / `core_dir()` rule, PHP 8.4 features in use, adding commands and
  stubs, what belongs here versus in the skeleton.
- **core-release** — tagging, pre-1.0 version semantics, and coordinating the skeleton's constraint.

## Testing

```bash
composer install && composer test
```

The suite must stay runnable with no consuming application present.

## Keeping skills current

The skills in `.claude/skills/` are part of the source, not documentation about it. When a change makes one of them
inaccurate — a moved directory, a renamed helper, a changed release step, a new convention — update the skill in the
**same commit** as the change. A skill that has drifted is worse than no skill.
