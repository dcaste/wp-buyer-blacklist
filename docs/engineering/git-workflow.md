# Git workflow

This repository uses a **two-step promotion** model so AI agents and contributors never land unreviewed work on `main`.

## Branches

| Branch | Role |
|--------|------|
| `main` | Known-good line. Updated **only** by the human maintainer after verifying `dev`. |
| `dev` | Integration branch. Default target for all PRs. |
| `issue/<n>-<slug>` | Work branch for a single GitHub issue. |

There is no separate long-lived `release` branch for now. `main` is what the maintainer has accepted.

## Happy path

```text
git fetch origin
git checkout dev
git pull origin dev
git checkout -b issue/12-short-slug

# ... work, commit ...

git push -u origin HEAD
gh pr create --base dev --title "…" --body "Closes #12"
# After review/checks: squash merge into dev, delete branch
```

Maintainer (human), when `dev` is verified:

```text
# merge dev → main locally or via GitHub UI (maintainer choice)
# agents must not perform this step
```

## Squash merge

**Squash** means: all commits on the issue branch become **one** commit on `dev` when the PR merges. The noisy “wip / fix typo / try again” history stays off `dev`. Prefer squash for every issue PR into `dev`.

## Rules (mandatory)

1. **No direct commits to `main` or `dev`.**
2. **All durable changes go through a PR into `dev` first** — product code, docs, PRD, agent config, research notes that remain in the repo.
3. **One implementation (or docs) issue → one branch → one PR → squash into `dev`.**
4. **PR base is always `dev`.** Never target `main` for day-to-day work.
5. **Branch naming:** `issue/<number>-<short-slug>` using the GitHub issue number.
6. **PR language:** English. Reference `Closes #<n>` and PRD requirement ids (`REQ-…`) when applicable.
7. **Agents never merge `dev` → `main`.**

## What about planning / wayfinder / research?

- Product decisions and specs still use GitHub Issues (wayfinder map, etc.).
- When those decisions produce **files in the repo**, land the files via the same git rules (branch from `dev`, PR into `dev`, squash).
- Throwaway exploration may use a short-lived branch; if the artifact should stay, bring it into `dev` through a PR tied to an issue.

## Branch protection (recommended)

On GitHub, protect:

- **`main`:** no direct pushes; no merges except by the maintainer; do not allow agents to bypass.
- **`dev`:** no direct pushes; require pull request; allow squash merge.

Exact click-path depends on the repo’s GitHub plan; the social rule in `AGENTS.md` applies even before protection is enabled.

## Checklist for an agent starting work

- [ ] Read the issue; confirm base branch is `dev`
- [ ] `git checkout dev && git pull && git checkout -b issue/<n>-…`
- [ ] Implement / document
- [ ] PR → `dev`, English body, `Closes #<n>`
- [ ] Squash merge when accepted
- [ ] Do **not** touch `main`

## Releases

1. On an issue branch from `dev`, set the version in the `Version:` header of `wp-buyer-blacklist.php` and in the fallback in `src/Admin/AdminPage.php`, and add the version to `CHANGELOG.md`. Merge it into `dev` like any other change.
2. The maintainer merges `dev` into `main`.
3. On `main`, run `bin/build-zip.sh`. It builds `dist/wp-buyer-blacklist-<version>.zip` from the files not listed in `.distignore`. It stops if `build/` or `assets/admin/` has uncommitted changes.
4. Tag the merge commit `v<version>` and create a GitHub release with the ZIP attached.
