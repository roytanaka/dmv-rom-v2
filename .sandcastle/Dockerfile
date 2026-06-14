# Sandbox image for the autonomous agent loop. Mirrors the toolchain in
# .github/workflows/ci.yml so the agent can run the full gate before it commits:
# PHP 8.4 + Composer + Pint + Pest, and Node 24 + pnpm + vue-tsc + Vite.
# No MariaDB — the test suite runs against in-memory SQLite (phpunit.xml).
FROM node:24-bookworm

# System dependencies + PHP 8.4 (via the sury repo) with the extensions Laravel
# 12 and the test suite need (sqlite3 for the in-memory test DB).
RUN apt-get update && apt-get install -y --no-install-recommends \
  git curl jq unzip ca-certificates gnupg lsb-release apt-transport-https \
  && curl -sSLo /tmp/sury-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb \
  && dpkg -i /tmp/sury-keyring.deb && rm /tmp/sury-keyring.deb \
  && echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ bookworm main" \
  > /etc/apt/sources.list.d/sury-php.list \
  && apt-get update && apt-get install -y --no-install-recommends \
  php8.4-cli php8.4-mbstring php8.4-xml php8.4-bcmath php8.4-curl \
  php8.4-sqlite3 php8.4-zip php8.4-intl \
  && rm -rf /var/lib/apt/lists/*

# Composer (system-wide).
RUN curl -sS https://getcomposer.org/installer \
  | php -- --install-dir=/usr/local/bin --filename=composer

# Enable pnpm via corepack, pinned to the repo's packageManager version.
RUN corepack enable && corepack prepare pnpm@11.0.9 --activate

# Install GitHub CLI
RUN curl -fsSL https://cli.github.com/packages/githubcli-archive-keyring.gpg \
  | dd of=/usr/share/keyrings/githubcli-archive-keyring.gpg \
  && echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/githubcli-archive-keyring.gpg] https://cli.github.com/packages stable main" \
  | tee /etc/apt/sources.list.d/github-cli.list > /dev/null \
  && apt-get update && apt-get install -y gh \
  && rm -rf /var/lib/apt/lists/*

# Build-args for UID/GID alignment: sandcastle docker build-image
# defaults these to the host user's UID/GID so image-built files
# and bind-mounted files share an owner without runtime chown.
ARG AGENT_UID=1000
ARG AGENT_GID=1000

# Rename the base image's "node" user to "agent" and align UID/GID.
RUN groupmod -o -g $AGENT_GID node && usermod -o -u $AGENT_UID -g $AGENT_GID -d /home/agent -m -l agent node
USER ${AGENT_UID}:${AGENT_GID}

# Install Claude Code CLI
RUN curl -fsSL https://claude.ai/install.sh | bash

# Add Claude to PATH
ENV PATH="/home/agent/.local/bin:$PATH"

WORKDIR /home/agent

# In worktree sandbox mode, Sandcastle bind-mounts the git worktree at /home/agent/workspace
# and overrides the working directory to /home/agent/workspace at container start.
# Structure your Dockerfile so that /home/agent/workspace can serve as the project root.
ENTRYPOINT ["sleep", "infinity"]
