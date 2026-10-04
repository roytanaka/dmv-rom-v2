# Step script for the "News" overview article (#524, ADR-0025).
#
# Persona: a Full-standing Docents member — an ordinary reader of the feed.
# Run: resources/help/screenshot-runner/run.sh news

persona amara.abara@dmv.test
start /news

nav /news 01                # the News feed: items with title, group, and date
