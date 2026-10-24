# Security policy

## Supported versions

Security fixes go into the latest 1.x release. Older versions are not patched, so update to the newest release first.

## Reporting a vulnerability

Please do not open a public issue for a security problem.

Use GitHub's private reporting instead: on the repository page, open the **Security** tab and select **Report a vulnerability**. Only the maintainer can read the report.

If you don't see that option, open an issue titled "Security contact request" and leave out any details. The maintainer will reply with a private way to send them.

A useful report has:

- the plugin version, and the WordPress, WooCommerce and PHP versions;
- the steps to reproduce the problem;
- what an attacker gains: for example, which Blacklist entries or Buyer details they can read, change or bypass.

This is a one-person project, so a reply can take a few days. You will hear back once the report has been read, and again when a fix is released. If you want credit in the release notes, say so.

## What is in scope

Problems in the code of this repository, such as:

- a way to complete checkout that an active Blacklist entry should refuse, in the classic checkout or the Checkout Block;
- a REST route or admin action that works without the right capability or nonce;
- SQL injection, cross-site scripting or cross-site request forgery;
- a way to read Buyer emails, addresses or other stored data without permission.

## What is not in scope

- Problems in WordPress, WooCommerce or another plugin. Report those to their own projects.
- Behavior the README lists under **Rules to be aware of**. For example, a Buyer who uses a different email address or a new address is not matched, because matching is literal and only covers what the entry names.
- Administrator accounts that are already compromised.
