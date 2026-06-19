import http from 'k6/http';
import { check, fail, sleep } from 'k6';
import { Counter } from 'k6/metrics';
import { open as fsOpen } from 'k6/experimental/fs';
import exec from 'k6/execution';

/*
 * k6 Load Test for AUFlow
 *
 * Smoke test mode:
 * SMOKE=1 k6 run tests/load/auflow-load-test.js
 */

export const rateLimitedTotal = new Counter('rate_limited_total');
export const httpStatusTotal = new Counter('http_status_total');
export const httpErrorStatusTotal = new Counter('http_error_status_total');
const httpErrorStatusCounters = {};
for (let status = 400; status <= 599; status += 1) {
  httpErrorStatusCounters[status] = new Counter(`http_status_${status}_total`);
}

const isSmoke = __ENV.SMOKE === '1';
const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const RESULTS_DIR = __ENV.K6_RESULTS_DIR || 'tests/load/results';
const SUMMARY_FALLBACK_PATH = __ENV.K6_SUMMARY_FALLBACK_PATH || 'k6-summary.json';
const DEFAULT_STUDENT_EMAILS = [
  'student.juan@auf.edu.ph',
  'student.ana@auf.edu.ph',
  'student.pedro@auf.edu.ph',
  'student.maria@auf.edu.ph',
  'student.carlo@auf.edu.ph',
  'student.liza@auf.edu.ph',
  'student.jose@auf.edu.ph',
  'student.nina@auf.edu.ph',
];

const defaultFormIdCfg = parsePositiveIntEnv('TEST_FORM_ID', 1);
const defaultProgressIdCfg = parsePositiveIntEnv('TEST_PROGRESS_ID', 1);
const DEFAULT_FORM_ID = defaultFormIdCfg.value;
const DEFAULT_PROGRESS_ID = defaultProgressIdCfg.value;

const inertiaParseState = {
  studentForms: { failedAttempts: 0, disableParsing: false, successLogged: false },
  studentFormDetails: { failedAttempts: 0, disableParsing: false, successLogged: false },
  staffRequests: { failedAttempts: 0, disableParsing: false, successLogged: false },
};

const loginState = {
  studentWarned: false,
  staffWarned: false,
};

const authState = {
  student: { loggedIn: false, csrfToken: null },
  staff: { loggedIn: false, csrfToken: null },
};

const summaryOutputPath = await resolveSummaryOutputPath();

export const options = {
  noCookiesReset: true,
  scenarios: {
    student_scenario: {
      executor: 'ramping-vus',
      startVUs: 1,
      stages: isSmoke
        ? [{ duration: '30s', target: 4 }]
        : [
            { duration: '2m', target: 80 },
            { duration: '3m', target: 80 },
            { duration: '1m', target: 0 },
          ],
      exec: 'studentScenario',
    },
    staff_scenario: {
      executor: 'ramping-vus',
      startVUs: 1,
      stages: isSmoke
        ? [{ duration: '30s', target: 1 }]
        : [
            { duration: '2m', target: 20 },
            { duration: '3m', target: 20 },
            { duration: '1m', target: 0 },
          ],
      exec: 'staffScenario',
    },
  },
  thresholds: isSmoke
    ? {}
    : {
        http_req_failed: ['rate<0.10'],
        http_req_duration: ['p(95)<2000'],
        'http_req_duration{name:submit}': ['p(95)<3000'],
      },
};

export async function setup() {
  logFallbackIdWarnings();

  const deadline = Date.now() + 5000;
  let lastConnectionError = 'unknown connection error';

  while (Date.now() < deadline) {
    const probe = http.get(`${BASE_URL}/login`, {
      redirects: 0,
      timeout: '2s',
      tags: { name: 'startup_probe_login' },
    });

    recordStatus(probe, 'startup_probe_login');

    if (probe.status > 0) {
      return { baseUrl: BASE_URL };
    }

    lastConnectionError = probe.error || probe.error_code || 'unknown connection error';
    sleep(1);
  }

  fail(`Cannot reach ${BASE_URL}. Is the dev server running? Last error: ${lastConnectionError}`);
}

