#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${APP_TEST_BASE_URL:-${BASE_URL:-http://localhost:8080}}"

assert_page_contains() {
  local path="$1"
  local expected="$2"
  local body

  body="$(curl -fsS "${BASE_URL}${path}")"

  if [[ "${body}" != *"${expected}"* ]]; then
    echo "Expected ${path} to contain '${expected}'." >&2
    exit 1
  fi

  echo "[OK] ${path}"
}

assert_page_contains "/" "FlashMind"
assert_page_contains "/login" "Sign In"
assert_page_contains "/register" "Sign Up"
assert_page_contains "/explore" "Explore Marketplace"

echo "Integration endpoint checks passed for ${BASE_URL}"
