#!/usr/bin/env node
// api/v2 probe runner. Zero dependencies.
// Usage: node run_probes.mjs [baseUrl]   (default http://localhost:8087)
// Cases: cases/*.json, each an array of probe objects. Exit 1 on any failure.

import { readFileSync, readdirSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const BASE = (process.argv[2] || "http://localhost:8087").replace(/\/$/, "");
const CASES_DIR = join(dirname(fileURLToPath(import.meta.url)), "cases");

// Fixture keys (tests/api-v2/fixture.sql)
const KEYS = {
  ALL_KEY: "ci-key-allclients-000000000000001",
  CLIENT_KEY: "ci-key-client101-0000000000000002",
  EXPIRED_KEY: "ci-key-expired-000000000000000003",
  BAD_KEY: "ci-key-not-in-the-database-000000",
};

const sub = (s) => s.replace(/\$\{(\w+)\}/g, (_, k) => KEYS[k] ?? `\${${k}}`);

function check(desc, cond, got, failures) {
  if (!cond) failures.push(`${desc} (got: ${JSON.stringify(got)})`);
}

async function runProbe(probe) {
  const failures = [];
  const headers = { ...(probe.headers || {}) };
  for (const k of Object.keys(headers)) headers[k] = sub(headers[k]);

  const init = { method: probe.method || "GET", headers, signal: AbortSignal.timeout(15000) };
  if (probe.body !== undefined) {
    init.body = typeof probe.body === "string" ? sub(probe.body) : JSON.stringify(probe.body);
    headers["Content-Type"] ??= "application/json";
  }

  const res = await fetch(BASE + sub(probe.path), init);
  const text = await res.text();
  let json = null;
  try { json = JSON.parse(text); } catch { /* asserted below */ }

  const e = probe.expect || {};
  check(`status ${e.status}`, res.status === e.status, res.status, failures);
  if (e.json !== false) check("body is JSON", json !== null, text.slice(0, 200), failures);

  if (json !== null) {
    // v2 contract: success is a real boolean; data present on success; error object on failure
    if (e.success !== undefined) {
      check(`success === ${e.success}`, json.success === e.success, json.success, failures);
      if (e.success === true) check("data present", "data" in json, Object.keys(json), failures);
      if (e.success === false) check("error object present", typeof json.error === "object" && json.error !== null, json.error, failures);
    }
    if (e.error_code) check(`error.code ${e.error_code}`, json.error?.code === e.error_code, json.error?.code, failures);
    if (e.data_includes) {
      for (const [k, v] of Object.entries(e.data_includes)) {
        check(`data.${k} === ${JSON.stringify(v)}`, JSON.stringify(json.data?.[k]) === JSON.stringify(v), json.data?.[k], failures);
      }
    }
    if (e.data_types) {
      for (const [k, t] of Object.entries(e.data_types)) {
        const val = json.data?.[k];
        const actual = Array.isArray(val) ? "array" : typeof val;
        check(`data.${k} is ${t}`, actual === t, actual, failures);
      }
    }
    if (e.data_contains) {
      for (const [k, v] of Object.entries(e.data_contains)) {
        const arr = json.data?.[k];
        check(`data.${k} contains ${JSON.stringify(v)}`, Array.isArray(arr) && arr.includes(v), arr, failures);
      }
    }
    if (e.data_length !== undefined) {
      check(`data length ${e.data_length}`, Array.isArray(json.data) && json.data.length === e.data_length,
        Array.isArray(json.data) ? json.data.length : json.data, failures);
    }
    if (e.meta_includes) {
      for (const [k, v] of Object.entries(e.meta_includes)) {
        check(`meta.${k} === ${JSON.stringify(v)}`, JSON.stringify(json.meta?.[k]) === JSON.stringify(v), json.meta?.[k], failures);
      }
    }
    if (e.first_includes) {
      for (const [k, v] of Object.entries(e.first_includes)) {
        check(`data[0].${k} === ${JSON.stringify(v)}`, JSON.stringify(json.data?.[0]?.[k]) === JSON.stringify(v), json.data?.[0]?.[k], failures);
      }
    }
    // v1 canary: raw top-level equality (stringly success and friends must stay stringly)
    if (e.raw) {
      for (const [k, v] of Object.entries(e.raw)) {
        check(`raw.${k} === ${JSON.stringify(v)}`, JSON.stringify(json[k]) === JSON.stringify(v), json[k], failures);
      }
    }
  }
  return failures;
}

let total = 0, failed = 0;
for (const file of readdirSync(CASES_DIR).filter((f) => f.endsWith(".json")).sort()) {
  const probes = JSON.parse(readFileSync(join(CASES_DIR, file), "utf8"));
  for (const probe of probes) {
    total++;
    let failures;
    try {
      failures = await runProbe(probe);
    } catch (err) {
      failures = [`request error: ${err.message}`];
    }
    if (failures.length) {
      failed++;
      console.log(`FAIL  ${file} :: ${probe.name}`);
      for (const f of failures) console.log(`      - ${f}`);
    } else {
      console.log(`ok    ${file} :: ${probe.name}`);
    }
  }
}

console.log(`\n${total - failed} passed, ${failed} failed, ${total} total`);
process.exit(failed ? 1 : 0);
