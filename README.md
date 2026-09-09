# CloudPanel post-firewall hook proof of concept

This repository proposes a small extension point for CloudPanel's firewall workflow: after CloudPanel successfully rebuilds and enables UFW, it runs administrator-provided executable hooks from a dedicated directory.

## Problem

CloudPanel's firewall workflow rebuilds UFW state by resetting UFW, recreating CloudPanel-managed rules, and enabling UFW again. External tools that also maintain firewall state can lose their rules during that rebuild.

A concrete example is Fail2Ban. A Fail2Ban jail may have active bans represented by firewall rules; after CloudPanel rebuilds UFW, those rules can disappear until Fail2Ban recreates them.

The goal of this proposal is **not** to add Fail2Ban-specific behavior to CloudPanel. The goal is to provide a generic post-apply hook that any external integration can use.

## Proposed interface

After a successful CloudPanel firewall apply, execute executable files in lexical order from:

```text
/etc/cloudpanel/hooks/firewall-post.d/
```

Example:

```text
/etc/cloudpanel/hooks/firewall-post.d/
  10-fail2ban
  20-crowdsec
  90-local-firewall-state
```

Non-executable files are ignored.

## Why this location in the lifecycle?

The hook should run after the complete CloudPanel firewall rebuild, not inside the low-level UFW `enable` command. Semantically, the hook means:

> CloudPanel has finished applying its firewall configuration.

That makes it useful for any integration that needs to restore or reconcile external firewall state.

## Why use CloudPanel's command architecture?

CloudPanel already wraps privileged system operations in classes derived from `App\\System\\Command` and executes them through `App\\System\\CommandExecutor` / Symfony Process. This proof of concept follows the same pattern instead of calling PHP `exec()` directly.

The proposed classes are:

```text
src/System/Command/FirewallPostHookCommand.php
src/Firewall/PostApplyHookRunner.php
```

The firewall controller then calls the runner after `UfwFirewall::enable()` succeeds.

## Proof-of-concept files

- [`src/System/Command/FirewallPostHookCommand.php`](src/System/Command/FirewallPostHookCommand.php) — wraps execution of one hook file.
- [`src/Firewall/PostApplyHookRunner.php`](src/Firewall/PostApplyHookRunner.php) — discovers and executes hooks in lexical order.
- [`patches/FirewallController.example.patch`](patches/FirewallController.example.patch) — shows the intended integration point.
- [`examples/10-fail2ban`](examples/10-fail2ban) — Fail2Ban example consumer.
- [`examples/10-proof-hook`](examples/10-proof-hook) — harmless proof hook using `logger`.
- [`docs/reproduction.md`](docs/reproduction.md) — before/after reproduction procedure.
- [`docs/upstream-message.md`](docs/upstream-message.md) — ready-to-send proposal for CloudPanel maintainers.

## Important implementation notes

### Hook failures should not roll back a successful firewall apply

The firewall may already be successfully changed before a post-hook runs. A broken administrator hook should therefore be logged, but should not make CloudPanel pretend the firewall apply itself failed or attempt to undo it.

The proof-of-concept runner catches per-hook exceptions. In an upstream implementation, that catch should use CloudPanel's existing logger.

### Security

Only executable regular files in the dedicated root-owned hook directory should run. Administrators should control ownership and permissions, for example:

```bash
sudo install -d -o root -g root -m 0755 /etc/cloudpanel/hooks/firewall-post.d
```

Individual hooks should also be root-owned and non-writable by untrusted users.

The command wrapper uses `escapeshellarg()` for the hook path.

### Naming

`/etc/cloudpanel/hooks/firewall-post.d/` is a proposal. CloudPanel maintainers may prefer another location or naming scheme. The important part is a stable post-firewall-apply extension point.

## Fail2Ban example

Install the example hook:

```bash
sudo install -d -o root -g root -m 0755 /etc/cloudpanel/hooks/firewall-post.d
sudo install -o root -g root -m 0755 examples/10-fail2ban \
  /etc/cloudpanel/hooks/firewall-post.d/10-fail2ban
```

The hook only restarts Fail2Ban if it is already active:

```sh
#!/bin/sh

if systemctl is-active --quiet fail2ban; then
    systemctl restart fail2ban
fi
```

Fail2Ban is intentionally only an example consumer. CloudPanel itself does not need to know about Fail2Ban.

## Harmless proof hook

Before testing Fail2Ban, the generic mechanism can be demonstrated with:

```bash
sudo install -o root -g root -m 0755 examples/10-proof-hook \
  /etc/cloudpanel/hooks/firewall-post.d/10-proof-hook
```

Change a firewall rule in CloudPanel, then verify:

```bash
journalctl -t cloudpanel-firewall-hook -n 20
```

## Status

This is a proof of concept derived from inspection of an installed CloudPanel application. The distributed application files are transformed/obfuscated, so the included controller patch is intentionally illustrative rather than a byte-for-byte patch against a particular release.

The proposal is meant to show the integration point and architecture clearly enough for CloudPanel maintainers to implement it in their source tree.
