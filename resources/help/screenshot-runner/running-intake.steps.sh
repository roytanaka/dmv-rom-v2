# Step script for the "Running intake" article (#931, spec #914, ADR-0038 §8).
#
# Persona: the President (super-tier), who reaches the Applicants list through the Members tabs and
# edits every Applicant, as the Membership Chair does. The demo seed has no Membership Chair
# persona. Reseed first: the demo seed adds twelve Applicants, one per status and more.
# Do not save a status change while shooting: Accept creates a Member and writes Notices.
# Run: resources/help/screenshot-runner/run.sh running-intake

persona margaret.chen@dmv.test
start /officer/members/applicants

nav /officer/members/applicants 01                          # the All list: status, placement, mentor columns
nav /officer/members/applicants?status=accepted 02          # the Accepted filter
nav /officer/members/applicants/4 03                        # an Assigned-for-interview record: status, placement, mentor selects
