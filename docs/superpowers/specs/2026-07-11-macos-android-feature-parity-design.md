# macOS and Android Feature Parity Design

## Goal

Make the macOS client use the same product content, feature entry points,
ordering, labels, and state behavior as the current Android client while
retaining only the platform differences required for a desktop window and TUN
authorization.

## Shared product experience

- Android and macOS use the same dashboard, subscription, profile, node,
  update, authentication, and account components.
- User summary cards do not display an avatar or generated email initial.
- The profile page has the same business entries on both platforms. macOS does
  not add an About or Diagnostics page; version handling remains a direct
  `检查更新` action.
- macOS may keep its desktop sidebar, wider constraints, tray behavior, login
  startup control, manual DMG update instructions, and TUN helper approval.
  These platform controls must not change shared business content.
- Android-only share mechanisms remain platform-specific only where the target
  operating system cannot provide the same interaction.

## Startup and update flow

1. Initialize logging and Flutter bindings.
2. Resolve the dynamic API domain before providers or business screens start.
3. Construct one `DomainResolver` and inject it into the shared `DioClient` and
   `AppUpdateService`; all startup, login, subscription, and update traffic uses
   the same selected domain and cached failover state.
4. Show the application window and normal routes after domain initialization.
5. On the first authenticated main screen frame, send the heartbeat and check
   for updates automatically. Use the already-resolved domain; keep forced and
   optional update behavior unchanged.
6. If control-plane resolution or every health probe fails, use the resolver's
   cached or built-in fallback instead of blocking application startup.

## Component boundaries

- `AppBootstrap` owns startup domain resolution and the preconstructed shared
  network dependencies.
- `DioClient` remains responsible for request authentication and safe domain
  failover, but does not start the first resolution implicitly.
- `AppUpdateProvider` accepts an injected update service so startup and update
  checks do not create an independent resolver.
- Shared dashboard/profile widgets contain no macOS-specific product content.
  macOS setup guidance stays in the connect-time TUN flow.

## Verification

- Unit tests prove startup resolution happens before the app receives its
  clients and that both services share one resolver.
- Widget/source-contract tests prove dashboard and profile no longer render an
  avatar or About/Diagnostics entry and still render `检查更新`.
- Existing domain failover, update, provider, Android, Windows, and macOS tests
  remain passing.
- A macOS release build must still produce one arm64 DMG with strict ad-hoc
  signature verification.
