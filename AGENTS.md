## Agent skills

### Issue tracker

Issues live in GitHub Issues for `dcaste/wp-buyer-blacklist` (via `gh`). See `docs/agents/issue-tracker.md`.

### Triage labels

Default vocabulary: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context layout — root `CONTEXT.md` + `docs/adr/`. See `docs/agents/domain.md`.

## Language

**English for all durable artifacts:** documentation, PRDs, ADRs, research notes, GitHub issues/PRs/comments that form the engineering record, product code, and code comments. Working chat with the maintainer may be another language; anything committed or filed is English.

**Run `/unslop` before writing a commit message, PR title/body, or code comment.** Durable artifacts should read as plain human writing, not AI-generated prose.

## PHP coding standards (mandatory)

All PHP changes must pass `composer lint` (PHPCS, `wp-coding-standards/wpcs`, configured in `phpcs.xml.dist`) before commit. Fix findings in the code; where a finding is a false positive, resolve it with a targeted `phpcs:ignore` comment stating why, or a ruleset property (see `phpcs.xml.dist` for examples: `custom_capabilities`, `PrefixAllGlobals` exclusions) — never a blanket sniff exclusion covering code that hasn't been checked. `composer lint:fix` (phpcbf) auto-fixes formatting; run it before hand-fixing the rest.

Pre-existing violations in code you didn't touch don't block unrelated work; track them as their own issue instead (see #76 for the missing-docblocks backlog).

## Git workflow (mandatory)

Full detail: [`docs/engineering/git-workflow.md`](docs/engineering/git-workflow.md).

**Non-negotiable rules for humans and agents:**

1. **Never commit to `main`.** Do not push to `main`. Do not open implementation PRs targeting `main`.
2. **Never commit directly to `dev`.** `dev` receives work only through pull requests.
3. **Integration branch is `dev`.** All work (code, docs, PRD, research kept in-repo) merges to `dev` first.
4. **One issue → one branch → one PR into `dev`.**
   - Branch from latest `dev`: `issue/<number>-<short-slug>` (example: `issue/14-git-branching-workflow`).
   - Open a PR **into `dev`**, not `main`.
   - PR body in English; include `Closes #<number>` and any `REQ-…` ids touched.
5. **Squash merge** the PR into `dev`. Delete the issue branch after merge.
6. **`main` is maintainer-only.** The human maintainer merges `dev` → `main` manually after verifying that `dev` works. Agents must not merge into `main`.

If you find yourself on `main` with local changes, stop, move the work onto a branch created from `dev`, and continue there.
