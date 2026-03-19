# Git Workflow

This project already has a local Git repository and a clean baseline commit history.

## Current Branch

Use `main` as the stable branch.

## Safe Daily Workflow

Check current status:

```powershell
git status --short --branch
```

Save your current work:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\git-save.ps1 "feat: short summary of changes"
```

See recent history:

```powershell
git log --oneline -10
```

## Commit Message Pattern

Use short and clear commit messages:

- `feat: add live chat assignment controls`
- `fix: prevent duplicate address submission`
- `refactor: simplify customize flow`
- `chore: update git hygiene rules`

## Before Big Changes

Run:

```powershell
git status
```

If the tree is already dirty, review the changes first before adding new work.

## Connect To GitHub Later

When you already have a GitHub repository URL, connect it with:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\git-connect-remote.ps1 "https://github.com/USERNAME/REPO.git"
```

Then push:

```powershell
git push -u origin main
```
