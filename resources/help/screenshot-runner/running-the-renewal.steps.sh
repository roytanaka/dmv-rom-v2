# Step script for the "Running the renewal" article (#937, spec #915, ADR-0038 §9).
#
# Persona: the Records steward (Priya Nair), a member of the Records Group, which stewards member
# administration. Only member-administration authority reaches Renewal settings. Reseed first: the
# demo seed turns on this year's Renewal with past send dates and placeholder texts.
# The script never selects Save: it would change the demo row.
# Run: resources/help/screenshot-runner/run.sh running-the-renewal

persona priya.nair@dmv.test
start /officer/members/renewal

nav /officer/members/renewal 01   # the page: counts, show flag, year, both series, attachment, addresses
nav /officer/members 02           # the Members list header with the Renewal settings button
