#!/usr/bin/env bash
# =============================================================================
# AUFlow Baseline Load Test
# =============================================================================
#
# PURPOSE
#   Verifies that AUFlow's most vulnerable endpoints behave correctly under
#   concurrency: rate limits fire correctly, no 5xx errors appear, and the
#   server does not crash.
#
# PREREQUISITES
#   1. Install wrk (HTTP benchmarking tool):
#        Fedora/RHEL:   sudo dnf install wrk
#        macOS (Homebrew): brew install wrk
#        From source:   https://github.com/wg/wrk
#
#   2. Seed test data:
#        php artisan migrate:fresh --seed
#        php artisan db:seed --class=MockTestDataSeeder
#
#      This creates a staff user (admin@auf.edu.ph / password), student users
#      (student001@auf.edu.ph … student050@auf.edu.ph / password), and mock
#      workflow progress rows that can be targeted by the approval test.
#
#   3. Start the local dev server in a separate terminal:
#        php artisan serve          # default: http://localhost:8000
#
# USAGE
#   BASE_URL=http://localhost:8000 bash tests/load/auflow-baseline.sh
#
#   All wrk output is written to tests/load/results/<timestamp>/.
#   A summary is printed at the end. Any scenario that produced unexpected
#   5xx responses triggers a WARNING line.
#
# RATE LIMIT REFERENCE (from AppServiceProvider)
#   login       : 5 req/min per email+IP
#   submissions : 5 req/min per user (or IP when unauthenticated)
#   approvals   : 10 req/min per user
#   snapshots   : no explicit limit (object-storage path lookup)
# =============================================================================

set -euo pipefail

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------
BASE_URL="${BASE_URL:-http://localhost:8000}"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
RESULTS_DIR="$(dirname "$0")/results/${TIMESTAMP}"
WRK_BIN="${WRK_BIN:-wrk}"

# Default test user (created by AdminAccountSeeder + MockTestDataSeeder)
TEST_EMAIL="${TEST_EMAIL:-admin@auf.edu.ph}"
TEST_PASSWORD="${TEST_PASSWORD:-password}"

# ---------------------------------------------------------------------------
# Colours
# ---------------------------------------------------------------------------
RED='\033[0;31m'
YELLOW='\033[1;33m'
GREEN='\033[0;32m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------
log()    { echo -e "${CYAN}[$(date +%H:%M:%S)]${NC} $*"; }
warn()   { echo -e "${YELLOW}[WARN]${NC} $*"; }
error()  { echo -e "${RED}[ERROR]${NC} $*"; }
pass()   { echo -e "${GREEN}[PASS]${NC} $*"; }
header() { echo -e "\n${BOLD}════════════════════════════════════════${NC}"; echo -e "${BOLD} $*${NC}"; echo -e "${BOLD}════════════════════════════════════════${NC}"; }

check_deps() {
    if ! command -v "$WRK_BIN" &>/dev/null; then
        error "wrk not found. Install it first:"
        error "  Fedora: sudo dnf install wrk"
        error "  macOS:  brew install wrk"
        exit 1
    fi
    if ! command -v curl &>/dev/null; then
        error "curl not found."
        exit 1
    fi
}

check_server() {
    log "Checking server at ${BASE_URL} …"
    if ! curl -sf --max-time 5 "${BASE_URL}/login" -o /dev/null; then
        error "Server not reachable at ${BASE_URL}. Start it with: php artisan serve"
        exit 1
    fi
    log "Server is up."
}

# Run wrk and save output; return the output string.
# Usage: run_wrk <label> <connections> <threads> <requests_approx> <extra_args…>
# wrk doesn't support a fixed request count natively — we use a short duration
# (--duration) sized so that connections × duration ≈ target request count at
# the expected ~100 req/s throughput locally. We cap with --duration to keep
# tests deterministic.
run_wrk() {
    local label="$1"; shift
    local connections="$1"; shift
    local threads="$1"; shift
    local duration="$1"; shift  # e.g. "5s"
    local logfile="${RESULTS_DIR}/${label}.log"

    log "Running: ${label} (${connections} connections, ${threads} threads, ${duration})"
    "$WRK_BIN" --connections "$connections" \
               --threads     "$threads" \
               --duration    "$duration" \
               "$@" 2>&1 | tee "$logfile"

    echo "$logfile"
}

