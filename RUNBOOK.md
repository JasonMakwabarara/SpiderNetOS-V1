# SpiderNetOS Operations Runbook

## Deployment

```bash
git pull
composer install --no-dev
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan queue:restart   # reload queue workers with new code
```

The queue worker (`php artisan queue:work redis`) processes `SendEmailJob` and other
queued work. Run it under a supervisor (systemd / Supervisor / the docker-compose
`queue` service).

## Environment

- `.env` holds **only** bootstrap secrets: `APP_KEY`, DB, Redis, mail, queue.
- **No AI provider keys in `.env` or code.** AI providers (OpenAI, Ollama, Azure, etc.)
  are configured from the super-admin **Providers** panel and stored encrypted in the
  `ai_providers` table.
- Postgres must have the `vector` extension available (the `pgvector/pgvector` image
  ships it). The `memories` migration enables it.

---

## SECURITY INCIDENT: leaked AI key remediation

A live OpenAI key (`sk-proj-uVVbs5...`) was previously committed to this repository in
multiple files. The current working tree has been fully scrubbed and the offending files
deleted. The following steps complete the remediation and **must be performed by a
repository administrator**.

### 1. Rotate the key (do immediately)
The committed key must be treated as compromised. Revoke it in the OpenAI dashboard
(API keys → revoke) and issue a replacement. Add the replacement **only** via the
super-admin Providers panel — never in code or `.env`.

### 2. Purge the secret from git history
Removing files from the working tree does **not** remove them from history. Because this
repo has a shared remote (`origin`), coordinate with all collaborators before rewriting.

Using [git-filter-repo] (recommended):

```bash
# 1. Tell everyone to stop pushing and to re-clone after this is done.
pip install git-filter-repo

# 2. From a fresh mirror clone:
git clone --mirror https://github.com/JasonMakwabarara/SpiderNetOS-V1.git
cd SpiderNetOS-V1.git

# 3. Replace the secret string everywhere in history.
printf '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==>***REMOVED***\n' > replacements.txt
git filter-repo --replace-text replacements.txt

# 4. Force-push the rewritten history.
git push --force --all
git push --force --tags
```

Alternatively use [BFG Repo-Cleaner] with `--replace-text replacements.txt`.

### 3. After the rewrite
- Every collaborator must **delete their local clone and re-clone** (rebasing onto a
  rewritten history is error-prone).
- Confirm the secret is gone from history:
  ```bash
  git log -p | git grep -F "sk-proj-uVVbs5"   # expect no output
  ```
- Treat any other credentials that were ever committed (mock admin password, etc.) as
  compromised and rotate them too.

### 4. Prevent recurrence
- `.gitignore` now excludes `.env`, `*.env.txt`, archives, backups, `composer.phar`.
- CI runs `gitleaks` as a required check on every push/PR.

[git-filter-repo]: https://github.com/newren/git-filter-repo
[BFG Repo-Cleaner]: https://rtyley.github.io/bfg-repo-cleaner/