export function handleSummary(data) {
  const totalRequests = getMetricValue(data, 'http_reqs', 'count');
  const failureRate = getMetricValue(data, 'http_req_failed', 'rate');
  const p95ResponseMs = getMetricValue(data, 'http_req_duration', 'p(95)');
  const rateLimitedCount = getMetricValue(data, 'rate_limited_total', 'count');

  console.log('\n========== k6 Summary ==========');
  console.log(`Total requests: ${Math.round(totalRequests)}`);
  console.log(`Failure rate: ${(failureRate * 100).toFixed(2)}%`);
  console.log(`p95 response time: ${p95ResponseMs.toFixed(2)} ms`);
  console.log(`rate_limited_total: ${Math.round(rateLimitedCount)}`);

  if (isSmoke) {
    const statuses = [];

    for (let status = 400; status <= 599; status += 1) {
      const count = getMetricValue(data, `http_status_${status}_total`, 'count');
      if (count > 0) {
        statuses.push({ status, count });
      }
    }

    console.log('Smoke error statuses (4xx/5xx):');
    if (statuses.length === 0) {
      console.log('- none observed');
    } else {
      for (const row of statuses) {
        console.log(`- ${row.status}: ${Math.round(row.count)}`);
      }
    }
  }

  console.log(`Summary JSON output: ${summaryOutputPath}`);

  return {
    [summaryOutputPath]: JSON.stringify(data, null, 2),
  };
}

function parsePositiveIntEnv(name, fallbackValue) {
  const raw = __ENV[name];

  if (raw === undefined || raw === null || raw === '') {
    return { value: fallbackValue, source: 'default', raw: null };
  }

  const parsed = Number(raw);
  if (!Number.isInteger(parsed) || parsed <= 0) {
    return { value: fallbackValue, source: 'invalid', raw };
  }

  return { value: parsed, source: 'env', raw };
}

function logFallbackIdWarnings() {
  if (defaultFormIdCfg.source === 'invalid') {
    console.warn(`[WARN] TEST_FORM_ID="${defaultFormIdCfg.raw}" is invalid. Falling back to ${DEFAULT_FORM_ID}.`);
  }
  if (defaultProgressIdCfg.source === 'invalid') {
    console.warn(`[WARN] TEST_PROGRESS_ID="${defaultProgressIdCfg.raw}" is invalid. Falling back to ${DEFAULT_PROGRESS_ID}.`);
  }
  if (defaultFormIdCfg.source === 'default' && DEFAULT_FORM_ID === 1) {
    console.warn('[WARN] TEST_FORM_ID is not set and fallback ID 1 is being used. Seed the database first and set TEST_FORM_ID for stable runs.');
  }
  if (defaultProgressIdCfg.source === 'default' && DEFAULT_PROGRESS_ID === 1) {
    console.warn('[WARN] TEST_PROGRESS_ID is not set and fallback ID 1 is being used. Seed the database first and set TEST_PROGRESS_ID for stable runs.');
  }
}

function resolveAbsolutePath(path) {
  if (path.startsWith('/')) {
    return path;
  }

  const pwd = __ENV.PWD || '';
  if (!pwd) {
    return path;
  }

  return `${pwd.replace(/\/+$/, '')}/${path}`;
}

function resolveStudentEmail() {
  if (__ENV.TEST_STUDENT_EMAIL) {
    return __ENV.TEST_STUDENT_EMAIL;
  }

  const configuredList = (__ENV.TEST_STUDENT_EMAILS || '')
    .split(',')
    .map((value) => value.trim())
    .filter((value) => value.length > 0);

  const pool = configuredList.length > 0 ? configuredList : DEFAULT_STUDENT_EMAILS;
  const vuId = exec.vu.idInTest || 1;
  return pool[(vuId - 1) % pool.length];
}

