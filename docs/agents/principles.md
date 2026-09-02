# The six core principles of TetherPHP

> Read this when adding a feature or API, choosing between implementation approaches, reviewing a change, deciding whether something belongs in the core at all, or when a change adds configuration, indirection or a second way to do an existing thing.

These govern what goes into the framework and how it is shaped. They are design constraints, not marketing. When a
change conflicts with one, the change is wrong until argued otherwise — and the argument belongs in the commit
message.

## 1. Human First

**Code should be obvious to a human.** A developer opening an unfamiliar TetherPHP project should quickly understand
what it does and where things belong. No cleverness for cleverness' sake.

In practice: prefer a longer, plainer implementation over a dense one. Name things for what they are. If explaining a
mechanism takes a paragraph, the mechanism is probably wrong.

## 2. Agent Ready

**Everything a human can understand, an agent should be able to understand.** Architecture, conventions and tooling
are deliberately designed for AI agents as well as people: predictable naming, explicit dependencies, consistent
structure, machine-readable context.

In practice: a file's location should be derivable from its name and vice versa. Behaviour should be discoverable by
reading the code rather than by knowing folklore. Anything an agent would need to *guess* is a design defect —
`AGENTS.md`, the skills in `.claude/skills/`, and the `tether` tooling exist to remove guessing.

## 3. Explicit Over Magic

**If something matters, make it visible.** This flow must be traceable end to end without knowing implicit framework
behaviour:

```
Request → Route → Action → Domain → Responder → Response
```

In practice: no auto-discovery that a reader cannot follow, no behaviour triggered by naming coincidence, no global
state written in one place and read in another. If a feature requires a "how did that happen?" answer, it fails this
principle. Constructor arguments beat container lookups; a returned value beats a side effect.

## 4. One Obvious Way

**There should be one obvious way to do things.** TetherPHP is opinionated: one clear convention rather than five
approaches to the same problem. This reduces cognitive load for developers and agents alike.

In practice: adding a second way to do something already supported requires removing the first. Options and config
flags are the usual smell — each one multiplies the states a reader has to hold. Ask "what does this let someone do
that they cannot already do?" and if the answer is "the same thing, differently", reject it.

## 5. Small & Composable

**The core should do less, but do it well.** The core provides the fundamental building blocks; additional
functionality composes in as packages. Small core, strong foundations.

In practice: the bar for a new core dependency is very high, and the bar for a new core *concept* is higher. Before
adding something, ask whether it could live as a separate package. Database layers, templating engines, queues,
mailers and validation are all candidates for packages rather than core.

## 6. Tools Are Part of the Framework

**The framework should help you understand the application, not just run it.** The CLI is first-class, not an
afterthought — commands like `make:action`, `test`, `inspect`, `explain` and `context` help developers and agents
build, understand and maintain an application.

In practice: a new runtime feature is not finished until the tooling can show it. If routing gains a capability,
something must be able to *display* the resulting route table. Tooling output is an interface: keep it stable,
scriptable and honest about exit codes.

## Applying them

They will conflict. The usual tension is **Small & Composable** against **Tools Are Part of the Framework** — rich
tooling is a lot of surface area. Resolve it by keeping the *runtime* small and letting tooling be generous, since
tooling is not a dependency of the running application.

The second common tension is **Explicit Over Magic** against ergonomics: explicit code is more typing. Resolve it with
code generation rather than runtime magic — `tether make:*` writes the explicit code so the developer does not have
to, and what runs is still what they can read.

## Review checklist

For any change to the framework:

- Could someone unfamiliar read this and predict what it does?
- Could an agent locate and modify this without folklore?
- Can the Request → Response path still be traced by reading?
- Does this add a second way to do an existing thing?
- Does this belong in the core, or could it be a package?
- Is there tooling that makes this visible?

## Keeping this guide current

If a principle is changed, added or dropped, update this file, both repositories' `AGENTS.md`, and any guide that
cites them, in the same commit.
