# Step script for the "Running intake" article (#931, #946, spec #914, ADR-0038 §8).
#
# Persona: the Membership Chair (Kenji Ferreira), who edits every Applicant. The Membership Group
# stewards `applicants`, so the Chair reaches the list from Officer Tools → Applicants. Kenji is a
# generated pool member, not a curated Persona: if the pool changes, check who chairs Membership
# and which Applicant is 4. Reseed first: the demo seed adds twelve Applicants, one per status and more.
# Do not save a status change while shooting: Accept creates a Member and writes Notices.
# Run: resources/help/screenshot-runner/run.sh running-intake

persona kenji.ferreira41@dmv.test
start /officer/members/applicants

nav /officer/members/applicants 01                          # the All list: status, dates, email, mentor columns
nav /officer/members/applicants?status=accepted 02          # the Accepted filter
nav /officer/members/applicants/4 03                        # an Assigned-for-interview record: status, placement, mentor selects
