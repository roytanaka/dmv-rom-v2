# Security Policy

## Supported versions

This project is in active pre-cutover development. Only the latest `main` branch is supported; older commits and pre-release branches do not receive security fixes.

## Reporting a vulnerability

Please report suspected vulnerabilities **privately** using GitHub's private vulnerability reporting:

1. Go to the [Security tab](https://github.com/roytanaka/dmv-rom-v2/security) of this repository.
2. Click **Report a vulnerability**.
3. Fill in a description, reproduction steps, and (if known) the affected files or routes.

Do **not** open a public issue or pull request for security reports — that would expose the vulnerability before a fix is available.

## What to expect

- **Acknowledgement** within 7 days.
- **Triage and severity assessment** within 14 days.
- **Fix timeline** depends on severity; we will keep you updated through the private advisory thread.
- Once a fix is released, we will credit reporters in the advisory unless you prefer to remain anonymous.

## Scope

In scope:

- The application code in this repository.
- Deployment scripts and CI/CD workflows in this repository.

Out of scope:

- The legacy PHP application (separate codebase, not maintained here).
- Third-party services (Stormweb hosting, GitHub Actions, etc.) — please report those to the respective vendor.
- Findings that require physical access to a user's device or social engineering.
