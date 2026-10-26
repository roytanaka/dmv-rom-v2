# Step script for the "Administer Groups" article (#845, ADR-0036 §2-§5).
#
# Persona: the President — super-tier, so DMV Settings shows in Officer Tools and the
# Groups tree and structure forms open.
# Run: resources/help/screenshot-runner/run.sh administer-groups
#
# Nothing is saved: the Create dialog and the archive confirmation are shot open, never
# submitted, so the demo data stays as seeded.

persona margaret.chen@dmv.test
start /dashboard

nav /officer/settings 01                                  # the Groups tree: toolbar, kinds, capability slots, statuses, Set by officers / No page rows
act openCreateGroupDialog 02                              # the Create a Group dialog: name, slug, section, parent, kind
nav /officer/settings/groups/oral-history-project 03      # one Group's structure form, top: Identity and Listing
act showCapabilities 04                                   # the Capabilities card: switch rows with record counts
act showArchiveSection 05                                 # the Archive card and the save bar with the last-changed line
act openArchiveConfirm 06                                 # the Archive confirmation dialog
