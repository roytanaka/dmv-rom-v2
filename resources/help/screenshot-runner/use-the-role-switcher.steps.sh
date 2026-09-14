# Step script for the "Use the Role-switcher" article (#527, ADR-0009).
#
# Persona: the Support operator — the marker that shows the Role-switcher toolbar.
# Run: resources/help/screenshot-runner/run.sh use-the-role-switcher
#
# The Persona list is a Radix dropdown the runner cannot open; a developer captures the open
# list by hand. Non-production only, so shoot on local or staging.

persona operator@dmv.test
start /dashboard

nav /dashboard 01   # the dashboard: the Role-switcher button in the bottom-right corner
