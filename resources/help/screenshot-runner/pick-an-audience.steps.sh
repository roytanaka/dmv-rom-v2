# Step script for the "Pick an Audience" article (#527, ADR-0024 §5, §6).
#
# Persona: the Chair of Docents — an officer, so the menu offers the Group's roster and
# officer Audiences plus "Pick people…".
# Run: resources/help/screenshot-runner/run.sh pick-an-audience

persona oliver.bennett@dmv.test
start /groups/docents

act openEmailMenu 01            # the Email menu: each Audience with the number of Members it reaches
act openWholeGroupComposer
act removeFirstRecipient 02     # the Who step after one × : "Whole group, 1 removed"
nav /groups/docents/roster      # the Members tab: its Email control sits in the roster toolbar
act openHandPickComposer 03     # "Pick people…": an empty To field and the roster to tick from
