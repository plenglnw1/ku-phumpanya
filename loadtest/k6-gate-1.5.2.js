// Gate 1.5.2: 30 concurrent users, response ≤ 5 s, error rate ≤ 1 %.
//
//   k6 run loadtest/k6-gate-1.5.2.js
//   BASE_URL=https://phumpanya.ku.ac.th SESSION_COOKIE=... XSRF_TOKEN=... HISTORY_IDS=12,13 k6 run ...
//
// Without SESSION_COOKIE only the public pages are exercised. With it, each user
// also lists recents, smart picks and progress, and replays stored results
// (HISTORY_IDS). New searches (POST /api/search) run only with RUN_SEARCH=1,
// because each one spends Gemini quota (free tier: 20 requests/day per model).
import http from 'k6/http';
import { check, group, sleep } from 'k6';

const BASE = __ENV.BASE_URL || 'https://phumpanya.ku.ac.th';
const COOKIE = __ENV.SESSION_COOKIE || '';
const XSRF = __ENV.XSRF_TOKEN || '';
const HISTORY_IDS = (__ENV.HISTORY_IDS || '').split(',').filter(Boolean);
const RUN_SEARCH = __ENV.RUN_SEARCH === '1';
const QUERIES = [
  'ชีวมวลและพลังงานทดแทน',
  'ไคโตซานและบรรจุภัณฑ์ชีวภาพ',
  'microplastics water quality',
  'carbon footprint ภาคเกษตร',
  'การเพาะเลี้ยงสัตว์น้ำที่ยั่งยืน probiotic',
  'วนเกษตร agroforestry ความหลากหลายทางชีวภาพ',
];

export const options = {
  scenarios: {
    gate: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: __ENV.RAMP || '30s', target: 30 },
        { duration: __ENV.HOLD || '3m', target: 30 },
        { duration: '15s', target: 0 },
      ],
    },
  },
  thresholds: {
    http_req_duration: ['p(95)<5000', 'max<10000'],
    http_req_failed: ['rate<0.01'],
    checks: ['rate>0.99'],
  },
};

const api = {
  headers: Object.assign(
    { Accept: 'application/json', 'Content-Type': 'application/json', Referer: `${BASE}/`, Origin: BASE },
    COOKIE ? { Cookie: COOKIE } : {},
    XSRF ? { 'X-XSRF-TOKEN': XSRF } : {},
  ),
};

export default function () {
  group('public pages', () => {
    for (const path of ['/', '/learn/', '/smart-picks/', '/up']) {
      const res = http.get(`${BASE}${path}`, { tags: { name: path } });
      check(res, { [`${path} 200`]: (r) => r.status === 200 });
    }
  });

  if (COOKIE) {
    group('signed-in api', () => {
      for (const path of ['/api/user', '/api/search/recent', '/api/search/suggestions', '/api/smart-picks', '/api/progress']) {
        const res = http.get(`${BASE}${path}`, Object.assign({ tags: { name: path } }, api));
        check(res, { [`${path} 200`]: (r) => r.status === 200 });
      }

      if (HISTORY_IDS.length) {
        const id = HISTORY_IDS[Math.floor(Math.random() * HISTORY_IDS.length)];
        const res = http.get(`${BASE}/api/search/history/${id}`, Object.assign({ tags: { name: '/api/search/history/:id' } }, api));
        check(res, { 'history 200': (r) => r.status === 200 });
      }

      if (RUN_SEARCH) {
        const query = QUERIES[Math.floor(Math.random() * QUERIES.length)];
        const res = http.post(`${BASE}/api/search`, JSON.stringify({ query }), Object.assign({ tags: { name: 'POST /api/search' } }, api));
        check(res, { 'search 201': (r) => r.status === 201 });
      }
    });
  }

  sleep(1 + Math.random() * 2);
}
