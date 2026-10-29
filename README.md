# KU Phumpanya (ภูมิปัญญา)

KU Phumpanya is a BCG knowledge search and learning platform for Kasetsart University. A user asks a question; the platform searches KU Forest research, KUKR documents, and KU MOOC courses, and answers with three views:

- an overview synthesised by Gemini,
- a knowledge graph,
- a phased learning path with progress tracking.

Production: <https://phumpanya.ku.ac.th>. Sign-in is Google, restricted to `@ku.th`.

This repository is the Laravel 13 backend: the JSON API, the GraphRAG pipeline, the admin panel, and the deploy scripts. The UI is a Next.js static export in the **KU-BCG** repository; it is merged into `public/` on deploy.

## Architecture

```
Browser ── Next static UI (KU-BCG, served from public/)
   │  session cookie + XSRF (Sanctum)
   ▼
Laravel API (/api/*) ──► GraphRAG AgentPipeline
                           1. RetrievalLinker + HybridRetriever ─► Elasticsearch
                              (ku_bcg_documents, ku_bcg_relations)
                           2. QueryRouter ─► basic | intermediate | advanced
                           3. Tier generator + Synthesizer ─► Gemini (optional, falls back)
                           4. ResultFormatter (overview, graph, learning path, evidence, tags)
   │
   ├── MySQL (users, search_histories, learning_progress, activity_logs)
   └── Neo4j (knowledge graph explorer at /graph, read-only Cypher)
```

On the KU VM, Elasticsearch and Neo4j run as user processes. A cron watchdog runs every 10 minutes.

## Local development

You need PHP 8.3+, Composer, and Node 20+. Elasticsearch, Neo4j, and Gemini are optional; without them, search falls back to the seed config.

```bash
composer setup        # install, .env, key, migrate, build assets
composer dev          # serve + queue + logs + vite
php artisan test      # PHPUnit (SQLite in memory)
```

Key `.env` settings:

| Variable | Purpose |
|----------|---------|
| `ELASTICSEARCH_ENABLED`, `ELASTICSEARCH_HOST` | Retrieval backend |
| `GEMINI_ENABLED`, `GEMINI_API_KEY` | AI synthesis. Without a key, results are retrieval-only |
| `GEMINI_ASYNC_SYNTHESIS` | Return the retrieval draft first; synthesise on the next GET |
| `KG_NEO4J_HTTP_URL`, `KG_NEO4J_USERNAME`, `KG_NEO4J_PASSWORD` | `/graph` explorer |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | Google OAuth |
| `AUTH_PASSWORD_LOGIN_ENABLED` | Off by default. Production uses Google only |

## Useful commands

| Command | What it does |
|---------|--------------|
| `php artisan links:check --corpus=../KU-BCG/elastic-search/data/all_docs.json` | Check every corpus, seed, and evidence URL; writes a pass rate per source to `storage/app/private/reports/`. Run it off the KU host |
| `php artisan app:purge-demo-users` | Delete `@phumpanya.test` demo accounts (runs on every deploy) |
| `php artisan agent:search "<query>"` | Run the GraphRAG pipeline from the CLI |

## Deploy (KU VM)

You need the KU VPN and the deploy SSH key. The full procedure is in [docs/DEPLOY-KU-VM.md](docs/DEPLOY-KU-VM.md).

```bash
bash scripts/deploy-ku-vm.sh
```

GitHub Actions runs the test suite with coverage on every pull request. The deploy job cannot reach the KU host from GitHub because it has no VPN, so deploys run from a machine on the VPN.

## Documentation

| Doc | Content |
|-----|---------|
| [docs/api-spec.md](docs/api-spec.md) | REST API contract |
| [docs/ontology-schema.md](docs/ontology-schema.md) | OWL ontology and Neo4j graph schema |
| [docs/DEPLOY-KU-VM.md](docs/DEPLOY-KU-VM.md) | KU hosting deploy |
| [docs/RUNBOOK-google-login.md](docs/RUNBOOK-google-login.md) | Google login troubleshooting |
| [docs/gates/](docs/gates/) | Acceptance gate evidence (1.3–1.5) |
