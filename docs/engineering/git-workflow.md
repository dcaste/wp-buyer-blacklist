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

## Branch protection

The social rule in `AGENTS.md` applies even where protection is missing. These GitHub rulesets make it automatic. Create them in **Settings > Rules > Rulesets > New ruleset > New branch ruleset**. Set **Enforcement status** to **Active**.

| | `main` | `dev` |
|---|---|---|
| Bypass list | empty | empty |
| Restrict deletions | on | on |
| Block force pushes | on | on |
| Require a pull request before merging | on, 0 approvals | on, 0 approvals |
| Allowed merge methods | **Merge** only | **Squash** only |
| Required status check | **Check source branch** | none |

Use 0 approvals because a project with one maintainer can't approve its own pull request.

### Only `dev` can be merged into `main`

A branch rule decides who can write to a branch and which checks must pass. It doesn't know which branch a pull request comes from. The workflow `.github/workflows/main-source-branch.yml` fills that gap. For every pull request into `main` it runs the check **Check source branch**, which fails unless the head branch is `dev` in this same repository. A fork's branch named `dev` doesn't pass.

The check only works as a rule once it is **required**:

1. Merge the workflow into `dev`, then merge `dev` into `main`. That pull request is the first run of the check.
2. Create the `main` ruleset and add **Check source branch** under **Require status checks to pass**. The name is not in the list until the check has run once.

If you rename the job, update the required check in the ruleset too.

## Checklist for an agent starting work

- [ ] Read the issue; confirm base branch is `dev`
- [ ] `git checkout dev && git pull && git checkout -b issue/<n>-…`
- [ ] Implement / document
- [ ] PR → `dev`, English body, `Closes #<n>`
- [ ] Squash merge when accepted
- [ ] Do **not** touch `main`

## Releases

1. On an issue branch from `dev`, set the version in the `Version:` header of `wp-buyer-blacklist.php` and in the fallback in `src/Admin/AdminPage.php`, and add the version to `CHANGELOG.md`. Also update `Stable tag` and the changelog in `readme.txt`, which ships in the ZIP. Merge it into `dev` like any other change.
2. The maintainer merges `dev` into `main`.
3. On `main`, run `bin/build-zip.sh`. It builds `dist/wp-buyer-blacklist-<version>.zip` from the files not listed in `.distignore`. It stops if `build/` or `assets/admin/` has uncommitted changes.
4. Tag the merge commit `v<version>` and create a GitHub release with the ZIP attached.
