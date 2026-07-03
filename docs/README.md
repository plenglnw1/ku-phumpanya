# KU Phumpanya — Documentation

เอกสารสำหรับทีมพัฒนา Frontend / Integration

| เอกสาร | คำอธิบาย |
|--------|----------|
| [api-spec.md](./api-spec.md) | REST API contract (OpenAPI-style, Markdown) |

## Quick reference

| Item | Value |
|------|-------|
| Base URL (local) | `http://localhost` (Sail) หรือ `http://127.0.0.1:8000` |
| API prefix | `/api` |
| Auth (SPA) | Laravel Sanctum — session cookie + CSRF |
| Auth (primary) | Google OAuth → `GET /auth/google` |
| Health check | `GET /up` |

## สถานะปัจจุบัน

- Backend หลักเป็น **Laravel Blade SSR** — business logic อยู่ใน controllers แล้ว
- JSON API ยังมีเพียง `GET /api/user` (implemented)
- สเปกใน `api-spec.md` กำหนด **contract เป้าหมาย** ให้ FE สร้าง SPA ได้โดยไม่ต้อง reverse-engineer จาก Blade
- Endpoint ที่ mark `Planned` ยังไม่มี route — ต้องให้ Backend implement ก่อน integrate

## Related services (ไม่ใช่ Laravel API)

| Service | URL | ใช้ทำอะไร |
|---------|-----|-----------|
| Qdrant | `http://localhost:6333` | Vector DB (docs + relations) |
| Embed server | `http://localhost:8765/embed` | Query embedding (384-dim) |
| Gemini API | Google AI | Agent synthesis (optional) |
