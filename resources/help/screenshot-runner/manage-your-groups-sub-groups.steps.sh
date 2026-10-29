# Step script for the "Manage your Group's sub-groups" article (#889, PRD #881, ADR-0036 §11).
#
# Persona: the Chair of Docents — a parent officer, so the Settings tab shows the Sub-groups
# card and DMV Settings opens scoped to the Docents subtree. The sub-group in the shots is
# Docents › Sections, chaired by its own Persona (rosa.delgado@dmv.test), who shows on its
# roster. Rosa holds no office above a Group, so she cannot shoot the DMV Settings steps.
# Run: resources/help/screenshot-runner/run.sh manage-your-groups-sub-groups
#
# Nothing is saved: the Create dialog and the archive confirmation are shot open, never
# submitted, so the demo data stays as seeded.

persona oliver.bennett@dmv.test
start /groups/docents/settings

act showSubGroupsCard 01                                  # the Settings tab's Sub-groups card with Manage sub-groups
nav /officer/settings 02                                  # the scoped tree: Docents (Your Group), Sections, archived cohorts; Create a sub-group
act openCreateSubGroupDialog 03                           # the Create a sub-group dialog: name, parent Docents, the Chair search
nav /officer/settings/groups/docents-sections 04          # Sections' structure form: Identity (name, parent), Archive, save bar, Open the Group page
act openArchiveConfirm 05                                 # the Archive confirmation dialog
nav /groups/docents-sections/roster 06                    # Sections' Members tab as the parent Chair: Add member, Rosa as Chair
