# Step script for the "The org-wide reports" article (#526, ADR-0025).
#
# Persona: the President — super-tier, so every DMV-wide hours report renders, with the
# report links across the top.
# Run: resources/help/screenshot-runner/run.sh the-org-wide-reports

persona margaret.chen@dmv.test
start /hours

nav /hours 01                       # My Hours: the Summary Visitor Interactions across the DMV link
nav /hours/visitor-interactions 02  # Summary Visitor Interactions: the report links across the top
nav /hours/committee-summary 03     # Summary Committee Statistics: scheduled hours by committee, totals below
nav /hours/ranked 04                # Active Members Ranked Hours: Members by total hours, most first
