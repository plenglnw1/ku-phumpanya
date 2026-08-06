# KU Phumpanya — Documentation

เอกสารสำหรับทีมพัฒนา Frontend / Integration

| เอกสาร | คำอธิบาย |
|--------|----------|
| [api-spec.md](./api-spec.md) | REST API contract (OpenAPI-style, Markdown) |
| [DEPLOY-KU-VM.md](./DEPLOY-KU-VM.md) | KU hosting deploy (Mode A/B), same-domain Next static, GitHub Actions SCP/SSH |
| [DEPLOY-HOSTINGER-VPS.md](./DEPLOY-HOSTINGER-VPS.md) | Hostinger VPS Docker + Traefik + GHCR CI/CD |

## Quick reference

| Item | Value |
|------|-------|
| Base URL (local) | `http://localhost` (Sail) หรือ `http://127.0.0.1:8000` |
| API prefix | `/api` |
| Auth (SPA) | Laravel Sanctum — session cookie + CSRF |
| Auth (primary) | Google OAuth → `GET /auth/google` |
| Health check | `GET /up` |

## สถานะปัจจุบัน

- JSON API endpoints in `routes/api.php` — **implemented** (see `docs/api-spec.md`)
- Backend หลักยังมี **Laravel Blade SSR** สำหรับ web UI เดิม
- **Same-domain SPA:** build Next (`KU-BCG`, `output: 'export'`) → merge เข้า `public/` บน KU — ไม่ต้องรัน Node บน server

## Related services (ไม่ใช่ Laravel API)

| Service | URL | ใช้ทำอะไร |
|---------|-----|-----------|
| Qdrant | `http://localhost:6333` | Vector DB (docs + relations) |
| Embed server | `http://localhost:8765/embed` | Query embedding (384-dim) |
| Gemini API | Google AI | Agent synthesis (optional) |