# Check a wrk log file for unexpected 5xx responses.
# wrk prints "Non-2xx or 3xx responses: N" for errors; we look for 5xx in the
# body using a Lua script per scenario. Here we do a simpler check: if wrk
# reports any "Non-2xx or 3xx responses" we parse out the count and warn.
check_5xx() {
    local label="$1"
    local logfile="$2"
    local note="${3:-}"

    # wrk summary line looks like:  "Non-2xx or 3xx responses: 42"
    local non2xx
    non2xx=$(grep -oP 'Non-2xx or 3xx responses:\s*\K[0-9]+' "$logfile" || echo "0")

    if [[ "$non2xx" -gt 0 ]]; then
        # 429s are expected for rate-limited scenarios; 5xxs are not.
        # We can't distinguish from wrk summary alone, so we warn and
        # advise to inspect the log file.
        warn "SCENARIO '${label}': ${non2xx} non-2xx/3xx responses detected."
        warn "  → Inspect ${logfile} and the Laravel log (storage/logs/laravel.log)"
        warn "  → 429 responses are expected; any 500/503 indicate a server fault."
        if [[ -n "$note" ]]; then
            warn "  → ${note}"
        fi
    else
        pass "SCENARIO '${label}': No unexpected errors."
    fi
}

# ---------------------------------------------------------------------------
# Lua helper scripts written to temp files so wrk can load them
# ---------------------------------------------------------------------------
write_lua_post_login() {
    cat > "${RESULTS_DIR}/post_login.lua" <<'LUA'
-- POST application/x-www-form-urlencoded to /login
-- wrk uses this for the login flood scenario.
wrk.method = "POST"
wrk.headers["Content-Type"] = "application/x-www-form-urlencoded"
wrk.headers["Accept"] = "application/json"
-- Use a fixed email so the per-email rate limiter fires quickly.
wrk.body = "email=loadtest%40auf.edu.ph&password=wrongpassword"
LUA
}

write_lua_post_unauthenticated_submit() {
    local form_id="$1"
    cat > "${RESULTS_DIR}/post_unauth_submit.lua" <<LUA
-- Unauthenticated POST to student-dashboard submit.
-- Expected: 302 redirect to login, or 401/419 (CSRF). The middleware must
-- hold without crashing.
wrk.method = "POST"
wrk.headers["Content-Type"] = "application/json"
wrk.headers["Accept"] = "application/json"
wrk.headers["X-Requested-With"] = "XMLHttpRequest"
wrk.body = '{"_token":"invalid","fields":{}}'
LUA
}

write_lua_post_authenticated_submit() {
    local cookie="$1"
    local csrf_token="$2"
    cat > "${RESULTS_DIR}/post_auth_submit.lua" <<LUA
-- Authenticated POST to student-dashboard submit.
-- The same valid session cookie is reused; after 5 requests the submissions
-- rate limiter (5/min per user) should return 429.
wrk.method = "POST"
wrk.headers["Content-Type"] = "application/json"
wrk.headers["Accept"] = "application/json"
wrk.headers["X-Requested-With"] = "XMLHttpRequest"
wrk.headers["X-CSRF-TOKEN"] = "${csrf_token}"
wrk.headers["Cookie"] = "${cookie}"
wrk.body = '{"fields":{}}'
LUA
}

write_lua_put_authenticated_approve() {
    local cookie="$1"
    local csrf_token="$2"
    cat > "${RESULTS_DIR}/put_auth_approve.lua" <<LUA
-- Authenticated PUT to staff-dashboard approve.
-- After 10 requests the approvals rate limiter (10/min per user) fires.
-- Watch for deadlocks (SQLSTATE 40001 in Laravel log) and 500s here.
wrk.method = "PUT"
wrk.headers["Content-Type"] = "application/json"
wrk.headers["Accept"] = "application/json"
wrk.headers["X-Requested-With"] = "XMLHttpRequest"
wrk.headers["X-CSRF-TOKEN"] = "${csrf_token}"
wrk.headers["Cookie"] = "${cookie}"
wrk.body = '{"comment":"load test approval"}'
LUA
}

