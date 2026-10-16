import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '30s', target: 10 },
    { duration: '1m', target: 10 },
    { duration: '30s', target: 0 },
  ],
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<1000'],
  },
};

export default function () {
  const baseUrl = __ENV.BASE_URL || 'http://127.0.0.1:8000';

  const response = http.get(`${baseUrl}/`);

  check(response, {
    'status is 200': (r) => r.status === 200,
  });

  sleep(1);
}