async function resolveSummaryOutputPath() {
  const absoluteResultsDir = resolveAbsolutePath(RESULTS_DIR);

  try {
    await fsOpen(absoluteResultsDir);

    console.warn(`[WARN] ${RESULTS_DIR} resolves to a file path, not a directory. Falling back summary output to ${SUMMARY_FALLBACK_PATH}.`);
    return SUMMARY_FALLBACK_PATH;
  } catch (error) {
    const errorText = String(error);

    if (errorText.includes('opening a directory is not supported')) {
      return `${RESULTS_DIR}/k6-summary.json`;
    }

    if (errorText.includes('no such file or directory')) {
      console.warn(`[WARN] Results directory "${RESULTS_DIR}" does not exist. k6 cannot create directories in this runtime.`);
      console.warn(`[WARN] Run: mkdir -p ${RESULTS_DIR}`);
      console.warn(`[WARN] Falling back summary output to ${SUMMARY_FALLBACK_PATH}.`);
      return SUMMARY_FALLBACK_PATH;
    }

    console.warn(`[WARN] Could not verify results directory "${RESULTS_DIR}": ${errorText}`);
    console.warn(`[WARN] Falling back summary output to ${SUMMARY_FALLBACK_PATH}.`);
    return SUMMARY_FALLBACK_PATH;
  }
}

function extractCsrf(res) {
  const cookie = res.cookies['XSRF-TOKEN'];
  if (cookie && cookie.length > 0) {
    const rawValue = cookie[0].value;
    return decodeURIComponent(rawValue);
  }
  return null;
}

function logLoginRedirectWarning(response, actor) {
  const location = response.headers.Location || response.headers.location || '';
  const bouncedToLogin =
    response.status >= 300 &&
    response.status < 400 &&
    (location === '/login' || location.endsWith('/login'));

  if (!bouncedToLogin) {
    return;
  }

  if (actor === 'student' && !loginState.studentWarned) {
    console.warn('[WARN] student login redirected back to /login. Check TEST_STUDENT_EMAIL / TEST_STUDENT_PASSWORD and CSRF/session setup.');
    loginState.studentWarned = true;
  }

  if (actor === 'staff' && !loginState.staffWarned) {
    console.warn('[WARN] staff login redirected back to /login. Check TEST_STAFF_EMAIL / TEST_STAFF_PASSWORD and CSRF/session setup.');
    loginState.staffWarned = true;
  }
}

function ensureAuthenticated(actor, email, password) {
  const state = authState[actor];
  if (state && state.loggedIn && state.csrfToken) {
    return state;
  }

  let res = http.get(`${BASE_URL}/login`, {
    redirects: 0,
    tags: { name: `${actor}_get_login` },
  });
  recordStatus(res, `${actor}_get_login`);

  const csrfToken = extractCsrf(res);

  res = http.post(
    `${BASE_URL}/login`,
    {
      email,
      password,
    },
    {
      redirects: 0,
      tags: { name: `${actor}_post_login` },
      headers: {
        'X-XSRF-TOKEN': csrfToken,
      },
    },
  );
  recordStatus(res, `${actor}_post_login`);

  const location = res.headers.Location || res.headers.location || '';
  const successRedirect = res.status >= 300 && res.status < 400 && !location.endsWith('/login');

  if (!successRedirect) {
    logLoginRedirectWarning(res, actor);
    return null;
  }

  state.loggedIn = true;
  state.csrfToken = extractCsrf(res) || csrfToken;

  return state;
}

