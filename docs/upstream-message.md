# Suggested message to CloudPanel maintainers

## Subject

Proposal: generic post-firewall-apply hooks for external firewall integrations

## Message

CloudPanel currently rebuilds its UFW configuration by resetting UFW, recreating CloudPanel-managed rules, and enabling UFW again.

That workflow can remove firewall state maintained by external tools. I reproduced this with Fail2Ban: CloudPanel's firewall apply succeeds, but Fail2Ban-managed firewall state is lost until Fail2Ban recreates it.

I built a small proof of concept for a generic extension point rather than a Fail2Ban-specific workaround.

### Proposed behavior

After CloudPanel successfully completes its firewall rebuild, execute administrator-provided executable files in lexical order from a directory such as:

```text
/etc/cloudpanel/hooks/firewall-post.d/
```

For example:

```text
10-fail2ban
20-crowdsec
90-local-firewall-state
```

The implementation follows CloudPanel's existing `App\\System\\Command` / `CommandExecutor` pattern instead of calling PHP `exec()` directly.

Fail2Ban is only an example consumer. A hook can restart or reconcile Fail2Ban after the UFW rebuild, but CloudPanel itself does not need any Fail2Ban-specific code.

I have included:

- a proof-of-concept command class,
- a post-apply hook runner,
- the intended integration point after `UfwFirewall::enable()`,
- a harmless logger hook for verification,
- a Fail2Ban example hook,
- reproduction and before/after test instructions.

I also intentionally treat post-hook failure as non-fatal to the already-successful firewall apply; an administrator's broken custom hook should be logged, not cause CloudPanel to misreport or undo the firewall change.

Would you be interested in incorporating this type of hook, and if so, what contribution format would you prefer for the actual source tree?

Repository: https://github.com/GlitchMode/cloudpanel-firewall-post-hooks
