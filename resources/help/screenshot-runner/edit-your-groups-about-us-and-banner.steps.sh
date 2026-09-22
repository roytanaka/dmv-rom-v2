# Step script for the "Edit your Group's About Us and banner" article (#563, ADR-0025).
#
# Persona: the Chair of Docents — she edits her Group, so the About Us Edit control and
# the Change banner button both render on the Overview tab.
# Run: resources/help/screenshot-runner/run.sh edit-your-groups-about-us-and-banner

persona oliver.bennett@dmv.test
start /groups/docents

nav /groups/docents 01      # the Overview: the About Us card with Edit, and Change banner on the banner
act openAboutEditForm 02    # the About Us edit form: the text box, Save, and Cancel
nav /groups/docents         # reload to close the edit form
act openBannerPicker 03     # the Choose a banner dialog: the six banner options