function decodeHtmlEntities(input) {
  return String(input)
    .replace(/&quot;/g, '"')
    .replace(/&#34;/g, '"')
    .replace(/&apos;/g, "'")
    .replace(/&#39;/g, "'")
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&#x([0-9a-fA-F]+);/g, (_, hex) => String.fromCharCode(parseInt(hex, 16)))
    .replace(/&#(\d+);/g, (_, dec) => String.fromCharCode(parseInt(dec, 10)));
}

function normalizeSnippet(input, maxLen = 450) {
  return String(input || '')
    .replace(/\s+/g, ' ')
    .trim()
    .slice(0, maxLen);
}

function extractDataPage(html) {
  const appDivMatch = html.match(/<div[^>]*\bid\s*=\s*(['"])app\1[^>]*>/is);
  if (!appDivMatch) {
    return { dataPageJson: null, htmlSnippet: normalizeSnippet(html) };
  }

  const appDivTag = appDivMatch[0];
  const dataPageMatch = appDivTag.match(/\bdata-page\s*=\s*(?:"([\s\S]*?)"|'([\s\S]*?)')/i);

  if (!dataPageMatch) {
    return { dataPageJson: null, htmlSnippet: normalizeSnippet(appDivTag) };
  }

  const encoded = dataPageMatch[1] ?? dataPageMatch[2] ?? '';
  return {
    dataPageJson: decodeHtmlEntities(encoded).trim(),
    htmlSnippet: normalizeSnippet(appDivTag),
  };
}

function handleParseFailure(contextKey, reason, htmlSnippet) {
  const state = inertiaParseState[contextKey];

  if (!isSmoke) {
    console.warn(`[WARN] ${contextKey}: Inertia parse failed (${reason}).`);
    return null;
  }

  state.failedAttempts += 1;

  if (state.failedAttempts >= 3) {
    state.disableParsing = true;
    console.error(`[ERROR] ${contextKey}: Inertia parse failed ${state.failedAttempts} times; using fallback IDs. HTML snippet: ${htmlSnippet}`);
    return null;
  }

  console.warn(`[WARN] ${contextKey}: Inertia parse attempt ${state.failedAttempts} failed (${reason}); retrying.`);
  return null;
}

function parseInertiaPage(response, contextKey) {
  const state = inertiaParseState[contextKey];
  if (isSmoke && state.disableParsing) {
    return null;
  }

  let html = '';
  try {
    html = response.html().html();
  } catch (_) {
    html = '';
  }

  if (!html && typeof response.body === 'string') {
    html = response.body;
  }

  const { dataPageJson, htmlSnippet } = extractDataPage(html);
  if (!dataPageJson) {
    return handleParseFailure(contextKey, 'missing data-page on #app', htmlSnippet);
  }

  try {
    const parsed = JSON.parse(dataPageJson);
    if (!state.successLogged) {
      console.log(`[INFO] ${contextKey}: parsed Inertia data-page successfully (component: ${parsed.component || 'unknown'})`);
      state.successLogged = true;
    }
    return parsed;
  } catch (error) {
    return handleParseFailure(contextKey, `JSON parse error: ${error}`, htmlSnippet);
  }
}

function listItems(value) {
  if (Array.isArray(value)) {
    return value;
  }

  if (value && Array.isArray(value.data)) {
    return value.data;
  }

  return [];
}

function parseMaybeJson(value) {
  if (value && typeof value === 'object') {
    return value;
  }

  if (typeof value !== 'string') {
    return null;
  }

  try {
    return JSON.parse(value);
  } catch (_) {
    return null;
  }
}

function optionValue(option) {
  if (option && typeof option === 'object') {
    const candidate = option.value ?? option.label ?? option.id;
    return String(candidate ?? 'Option A');
  }

  return String(option ?? 'Option A');
}

function isFieldRequired(field) {
  return field && (field.is_required === true || field.is_required === 1 || field.is_required === '1');
}

function isDateRangeMode(field) {
  return String(field?.date_mode ?? 'single') === 'range';
}

function isSlotBasedDateField(field) {
  if (!field || String(field.data_type) !== 'date') {
    return false;
  }

  const useSlots = field.use_slots === true || field.use_slots === 1 || field.use_slots === '1';
  const requireFacility = field.require_facility === true || field.require_facility === 1 || field.require_facility === '1';
  return (useSlots || requireFacility) && !isDateRangeMode(field);
}

function hasRequiredSlotBasedDateField(fields) {
  for (const field of fields) {
    if (isFieldRequired(field) && isSlotBasedDateField(field)) {
      return true;
    }
  }

  return false;
}

function buildTableFieldValue(field) {
  const options = parseMaybeJson(field?.field_options) || {};
  const columns = Array.isArray(options.table_columns) ? options.table_columns : [];

  if (columns.length === 0) {
    return JSON.stringify([{ item: 'Sample item' }]);
  }

  const row = {};
  for (const column of columns) {
    const key = String(column?.id ?? '').trim();
    if (!key) {
      continue;
    }

    const type = String(column?.type ?? 'text').toLowerCase();
    if (type === 'number') {
      row[key] = 1;
    } else {
      row[key] = 'Sample value';
    }
  }

  return JSON.stringify([row]);
}

function buildStudentPayloadFromFields(fields) {
  const payload = {};
  const dateRanges = [];
  const today = new Date();
  const start = today.toISOString().slice(0, 10);
  const endDate = new Date(today.getTime() + 24 * 60 * 60 * 1000);
  const end = endDate.toISOString().slice(0, 10);

  for (const field of fields) {
    if (!field || !field.field_name) {
      continue;
    }

    const key = String(field.field_name);
    const dataType = String(field.data_type ?? '').toLowerCase();
    const required = isFieldRequired(field);

    if (!required) {
      continue;
    }

    if (dataType === 'date') {
      if (isDateRangeMode(field)) {
        dateRanges.push({
          field_name: key,
          start,
          end,
        });
      } else if (!isSlotBasedDateField(field)) {
        payload[key] = start;
      }

      continue;
    }

    if (dataType === 'number') {
      payload[key] = 1;
      continue;
    }

    if (dataType === 'email') {
      payload[key] = 'load.test@auf.edu.ph';
      continue;
    }

    if (dataType === 'textarea') {
      payload[key] = 'Load test submission';
      continue;
    }

    if (dataType === 'table') {
      payload[key] = buildTableFieldValue(field);
      continue;
    }

    if (dataType === 'select' || dataType === 'radio') {
      const options = Array.isArray(field.options) ? field.options : [];
      payload[key] = optionValue(options[0]);
      continue;
    }

    if (dataType === 'checkbox') {
      const options = Array.isArray(field.options) ? field.options : [];
      payload[key] = options.length > 1 ? [optionValue(options[0])] : true;
      continue;
    }

    payload[key] = key === 'student_id' ? '20XX-00001' : 'Load Test Value';
  }

  if (dateRanges.length > 0) {
    payload.date_ranges = dateRanges;
  }

  return payload;
}

function findFirstPendingProgressId(requests) {
  const rows = listItems(requests);

  for (const row of rows) {
    if (String(row?.status ?? '').toLowerCase() !== 'pending') {
      continue;
    }

    const candidate = Number(row?.progress_id ?? row?.id);
    if (Number.isInteger(candidate) && candidate > 0) {
      return candidate;
    }
  }

  return null;
}

function recordStatus(response, endpointTag) {
  if (!response) {
    return;
  }

  const status = Number(response.status || 0);

  httpStatusTotal.add(1, {
    endpoint: endpointTag,
    status: String(status),
  });

  if (status >= 400 && status < 600) {
    httpErrorStatusTotal.add(1, {
      endpoint: endpointTag,
      status: String(status),
    });

    httpErrorStatusCounters[status].add(1);
  }
}

function getMetricValue(data, metricName, key) {
  const metric = data && data.metrics ? data.metrics[metricName] : null;
  if (!metric || !metric.values || metric.values[key] === undefined) {
    return 0;
  }

  const value = Number(metric.values[key]);
  return Number.isFinite(value) ? value : 0;
}

export function studentScenario() {
  const email = resolveStudentEmail();
  const password = __ENV.TEST_STUDENT_PASSWORD || 'password';

  const auth = ensureAuthenticated('student', email, password);
  if (!auth) {
    sleep(1);
    return;
  }

  let csrfToken = auth.csrfToken;

  let res = http.get(`${BASE_URL}/student-dashboard/forms`, {
    headers: {
      Accept: 'text/html',
    },
  });
  recordStatus(res, 'student_get_forms');

  csrfToken = extractCsrf(res) || csrfToken;
  auth.csrfToken = csrfToken;
  let formId = DEFAULT_FORM_ID;
  let submissionFields = [];
  const props = parseInertiaPage(res, 'studentForms');
  const forms = props && props.props ? listItems(props.props.forms) : [];
  const firstForm = forms.length > 0 ? forms[0] : null;

  if (firstForm && Number.isInteger(Number(firstForm.id)) && Number(firstForm.id) > 0) {
    formId = Number(firstForm.id);
  } else {
    console.warn('Student: Inertia parsing fell back to hardcoded form ID');
  }

  // Prefer a required-field-compatible form and avoid slot-based required dates.
  const candidateIds = [];
  const seenCandidateIds = new Set();
  if (Number.isInteger(Number(formId)) && Number(formId) > 0) {
    const id = Number(formId);
    candidateIds.push(id);
    seenCandidateIds.add(id);
  }
  for (const form of forms) {
    const id = Number(form?.id);
    if (!Number.isInteger(id) || id <= 0 || seenCandidateIds.has(id)) {
      continue;
    }

    candidateIds.push(id);
    seenCandidateIds.add(id);
  }

  for (const candidateId of candidateIds) {
    const detailRes = http.get(`${BASE_URL}/student-dashboard/forms/${candidateId}`, {
      headers: {
        Accept: 'text/html',
      },
    });
    recordStatus(detailRes, 'student_get_form_details');

    csrfToken = extractCsrf(detailRes) || csrfToken;
    auth.csrfToken = csrfToken;

    const detailProps = parseInertiaPage(detailRes, 'studentFormDetails');
    const fields = detailProps?.props?.form?.fields;

    if (!Array.isArray(fields) || fields.length === 0) {
      continue;
    }

    if (hasRequiredSlotBasedDateField(fields)) {
      continue;
    }

    formId = candidateId;
    submissionFields = fields;
    break;
  }

  if (!Array.isArray(submissionFields) || submissionFields.length === 0) {
    console.warn('Student: no compatible form fields found for payload build; skipping submit iteration');
    sleep(Math.random() * 3 + 2);
    return;
  }

  const submissionPayload = buildStudentPayloadFromFields(submissionFields);

  res = http.post(
    `${BASE_URL}/student-dashboard/forms/${formId}/submit`,
    JSON.stringify(submissionPayload),
    {
      tags: { name: 'submit', expectedStatus: 429 },
      responseCallback: http.expectedStatuses({ min: 200, max: 399 }, 429),
      headers: {
        'X-XSRF-TOKEN': csrfToken,
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
    },
  );
  recordStatus(res, 'student_post_submit');

  if (res.status === 429) {
    rateLimitedTotal.add(1);
  } else {
    check(res, {
      'student submit success': (r) => r.status >= 200 && r.status < 400,
    });
  }

  sleep(Math.random() * 3 + 2);
}

export function staffScenario() {
  const email = __ENV.TEST_STAFF_EMAIL || 'auflow.auf@gmail.com';
  const password = __ENV.TEST_STAFF_PASSWORD || 'password';

  const auth = ensureAuthenticated('staff', email, password);
  if (!auth) {
    sleep(1);
    return;
  }

  let csrfToken = auth.csrfToken;

  let res = http.get(`${BASE_URL}/staff-dashboard/requests`, {
    headers: {
      Accept: 'text/html',
    },
  });
  recordStatus(res, 'staff_get_requests');

  csrfToken = extractCsrf(res) || csrfToken;
  auth.csrfToken = csrfToken;
  let progressId = DEFAULT_PROGRESS_ID;
  const props = parseInertiaPage(res, 'staffRequests');
  const pendingProgressId = props && props.props ? findFirstPendingProgressId(props.props.requests) : null;

  if (Number.isInteger(Number(pendingProgressId)) && Number(pendingProgressId) > 0) {
    progressId = Number(pendingProgressId);
  } else {
    console.warn('Staff: no pending request found; skipping approve iteration');
    sleep(Math.random() * 5 + 3);
    return;
  }

  res = http.put(
    `${BASE_URL}/staff-dashboard/progress/${progressId}/approve`,
    JSON.stringify({
      comment: 'Approved via load test',
    }),
    {
      tags: { name: 'approve', expectedStatus: 429 },
      responseCallback: http.expectedStatuses({ min: 200, max: 399 }, 429),
      headers: {
        'X-XSRF-TOKEN': csrfToken,
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
    },
  );
  recordStatus(res, 'staff_put_approve');

  if (res.status === 429) {
    rateLimitedTotal.add(1);
  } else {
    check(res, {
      'staff approve success': (r) => r.status >= 200 && r.status < 400,
    });
  }

  sleep(Math.random() * 5 + 3);
}
