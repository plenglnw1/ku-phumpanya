# KU Phumpanya API Specification

**Version:** `1.2.0`  
**Last updated:** 2026-10-29  
**App:** `ku-phumpanya` (Laravel 13)

---

## Table of contents

1. [Overview](#overview)
2. [Authentication](#authentication)
3. [Common conventions](#common-conventions)
4. [Error responses](#error-responses)
5. [Schemas](#schemas)
6. [Endpoints](#endpoints)
   - [System](#tag-system)
   - [Auth](#tag-auth)
   - [Search & GraphRAG](#tag-search)
   - [Learning path](#tag-learning)
   - [Smart picks](#tag-smart-picks)
   - [Profile](#tag-profile)
   - [Reference data](#tag-reference)
7. [Web routes (SSR — current UI)](#web-routes-ssr)
8. [Frontend integration notes](#frontend-integration-notes)

---

## Overview

KU Phumpanya เป็นแพลตฟอร์มค้นหาและเรียนรู้งานวิจัย BCG ของมหาวิทยาลัยเกษตรศาสตร์ โดยใช้ **GraphRAG pipeline**:

1. Retrieve: Elasticsearch hybrid search — docs (`ku_bcg_documents`, แบ่งโควตาต่อ source) + relation triples (`ku_bcg_relations`). Neo4j ใช้กับหน้า `/graph` explorer; Qdrant ใช้เฉพาะ local/legacy
2. Route query tier (`basic` | `intermediate` | `advanced`)
3. Gemini agent synthesis (ถ้า `GEMINI_ENABLED=true` และ quota พร้อม)
4. คืนผลลัพธ์ unified shape: overview, knowledge graph, learning path, evidence

### Base URLs

| Environment | URL |
|-------------|-----|
| Local (Sail) | `http://localhost` |
| Local (artisan serve) | `http://127.0.0.1:8000` |
| API prefix | `{BASE_URL}/api` |

### Implementation status legend

| Badge | Meaning |
|-------|---------|
| **Implemented** | Route มีอยู่และใช้งานได้ |
| **SSR only** | มีเฉพาะ web route คืน HTML — JSON shape อ้างอิงจาก section Schemas |
| **Planned** | Contract กำหนดแล้ว รอ Backend implement เป็น `/api/*` |

---

## Authentication

### Primary: Google OAuth (Implemented — browser redirect)

| Step | Method | Path | Notes |
|------|--------|------|-------|
| 1 | `GET` | `/auth/google` | Redirect ไป Google consent |
| 2 | `GET` | `/auth/google/callback` | สร้าง/อัปเดต user, login session |
| 3 | `GET` | `/register/complete` | ถ้า `profile_completed_at` เป็น null |
| 4 | `POST` | `/register/complete` | บันทึก role + คณะ/สาขา |

หลัง OAuth สำเร็จ Laravel ตั้ง **session cookie** (`phumpanya-session` บน KU) + **CSRF cookie** (`XSRF-TOKEN`)

Session บน production: idle timeout 30 นาที, หมดเมื่อปิด browser, ไม่มี remember-me, cookie `secure; httponly; samesite=lax` (ดู `docs/gates/1.5.3-pdpa.md`)

### SPA: Laravel Sanctum (partial)

- `EnsureFrontendRequestsAreStateful` เปิดอยู่บน `/api/*`
- Stateful domains: `localhost`, `localhost:3000`, `127.0.0.1:8000`, `FRONTEND_URL`
- CORS: `supports_credentials: true`, origin จาก `FRONTEND_URL` (default `http://localhost:3000`)

**SPA login flow (เมื่อ implement เพิ่ม):**

```
1. GET  /sanctum/csrf-cookie     → รับ XSRF-TOKEN
2. POST /auth/google              → browser redirect (ไม่ใช่ XHR)
   — หรือ POST /api/auth/login   → Planned (password; ปิดอยู่โดย default)
3. GET  /api/user                 → ตรวจ session
```

### Password login (SSR only, disabled by default)

`AUTH_PASSWORD_LOGIN_ENABLED=false` (default และค่าบน production) → `/login` redirect ไป welcome, `POST /api/auth/login` คืน `403 {"message":"Password login is disabled."}`

### Authorization rules

| Rule | Behavior |
|------|----------|
| `auth` | ต้อง login |
| `verified` | ต้อง verify email (Google users ถูก mark verified อัตโนมัติ) |
| `profile.complete` | ต้อง `profile_completed_at != null` ยกเว้น route `register.complete` |
| Resource ownership | `SearchHistory.user_id` ต้องตรงกับ user ปัจจุบัน → `403` |

---

## Common conventions

### Request headers (JSON API)

```http
Accept: application/json
Content-Type: application/json
X-XSRF-TOKEN: {value from XSRF-TOKEN cookie}
Cookie: phumpanya-session=...; XSRF-TOKEN=...
```

### Pagination

ยังไม่ใช้ global pagination — `recent_searches` จำกัด **10 รายการล่าสุด**

### Timestamps

ISO 8601 UTC ใน JSON, เช่น `2026-07-03T08:17:00.000000Z`

### IDs

| Resource | Type |
|----------|------|
| `User.id` | `integer` |
| `SearchHistory.id` | `integer` |

---

## Error responses

เมื่อ `Accept: application/json` และ path ขึ้นต้นด้วย `/api/`:

### 401 Unauthorized

```json
{
  "message": "Unauthenticated."
}
```

### 403 Forbidden

```json
{
  "message": "This action is unauthorized."
}
```

### 404 Not Found

```json
{
  "message": "No query results for model [App\\Models\\SearchHistory] {id}"
}
```

### 422 Validation Error

```json
{
  "message": "The query field is required.",
  "errors": {
    "query": ["The query field is required."]
  }
}
```

### 429 Too Many Requests

ใช้กับ email verification (`throttle:6,1`)

### 302 Redirect (web only)

OAuth, login สำเร็จ, search submit — FE ที่เป็น SPA ควรใช้ JSON API แทน redirect

---

## Schemas

### User

```yaml
User:
  type: object
  properties:
    id:
      type: integer
      example: 1
    name:
      type: string
      example: "สมชาย ใจดี"
    email:
      type: string
      format: email
    email_verified_at:
      type: string
      format: date-time
      nullable: true
    role:
      type: string
      enum: [admin, student, researcher, teacher]
      nullable: true
    avatar_url:
      type: string
      format: uri
      nullable: true
    profile_completed_at:
      type: string
      format: date-time
      nullable: true
    faculty:
      type: string
      nullable: true
      description: ชื่อคณะ (จากรายการ ku_faculties)
    department:
      type: string
      nullable: true
    student_id:
      type: string
      nullable: true
      pattern: '^\d{10}$'
      description: นิสิตเท่านั้น
    employee_id:
      type: string
      nullable: true
      maxLength: 20
      description: นักวิจัย/อาจารย์
    research_affiliation:
      type: string
      nullable: true
      description: นักวิจัยเท่านั้น
    consented_at:
      type: string
      format: date-time
      nullable: true
      description: เวลายอมรับประกาศความเป็นส่วนตัว (PDPA)
    consent_version:
      type: string
      nullable: true
      example: "2026-10"
    created_at:
      type: string
      format: date-time
    updated_at:
      type: string
      format: date-time
```

`password`, `remember_token` และ `google_id` ไม่ถูกส่งออกใน JSON

### SearchHistory

```yaml
SearchHistory:
  type: object
  properties:
    id:
      type: integer
    user_id:
      type: integer
    query:
      type: string
      maxLength: 500
    result:
      $ref: '#/components/schemas/GraphRagResult'
      nullable: true
      description: Cache ผล GraphRAG ตอน submit (ไม่ re-run agent เมื่อเปิด tab)
    created_at:
      type: string
      format: date-time
    updated_at:
      type: string
      format: date-time
```

### GraphRagResult

ผลลัพธ์หลักที่ FE ต้อง render ทั้ง 3 tab (overview / graph / learning)

```yaml
GraphRagResult:
  type: object
  required: [title, overview, knowledge_graph, learning_path, evidence]
  properties:
    title:
      type: string
      description: หัวข้อสังเคราะห์ (อาจไม่ตรงกับ query เดิม)
    overview:
      $ref: '#/components/schemas/Overview'
    knowledge_graph:
      $ref: '#/components/schemas/KnowledgeGraph'
    learning_path:
      $ref: '#/components/schemas/LearningPath'
    evidence:
      type: array
      items:
        $ref: '#/components/schemas/EvidenceItem'
    tier:
      type: string
      enum: [basic, intermediate, advanced]
      description: มีเมื่อ agent pipeline เปิด
    status:
      type: string
      enum: [ready, pending]
      description: >
        pending = ผล retrieval-only (draft) ที่ยังไม่ผ่าน Gemini synthesis; มีเฉพาะเมื่อ
        GEMINI_ASYNC_SYNTHESIS=true. FE เรียก GET /api/search/history/{id} เพื่อรับผล ready
    tags:
      type: object
      description: ป้าย chips — เรียงจากที่พบบ่อยใน evidence มากไปน้อย
      properties:
        bcg_pillars:
          type: array
          items: { type: string, example: "Bio" }
        faculties:
          type: array
          items: { type: string, example: "วนศาสตร์" }
          description: map จาก topic_ids ของเอกสารผ่าน config/bcg_topics.php
    _meta:
      $ref: '#/components/schemas/GraphRagMeta'
```

### Overview

```yaml
Overview:
  type: object
  properties:
    intro:
      type: string
    analogy:
      type: string
    research_basis:
      type: string
    expert:
      type: string
```

### KnowledgeGraph

```yaml
KnowledgeGraph:
  type: object
  properties:
    center:
      type: object
      properties:
        label: { type: string }
        color: { type: string, example: "#2D5A43" }
        type:
          type: string
          enum: [topic, faculty, course, bcg_pillar]
    description:
      type: string
    nodes:
      type: array
      items:
        type: object
        properties:
          label: { type: string }
          color: { type: string }
          type: { type: string }
    edges:
      type: array
      items:
        type: object
        properties:
          from: { type: string }
          to: { type: string }
          type: { type: string, description: "predicate เช่น mitigatesCarbonVia" }
```

**FE graph render:** ส่ง `knowledge_graph` เป็น JSON ใน `data-knowledge-graph` — ดู `resources/js/knowledge-graph.js` (force-directed SVG, 90 frames)

### LearningPath

```yaml
LearningPath:
  type: object
  properties:
    estimated_hours:
      type: string
      example: "90-140"
    subtitle:
      type: string
    phases:
      type: array
      items:
        type: object
        properties:
          name:
            type: string
            example: "Phase: Foundation"
          intro:
            type: string
          modules:
            type: array
            items:
              type: object
              properties:
                title: { type: string }
                hours: { type: string, example: "8-12 hrs" }
                desc: { type: string, description: "มักเป็น URL คอร์ส" }
```

### EvidenceItem

```yaml
EvidenceItem:
  type: object
  properties:
    title:
      type: string
    source:
      type: string
      enum: [KU_Forest, KUKR, KU_MOOC, KU_NonDegree, UNKNOWN]
    url:
      type: string
      format: uri
    snippet:
      type: string
      maxLength: 150
```

### GraphRagMeta

```yaml
GraphRagMeta:
  type: object
  properties:
    reason:
      type: string
      example: "forced via GEMINI_FORCE_TIER"
    calls:
      type: integer
      description: จำนวน Gemini API calls สำเร็จ — 0 = fallback/heuristic
    models:
      type: object
      properties:
        router: { type: string }
        sub: { type: string }
        synth: { type: string }
    docs_retrieved:
      type: integer
    relations_retrieved:
      type: integer
```

### SmartPicks

```yaml
SmartPicksResponse:
  type: object
  properties:
    smart_picks:
      type: object
      properties:
        headline: { type: string, example: "AI Recommendations" }
        reason: { type: string, description: อธิบายว่าแนะนำจากอะไร (ประวัติค้นหา / คณะ / cold start) }
        generated_at: { type: string, format: date-time }
        items:
          type: array
          items:
            type: object
            properties:
              title: { type: string }
              info: { type: string, example: "2565 · วนศาสตร์ · 4 คำสำคัญ" }
              source: { type: string, example: "KU Forest" }
              match: { type: integer, minimum: 45, maximum: 98, description: "% ความเกี่ยวข้องเทียบกับรายการแรก" }
              reasons: { type: array, items: { type: string }, maxItems: 2 }
              keywords: { type: array, items: { type: string }, maxItems: 4 }
              url: { type: string, format: uri }
              query: { type: string, description: ใช้เป็น query เมื่อกดค้นหาต่อ }
```

### LearningProgressIndex

```yaml
LearningProgressIndex:
  type: object
  properties:
    data:
      type: array
      items:
        type: object
        properties:
          id: { type: integer, description: SearchHistory id }
          query: { type: string }
          title: { type: string }
          completed_count: { type: integer }
          total: { type: integer }
          percent: { type: integer }
          created_at: { type: string, format: date-time }
    summary:
      type: object
      properties:
        roadmaps: { type: integer }
        started: { type: integer }
        finished: { type: integer }
        completed_count: { type: integer }
        total: { type: integer }
        percent: { type: integer }
```

### CompleteRegistrationRequest

```yaml
CompleteRegistrationRequest:
  type: object
  required: [role, faculty, department]
  properties:
    role:
      type: string
      enum: [student, researcher, teacher]
    faculty:
      type: string
      description: ต้องอยู่ใน GET /api/reference/faculties
    department:
      type: string
      maxLength: 255
    student_id:
      type: string
      pattern: '^\d{10}$'
      description: required if role=student
    employee_id:
      type: string
      maxLength: 20
      description: required if role=researcher|teacher
    research_affiliation:
      type: string
      maxLength: 255
      description: optional, role=researcher
```

### Example: GraphRagResult (truncated)

```json
{
  "title": "Carbon footprinting of rice products",
  "overview": {
    "intro": "คาร์บอนฟุตพริ้นท์ในภาคเกษตร...",
    "analogy": "Like connecting dots across KU Forest papers, KUKR library, and KU MOOC courses.",
    "research_basis": "Grounded in 6 retrieved documents from KU BCG corpus.",
    "expert": "Knowledge source: KU Phumpanya GraphRAG (local vector + relations)."
  },
  "knowledge_graph": {
    "center": { "label": "agriculture", "color": "#2D5A43", "type": "topic" },
    "description": "กราฟความรู้จากความสัมพันธ์ในข้อมูล KU BCG (งานวิจัย KUKR, KU Forest, คอร์ส KU MOOC และชุดวิชา Non-Degree)",
    "nodes": [
      { "label": "agriculture", "color": "#EAB308", "type": "topic" },
      { "label": "forest", "color": "#A855F7", "type": "topic" }
    ],
    "edges": [
      { "from": "agriculture", "to": "emission_reduction", "type": "mitigatesCarbonVia" }
    ]
  },
  "learning_path": {
    "estimated_hours": "90-140",
    "subtitle": "Heuristic path from retrieved sources",
    "phases": [
      {
        "name": "Phase: Foundation",
        "intro": "Start with core concepts from retrieved KU sources.",
        "modules": [
          {
            "title": "การคำนวณคาร์บอนฟุตพริ้นท์รายบุคคล",
            "hours": "8-12 hrs",
            "desc": "https://kumooc.ku.th/courses/6/info"
          }
        ]
      }
    ]
  },
  "evidence": [
    {
      "title": "Carbon footprinting of rice products",
      "source": "KUKR",
      "url": "https://kukr.lib.ku.ac.th/KUKR/Search/detail/203060",
      "snippet": "คาร์บอนฟุตพริ้นท์ และฉลากคาร์บอน..."
    }
  ],
  "tier": "advanced",
  "_meta": {
    "reason": "forced via GEMINI_FORCE_TIER",
    "calls": 2,
    "models": {
      "router": "gemini-2.5-flash-lite",
      "sub": "gemini-2.5-flash-lite",
      "synth": "gemini-2.5-flash"
    },
    "docs_retrieved": 6,
    "relations_retrieved": 10
  }
}
```

---

## Endpoints

### Tag: System

#### `GET /up` — Health check

| | |
|--|--|
| **Status** | Implemented |
| **Auth** | None |

**Response `200`**

```json
{
  "status": "ok"
}
```

---

### Tag: Auth

#### `GET /api/user` — Current user

| | |
|--|--|
| **Status** | Implemented |
| **Auth** | `auth:sanctum` |

**Response `200`** — `User` object + `consent_required: boolean`

`consent_required` เป็น `true` เมื่อ user ยังไม่เคยยอมรับ หรือยอมรับ version เก่ากว่า `pdpa.consent_version` → FE แสดงหน้ายอมรับ PDPA ก่อนใช้งาน

---

#### `POST /api/consent` — Accept privacy notice (PDPA)

| | |
|--|--|
| **Status** | Implemented |
| **Auth** | `auth:sanctum` |

ไม่มี request body. บันทึก `consented_at = now()`, `consent_version = pdpa.consent_version`

**Response `200`** — `User` (หลังจากนี้ `GET /api/user` คืน `consent_required: false`)

---

#### `GET /auth/google` — Start Google OAuth

| | |
|--|--|
| **Status** | Implemented (browser) |
| **Auth** | Guest |

**Response `302`** → Google OAuth consent URL

---

#### `GET /auth/google/callback` — OAuth callback

| | |
|--|--|
| **Status** | Implemented |
| **Auth** | Guest → session created |

**Response `302`**

- Profile incomplete → `/register/complete`
- Else → `/search` (หรือ intended URL)

---

#### `POST /api/auth/register/complete` — Complete onboarding (JSON)

| | |
|--|--|
| **Status** | Implemented |
| **Auth** | `auth:sanctum` |
| **Content-Type** | `application/json` |

**Request body** — `CompleteRegistrationRequest`

**Response `200`** + `User` JSON

SSR equivalent: `POST /register/complete` → `302`

---

#### `POST /logout` — End session

| | |
|--|--|
| **Status** | Implemented (`POST /api/logout` → `204`) |

---

### Tag: Search

#### `GET /api/search/suggestions` — Query suggestions

| | |
|--|--|
| **Status** | Implemented |
| **Auth** | `auth:sanctum`, `verified`, `profile.complete` |

**Response `200`**

```json
{
  "suggestions": [
    "carbon footprint in agriculture",
    "microplastics water quality",
    "chitosan biodegradable packaging"
  ]
}
```

**Source:** `config/graphrag_seed.php` → `GraphRagService::suggestions()`

---

#### `GET /api/search/recent` — Recent searches (sidebar)

| | |
|--|--|
| **Status** | Implemented |
| **Auth** | `auth:sanctum`, `verified`, `profile.complete` |

**Response `200`**

```json
{
  "data": [
    {
      "id": 42,
      "query": "เปรียบเทียบ carbon footprint ระหว่างคณะเกษตรและวนศาสตร์",
      "created_at": "2026-07-03T08:00:00.000000Z"
    }
  ]
}
```

Max **10** items, ใหม่สุดก่อน (`created_at desc`, แล้ว `id desc` เมื่อเวลาเท่ากัน). ไม่รวมรายการที่ `status = error`

---

#### `POST /api/search` — Submit search (run GraphRAG)

| | |
|--|--|
| **Status** | Implemented |
| **Auth** | required |
| **Side effect** | รัน agent pipeline (อาจใช้เวลา 5–30s), cache ใน `search_histories.result` |

**Request body**

```json
{
  "query": "เปรียบเทียบ carbon footprint ระหว่างคณะเกษตรและวนศาสตร์"
}
```

| Field | Type | Rules |
|-------|------|-------|
| `query` | string | required, max 500 |

**Response `201`**

```json
{
  "data": {
    "id": 42,
    "user_id": 1,
    "query": "เปรียบเทียบ carbon footprint ระหว่างคณะเกษตรและวนศาสตร์",
    "result": { "$ref": "GraphRagResult" },
    "created_at": "2026-07-03T08:00:00.000000Z",
    "updated_at": "2026-07-03T08:00:00.000000Z"
  }
}
```

**SSR equivalent:** `POST /search` → `302` → `/search/history/{id}`

**Performance note:** แสดง loading state — ปุ่ม disable + spinner ขณะรอ

**Async synthesis (`GEMINI_ASYNC_SYNTHESIS=true`):** `POST` คืนทันที (~ไม่กี่สิบ ms) ด้วย `result.status = "pending"` — ผล retrieval (evidence, graph, learning path, tags) ครบ แต่ overview ยังเป็น fallback. FE แสดงผลได้เลย แล้วเรียก `GET /api/search/history/{id}` (ครั้งเดียว ใช้เวลา ~4–5s) เพื่อรับ `status = "ready"`. ปิดอยู่บน production จนกว่า FE จะรองรับ

---

#### `GET /api/search/history/{searchHistory}` — Get search result

| | |
|--|--|
| **Status** | Implemented |
| **Auth** | required, owner only |

**Query parameters**

| Param | Type | Default | Values |
|-------|------|---------|--------|
| `tab` | string | `overview` | `overview`, `graph`, `learning` |

> `tab` เป็น UI hint — response JSON เหมือนกันทุก tab (ส่ง `GraphRagResult` เต็ม) FE เลือก render เอง

**Response `200`**

```json
{
  "data": {
    "id": 42,
    "query": "เปรียบเทียบ carbon footprint ระหว่างคณะเกษตรและวนศาสตร์",
    "tab": "overview",
    "result": { "$ref": "GraphRagResult" },
    "created_at": "2026-07-03T08:00:00.000000Z"
  }
}
```

**Fallback:** ถ้า `result` เป็น null (history เก่า) backend จะ re-run `GraphRagService::search(query)`

**Pending:** ถ้า `result.status = "pending"` backend รัน synthesis ใน request นี้ (lock ต่อ history id — request ซ้อนกันรอผลเดียวกัน) บันทึก แล้วคืน `status = "ready"`. ถ้า Gemini ล้มเหลวจะคืน draft เดิม

**SSR equivalent:** `GET /search/history/{id}?tab=graph`

---

#### `DELETE /api/search/history/{searchHistory}` — Delete history item

| | |
|--|--|
| **Status** | Implemented |
| **Auth** | owner |

Deletes one search and the learning progress recorded against it. To clear everything, use `DELETE /api/search/recent`.

**Response `204`** · **`403`** when the search belongs to another user · **`404`** when it does not exist

---

### Tag: Learning

#### `GET /api/learning` — Learning path page data

| | |
|--|--|
| **Status** | Implemented |
| **Auth** | required |

**Query parameters**

| Param | Type | Required |
|-------|------|----------|
| `search_history_id` | integer | no |

- มี `search_history_id` → ใช้ `query` จาก history (owner check)
- ไม่มี → default query `"carbon footprint in agriculture"`

**Response `200`**

```json
{
  "search_history_id": 42,
  "result": { "$ref": "GraphRagResult" }
}
```

**SSR equivalent:** `GET /learning` หรือ `GET /learning/{searchHistory}`

---

#### `GET /api/progress` — Learning progress across roadmaps

| | |
|--|--|
| **Status** | Implemented |
| **Auth** | required |

**Response `200`** — `LearningProgressIndex` (1 รายการต่อ search history ของ user, ใหม่สุดก่อน)

```json
{
  "data": [
    { "id": 42, "query": "ไคโตซานและบรรจุภัณฑ์ชีวภาพ", "title": "...", "completed_count": 3, "total": 12, "percent": 25, "created_at": "2026-10-29T08:00:00+00:00" }
  ],
  "summary": { "roadmaps": 4, "started": 2, "finished": 1, "completed_count": 15, "total": 48, "percent": 31 }
}
```

#### `GET|PUT /api/search/history/{searchHistory}/progress` — Progress of one roadmap

| | |
|--|--|
| **Status** | Implemented |
| **Auth** | required, owner only |

`PUT` body: `{ "phase": 0, "card": 1, "completed": true }`

---

### Tag: Smart picks

#### `GET /api/smart-picks` — AI recommendations

| | |
|--|--|
| **Status** | Implemented |
| **Auth** | required |

**Response `200`**

`SmartPicksResponse`

```json
{
  "smart_picks": {
    "headline": "AI Recommendations",
    "reason": "จากประวัติการค้นหาของคุณ",
    "generated_at": "2026-10-29T08:00:00+00:00",
    "items": [
      {
        "title": "การใช้ไคโตซานเคลือบผลไม้",
        "info": "2565 · อุตสาหกรรมเกษตร · 4 คำสำคัญ",
        "source": "KUKR",
        "match": 92,
        "reasons": ["ตรงกับคำค้น “ไคโตซาน”"],
        "keywords": ["chitosan", "coating"],
        "url": "https://kukr.lib.ku.ac.th/...",
        "query": "การใช้ไคโตซานเคลือบผลไม้"
      }
    ]
  }
}
```

**Source:** `SmartPicksService` — ค้น Elasticsearch ตาม interest profile (คำค้นล่าสุด, topic, BCG pillar, คณะของ user), จัดอันดับแบบ diversity ข้าม source. User ใหม่ (cold start) ใช้คณะเป็นหลัก

---

### Tag: Profile

#### `GET /api/profile` — Get profile

| | |
|--|--|
| **Status** | Implemented |
| **Auth** | required |

**Response `200`** — `User`

---

#### `PATCH /api/profile` — Update profile

| | |
|--|--|
| **Status** | Implemented |
| **Auth** | required |

**Request body**

| Field | Rules |
|-------|-------|
| `name` | required, string, max 255 |
| `email` | required, email, unique (ignore self) |
| `faculty` | nullable, in faculties list |
| `department` | nullable, max 255 |
| `student_id` | required if role=student, regex `^\d{10}$`, unique |
| `employee_id` | required if role=researcher\|teacher, max 20, unique |
| `research_affiliation` | nullable, max 255 |

**Response `200`** — `User`  
**Side effect:** เปลี่ยน email → `email_verified_at = null`

---

#### `DELETE /api/profile` — Delete account

| | |
|--|--|
| **Status** | Implemented (`204`) |
| **Auth** | required |

**Request body**

| Account | Body |
|---------|------|
| Google (ไม่มี password) | `{ "confirm_email": "nisit@ku.th" }` — ต้องตรงกับอีเมลของบัญชี (ไม่สนตัวพิมพ์เล็ก/ใหญ่) |
| Password | `{ "password": "current-password" }` |

Error อยู่ใน bag `userDeletion` (`422`)

**Response `204`** — ลบ user, search history และ learning progress (cascade); activity log ถูกตัด `user_id` เป็น null; logout + invalidate session

---

### Tag: Reference

#### `GET /api/reference/faculties` — KU faculty list

| | |
|--|--|
| **Status** | Implemented |
| **Auth** | none หรือ optional |

**Response `200`**

```json
{
  "faculties": [
    "เกษตรศาสตร์",
    "วนศาสตร์",
    "วิศวกรรมศาสตร์"
  ]
}
```

**Source:** `config/ku_faculties.php`

---

#### `GET /api/reference/roles` — Registerable roles

| | |
|--|--|
| **Status** | Implemented |

**Response `200`**

```json
{
  "roles": [
    { "value": "student", "label": "Student" },
    { "value": "researcher", "label": "Researcher" },
    { "value": "teacher", "label": "Teacher" }
  ]
}
```

**Source:** `config/auth_flow.php` → `allowed_roles_on_register`

---

## Web routes (SSR)

Route ที่มีอยู่จริงวันนี้ (คืน HTML):

| Method | Path | Controller | Middleware |
|--------|------|------------|------------|
| `GET` | `/` | welcome | guest |
| `GET` | `/search` | SearchController@index | auth, verified, profile.complete |
| `POST` | `/search` | SearchController@store | auth, verified, profile.complete |
| `GET` | `/search/history/{id}` | SearchController@show | auth, verified, profile.complete |
| `GET` | `/learning` | LearningPathController@show | auth, verified, profile.complete |
| `GET` | `/learning/{id}` | LearningPathController@show | auth, verified, profile.complete |
| `GET` | `/smart-picks` | SmartPicksController@index | auth, verified, profile.complete |
| `GET` | `/profile` | ProfileController@edit | auth, verified, profile.complete |
| `PATCH` | `/profile` | ProfileController@update | auth, verified, profile.complete |
| `DELETE` | `/profile` | ProfileController@destroy | auth, verified, profile.complete |
| `GET` | `/dashboard` | redirect → search | auth, verified, profile.complete |

---

## Frontend integration notes

### 1. Tab UI mapping

| `tab` query | FE section | Data keys |
|-------------|------------|-----------|
| `overview` | สรุป + evidence cards | `result.overview`, `result.evidence` |
| `graph` | SVG knowledge graph | `result.knowledge_graph` |
| `learning` | Phase modules | `result.learning_path` |

### 2. Tier badge

แสดง `result.tier` ถ้ามี:

| tier | สีแนะนำ |
|------|---------|
| `basic` | green |
| `intermediate` | blue |
| `advanced` | purple |

### 3. Gemini fallback detection

```js
const geminiActive = (result._meta?.calls ?? 0) > 0;
```

ถ้า `false` → overview เป็น heuristic fallback ไม่ใช่ AI synthesis

### 4. Search latency

| Phase | Typical duration |
|-------|------------------|
| Retrieval draft (Elasticsearch, KU host) | p50 ≈ 17ms |
| Gemini synthesis | ≈ 4–5s (หรือ fallback เร็วถ้า quota หมด) |
| Cached history | < 100ms |

### 5. CORS + cookies (Next.js / Vite example)

```ts
// lib/api.ts
const api = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost',
  withCredentials: true,
  headers: { Accept: 'application/json' },
});

// ก่อน POST ครั้งแรก
await api.get('/sanctum/csrf-cookie');
```

### 6. Env vars ที่ FE ควรรู้

| Variable | Default | Purpose |
|----------|---------|---------|
| `APP_URL` | `http://localhost` | API base |
| `FRONTEND_URL` | `http://localhost:3000` | CORS origin |
| `AUTH_PASSWORD_LOGIN_ENABLED` | `false` | ปิด email/password login |
| `GEMINI_ENABLED` | `true` | agent vs legacy |

### 7. Data corpus (อ้างอิง)

| Elasticsearch index | Count | Sources |
|---------------------|-------|---------|
| `ku_bcg_documents` | 612 | KUKR 403, KU_Forest 203, KU_MOOC 6 |
| `ku_bcg_relations` | — | BCG semantic triples (seed predicates เช่น `mitigatesCarbonVia`) |

Ontology และ Neo4j schema: `docs/ontology-schema.md`

---

## Changelog

| Version | Date | Notes |
|---------|------|-------|
| 1.2.0 | 2026-10-29 | `result.tags` + `result.status` (async synthesis), `GET /api/progress`, `POST /api/consent` + `consent_required`, delete account by `confirm_email`, recommender Smart Picks shape, `google_id` hidden, password login API returns 403 |
| 1.1.0 | 2026-07-08 | All `/api/*` Planned endpoints implemented |
| 1.0.0 | 2026-07-03 | Initial spec — SSR + planned REST contract |
