# Reproduction and verification

This document gives a minimal before/after demonstration of the problem and the proposed hook mechanism.

## 1. Confirm Fail2Ban is active

```bash
sudo systemctl is-active fail2ban
sudo fail2ban-client status
```

Pick an active jail, for example `sshd`:

```bash
sudo fail2ban-client status sshd
```

If the jail has no current bans, use a controlled test environment and create a temporary test ban according to your local Fail2Ban setup. Do not lock out the SSH address you are currently using.

## 2. Capture firewall state before a CloudPanel change

Depending on the host and Fail2Ban action backend, inspect the relevant state. Useful commands include:

```bash
sudo ufw status numbered
sudo nft list ruleset
sudo iptables-save
```

Save the output if you want a clean before/after comparison:

```bash
sudo nft list ruleset > /tmp/firewall-before.txt
```

## 3. Trigger a CloudPanel firewall rebuild

In CloudPanel, add, edit, or remove a firewall rule and save the change.

The observed CloudPanel flow is conceptually:

```text
UFW reset
recreate CloudPanel-managed rules
UFW enable
```

## 4. Verify the external firewall state was affected

Check the same Fail2Ban jail and firewall state again:

```bash
sudo fail2ban-client status sshd
sudo nft list ruleset > /tmp/firewall-after-cloudpanel.txt
```

Compare:

```bash
diff -u /tmp/firewall-before.txt /tmp/firewall-after-cloudpanel.txt
```

The exact firewall representation depends on Fail2Ban's configured `banaction` and the system's UFW/netfilter backend.

## 5. Verify the generic hook mechanism first

Install the harmless proof hook:

```bash
sudo install -d -o root -g root -m 0755 /etc/cloudpanel/hooks/firewall-post.d
sudo install -o root -g root -m 0755 examples/10-proof-hook \
  /etc/cloudpanel/hooks/firewall-post.d/10-proof-hook
```

Trigger another CloudPanel firewall change and then run:

```bash
journalctl -t cloudpanel-firewall-hook -n 20
```

Expected result:

```text
post-firewall hook executed
```

## 6. Verify the Fail2Ban example

Replace the proof hook with the Fail2Ban hook:

```bash
sudo rm -f /etc/cloudpanel/hooks/firewall-post.d/10-proof-hook
sudo install -o root -g root -m 0755 examples/10-fail2ban \
  /etc/cloudpanel/hooks/firewall-post.d/10-fail2ban
```

Trigger another CloudPanel firewall change.

Then verify:

```bash
sudo systemctl status fail2ban --no-pager
sudo fail2ban-client status
sudo fail2ban-client status sshd
```

The important proof is not merely that Fail2Ban restarted; it is that the external firewall state needed by Fail2Ban is restored after CloudPanel's firewall rebuild.

## 7. Optional service-log proof

Record Fail2Ban's restart around the test:

```bash
journalctl -u fail2ban --since '-5 minutes' --no-pager
```

This gives maintainers a concise timeline:

```text
CloudPanel firewall change
-> UFW rebuilt
-> post hook executed
-> Fail2Ban restarted
-> Fail2Ban firewall state restored
```
