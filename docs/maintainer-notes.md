# Maintainer notes

## Observed CloudPanel architecture

The installed application contains a UFW abstraction and command classes. The relevant flow is conceptually:

```text
FirewallController::setUfwFirewallRules()
    -> UfwFirewall::reset()
    -> UfwFirewall::allowTcpRule(...)
    -> UfwFirewall::allowUdpRule(...)
    -> UfwFirewall::enable()
```

`UfwFirewall` delegates privileged operations to `App\\System\\CommandExecutor`, which executes `App\\System\\Command` instances through Symfony Process.

Existing examples include service restart/reload/status commands and filesystem/system commands. The proof of concept therefore introduces a dedicated `FirewallPostHookCommand` rather than using raw PHP process functions.

## Suggested upstream refinements

A production implementation could improve the proof of concept by:

- injecting or reusing CloudPanel's logger and logging per-hook failures;
- deciding whether the hook runner itself should be a Symfony service;
- validating hook ownership and permissions in addition to executable status;
- defining a stable naming/location convention for hook directories;
- documenting execution order and failure semantics;
- adding tests for no-directory, empty-directory, non-executable file, ordered execution, and failed-hook behavior.

## Why not put the hook in the low-level UFW Enable command?

The hook should represent completion of the whole CloudPanel firewall transaction, not simply execution of `ufw --force enable` in isolation. Keeping it at the controller/service orchestration level preserves that meaning.

## Why not hard-code Fail2Ban?

Hard-coding Fail2Ban would solve only one integration. A generic post-apply lifecycle hook also supports other tools such as CrowdSec and local administrator firewall reconciliation scripts without making CloudPanel depend on them.
