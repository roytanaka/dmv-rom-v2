# Step script for the "Find your Group's settings" article (#608, ADR-0027).
#
# Persona: the Scheduler of Docents — a schedule admin, so the Settings tab renders in the
# Group's tab strip. It shows only to a Member with a configuration right on the Group.
# Run: resources/help/screenshot-runner/run.sh group-settings

persona james.tremblay@dmv.test
start /groups/docents/settings

nav /groups/docents/settings 01     # the Settings tab, last in the strip, with its first cards
