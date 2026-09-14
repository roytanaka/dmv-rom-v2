# Step script for the "The org-wide reports" article (#526, ADR-0025).
#
# Persona: the President — super-tier, so every DMV-wide hours report renders.
# Run: resources/help/screenshot-runner/run.sh the-org-wide-reports

persona margaret.chen@dmv.test
start /hours/committee-summary

nav /hours/committee-summary 01     # Summary Committee Statistics: scheduled hours by committee, totals below
nav /hours/visitor-interactions 02  # Summary Visitor Interactions: the visitor count each Group recorded
nav /hours/ranked 03                # Active Members Ranked Hours: Members by total hours, most first