# ---------------------------------------------------------------------------
# Auth helpers: log in via curl and return "cookie_header;csrf_token"
# ---------------------------------------------------------------------------
login_and_capture() {
    local email="$1"
    local password="$2"
    local cookie_jar="${RESULTS_DIR}/cookiejar_${email//[@.]/_}.txt"

    log "Authenticating ${email} …"

    # Step 1: GET /login to obtain the CSRF token from the session cookie.
    local login_page
    login_page=$(curl -sf --max-time 10 \
        -c "$cookie_jar" \
        "${BASE_URL}/login" \
        -H "Accept: text/html" 2>/dev/null || true)

    if [[ -z "$login_page" ]]; then
        warn "Could not fetch login page for ${email}. Authenticated tests will be skipped."
        echo ""
        return
    fi

    # Extract CSRF token from the HTML meta tag or hidden input.
    local csrf
    csrf=$(echo "$login_page" | grep -oP '(?<=name="csrf-token" content=")[^"]+' || true)
    if [[ -z "$csrf" ]]; then
        csrf=$(echo "$login_page" | grep -oP '(?<=name="_token" value=")[^"]+' || true)
    fi
    # Also try extracting from cookie (Laravel 11+ sends XSRF-TOKEN as cookie).
    if [[ -z "$csrf" ]]; then
        csrf=$(grep 'XSRF-TOKEN' "$cookie_jar" 2>/dev/null | awk '{print $7}' | head -1 | python3 -c "import sys,urllib.parse; print(urllib.parse.unquote(sys.stdin.read().strip()))" 2>/dev/null || true)
    fi

    if [[ -z "$csrf" ]]; then
        warn "Could not extract CSRF token for ${email}. Authenticated tests will be skipped."
        echo ""
        return
    fi

    # Step 2: POST /login with credentials.
    local login_response
    login_response=$(curl -sf --max-time 10 \
        -c "$cookie_jar" -b "$cookie_jar" \
        -X POST "${BASE_URL}/login" \
        -H "Content-Type: application/x-www-form-urlencoded" \
        -H "Accept: application/json" \
        -d "email=$(python3 -c "import urllib.parse; print(urllib.parse.quote('${email}')")" \
        -d "password=$(python3 -c "import urllib.parse; print(urllib.parse.quote('${password}')")" \
        -d "_token=${csrf}" \
        -w "\nHTTP_STATUS:%{http_code}" 2>/dev/null || true)

    local status
    status=$(echo "$login_response" | grep -oP 'HTTP_STATUS:\K[0-9]+' || echo "0")

    if [[ "$status" != "200" && "$status" != "302" && "$status" != "204" ]]; then
        warn "Login failed for ${email} (HTTP ${status}). Authenticated tests will be skipped."
        echo ""
        return
    fi

    # Build the cookie header string from the cookie jar.
    local cookie_header
    cookie_header=$(grep -v '^#' "$cookie_jar" 2>/dev/null | awk 'NF>=7 {printf "%s=%s; ", $6, $7}' | sed 's/; $//' || true)

    if [[ -z "$cookie_header" ]]; then
        warn "No cookies captured for ${email}. Authenticated tests will be skipped."
        echo ""
        return
    fi

    log "Authenticated as ${email} (cookies captured)."
    echo "${cookie_header}|${csrf}"
}

# ---------------------------------------------------------------------------
# Discover a real form ID and progress ID from the seeded data
# ---------------------------------------------------------------------------
discover_ids() {
    log "Discovering seeded form and progress IDs …"

    # Use curl to hit the student forms list and parse the first form ID.
    # We rely on the JSON API returning Inertia props.
    local form_id=""
    local progress_id=""

    # Try to get a form ID from the database via artisan tinker (no HTTP needed).
    form_id=$(php artisan tinker --execute \
        'echo \App\Modules\FormBuilder\Models\Form::where("status","active")->value("id");' \
        2>/dev/null | tail -1 | tr -d '[:space:]' || true)

    progress_id=$(php artisan tinker --execute \
        'echo \App\Modules\WorkflowBuilder\Models\WorkflowStepProgress::where("status","pending")->value("id");' \
        2>/dev/null | tail -1 | tr -d '[:space:]' || true)

    # Fallback to placeholder values if artisan not available in PATH context.
    form_id="${form_id:-1}"
    progress_id="${progress_id:-1}"

    echo "${form_id}|${progress_id}"
}

# ---------------------------------------------------------------------------
# SCENARIO 1 – Login flood
# ---------------------------------------------------------------------------
scenario_login() {
    header "SCENARIO 1: Login Endpoint Flood"
    cat <<'INFO'
  Goal    : Flood POST /login with 50 concurrent connections for ~10 seconds.
  Expected: After 5 requests/min per email+IP the limiter returns 429.
            No 500/503 errors. Server stays responsive.
  Limiter : throttle:login → 5 req/min per email+IP (AppServiceProvider)
INFO

    write_lua_post_login

    local logfile
    logfile=$(run_wrk "01_login_flood" \
        50 4 "10s" \
        --script "${RESULTS_DIR}/post_login.lua" \
        "${BASE_URL}/login")

    check_5xx "01_login_flood" "$logfile" \
        "429s indicate the limiter is working; 500s indicate a server fault."
}

# ---------------------------------------------------------------------------
# SCENARIO 2 – Unauthenticated submit flood
# ---------------------------------------------------------------------------
scenario_unauth_submit() {
    local form_id="$1"
    header "SCENARIO 2: Unauthenticated Submit Flood (form ${form_id})"
    cat <<'INFO'
  Goal    : Hit POST student-dashboard/forms/{id}/submit without a session.
  Expected: 302 redirect to /login or 401/419 CSRF mismatch. The IP-based
            submissions limiter (5/min) fires quickly. No crash.
INFO

    write_lua_post_unauthenticated_submit "$form_id"

    local logfile
    logfile=$(run_wrk "02_unauth_submit" \
        30 4 "8s" \
        --script "${RESULTS_DIR}/post_unauth_submit.lua" \
        "${BASE_URL}/student-dashboard/forms/${form_id}/submit")

    check_5xx "02_unauth_submit" "$logfile"
}

# ---------------------------------------------------------------------------
# SCENARIO 3 – Authenticated submission flood
# ---------------------------------------------------------------------------
scenario_auth_submit() {
    local form_id="$1"
    local auth_result="$2"

    header "SCENARIO 3: Authenticated Submission Flood (form ${form_id})"

    if [[ -z "$auth_result" ]]; then
        warn "Skipping: authentication not available."
        return
    fi

    local cookie="${auth_result%%|*}"
    local csrf="${auth_result##*|}"

    cat <<'INFO'
  Goal    : Authenticated POST student-dashboard/forms/{id}/submit with 20
            concurrent connections for ~8 seconds.
  Expected: First 5 requests succeed (or fail on missing fields — that is OK).
            After that the submissions limiter (5 req/min per user) returns 429.
            No 500/503 errors. Idempotency key prevents duplicate DB writes.
INFO

    write_lua_post_authenticated_submit "$cookie" "$csrf"

    local logfile
    logfile=$(run_wrk "03_auth_submit" \
        20 4 "8s" \
        --script "${RESULTS_DIR}/post_auth_submit.lua" \
        "${BASE_URL}/student-dashboard/forms/${form_id}/submit")

    check_5xx "03_auth_submit" "$logfile"
}

# ---------------------------------------------------------------------------
# SCENARIO 4 – Authenticated approval flood
# ---------------------------------------------------------------------------
scenario_auth_approve() {
    local progress_id="$1"
    local auth_result="$2"

    header "SCENARIO 4: Authenticated Approval Flood (progress ${progress_id})"

    if [[ -z "$auth_result" ]]; then
        warn "Skipping: authentication not available."
        return
    fi

    local cookie="${auth_result%%|*}"
    local csrf="${auth_result##*|}"

    cat <<'INFO'
  Goal    : Authenticated PUT staff-dashboard/progress/{id}/approve with 20
            concurrent connections for ~8 seconds.
  Expected: After the approvals limiter (10 req/min per user) fires, 429s
            appear. Watch the Laravel log for SQLSTATE[40001] deadlock errors
            — those indicate a missing lockForUpdate() in the approval action.
            No 500/503 errors beyond what the limiter produces.
INFO

    write_lua_put_authenticated_approve "$cookie" "$csrf"

    local logfile
    logfile=$(run_wrk "04_auth_approve" \
        20 4 "8s" \
        --script "${RESULTS_DIR}/put_auth_approve.lua" \
        "${BASE_URL}/staff-dashboard/progress/${progress_id}/approve")

    check_5xx "04_auth_approve" "$logfile" \
        "Check storage/logs/laravel.log for SQLSTATE[40001] deadlock errors."
}

# ---------------------------------------------------------------------------
# SCENARIO 5 – Snapshot read-heavy
# ---------------------------------------------------------------------------
scenario_snapshot() {
    header "SCENARIO 5: Snapshot Retrieval (read-heavy)"

    # Discover a real public_id from the database.
    local public_id
    public_id=$(php artisan tinker --execute \
        'echo \App\Modules\VerificationSnapshot\Models\Snapshot::value("public_id");' \
        2>/dev/null | tail -1 | tr -d '[:space:]' || true)

    if [[ -z "$public_id" || "$public_id" == "null" ]]; then
        warn "No snapshots found in the database."
        warn "Run: php artisan db:seed --class=MockTestDataSeeder"
        warn "Skipping snapshot scenario."
        return
    fi

    cat <<INFO
  Goal    : GET /snapshots/${public_id} with 100 concurrent connections for 15s.
  Expected: Responses are fast (< 200 ms p99). No 500/503 errors.
            This is a read-only public endpoint; there is no explicit rate limit.
            We are stress-testing the object-storage path lookup and Eloquent
            query performance.
INFO

    local logfile
    logfile=$(run_wrk "05_snapshot_read" \
        100 8 "15s" \
        "${BASE_URL}/snapshots/${public_id}")

    check_5xx "05_snapshot_read" "$logfile"
}

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------
print_summary() {
    header "SUMMARY"
    echo "Results directory: ${RESULTS_DIR}"
    echo ""
    echo "Log files:"
    for f in "${RESULTS_DIR}"/*.log; do
        [[ -f "$f" ]] || continue
        local warnings
        warnings=$(grep -c 'Non-2xx or 3xx' "$f" 2>/dev/null || echo "0")
        printf "  %-40s  %s\n" "$(basename "$f")" \
            "$(grep 'Non-2xx or 3xx' "$f" 2>/dev/null || echo 'No errors')"
    done
    echo ""
    echo "Next steps:"
    echo "  1. Check storage/logs/laravel.log for any 500-level errors or deadlocks."
    echo "  2. Run: redis-cli monitor  to verify rate limit counters are incrementing."
    echo "  3. For deeper analysis, feed the .log files into wrk2 or vegeta."
}

# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------
main() {
    header "AUFlow Baseline Load Test — ${TIMESTAMP}"
    echo "  Base URL : ${BASE_URL}"
    echo "  Results  : ${RESULTS_DIR}"

    check_deps
    mkdir -p "${RESULTS_DIR}"
    check_server

    # Discover real IDs from seeded data.
    local ids
    ids=$(discover_ids)
    local form_id="${ids%%|*}"
    local progress_id="${ids##*|}"
    log "Using form_id=${form_id}, progress_id=${progress_id}"

    # Authenticate once; share session for scenarios 3 & 4.
    local auth_result
    auth_result=$(login_and_capture "$TEST_EMAIL" "$TEST_PASSWORD")

    # Run scenarios sequentially to avoid cross-scenario interference.
    scenario_login
    scenario_unauth_submit "$form_id"
    scenario_auth_submit   "$form_id"     "$auth_result"
    scenario_auth_approve  "$progress_id" "$auth_result"
    scenario_snapshot

    print_summary
}

main "$@"
