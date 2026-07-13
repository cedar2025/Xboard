# macOS V2BOX-Compatible Latency Test Design

## Context

The current macOS client asks the running sing-box Clash API to execute one
`/proxies/{node}/delay` request per node. sing-box 1.12.25 starts timing before
the outbound connection is established, performs a complete HTTPS request, and
then closes the connection. The result therefore represents a cold connection.

V2BOX uses a different Connection-test model. It creates an isolated test core,
performs two requests through the same HTTP transport, allows the second request
to reuse the established proxy and TLS connection, and returns the lower valid
duration. A local comparison using the same node and Google endpoint produced a
1163 ms first request and a 261 ms reused request; the latter matched the V2BOX
result of approximately 253 ms.

## Goals

- Make macOS node latency results use the same warm-connection measurement
  model as V2BOX Connection tests.
- Keep the official sing-box 1.12.25 ARM64 binary unchanged.
- Preserve a maximum of four concurrently tested nodes.
- Keep a five-second total deadline for each node, including warm-up and the
  measured request.
- Avoid changing the user's selected node, active tunnel, system routes, system
  proxy, or V2BOX state.
- Leave Android and Windows latency behavior unchanged.

## Non-Goals

- Do not add a new speed-test screen or expose connection-count controls.
- Do not implement TCP or ICMP test modes.
- Do not change subscriptions, server APIs, node formats, or account data.
- Do not patch or rebuild sing-box.
- Do not normalize results by dividing, subtracting, or otherwise fabricating a
  corrected latency.

## Architecture

### Isolated speed-test core

When a macOS latency run begins, the app creates a temporary sing-box config
derived from the already sanitized runtime config. The temporary config:

- contains the concrete remote node outbounds required for testing;
- has no TUN inbound and cannot modify system routes;
- disables the cache-file service to avoid conflicts with the active core;
- listens only on loopback;
- uses a dedicated Clash API port separate from `127.0.0.1:9090`;
- adds four hidden selector outbounds, one for each test worker;
- adds four loopback mixed inbounds, each routed to its matching selector;
- uses the same three compatibility environment variables as the main core.

The app runs `sing-box check` before starting the temporary core. Startup fails
closed: the existing active tunnel is left untouched and the UI receives a
readable speed-test error.

### Worker data flow

The node list is distributed across at most four workers. For each node, a
worker:

1. selects the node on its private hidden selector through the temporary Clash
   API;
2. creates a fresh HTTP client configured to use that worker's loopback mixed
   inbound;
3. starts a five-second deadline for the whole node test;
4. sends a first HTTPS request to the configured Google endpoint as warm-up;
5. sends a second request with the same HTTP client so the proxy connection and
   TLS session can be reused;
6. accepts HTTP 200 or 204 responses and returns the lower valid duration;
7. closes the HTTP client before the worker selects its next node.

The default endpoint remains `https://www.gstatic.com/generate_204`. A custom
user endpoint remains authoritative. Both requests use the same endpoint.

### Lifecycle and cleanup

Only one macOS latency session may run at a time. Cancellation, navigation,
timeout, process exit, or an exception triggers the same idempotent cleanup:

- close worker HTTP clients;
- terminate the temporary sing-box process;
- wait briefly and force-kill only that owned process if necessary;
- remove the generated config and transient log;
- release all reserved loopback ports.

The temporary process is tracked by its `Process` object and PID. Cleanup must
never use a broad process-name kill and must never stop the production core or
the privileged TUN helper.

## Interfaces

Introduce a macOS-only latency-session abstraction with these responsibilities:

- build and validate the isolated config;
- allocate the API and four worker ports;
- start the temporary core and wait for readiness;
- select worker outbounds;
- execute the two-request connection test;
- provide idempotent cleanup.

`NodeProvider` keeps orchestration and UI updates, but delegates macOS probes to
the session. The existing Clash `/delay` code remains the path for other
platforms. The `LatencyTestPolicy.v2boxConnection` profile continues to define
Google as the migrated default, four workers, and a five-second node deadline.

## Error Handling and Logging

- Config validation failure: mark all untested nodes as timeout and show
  `测速配置无效` when the action was user initiated.
- Temporary core startup failure: show `测速服务启动失败`; never affect VPN
  connection state.
- Selector update or HTTP failure: mark only that node as timeout and continue.
- Five-second deadline: cancel the current node request and continue with the
  worker's next node.
- Unexpected temporary core exit: stop scheduling new nodes, preserve completed
  results, mark remaining nodes as timeout, and clean up.

Logs record the node tag, endpoint, first duration, second duration, selected
duration, and total elapsed time. They must not include node credentials,
complete configurations, authorization values, or subscription URLs.

## Testing

Add tests before implementation for:

- generated configs contain no TUN inbound and have four isolated
  selector/inbound pairs;
- active selector and route configuration are not mutated;
- cache and port conflicts are removed;
- compatibility environment variables are present;
- two successful requests reuse one client and return the lower duration;
- a failed warm-up can still return a valid second result within the deadline;
- a failed second request can return a valid first result;
- the five-second deadline covers both requests;
- at most four nodes execute concurrently;
- custom endpoints are preserved;
- cancellation and process exit perform idempotent owned-process cleanup;
- Android and Windows retain the existing policy.

Acceptance verification includes `flutter analyze`, the full Flutter test suite,
native contract tests, `git diff --check`, real-config `sing-box check`, macOS
release build, signing verification, and installation on the current Mac. A
local test with V2BOX left connected should show the second reused request near
the V2BOX Connection value without changing V2BOX state.
