# KU Phumpanya — Architecture Summary & Deploy Checklist for IT

Two separate codebases, two separate runtimes. Both need to be live and able to reach each other over HTTPS for the system to work.

## 1. Architecture

| | Frontend | Backend |
|---|---|---|
| **What** | Next.js 16 SPA (search UI, knowledge graph, learning path) | Laravel 13 API + Google OAuth + GraphRAG pipeline |
| **Repo** | `github.com/chissanupun/KU-BCG` | `github.com/plenglnw1/ku-phumpanya` |
| **Runtime** | Node.js (or static export — see §4) | PHP 8.4+ |
| **Talks to** | Backend API only, via `fetch` (client-side) | MySQL/SQLite/Postgres, Google OAuth, Gemini API, Qdrant vector DB |

Browser loads the Next.js app → all data comes from `fetch()` calls to the Laravel API (cookie-based session auth via Laravel Sanctum, not tokens) → Laravel calls Google OAuth, Gemini, and Qdrant server-side.

```
Browser → Next.js frontend (static or Node)
              │  fetch(), credentials: include
              ▼
        Laravel API (PHP 8.4+, Apache/nginx)
              │
     ┌────────┼────────────┬──────────────┐
     ▼        ▼             ▼              ▼
  MySQL   Google OAuth   Gemini API   Qdrant (vector search)
                                       + embed_server (query embedding)
```

## 2. Runtime requirements

**Backend (Laravel)**
- PHP **8.4+** (hard requirement — `symfony/http-foundation` 8.1 uses PHP 8.4 property-hook syntax, will fatal-error on 8.3)
- Extensions: mysql/sqlite, xml, curl, mbstring (standard Laravel set)
- Composer 2.x
- Apache with `mod_rewrite` (or nginx) — `.htaccess`-based routing to `public/index.php`
- `open_basedir` note (if KU shared hosting restricts it): app directory must live inside the same allowed path as the web root — see `docs/DEPLOY-KU-VM.md` §"500 Internal Server Error" for the exact symptom/fix already worked out for `phumpanya.ku.ac.th`.

**Frontend (Next.js)**
- If Node-hosted: Node 20+, `npm run build && npm run start`
- If Apache-only (no Node on the KU VM): app is 100% client components (`"use client"` on every page) — no server-side data fetching — so `next export` (static output) is likely viable, serving the static bundle from Apache alongside/behind the Laravel app. **Not yet tested** — flag as an open item, not a confirmed path.

## 3. External services backend needs to reach

| Service | Required for | Notes |
|---|---|---|
| Google OAuth | Login (only auth method) | Needs a registered redirect URI: `https://<backend-domain>/auth/google/callback` |
| Gemini API (Google AI Studio) | Search result generation | API key must be created in a **separate** GCP project from the OAuth credentials, or it 403s |
| Qdrant (vector DB) | Semantic search ranking | Self-hosted or Qdrant Cloud; reachable from backend server. **Fails gracefully** — if unreachable, search falls back to static seed data (lower quality, not broken) |
| embed_server (query embedding, port 8765 by convention) | Feeds Qdrant search | **Not yet implemented** — referenced in `.env.example` comments and `QdrantRetriever.php`, but no Python service exists in either repo today. Until built, Qdrant path is effectively dead and search always falls back to config-seeded data |

## 4. Env vars to configure (names only — values supplied separately, never commit real secrets)

```
APP_URL, APP_KEY, APP_ENV=production, APP_DEBUG=false
FRONTEND_URL=https://<frontend-domain>          # drives CORS + Sanctum stateful domain automatically
DB_CONNECTION, DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD
SESSION_DRIVER=database  (or file — avoid on multi-worker without sticky sessions)
GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI
GEMINI_ENABLED=true, GEMINI_API_KEY
QDRANT_ENABLED, QDRANT_HOST, QDRANT_API_KEY, QDRANT_EMBEDDING_URL
ELASTICSEARCH_ENABLED=false   # keep off unless ES is actually provisioned
```

Frontend needs exactly one:
```
NEXT_PUBLIC_API_URL=https://<backend-domain>
```

## 5. Cross-origin / cookie requirement (important, easy to get wrong)

Auth is cookie-based (Laravel Sanctum SPA mode), not bearer tokens. For login to work with frontend and backend on **different domains**:
- Both must be served over **HTTPS** in production (Secure cookies required for cross-site `SameSite=None`)
- `FRONTEND_URL` on the backend must exactly match the frontend's origin — Sanctum derives its allowed stateful domain from this automatically
- CORS (`config/cors.php`) already reads `FRONTEND_URL` for `Access-Control-Allow-Origin`, `supports_credentials: true`
- If either side has aggressive third-party-cookie blocking in mind (e.g. frontend on Vercel, backend on `*.ku.ac.th`), test login end-to-end after deploy — this is the most fragile part of a cross-domain setup

Simplest to get right: put both under the **same parent domain** or path-route them behind one reverse proxy so the browser sees one origin. Confirm with IT which topology they're provisioning before finalizing `FRONTEND_URL`.

## 6. Already-written deploy docs (in `ku-phumpanya` repo)

- `docs/DEPLOY-KU-VM.md` — step-by-step for the actual KU shared-hosting VM (`phumpanya.ku.ac.th`, account `ppyku`, SFTP port 22, Apache/PHP-only, `open_basedir` gotcha + fix already documented)
- `docs/VERCEL-DEPLOY.md` — alternate path if backend goes on Vercel instead (Go shim + Neon Postgres) — **not the target**, KU requires deploying on the university's own server

## 7. Open items before this is deploy-ready

1. `embed_server` (Qdrant query embedding) doesn't exist yet — decide whether to build it or accept degraded (non-vector) search quality at launch
2. Confirm final domain topology (same parent domain vs fully separate) before setting `FRONTEND_URL`
3. Decide frontend hosting: static export on the KU VM vs external Node host (Vercel) calling the KU API cross-origin
4. Google OAuth redirect URI must be added in Google Cloud Console for the **production** backend domain (separate from the `localhost:8000` one already registered for dev)
