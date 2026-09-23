# Step script for the "Support" overview article (#623, ADR-0025 §5, ADR-0009).
#
# Persona: the Support operator — the marker that shows the Role-switcher toolbar.
# Run: resources/help/screenshot-runner/run.sh support
#
# Non-production only, so shoot on local or staging.

persona operator@dmv.test
start /dashboard

nav /dashboard 01        # the dashboard: the Role-switcher button in the bottom-right corner
