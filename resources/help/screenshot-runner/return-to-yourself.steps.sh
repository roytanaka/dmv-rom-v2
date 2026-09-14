# Step script for the "Return to yourself after impersonating" article (#527, ADR-0009).
#
# Persona: the Support operator — the marker that shows the Role-switcher toolbar.
# Run: resources/help/screenshot-runner/run.sh return-to-yourself
#
# The active toolbar (the Persona name with Switch and Stop) shows only during an
# impersonation session, which the runner cannot start; a developer becomes a Persona first,
# then captures the Stop and Switch shots by hand. Non-production only.

persona operator@dmv.test
start /dashboard

nav /dashboard 01   # the dashboard: the Role-switcher toolbar in the bottom-right corner
