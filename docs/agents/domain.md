# Domain Docs

How the engineering skills should consume this repo's domain documentation when exploring the codebase.

## Layout: single-context

```
/
├── CONTEXT.md            ← domain glossary + project context (created lazily)
├── docs/adr/             ← accepted architecture decisions
│   ├── 0002-stay-on-stormweb-shared-hosting.md
│   ├── 0005-inertia-instead-of-spa-plus-api.md
│   └── 0007-dev-staging-deploy-strategy.md
└── ...
```

## Before exploring, read these

- **`CONTEXT.md`** at the repo root — domain glossary and project context.
- **`docs/adr/`** — read ADRs that touch the area you're about to work in.

If `CONTEXT.md` doesn't exist yet, **proceed silently**. Don't flag its absence; don't suggest creating it upfront. The producer skill (`/grill-with-docs`) creates it lazily when terms or decisions actually get resolved.

## Use the glossary's vocabulary

When your output names a domain concept (in an issue title, a refactor proposal, a hypothesis, a test name), use the term as defined in `CONTEXT.md`. Don't drift to synonyms the glossary explicitly avoids.

If the concept you need isn't in the glossary yet, that's a signal — either you're inventing language the project doesn't use (reconsider) or there's a real gap (note it for `/grill-with-docs`).

## Flag ADR conflicts

If your output contradicts an existing ADR, surface it explicitly rather than silently overriding:

> _Contradicts ADR-0007 (dev/staging deploy strategy) — but worth reopening because…_
